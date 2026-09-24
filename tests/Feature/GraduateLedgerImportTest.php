<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\Student;
use App\Models\UACS;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GraduateLedgerImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed master courses and terms used across tests
        Course::firstOrCreate(
            ['course_code' => 'MA Mathematics'],
            ['course_desc' => 'Master of Arts in Mathematics', 'course_college' => 'Graduate School'],
        );
        Course::firstOrCreate(
            ['course_code' => 'MSIT'],
            ['course_desc' => 'Master of Science in Information Technology', 'course_college' => 'Graduate School'],
        );
        Course::firstOrCreate(
            ['course_code' => 'MBA'],
            ['course_desc' => 'Master in Business Administration', 'course_college' => 'Graduate School'],
        );
        AcademicTerm::firstOrCreate([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
        ]);
        AcademicTerm::firstOrCreate([
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
        ]);
    }

    public function test_user_can_import_excel_transactions_into_the_ledger(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'ledger-import').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['student_name', 'course', 'school_year', 'semester_short', 'semester', 'units', 'transaction_date', 'reference_or_jev_number', 'particulars', 'tuition_per_unit_or_misc', 'ar_payment', 'amount', 'remarks', 'input_by'],
            ['Cruz, Juan Dela', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', 9, '2026-07-22', 'OR-001', 'Tuition', '150.00', 'AR', '1350.00', 'Imported', 'Admin'],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($file, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $student = Student::query()
            ->where('last_name', 'Cruz')
            ->where('first_name', 'Juan Dela')
            ->first();
        $this->assertNotNull($student);
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'reference_number' => 'OR-001',
            'amount' => '1350.00',
            'input_by' => $user->id,
        ]);

        unlink($file);
    }

    public function test_csv_import_uses_authenticated_user_and_ignores_spreadsheet_input_by_values(): void
    {
        $user = User::factory()->staff()->create();
        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Initials, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-INITIALS,Tuition,0,AR,100,, MBC ',
            '"Blank, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-BLANK,Tuition,0,AR,100,,',
            '"Numeric, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-NUMERIC,Tuition,0,AR,100,,123',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-INITIALS',
            'input_by' => $user->id,
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-BLANK',
            'input_by' => $user->id,
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-NUMERIC',
            'input_by' => $user->id,
        ]);
    }

    public function test_import_uses_amount_signs_reports_formatting_warnings_and_ignores_other_sheets(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'ledger-import-rules').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Graduate School');
        $sheet->fromArray([
            ['student_name', 'course', 'school_year', 'semester_short', 'semester', 'units', 'transaction_date', 'reference_or_jev_number', 'particulars', 'tuition_per_unit_or_misc', 'ar_payment', 'amount', 'remarks', 'input_by'],
            ['Negative Blank', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', null, '2026-07-22', 70000, 'Payment', null, '', -100, null, 'Admin'],
            ['Negative AR', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', null, '2026-07-22', 'NEG-AR', 'Payment', null, 'AR', -200, null, 'Admin'],
            ['Positive Payment', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', null, '2026-07-22', 'POS-PAY', 'Payment', null, 'PAYMENT', 300, null, 'Admin'],
            ['Positive AR', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', null, '2026-07-22', 'POS-AR', 'Assessment', null, '', 400, null, 'Admin'],
        ], null, 'A1');

        $ignoredSheet = $spreadsheet->createSheet();
        $ignoredSheet->setTitle('Notes');
        $ignoredSheet->fromArray([
            ['student_name', 'course', 'school_year', 'semester_short', 'semester', 'units', 'transaction_date', 'reference_or_jev_number', 'particulars', 'tuition_per_unit_or_misc', 'ar_payment', 'amount', 'remarks', 'input_by'],
            ['Ignored Student', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', null, '2026-07-22', 'IGNORED', 'Assessment', null, 'AR', 999, null, 'Admin'],
        ], null, 'A1');

        (new Xlsx($spreadsheet))->save($file);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($file, 'import-rules.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response
            ->assertRedirect('/graduate-ledger')
            ->assertSessionHas('success', function (string $message): bool {
                return str_contains($message, '1 negative amount(s) with a blank or unknown type')
                    && str_contains($message, '1 negative amount(s) labeled AR')
                    && str_contains($message, '1 positive amount(s) labeled PAYMENT');
            });

        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => '70000', 'entry_type' => 'payment', 'amount' => '100.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'NEG-AR', 'entry_type' => 'ar', 'amount' => '200.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'POS-PAY', 'entry_type' => 'payment', 'amount' => '300.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'POS-AR', 'entry_type' => 'ar', 'amount' => '400.00']);
        $this->assertDatabaseMissing('graduate_ledgers', ['reference_number' => 'IGNORED']);

        unlink($file);
    }

    public function test_import_normalizes_course_spelling_variants_to_canonical_course_without_creating_new_courses(): void
    {
        $user = User::factory()->staff()->create();
        $initialCourseCount = Course::count();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Student One, A",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-1,Tuition,0,AR,100,,',
            '"Student Two, B",M.S. - Math,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-2,Tuition,0,AR,100,,',
            '"Student Three, C",MSMATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-3,Tuition,0,AR,100,,',
            '"Student Four, D",MS IT,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-4,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('variants.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // No new course records should have been created
        $this->assertSame($initialCourseCount, Course::count());

        $mathCourse = Course::where('course_code', 'MA Mathematics')->firstOrFail();
        $msitCourse = Course::where('course_code', 'MSIT')->firstOrFail();

        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-1', 'course_id' => $mathCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-2', 'course_id' => $mathCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-3', 'course_id' => $mathCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-4', 'course_id' => $msitCourse->id]);
    }

    public function test_import_auto_creates_unrecognized_course_when_no_preset_is_supplied(): void
    {
        $user = User::factory()->staff()->create();
        $initialCourseCount = Course::count();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Unknown Course Student, U",MS Nursing,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-SKIP-1,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('skip.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // A course row is auto-created using the normalized code; the raw
        // string is kept as course_desc since no canonical title is known
        $this->assertSame($initialCourseCount + 1, Course::count());
        $this->assertDatabaseHas('courses', [
            'course_code' => 'MS NURSING',
            'course_desc' => 'MS Nursing',
            'course_college' => 'Graduate School',
        ]);

        $course = Course::where('course_code', 'MS NURSING')->firstOrFail();
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-SKIP-1',
            'course_id' => $course->id,
        ]);
    }

    public function test_import_auto_creates_canonical_course_code_not_yet_in_master_list(): void
    {
        $user = User::factory()->staff()->create();

        // 'PhD. Mathematics' is a known canonical code but NOT seeded in setUp
        $this->assertDatabaseMissing('courses', ['course_code' => 'PhD. Mathematics']);

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"New Program Student, N",PHD-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-CANON-NEW,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('canon.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Recognized canonical code is auto-created with the proper course_code
        // and the official program description
        $this->assertDatabaseHas('courses', [
            'course_code' => 'PhD. Mathematics',
            'course_desc' => 'Doctor of Philosophy in Mathematics',
            'course_college' => 'Graduate School',
        ]);
        $course = Course::where('course_code', 'PhD. Mathematics')->firstOrFail();
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-CANON-NEW',
            'course_id' => $course->id,
        ]);
    }

    public function test_import_preserves_exact_raw_course_code_when_given_lone_ms(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Lone MS Student, M",MS,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-MS-EXACT,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('ms.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Course is stored exactly as "MS"
        $this->assertDatabaseHas('courses', [
            'course_code' => 'MS',
            'course_college' => 'Graduate School',
        ]);
        $course = Course::where('course_code', 'MS')->firstOrFail();
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-MS-EXACT',
            'course_id' => $course->id,
        ]);
    }

    public function test_import_uses_preset_course_for_blanks_but_auto_creates_unrecognized_courses(): void
    {
        $user = User::factory()->staff()->create();
        $mbaCourse = Course::where('course_code', 'MBA')->firstOrFail();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Blank Course Student, B",,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-PRESET-1,Tuition,0,AR,100,,',
            '"Unrecognized Course Student, U",UNKNOWN_CODE,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-PRESET-2,Tuition,0,AR,100,,',
            '"Explicit Course Student, E",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-PRESET-3,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('preset.csv', $csv),
            'preset_course_id' => $mbaCourse->id,
        ]);

        $response->assertRedirect('/graduate-ledger');

        $mathCourse = Course::where('course_code', 'MA Mathematics')->firstOrFail();
        // Unrecognized value is stored under its normalized code with the raw
        // string preserved as the description so variants never create dups
        $autoCreated = Course::where('course_code', 'UNKNOWN CODE')->firstOrFail();
        $this->assertSame('UNKNOWN_CODE', $autoCreated->course_desc);

        // Blank course uses the MBA preset
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PRESET-1', 'course_id' => $mbaCourse->id]);
        // Unrecognized value is auto-created (preset is only for blanks)
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PRESET-2', 'course_id' => $autoCreated->id]);
        // Explicit valid row still uses its own resolved course
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PRESET-3', 'course_id' => $mathCourse->id]);
    }

    public function test_import_falls_back_to_unassigned_term_for_malformed_school_year_or_semester(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Bad Year Student, B",MS-MATH,2025-2028,1st Sem.,First Semester,,2026-07-22,REF-BAD-YEAR,Tuition,0,AR,100,,',
            '"Garbage Sem Student, G",MS-MATH,2025-2026,XYZ,FOOBAR_SEMESTER,,2026-07-22,REF-BAD-SEM,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('badterms.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $unassignedTerm = AcademicTerm::where('school_year', 'Unassigned')->where('semester', 'First Semester')->firstOrFail();

        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-BAD-YEAR',
            'academic_term_id' => $unassignedTerm->id,
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-BAD-SEM',
            'academic_term_id' => $unassignedTerm->id,
        ]);
    }

    public function test_import_falls_back_to_unassigned_course_when_blank_and_no_preset(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"No Course Student, N",,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-NO-COURSE,Tuition,0,AR,6200,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('no_course.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $unassignedCourse = Course::where('course_code', 'UNASSIGNED')->firstOrFail();

        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-NO-COURSE',
            'course_id' => $unassignedCourse->id,
            'amount' => '6200.00',
        ]);
    }

    public function test_reimporting_same_file_skips_duplicates(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Duplicate Test Student, D",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-DUP-1,Tuition,0,AR,1500,,',
        ]);

        // First import
        $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('dup1.csv', $csv),
        ]);

        $this->assertSame(1, GraduateLedger::where('reference_number', 'REF-DUP-1')->count());

        // Re-import the exact same file
        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('dup2.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');
        $this->assertSame(1, GraduateLedger::where('reference_number', 'REF-DUP-1')->count());
    }

    public function test_import_uses_preset_academic_term_fallback(): void
    {
        $user = User::factory()->staff()->create();
        $term2 = AcademicTerm::where('school_year', '2025-2026')->where('semester', 'Second Semester')->firstOrFail();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Blank Term Student, B",MS-MATH,,,,2026-07-22,REF-PRESET-TERM-1,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('preset_term.csv', $csv),
            'preset_academic_term_id' => $term2->id,
        ]);

        $response->assertRedirect('/graduate-ledger');

        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-PRESET-TERM-1',
            'academic_term_id' => $term2->id,
        ]);
    }

    public function test_import_calculates_excel_formulas_for_rate_and_amount_and_handles_formula_errors_gracefully(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'ledger-formula-test').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['student_name', 'course', 'school_year', 'semester_short', 'semester', 'units', 'transaction_date', 'reference_or_jev_number', 'particulars', 'tuition_per_unit_or_misc', 'ar_payment', 'amount', 'remarks', 'input_by'],
            ['Formula Student One', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', 6, '2026-07-22', 'FORMULA-VALID', 'Tuition', '=100+50', 'AR', '=F2*J2', 'Formula Test', 'Admin'],
            ['Formula Student Two', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', 3, '2026-07-22', 'FORMULA-ERROR', 'Tuition', '150.00', 'AR', '=#DIV/0!', 'Error Formula', 'Admin'],
        ], null, 'A1');

        (new Xlsx($spreadsheet))->save($file);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($file, 'formula-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Valid formula should evaluate =100+50 to 150 rate and =6*150 to 900 amount
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'FORMULA-VALID',
            'rate' => '150.00',
            'amount' => '900.00',
        ]);

        // Error formula should not crash and be cleaned to 0.00
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'FORMULA-ERROR',
            'amount' => '0.00',
        ]);

        unlink($file);
    }

    // ─── Cashier Order of Payment → Graduate Ledger Auto-Posting ─────────────

    public function test_cashier_payment_updates_staff_input_and_auto_posts_to_matching_graduate_student_ledger(): void
    {
        $cashier = User::factory()->cashier()->create();

        // 1. Setup Student with existing ledger entry (gives course & term context)
        $student = Student::create([
            'student_number' => '202600001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@example.com',
        ]);
        $course = Course::where('course_code', 'MBA')->firstOrFail();
        $term = AcademicTerm::where('school_year', '2025-2026')->where('semester', 'First Semester')->firstOrFail();

        GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-08-01',
            'reference_number' => 'AR-ORIGINAL-001',
            'particulars' => 'Initial Assessment',
            'amount' => 5000.00,
            'status' => 'posted',
        ]);

        // 2. Setup Order of Payment for Maria Santos
        $membership = Membership::firstOrCreate(
            ['member_code' => 'STUDENT'],
            ['member_desc' => 'Graduate Student'],
        );
        $paymentOption = PaymentDetailOption::firstOrCreate(
            ['payment_desc' => 'Tuition Fee Payment'],
        );
        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main Account', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees'],
        );

        $formInput = FormInput::create([
            'reference_number' => 'OP-2026-0001',
            'email' => 'maria.santos@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Maria',
            'lastname_or_agency' => 'Santos',
            'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 2500.00,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
        ]);

        $staffInput = StaffInput::create([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-08-15',
            'uacs_id' => $uacs->id,
            'status' => 'processed',
        ]);

        // 3. Cashier saves OR number and marks as paid
        $response = $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'or_no' => '9999-0001',
            'or_date' => '2026-08-16',
        ]);

        $response->assertRedirect("/cashier/requests/{$staffInput->id}");
        $response->assertSessionHas('success', function (string $msg): bool {
            return str_contains($msg, 'Paid') && str_contains($msg, 'Posted to the graduate ledger');
        });

        // 4. Verify StaffInput is marked paid with OR details
        $this->assertDatabaseHas('staff_inputs', [
            'id' => $staffInput->id,
            'status' => 'paid',
            'or_no' => '9999-0001',
        ]);

        // 5. Verify Graduate Ledger received the payment row
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'reference_number' => '9999-0001',
            'amount' => '2500.00',
            'particulars' => 'Tuition Fee Payment',
            'remarks' => 'OR from OP OP-2026-0001',
            'status' => 'posted',
        ]);
    }

    public function test_cashier_payment_is_idempotent_and_does_not_duplicate_ledger_rows_on_resave(): void
    {
        $cashier = User::factory()->cashier()->create();

        $student = Student::create([
            'student_number' => '202600002',
            'first_name' => 'Pedro',
            'last_name' => 'Penduko',
        ]);
        $course = Course::where('course_code', 'MBA')->firstOrFail();
        $term = AcademicTerm::where('school_year', '2025-2026')->where('semester', 'First Semester')->firstOrFail();

        GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-08-01',
            'reference_number' => 'AR-ORIGINAL-002',
            'particulars' => 'Assessment',
            'amount' => 3000.00,
            'status' => 'posted',
        ]);

        $membership = Membership::firstOrCreate(['member_code' => 'STUDENT'], ['member_desc' => 'Graduate Student']);
        $paymentOption = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Tuition']);
        $bank = BankAccountInfo::firstOrCreate(['account_num' => '123456789'], ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01']);
        $uacs = UACS::firstOrCreate(['object_code' => '4020101000'], ['account_title' => 'Tuition Fees']);

        $formInput = FormInput::create([
            'reference_number' => 'OP-2026-0002',
            'email' => 'pedro@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Pedro',
            'lastname_or_agency' => 'Penduko',
            'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 1500.00,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
        ]);

        $staffInput = StaffInput::create([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-08-15',
            'uacs_id' => $uacs->id,
            'status' => 'processed',
        ]);

        // First save
        $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'or_no' => '8888-0001',
            'or_date' => '2026-08-16',
        ]);

        $this->assertSame(1, GraduateLedger::where('reference_number', '8888-0001')->count());

        // Second save (correction with same OR)
        $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'or_no' => '8888-0001',
            'or_date' => '2026-08-17',
        ]);

        // Count must still be exactly 1
        $this->assertSame(1, GraduateLedger::where('reference_number', '8888-0001')->count());
    }

    public function test_cashier_payment_does_not_post_when_no_matching_student_exists(): void
    {
        $cashier = User::factory()->cashier()->create();

        $membership = Membership::firstOrCreate(['member_code' => 'OTHER'], ['member_desc' => 'General Public']);
        $paymentOption = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Transcript of Records']);
        $bank = BankAccountInfo::firstOrCreate(['account_num' => '123456789'], ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01']);
        $uacs = UACS::firstOrCreate(['object_code' => '4020101000'], ['account_title' => 'Certification Fees']);

        $formInput = FormInput::create([
            'reference_number' => 'OP-2026-0003',
            'email' => 'stranger@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'TotallyNonExistent',
            'lastname_or_agency' => 'PersonInLedger',
            'office_or_college' => 'CAS',
            'position_or_designation' => 'Alumni',
            'address' => 'Dumaguete City',
            'amount' => 500.00,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
        ]);

        $staffInput = StaffInput::create([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-08-15',
            'uacs_id' => $uacs->id,
            'status' => 'processed',
        ]);

        $response = $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'or_no' => '7777-0001',
            'or_date' => '2026-08-16',
        ]);

        $response->assertRedirect("/cashier/requests/{$staffInput->id}");
        $response->assertSessionHas('success', function (string $msg): bool {
            return str_contains($msg, 'No matching ledger student');
        });

        // StaffInput is still updated to paid
        $this->assertDatabaseHas('staff_inputs', [
            'id' => $staffInput->id,
            'status' => 'paid',
            'or_no' => '7777-0001',
        ]);

        // But no ledger entry is created
        $this->assertDatabaseMissing('graduate_ledgers', [
            'reference_number' => '7777-0001',
        ]);
    }

    public function test_public_op_form_submission_resolves_student_num_and_academic_term_to_fks(): void
    {
        $student = Student::create([
            'student_number' => '202600999',
            'first_name' => 'Juana',
            'last_name' => 'Change',
            'email' => 'juana@example.com',
        ]);

        $membership = Membership::firstOrCreate(['member_code' => 'STUDENT'], ['member_desc' => 'Graduate Student']);
        $paymentOption = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Tuition']);

        $response = $this->post('/public/opform', [
            'email' => 'juana@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Juana',
            'lastname_or_agency' => 'Change',
            'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 1200.00,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
            // Submitting 9-digit unhyphenated form
            'student_num' => '202600999',
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
        ]);

        $response->assertRedirect();

        // Verify FormInput resolved the 9-digit string to the student's integer ID
        $term = AcademicTerm::where('school_year', '2025-2026')->where('semester', 'Second Semester')->firstOrFail();
        $this->assertDatabaseHas('form_inputs', [
            'email' => 'juana@example.com',
            'student_num' => $student->id,
            'academic_term' => $term->id,
        ]);
    }

    // ─── Re-import Idempotency / Normalization Coverage ─────────────────────

    public function test_import_again_does_not_duplicate_same_file_rows(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Duplicate Check, A",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-DUP-1,Tuition,0,AR,100,,',
            '"Duplicate Check, A",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-23,REF-DUP-2,Tuition,0,AR,200,,',
        ]);

        $import = function () use ($user, $csv) {
            return $this->actingAs($user)->post('/graduate-ledger/import', [
                'file' => UploadedFile::fake()->createWithContent('duplicate.csv', $csv),
            ]);
        };

        $first = $import();
        $first->assertRedirect('/graduate-ledger');
        $this->assertSame(2, GraduateLedger::whereIn('reference_number', ['REF-DUP-1', 'REF-DUP-2'])->count());

        $second = $import();
        $second
            ->assertRedirect('/graduate-ledger')
            ->assertSessionHas('success', function (string $message): bool {
                return str_contains($message, '2 duplicate row(s) skipped');
            });

        // No duplicates should have been created
        $this->assertSame(2, GraduateLedger::whereIn('reference_number', ['REF-DUP-1', 'REF-DUP-2'])->count());
    }

    public function test_import_falls_back_to_semester_short_column_when_semester_is_blank(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Short Sem Student, S",MSIT,2025-2026,2nd Sem.,,,2026-07-22,REF-SHORTSEM,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('shortsem.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $term = AcademicTerm::where('school_year', '2025-2026')->where('semester', 'Second Semester')->firstOrFail();
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-SHORTSEM',
            'academic_term_id' => $term->id,
        ]);
    }

    public function test_import_auto_repairs_broken_school_year_typos(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Typo Left, T",MSIT,209-2020,1st Sem.,First Semester,,2026-07-22,REF-TYPO-LEFT,Tuition,0,AR,100,,',
            '"Typo Right, T",MSIT,2019-202,1st Sem.,First Semester,,2026-07-22,REF-TYPO-RIGHT,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('typos.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $term2020 = AcademicTerm::where('school_year', '2020-2021')->where('semester', 'First Semester')->firstOrFail();
        $term2019 = AcademicTerm::where('school_year', '2019-2020')->where('semester', 'First Semester')->firstOrFail();

        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-TYPO-LEFT',
            'academic_term_id' => $term2020->id,
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-TYPO-RIGHT',
            'academic_term_id' => $term2019->id,
        ]);
    }

    public function test_cashier_payment_correction_updates_existing_ledger_row_in_place(): void
    {
        $cashier = User::factory()->cashier()->create();

        $student = Student::create([
            'student_number' => '202600888',
            'first_name' => 'Clara',
            'last_name' => 'Oswald',
            'email' => 'clara@example.com',
        ]);
        $course = Course::where('course_code', 'MSIT')->firstOrFail();
        $term = AcademicTerm::where('school_year', '2025-2026')->where('semester', 'First Semester')->firstOrFail();

        GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-08-01',
            'reference_number' => 'AR-ORIGINAL-888',
            'particulars' => 'Tuition',
            'amount' => 5000.00,
            'status' => 'posted',
        ]);

        $membership = Membership::firstOrCreate(['member_code' => 'STUDENT'], ['member_desc' => 'Graduate Student']);
        $paymentOption = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Tuition Fee']);
        $bank = BankAccountInfo::firstOrCreate(['account_num' => '123456789'], ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01']);
        $uacs = UACS::firstOrCreate(['object_code' => '4020101000'], ['account_title' => 'Tuition Fees']);

        $formInput = FormInput::create([
            'reference_number' => 'OP-2026-CORRECT-1',
            'email' => 'clara@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Clara',
            'lastname_or_agency' => 'Oswald',
            'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 2000.00,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
            'student_num' => $student->id,
        ]);

        $staffInput = StaffInput::create([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-08-15',
            'uacs_id' => $uacs->id,
            'status' => 'processed',
        ]);

        // 1. First cashier save
        $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'or_no' => '6666-0001',
            'or_date' => '2026-08-16',
        ]);

        $this->assertSame(1, GraduateLedger::where('remarks', 'OR from OP OP-2026-CORRECT-1')->count());
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => '6666-0001',
            'amount' => '2000.00',
        ]);

        // 2. Cashier corrects OR to a new number and updates date
        $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'or_no' => '6666-0002',
            'or_date' => '2026-08-18',
        ]);

        // Total payments for this OP must still be exactly 1 (NOT 2)
        $this->assertSame(1, GraduateLedger::where('remarks', 'OR from OP OP-2026-CORRECT-1')->count());
        // Old OR no longer exists in ledger
        $this->assertDatabaseMissing('graduate_ledgers', [
            'reference_number' => '6666-0001',
        ]);
        // Updated OR exists with new date
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => '6666-0002',
            'transaction_date' => '2026-08-18',
            'amount' => '2000.00',
        ]);
    }

    public function test_import_maps_and_populates_canonical_course_codes_and_descriptions(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Student Soc 1, S",MASOC,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-SOC-1,Tuition,0,AR,100,,',
            '"Student Soc 2, S",MASOCIO,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-SOC-2,Tuition,0,AR,100,,',
            '"Student Fil 1, F",MA. FIL,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-FIL-1,Tuition,0,AR,100,,',
            '"Student Fil 2, F",EDD Fil,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-FIL-2,Tuition,0,AR,100,,',
            '"Student Math 1, M",PH Math,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-PHM-1,Tuition,0,AR,100,,',
            '"Student Math 2, M",PHD MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-PHM-2,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('canonical_desc.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Verify MA Sociology
        $socioCourse = Course::where('course_code', 'MA Sociology')->firstOrFail();
        $this->assertSame('Doctor of Philosophy in Mathematics', Course::descriptionFor('PhD. Mathematics'));
        $this->assertSame('Master of Arts in Sociology', $socioCourse->course_desc);

        // Verify MA Filipino & EdD. Filipino
        $maFilCourse = Course::where('course_code', 'MA Filipino')->firstOrFail();
        $this->assertSame('Master of Arts in Filipino', $maFilCourse->course_desc);

        $eddFilCourse = Course::where('course_code', 'EdD. Filipino')->firstOrFail();
        $this->assertSame('Doctor of Education in Filipino', $eddFilCourse->course_desc);

        // Verify PhD. Mathematics
        $phdMathCourse = Course::where('course_code', 'PhD. Mathematics')->firstOrFail();
        $this->assertSame('Doctor of Philosophy in Mathematics', $phdMathCourse->course_desc);

        // Verify ledger records are bound to the canonical course IDs
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-SOC-1', 'course_id' => $socioCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-SOC-2', 'course_id' => $socioCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-FIL-1', 'course_id' => $maFilCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-FIL-2', 'course_id' => $eddFilCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PHM-1', 'course_id' => $phdMathCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PHM-2', 'course_id' => $phdMathCourse->id]);
    }
}

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
            ['course_code' => 'MS MATH'],
            ['course_desc' => 'Master of Science in Mathematics', 'course_college' => 'Graduate School'],
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
        AcademicTerm::firstOrCreate([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
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
            'imported_input_by' => 'Admin',
        ]);

        unlink($file);
    }

    public function test_import_normalizes_middle_names_to_initials_without_splitting_double_first_names(): void
    {
        $user = User::factory()->staff()->create();
        $existingStudent = Student::create([
            'last_name' => 'Abugan',
            'first_name' => 'Ma. Jessa',
            'middle_name' => 'Febra',
        ]);
        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Santos, Adrian Ace",MBA,2025-2026,1st Sem.,First Semester,,2026-07-22,NAME-1,Tuition,0,AR,100,,',
            '"Reyes, Maria Katrina Cassandra",MBA,2025-2026,1st Sem.,First Semester,,2026-07-22,NAME-2,Tuition,0,AR,100,,',
            '"Cruz, Juan D.",MBA,2025-2026,1st Sem.,First Semester,,2026-07-22,NAME-3,Tuition,0,AR,100,,',
            '"Cadimas, Joe Val II",MBA,2025-2026,1st Sem.,First Semester,,2026-07-22,NAME-4,Tuition,0,AR,100,,',
            '"Vargas, Carlos Daniel Z. (FOREIGN)",MBA,2025-2026,1st Sem.,First Semester,,2026-07-22,NAME-5,Tuition,0,AR,100,,',
            '"Abugan, Ma. Jessa Febra",MBA,2025-2026,1st Sem.,First Semester,,2026-07-22,NAME-6,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('names.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $this->assertDatabaseHas('students', [
            'last_name' => 'Santos',
            'first_name' => 'Adrian Ace',
            'middle_name' => null,
        ]);
        $this->assertDatabaseHas('students', [
            'last_name' => 'Reyes',
            'first_name' => 'Maria Katrina',
            'middle_name' => 'C',
        ]);
        $this->assertDatabaseHas('students', [
            'last_name' => 'Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'D',
        ]);
        $this->assertDatabaseHas('students', [
            'last_name' => 'Cadimas',
            'first_name' => 'Joe Val II',
            'middle_name' => null,
        ]);
        $this->assertDatabaseHas('students', [
            'last_name' => 'Vargas',
            'first_name' => 'Carlos Daniel',
            'middle_name' => 'Z',
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $existingStudent->id,
            'reference_number' => 'NAME-6',
        ]);
        $this->assertSame(1, Student::query()->where('last_name', 'Abugan')->count());
    }

    public function test_csv_import_uses_authenticated_user_and_normalizes_spreadsheet_input_by_attribution(): void
    {
        $user = User::factory()->staff()->create();
        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $overlength = str_repeat('X', 260);
        $csv = implode("\n", [
            $header,
            '"Initials, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-INITIALS,Tuition,0,AR,100,, MBC ',
            '"Blank, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-BLANK,Tuition,0,AR,100,,',
            '"Numeric, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-NUMERIC,Tuition,0,AR,100,,123',
            '"Formula, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-FORMULA,Tuition,0,AR,100,,=BAD_FORMULA()',
            '"Long, Student",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,CSV-LONG,Tuition,0,AR,100,,"'.$overlength.'"',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-INITIALS',
            'input_by' => $user->id,
            'imported_input_by' => 'MBC',
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-BLANK',
            'input_by' => $user->id,
            'imported_input_by' => null,
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-NUMERIC',
            'input_by' => $user->id,
            'imported_input_by' => '123',
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-FORMULA',
            'input_by' => $user->id,
            'imported_input_by' => null,
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'CSV-LONG',
            'input_by' => $user->id,
            'imported_input_by' => null,
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
        Course::firstOrCreate(
            ['course_code' => 'MA Psychology'],
            ['course_desc' => 'Master of Arts in Psychology', 'course_college' => 'Graduate School'],
        );
        Course::firstOrCreate(
            ['course_code' => 'MASPED'],
            ['course_desc' => 'Master of Arts in Special Education', 'course_college' => 'Graduate School'],
        );
        Course::firstOrCreate(
            ['course_code' => 'UNASSIGNED', 'course_college' => 'Graduate School'],
            ['course_desc' => 'Unassigned / Pending Course Assignment'],
        );
        $initialCourseCount = Course::count();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Student One, A",MS-MATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-1,Tuition,0,AR,100,,',
            '"Student Two, B",M.S. - Math,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-2,Tuition,0,AR,100,,',
            '"Student Three, C",MSMATH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-3,Tuition,0,AR,100,,',
            '"Student Four, D",MS IT,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-4,Tuition,0,AR,100,,',
            '"Student Five, E",MAPYSCH,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-5,Tuition,0,AR,100,,',
            '"Student Six, F",MASPEED,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-6,Tuition,0,AR,100,,',
            '"Student Seven, G",MAMT,2025-2026,1st Sem.,First Semester,,2026-07-22,REF-VAR-7,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('variants.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Every supplied variant must resolve without creating another course.
        $this->assertSame($initialCourseCount, Course::count());

        $mathCourse = Course::where('course_code', 'MS MATH')->firstOrFail();
        $maMathCourse = Course::where('course_code', 'MA Mathematics')->firstOrFail();
        $msitCourse = Course::where('course_code', 'MSIT')->firstOrFail();
        $psychologyCourse = Course::where('course_code', 'MA Psychology')->firstOrFail();
        $specialEducationCourse = Course::where('course_code', 'MASPED')->firstOrFail();

        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-1', 'course_id' => $mathCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-2', 'course_id' => $mathCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-3', 'course_id' => $mathCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-4', 'course_id' => $msitCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-5', 'course_id' => $psychologyCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-6', 'course_id' => $specialEducationCourse->id]);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-VAR-7', 'course_id' => $maMathCourse->id]);
    }

    public function test_import_skips_unrecognized_course_when_no_preset_is_supplied(): void
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

        $this->assertSame($initialCourseCount, Course::count());
        $this->assertDatabaseMissing('courses', ['course_code' => 'MS NURSING']);
        $this->assertDatabaseMissing('graduate_ledgers', ['reference_number' => 'REF-SKIP-1']);

        $report = session('import_report');
        $this->assertNotNull($report);
        $this->assertSame(1, $report['skipped']);
        $this->assertStringContainsString('Unrecognized course', $report['errors'][0]['reason']);
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

        // Recognized canonical code is now NOT auto-created
        $this->assertDatabaseMissing('courses', [
            'course_code' => 'PhD. Mathematics',
        ]);

        $this->assertDatabaseMissing('graduate_ledgers', [
            'reference_number' => 'REF-CANON-NEW',
        ]);
    }

    public function test_import_skips_lone_ms_when_it_is_not_in_the_course_master(): void
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

        $this->assertDatabaseMissing('courses', ['course_code' => 'MS']);
        $this->assertDatabaseMissing('graduate_ledgers', ['reference_number' => 'REF-MS-EXACT']);

        $report = session('import_report');
        $this->assertNotNull($report);
        $this->assertSame(1, $report['skipped']);
        $this->assertStringContainsString('Unrecognized course', $report['errors'][0]['reason']);
    }

    public function test_import_uses_preset_course_for_blank_and_unrecognized_courses(): void
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

        $mathCourse = Course::where('course_code', 'MS MATH')->firstOrFail();
        // Blank course uses the MBA preset
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PRESET-1', 'course_id' => $mbaCourse->id]);
        // Unrecognized values also use the selected preset and do not mutate master data.
        $this->assertDatabaseMissing('courses', ['course_code' => 'UNKNOWN CODE']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PRESET-2', 'course_id' => $mbaCourse->id]);
        // Explicit valid row still uses its own resolved course
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'REF-PRESET-3', 'course_id' => $mathCourse->id]);
    }

    public function test_import_skips_row_and_reports_issue_for_malformed_school_year_or_semester(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            '"Bad Year Student, B",MS-MATH,INVALID_YEAR,1st Sem.,First Semester,,2026-07-22,REF-BAD-YEAR,Tuition,0,AR,100,,',
            '"Garbage Sem Student, G",MS-MATH,2025-2026,XYZ,FOOBAR,,2026-07-22,REF-BAD-SEM,Tuition,0,AR,100,,',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('badterms.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $this->assertDatabaseMissing('academic_terms', ['school_year' => 'Unassigned']);
        $this->assertDatabaseMissing('graduate_ledgers', ['reference_number' => 'REF-BAD-YEAR']);
        $this->assertDatabaseMissing('graduate_ledgers', ['reference_number' => 'REF-BAD-SEM']);

        $report = session('import_report');
        $this->assertNotNull($report);
        $this->assertSame(2, $report['skipped']);
        $this->assertCount(2, $report['errors']);
        $this->assertSame(2, $report['errors'][0]['row']);
        $this->assertSame('Bad Year Student, B', $report['errors'][0]['student']);
        $this->assertStringContainsString('Invalid school year or semester', $report['errors'][0]['reason']);
    }

    public function test_import_skips_row_and_reports_issue_when_course_blank_and_no_preset(): void
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

        $this->assertDatabaseMissing('courses', ['course_code' => 'UNASSIGNED']);
        $this->assertDatabaseMissing('graduate_ledgers', ['reference_number' => 'REF-NO-COURSE']);

        $report = session('import_report');
        $this->assertNotNull($report);
        $this->assertSame(1, $report['skipped']);
        $this->assertCount(1, $report['errors']);
        $this->assertSame(2, $report['errors'][0]['row']);
        $this->assertSame('No Course Student, N', $report['errors'][0]['student']);
        $this->assertStringContainsString('Missing course', $report['errors'][0]['reason']);
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
            '"Blank Term Student, B",MS-MATH,,,,,"2026-07-22",REF-PRESET-TERM-1,Tuition,0,AR,100,,',
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

        // Error formula evaluating to 0 amount is skipped with an issue
        $this->assertDatabaseMissing('graduate_ledgers', [
            'reference_number' => 'FORMULA-ERROR',
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
            'student_num' => $student->id,
            'firstname_or_office' => 'Maria',
            'lastname_or_agency' => 'Santos',
            'office_or_college' => 'Graduate School',
            'course_id' => $course->id,
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
            'status' => 'paid',
            'or_no' => '9999-0001',
            'or_date' => '2026-08-16',
        ]);

        $response->assertRedirect("/cashier/requests/{$staffInput->id}");
        $response->assertSessionHas('success', function (string $msg): bool {
            return str_contains($msg, 'Payment details') && str_contains($msg, 'Posted to the graduate ledger');
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
            'remarks' => "OP:{$formInput->id}",
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
            'student_num' => $student->id,
            'firstname_or_office' => 'Pedro',
            'lastname_or_agency' => 'Penduko',
            'office_or_college' => 'Graduate School',
            'course_id' => $course->id,
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
            'status' => 'paid',
            'or_no' => '8888-0001',
            'or_date' => '2026-08-16',
        ]);

        $this->assertSame(1, GraduateLedger::where('reference_number', '8888-0001')->count());

        // Second save (correction with same OR)
        $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'status' => 'paid',
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
            'status' => 'paid',
            'or_no' => '7777-0001',
            'or_date' => '2026-08-16',
        ]);

        $response->assertRedirect("/cashier/requests/{$staffInput->id}");
        $response->assertSessionHas('success', function (string $msg): bool {
            return str_contains($msg, 'Payment details and status updated');
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
        $term = AcademicTerm::create([
            'school_year' => '2019-2020',
            'semester' => 'First Semester',
        ]);

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

        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-TYPO-LEFT',
            'academic_term_id' => $term->id,
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-TYPO-RIGHT',
            'academic_term_id' => $term->id,
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
            'course_id' => $course->id,
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
            'status' => 'paid',
            'or_no' => '6666-0001',
            'or_date' => '2026-08-16',
        ]);

        $this->assertSame(1, GraduateLedger::where('remarks', "OP:{$formInput->id}")->count());
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => '6666-0001',
            'amount' => '2000.00',
        ]);

        // 2. Cashier corrects OR to a new number and updates date
        $this->actingAs($cashier)->put("/cashier/requests/{$staffInput->id}/payment", [
            'status' => 'paid',
            'or_no' => '6666-0002',
            'or_date' => '2026-08-18',
        ]);

        // Total payments for this OP must still be exactly 1 (NOT 2)
        $this->assertSame(1, GraduateLedger::where('remarks', "OP:{$formInput->id}")->count());
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
        foreach (['MA Sociology', 'MA Filipino', 'EdD. Filipino', 'PhD. Mathematics'] as $code) {
            Course::create([
                'course_code' => $code,
                'course_desc' => Course::descriptionFor($code),
                'course_college' => 'Graduate School',
            ]);
        }

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

    public function test_import_with_student_number_and_email_saves_and_backfills_without_overwriting(): void
    {
        $user = User::factory()->staff()->create();

        // 1. Existing student with missing email and missing student number
        $existing = Student::create([
            'last_name' => 'Penduko',
            'first_name' => 'Pedro',
            'middle_name' => 'A',
            'email' => null,
            'student_number' => null,
        ]);

        // 2. Existing student with populated fields that must NOT be overwritten
        $keepExisting = Student::create([
            'student_number' => '2026-99999',
            'last_name' => 'Silang',
            'first_name' => 'Gabriela',
            'middle_name' => 'B',
            'email' => 'keep.mine@example.com',
        ]);

        $header = 'student_number,student_name,email,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';
        $csv = implode("\n", [
            $header,
            // Backfill Pedro by name match
            '2026-11111,"Penduko, Pedro A.",pedro.grad@example.com,MS-MATH,2025-2026,1st Sem.,First Semester,9,2026-07-22,REF-BACKFILL-1,Tuition,150.00,AR,1350.00,Backfilled,Admin',
            // Gabriela matched by student number, attempt overwrite email
            '2026-99999,"Silang, Gabriela B.",overwrite.grad@example.com,MS-MATH,2025-2026,1st Sem.,First Semester,9,2026-07-22,REF-BACKFILL-2,Tuition,150.00,AR,1350.00,AttemptOverwrite,Admin',
            // New student with invalid email
            '2026-22222,"Rizal, Jose P.",bad-email,MS-MATH,2025-2026,1st Sem.,First Semester,9,2026-07-22,REF-BACKFILL-3,Tuition,150.00,AR,1350.00,InvalidEmail,Admin',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('grad_backfill.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Check Pedro: backfilled student number and email
        $existing->refresh();
        $this->assertSame('2026-11111', $existing->student_number);
        $this->assertSame('pedro.grad@example.com', $existing->email);

        // Check Gabriela: preserved original email
        $keepExisting->refresh();
        $this->assertSame('keep.mine@example.com', $keepExisting->email);

        // Check Jose: created, but email is null
        $jose = Student::where('student_number', '2026-22222')->first();
        $this->assertNotNull($jose);
        $this->assertNull($jose->email);
    }

    public function test_template_file_can_be_imported_successfully(): void
    {
        $user = User::factory()->staff()->create();
        $templatePath = base_path('plan/graduate_ledger_import_template.xlsx');

        $this->assertFileExists($templatePath);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($templatePath, 'graduate_ledger_import_template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Check sample row from template: 2026-0001, DELA CRUZ, JUAN, A, juan.delacruz@norsu.edu.ph
        $student = Student::where('student_number', '2026-0001')->first();
        $this->assertNotNull($student);
        $this->assertSame('DELA CRUZ', $student->last_name);
        $this->assertSame('JUAN', $student->first_name);
        $this->assertSame('A', $student->middle_name);
        $this->assertSame('juan.delacruz@norsu.edu.ph', $student->email);

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '8100.00',
            'input_by' => $user->id,
            'imported_input_by' => 'Staff Name',
        ]);
    }

    public function test_split_headers_import_correctly(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'ledger-split').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['student_id_number', 'last_name', 'first_name', 'middle_name', 'email', 'course', 'school_year', 'semester', 'units', 'transaction_date', 'reference_number', 'particulars', 'tuition_rate', 'ar_or_payment', 'amount', 'remarks'],
            ['2026-0003', 'BONDOC', 'JAMES', 'B', 'james.bondoc@norsu.edu.ph', 'MBA', '2026-2027', 'First Semester', 18, '2026-08-01', 'OR-SPLIT-1', 'Tuition Fee', '450.00', 'AR', '8100.00', 'Enrolled'],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($file, 'split.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $student = Student::where('student_number', '2026-0003')->first();
        $this->assertNotNull($student);
        $this->assertSame('BONDOC', $student->last_name);
        $this->assertSame('JAMES', $student->first_name);
        $this->assertSame('B', $student->middle_name);
        $this->assertSame('james.bondoc@norsu.edu.ph', $student->email);

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'reference_number' => 'OR-SPLIT-1',
            'amount' => '8100.00',
            'entry_type' => 'ar',
            'input_by' => $user->id,
            'imported_input_by' => null,
        ]);

        unlink($file);
    }

    public function test_split_header_aliases_import_correctly(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'student_no,surname,given_name,middle_initial,email_address,program,academic_year,semester,units,date,ref_no,particulars,rate,type,amount,remarks';
        $csv = implode("\n", [
            $header,
            '2026-0004,DEL MUNDO,CLARA,M,clara@norsu.edu.ph,MBA,2026-2027,First Semester,18,2026-08-01,OR-ALIAS-1,Tuition Fee,450.00,Payment,5000.00,Paid',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('aliases.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $student = Student::where('student_number', '2026-0004')->first();
        $this->assertNotNull($student);
        $this->assertSame('DEL MUNDO', $student->last_name);
        $this->assertSame('CLARA', $student->first_name);
        $this->assertSame('M', $student->middle_name);
        $this->assertSame('clara@norsu.edu.ph', $student->email);

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'reference_number' => 'OR-ALIAS-1',
            'amount' => '5000.00',
            'entry_type' => 'payment',
        ]);
    }

    public function test_split_and_full_name_coexist_uses_split_when_populated_and_falls_back_to_full_name_when_blank(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'last_name,first_name,middle_name,full_name,course,school_year,semester,amount,reference_number';
        $csv = implode("\n", [
            $header,
            'Santos,Maria,C,"Ignored Full Name",MBA,2026-2027,First Semester,2500.00,REF-COEXIST-1',
            ',,,"Mercado, Andres D.",MBA,2026-2027,First Semester,3500.00,REF-COEXIST-2',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('coexist.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        // Split name row
        $santos = Student::where('last_name', 'Santos')->first();
        $this->assertNotNull($santos);
        $this->assertSame('Maria', $santos->first_name);
        $this->assertSame('C', $santos->middle_name);
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $santos->id,
            'reference_number' => 'REF-COEXIST-1',
        ]);

        // Ignored full name should not exist
        $this->assertNull(Student::where('last_name', 'Ignored Full Name')->first());
        $this->assertNull(Student::where('last_name', 'Ignored')->first());

        // Fallback row
        $mercado = Student::where('last_name', 'Mercado')->first();
        $this->assertNotNull($mercado);
        $this->assertSame('Andres', $mercado->first_name);
        $this->assertSame('D', $mercado->middle_name);
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $mercado->id,
            'reference_number' => 'REF-COEXIST-2',
        ]);
    }

    public function test_missing_student_name_reports_error_when_no_usable_name(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'last_name,first_name,middle_name,full_name,course,school_year,semester,amount,reference_number';
        $csv = implode("\n", [
            $header,
            ',,,,MBA,2026-2027,First Semester,1000.00,REF-NONAME',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('missing_name.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $report = session('import_report');
        $this->assertNotNull($report);
        $this->assertSame(1, $report['skipped']);
        $this->assertCount(1, $report['errors']);
        $this->assertSame(2, $report['errors'][0]['row']);
        $this->assertNull($report['errors'][0]['student']);
        $this->assertSame('Missing student name', $report['errors'][0]['reason']);
    }

    public function test_full_name_only_imports_remain_compatible(): void
    {
        $user = User::factory()->staff()->create();

        $header = 'full_name,course,school_year,semester,amount,reference_number';
        $csv = implode("\n", [
            $header,
            '"Luna, Antonio N.",MBA,2026-2027,First Semester,4000.00,REF-FULLNAME-1',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('full_name.csv', $csv),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $luna = Student::where('last_name', 'Luna')->first();
        $this->assertNotNull($luna);
        $this->assertSame('Antonio', $luna->first_name);
        $this->assertSame('N', $luna->middle_name);

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $luna->id,
            'reference_number' => 'REF-FULLNAME-1',
            'amount' => '4000.00',
        ]);
    }

    public function test_legacy_positional_workbook_remains_compatible(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'ledger-positional').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        // Unrecognized headers so resolveColumnMap returns null
        $sheet->fromArray([
            ['HeaderA', 'HeaderB', 'HeaderC', 'HeaderD', 'HeaderE', 'HeaderF', 'HeaderG', 'HeaderH', 'HeaderI', 'HeaderJ', 'HeaderK', 'HeaderL', 'HeaderM', 'HeaderN'],
            ['Mabini, Apolinario P.', 'MBA', '2026-2027', '1st Sem.', 'First Semester', 9, '2026-08-01', 'REF-LEGACY-1', 'Tuition Fee', '450.00', 'AR', '4050.00', 'Legacy', 'Encoder'],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($file, 'positional.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $mabini = Student::where('last_name', 'Mabini')->first();
        $this->assertNotNull($mabini);
        $this->assertSame('Apolinario', $mabini->first_name);
        $this->assertSame('P', $mabini->middle_name);

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $mabini->id,
            'reference_number' => 'REF-LEGACY-1',
            'amount' => '4050.00',
            'entry_type' => 'ar',
            'input_by' => $user->id,
            'imported_input_by' => 'Encoder',
        ]);

        unlink($file);
    }

    public function test_input_by_header_aliases_are_recognized_for_attribution(): void
    {
        $user = User::factory()->staff()->create();

        // 1. input_by_ (e.g. "Input By:")
        $csv1 = implode("\n", [
            'student_name,course,school_year,semester,amount,reference_number,Input By:',
            '"Alias One, S",MBA,2026-2027,First Semester,1000.00,REF-ALIAS-INP1,Staff Alice',
        ]);
        $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('alias1.csv', $csv1),
        ])->assertRedirect('/graduate-ledger');

        // 2. encoded_by
        $csv2 = implode("\n", [
            'student_name,course,school_year,semester,amount,reference_number,encoded_by',
            '"Alias Two, S",MBA,2026-2027,First Semester,1000.00,REF-ALIAS-INP2,Staff Bob',
        ]);
        $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('alias2.csv', $csv2),
        ])->assertRedirect('/graduate-ledger');

        // 3. prepared_by
        $csv3 = implode("\n", [
            'student_name,course,school_year,semester,amount,reference_number,prepared_by',
            '"Alias Three, S",MBA,2026-2027,First Semester,1000.00,REF-ALIAS-INP3,Staff Charlie',
        ]);
        $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent('alias3.csv', $csv3),
        ])->assertRedirect('/graduate-ledger');

        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-ALIAS-INP1',
            'input_by' => $user->id,
            'imported_input_by' => 'Staff Alice',
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-ALIAS-INP2',
            'input_by' => $user->id,
            'imported_input_by' => 'Staff Bob',
        ]);
        $this->assertDatabaseHas('graduate_ledgers', [
            'reference_number' => 'REF-ALIAS-INP3',
            'input_by' => $user->id,
            'imported_input_by' => 'Staff Charlie',
        ]);
    }
}

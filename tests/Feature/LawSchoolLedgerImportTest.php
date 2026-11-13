<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LawSchoolLedgerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_import_excel_transactions_with_combined_name_column(): void
    {
        $user = User::factory()->staff()->create();

        Course::create([
            'course_code' => Course::GRADUATE_FALLBACK_CODE,
            'course_desc' => 'Unassigned / Pending Graduate Course Assignment',
            'course_college' => 'Graduate School',
        ]);

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-import').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'NAME (Last Name, First Name, M.I.)',
                'COURSE',
                'SCHOOL YEAR',
                'SEMESTER/ SUMMER',
                'UNITS',
                'TRANSACTION DATE',
                'Reference JEV / O.R. NUMBER',
                'PARTICULARS',
                'TUITION per UNIT/ Reg. and Miscellaneous per semester',
                'AR/PAYMENT',
                'AMOUNT',
                'REMARKS',
                'STATUS',
                'INPUT BY:',
            ],
            [
                'ABELLA, REXIL A.',
                'JD',
                '2025-2026',
                '1st Sem',
                10,
                '2025-07-30',
                null,
                'Tuition',
                950,
                'AR',
                9500,
                null,
                null,
                'JVT',
            ],
            [
                'ABELLA, REXIL A.',
                'JD',
                '2025-2026',
                '1st Sem',
                10,
                '2025-07-30',
                '0654396',
                'Tuition',
                null,
                'PAYMENT',
                -6000,
                null,
                null,
                'JVT',
            ],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        $response = $this->actingAs($user)->post('/law-ledger/import', [
            'file' => new UploadedFile($file, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/law-ledger');

        $this->assertDatabaseHas('courses', [
            'course_code' => Course::GRADUATE_FALLBACK_CODE,
            'course_college' => 'Graduate School',
        ]);
        $this->assertDatabaseHas('courses', [
            'course_code' => Course::LAW_FALLBACK_CODE,
            'course_college' => 'School of Law',
        ]);

        $student = Student::query()
            ->where('last_name', 'ABELLA')
            ->where('first_name', 'REXIL')
            ->first();

        $this->assertNotNull($student);
        $this->assertEquals('A', $student->middle_name);
        $this->assertNull($student->student_number);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '9500.00',
            'particulars' => 'Tuition',
            'imported_input_by' => 'JVT',
        ]);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'payment',
            'amount' => '6000.00',
            'reference_number' => '0654396',
            'imported_input_by' => 'JVT',
        ]);

        unlink($file);
    }

    public function test_broken_excel_formula_is_not_saved_as_student_number(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-import-formula').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'STUDENT ID NUMBER',
                'NAME (Last Name, First Name, M.I.)',
                'COURSE',
                'SCHOOL YEAR',
                'SEMESTER/ SUMMER',
                'UNITS',
                'TRANSACTION DATE',
                'Reference JEV / O.R. NUMBER',
                'PARTICULARS',
                'TUITION per UNIT/ Reg. and Miscellaneous per semester',
                'AR/PAYMENT',
                'AMOUNT',
                'REMARKS',
                'STATUS',
                'INPUT BY:',
            ],
            [
                '=IFERROR(INDEX(#REF!,MATCH(TRIM(B2),$A$2:$A$400,0)),"")',
                'FORMULA, BROKEN A.',
                'JD',
                '2025-2026',
                '1st Sem',
                10,
                '2025-07-30',
                null,
                'Tuition',
                950,
                'AR',
                9500,
                null,
                null,
                'JVT',
            ],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        $response = $this->actingAs($user)->post('/law-ledger/import', [
            'file' => new UploadedFile($file, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/law-ledger');

        $student = Student::query()
            ->where('last_name', 'FORMULA')
            ->where('first_name', 'BROKEN')
            ->first();

        $this->assertNotNull($student);
        $this->assertNull($student->student_number);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '9500.00',
            'imported_input_by' => 'JVT',
        ]);

        unlink($file);
    }

    public function test_blank_excel_transaction_date_stays_null(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-import-blank-date').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'NAME (Last Name, First Name, M.I.)',
                'COURSE',
                'SCHOOL YEAR',
                'SEMESTER/ SUMMER',
                'UNITS',
                'TRANSACTION DATE',
                'Reference JEV / O.R. NUMBER',
                'PARTICULARS',
                'TUITION per UNIT/ Reg. and Miscellaneous per semester',
                'AR/PAYMENT',
                'AMOUNT',
                'REMARKS',
                'STATUS',
                'INPUT BY:',
            ],
            [
                'DATELESS, STUDENT A.',
                'JD',
                '2025-2026',
                '1st Sem',
                10,
                null,
                null,
                'Tuition',
                950,
                'AR',
                9500,
                null,
                null,
                'JVT',
            ],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        $response = $this->actingAs($user)->post('/law-ledger/import', [
            'file' => new UploadedFile($file, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/law-ledger');

        $student = Student::query()
            ->where('last_name', 'DATELESS')
            ->where('first_name', 'STUDENT')
            ->first();

        $this->assertNotNull($student);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '9500.00',
            'transaction_date' => null,
        ]);

        unlink($file);
    }

    public function test_user_can_import_csv_transactions_with_combined_name_column(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-import').'.csv';
        $handle = fopen($file, 'w');
        fputcsv($handle, [
            'STUDENT ID NUMBER',
            'NAME (Last Name, First Name, M.I.)',
            'COURSE',
            'SCHOOL YEAR',
            'SEMESTER/ SUMMER',
            'UNITS',
            'TRANSACTION DATE',
            'Reference JEV / O.R. NUMBER',
            'PARTICULARS',
            'TUITION per UNIT/ Reg. and Miscellaneous per semester',
            'AR/PAYMENT',
            'AMOUNT',
            'REMARKS',
            'STATUS',
            'INPUT BY:',
        ]);
        fputcsv($handle, [
            '202600002.0',
            'ACHARON, MARIA RIZA A.',
            'JD',
            '2025-2026',
            '1st Sem',
            17,
            '2025-07-30',
            '',
            'Tuition',
            950,
            'AR',
            16150,
            '',
            '',
            'JVT',
        ]);
        fputcsv($handle, [
            '202600002.0',
            'ACHARON, MARIA RIZA A.',
            'JD',
            '2025-2026',
            '1st Sem',
            17,
            '2025-07-30',
            '0655531',
            'Tuition',
            '',
            'PAYMENT',
            '(6,000.00)',
            '',
            '',
            'JVT',
        ]);
        fclose($handle);

        $response = $this->actingAs($user)->post('/law-ledger/import', [
            'file' => new UploadedFile($file, 'import.csv', 'text/csv', null, true),
        ]);

        $response->assertRedirect('/law-ledger');

        $student = Student::query()
            ->where('last_name', 'ACHARON')
            ->where('first_name', 'MARIA RIZA')
            ->first();

        $this->assertNotNull($student);
        $this->assertEquals('A', $student->middle_name);
        $this->assertSame('202600002', $student->student_number);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '16150.00',
        ]);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'payment',
            'amount' => '6000.00',
            'reference_number' => '0655531',
        ]);

        $this->actingAs($user)
            ->getJson("/law-ledger/students/{$student->id}/balance")
            ->assertOk()
            ->assertJsonPath('summary.totalCharges', 16150)
            ->assertJsonPath('summary.totalPayments', 6000)
            ->assertJsonPath('summary.outstandingBalance', 10150);

        unlink($file);
    }

    public function test_user_can_import_csv_with_separate_name_columns(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-sep').'.csv';
        $handle = fopen($file, 'w');
        fputcsv($handle, [
            'student_number',
            'last_name',
            'first_name',
            'middle_initial',
            'course',
            'school_year',
            'semester_or_summer',
            'units',
            'transaction_date',
            'reference_jev_or_number',
            'particulars',
            'tuition_per_unit_or_fee_per_semester',
            'ar_or_payment',
            'amount',
            'remarks',
        ]);
        fputcsv($handle, [
            '2026-00003',
            'DELA CRUZ',
            'JUAN',
            'P.',
            'JD',
            '2025-2026',
            'First Semester',
            15,
            '2025-08-01',
            'REF-123',
            'Tuition',
            950,
            'AR',
            14250,
            'Test remarks',
        ]);
        fclose($handle);

        $response = $this->actingAs($user)->post('/law-ledger/import', [
            'file' => new UploadedFile($file, 'import.csv', 'text/csv', null, true),
        ]);

        $response->assertRedirect('/law-ledger');

        $student = Student::query()
            ->where('last_name', 'DELA CRUZ')
            ->where('first_name', 'JUAN')
            ->first();

        $this->assertNotNull($student);
        $this->assertSame('2026-00003', $student->student_number);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '14250.00',
            'reference_number' => 'REF-123',
        ]);

        unlink($file);
    }

    public function test_import_with_email_column_saves_email_and_backfills_without_overwriting(): void
    {
        $user = User::factory()->staff()->create();

        // 1. Existing student with no email - should be backfilled
        $existingWithoutEmail = Student::create([
            'student_number' => '2026-00010',
            'last_name' => 'PENDUKO',
            'first_name' => 'PEDRO',
            'email' => null,
        ]);

        // 2. Existing student with populated email - should NOT be overwritten
        $existingWithEmail = Student::create([
            'student_number' => '2026-00020',
            'last_name' => 'SILANG',
            'first_name' => 'GABRIELA',
            'email' => 'keep.mine@example.com',
        ]);

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-email').'.csv';
        $handle = fopen($file, 'w');
        fputcsv($handle, [
            'STUDENT ID NUMBER',
            'NAME (Last Name, First Name, M.I.)',
            'EMAIL',
            'COURSE',
            'SCHOOL YEAR',
            'SEMESTER/ SUMMER',
            'UNITS',
            'TRANSACTION DATE',
            'Reference JEV / O.R. NUMBER',
            'PARTICULARS',
            'TUITION per UNIT/ Reg. and Miscellaneous per semester',
            'AR/PAYMENT',
            'AMOUNT',
            'REMARKS',
            'STATUS',
            'INPUT BY:',
        ]);
        // Row 1: Backfill Pedro
        fputcsv($handle, [
            '2026-00010',
            'PENDUKO, PEDRO A.',
            'pedro.backfilled@example.com',
            'JD',
            '2025-2026',
            '1st Sem',
            10,
            '2025-07-30',
            null,
            'Tuition',
            950,
            'AR',
            9500,
            null,
            null,
            'JVT',
        ]);
        // Row 2: Keep Gabriela's email, do not overwrite with new email
        fputcsv($handle, [
            '2026-00020',
            'SILANG, GABRIELA B.',
            'overwrite.attempt@example.com',
            'JD',
            '2025-2026',
            '1st Sem',
            10,
            '2025-07-30',
            null,
            'Tuition',
            950,
            'AR',
            9500,
            null,
            null,
            'JVT',
        ]);
        // Row 3: New student with valid email
        fputcsv($handle, [
            '2026-00030',
            'BONIFACIO, ANDRES C.',
            'andres@example.com',
            'JD',
            '2025-2026',
            '1st Sem',
            10,
            '2025-07-30',
            null,
            'Tuition',
            950,
            'AR',
            9500,
            null,
            null,
            'JVT',
        ]);
        // Row 4: New student with invalid email
        fputcsv($handle, [
            '2026-00040',
            'RIZAL, JOSE P.',
            'invalid-email-format',
            'JD',
            '2025-2026',
            '1st Sem',
            10,
            '2025-07-30',
            null,
            'Tuition',
            950,
            'AR',
            9500,
            null,
            null,
            'JVT',
        ]);
        fclose($handle);

        $response = $this->actingAs($user)->post('/law-ledger/import', [
            'file' => new UploadedFile($file, 'import.csv', 'text/csv', null, true),
        ]);

        $response->assertRedirect('/law-ledger');

        // Verify Pedro got backfilled
        $existingWithoutEmail->refresh();
        $this->assertSame('pedro.backfilled@example.com', $existingWithoutEmail->email);

        // Verify Gabriela was NOT overwritten
        $existingWithEmail->refresh();
        $this->assertSame('keep.mine@example.com', $existingWithEmail->email);

        // Verify Andres was created with email
        $andres = Student::where('last_name', 'BONIFACIO')->first();
        $this->assertNotNull($andres);
        $this->assertSame('andres@example.com', $andres->email);

        // Verify Jose was created with null email due to invalid format
        $jose = Student::where('last_name', 'RIZAL')->first();
        $this->assertNotNull($jose);
        $this->assertNull($jose->email);

        unlink($file);
    }

    public function test_template_file_can_be_imported_successfully(): void
    {
        $user = User::factory()->staff()->create();
        $templatePath = base_path('plan/law_ledger_import_template.xlsx');

        $this->assertFileExists($templatePath);

        $response = $this->actingAs($user)->post('/law-ledger/import', [
            'file' => new UploadedFile($templatePath, 'law_ledger_import_template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/law-ledger');

        // Check the first sample row from the current 100-student template.
        $student = Student::where('student_number', '2026-2001')->first();
        $this->assertNotNull($student);
        $this->assertNotSame('', $student->last_name);
        $this->assertNotSame('', $student->first_name);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '8100.00',
        ]);
    }
}

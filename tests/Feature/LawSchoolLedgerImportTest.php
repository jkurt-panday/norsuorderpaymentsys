<?php

namespace Tests\Feature;

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

        $student = Student::query()
            ->where('last_name', 'ABELLA')
            ->where('first_name', 'REXIL')
            ->first();

        $this->assertNotNull($student);
        $this->assertEquals('A', $student->middle_name);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '9500.00',
            'particulars' => 'Tuition',
        ]);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'payment',
            'amount' => '6000.00',
            'reference_number' => '0654396',
        ]);

        unlink($file);
    }

    public function test_user_can_import_csv_transactions_with_combined_name_column(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-import').'.csv';
        $handle = fopen($file, 'w');
        fputcsv($handle, [
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
            -6000,
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

        unlink($file);
    }

    public function test_user_can_import_csv_with_separate_name_columns(): void
    {
        $user = User::factory()->staff()->create();

        $file = tempnam(sys_get_temp_dir(), 'law-ledger-sep').'.csv';
        $handle = fopen($file, 'w');
        fputcsv($handle, [
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

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'ar',
            'amount' => '14250.00',
            'reference_number' => 'REF-123',
        ]);

        unlink($file);
    }
}

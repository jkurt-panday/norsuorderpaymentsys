<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GraduateLedgerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_import_excel_transactions_into_the_ledger(): void
    {
        $user = User::factory()->create();

        $file = tempnam(sys_get_temp_dir(), 'ledger-import').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['student_name', 'course', 'school_year', 'semester_short', 'semester', 'units', 'transaction_date', 'reference_or_jev_number', 'particulars', 'tuition_per_unit_or_misc', 'ar_payment', 'amount', 'remarks', 'input_by'],
            ['Juan Dela Cruz', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', 9, '2026-07-22', 'OR-001', 'Tuition', '150.00', 'AR', '1350.00', 'Imported', 'Admin'],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        $response = $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($file, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect('/graduate-ledger');

        $student = Student::where('raw_name_from_csv', 'Juan Dela Cruz')->first();
        $this->assertNotNull($student);
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id'              => $student->id,
            'entry_type'              => 'ar',
            'reference_or_jev_number' => 'OR-001',
            'amount'                  => '1350.00',
        ]);

        unlink($file);
    }

    public function test_import_uses_amount_signs_reports_formatting_warnings_and_ignores_other_sheets(): void
    {
        $user = User::factory()->create();

        $file = tempnam(sys_get_temp_dir(), 'ledger-import-rules').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Graduate School');
        $sheet->fromArray([
            ['student_name', 'course', 'school_year', 'semester_short', 'semester', 'units', 'transaction_date', 'reference_or_jev_number', 'particulars', 'tuition_per_unit_or_misc', 'ar_payment', 'amount', 'remarks', 'input_by'],
            ['Negative Blank', 'MS-MATH', '2025-2026', '1st Sem.', 'First Semester', null, '2026-07-22', 'NEG-BLANK', 'Payment', null, '', -100, null, 'Admin'],
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

        $this->assertDatabaseHas('graduate_ledgers', ['reference_or_jev_number' => 'NEG-BLANK', 'entry_type' => 'payment', 'amount' => '100.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_or_jev_number' => 'NEG-AR', 'entry_type' => 'payment', 'amount' => '200.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_or_jev_number' => 'POS-PAY', 'entry_type' => 'payment', 'amount' => '300.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_or_jev_number' => 'POS-AR', 'entry_type' => 'ar', 'amount' => '400.00']);
        $this->assertDatabaseMissing('graduate_ledgers', ['reference_or_jev_number' => 'IGNORED']);

        unlink($file);
    }
}

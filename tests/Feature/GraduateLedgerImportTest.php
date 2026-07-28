<?php

namespace Tests\Feature;

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
        $spreadsheet = new Spreadsheet();
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
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_name' => 'Juan Dela Cruz',
            'reference_or_jev_number' => 'OR-001',
            'amount' => '1350.00',
        ]);

        unlink($file);
    }
}

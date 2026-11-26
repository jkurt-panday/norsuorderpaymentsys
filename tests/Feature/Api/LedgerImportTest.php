<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LedgerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_requires_a_supported_file(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->postJson('/api/v1/graduate-ledger/import', [
            'file' => UploadedFile::fake()->create('ledger.txt', 1, 'text/plain'),
        ])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
    }

    public function test_import_accepts_csv_and_returns_structured_result(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent('ledger.csv', "STUDENT NUMBER,FULL NAME,AMOUNT\nS1,Test Student,100\n");
        $response = $this->actingAs($staff)->postJson('/api/v1/graduate-ledger/import', ['file' => $csv]);
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'imported', 'skipped', 'duplicates', 'warnings', 'detail_available'])
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('duplicates', 0);
    }

    public function test_empty_rows_are_ignored_but_non_empty_invalid_rows_are_skipped(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent(
            'ledger.csv',
            "FULL NAME,COURSE,AMOUNT\nTest Student,MBA,100\n,,\n,JD,100\n",
        );

        $this->actingAs($staff)
            ->postJson('/api/v1/graduate-ledger/import', ['file' => $csv])
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 1);
    }

    public function test_law_import_ignores_empty_rows(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $csv = UploadedFile::fake()->createWithContent(
            'law-ledger.csv',
            "FULL NAME,COURSE,AMOUNT\nLaw Student,JD,100\n,,\n",
        );

        $this->actingAs($staff)
            ->postJson('/api/v1/law-ledger/import', ['file' => $csv])
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 0);
    }

    public function test_formula_only_excel_template_rows_are_ignored(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        foreach ([
            ['graduate', 'Graduate Student', 'MBA'],
            ['law', 'Law Student', 'JD'],
        ] as [$ledger, $studentName, $course]) {
            $path = tempnam(sys_get_temp_dir(), $ledger.'-empty-row').'.xlsx';
            $spreadsheet = new Spreadsheet;
            $spreadsheet->getActiveSheet()->fromArray([
                ['FULL NAME', 'COURSE', 'AMOUNT'],
                [$studentName, $course, 100],
                [null, null, '=IFERROR(1/0,"")'],
            ]);
            (new Xlsx($spreadsheet))->save($path);
            $spreadsheet->disconnectWorksheets();

            try {
                $file = new UploadedFile(
                    $path,
                    $ledger.'-ledger.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true,
                );

                $this->actingAs($staff)
                    ->postJson("/api/v1/{$ledger}-ledger/import", ['file' => $file])
                    ->assertOk()
                    ->assertJsonPath('imported', 1)
                    ->assertJsonPath('skipped', 0);
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_graduate_excel_continuation_rows_inherit_student_context(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $path = tempnam(sys_get_temp_dir(), 'graduate-continuation').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['FULL NAME', 'COURSE', 'SCHOOL YEAR', 'SEMESTER', 'PARTICULARS', 'AR/PAYMENT', 'AMOUNT', 'REMARKS', 'INPUT BY:'],
            ['Context Student', 'MBA', '2026-2027', 'First Semester', 'Tuition', 'AR', 100, '=IF(G2>0,"Outstanding","")', 'RAL'],
            [null, null, null, null, 'Miscellaneous', 'AR', 50, '=IF(G3>0,"Outstanding","")', 'RAL'],
            [null, null, null, null, null, null, null, '=IFERROR(1/0,"")', 'RAL'],
        ]);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $file = new UploadedFile(
                $path,
                'graduate-ledger.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            );

            $this->actingAs($staff)
                ->postJson('/api/v1/graduate-ledger/import', ['file' => $file])
                ->assertOk()
                ->assertJsonPath('imported', 2)
                ->assertJsonPath('skipped', 0);
        } finally {
            @unlink($path);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\UACS;
use App\Models\User;
use App\Services\UACSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UacsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_skips_headers_blank_rows_and_normalizes_trimmed_codes(): void
    {
        $file = $this->csvFile("Title\nNORSU Object Code,NORSU Sub-Object Code,UACS Object Code,UACS Sub-Object Code,RCA Code,Account Title\n\n 000000000001 , 01 , 1234567890 , 02 , 12345678 , Tuition\n");

        $count = app(UACSService::class)->import($file);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('uacs', [
            'norsu_object_code' => '000000000001',
            'norsu_sub_object_code' => '01',
            'uacs_object_code' => '1234567890',
            'uacs_sub_object_code' => '02',
            'rca_code' => '12345678',
            'account_title' => 'Tuition',
        ]);
    }

    public function test_xlsx_import_works_and_formula_object_codes_are_resolved(): void
    {
        $file = $this->xlsxFile([
            ['NORSU Object Code', 'NORSU Sub-Object Code', 'UACS Object Code', 'UACS Sub-Object Code', 'RCA Code', 'Account Title'],
            ['=C3&B3', '01', '1234567890', '02', '12345678', 'Formula row'],
        ]);

        $count = app(UACSService::class)->import($file);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('uacs', ['norsu_object_code' => '123456789001', 'account_title' => 'Formula row']);
    }

    public function test_unsupported_extensions_are_rejected_by_the_import_endpoint(): void
    {
        $staff = User::factory()->staff()->create();
        $file = UploadedFile::fake()->create('uacs.pdf', 10, 'application/pdf');

        $this->actingAs($staff)->from('/staff/uacs')->post(route('staff.uacs.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('uacs', 0);
    }

    public function test_placeholder_values_are_preserved_and_numeric_codes_are_normalized(): void
    {
        $file = $this->csvFile("NORSU Object Code,NORSU Sub-Object Code,UACS Object Code,UACS Sub-Object Code,RCA Code,Account Title\n-,N/A,1,2,3,Placeholder\n");

        app(UACSService::class)->import($file);

        $this->assertDatabaseHas('uacs', [
            'norsu_object_code' => '-',
            'norsu_sub_object_code' => 'N/A',
            'uacs_object_code' => '0000000001',
            'uacs_sub_object_code' => '02',
            'rca_code' => '00000003',
        ]);
    }

    public function test_duplicate_records_are_not_inserted_twice(): void
    {
        $file = $this->csvFile("NORSU Object Code,NORSU Sub-Object Code,UACS Object Code,UACS Sub-Object Code,RCA Code,Account Title\n000000000001,01,1234567890,02,12345678,Tuition\n000000000001,01,1234567890,02,12345678,Tuition\n");

        app(UACSService::class)->import($file);

        $this->assertSame(2, UACS::query()->count());
    }

    public function test_invalid_rows_are_skipped_and_reported_without_partial_corruption(): void
    {
        $file = $this->csvFile("NORSU Object Code,NORSU Sub-Object Code,UACS Object Code,UACS Sub-Object Code,RCA Code,Account Title\n000000000001,01,1234567890,02,12345678,Valid\nshort,01,1234567890,02,12345678,Invalid\n");

        $response = $this->actingAs(User::factory()->staff()->create())->from('/staff/uacs')->post(route('staff.uacs.import'), ['file' => $file]);

        $response->assertRedirect(route('staff.uacs.index'))->assertSessionHas('success');
        $this->assertDatabaseCount('uacs', 2);
        $this->assertDatabaseHas('uacs', ['account_title' => 'Valid']);
        $this->assertDatabaseHas('uacs', ['account_title' => 'Invalid']);
    }

    public function test_import_failure_does_not_leave_partial_rows(): void
    {
        $file = $this->csvFile("NORSU Object Code,NORSU Sub-Object Code,UACS Object Code,UACS Sub-Object Code,RCA Code,Account Title\n000000000001,01,1234567890,02,12345678,First\n");
        UACS::create(['norsu_object_code' => '000000000001', 'norsu_sub_object_code' => '01', 'uacs_object_code' => '1234567890', 'uacs_sub_object_code' => '02', 'rca_code' => '12345678', 'account_title' => 'Existing']);

        try {
            app(UACSService::class)->import($file);
        } catch (\Throwable) {
            // A failed transaction must not add the duplicate row.
        }
        $this->assertDatabaseCount('uacs', 2);
    }

    public function test_large_csv_import_processes_many_rows(): void
    {
        $rows = ['NORSU Object Code,NORSU Sub-Object Code,UACS Object Code,UACS Sub-Object Code,RCA Code,Account Title'];
        for ($i = 1; $i <= 1200; $i++) {
            $rows[] = sprintf('%012d,01,%010d,02,%08d,Account %d', $i, $i, $i, $i);
        }

        $count = app(UACSService::class)->import($this->csvFile(implode("\n", $rows)));

        $this->assertSame(1200, $count);
        $this->assertSame(1200, UACS::count());
    }

    private function csvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'uacs-');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'uacs.csv', 'text/csv', null, true);
    }

    /** @param array<int, array<int, string>> $rows */
    private function xlsxFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1), $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'uacs-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, 'uacs.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}

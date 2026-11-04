<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GraduateLedgerImportRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private string $header = 'student_name,course,school_year,semester_short,semester,units,transaction_date,reference_or_jev_number,particulars,tuition_per_unit_or_misc,ar_payment,amount,remarks,input_by';

    protected function setUp(): void
    {
        parent::setUp();
        Course::create(['course_code' => 'MBA', 'course_desc' => 'Master Business', 'course_college' => 'Graduate School']);
        AcademicTerm::create(['school_year' => '2025-2026', 'semester' => 'First Semester']);
    }

    public function test_valid_csv_imports_and_reports_counters(): void
    {
        $user = User::factory()->staff()->create();
        $csv = $this->csv([
            ['Student, One', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'CSV-1', 'Tuition', '0', 'AR', '100', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $response = $this->import($user, $csv, 'ledger.csv');

        $response->assertRedirect(route('graduate-ledger.index'))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 records imported'));
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'CSV-1', 'amount' => '100.00']);
    }

    public function test_valid_xlsx_imports_successfully(): void
    {
        $user = User::factory()->staff()->create();
        $path = tempnam(sys_get_temp_dir(), 'ledger').'.xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            str_getcsv($this->header),
            ['Student, XLSX', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'XLSX-1', 'Tuition', '0', 'AR', '200', '', ''],
        ]);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => new UploadedFile($path, 'ledger.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect();

        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'XLSX-1']);
        unlink($path);
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->post('/graduate-ledger/import', ['file' => UploadedFile::fake()->create('ledger.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    public function test_empty_file_is_handled_without_inserting_records(): void
    {
        $response = $this->import(User::factory()->staff()->create(), '', 'empty.csv');

        $response->assertRedirect()->assertSessionHas('success', fn (string $message): bool => str_contains($message, '0 records imported'));
        $this->assertDatabaseCount('graduate_ledgers', 0);
    }

    public function test_unknown_course_term_and_student_are_reported_with_row_numbers(): void
    {
        $user = User::factory()->staff()->create();
        $csv = $this->csv([
            ['Unknown, Student', 'UNKNOWN', '2099-2099', '1st Sem.', 'First Semester', '', '2026-01-01', 'BAD-1', 'Tuition', '0', 'AR', '100', '', ''],
        ]);

        $response = $this->import($user, $csv, 'errors.csv');
        $report = session('import_report');

        $response->assertRedirect();
        $this->assertSame(0, $report['imported']);
        $this->assertGreaterThan(0, $report['total_errors']);
        $this->assertSame(2, $report['errors'][0]['row']);
        $this->assertNotEmpty($report['errors'][0]['reason']);
    }

    public function test_duplicate_rows_in_file_and_database_are_skipped(): void
    {
        $user = User::factory()->staff()->create();
        $csv = $this->csv([
            ['Duplicate, Student', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'DUP-1', 'Tuition', '0', 'AR', '100', '', ''],
            ['Duplicate, Student', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'DUP-1', 'Tuition', '0', 'AR', '100', '', ''],
        ]);

        $this->import($user, $csv, 'duplicates.csv');
        $this->import($user, $csv, 'duplicates.csv');

        $this->assertSame(1, GraduateLedger::where('reference_number', 'DUP-1')->count());
    }

    public function test_classification_warnings_are_reported(): void
    {
        $user = User::factory()->staff()->create();
        $csv = $this->csv([
            ['Negative, Blank', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'WARN-1', 'Payment', '0', '', '-100', '', ''],
            ['Negative, AR', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'WARN-2', 'Payment', '0', 'AR', '-200', '', ''],
            ['Positive, Payment', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'WARN-3', 'Payment', '0', 'PAYMENT', '300', '', ''],
        ]);

        $this->import($user, $csv, 'warnings.csv')
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'negative amount'));

        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'WARN-1', 'entry_type' => 'payment']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'WARN-2', 'entry_type' => 'ar']);
        $this->assertDatabaseHas('graduate_ledgers', ['reference_number' => 'WARN-3', 'entry_type' => 'payment']);
    }

    public function test_missing_header_is_rejected(): void
    {
        $response = $this->import(User::factory()->staff()->create(), "Student, One,MBA,2025-2026,1st Sem.,First Semester,,,,,,,,\n", 'no-header.csv');

        $response->assertSessionHas('import_report');
        $this->assertSame(0, GraduateLedger::count());
    }

    public function test_reimporting_same_file_is_idempotent(): void
    {
        $user = User::factory()->staff()->create();
        $csv = $this->csv([
            ['Idempotent, Student', 'MBA', '2025-2026', '1st Sem.', 'First Semester', '', '2026-01-01', 'IDEMP-1', 'Tuition', '0', 'AR', '100', '', ''],
        ]);

        $this->import($user, $csv, 'same.csv');
        $second = $this->import($user, $csv, 'same.csv');

        $this->assertSame(1, GraduateLedger::where('reference_number', 'IDEMP-1')->count());
        $second->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'duplicate'));
    }

    /** @param list<array<int, mixed>> $rows */
    private function csv(array $rows): string
    {
        $lines = [$this->header];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($value): string => str_contains((string) $value, ',') ? '"'.$value.'"' : (string) $value, $row));
        }
        return implode("\n", $lines);
    }

    private function import(User $user, string $contents, string $filename): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post('/graduate-ledger/import', [
            'file' => UploadedFile::fake()->createWithContent($filename, $contents),
        ]);
    }
}

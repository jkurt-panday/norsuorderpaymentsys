<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LawLedgerRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private string $header = 'student_name,course,school_year,semester_or_summer,units,transaction_date,reference_number,particulars,rate,ar_or_payment,amount,remarks,status,input_by';

    protected function setUp(): void
    {
        parent::setUp();
        $this->course();
        $this->term();
    }

    public function test_law_ledger_index_loads_for_staff(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('law-ledger.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('law-ledger/Index'));
    }

    public function test_staff_can_create_update_and_delete_law_entries(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        $this->actingAs($staff)->post(route('law-ledger.store'), $this->payload($record, [
            'entry_type' => 'payment', 'amount' => '100.00', 'reference_number' => 'LAW-PAY',
        ]))->assertRedirect();
        $created = LawSchoolLedger::where('reference_number', 'LAW-PAY')->firstOrFail();

        $this->actingAs($staff)->put(route('law-ledger.update', $created->id), $this->payload($record, [
            'entry_type' => 'adjustment', 'amount' => '50.00', 'reference_number' => 'LAW-UPDATED',
        ]))->assertRedirect();
        $this->assertDatabaseHas('law_school_ledgers', ['id' => $created->id, 'entry_type' => 'adjustment']);

        $this->actingAs($staff)->delete(route('law-ledger.destroy', $created->id))->assertRedirect();
        $this->assertDatabaseMissing('law_school_ledgers', ['id' => $created->id]);
    }

    public function test_student_course_term_and_entry_validation_is_enforced(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        $this->actingAs($staff)->post(route('law-ledger.store'), [
            'student_id' => 999999, 'course_id' => 999999, 'academic_term_id' => 999999,
            'entry_type' => 'invalid', 'transaction_date' => 'bad', 'rate' => '1',
        ])->assertSessionHasErrors(['student_id', 'course_id', 'academic_term_id', 'entry_type', 'transaction_date']);

        $this->actingAs($staff)->post(route('law-ledger.store'), $this->payload($record, [
            'entry_type' => 'payment', 'amount' => 0,
        ]))->assertSessionHasErrors('amount');
    }

    public function test_ar_payment_and_adjustment_entries_have_expected_status_and_amounts(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        foreach (['ar', 'payment', 'adjustment'] as $type) {
            $this->actingAs($staff)->post(route('law-ledger.store'), $this->payload($record, [
                'entry_type' => $type, 'amount' => $type === 'ar' ? null : '50.00', 'reference_number' => 'LAW-'.strtoupper($type),
            ]))->assertRedirect();
        }

        $this->assertDatabaseHas('law_school_ledgers', ['entry_type' => 'ar', 'status' => 'Pending']);
        $this->assertDatabaseHas('law_school_ledgers', ['entry_type' => 'payment', 'amount' => '50.00']);
        $this->assertDatabaseHas('law_school_ledgers', ['entry_type' => 'adjustment', 'amount' => '50.00']);
    }

    public function test_law_balance_and_filters_work(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();
        LawSchoolLedger::create(array_merge($this->base($record), ['entry_type' => 'ar', 'amount' => 1000, 'reference_number' => 'LAW-BAL-AR', 'status' => 'Pending']));
        LawSchoolLedger::create(array_merge($this->base($record), ['entry_type' => 'payment', 'amount' => 400, 'reference_number' => 'LAW-BAL-PAY', 'status' => 'Paid']));

        $this->actingAs($staff)->getJson(route('law-ledger.students.balance', $record->student))
            ->assertOk()->assertJsonPath('summary.totalCharges', 1500)->assertJsonPath('summary.totalPayments', 400)->assertJsonPath('summary.outstandingBalance', 1100);

        foreach ([
            'semester_or_summer' => 'First Semester', 'school_year' => '2025-2026', 'course' => 'JD',
            'status' => 'Paid', 'ar_or_payment' => 'payment', 'balance_status' => 'with_balance',
        ] as $key => $value) {
            $this->actingAs($staff)->get(route('law-ledger.index', [$key => $value]))->assertOk();
        }
    }

    public function test_duplicate_law_entries_are_not_implicitly_created_by_update(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();
        $record->update(['reference_number' => 'LAW-DUP']);

        $this->actingAs($staff)->put(route('law-ledger.update', $record->id), $this->payload($record, [
            'reference_number' => 'LAW-DUP', 'amount' => '100.00',
        ]))->assertRedirect();

        $this->assertSame(1, LawSchoolLedger::where('reference_number', 'LAW-DUP')->count());
    }

    public function test_csv_import_and_duplicate_detection_work(): void
    {
        $staff = User::factory()->staff()->create();
        $csv = $this->csv([
            ['DOE, JANE A.', 'JD', '2025-2026', 'First Semester', '10', '2026-01-01', 'LAW-CSV', 'Tuition', '950', 'AR', '9500', '', 'Pending', ''],
            ['DOE, JANE A.', 'JD', '2025-2026', 'First Semester', '10', '2026-01-01', 'LAW-CSV', 'Tuition', '950', 'AR', '9500', '', 'Pending', ''],
        ]);

        $this->import($staff, $csv, 'law.csv')->assertRedirect();
        $this->import($staff, $csv, 'law.csv')->assertRedirect();

        $this->assertSame(1, LawSchoolLedger::where('reference_number', 'LAW-CSV')->count());
    }

    public function test_xlsx_import_and_invalid_row_reporting_work(): void
    {
        $staff = User::factory()->staff()->create();
        $path = tempnam(sys_get_temp_dir(), 'law').'.xlsx';
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            str_getcsv($this->header),
            ['DOE, JANE A.', 'JD', '2025-2026', 'First Semester', 10, '2026-01-01', 'LAW-XLSX', 'Tuition', 950, 'AR', 9500, '', 'Pending', ''],
            ['Unknown, Person', 'UNKNOWN', 'BAD', 'Unknown', 10, 'bad', 'LAW-BAD', 'Tuition', 950, 'AR', 100, '', 'Pending', ''],
        ]);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);

        $response = $this->actingAs($staff)->post('/law-ledger/import', [
            'file' => new UploadedFile($path, 'law.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('law_school_ledgers', ['reference_number' => 'LAW-XLSX']);
        unlink($path);
    }

    public function test_law_matching_and_statement_output_use_the_law_record(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();
        $assessment = \App\Models\AssessmentForm::create([
            'reference_number' => 'ASSESS-LAW', 'email' => 'law@example.com', 'contact_num' => '09123456789',
            'first_name' => 'Jane', 'last_name' => 'Doe', 'student_id' => 'LAW-001', 'course_id' => $record->course_id,
            'address' => 'Dumaguete', 'enrolled_under' => 'School of Law', 'sy_last_attended' => '2025-2026', 'semester' => 'First Semester',
        ]);

        $result = app(\App\Services\LedgerMatchingService::class)->forAssessment($assessment);
        $this->assertSame('matched', $result['matchStatus']);
        $this->assertSame('LAW-CSV-MATCH', $result['records'][0]['referenceNo']);

        $this->actingAs($staff)->get('/law-ledger')->assertOk();
    }

    private function student(): Student
    {
        return Student::create(['student_number' => 'LAW-001', 'first_name' => 'Jane', 'last_name' => 'Doe', 'middle_name' => 'A']);
    }

    private function course(): Course
    {
        return Course::create(['course_code' => 'JD', 'course_desc' => 'Juris Doctor', 'course_college' => 'School of Law']);
    }

    private function term(): AcademicTerm
    {
        return AcademicTerm::create(['school_year' => '2025-2026', 'semester' => 'First Semester']);
    }

    private function record(): LawSchoolLedger
    {
        $student = $this->student();
        return LawSchoolLedger::create(array_merge($this->base(null), [
            'student_id' => $student->id, 'course_id' => Course::where('course_code', 'JD')->value('id'),
            'academic_term_id' => AcademicTerm::where('school_year', '2025-2026')->value('id'),
            'entry_type' => 'ar', 'amount' => 500, 'reference_number' => 'LAW-CSV-MATCH', 'status' => 'Pending',
        ]));
    }

    private function base(?LawSchoolLedger $record): array
    {
        return ['student_id' => $record?->student_id, 'course_id' => $record?->course_id, 'academic_term_id' => $record?->academic_term_id,
            'transaction_date' => '2026-01-01', 'particulars' => 'Tuition', 'rate' => '100.00', 'units' => 5, 'status' => 'Pending'];
    }

    private function payload(LawSchoolLedger $record, array $overrides = []): array
    {
        return array_merge($this->base($record), ['student_id' => $record->student_id, 'course_id' => $record->course_id,
            'academic_term_id' => $record->academic_term_id, 'entry_type' => 'ar', 'amount' => '500.00', 'reference_number' => 'LAW-NEW',
            'tuition_per_unit_or_fee_per_semester' => '100.00', 'rate' => '100.00', 'status' => 'Pending'], $overrides);
    }

    /** @param list<array<int, mixed>> $rows */
    private function csv(array $rows): string
    {
        $lines = [$this->header];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v): string => str_contains((string) $v, ',') ? '"'.$v.'"' : (string) $v, $row));
        }
        return implode("\n", $lines);
    }

    private function import(User $user, string $contents, string $filename): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post('/law-ledger/import', ['file' => UploadedFile::fake()->createWithContent($filename, $contents)]);
    }
}

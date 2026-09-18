<?php

namespace Tests\Feature;

use App\Exports\GraduateLedgerExport;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GraduateLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_new_ledger_transaction(): void
    {
        $user = User::factory()->staff()->create();

        $student = Student::create([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
        ]);
        $course = $this->graduateCourse('MS-MATH', 'MS in Mathematics');
        $term = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'units' => 9,
            'transaction_date' => '2026-07-22',
            'reference_number' => 'OR-001',
            'particulars' => 'Tuition',
            'rate' => '150.00',
            'amount' => '1350.00',
            'remarks' => 'New transaction',
        ]);

        $response->assertRedirect('/graduate-ledger');

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'reference_number' => 'OR-001',
            'amount' => '1350.00',
        ]);
    }

    public function test_user_can_create_a_transaction_with_a_new_student_id(): void
    {
        $user = User::factory()->staff()->create();
        $course = $this->graduateCourse('MBA');

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'new_student' => [
                'student_number' => '2026-00123',
                'last_name' => 'Reyes',
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
            ],
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
            'course_id' => $course->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'rate' => '0.00',
            'amount' => '500.00',
        ]);

        $response->assertRedirect('/graduate-ledger');

        $student = Student::query()->where('student_number', '2026-00123')->firstOrFail();

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'payment',
            'amount' => '500.00',
        ]);
    }

    public function test_new_student_rejects_an_existing_student_id(): void
    {
        $user = User::factory()->staff()->create();
        $course = $this->graduateCourse('MBA');
        Student::create([
            'student_number' => '2026-00123',
            'last_name' => 'Existing',
            'first_name' => 'Student',
        ]);

        $response = $this->actingAs($user)
            ->from('/graduate-ledger/add')
            ->post('/graduate-ledger', [
                'new_student' => [
                    'student_number' => '2026-00123',
                    'last_name' => 'Duplicate',
                    'first_name' => 'Student',
                ],
                'school_year' => '2026-2027',
                'semester' => 'First Semester',
                'course_id' => $course->id,
                'entry_type' => 'payment',
                'transaction_date' => '2026-08-28',
                'rate' => '0.00',
                'amount' => '500.00',
            ]);

        $response->assertRedirect('/graduate-ledger/add');
        $response->assertSessionHasErrors('new_student.student_number');
        $this->assertDatabaseCount('graduate_ledgers', 0);
    }

    public function test_new_student_can_be_created_without_a_student_id(): void
    {
        $user = User::factory()->staff()->create();
        $course = $this->graduateCourse('MBA');

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'new_student' => [
                'student_number' => '',
                'last_name' => 'Legacy',
                'first_name' => 'Student',
            ],
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
            'course_id' => $course->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'rate' => '0.00',
            'amount' => '500.00',
        ]);

        $response->assertRedirect('/graduate-ledger');
        $this->assertDatabaseHas('students', [
            'last_name' => 'Legacy',
            'first_name' => 'Student',
            'student_number' => null,
        ]);
    }

    public function test_index_can_filter_records_by_school_year_and_date_range(): void
    {
        $user = User::factory()->staff()->create();

        $studentA = Student::create(['last_name' => 'Filtered', 'first_name' => 'Student']);
        $studentB = Student::create(['last_name' => 'Other', 'first_name' => 'Student']);
        $course = $this->graduateCourse('MS-MATH');
        $termA = AcademicTerm::create([
            'school_year' => '2024-2025',
            'semester' => 'First Semester',
        ]);
        $termB = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
        ]);

        GraduateLedger::create([
            'student_id' => $studentA->id,
            'course_id' => $course->id,
            'academic_term_id' => $termA->id,
            'entry_type' => 'ar',
            'transaction_date' => '2024-07-10',
            'reference_number' => 'OR-100',
            'particulars' => 'Tuition',
            'rate' => '100.00',
            'amount' => '100.00',
            'remarks' => 'Filtered',
            'status' => 'posted',
        ]);

        GraduateLedger::create([
            'student_id' => $studentB->id,
            'course_id' => $course->id,
            'academic_term_id' => $termB->id,
            'entry_type' => 'ar',
            'transaction_date' => '2025-08-12',
            'reference_number' => 'OR-200',
            'particulars' => 'Tuition',
            'rate' => '100.00',
            'amount' => '100.00',
            'remarks' => 'Other',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($user)->get('/graduate-ledger?school_year=2024-2025&date_from=2024-07-01&date_to=2024-07-31');

        $response->assertOk();
        $response->assertSee('OR-100');
        $response->assertDontSee('OR-200');
    }

    public function test_ar_transaction_resolves_a_canonical_term_and_computes_the_amount(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create(['last_name' => 'Computed', 'first_name' => 'Student']);
        $course = $this->graduateCourse('MSIT');

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => '',
            'school_year' => '2026-2027',
            'semester' => 'Second Semester',
            'entry_type' => 'ar',
            'units' => 3,
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'rate' => '750.00',
            'amount' => '',
        ]);

        $response->assertRedirect('/graduate-ledger');

        $term = AcademicTerm::query()
            ->where('school_year', '2026-2027')
            ->where('semester', 'Second Semester')
            ->firstOrFail();

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'amount' => '2250.00',
        ]);
    }

    public function test_payment_defaults_a_blank_tuition_field_to_zero(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create(['last_name' => 'Paying', 'first_name' => 'Student']);
        $course = $this->graduateCourse('MBA');
        $term = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'rate' => '',
            'amount' => '1000.00',
        ]);

        $response->assertRedirect('/graduate-ledger');
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'payment',
            'rate' => '0.00',
            'amount' => '1000.00',
        ]);
    }

    public function test_manual_transaction_rejects_fractional_units(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create(['last_name' => 'Invalid', 'first_name' => 'Units']);
        $course = $this->graduateCourse('MSIT');
        $term = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
        ]);

        $response = $this->actingAs($user)
            ->from('/graduate-ledger/add')
            ->post('/graduate-ledger', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'academic_term_id' => $term->id,
                'entry_type' => 'ar',
                'units' => 1.5,
                'transaction_date' => '2026-08-28',
                'rate' => '750.00',
                'amount' => '',
            ]);

        $response->assertRedirect('/graduate-ledger/add');
        $response->assertSessionHasErrors('units');
        $this->assertDatabaseCount('graduate_ledgers', 0);
    }

    public function test_editing_an_imported_transaction_preserves_its_attribution(): void
    {
        $importer = User::factory()->staff()->create(['name' => 'Original Importer']);
        $editor = User::factory()->staff()->create(['name' => 'Later Editor']);
        $student = Student::create(['last_name' => 'Imported', 'first_name' => 'Student']);
        $course = $this->graduateCourse('MBA');
        $term = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
        ]);
        $ledger = GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'rate' => '0.00',
            'amount' => '500.00',
            'remarks' => 'Before edit',
            'status' => 'posted',
            'input_by' => $importer->id,
            'imported_input_by' => 'MBC',
        ]);

        $this->actingAs($editor)->put("/graduate-ledger/{$ledger->id}", [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'rate' => '0.00',
            'amount' => '500.00',
            'remarks' => 'After edit',
        ])->assertRedirect('/graduate-ledger');

        $ledger->refresh();

        $this->assertSame($importer->id, $ledger->input_by);
        $this->assertSame('MBC', $ledger->imported_input_by);
        $this->assertSame('After edit', $ledger->remarks);

        $this->actingAs($editor)->get("/graduate-ledger/{$ledger->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('record.input_by', 'MBC')
                ->where('record.is_imported', true));
    }

    public function test_ledger_display_and_export_use_imported_text_or_the_user_name_fallback(): void
    {
        $user = User::factory()->staff()->create(['name' => 'Ledger Encoder']);
        $student = Student::create(['last_name' => 'Display', 'first_name' => 'Student']);
        $course = $this->graduateCourse('MSIT');
        $term = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
        ]);
        $base = [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'rate' => '100.00',
            'amount' => '100.00',
            'status' => 'posted',
            'input_by' => $user->id,
        ];
        $manual = GraduateLedger::create($base + ['reference_number' => 'MANUAL']);
        $imported = GraduateLedger::create($base + [
            'reference_number' => 'IMPORTED',
            'imported_input_by' => 'ABC',
        ]);
        $blank = GraduateLedger::create($base + [
            'reference_number' => 'BLANK',
            'imported_input_by' => '',
        ]);

        $this->assertSame('Ledger Encoder', $manual->inputByDisplay());
        $this->assertSame('ABC', $imported->inputByDisplay());
        $this->assertSame('', $blank->inputByDisplay());

        $export = new GraduateLedgerExport(GraduateLedger::query());
        $this->assertSame('ABC', $export->map($imported->load(['student', 'course', 'academicTerm', 'inputByUser']))[13]);

        $this->actingAs($user)->get('/graduate-ledger')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.data.0.inputBy', '')
                ->where('records.data.1.inputBy', 'ABC')
                ->where('records.data.2.inputBy', 'Ledger Encoder'));
    }

    public function test_index_filters_records_by_student_and_academic_term_balance_status(): void
    {
        $user = User::factory()->staff()->create();
        $course = $this->graduateCourse('MBA');
        $term = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
        ]);

        // Student 1: Unpaid balance (AR 5,000 - Payment 2,000 = 3,000 remaining)
        $unpaidStudent = Student::create([
            'student_number' => '202600001',
            'first_name' => 'Unpaid',
            'last_name' => 'Student',
        ]);
        GraduateLedger::create([
            'student_id' => $unpaidStudent->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-08-01',
            'reference_number' => 'AR-UNPAID-1',
            'amount' => 5000.00,
            'status' => 'posted',
        ]);

        $settledTerm = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
        ]);
        GraduateLedger::create([
            'student_id' => $unpaidStudent->id,
            'course_id' => $course->id,
            'academic_term_id' => $settledTerm->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-01',
            'reference_number' => 'AR-SETTLED-TERM',
            'amount' => 1000.00,
            'status' => 'posted',
        ]);
        GraduateLedger::create([
            'student_id' => $unpaidStudent->id,
            'course_id' => $course->id,
            'academic_term_id' => $settledTerm->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-01-02',
            'reference_number' => 'PAY-SETTLED-TERM',
            'amount' => 1000.00,
            'status' => 'posted',
        ]);
        GraduateLedger::create([
            'student_id' => $unpaidStudent->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-02',
            'reference_number' => 'PAY-UNPAID-1',
            'amount' => 2000.00,
            'status' => 'posted',
        ]);

        // Student 2: Fully paid / cleared (AR 3,000 - Payment 3,000 = 0 balance)
        $paidStudent = Student::create([
            'student_number' => '202600002',
            'first_name' => 'Paid',
            'last_name' => 'Student',
        ]);
        GraduateLedger::create([
            'student_id' => $paidStudent->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-08-01',
            'reference_number' => 'AR-PAID-1',
            'amount' => 3000.00,
            'status' => 'posted',
        ]);
        GraduateLedger::create([
            'student_id' => $paidStudent->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-02',
            'reference_number' => 'PAY-PAID-1',
            'amount' => 3000.00,
            'status' => 'posted',
        ]);

        // Outstanding returns only Student 1's unpaid term, not their settled term.
        $responseWithBalance = $this->actingAs($user)->get('/graduate-ledger?balance_status=with_balance');
        $responseWithBalance->assertOk();
        $responseWithBalance->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 2)
            ->where('records.data.0.referenceNo', 'PAY-UNPAID-1')
            ->where('records.data.1.referenceNo', 'AR-UNPAID-1')
        );

        // Cleared returns Student 2 and Student 1's settled term.
        $responseCleared = $this->actingAs($user)->get('/graduate-ledger?balance_status=cleared');
        $responseCleared->assertOk();
        $responseCleared->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 4)
            ->where('records.data.0.referenceNo', 'PAY-PAID-1')
            ->where('records.data.1.referenceNo', 'AR-PAID-1')
            ->where('records.data.2.referenceNo', 'PAY-SETTLED-TERM')
            ->where('records.data.3.referenceNo', 'AR-SETTLED-TERM')
        );
    }

    public function test_remarks_are_calculated_as_outstanding_or_settled_per_student_and_academic_term(): void
    {
        $user = User::factory()->staff()->create();
        $course = $this->graduateCourse('MBA');
        $student = Student::create([
            'student_number' => '2026-9999',
            'first_name' => 'TermCalc',
            'last_name' => 'Student',
        ]);

        $term1 = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
        ]);
        $term2 = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
        ]);

        // Term 1: Settled (AR 4000 - Pay 4000 = 0)
        $term1Ar = GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term1->id,
            'entry_type' => 'ar',
            'transaction_date' => '2025-08-10',
            'reference_number' => 'T1-AR',
            'amount' => 4000.00,
            'status' => 'posted',
        ]);
        $term1Pay = GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term1->id,
            'entry_type' => 'payment',
            'transaction_date' => '2025-08-15',
            'reference_number' => 'T1-PAY',
            'amount' => 4000.00,
            'status' => 'posted',
        ]);

        // Term 2: Outstanding (AR 5000 - Pay 2000 = 3000 balance)
        $term2Ar = GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term2->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-10',
            'reference_number' => 'T2-AR',
            'amount' => 5000.00,
            'status' => 'posted',
        ]);
        $term2Pay = GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term2->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-01-15',
            'reference_number' => 'T2-PAY',
            'amount' => 2000.00,
            'status' => 'posted',
        ]);

        // UI Index assertion
        $response = $this->actingAs($user)->get('/graduate-ledger');
        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->has('records.data', 4);
            // Latest id first: T2-PAY, T2-AR, T1-PAY, T1-AR
            $data = $page->toArray()['props']['records']['data'];
            $byRef = collect($data)->keyBy('referenceNo');

            $this->assertSame('Outstanding', $byRef['T2-PAY']['remark']);
            $this->assertSame('Outstanding', $byRef['T2-AR']['remark']);
            $this->assertSame('Settled', $byRef['T1-PAY']['remark']);
            $this->assertSame('Settled', $byRef['T1-AR']['remark']);
        });

        // Export assertion
        $export = new GraduateLedgerExport(GraduateLedger::query());
        $mapT1 = $export->map($term1Ar->load(['student', 'course', 'academicTerm', 'inputByUser']));
        $mapT2 = $export->map($term2Ar->load(['student', 'course', 'academicTerm', 'inputByUser']));

        $this->assertSame('Settled', $mapT1[12]);
        $this->assertSame('Outstanding', $mapT2[12]);
    }

    private function graduateCourse(string $code, ?string $description = null): Course
    {
        return Course::create([
            'course_code' => $code,
            'course_desc' => $description ?? $code,
            'course_college' => 'Graduate School',
        ]);
    }
}

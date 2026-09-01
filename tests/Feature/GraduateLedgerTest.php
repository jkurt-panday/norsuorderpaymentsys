<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraduateLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_new_ledger_transaction(): void
    {
        $user = User::factory()->create();

        $student = Student::create([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'raw_name_from_csv' => 'Dela Cruz, Juan S.',
        ]);
        $course = Course::create(['code' => 'MS-MATH', 'title' => 'MS in Mathematics']);
        $term = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'units' => 9,
            'transaction_date' => '2026-07-22',
            'reference_or_jev_number' => 'OR-001',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '150.00',
            'amount' => '1350.00',
            'remarks' => 'New transaction',
            'input_by' => 'Admin',
        ]);

        $response->assertRedirect('/graduate-ledger');

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'reference_or_jev_number' => 'OR-001',
            'amount' => '1350.00',
        ]);
    }

    public function test_user_can_create_a_transaction_with_a_new_student_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'new_student' => [
                'student_number' => '2026-00123',
                'last_name' => 'Reyes',
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
            ],
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '0.00',
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
        $user = User::factory()->create();
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
                'entry_type' => 'payment',
                'transaction_date' => '2026-08-28',
                'tuition_per_unit_or_misc' => '0.00',
                'amount' => '500.00',
            ]);

        $response->assertRedirect('/graduate-ledger/add');
        $response->assertSessionHasErrors('new_student.student_number');
        $this->assertDatabaseCount('graduate_ledgers', 0);
    }

    public function test_new_student_can_be_created_without_a_student_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'new_student' => [
                'student_number' => '',
                'last_name' => 'Legacy',
                'first_name' => 'Student',
            ],
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'tuition_per_unit_or_misc' => '0.00',
            'amount' => '500.00',
        ]);

        $response->assertRedirect('/graduate-ledger');
        $this->assertDatabaseHas('graduate_student', [
            'last_name' => 'Legacy',
            'first_name' => 'Student',
            'student_number' => null,
        ]);
    }

    public function test_index_can_filter_records_by_school_year_and_date_range(): void
    {
        $user = User::factory()->create();

        $studentA = Student::create(['last_name' => 'Filtered', 'first_name' => 'Student']);
        $studentB = Student::create(['last_name' => 'Other', 'first_name' => 'Student']);
        $course = Course::create(['code' => 'MS-MATH']);
        $termA = AcademicTerm::create([
            'school_year' => '2024-2025',
            'semester' => 'First Semester',
            'sort_order' => 1,
        ]);
        $termB = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
            'sort_order' => 2,
        ]);

        GraduateLedger::create([
            'student_id' => $studentA->id,
            'course_id' => $course->id,
            'academic_term_id' => $termA->id,
            'entry_type' => 'ar',
            'transaction_date' => '2024-07-10',
            'reference_or_jev_number' => 'OR-100',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '100.00',
            'amount' => '100.00',
            'remarks' => 'Filtered',
            'input_by' => 'Admin',
        ]);

        GraduateLedger::create([
            'student_id' => $studentB->id,
            'course_id' => $course->id,
            'academic_term_id' => $termB->id,
            'entry_type' => 'ar',
            'transaction_date' => '2025-08-12',
            'reference_or_jev_number' => 'OR-200',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '100.00',
            'amount' => '100.00',
            'remarks' => 'Other',
            'input_by' => 'Admin',
        ]);

        $response = $this->actingAs($user)->get('/graduate-ledger?school_year=2024-2025&date_from=2024-07-01&date_to=2024-07-31');

        $response->assertOk();
        $response->assertSee('OR-100');
        $response->assertDontSee('OR-200');
    }

    public function test_ar_transaction_resolves_a_canonical_term_and_computes_the_amount(): void
    {
        $user = User::factory()->create();
        $student = Student::create(['last_name' => 'Computed', 'first_name' => 'Student']);

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_id' => $student->id,
            'academic_term_id' => '',
            'school_year' => '2026-2027',
            'semester' => 'Second Semester',
            'entry_type' => 'ar',
            'units' => 3,
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '750.00',
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
        $user = User::factory()->create();
        $student = Student::create(['last_name' => 'Paying', 'first_name' => 'Student']);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_id' => $student->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-08-28',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '',
            'amount' => '1000.00',
        ]);

        $response->assertRedirect('/graduate-ledger');
        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'payment',
            'tuition_per_unit_or_misc' => '0.00',
            'amount' => '1000.00',
        ]);
    }

    public function test_manual_transaction_rejects_fractional_units(): void
    {
        $user = User::factory()->create();
        $student = Student::create(['last_name' => 'Invalid', 'first_name' => 'Units']);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->from('/graduate-ledger/add')
            ->post('/graduate-ledger', [
                'student_id' => $student->id,
                'academic_term_id' => $term->id,
                'entry_type' => 'ar',
                'units' => 1.5,
                'transaction_date' => '2026-08-28',
                'tuition_per_unit_or_misc' => '750.00',
                'amount' => '',
            ]);

        $response->assertRedirect('/graduate-ledger/add');
        $response->assertSessionHasErrors('units');
        $this->assertDatabaseCount('graduate_ledgers', 0);
    }
}

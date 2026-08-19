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
            'last_name'         => 'Dela Cruz',
            'first_name'        => 'Juan',
            'middle_name'       => 'Santos',
            'raw_name_from_csv' => 'Dela Cruz, Juan S.',
        ]);
        $course = Course::create(['code' => 'MS-MATH', 'title' => 'MS in Mathematics']);
        $term = AcademicTerm::create([
            'school_year'    => '2025-2026',
            'semester_short' => '1st Sem.',
            'semester'       => 'First Semester',
            'sort_order'     => 1,
        ]);

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_id'               => $student->id,
            'course_id'                => $course->id,
            'academic_term_id'         => $term->id,
            'entry_type'               => 'ar',
            'units'                    => 9,
            'transaction_date'         => '2026-07-22',
            'reference_or_jev_number'  => 'OR-001',
            'particulars'              => 'Tuition',
            'tuition_per_unit_or_misc' => '150.00',
            'amount'                   => '1350.00',
            'remarks'                  => 'New transaction',
            'input_by'                 => 'Admin',
        ]);

        $response->assertRedirect('/graduate-ledger');

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_id'               => $student->id,
            'course_id'                => $course->id,
            'academic_term_id'         => $term->id,
            'entry_type'               => 'ar',
            'reference_or_jev_number'  => 'OR-001',
            'amount'                   => '1350.00',
        ]);
    }

    public function test_index_can_filter_records_by_school_year_and_date_range(): void
    {
        $user = User::factory()->create();

        $studentA = Student::create(['last_name' => 'Filtered', 'first_name' => 'Student']);
        $studentB = Student::create(['last_name' => 'Other', 'first_name' => 'Student']);
        $course   = Course::create(['code' => 'MS-MATH']);
        $termA = AcademicTerm::create([
            'school_year'    => '2024-2025',
            'semester_short' => '1st Sem.',
            'semester'       => 'First Semester',
            'sort_order'     => 1,
        ]);
        $termB = AcademicTerm::create([
            'school_year'    => '2025-2026',
            'semester_short' => '2nd Sem.',
            'semester'       => 'Second Semester',
            'sort_order'     => 2,
        ]);

        GraduateLedger::create([
            'student_id'               => $studentA->id,
            'course_id'                => $course->id,
            'academic_term_id'         => $termA->id,
            'entry_type'               => 'ar',
            'transaction_date'         => '2024-07-10',
            'reference_or_jev_number'  => 'OR-100',
            'particulars'              => 'Tuition',
            'tuition_per_unit_or_misc' => '100.00',
            'amount'                   => '100.00',
            'remarks'                  => 'Filtered',
            'input_by'                 => 'Admin',
        ]);

        GraduateLedger::create([
            'student_id'               => $studentB->id,
            'course_id'                => $course->id,
            'academic_term_id'         => $termB->id,
            'entry_type'               => 'ar',
            'transaction_date'         => '2025-08-12',
            'reference_or_jev_number'  => 'OR-200',
            'particulars'              => 'Tuition',
            'tuition_per_unit_or_misc' => '100.00',
            'amount'                   => '100.00',
            'remarks'                  => 'Other',
            'input_by'                 => 'Admin',
        ]);

        $response = $this->actingAs($user)->get('/graduate-ledger?school_year=2024-2025&date_from=2024-07-01&date_to=2024-07-31');

        $response->assertOk();
        $response->assertSee('OR-100');
        $response->assertDontSee('OR-200');
    }
}

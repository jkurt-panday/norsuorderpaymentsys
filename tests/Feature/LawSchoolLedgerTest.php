<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawSchoolLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_a_students_law_ledger_balance_history(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '202600111',
            'email' => 'law.student@example.com',
            'contact_num' => '09171234567',
            'last_name' => 'Reyes',
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
        ]);
        $course = Course::create([
            'course_code' => 'JD',
            'course_desc' => 'Juris Doctor',
            'course_college' => 'School of Law',
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
        ]);

        $assessment = LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'units' => 10,
            'transaction_date' => '2026-01-10',
            'reference_number' => 'LAW-AR-001',
            'particulars' => 'Tuition',
            'rate' => 950,
            'amount' => 9500,
            'status' => 'Pending',
        ]);
        LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'payment',
            'transaction_date' => '2026-01-20',
            'reference_number' => 'LAW-PAY-001',
            'particulars' => 'Tuition',
            'amount' => 6000,
            'status' => 'Paid',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/law-ledger/students/{$student->id}/balance");

        $response->assertOk()
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('student.studentNumber', '202600111')
            ->assertJsonPath('student.name', 'Reyes, Maria S.')
            ->assertJsonPath('student.email', 'law.student@example.com')
            ->assertJsonPath('student.contactNumber', '09171234567')
            ->assertJsonPath('student.course', 'JD')
            ->assertJsonPath('summary.totalCharges', 9500)
            ->assertJsonPath('summary.totalPayments', 6000)
            ->assertJsonPath('summary.outstandingBalance', 3500)
            ->assertJsonCount(2, 'transactions')
            ->assertJsonPath('transactions.0.id', $assessment->id)
            ->assertJsonPath('transactions.0.arPayment', 'AR')
            ->assertJsonPath('transactions.1.arPayment', 'Payment');
    }

    public function test_add_transaction_form_prefills_student_and_entry_type_from_query(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create([
            'last_name' => 'Reyes',
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
        ]);

        $response = $this->actingAs($user)->get('/law-ledger/add?student_id='.$student->id.'&entry_type=payment');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('law-ledger/AddTransaction')
            ->where('selectedStudentId', $student->id)
            ->where('defaultEntryType', 'payment'));
    }
}

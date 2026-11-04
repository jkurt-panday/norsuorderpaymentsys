<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use App\Http\Controllers\GraduateLedgerController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class LedgerBalanceStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_balance_sums_charges_payments_and_adjustments_per_term(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create(['student_number' => 'BAL-001', 'last_name' => 'Balance', 'first_name' => 'Test']);
        $course = $this->course('MBA');
        $termOne = $this->term('2025-2026', 'First Semester');
        $termTwo = $this->term('2025-2026', 'Second Semester');

        $this->ledger($student, $course, $termOne, 'ar', '100.10', 'T1-CHARGE');
        $this->ledger($student, $course, $termOne, 'payment', '40.05', 'T1-PAYMENT');
        $this->ledger($student, $course, $termOne, 'adjustment', '10.05', 'T1-ADJUSTMENT');
        $this->ledger($student, $course, $termTwo, 'ar', '50.00', 'T2-CHARGE');

        $response = $this->actingAs($user)->getJson("/graduate-ledger/students/{$student->id}/balance");

        $response->assertOk()
            ->assertJsonPath('summary.totalCharges', 150.10)
            ->assertJsonPath('summary.totalPayments', 40.05)
            ->assertJsonPath('summary.totalAdjustments', 10.05)
            ->assertJsonPath('summary.outstandingBalance', 100)
            ->assertJsonCount(4, 'transactions');

        $transactions = $response->json('transactions');
        $this->assertSame(['T2-CHARGE', 'T1-ADJUSTMENT', 'T1-PAYMENT', 'T1-CHARGE'], array_column($transactions, 'referenceNo'));
        $this->assertSame('Outstanding', $transactions[0]['remark']);
        $this->assertSame('Outstanding', $transactions[1]['remark']);
    }

    public function test_zero_and_negative_term_balances_are_classified_as_settled_and_terms_are_independent(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create(['last_name' => 'Status', 'first_name' => 'Test']);
        $course = $this->course('MSIT');
        $zero = $this->term('2024-2025', 'First Semester');
        $negative = $this->term('2024-2025', 'Second Semester');
        $outstanding = $this->term('2025-2026', 'First Semester');

        $this->ledger($student, $course, $zero, 'ar', '100.00', 'ZERO-AR');
        $this->ledger($student, $course, $zero, 'payment', '100.00', 'ZERO-PAY');
        $this->ledger($student, $course, $negative, 'ar', '100.00', 'NEG-AR');
        $this->ledger($student, $course, $negative, 'payment', '125.00', 'NEG-PAY');
        $this->ledger($student, $course, $outstanding, 'ar', '200.00', 'OUT-AR');

        $response = $this->actingAs($user)->getJson("/graduate-ledger/students/{$student->id}/balance");
        $byReference = collect($response->json('transactions'))->keyBy('referenceNo');

        $this->assertSame('Settled', $byReference['ZERO-AR']['remark']);
        $this->assertSame('Settled', $byReference['ZERO-PAY']['remark']);
        $this->assertSame('Settled', $byReference['NEG-AR']['remark']);
        $this->assertSame('Settled', $byReference['NEG-PAY']['remark']);
        $this->assertSame('Outstanding', $byReference['OUT-AR']['remark']);
        $this->assertSame(175, $response->json('summary.outstandingBalance'));
    }

    public function test_statement_contains_only_selected_student_records_and_pdf_totals(): void
    {
        Pdf::fake();

        $user = User::factory()->staff()->create();
        $student = Student::create(['student_number' => 'PDF-001', 'last_name' => 'Statement', 'first_name' => 'Target']);
        $other = Student::create(['student_number' => 'PDF-002', 'last_name' => 'Unrelated', 'first_name' => 'Student']);
        $course = $this->course('MEd');
        $term = $this->term('2026-2027', 'First Semester');
        $otherTerm = $this->term('2026-2027', 'Second Semester');

        $this->ledger($student, $course, $term, 'ar', '123.456', 'PDF-CHARGE', '2026-08-20');
        $this->ledger($student, $course, $term, 'payment', '23.456', 'PDF-PAYMENT', '2026-08-21');
        $this->ledger($student, $course, $otherTerm, 'ar', '999.99', 'PDF-OTHER-TERM');
        $this->ledger($other, $course, $term, 'ar', '888.88', 'PDF-OTHER-STUDENT');

        $pdf = app(GraduateLedgerController::class)->generatePdf(
            Request::create('/graduate-ledger/pdf', 'GET', [
                'student_id' => $student->id,
                'school_year' => '2026-2027',
                'semester' => 'First Semester',
            ]),
        );
        $pdf->generatePdfContent();

        Pdf::assertSee(['PDF-CHARGE', 'PDF-PAYMENT', '100.00']);
        Pdf::assertDontSee(['PDF-OTHER-TERM', 'PDF-OTHER-STUDENT', '999.99', '888.88']);
    }

    public function test_statement_handles_no_records_and_missing_relationships(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create(['last_name' => 'Empty', 'first_name' => 'Statement']);

        $response = $this->actingAs($user)->getJson("/graduate-ledger/students/{$student->id}/balance");
        $response->assertOk()
            ->assertJsonPath('summary.totalCharges', 0)
            ->assertJsonPath('summary.totalPayments', 0)
            ->assertJsonPath('summary.totalAdjustments', 0)
            ->assertJsonPath('summary.outstandingBalance', 0)
            ->assertJsonCount(0, 'transactions');

        GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => null,
            'academic_term_id' => null,
            'entry_type' => 'ar',
            'transaction_date' => '2026-09-01',
            'reference_number' => 'NO-RELATIONSHIPS',
            'particulars' => 'Miscellaneous',
            'rate' => 0,
            'amount' => 25,
            'status' => 'posted',
        ]);

        $this->actingAs($user)->getJson("/graduate-ledger/students/{$student->id}/balance")
            ->assertOk()
            ->assertJsonPath('transactions.0.referenceNo', 'NO-RELATIONSHIPS')
            ->assertJsonPath('transactions.0.remark', 'Outstanding');
    }

    private function course(string $code): Course
    {
        return Course::create([
            'course_code' => $code,
            'course_desc' => $code,
            'course_college' => 'Graduate School',
        ]);
    }

    private function term(string $schoolYear, string $semester): AcademicTerm
    {
        return AcademicTerm::create(['school_year' => $schoolYear, 'semester' => $semester]);
    }

    private function ledger(
        Student $student,
        Course $course,
        AcademicTerm $term,
        string $entryType,
        string $amount,
        string $reference,
        string $date = '2026-08-01',
    ): GraduateLedger {
        return GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => $entryType,
            'transaction_date' => $date,
            'reference_number' => $reference,
            'particulars' => ucfirst($entryType),
            'rate' => 0,
            'amount' => $amount,
            'status' => 'posted',
        ]);
    }
}

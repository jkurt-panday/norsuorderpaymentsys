<?php

namespace Tests\Feature;

use App\Jobs\SendLawLedgerStatementEmail;
use App\Mail\LawSchoolLedgerStatementMail;
use App\Models\AcademicTerm;
use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\LawSchoolLedger;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\Student;
use App\Models\UACS;
use App\Models\User;
use App\Services\CashierLedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPdf\Facades\Pdf;
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

    public function test_latin_honor_discount_keeps_assessment_amount_and_creates_adjustment(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '202600222',
            'last_name' => 'Santos',
            'first_name' => 'Ana',
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
            'reference_number' => 'LAW-AR-002',
            'particulars' => 'Tuition',
            'rate' => 1000,
            'amount' => 10000,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($user)
            ->from('/law-ledger')
            ->post("/law-ledger/{$assessment->id}/apply-honor", [
                'latin_honor' => 'CUM_LAUDE',
            ]);

        $response->assertRedirect('/law-ledger');

        $assessment->refresh();
        $this->assertSame('CUM_LAUDE', $assessment->latin_honor);
        $this->assertEquals(5000.00, (float) $assessment->discount_amount);
        $this->assertEquals(10000.00, (float) $assessment->amount);
        $this->assertSame('Pending', $assessment->status);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'adjustment',
            'reference_number' => 'HONOR-CUM-'.$assessment->id,
            'amount' => 5000.00,
            'particulars' => 'Cum Laude Scholarship (50%)',
            'status' => 'Applied',
            'latin_honor' => 'CUM_LAUDE',
        ]);

        $balanceResponse = $this->actingAs($user)
            ->getJson("/law-ledger/students/{$student->id}/balance");

        $balanceResponse->assertOk()
            ->assertJsonPath('summary.totalCharges', 10000)
            ->assertJsonPath('summary.totalPayments', 5000)
            ->assertJsonPath('summary.outstandingBalance', 5000);
    }

    public function test_magna_cum_laude_latin_honor_discount_is_seventy_five_percent(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '202600223',
            'last_name' => 'Cruz',
            'first_name' => 'Ben',
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
            'reference_number' => 'LAW-AR-003',
            'particulars' => 'Tuition',
            'rate' => 1000,
            'amount' => 10000,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($user)
            ->from('/law-ledger')
            ->post("/law-ledger/{$assessment->id}/apply-honor", [
                'latin_honor' => 'MAGNA',
            ]);

        $response->assertRedirect('/law-ledger');

        $assessment->refresh();
        $this->assertSame('MAGNA', $assessment->latin_honor);
        $this->assertEquals(7500.00, (float) $assessment->discount_amount);
        $this->assertEquals(10000.00, (float) $assessment->amount);

        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'adjustment',
            'reference_number' => 'HONOR-MAG-'.$assessment->id,
            'amount' => 7500.00,
            'particulars' => 'Magna Cum Laude Scholarship (75%)',
            'status' => 'Applied',
            'latin_honor' => 'MAGNA',
        ]);
    }

    public function test_law_ledger_queues_statement_emails_using_the_current_filters(): void
    {
        Bus::fake();

        $user = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => 'LAW-EMAIL-1',
            'email' => 'law.email@example.com',
            'last_name' => 'Email',
            'first_name' => 'Target',
        ]);
        $otherStudent = Student::create([
            'student_number' => 'LAW-EMAIL-2',
            'email' => 'other.law.email@example.com',
            'last_name' => 'Email',
            'first_name' => 'Other',
        ]);
        $course = Course::create([
            'course_code' => 'JD',
            'course_desc' => 'Juris Doctor',
            'course_college' => 'School of Law',
        ]);
        $firstTerm = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
        ]);
        $secondTerm = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
        ]);

        LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $firstTerm->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-10',
            'reference_number' => 'LAW-EMAIL-AR',
            'particulars' => 'Tuition',
            'rate' => 1000,
            'amount' => 1000,
            'status' => 'Pending',
        ]);
        LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $secondTerm->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-02-10',
            'reference_number' => 'LAW-EMAIL-OTHER-TERM',
            'particulars' => 'Tuition',
            'rate' => 2000,
            'amount' => 2000,
            'status' => 'Pending',
        ]);
        LawSchoolLedger::create([
            'student_id' => $otherStudent->id,
            'course_id' => $course->id,
            'academic_term_id' => $firstTerm->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-10',
            'reference_number' => 'LAW-EMAIL-OTHER-STUDENT',
            'particulars' => 'Tuition',
            'rate' => 3000,
            'amount' => 3000,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($user)
            ->from('/law-ledger')
            ->post('/law-ledger/send-emails', [
                'school_year' => '2025-2026',
                'semester_or_summer' => '1st Sem',
                'student_ids' => [$student->id],
                'subject' => 'Law SOA',
                'note' => 'Please settle your balance.',
                'exam_period' => 'Midterm',
                'exam_deadline' => '2026-03-01',
            ]);

        $response->assertRedirect('/law-ledger')
            ->assertSessionHas('success', 'Queued SOA email for 1 student(s).');

        Bus::assertDispatched(SendLawLedgerStatementEmail::class, function (SendLawLedgerStatementEmail $job) use ($student): bool {
            return $job->studentId === $student->id
                && $job->schoolYear === '2025-2026'
                && $job->semester === 'First Semester'
                && $job->subject === 'Law SOA'
                && $job->note === 'Please settle your balance.'
                && $job->examPeriod === 'Midterm'
                && $job->examDeadline === '2026-03-01';
        });
        Bus::assertNotDispatched(SendLawLedgerStatementEmail::class, function (SendLawLedgerStatementEmail $job) use ($otherStudent): bool {
            return $job->studentId === $otherStudent->id;
        });
    }

    public function test_queued_law_ledger_statement_job_generates_filtered_pdf_and_sends_email(): void
    {
        Pdf::fake();
        Mail::fake();

        $student = Student::create([
            'student_number' => 'LAW-JOB-1',
            'email' => 'law.job@example.com',
            'last_name' => 'Job',
            'first_name' => 'Target',
        ]);
        $course = Course::create([
            'course_code' => 'JD',
            'course_desc' => 'Juris Doctor',
            'course_college' => 'School of Law',
        ]);
        $firstTerm = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
        ]);
        $secondTerm = AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => 'Second Semester',
        ]);

        LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $firstTerm->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-10',
            'reference_number' => 'LAW-JOB-AR',
            'particulars' => 'Tuition',
            'rate' => 1000,
            'amount' => 1000,
            'status' => 'Pending',
        ]);
        LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $secondTerm->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-02-10',
            'reference_number' => 'LAW-JOB-OTHER-TERM',
            'particulars' => 'Tuition',
            'rate' => 2000,
            'amount' => 2000,
            'status' => 'Pending',
        ]);

        (new SendLawLedgerStatementEmail(
            $student->id,
            '2025-2026',
            '1st Sem',
            'Law SOA',
            'Please settle your balance.',
            'Midterm',
            '2026-03-01',
        ))->handle();

        Mail::assertSent(LawSchoolLedgerStatementMail::class, function (LawSchoolLedgerStatementMail $mail) use ($student): bool {
            return $mail->hasTo('law.job@example.com')
                && $mail->student->is($student)
                && $mail->customSubject === 'Law SOA'
                && $mail->note === 'Please settle your balance.'
                && $mail->examPeriod === 'Midterm'
                && $mail->examDeadline === '2026-03-01';
        });

        Pdf::assertViewHas('records');
        Pdf::assertDontSee('LAW-JOB-OTHER-TERM');

        $this->assertDatabaseHas('activity_log', [
            'action' => 'email.sent',
            'subject_type' => LawSchoolLedger::class,
            'description' => 'Law Ledger SOA emailed to Job, Target (law.job@example.com).',
        ]);
    }

    public function test_paid_law_op_posts_paid_clickable_ledger_payment(): void
    {
        $user = User::factory()->staff()->create();
        $cashier = User::factory()->cashier()->create();
        $student = Student::create([
            'student_number' => '202600777',
            'email' => 'paid.law@example.com',
            'last_name' => 'Paid',
            'first_name' => 'Law',
        ]);
        $course = Course::create([
            'course_code' => 'JD-OP',
            'course_desc' => 'Juris Doctor',
            'course_college' => 'School of Law',
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
        ]);
        $bank = BankAccountInfo::create([
            'account_num' => 'LAW-OP-ACCOUNT',
            'account_name' => 'Law OP Account',
            'bank_name' => 'Landbank',
            'fund_cluster' => '01',
        ]);
        $uacs = UACS::create([
            'object_code' => '4020101000',
            'account_title' => 'Tuition Fees',
        ]);
        $membership = Membership::create([
            'member_code' => 'LAW-STUDENT',
            'member_desc' => 'Law Student',
        ]);
        $paymentOption = PaymentDetailOption::create([
            'payment_desc' => 'Law Tuition Payment',
        ]);

        LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-09-01',
            'reference_number' => 'LAW-AR-OP-001',
            'particulars' => 'Tuition',
            'rate' => 1000,
            'amount' => 2500,
            'status' => 'Pending',
        ]);

        $formInput = FormInput::create([
            'reference_number' => 'OP-LAW-2026-0001',
            'email' => 'paid.law@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Law',
            'middlename_or_project' => null,
            'lastname_or_agency' => 'Paid',
            'office_or_college' => 'School of Law',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 2500,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
            'student_num' => $student->id,
            'submitted_student_number' => '202600777',
            'course_id' => $course->id,
            'academic_term' => $term->id,
        ]);

        $staffInput = StaffInput::create([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-09-02',
            'uacs_id' => $uacs->id,
            'status' => 'paid',
            'or_no' => 'LAW-OR-2026-0001',
            'or_date' => '2026-09-03',
        ]);

        $this->actingAs($cashier);
        $result = app(CashierLedgerPostingService::class)->postPayment($staffInput);

        $this->assertTrue($result['posted']);
        $this->assertDatabaseHas('law_school_ledgers', [
            'student_id' => $student->id,
            'entry_type' => 'payment',
            'reference_number' => 'LAW-OR-2026-0001',
            'amount' => '2500.00',
            'status' => 'Paid',
            'remarks' => 'OP:'.$formInput->id,
        ]);

        $this->actingAs($user)->get('/law-ledger')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('records.data.0.referenceNo', 'LAW-OR-2026-0001')
                ->where('records.data.0.status', 'Paid')
                ->where('records.data.0.orLink', route('staff.requests.show', $formInput))
            );
    }

    public function test_latin_honor_discount_cannot_be_applied_twice_to_same_assessment(): void
    {
        $user = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '202600333',
            'last_name' => 'Cruz',
            'first_name' => 'Ben',
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
            'transaction_date' => '2026-01-10',
            'reference_number' => 'LAW-AR-003',
            'particulars' => 'Tuition',
            'rate' => 1000,
            'amount' => 10000,
            'status' => 'Pending',
        ]);

        LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'adjustment',
            'transaction_date' => '2026-01-11',
            'reference_number' => 'HONOR-CUM-'.$assessment->id,
            'particulars' => 'Cum Laude Scholarship (50%)',
            'rate' => 0,
            'amount' => 5000,
            'status' => 'Applied',
            'latin_honor' => 'CUM_LAUDE',
        ]);

        $response = $this->actingAs($user)
            ->from('/law-ledger')
            ->post("/law-ledger/{$assessment->id}/apply-honor", [
                'latin_honor' => 'CUM_LAUDE',
            ]);

        $response->assertRedirect('/law-ledger')
            ->assertSessionHas('error', 'A Latin honor discount has already been applied to this assessment.');

        $this->assertSame(1, LawSchoolLedger::query()
            ->where('entry_type', 'adjustment')
            ->where('reference_number', 'HONOR-CUM-'.$assessment->id)
            ->count());
    }
}

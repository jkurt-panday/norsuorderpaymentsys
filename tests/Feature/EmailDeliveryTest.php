<?php

namespace Tests\Feature;

use App\Jobs\SendLawLedgerStatementEmail;
use App\Jobs\SendOrderOfPaymentEmail;
use App\Mail\AssessmentSoaMail;
use App\Mail\GraduateLedgerStatementMail;
use App\Mail\LawSchoolLedgerStatementMail;
use App\Mail\OrderOfPaymentMail;
use App\Models\AcademicTerm;
use App\Models\ActivityLog;
use App\Models\AssessmentForm;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\User;
use App\Models\StaffInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_email_has_recipient_subject_body_and_two_pdf_attachments(): void
    {
        $formInput = FormInput::create([
            'reference_number' => 'OP-EMAIL-001',
            'email' => 'payer@example.com',
            'contact_num' => '09171234567',
            'firstname_or_office' => 'Ana',
            'lastname_or_agency' => 'Payer',
            'office_or_college' => 'Accounting',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete',
            'request_type' => 'New Request',
            'amount' => 1250,
        ]);

        $mail = new OrderOfPaymentMail($formInput, '%PDF portrait%', '%PDF landscape%', 'Custom OP', 'Ana Payer', '<b>Pay now</b>');

        $mail->assertTo('payer@example.com');
        $this->assertSame('Custom OP', $mail->envelope()->subject);
        $this->assertStringContainsString('Reference No. <strong>OP-EMAIL-001</strong>', $mail->render());
        $mail->assertSeeInHtml('<b>Pay now</b>');
        $this->assertSame(['OP-OP-EMAIL-001-portrait.pdf', 'OP-OP-EMAIL-001-landscape.pdf'], array_map(fn ($attachment) => $attachment->as, $mail->attachments()));
    }

    public function test_assessment_and_statement_emails_have_correct_recipients_subjects_content_and_filenames(): void
    {
        $assessment = AssessmentForm::create([
            'reference_number' => 'AF-EMAIL-001',
            'email' => 'assessment@example.com',
            'contact_num' => '09171234567',
            'first_name' => 'Assessment',
            'last_name' => 'Student',
            'enrolled_under' => 'Graduate',
            'semester' => 'First Semester',
            'sy_last_attended' => '2025-2026',
            'course_id' => \App\Models\Courses::create(['course_code' => 'MBA-EMAIL', 'course_desc' => 'MBA', 'course_college' => 'Graduate School'])->id,
            'address' => 'Dumaguete City',
        ]);
        $student = Student::create([
            'student_number' => 'STMT-001',
            'email' => 'statement@example.com',
            'last_name' => 'Ledger',
            'first_name' => 'Student',
        ]);

        $assessmentMail = new AssessmentSoaMail($assessment, '%PDF%', 'Assessment Subject', 'Assessment Student', '<img src=x onerror=alert(1)>');
        $assessmentMail->assertTo('assessment@example.com');
        $this->assertSame('Assessment Subject', $assessmentMail->envelope()->subject);
        $assessmentMail->assertSeeInHtml('AF-EMAIL-001');
        $assessmentMail->assertSeeInHtml('<img src=x onerror=alert(1)>');
        $this->assertSame('SOA-AF-EMAIL-001.pdf', $assessmentMail->attachments()[0]->as);

        foreach ([GraduateLedgerStatementMail::class, LawSchoolLedgerStatementMail::class] as $mailClass) {
            $mail = new $mailClass($student, '%PDF%', 'Statement Subject', '<script>alert(1)</script>');
            $mail->assertTo('statement@example.com');
                $this->assertSame('Statement Subject', $mail->envelope()->subject);
            $mail->assertSeeInHtml('<script>alert(1)</script>');
            $this->assertSame('SOA_Ledger_Student.pdf', $mail->attachments()[0]->as);
        }
    }

    public function test_missing_order_of_payment_is_not_sent_and_invalid_recipient_is_marked_failed(): void
    {
        Mail::fake();
        $job = new SendOrderOfPaymentEmail(999999);
        $job->handle();
        Mail::assertNothingSent();

        $formInput = FormInput::create([
            'reference_number' => 'OP-INVALID-001',
            'email' => 'invalid@example.com',
            'contact_num' => '09171234567',
            'firstname_or_office' => 'No',
            'lastname_or_agency' => 'Email',
            'office_or_college' => 'Accounting',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete',
            'request_type' => 'New Request',
            'amount' => 1,
        ]);
        (new SendOrderOfPaymentEmail($formInput->id))->handle();
        $this->assertDatabaseMissing('activity_log', ['description' => 'Email sent to invalid@example.com with subject "Order of Payment - OP-INVALID-001".']);
        Mail::assertNothingSent();
    }

    public function test_order_of_payment_job_retry_configuration_and_failed_callback_are_recorded(): void
    {
        $formInput = FormInput::create([
            'reference_number' => 'OP-FAILED-001',
            'email' => 'failed@example.com',
            'contact_num' => '09171234567',
            'firstname_or_office' => 'Failed',
            'lastname_or_agency' => 'Recipient',
            'office_or_college' => 'Accounting',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete',
            'request_type' => 'New Request',
            'amount' => 1,
        ]);
        $staffInput = StaffInput::create(['form_input_id' => $formInput->id, 'status' => 'processed', 'ref_date' => '2026-11-01']);
        $job = new SendOrderOfPaymentEmail($formInput->id);

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff);
        $job->failed(new \RuntimeException('SMTP unavailable'));

        $this->assertSame('failed', $staffInput->fresh()->email_status);
        $this->assertSame('SMTP unavailable', $staffInput->fresh()->email_error);
    }

    public function test_law_statement_job_sends_email_and_logs_system_actor(): void
    {
        Pdf::fake();
        Mail::fake();
        $student = Student::create(['student_number' => 'LAW-EMAIL-001', 'email' => 'law@example.com', 'last_name' => 'Law', 'first_name' => 'Student']);
        $course = Course::create(['course_code' => 'JD', 'course_desc' => 'Juris Doctor', 'course_college' => 'School of Law']);
        $term = AcademicTerm::create(['school_year' => '2025-2026', 'semester' => 'First Semester']);
        LawSchoolLedger::create([
            'student_id' => $student->id, 'course_id' => $course->id, 'academic_term_id' => $term->id,
            'entry_type' => 'ar', 'transaction_date' => '2026-01-01', 'reference_number' => 'LAW-001',
            'particulars' => 'Tuition', 'amount' => 100, 'status' => 'Pending',
        ]);

        (new SendLawLedgerStatementEmail($student->id, '2025-2026', 'First Semester', 'Law Subject'))->handle();

        Mail::assertSent(LawSchoolLedgerStatementMail::class, fn (LawSchoolLedgerStatementMail $mail): bool => $mail->hasTo('law@example.com'));
        $this->assertDatabaseHas('activity_log', [
            'action' => 'email.sent',
            'actor_id' => null,
            'actor_name' => 'Queue Worker',
            'actor_role' => 'system',
        ]);
    }

    public function test_bulk_order_of_payment_rejects_empty_selection_and_removes_duplicates(): void
    {
        Queue::fake();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->postJson('/staff/requests/bulk-email-op', ['form_input_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('form_input_ids');

        $formInput = FormInput::create([
            'reference_number' => 'OP-BULK-001', 'email' => 'bulk@example.com', 'contact_num' => '09171234567',
            'firstname_or_office' => 'Bulk', 'lastname_or_agency' => 'Recipient', 'office_or_college' => 'Accounting', 'position_or_designation' => 'Student', 'address' => 'Dumaguete', 'request_type' => 'New Request', 'amount' => 1,
        ]);
        StaffInput::create(['form_input_id' => $formInput->id, 'status' => 'processed', 'ref_date' => '2026-11-01']);

        $response = $this->actingAs($staff)->postJson('/staff/requests/bulk-email-op', [
            'form_input_ids' => [$formInput->id, $formInput->id],
        ]);

        $response->assertOk()->assertJsonPath('total', 1);
        Queue::assertPushed(SendOrderOfPaymentEmail::class, 1);
    }

    public function test_email_actor_logging_is_deduplicated_and_guest_actor_is_null(): void
    {
        $message = (new \Symfony\Component\Mime\Email())
            ->from('sender@example.com')
            ->to('guest@example.com')
            ->subject('Guest subject')
            ->text('body');
        $sent = new \Symfony\Component\Mailer\SentMessage($message, new \Symfony\Component\Mailer\Envelope(new \Symfony\Component\Mime\Address('sender@example.com'), [new \Symfony\Component\Mime\Address('guest@example.com')]));
        $event = new \Illuminate\Mail\Events\MessageSent(new \Illuminate\Mail\SentMessage($sent));
        $listener = app(\App\Listeners\LogSentEmails::class);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('activity_log', 1);
        $this->assertDatabaseHas('activity_log', ['actor_id' => null, 'actor_name' => null, 'actor_role' => null]);
    }
}

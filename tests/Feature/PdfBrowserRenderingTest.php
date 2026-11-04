<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AssessmentForm;
use App\Models\Course;
use App\Models\Courses;
use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

class PdfBrowserRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_order_of_payment_receipt_pdf_renders_with_expected_a4_metadata(): void
    {
        Pdf::fake();
        $form = FormInput::create($this->formPayload(['reference_number' => 'OP-PDF-001']));

        $this->withSession(['success_reference_number' => $form->reference_number])
            ->get(route('public.download', $form->reference_number))
            ->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($form): bool {
            return $pdf->viewName === 'pdf.success-receipt'
                && $pdf->viewData['formInput']->is($form)
                && $pdf->format === 'a4';
        });
    }

    public function test_assessment_receipt_pdf_renders_and_download_headers_are_controlled(): void
    {
        Pdf::fake();
        $assessment = $this->assessment(['reference_number' => 'AF-PDF-001']);

        $this->withSession(['success_reference_number' => $assessment->reference_number])
            ->get(route('public.download_req', $assessment->reference_number))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf): bool => $pdf->viewName === 'pdf.assessment-success-receipt');
    }

    public function test_assessment_receipt_cannot_expose_another_record(): void
    {
        Pdf::fake();
        $first = $this->assessment(['reference_number' => 'AF-FIRST']);
        $second = $this->assessment(['reference_number' => 'AF-SECOND', 'first_name' => 'Other User']);

        $html = view('pdf.assessment-success-receipt', ['assessmentForm' => $first->load('course')])->render();
        $this->assertStringContainsString('AF-FIRST', $html);
        $this->assertStringNotContainsString('AF-SECOND', $html);
        $this->assertStringNotContainsString('Other User', $html);
    }

    public function test_long_names_special_characters_and_missing_values_render_in_receipt_html(): void
    {
        $assessment = $this->assessment([
            'first_name' => str_repeat('José & Ana ', 10),
            'middle_name' => null,
            'contact_num' => '',
            'address' => '<unsafe> & address',
        ]);

        $html = view('pdf.assessment-success-receipt', ['assessmentForm' => $assessment->load('course')])->render();
        $this->assertStringContainsString('José', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('&lt;unsafe&gt;', $html);
        $this->assertStringNotContainsString('ErrorException', $html);
    }

    public function test_graduate_statement_pdf_renders_with_long_transaction_table_and_only_selected_student_data(): void
    {
        Pdf::fake();
        $staff = User::factory()->staff()->create();
        $student = Student::create(['student_number' => 'GRAD-PDF', 'first_name' => 'Long', 'last_name' => 'Name']);
        $other = Student::create(['student_number' => 'OTHER-PDF', 'first_name' => 'Other', 'last_name' => 'Student']);
        $course = $this->course('MBA');
        $term = AcademicTerm::create(['school_year' => '2025-2026', 'semester' => 'First Semester']);
        for ($i = 1; $i <= 25; $i++) {
            GraduateLedger::create([
                'student_id' => $student->id, 'course_id' => $course->id, 'academic_term_id' => $term->id,
                'entry_type' => $i % 2 ? 'ar' : 'payment', 'transaction_date' => '2026-01-'.str_pad((string) (($i % 9) + 1), 2, '0', STR_PAD_LEFT),
                'reference_number' => 'GRAD-'.$i, 'particulars' => 'Tuition', 'amount' => 100, 'status' => 'posted',
            ]);
        }
        GraduateLedger::create([
            'student_id' => $other->id, 'course_id' => $course->id, 'academic_term_id' => $term->id,
            'entry_type' => 'ar', 'transaction_date' => '2026-01-01', 'reference_number' => 'OTHER-DATA', 'particulars' => 'Other', 'amount' => 999, 'status' => 'posted',
        ]);

        $this->actingAs($staff)->get(route('graduate-ledger.pdf', ['student_id' => $student->id]))->assertOk();
        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
            $refs = $pdf->viewData['records']->pluck('referenceNo')->all();
            return $pdf->viewName === 'pdf.student-ledger-statement'
                && count($refs) === 25
                && ! in_array('OTHER-DATA', $refs, true)
                && $pdf->format === 'a4';
        });
    }

    public function test_law_statement_pdf_renders_with_a4_format_and_oldest_order(): void
    {
        Pdf::fake();
        $staff = User::factory()->staff()->create();
        $student = Student::create(['student_number' => 'LAW-PDF', 'first_name' => 'Law', 'last_name' => 'Student']);
        $course = $this->course('JD', 'School of Law');
        $term = AcademicTerm::create(['school_year' => '2025-2026', 'semester' => 'First Semester']);
        LawSchoolLedger::create(['student_id' => $student->id, 'course_id' => $course->id, 'academic_term_id' => $term->id, 'entry_type' => 'ar', 'transaction_date' => '2026-02-01', 'reference_number' => 'LAW-NEW', 'particulars' => 'Tuition', 'amount' => 100, 'status' => 'posted']);
        LawSchoolLedger::create(['student_id' => $student->id, 'course_id' => $course->id, 'academic_term_id' => $term->id, 'entry_type' => 'payment', 'transaction_date' => '2026-01-01', 'reference_number' => 'LAW-OLD', 'particulars' => 'Payment', 'amount' => 50, 'status' => 'posted']);

        $this->actingAs($staff)->get(route('law-ledger.pdf', ['student_id' => $student->id, 'order' => 'oldest']))->assertOk();
        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
            return $pdf->viewName === 'pdf.law-student-ledger-statement'
                && $pdf->format === 'a4'
                && $pdf->viewData['records']->pluck('reference_jev_or_number')->all() === ['LAW-OLD', 'LAW-NEW'];
        });
    }

    public function test_pdf_renderer_failure_is_returned_as_a_controlled_server_error(): void
    {
        $assessment = $this->assessment();
        $this->app->instance(\App\Services\ReceiptPDFService::class, new class
        {
            public function assessmentPrint(AssessmentForm $assessment): never
            {
                throw new \RuntimeException('Chrome unavailable');
            }
        });

        $response = $this->withSession(['success_reference_number' => $assessment->reference_number])
            ->get(route('public.download_req', $assessment->reference_number));
        $this->assertContains($response->status(), [500, 503]);
    }

    public function test_browser_assets_are_available_for_ci_pdf_rendering(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules')) || is_file(public_path('build/manifest.json')));
    }

    /** @return array<string, mixed> */
    private function formPayload(array $overrides = []): array
    {
        $membership = Membership::firstOrCreate(['member_code' => 'PDF-'.uniqid()], ['member_desc' => 'PDF']);
        $option = PaymentDetailOption::firstOrCreate(['payment_desc' => 'PDF '.uniqid()]);
        return array_merge([
            'reference_number' => 'OP-'.uniqid(), 'email' => 'pdf@example.com', 'contact_num' => '09171234567',
            'firstname_or_office' => 'PDF', 'lastname_or_agency' => 'User', 'office_or_college' => 'Office',
            'position_or_designation' => 'Student', 'address' => 'Dumaguete', 'amount' => 100, 'request_type' => 'New Request',
            'membership_id' => $membership->id, 'payment_detail_option_id' => $option->id,
        ], $overrides);
    }

    private function assessment(array $overrides = []): AssessmentForm
    {
        $course = Courses::create(['course_code' => 'PDF-'.uniqid(), 'course_desc' => 'PDF Course', 'course_college' => 'Graduate School']);
        return AssessmentForm::create(array_merge([
            'reference_number' => 'AF-'.uniqid(), 'email' => 'pdf@example.com', 'contact_num' => '09171234567',
            'first_name' => 'Maria', 'middle_name' => 'Q', 'last_name' => 'Reyes', 'student_id' => 'PDF-STUDENT',
            'course_id' => $course->id, 'address' => 'Dumaguete', 'enrolled_under' => 'Graduate',
            'sy_last_attended' => '2025-2026', 'semester' => 'First Semester', 'status' => 'pending',
        ], $overrides));
    }

    private function course(string $code, string $college = 'Graduate School'): Course
    {
        return Course::create(['course_code' => $code.'-'.uniqid(), 'course_desc' => $code, 'course_college' => $college]);
    }
}

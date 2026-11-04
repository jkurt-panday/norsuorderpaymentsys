<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\Courses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

class AssessmentCompletionReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_page_loads_for_a_valid_assessment(): void
    {
        $assessment = $this->assessment();

        $this->withSession(['success_reference_number' => $assessment->reference_number])
            ->get(route('public.complete', $assessment->reference_number))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/AssessmentSuccess')
                ->where('assessmentForm.reference_number', $assessment->reference_number));
    }

    public function test_invalid_reference_number_returns_404(): void
    {
        $this->get(route('public.complete', 'missing-reference'))->assertNotFound();
    }

    public function test_completion_page_requires_the_matching_session_reference(): void
    {
        $assessment = $this->assessment();

        $this->get(route('public.complete', $assessment->reference_number))
            ->assertNotFound();
    }

    public function test_receipt_download_works_for_a_valid_record(): void
    {
        Pdf::fake();
        $assessment = $this->assessment();

        $this->withSession(['success_reference_number' => $assessment->reference_number])
            ->get(route('public.download_req', $assessment->reference_number))
            ->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($assessment): bool {
            return $pdf->viewData['assessmentForm']->is($assessment);
        });
    }

    public function test_receipt_print_response_works_for_a_valid_record(): void
    {
        Pdf::fake();
        $assessment = $this->assessment();

        $this->withSession(['success_reference_number' => $assessment->reference_number])
            ->get(route('public.print_req', $assessment->reference_number))
            ->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($assessment): bool {
            return $pdf->viewData['assessmentForm']->is($assessment);
        });
    }

    public function test_receipt_contains_applicant_reference_course_and_semester(): void
    {
        $assessment = $this->assessment([
            'reference_number' => '2026-11-AF-777',
            'first_name' => 'Ana',
            'middle_name' => 'Q',
            'last_name' => 'Santos',
            'semester' => 'Second Semester',
        ]);

        $html = view('pdf.assessment-success-receipt', [
            'assessmentForm' => $assessment->load('course'),
        ])->render();

        $this->assertStringContainsString('2026-11-AF-777', $html);
        $this->assertStringContainsString('Ana', $html);
        $this->assertStringContainsString('Santos', $html);
        $this->assertStringContainsString('Master of Business Administration', $html);
        $this->assertStringContainsString('Second Semester', $html);
        $this->assertStringContainsString('2025-2026', $html);
    }

    public function test_receipt_escapes_special_characters(): void
    {
        $assessment = $this->assessment([
            'first_name' => '<script>alert("x")</script>',
            'address' => 'A & B < Main Street',
        ]);

        $html = view('pdf.assessment-success-receipt', [
            'assessmentForm' => $assessment->load('course'),
        ])->render();

        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('A &amp; B &lt; Main Street', $html);
    }

    public function test_receipt_handles_missing_optional_fields(): void
    {
        $assessment = $this->assessment([
            'middle_name' => null,
            'contact_num' => '',
        ]);

        $html = view('pdf.assessment-success-receipt', [
            'assessmentForm' => $assessment->load('course'),
        ])->render();

        $this->assertStringContainsString('N/A', $html);
        $this->assertStringNotContainsString('ErrorException', $html);
    }

    public function test_receipt_handles_long_names_and_text(): void
    {
        $assessment = $this->assessment([
            'first_name' => str_repeat('LongName ', 10),
            'last_name' => str_repeat('Surname ', 10),
            'address' => str_repeat('A very long address, ', 30),
        ]);

        $html = view('pdf.assessment-success-receipt', [
            'assessmentForm' => $assessment->load('course'),
        ])->render();

        $this->assertStringContainsString('LongName', $html);
        $this->assertStringContainsString('A very long address', $html);
    }

    public function test_receipt_does_not_expose_another_applicants_data(): void
    {
        $first = $this->assessment(['reference_number' => '2026-11-AF-001']);
        $second = $this->assessment([
            'reference_number' => '2026-11-AF-002',
            'first_name' => 'OtherApplicant',
            'email' => 'other@example.com',
        ]);

        $html = view('pdf.assessment-success-receipt', [
            'assessmentForm' => $first->load('course'),
        ])->render();

        $this->assertStringContainsString($first->reference_number, $html);
        $this->assertStringNotContainsString($second->reference_number, $html);
        $this->assertStringNotContainsString($second->email, $html);
        $this->assertStringNotContainsString($second->first_name, $html);
    }

    public function test_receipt_generation_is_graceful_when_pdf_renderer_is_unavailable(): void
    {
        $assessment = $this->assessment();
        $this->app->instance(\App\Services\ReceiptPDFService::class, new class
        {
            public function assessmentPrint(AssessmentForm $assessment): never
            {
                throw new \RuntimeException('PDF renderer unavailable');
            }
        });

        $response = $this->withSession(['success_reference_number' => $assessment->reference_number])
            ->get(route('public.download_req', $assessment->reference_number));

        $this->assertContains($response->status(), [500, 503]);
    }

    /** @param array<string, mixed> $overrides */
    private function assessment(array $overrides = []): AssessmentForm
    {
        $course = Courses::query()->create([
            'course_code' => 'MBA-'.fake()->unique()->numerify('###'),
            'course_desc' => 'Master of Business Administration',
            'course_college' => 'Graduate School',
        ]);

        return AssessmentForm::query()->create(array_merge([
            'reference_number' => '2026-11-AF-'.fake()->unique()->numerify('###'),
            'email' => 'student@example.com',
            'contact_num' => '09123456789',
            'first_name' => 'Maria',
            'middle_name' => 'Q',
            'last_name' => 'Reyes',
            'student_id' => '202600001',
            'course_id' => $course->id,
            'address' => 'Dumaguete City',
            'enrolled_under' => 'Graduate',
            'sy_last_attended' => '2025-2026',
            'semester' => 'First Semester',
            'status' => 'pending',
        ], $overrides));
    }
}

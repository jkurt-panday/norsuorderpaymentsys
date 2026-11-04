<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\Courses;
use App\Services\ReferenceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicAssessmentFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_form_page_loads_with_public_courses_only(): void
    {
        $course = $this->course('BSIT');
        $placeholder = $this->course('UNASSIGNED');

        $this->get(route('public.assessmentform'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/AssessmentForm')
                ->has('courses', 1)
                ->where('courses.0.id', $course->id)
                ->missing('password')
                ->missing('users')
                ->missing('database'));

        $this->assertNotSame($course->id, $placeholder->id);
    }

    public function test_assessment_form_does_not_expose_academic_terms_or_college_options_until_the_backend_provides_them(): void
    {
        $this->course('BSIT');

        $this->get(route('public.assessmentform'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('academicTerms')
                ->missing('colleges')
                ->missing('offices'));
    }

    public function test_required_assessment_fields_are_validated(): void
    {
        foreach ([
            'email', 'contact_num', 'first_name', 'last_name', 'student_id',
            'course_id', 'address', 'enrolled_under', 'sy_last_attended', 'semester',
        ] as $field) {
            $payload = $this->validPayload();
            unset($payload[$field]);

            $this->post('/public/assessmentform', $payload)
                ->assertSessionHasErrors($field);
        }
    }

    public function test_email_format_is_validated(): void
    {
        $this->post('/public/assessmentform', $this->validPayload(['email' => 'invalid']))
            ->assertSessionHasErrors('email');
    }

    public function test_contact_number_format_is_validated(): void
    {
        $this->post('/public/assessmentform', $this->validPayload(['contact_num' => '09<script>']))
            ->assertSessionHasErrors('contact_num');
    }

    public function test_student_id_must_contain_only_digits_and_fit_the_limit(): void
    {
        $this->post('/public/assessmentform', $this->validPayload(['student_id' => 'ABC123']))
            ->assertSessionHasErrors('student_id');

        $this->post('/public/assessmentform', $this->validPayload(['student_id' => str_repeat('1', 10)]))
            ->assertSessionHasErrors('student_id');
    }

    public function test_invalid_course_id_is_rejected(): void
    {
        $this->post('/public/assessmentform', $this->validPayload(['course_id' => 999999]))
            ->assertSessionHasErrors('course_id');
    }

    public function test_oversized_input_is_rejected(): void
    {
        $this->post('/public/assessmentform', $this->validPayload([
            'first_name' => str_repeat('A', 101),
            'email' => str_repeat('a', 250).'@example.com',
        ]))->assertSessionHasErrors(['first_name', 'email']);
    }

    public function test_unknown_fields_are_not_persisted(): void
    {
        $payload = $this->validPayload(['is_admin' => true, 'status' => 'completed']);

        $this->post('/public/assessmentform', $payload)->assertRedirect();

        $assessment = AssessmentForm::query()->latest('id')->firstOrFail();
        $this->assertSame('pending', $assessment->status);
        $this->assertFalse(array_key_exists('is_admin', $assessment->getAttributes()));
    }

    public function test_valid_assessment_is_created_with_a_unique_reference_number(): void
    {
        $this->post('/public/assessmentform', $this->validPayload())
            ->assertRedirect();

        $first = AssessmentForm::query()->latest('id')->firstOrFail();
        $this->assertSame($first->reference_number, session('success_reference_number'));

        $this->post('/public/assessmentform', $this->validPayload([
            'email' => 'second@example.com',
            'student_id' => '202600002',
        ]))->assertRedirect();

        $second = AssessmentForm::query()->latest('id')->firstOrFail();
        $this->assertNotSame($first->reference_number, $second->reference_number);
        $this->assertSame(2, AssessmentForm::query()->count());
    }

    public function test_success_page_requires_the_submitted_reference_and_displays_it(): void
    {
        $this->post('/public/assessmentform', $this->validPayload());
        $assessment = AssessmentForm::query()->firstOrFail();

        $this->get(route('public.complete', $assessment->reference_number))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/AssessmentSuccess')
                ->where('assessmentForm.reference_number', $assessment->reference_number));
    }

    public function test_success_page_cannot_be_opened_without_the_matching_session_reference(): void
    {
        $assessment = AssessmentForm::factory()->create(['course_id' => $this->course('BSIT')->id]);

        $this->get(route('public.complete', $assessment->reference_number))
            ->assertNotFound();
    }

    public function test_submission_rolls_back_when_reference_generation_fails(): void
    {
        $this->app->instance(ReferenceNumberService::class, new class extends ReferenceNumberService
        {
            public function assess_ref_gen(): string
            {
                throw new \RuntimeException('reference service unavailable');
            }
        });

        $this->post('/public/assessmentform', $this->validPayload())
            ->assertSessionHas('error');

        $this->assertDatabaseCount('assessment_forms', 0);
    }

    public function test_public_submission_is_rate_limited(): void
    {
        $this->course('BSIT');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post('/public/assessmentform', $this->validPayload([
                'email' => "student{$attempt}@example.com",
                'student_id' => '20260'.str_pad((string) $attempt, 4, '0', STR_PAD_LEFT),
            ]));
        }

        $response = $this->post('/public/assessmentform', $this->validPayload([
            'email' => 'student21@example.com',
            'student_id' => '202600021',
        ]));

        $this->assertContains($response->status(), [302, 429]);
        $this->assertSame(429, $response->status(), 'No rate limit is configured for this public endpoint.');
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'student@example.com',
            'contact_num' => '09123456789',
            'first_name' => 'Maria',
            'middle_name' => 'Q',
            'last_name' => 'Reyes',
            'student_id' => '202600001',
            'course_id' => $this->course('BSIT')->id,
            'address' => 'Dumaguete City',
            'enrolled_under' => 'Graduate',
            'sy_last_attended' => '2025-2026',
            'semester' => 'First Semester',
        ], $overrides);
    }

    private function course(string $code): Courses
    {
        return Courses::query()->firstOrCreate([
            'course_code' => $code,
        ], [
            'course_desc' => $code,
            'course_college' => 'Graduate School',
        ]);
    }
}

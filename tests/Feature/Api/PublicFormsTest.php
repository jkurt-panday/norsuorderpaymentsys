<?php

namespace Tests\Feature\Api;

use App\Models\Courses;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_op_submission_returns_validation_errors(): void
    {
        $this->postJson('/api/v1/public/opform')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'contact_num',
                'firstname_or_office',
                'lastname_or_agency',
                'membership_id',
                'payment_detail_option_id',
            ]);
    }

    public function test_op_submission_returns_access_credentials_and_protects_result(): void
    {
        $membership = Membership::query()->create([
            'member_code' => 'API',
            'member_desc' => 'API Member',
        ]);
        $paymentOption = PaymentDetailOption::query()->create([
            'payment_desc' => 'API Payment',
        ]);

        $response = $this->postJson('/api/v1/public/opform', [
            'email' => 'student@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Maria',
            'lastname_or_agency' => 'Reyes',
            'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 100,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['reference_number', 'access_token']]);

        $reference = $response->json('data.reference_number');
        $token = $response->json('data.access_token');

        $this->getJson("/api/v1/public/opform/{$reference}?token={$token}")
            ->assertSuccessful()
            ->assertJsonPath('data.reference_number', $reference);

        $this->getJson("/api/v1/public/opform/{$reference}")->assertNotFound();
        $this->assertDatabaseHas('form_inputs', ['reference_number' => $reference]);
    }

    public function test_assessment_submission_returns_access_credentials_and_protects_result(): void
    {
        $course = Courses::query()->create([
            'course_code' => 'API-BSIT',
            'course_desc' => 'API BSIT',
            'course_college' => 'Graduate School',
        ]);

        $response = $this->postJson('/api/v1/public/assessmentform', [
            'email' => 'student@example.com',
            'contact_num' => '09123456789',
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
            'student_id' => '202600001',
            'course_id' => $course->id,
            'address' => 'Dumaguete City',
            'enrolled_under' => 'Graduate',
            'sy_last_attended' => '2025-2026',
            'semester' => 'First Semester',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['reference_number', 'access_token']]);

        $reference = $response->json('data.reference_number');
        $token = $response->json('data.access_token');

        $this->getJson("/api/v1/public/assessmentform/{$reference}?token={$token}")
            ->assertSuccessful()
            ->assertJsonPath('data.reference_number', $reference);

        $this->getJson("/api/v1/public/assessmentform/{$reference}")->assertNotFound();
        $this->assertDatabaseHas('assessment_forms', ['reference_number' => $reference]);
    }

    public function test_assessment_form_options_are_returned(): void
    {
        Courses::query()->create([
            'course_code' => 'API-BSIT',
            'course_desc' => 'API BSIT',
            'course_college' => 'Graduate School',
        ]);

        $this->getJson('/api/v1/public/assessmentform/options')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.courses');
    }
}

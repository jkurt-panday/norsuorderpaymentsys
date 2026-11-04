<?php

namespace Tests\Feature;

use App\Models\CollegeOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CollegeOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_can_submit_a_valid_college_or_office(): void
    {
        $response = $this->postJson(route('public.college-offices.store'), [
            'name' => 'College of Engineering',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'College of Engineering');

        $this->assertDatabaseHas('college_offices', [
            'name' => 'College of Engineering',
        ]);
    }

    public function test_guest_cannot_create_a_college_or_office_when_creation_is_protected(): void
    {
        $this->postJson(route('public.college-offices.store'), [
            'name' => 'Guest Office',
        ])->assertUnauthorized();
    }

    public function test_authorized_staff_can_create_a_college_or_office(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->postJson(route('public.college-offices.store'), [
                'name' => 'Staff Office',
            ])->assertCreated();
    }

    public function test_name_is_required_and_must_be_a_string(): void
    {
        $this->postJson(route('public.college-offices.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson(route('public.college-offices.store'), ['name' => 123])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_name_length_limit_is_enforced(): void
    {
        $this->postJson(route('public.college-offices.store'), [
            'name' => str_repeat('A', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_duplicate_names_are_rejected(): void
    {
        CollegeOffice::create(['name' => 'Registrar Office']);

        $this->postJson(route('public.college-offices.store'), [
            'name' => 'Registrar Office',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_duplicate_names_are_rejected_case_insensitively(): void
    {
        CollegeOffice::create(['name' => 'College of Law']);

        $this->postJson(route('public.college-offices.store'), [
            'name' => 'college of law',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_successful_creation_returns_the_expected_json_response(): void
    {
        $response = $this->postJson(route('public.college-offices.store'), [
            'name' => 'Office of Admissions',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'name', 'created_at', 'updated_at'])
            ->assertJsonPath('name', 'Office of Admissions');
    }

    public function test_unexpected_database_failure_returns_a_safe_error(): void
    {
        Schema::drop('college_offices');

        $response = $this->postJson(route('public.college-offices.store'), [
            'name' => 'Unavailable Office',
        ]);

        $this->assertNotSame(201, $response->status());
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('college_offices', $response->getContent());
    }

}

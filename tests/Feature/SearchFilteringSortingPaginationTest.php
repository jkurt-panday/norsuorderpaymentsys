<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchFilteringSortingPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_search_is_case_insensitive_and_matches_all_name_email_and_reference_columns(): void
    {
        $this->form(['reference_number' => 'REF-ALPHA', 'firstname_or_office' => 'Maria', 'middlename_or_project' => 'Q', 'lastname_or_agency' => 'Santos', 'email' => 'alpha@example.com']);
        $this->form(['reference_number' => 'REF-BETA', 'firstname_or_office' => 'Other', 'lastname_or_agency' => 'Person', 'email' => 'beta@example.com']);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/staff/requests?search=%20ALPHA%20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('formInputs.data', 1)->where('formInputs.data.0.reference_number', 'REF-ALPHA'));

        foreach (['maria', 'q', 'santos', 'alpha@example.com', 'ref-alpha'] as $search) {
            $this->actingAs($staff)->get('/staff/requests?search='.$search)->assertInertia(fn (Assert $page) => $page->has('formInputs.data', 1));
        }
    }

    public function test_request_filters_combine_and_unknown_status_does_not_expose_records(): void
    {
        $first = $this->form(['reference_number' => 'REF-ONE']);
        $first->forceFill(['created_at' => now()->subDays(2)])->save();

        $second = $this->form(['reference_number' => 'REF-TWO']);
        $second->forceFill(['created_at' => now()->subDay()])->save();

        $first->staffInput()->create(['status' => 'paid', 'ref_date' => now()->toDateString()]);
        $second->staffInput()->create(['status' => 'pending', 'ref_date' => now()->toDateString()]);

        $this->actingAs(User::factory()->staff()->create())->get('/staff/requests?status=paid&date_from='.now()->subDays(3)->toDateString().'&date_to='.now()->subDay()->toDateString())
            ->assertInertia(fn (Assert $page) => $page->has('formInputs.data', 1));
        $this->actingAs(User::factory()->staff()->create())->get('/staff/requests?status=does-not-exist')->assertInertia(fn (Assert $page) => $page->has('formInputs.data', 0));
    }

    public function test_request_date_ranges_and_large_pages_render_safely(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->form(['reference_number' => 'PAGE-'.$i]);
        }

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/staff/requests?page=999')->assertOk();
        $this->actingAs($staff)->get('/staff/requests?date_from=not-a-date')->assertOk();
    }

    public function test_assessment_search_filters_and_sorting_preserve_query_parameters_in_pagination(): void
    {
        $staff = User::factory()->staff()->create();
        $this->assessment(['reference_number' => 'A-ALPHA', 'first_name' => 'Alice', 'last_name' => 'Zulu', 'created_at' => now()->subDays(2)]);
        $this->assessment(['reference_number' => 'A-BETA', 'first_name' => 'Bob', 'last_name' => 'Alpha', 'created_at' => now()->subDay()]);

        $this->actingAs($staff)->get('/staff/assessments?search=alpha&sort=last_name&direction=asc&date_from=2026-01-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'alpha')
                ->where('filters.sort', 'last_name')
                ->where('filters.direction', 'asc')
                ->has('assessments.data', 2)
                ->where('assessments.data.0.reference_number', 'A-BETA'));

        $this->actingAs($staff)->get('/staff/assessments?sort=last_name;drop table users&direction=desc')
            ->assertOk();
    }

    public function test_assessment_unknown_filters_return_empty_results_and_empty_state_is_valid(): void
    {
        $staff = User::factory()->staff()->create();
        $this->assessment(['reference_number' => 'ONLY-ONE']);

        $this->actingAs($staff)->get('/staff/assessments?status=unknown&course_id=999999')
            ->assertInertia(fn (Assert $page) => $page->has('assessments.data', 0));
    }

    public function test_master_data_search_is_case_insensitive_and_unknown_sort_is_allowlisted(): void
    {
        $staff = User::factory()->staff()->create();
        Membership::create(['member_code' => 'ALPHA', 'member_desc' => 'First']);
        Membership::create(['member_code' => 'BETA', 'member_desc' => 'Second']);

        $this->actingAs($staff)->get('/staff/memberships?search= alpha &sort=member_code&direction=desc')
            ->assertInertia(fn (Assert $page) => $page->has('memberships.data', 1));
        $this->actingAs($staff)->get('/staff/memberships?sort=member_code%20desc%2C%20id')->assertOk();
    }

    /** @param array<string, mixed> $overrides */
    private function form(array $overrides = []): FormInput
    {
        $membership = Membership::firstOrCreate(['member_code' => 'M-'.uniqid()], ['member_desc' => 'Member']);
        $option = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Option '.uniqid()]);
        return FormInput::create(array_merge([
            'reference_number' => 'REF-'.uniqid(), 'email' => 'person@example.com', 'contact_num' => '09171234567',
            'firstname_or_office' => 'First', 'middlename_or_project' => null, 'lastname_or_agency' => 'Last',
            'office_or_college' => 'Office', 'position_or_designation' => 'Student', 'address' => 'Address',
            'amount' => 100, 'request_type' => 'New Request', 'membership_id' => $membership->id, 'payment_detail_option_id' => $option->id,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function assessment(array $overrides = []): AssessmentForm
    {
        $course = Course::create(['course_code' => 'COURSE-'.uniqid(), 'course_desc' => 'Course', 'course_college' => 'Graduate School']);
        return AssessmentForm::create(array_merge([
            'reference_number' => 'ASSESS-'.uniqid(), 'email' => 'assessment@example.com', 'contact_num' => '09171234567',
            'first_name' => 'First', 'last_name' => 'Last', 'student_id' => 'S-1', 'course_id' => $course->id,
            'address' => 'Address', 'enrolled_under' => 'Graduate', 'sy_last_attended' => '2025-2026',
            'semester' => 'First Semester', 'status' => 'pending',
        ], $overrides));
    }
}

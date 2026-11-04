<?php

namespace Tests\Feature;

use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\UACS;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_staff_requests(): void
    {
        $this->get(route('staff.requests.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_staff_can_view_requests_and_page_loads(): void
    {
        $staff = User::factory()->staff()->create();
        $this->formInput(['firstname_or_office' => 'Maria']);

        $this->actingAs($staff)->get(route('staff.requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/requestform/request')
                ->has('formInputs.data', 1)
                ->has('formInputs.current_page')
                ->has('formInputs.per_page'));
    }

    public function test_request_list_is_paginated(): void
    {
        $staff = User::factory()->staff()->create();
        for ($i = 0; $i < 11; $i++) {
            $this->formInput(['email' => "student{$i}@example.com"]);
        }

        $this->actingAs($staff)->get(route('staff.requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('formInputs.per_page', 10)
                ->has('formInputs.data', 10)
                ->where('formInputs.total', 11));
    }

    public function test_search_status_and_date_filters_work(): void
    {
        $staff = User::factory()->staff()->create();
        $matching = $this->formInput(['firstname_or_office' => 'Target', 'email' => 'target@example.com']);
        $matching->created_at = now()->subDay();
        $matching->save();
        StaffInput::create(array_merge($this->staffPayload($matching), ['status' => 'processed']));
        $other = $this->formInput(['firstname_or_office' => 'Other', 'email' => 'other@example.com']);
        StaffInput::create(array_merge($this->staffPayload($other), ['status' => 'cancelled']));

        $this->actingAs($staff)->get(route('staff.requests.index', [
            'search' => 'target',
            'status' => 'processed',
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('formInputs.data', 1)
            ->where('formInputs.data.0.email', 'target@example.com'));
    }

    public function test_invalid_sort_column_is_ignored_safely(): void
    {
        $staff = User::factory()->staff()->create();
        $this->formInput();

        $this->actingAs($staff)->get(route('staff.requests.index', [
            'sort' => 'password;drop table form_inputs',
            'direction' => 'desc',
        ]))->assertOk();
    }

    public function test_request_details_display_correctly(): void
    {
        $staff = User::factory()->staff()->create();
        $form = $this->formInput();

        $this->actingAs($staff)->get(route('staff.requests.show', $form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/requestform/showrequest')
                ->where('formInput.id', $form->id)
                ->where('formInput.reference_number', $form->reference_number)
                ->where('formInput.email', $form->email));
    }

    public function test_guest_cannot_create_or_update_a_staff_request(): void
    {
        $form = $this->formInput();
        $payload = $this->staffPayload($form);

        $this->post(route('staff.requests.store'), $payload)->assertRedirect(route('login'));
        $staffInput = StaffInput::create($payload);
        $this->put(route('staff.requests.update', $staffInput), $payload)->assertRedirect(route('login'));
    }

    public function test_new_request_requires_valid_processing_fields(): void
    {
        $staff = User::factory()->staff()->create();
        $form = $this->formInput();

        $this->actingAs($staff)->post(route('staff.requests.store'), [
            'form_input_id' => $form->id,
            'fundcluster_id' => 999999,
            'uacs_id' => 999999,
            'ref_date' => now()->addDay()->toDateString(),
            'status' => 'invalid',
        ])->assertSessionHasErrors([
            'fundcluster_id', 'uacs_id', 'ref_date', 'status',
        ]);
    }

    public function test_valid_request_can_be_created_with_default_processed_status(): void
    {
        $staff = User::factory()->staff()->create();
        $form = $this->formInput();
        $payload = $this->staffPayload($form);
        unset($payload['status']);

        $this->actingAs($staff)->post(route('staff.requests.store'), $payload)
            ->assertRedirect(route('staff.requests.show', $form));

        $this->assertDatabaseHas('staff_inputs', [
            'form_input_id' => $form->id,
            'status' => 'processed',
        ]);
    }

    public function test_duplicate_request_processing_is_prevented(): void
    {
        $staff = User::factory()->staff()->create();
        $form = $this->formInput();
        StaffInput::create($this->staffPayload($form));

        $this->actingAs($staff)->post(route('staff.requests.store'), $this->staffPayload($form))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, StaffInput::where('form_input_id', $form->id)->count());
    }

    public function test_request_can_be_edited_and_cancelled(): void
    {
        $staff = User::factory()->staff()->create();
        $form = $this->formInput();
        $staffInput = StaffInput::create($this->staffPayload($form, ['status' => 'processed']));
        $payload = $this->staffPayload($form, ['status' => 'cancelled', 'purpose' => 'Cancelled by staff']);

        $this->actingAs($staff)->put(route('staff.requests.update', $staffInput), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('staff_inputs', [
            'id' => $staffInput->id,
            'status' => 'cancelled',
            'purpose' => 'Cancelled by staff',
        ]);
    }

    public function test_request_list_updates_after_creation_and_cancellation(): void
    {
        $staff = User::factory()->staff()->create();
        $form = $this->formInput();
        $this->actingAs($staff)->get(route('staff.requests.index', ['status' => 'unprocessed']))
            ->assertInertia(fn (Assert $page) => $page->has('formInputs.data', 1));

        $staffInput = StaffInput::create($this->staffPayload($form));
        $this->actingAs($staff)->get(route('staff.requests.index', ['status' => 'unprocessed']))
            ->assertInertia(fn (Assert $page) => $page->has('formInputs.data', 0));

        $staffInput->update(['status' => 'cancelled']);
        $this->actingAs($staff)->get(route('staff.requests.index', ['status' => 'cancelled']))
            ->assertInertia(fn (Assert $page) => $page->has('formInputs.data', 1));
    }

    public function test_bulk_email_rejects_empty_selection(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->postJson(route('staff.requests.bulkEmailOp'), [
            'form_input_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('form_input_ids');
    }

    public function test_bulk_email_deduplicates_selected_ids(): void
    {
        $staff = User::factory()->staff()->create();
        $form = $this->formInput();
        $staffInput = StaffInput::create($this->staffPayload($form));

        $this->actingAs($staff)->postJson(route('staff.requests.bulkEmailOp'), [
            'form_input_ids' => [$form->id, $form->id],
        ])->assertOk()
            ->assertJsonPath('total', 1);

        $this->assertSame(1, StaffInput::whereKey($staffInput->id)->count());
    }

    /** @param array<string, mixed> $overrides */
    private function formInput(array $overrides = []): FormInput
    {
        $membership = Membership::firstOrCreate(['member_code' => 'STUDENT'], ['member_desc' => 'Student']);
        $option = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Tuition']);

        return FormInput::create(array_merge([
            'reference_number' => 'OP-'.uniqid(),
            'email' => 'student@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Maria',
            'middlename_or_project' => null,
            'lastname_or_agency' => 'Reyes',
            'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 500,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $option->id,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function staffPayload(FormInput $form, array $overrides = []): array
    {
        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['norsu_object_code' => '123456789012', 'norsu_sub_object_code' => '01'],
            [
                'uacs_object_code' => '1234567890',
                'uacs_sub_object_code' => '01',
                'rca_code' => '12345678',
                'account_title' => 'Tuition Fees',
            ],
        );

        return array_merge([
            'form_input_id' => $form->id,
            'fundcluster_id' => $bank->id,
            'ref_document_id' => null,
            'ref_date' => now()->toDateString(),
            'uacs_id' => $uacs->id,
            'status' => 'processed',
            'purpose' => null,
        ], $overrides);
    }
}

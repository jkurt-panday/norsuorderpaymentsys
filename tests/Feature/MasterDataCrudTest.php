<?php

namespace Tests\Feature;

use App\Models\AuthorizedOfficial;
use App\Models\BankAccountInfo;
use App\Models\CollegeOffice;
use App\Models\Courses;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\UACS;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_master_data_indexes_and_create_pages_load_and_non_staff_is_denied(): void
    {
        $staff = User::factory()->staff()->create();
        $resources = [
            ['staff.memberships.index', 'staff.memberships.create'],
            ['staff.bank-accounts.index', 'staff.bank-accounts.create'],
            ['staff.payment-options.index', 'staff.payment-options.create'],
            ['staff.uacs.index', 'staff.uacs.create'],
            ['staff.authorized-officials.index', 'staff.authorized-officials.create'],
            ['staff.courses.index', 'staff.courses.create'],
        ];

        foreach ($resources as [$index, $create]) {
            $this->actingAs($staff)->get(route($index))->assertOk();
            $this->actingAs($staff)->get(route($create))->assertOk();
            $this->actingAs(User::factory()->client()->create())->get(route($index))->assertRedirect();
        }
    }

    public function test_memberships_support_crud_validation_search_sort_pagination_and_flash_messages(): void
    {
        $staff = User::factory()->staff()->create();
        $existing = Membership::create(['member_code' => 'AAA', 'member_desc' => 'Alpha']);
        Membership::create(['member_code' => 'ZZZ', 'member_desc' => 'Searchable']);

        $this->actingAs($staff)->post(route('staff.memberships.store'), ['member_code' => 'AAA', 'member_desc' => 'Duplicate'])
            ->assertSessionHasErrors('member_code');
        $created = $this->actingAs($staff)->post(route('staff.memberships.store'), ['member_code' => 'NEW', 'member_desc' => 'New membership']);
        $created->assertRedirect(route('staff.memberships.index'))->assertSessionHas('success', 'Membership created successfully.');
        $membership = Membership::where('member_code', 'NEW')->firstOrFail();
        $this->actingAs($staff)->put(route('staff.memberships.update', $membership), ['member_code' => 'UPDATED', 'member_desc' => 'Updated'])
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($staff)->get(route('staff.memberships.index', ['search' => 'Searchable', 'sort' => 'member_code', 'direction' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page->has('memberships.data', 1)->where('memberships.data.0.member_code', 'ZZZ'));
        $this->actingAs($staff)->delete(route('staff.memberships.destroy', $existing))->assertRedirect()->assertSessionHas('success');
    }

    public function test_membership_delete_is_prevented_when_referenced(): void
    {
        $staff = User::factory()->staff()->create();
        $membership = Membership::create(['member_code' => 'USED', 'member_desc' => 'Used']);
        FormInput::create($this->formInputPayload(['membership_id' => $membership->id]));

        $this->actingAs($staff)->delete(route('staff.memberships.destroy', $membership))
            ->assertSessionHas('error', 'Cannot delete membership that has associated form inputs.');
        $this->assertDatabaseHas('memberships', ['id' => $membership->id]);
    }

    public function test_bank_accounts_and_payment_options_validate_update_and_protect_referenced_deletes(): void
    {
        $staff = User::factory()->staff()->create();
        $bank = BankAccountInfo::create(['account_name' => 'Main', 'bank_name' => 'Landbank', 'account_num' => '001', 'fund_cluster' => '01']);
        $option = PaymentDetailOption::create(['payment_desc' => 'Tuition']);
        $this->actingAs($staff)->post(route('staff.bank-accounts.store'), ['account_name' => 'Other', 'bank_name' => 'DBP', 'account_num' => '001', 'fund_cluster' => '02'])->assertSessionHasErrors('account_num');
        $this->actingAs($staff)->put(route('staff.bank-accounts.update', $bank), ['account_name' => 'Updated', 'bank_name' => 'Landbank', 'account_num' => '001', 'fund_cluster' => '01'])->assertRedirect();
        $this->actingAs($staff)->put(route('staff.payment-options.update', $option), ['payment_desc' => 'Updated Tuition'])->assertRedirect();
        $form = FormInput::create($this->formInputPayload(['payment_detail_option_id' => $option->id]));
        $this->actingAs($staff)->delete(route('staff.payment-options.destroy', $option))->assertSessionHas('error');
        $this->assertDatabaseHas('payment_detail_options', ['id' => $option->id]);
        $this->actingAs($staff)->delete(route('staff.bank-accounts.destroy', $bank))->assertRedirect()->assertSessionHas('success');
    }

    public function test_uacs_crud_validation_and_referenced_delete_protection(): void
    {
        $staff = User::factory()->staff()->create();
        $payload = $this->uacsPayload('000000000001');
        $this->actingAs($staff)->post(route('staff.uacs.store'), $payload)->assertRedirect()->assertSessionHas('success');
        $uacs = UACS::firstOrFail();
        $this->actingAs($staff)->post(route('staff.uacs.store'), $payload)->assertSessionHasErrors('norsu_object_code');
        $this->actingAs($staff)->put(route('staff.uacs.update', $uacs), $payload + ['account_title' => 'Updated'])->assertRedirect();
        StaffInput::create(['form_input_id' => FormInput::create($this->formInputPayload())->id, 'uacs_id' => $uacs->id, 'ref_date' => '2026-01-01', 'status' => 'processed']);
        $this->actingAs($staff)->delete(route('staff.uacs.destroy', $uacs))->assertSessionHas('error');
    }

    public function test_authorized_officials_keep_one_active_and_prevent_active_delete(): void
    {
        $staff = User::factory()->staff()->create();
        $first = AuthorizedOfficial::create(['name' => 'First', 'position' => 'Head', 'course' => 'CPA', 'is_active' => true]);
        $this->actingAs($staff)->post(route('staff.authorized-officials.store'), ['name' => 'Second', 'position' => 'Head', 'course' => 'CPA'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($first->fresh()->is_active);
        $second = AuthorizedOfficial::where('name', 'Second')->firstOrFail();
        $this->actingAs($staff)->delete(route('staff.authorized-officials.destroy', $second))->assertSessionHas('error');
        $this->actingAs($staff)->put(route('staff.authorized-officials.set-active', $first))->assertRedirect();
        $this->assertTrue($first->fresh()->is_active);
    }

    public function test_courses_validate_duplicates_update_and_delete(): void
    {
        $staff = User::factory()->staff()->create();
        $course = Courses::create(['course_code' => 'MBA', 'course_desc' => 'Business', 'course_college' => 'Graduate School']);
        $payload = ['course_code' => 'MBA', 'course_desc' => 'Duplicate', 'course_college' => 'Graduate School'];
        $this->actingAs($staff)->post(route('staff.courses.store'), $payload)->assertSessionHasErrors('course_code');
        $this->actingAs($staff)->put(route('staff.courses.update', $course), ['course_code' => 'MBA2', 'course_desc' => 'Updated', 'course_college' => 'Graduate School'])->assertRedirect();
        $this->actingAs($staff)->delete(route('staff.courses.destroy', $course))->assertRedirect()->assertSessionHas('success');
    }

    public function test_users_are_available_through_the_admin_crud_and_college_offices_are_public_only(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->postJson(route('public.college-offices.store'), ['name' => 'Registrar'])->assertCreated();
        $this->postJson(route('public.college-offices.store'), ['name' => 'Registrar'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('college_offices', ['name' => 'Registrar']);
    }

    public function test_academic_terms_have_unique_values_and_are_usable_by_ledger_resources(): void
    {
        $term = \App\Models\AcademicTerm::create(['school_year' => '2026-2027', 'semester' => 'First Semester']);
        $this->assertDatabaseHas('academic_terms', ['id' => $term->id]);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        \App\Models\AcademicTerm::create(['school_year' => '2026-2027', 'semester' => 'First Semester']);
    }

    /** @return array<string, mixed> */
    private function formInputPayload(array $overrides = []): array
    {
        $membership = $overrides['membership_id'] ?? Membership::firstOrCreate(['member_code' => 'FORM'], ['member_desc' => 'Form']);
        $option = $overrides['payment_detail_option_id'] ?? PaymentDetailOption::firstOrCreate(['payment_desc' => 'Form Option']);
        return array_merge([
            'reference_number' => 'FORM-'.uniqid(), 'email' => 'form@example.com', 'contact_num' => '09171234567',
            'firstname_or_office' => 'Test', 'lastname_or_agency' => 'User', 'office_or_college' => 'Office',
            'position_or_designation' => 'Staff', 'address' => 'Address', 'amount' => 1, 'request_type' => 'New Request',
            'membership_id' => $membership instanceof Membership ? $membership->id : $membership,
            'payment_detail_option_id' => $option instanceof PaymentDetailOption ? $option->id : $option,
        ], $overrides);
    }

    /** @return array<string, string> */
    private function uacsPayload(string $norsuCode): array
    {
        return ['norsu_object_code' => $norsuCode, 'norsu_sub_object_code' => '01', 'uacs_object_code' => '1234567890', 'uacs_sub_object_code' => '01', 'rca_code' => '12345678', 'account_title' => 'Tuition'];
    }
}

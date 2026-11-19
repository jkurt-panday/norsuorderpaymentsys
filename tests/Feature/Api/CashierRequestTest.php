<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\UACS;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_list_and_show_requests(): void
    {
        $cashier = User::factory()->cashier()->create();
        $form = FormInput::create([
            'reference_number' => 'OP-API-CASHIER-1', 'email' => 'cashier@example.com',
            'firstname_or_office' => 'Cashier', 'lastname_or_agency' => 'Student', 'contact_num' => '09123456789', 'position_or_designation' => 'Student',
            'office_or_college' => 'General', 'address' => 'Dumaguete', 'amount' => 500,
            'request_type' => 'New Request', 'membership_id' => Membership::create(['member_code' => 'API', 'member_desc' => 'Student'])->id,
            'payment_detail_option_id' => PaymentDetailOption::create(['payment_desc' => 'Tuition'])->id,
            'course_id' => Course::create(['course_code' => 'API', 'course_desc' => 'General', 'course_college' => 'Graduate School'])->id,
            'academic_term' => AcademicTerm::create(['school_year' => '2026-2027', 'semester' => 'First Semester'])->id,
        ]);
        $staff = StaffInput::create([
            'form_input_id' => $form->id,
            'fundcluster_id' => BankAccountInfo::create(['account_num' => 'API-1', 'account_name' => 'Main', 'bank_name' => 'Bank', 'fund_cluster' => '01'])->id,
            'ref_date' => '2026-11-01',
            'uacs_id' => UACS::create(['uacs_object_code' => 'API-1', 'account_title' => 'Tuition'])->id,
            'status' => 'processed',
        ]);

        $this->actingAs($cashier)->getJson('/api/v1/cashier/requests')
            ->assertOk()->assertJsonPath('data.0.id', $staff->id)
            ->assertJsonStructure(['data', 'meta', 'links']);
        $this->actingAs($cashier)->getJson("/api/v1/cashier/requests/{$staff->id}")
            ->assertOk()->assertJsonPath('data.id', $staff->id);
    }

    public function test_non_cashier_is_denied(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->getJson('/api/v1/cashier/requests')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\BankAccountInfo;
use App\Models\FormInput;
use App\Models\StaffInput;
use App\Models\UACS;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Staff and admin may correct an OR number the cashier already placed, but they
 * may not issue the first one — placing an OR number stays cashier-only so the
 * cashier remains the sole issuer of official receipts.
 */
class OpOrNumberCorrectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_correct_an_existing_or_number(): void
    {
        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput(['or_no' => '2026-001', 'or_date' => '2026-09-01']);

        $this->actingAs($staff)
            ->put("/staff/requests/{$staffInput->id}/or", [
                'or_no' => '2026-0099',
                'or_date' => '2026-09-05',
            ])
            ->assertRedirect(route('staff.requests.show', $staffInput->formInput))
            ->assertSessionHas('success');

        $staffInput->refresh();

        $this->assertSame('2026-0099', $staffInput->or_no);
        $this->assertSame('2026-09-05', $staffInput->or_date->format('Y-m-d'));
    }

    public function test_admin_can_correct_an_existing_or_number(): void
    {
        $admin = User::factory()->admin()->create();
        $staffInput = $this->makeStaffInput(['or_no' => '2026-001', 'or_date' => '2026-09-01']);

        $this->actingAs($admin)
            ->put("/staff/requests/{$staffInput->id}/or", [
                'or_no' => '2026-0020',
                'or_date' => '2026-09-02',
            ])
            ->assertRedirect(route('staff.requests.show', $staffInput->formInput))
            ->assertSessionHas('success');

        $this->assertSame('2026-0020', $staffInput->fresh()->or_no);
    }

    public function test_staff_cannot_place_an_or_number_when_none_exists(): void
    {
        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput();

        $this->actingAs($staff)
            ->put("/staff/requests/{$staffInput->id}/or", [
                'or_no' => '2026-7777',
                'or_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors('or_no');

        $this->assertNull($staffInput->fresh()->or_no);
    }

    public function test_admin_cannot_place_an_or_number_when_none_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $staffInput = $this->makeStaffInput();

        $this->actingAs($admin)
            ->put("/staff/requests/{$staffInput->id}/or", [
                'or_no' => '2026-7777',
                'or_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors('or_no');

        $this->assertNull($staffInput->fresh()->or_no);
    }

    public function test_or_number_format_is_validated(): void
    {
        $staff = User::factory()->staff()->create();
        $staffInput = $this->makeStaffInput(['or_no' => '2026-001', 'or_date' => '2026-09-01']);

        $this->actingAs($staff)
            ->put("/staff/requests/{$staffInput->id}/or", [
                'or_no' => 'OR#12345',
                'or_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors('or_no');

        $this->assertSame('2026-001', $staffInput->fresh()->or_no);
    }

    public function test_cashier_and_client_cannot_use_the_staff_or_correction_route(): void
    {
        foreach (['cashier', 'client'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $staffInput = $this->makeStaffInput(['or_no' => '2026-001', 'or_date' => '2026-09-01']);

            $this->actingAs($user)
                ->put("/staff/requests/{$staffInput->id}/or", [
                    'or_no' => '2026-8888',
                    'or_date' => '2026-09-05',
                ])
                ->assertForbidden();

            $this->assertSame('2026-001', $staffInput->fresh()->or_no);
        }
    }

    private function makeStaffInput(array $attributes = []): StaffInput
    {
        $formInput = FormInput::create([
            'reference_number' => 'OP-OR-'.uniqid(),
            'email' => 'client@example.com',
            'firstname_or_office' => 'Test',
            'lastname_or_agency' => 'Client',
            'amount' => 500,
            'membership_id' => null,
        ]);

        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees'],
        );

        return StaffInput::create(array_merge([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-09-01',
            'uacs_id' => $uacs->id,
            'status' => 'processed',
        ], $attributes));
    }
}
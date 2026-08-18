<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_provision_users(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('admin.users.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_admin_can_provision_a_staff_and_an_audit_entry_is_created(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->validPayload())
            ->assertRedirect();

        $user = User::where('email', 'new.staff@norsu.edu.ph')->firstOrFail();

        $this->assertDatabaseHas('activity_log', [
            'actor_id' => $admin->id,
            'action' => 'user.created',
            'target_id' => $user->id,
        ]);
    }

    public function test_invalid_roles_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('dashboard'))
            ->post(route('admin.users.store'), [...$this->validPayload(), 'role' => 'user'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('role');
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'name' => 'New Staff',
            'email' => 'new.staff@norsu.edu.ph',
            'password' => 'CorrectHorseBatteryStaple1!',
            'role' => 'staff',
        ];
    }
}

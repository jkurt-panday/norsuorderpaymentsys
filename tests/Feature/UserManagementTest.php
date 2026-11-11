<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users_with_filters_and_sorting(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Zed Staff', 'email' => 'zed@example.com', 'role' => 'staff']);
        User::factory()->create(['name' => 'Ana Cashier', 'email' => 'ana@example.com', 'role' => 'cashier']);

        $this->actingAs($admin)->get('/admin/users?search=Ana&role=cashier&sort=name&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/UserManagement')
                ->where('userFilters.search', 'Ana')
                ->where('userFilters.role', 'cashier')
                ->has('users.data', 1)
                ->where('users.data.0.email', 'ana@example.com'));
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        foreach (['staff', 'cashier'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/admin/users')->assertRedirect();
        }
    }

    public function test_admin_can_create_user_with_valid_role_and_hashed_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'password' => 'CorrectHorseBatteryStaple1!',
            'role' => 'staff',
        ])->assertRedirect();

        $user = User::where('email', 'new.staff@example.com')->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertTrue(Hash::check('CorrectHorseBatteryStaple1!', $user->password));
        $this->assertNotSame('CorrectHorseBatteryStaple1!', $user->password);
    }

    public function test_email_must_be_unique_and_role_must_be_valid(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->from('/admin/users')->post('/admin/users', [
            'name' => 'Invalid', 'email' => 'taken@example.com', 'password' => 'CorrectHorseBatteryStaple1!', 'role' => 'owner',
        ])->assertRedirect('/admin/users')->assertSessionHasErrors(['email', 'role']);
    }

    public function test_admin_can_update_user_and_change_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->staff()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => 'Updated User', 'email' => 'updated@example.com', 'password' => 'NewPassword1!', 'role' => 'cashier',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated User', $user->name);
        $this->assertSame('cashier', $user->role);
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }

    public function test_admin_can_deactivate_user_and_deactivated_user_cannot_authenticate(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->staff()->create(['email' => 'deactivated@example.com']);

        $this->actingAs($admin)->put("/admin/users/{$user->id}/toggle")->assertRedirect('/admin/users');
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->post('/logout');
        $this->from('/login')->post('/login', ['email' => 'deactivated@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_toggle_restores_soft_deleted_users_and_listing_includes_deleted_users(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->staff()->create();
        $user->delete();

        $this->actingAs($admin)->get('/admin/users')
            ->assertInertia(function (Assert $page) use ($user): void {
                $data = $page->toArray()['props']['users']['data'];
                $deleted = collect($data)->firstWhere('id', $user->id);
                $this->assertNotNull($deleted['deleted_at'] ?? null);
            });

        $this->actingAs($admin)->put("/admin/users/{$user->id}/toggle")
            ->assertRedirect('/admin/users');
        $this->assertNull($user->fresh()->deleted_at);
    }

    public function test_admin_cannot_delete_or_deactivate_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")
            ->assertSessionHas('error', 'You cannot delete your own account.');
        $this->actingAs($admin)->put("/admin/users/{$admin->id}/toggle")
            ->assertSessionHas('error', 'You cannot change the status of your own account.');
        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_admin_dashboard_shows_user_counts(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->staff()->count(2)->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('stats.staffCount', 2)
                ->where('stats.adminCount', 1)
                ->missing('stats.clientCount'));
    }

    public function test_user_deletion_is_soft_delete_and_deleted_user_can_be_updated(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->staff()->create();

        $this->actingAs($admin)->delete("/admin/users/{$user->id}")->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => 'Restored Details', 'email' => 'deleted.updated@example.com', 'password' => '', 'role' => 'staff',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Restored Details']);
    }
}

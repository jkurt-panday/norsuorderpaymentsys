<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard_activity_and_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = User::factory()->create(['role' => 'staff']);
        ActivityLog::create([
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'actor_role' => 'staff',
            'action' => 'updated', 'description' => 'Updated a request', 'subject_type' => User::class,
            'subject_id' => $actor->id,
        ]);

        $this->actingAs($admin)->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.staff_count', 1)->assertJsonPath('data.admin_count', 1);
        $this->actingAs($admin)->getJson('/api/v1/admin/activity-log')
            ->assertOk()->assertJsonStructure(['data', 'meta', 'links'])->assertJsonPath('data.0.action', 'updated');
        $this->actingAs($admin)->getJson('/api/v1/admin/users')
            ->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_admin_can_create_update_toggle_and_delete_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $create = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'name' => 'New Staff', 'email' => 'new.staff@example.com', 'password' => 'Password123!', 'role' => 'staff',
        ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$id}", [
            'name' => 'Updated Staff', 'email' => 'updated.staff@example.com', 'password' => 'NewPassword123!', 'role' => 'cashier',
        ])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $id, 'role' => 'cashier']);

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$id}/toggle")->assertOk()->assertJsonPath('data.deleted_at', fn ($value) => $value !== null);
        $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$id}")->assertNoContent();
    }

    public function test_non_admin_and_self_mutation_are_denied(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->getJson('/api/v1/admin/users')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson('/api/v1/admin/users/'.$staff->id.'/toggle')->assertOk();
    }
}

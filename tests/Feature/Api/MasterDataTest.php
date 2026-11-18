<?php

namespace Tests\Feature\Api;

use App\Models\AuthorizedOfficial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_crud_master_data_resources(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $payloads = [
            'memberships' => ['member_code' => 'M1', 'member_desc' => 'Student'],
            'payment-options' => ['payment_desc' => 'Tuition'],
            'bank-accounts' => ['account_name' => 'Main', 'bank_name' => 'Bank', 'account_num' => '001', 'fund_cluster' => '01'],
            'uacs' => ['uacs_object_code' => '100', 'account_title' => 'Tuition'],
            'authorized-officials' => ['name' => 'Official', 'position' => 'Director', 'course' => 'Graduate'],
            'courses' => ['course_code' => 'MBA', 'course_desc' => 'Master', 'course_college' => 'Graduate School'],
            'academic-terms' => ['school_year' => '2026-2027', 'semester' => 'First Semester'],
            'college-offices' => ['name' => 'Registrar'],
        ];

        foreach ($payloads as $resource => $payload) {
            $response = $this->actingAs($staff)->postJson('/api/v1/'.$resource, $payload)->assertCreated();
            $id = $response->json('data.id');
            $this->actingAs($staff)->getJson('/api/v1/'.$resource)->assertOk()->assertJsonPath('data.0.id', $id);
            $this->actingAs($staff)->putJson('/api/v1/'.$resource.'/'.$id, $payload)->assertOk();
            if ($resource !== 'authorized-officials') {
                $this->actingAs($staff)->deleteJson('/api/v1/'.$resource.'/'.$id)->assertNoContent();
            }
        }
    }

    public function test_authorized_official_activation_keeps_one_active_and_role_boundary_applies(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $first = AuthorizedOfficial::create(['name' => 'One', 'position' => 'Director', 'course' => 'Graduate', 'is_active' => true]);
        $second = AuthorizedOfficial::create(['name' => 'Two', 'position' => 'Director', 'course' => 'Law', 'is_active' => false]);
        $this->actingAs($staff)->putJson('/api/v1/authorized-officials/'.$second->id.'/set-active')->assertOk();
        $this->assertDatabaseHas('authorized_officials', ['id' => $first->id, 'is_active' => 0]);
        $this->assertDatabaseHas('authorized_officials', ['id' => $second->id, 'is_active' => 1]);

        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->getJson('/api/v1/memberships')->assertForbidden();
    }
}

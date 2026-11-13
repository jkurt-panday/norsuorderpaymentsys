<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_returns_json_envelope(): void
    {
        $this->getJson('/api/v1/auth/user')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_unknown_api_route_returns_json_envelope(): void
    {
        $this->getJson('/api/v1/this-does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_authenticated_user_is_returned_without_password(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)->getJson('/api/v1/auth/user')
            ->assertSuccessful()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonMissingPath('data.password');
    }

    public function test_cashier_cannot_access_staff_endpoint(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->getJson('/api/v1/staff/requests')
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_matching_origin_receives_cors_header(): void
    {
        $origin = config('app.url');

        $this->withHeaders(['Origin' => $origin])
            ->getJson('/api/v1/auth/user')
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_unknown_origin_does_not_receive_cors_header(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example.com'])
            ->getJson('/api/v1/auth/user');

        $this->assertNotSame(
            'https://evil.example.com',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_sanctum_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertSuccessful()
            ->assertCookieNotExpired('XSRF-TOKEN');
    }
}

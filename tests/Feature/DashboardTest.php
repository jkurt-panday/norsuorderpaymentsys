<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('staff.dashboard'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));

        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->get(route('dashboard'))->assertRedirect(route('cashier.requests.index'));
    }
}

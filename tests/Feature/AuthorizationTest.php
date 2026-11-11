<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_every_protected_area(): void
    {
        foreach ([
            'staff.dashboard',
            'staff.assessments.index',
            'cashier.requests.index',
            'graduate-ledger.index',
            'law-ledger.index',
            'staff.uacs.index',
            'staff.memberships.index',
            'staff.bank-accounts.index',
            'staff.payment-options.index',
            'staff.authorized-officials.index',
            'admin.users.index',
            'graduate-ledger.export',
            'graduate-ledger.pdf',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertInstanceOf(Route::class, $route);
            $this->assertContains('auth', $route->gatherMiddleware());

            $this->get($route->uri())->assertRedirect(route('login'));
        }
    }

    public function test_admin_routes_are_admin_only(): void
    {
        foreach (['admin.dashboard', 'admin.users.index', 'admin.activity-log'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertInstanceOf(Route::class, $route);
            $this->assertContains('admin', $route->gatherMiddleware());
        }

        foreach (['staff', 'cashier'] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.dashboard'));

            $response->assertRedirect();
            $this->assertNotSame(route('admin.dashboard'), $response->headers->get('Location'));
        }
    }

    public function test_staff_areas_allow_staff_and_admin_but_not_cashier(): void
    {
        foreach (['staff.dashboard', 'graduate-ledger.index', 'law-ledger.index', 'staff.uacs.index', 'staff.memberships.index', 'staff.bank-accounts.index', 'staff.payment-options.index', 'staff.authorized-officials.index'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertInstanceOf(Route::class, $route);
            $this->assertContains('staff', $route->gatherMiddleware());
        }

        $response = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('graduate-ledger.index'));

        $response->assertRedirect();
        $this->assertNotSame(route('graduate-ledger.index'), $response->headers->get('Location'));
    }

    public function test_cashier_area_is_cashier_only(): void
    {
        $route = app('router')->getRoutes()->getByName('cashier.requests.index');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains('cashier', $route->gatherMiddleware());

        foreach (['admin', 'staff'] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('cashier.requests.index'));

            $response->assertRedirect();
            $this->assertNotSame(route('cashier.requests.index'), $response->headers->get('Location'));
        }
    }

    public function test_mutating_routes_carry_role_middleware_against_crafted_requests(): void
    {
        $protectedMutations = [
            'admin.users.store' => 'admin',
            'staff.memberships.store' => 'staff',
            'staff.bank-accounts.store' => 'staff',
            'staff.payment-options.store' => 'staff',
            'staff.authorized-officials.store' => 'staff',
            'staff.uacs.store' => 'staff',
            'graduate-ledger.store' => 'staff',
            'law-ledger.store' => 'staff',
            'cashier.requests.payment.update' => 'cashier',
        ];

        foreach ($protectedMutations as $name => $requiredMiddleware) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertInstanceOf(Route::class, $route);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains($requiredMiddleware, $route->gatherMiddleware());
        }
    }

    public function test_delete_routes_are_protected_from_guest_requests(): void
    {
        foreach (['admin.users.destroy', 'staff.memberships.destroy', 'staff.bank-accounts.destroy', 'staff.payment-options.destroy', 'staff.authorized-officials.destroy', 'staff.uacs.destroy', 'graduate-ledger.destroy', 'law-ledger.destroy'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertInstanceOf(Route::class, $route);
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_no_api_endpoints_bypass_web_authorization(): void
    {
        $apiRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'));

        $this->assertCount(0, $apiRoutes);
    }
}

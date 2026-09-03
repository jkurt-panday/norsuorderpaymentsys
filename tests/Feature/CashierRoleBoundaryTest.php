<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsCashier;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CashierRoleBoundaryTest extends TestCase
{
    public function test_cashier_middleware_only_allows_cashiers(): void
    {
        $response = $this->runMiddleware(
            new EnsureUserIsCashier,
            $this->userWithRole('cashier'),
        );

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        foreach (['admin', 'staff', 'client'] as $role) {
            try {
                $this->runMiddleware(new EnsureUserIsCashier, $this->userWithRole($role));
                $this->fail("The cashier middleware allowed the [{$role}] role.");
            } catch (HttpException $exception) {
                $this->assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
            }
        }
    }

    public function test_staff_middleware_allows_staff_and_admin_but_denies_cashiers(): void
    {
        foreach (['staff', 'admin'] as $role) {
            $response = $this->runMiddleware(
                new EnsureUserIsStaff,
                $this->userWithRole($role),
            );

            $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }

        foreach (['cashier', 'client'] as $role) {
            try {
                $this->runMiddleware(new EnsureUserIsStaff, $this->userWithRole($role));
                $this->fail("The staff middleware allowed the [{$role}] role.");
            } catch (HttpException $exception) {
                $this->assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
            }
        }
    }

    public function test_payment_route_is_cashier_only_and_removed_from_staff_routes(): void
    {
        $paymentRoute = app('router')->getRoutes()
            ->getByName('cashier.requests.payment.update');

        $this->assertInstanceOf(IlluminateRoute::class, $paymentRoute);
        $this->assertContains('auth', $paymentRoute->gatherMiddleware());
        $this->assertContains('cashier', $paymentRoute->gatherMiddleware());
        $this->assertNull(
            app('router')->getRoutes()->getByName('staff.requests.updateOr'),
        );
    }

    public function test_staff_web_and_api_routes_require_staff_middleware(): void
    {
        $staffRoute = app('router')->getRoutes()->getByName('staff.requests.store');
        $ledgerRoute = app('router')->getRoutes()->getByName('graduate-ledger.store');

        $this->assertContains('staff', $staffRoute->gatherMiddleware());
        $this->assertContains('staff', $ledgerRoute->gatherMiddleware());

        $apiRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'api/staff/'));

        $this->assertNotEmpty($apiRoutes);
        $apiRoutes->each(function (IlluminateRoute $route): void {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('staff', $route->gatherMiddleware());
        });
    }

    public function test_denied_web_route_redirects_with_permission_message(): void
    {
        $this->withoutMiddleware(HandleInertiaRequests::class);

        $cashier = $this->userWithRole('cashier');
        $cashier->id = 1;

        $response = $this->actingAs($cashier)->get(route('staff.requests.index'));

        $response
            ->assertRedirect(route('cashier.requests.index'))
            ->assertSessionHas(
                'error',
                "You don't have permission to access that section.",
            );
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->make(['role' => $role]);
    }

    private function runMiddleware(object $middleware, User $user): Response
    {
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $user);

        return $middleware->handle(
            $request,
            fn (): Response => response()->noContent(),
        );
    }
}

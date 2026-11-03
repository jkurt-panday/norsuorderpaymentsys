<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCashier;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests; // 🌟 1. Make sure to import your admin middleware
use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare tunnels (trycloudflare.com) and other reverse proxies
        // terminate TLS and forward plain HTTP to the app, so Laravel would
        // otherwise think the request is insecure and emit http:// asset URLs
        // (mixed content). Trust the local/private proxy hops only.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecureHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'cashier' => EnsureUserIsCashier::class,
            'staff' => EnsureUserIsStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403 && ! $request->expectsJson()) {
                $user = $request->user();
                if (! $user) {
                    return redirect()->route('login')->with('error', 'Please log in to access this page.');
                }

                $targetRoute = match ($user->role) {
                    'admin' => 'admin.dashboard',
                    'staff' => 'staff.dashboard',
                    'cashier' => 'cashier.requests.index',
                    default => 'client.dashboard',
                };

                return redirect()->route($targetRoute)
                    ->with('error', "You don't have permission to access that section.");
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests; // 🌟 1. Make sure to import your admin middleware
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // 🌟 2. ADD THIS ALIAS BLOCK HERE:
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
                'staff' => \App\Http\Middleware\EnsureUserIsStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403 && ! $request->expectsJson()) {
                $user = $request->user();
                if (! $user) {
                    return redirect()->route('login')->with('error', 'Please log in to access this page.');
                }

                $targetRoute = match ($user->role) {
                    'admin' => 'admin.users.index',
                    'staff' => 'staff.dashboard',
                    default => 'client.dashboard',
                };

                return redirect()->route($targetRoute)->with('error', $e->getMessage() ?: 'Unauthorized access.');
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

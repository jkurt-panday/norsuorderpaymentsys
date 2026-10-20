<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * Asset versioning exists so a production deploy forces a hard reload of
     * the SPA. It must stay off outside production: the Vite dev server
     * serves the assets in local, and any rebuild of
     * public/build/manifest.json would change the hash and make every
     * Inertia request fail with a 409 Conflict.
     *
     * In local/testing the browser's version is echoed back so a tab that
     * was loaded with an older version can never trip the conflict check
     * either.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        if (! app()->environment('local', 'testing')) {
            return parent::version($request);
        }

        return $request->header(Header::VERSION);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // 🌟 1. SHARE AUTHENTICATED USER DATA GLOBALLY
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'profile' => $request->user()->profile,
                ] : null,
            ],

            // 🌟 2. SHARE SYSTEM FLASH MESSAGES FOR NOTIFICATIONS
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}

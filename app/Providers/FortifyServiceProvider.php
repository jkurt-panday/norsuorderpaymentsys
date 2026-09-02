<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use App\Models\User;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(LoginResponseContract::class, function () {
            return new class implements LoginResponseContract {
                public function toResponse($request)
                {
                    $user = auth()->user();

                    if ($user?->role === 'admin') {
                        return redirect('/admin/dashboard');
                    }

                    if ($user?->role === 'staff') {
                        return redirect('/staff/staffdashboard');
                    }

                    if ($user?->role === 'cashier') {
                        return redirect('/staff/requests');
                    }

                    if ($user?->role === 'client') {
                        return redirect('/client/dashboard');
                    }

                    abort(403, 'Invalid user role.');
                }
            };
        });

        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::withTrashed()->where('email', $request->email)->first();

            if ($user && $user->trashed()) {
                session()->flash('deactivated_account', true);

                throw ValidationException::withMessages([
                    'email' => __('Your account has been deactivated. Please contact an administrator.'),
                ]);
            }

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            if ($redirect = $this->redirectIfAuthenticated($request)) {
                return $redirect;
            }

            return Inertia::render('auth/login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'status' => $request->session()->get('status'),
                'deactivated_account' => $request->session()->get('deactivated_account') || $request->session()->get('google_deactivated_account'),
            ]);
        });

        Fortify::resetPasswordView(function (Request $request) {
            if ($redirect = $this->redirectIfAuthenticated($request)) {
                return $redirect;
            }

            return Inertia::render('auth/reset-password', [
                'email' => $request->email,
                'token' => $request->route('token'),
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        });

        Fortify::requestPasswordResetLinkView(function (Request $request) {
            if ($redirect = $this->redirectIfAuthenticated($request)) {
                return $redirect;
            }

            return Inertia::render('auth/forgot-password', [
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::confirmPasswordView(function (Request $request) {
            if ($redirect = $this->redirectIfAuthenticated($request)) {
                return $redirect;
            }

            return Inertia::render('auth/confirm-password');
        });
    }

    /**
     * Redirect an already-authenticated user to their role-based dashboard.
     */
    private function redirectIfAuthenticated(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return match ($user->role) {
            'admin' => redirect('/admin/dashboard'),
            'staff' => redirect('/staff/staffdashboard'),
            'cashier' => redirect('/staff/requests'),
            'client' => redirect('/client/dashboard'),
            default => redirect('/'),
        };
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // RateLimiter::for('passkeys', function (Request $request) {
        //     return Limit::perMinute(10)->by(
        //         ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
        //     );
        // });
    }
}

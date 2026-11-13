<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\FailedPasswordResetLinkRequestResponse;
use App\Http\Responses\FailedPasswordResetResponse;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\PasswordResetResponse;
use App\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Fortify response contracts are bound here so they are available before
     * boot() runs and can be overridden by tests via the service container.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);

        // Password-reset responses — only active when the feature is enabled.
        $this->app->singleton(
            SuccessfulPasswordResetLinkRequestResponseContract::class,
            SuccessfulPasswordResetLinkRequestResponse::class,
        );
        $this->app->singleton(
            FailedPasswordResetLinkRequestResponseContract::class,
            FailedPasswordResetLinkRequestResponse::class,
        );
        $this->app->singleton(
            PasswordResetResponseContract::class,
            PasswordResetResponse::class,
        );
        $this->app->singleton(
            FailedPasswordResetResponseContract::class,
            FailedPasswordResetResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        // Point password-reset emails at the React SPA instead of the Inertia
        // reset-password page. Falls back to the web route when FRONTEND_URL
        // is not set (local dev without the SPA running).
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $base.'/reset-password?token='.rawurlencode($token).'&email='.rawurlencode((string) $user->email);
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
                'deactivated_account' => $request->session()->get('deactivated_account'),
            ]);
        });

        Fortify::resetPasswordView(function (Request $request) {
            if ($redirect = $this->redirectIfAuthenticated($request)) {
                return $redirect;
            }

            $defaults = PasswordRule::defaults();

            return Inertia::render('auth/reset-password', [
                'email'         => $request->email,
                'token'         => $request->route('token'),
                'passwordRules' => $defaults instanceof PasswordRule && method_exists($defaults, 'toPasswordRulesString')
                    ? $defaults->toPasswordRulesString()
                    : '',
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
            return Inertia::render('auth/confirm-password');
        });
    }

    /**
     * Redirect an already-authenticated user to their role-based dashboard.
     */
    private function redirectIfAuthenticated(Request $request): ?RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return match ($user->role) {
            'admin' => redirect('/admin/dashboard'),
            'staff' => redirect('/staff/staffdashboard'),
            'cashier' => redirect('/cashier/requests'),
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

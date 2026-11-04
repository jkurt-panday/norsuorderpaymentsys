<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_guest_can_view_the_login_page(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('staff.dashboard', absolute: false));
    }

    public function test_successful_login_regenerates_the_session(): void
    {
        $user = User::factory()->staff()->create();
        $oldSessionId = $this->app['session']->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotSame($oldSessionId, $this->app['session']->getId());
    }

    public function test_login_rejects_an_incorrect_password(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_rejects_an_unknown_email(): void
    {
        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_validates_required_and_valid_email(): void
    {
        $this->post(route('login.store'), [
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.store'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_login_validates_required_password(): void
    {
        $this->post(route('login.store'), [
            'email' => 'user@example.com',
        ])->assertSessionHasErrors('password');
    }

    public function test_deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_from_the_login_page(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->get(route('login'))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_logout_and_the_session_is_invalidated(): void
    {
        $user = User::factory()->create();
        $oldSessionId = $this->app['session']->getId();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertNotSame($oldSessionId, $this->app['session']->getId());
    }

    public function test_logout_regenerates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $oldToken = csrf_token();

        $this->actingAs($user)->post(route('logout'));

        $this->assertNotSame($oldToken, csrf_token());
    }

    public function test_guest_logout_is_handled_safely(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_remember_me_persists_the_remember_token(): void
    {
        $user = User::factory()->staff()->create(['remember_token' => null]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}

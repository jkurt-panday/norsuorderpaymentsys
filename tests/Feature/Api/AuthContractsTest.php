<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthContractsTest extends TestCase
{
    use RefreshDatabase;

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_api_login_returns_json_user_on_success(): void
    {
        $user = User::factory()->create([
            'role'     => 'staff',
            'password' => Hash::make('Password1!'),
        ]);

        $this->postJson('/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('data.role', 'staff');
    }

    public function test_api_login_returns_422_on_bad_credentials(): void
    {
        User::factory()->create([
            'role'     => 'staff',
            'password' => Hash::make('Password1!'),
        ]);

        $this->postJson('/login', [
            'email'    => 'nobody@example.com',
            'password' => 'wrongpassword',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_api_login_returns_422_for_deactivated_account(): void
    {
        $user = User::factory()->create([
            'role'     => 'staff',
            'password' => Hash::make('Password1!'),
        ]);
        $user->delete(); // soft-delete = deactivated

        $this->postJson('/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_api_logout_returns_204(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)
            ->postJson('/logout')
            ->assertNoContent();
    }

    public function test_api_logout_unauthenticated_returns_401(): void
    {
        // Fortify's logout route requires an active session; unauthenticated
        // JSON callers receive 401, not a 5xx.
        $this->postJson('/logout')
            ->assertUnauthorized();
    }

    // ── Forgot password ───────────────────────────────────────────────────────

    public function test_api_forgot_password_returns_200_with_generic_message_for_known_email(): void
    {
        User::factory()->create(['email' => 'staff@example.com', 'role' => 'staff']);

        $this->postJson('/forgot-password', ['email' => 'staff@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);
    }

    public function test_api_forgot_password_returns_same_message_for_unknown_email(): void
    {
        // Must not reveal whether the address is registered.
        $known = $this->postJson('/forgot-password', ['email' => 'nobody@norsu.edu.ph'])
            ->assertOk()
            ->json('message');

        User::factory()->create(['email' => 'real@norsu.edu.ph', 'role' => 'staff']);

        $unknown = $this->postJson('/forgot-password', ['email' => 'real@norsu.edu.ph'])
            ->assertOk()
            ->json('message');

        $this->assertSame($known, $unknown);
    }

    public function test_api_forgot_password_validates_email_field(): void
    {
        $this->postJson('/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ── Reset password ────────────────────────────────────────────────────────

    public function test_api_reset_password_returns_422_for_invalid_token(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->postJson('/reset-password', [
            'token'                 => 'invalid-token',
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_api_reset_password_succeeds_with_valid_token(): void
    {
        $user = User::factory()->create([
            'role'     => 'staff',
            'password' => Hash::make('OldPassword1!'),
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

class AuthenticationTest extends BusinessTestCase
{
    public function test_guest_cannot_access_business_data(): void
    {
        $this->getJson($this->endpoint('products'))->assertUnauthorized();
    }

    public function test_registration_creates_company_and_admin_session(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Lenin', 'email' => 'LENIN@example.com', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123', 'organization_name' => 'Smart Company'])
            ->assertCreated()->assertJsonPath('data.email', 'lenin@example.com')->assertJsonPath('data.memberships.0.role', 'admin')->assertJsonMissingPath('data.password');
        $this->assertDatabaseHas('organizations', ['name' => 'Smart Company']);
        $this->assertAuthenticated('web');
    }

    public function test_login_and_logout_use_session_authentication(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => $this->admin->email, 'password' => 'password'])->assertOk()->assertJsonPath('data.id', $this->admin->id);
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_invalid_credentials_and_weak_passwords_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => $this->admin->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short', 'organization_name' => 'X'])->assertUnprocessable();
    }

    public function test_password_reset_does_not_enumerate_accounts(): void
    {
        Notification::fake();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->admin->email])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($this->admin, ResetPassword::class);
        $token = Password::createToken($this->admin);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $this->admin->email, 'token' => $token, 'password' => 'NewPassword1234', 'password_confirmation' => 'NewPassword1234'])->assertOk();
        $this->assertTrue(Hash::check('NewPassword1234', $this->admin->refresh()->password));
    }

    public function test_profile_requires_current_password(): void
    {
        $this->actingAs($this->admin)->patchJson('/api/v1/auth/profile', ['name' => 'Changed', 'email' => 'changed@example.com', 'current_password' => 'wrong'])->assertUnprocessable();
        $this->patchJson('/api/v1/auth/profile', ['name' => 'Changed', 'email' => 'changed@example.com', 'current_password' => 'password'])->assertOk();
    }
}

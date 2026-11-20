<?php

namespace Tests\Feature;

use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Services\SaleService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuthLifecycleTest extends BusinessTestCase
{
    public function test_registration_dispatches_verification_email_and_sets_last_login_at(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Roberto',
            'email' => 'roberto@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'organization_name' => 'Roberto Corp',
        ])->assertCreated();

        Notification::assertSentTo(
            User::where('email', 'roberto@example.com')->first(),
            VerifyEmail::class
        );

        $user = User::where('email', 'roberto@example.com')->first();
        $this->assertNotNull($user->last_login_at);
        $this->assertFalse($response->json('data.email_verified'));
        $this->assertNotNull($response->json('data.last_login_at'));
    }

    public function test_login_updates_last_login_at(): void
    {
        $this->assertNull($this->admin->last_login_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertNotNull($this->admin->refresh()->last_login_at);
    }

    public function test_email_verification_and_resend_endpoints(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        // Resend works for unverified user
        $this->actingAs($user)->postJson('/api/v1/auth/email/resend')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);

        // Unsigned verification attempts are rejected.
        $hash = sha1($user->getEmailForVerification());
        $this->getJson("/api/v1/auth/email/verify/{$user->id}/{$hash}")->assertForbidden();

        // A valid temporary signed URL marks the address as verified.
        $signedUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => $hash,
        ]);
        $this->getJson($signedUrl)->assertOk();

        $this->assertTrue($user->refresh()->hasVerifiedEmail());

        // Resending when already verified returns appropriate message
        $this->postJson('/api/v1/auth/email/resend')
            ->assertOk()
            ->assertJsonPath('message', 'Tu correo electrónico ya se encuentra verificado.');
    }

    public function test_profile_email_change_resets_verification(): void
    {
        Notification::fake();
        $this->admin->markEmailAsVerified();
        $this->assertTrue($this->admin->refresh()->hasVerifiedEmail());

        $this->actingAs($this->admin)->patchJson('/api/v1/auth/profile', [
            'name' => 'Admin Updated',
            'email' => 'newadminemail@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $this->assertFalse($this->admin->refresh()->hasVerifiedEmail());
        Notification::assertSentTo($this->admin, VerifyEmail::class);
    }

    public function test_change_password_from_profile_with_session_invalidation(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable();

        $this->actingAs($this->admin)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $this->admin->refresh()->password));
    }

    public function test_admin_can_invite_member_and_send_notification(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)->postJson($this->endpoint('invitations'), [
            'email' => 'colleague@example.com',
            'role' => 'manager',
        ])->assertCreated();

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $this->organization->id,
            'email' => 'colleague@example.com',
            'token' => $token,
        ]);

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'colleague@example.com';
            }
        );
    }

    public function test_public_invitation_inspection_and_acceptance_by_new_user(): void
    {
        Notification::fake();
        $role = Role::where('name', 'manager')->firstOrFail();
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by' => $this->admin->id,
            'email' => 'invitee@example.com',
            'role_id' => $role->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // Public inspection
        $this->getJson("/api/v1/invitations/{$invitation->token}")
            ->assertOk()
            ->assertJsonPath('data.organization.name', $this->organization->name)
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.is_valid', true);

        // Guest acceptance
        $acceptResponse = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
            'name' => 'Carlos Nuevo',
            'password' => 'CarlosPassword123!',
            'password_confirmation' => 'CarlosPassword123!',
        ])->assertOk();

        $user = User::where('email', 'invitee@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'active' => true,
        ]);

        $this->assertNotNull($invitation->refresh()->accepted_at);
        $this->assertSame($user->id, $invitation->accepted_by);
    }

    public function test_authenticated_user_can_accept_invitation_matching_email(): void
    {
        $role = Role::where('name', 'seller')->firstOrFail();
        $user = User::factory()->create(['email' => 'matching@example.com']);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by' => $this->admin->id,
            'email' => 'matching@example.com',
            'role_id' => $role->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)->postJson("/api/v1/invitations/{$invitation->token}/accept")->assertOk();

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'active' => true,
        ]);
    }

    public function test_invitation_rejected_for_existing_active_member(): void
    {
        $seller = $this->member('seller');

        $this->actingAs($this->admin)->postJson($this->endpoint('invitations'), [
            'email' => $seller->email,
            'role' => 'manager',
        ])->assertUnprocessable();
    }

    public function test_admin_can_deactivate_and_reactivate_member(): void
    {
        $seller = $this->member('seller');
        $membership = $seller->memberships()->where('organization_id', $this->organization->id)->firstOrFail();

        // Deactivate
        $this->actingAs($this->admin)->patchJson($this->endpoint("members/{$membership->id}"), [
            'active' => false,
        ])->assertOk();

        $this->assertFalse($membership->refresh()->active);
        $this->assertNotNull($membership->deactivated_at);

        // Reactivate
        $this->actingAs($this->admin)->patchJson($this->endpoint("members/{$membership->id}"), [
            'active' => true,
        ])->assertOk();

        $this->assertTrue($membership->refresh()->active);
        $this->assertNull($membership->deactivated_at);
    }

    public function test_deactivated_member_is_blocked_by_middleware_with_403(): void
    {
        $seller = $this->member('seller');
        $membership = $seller->memberships()->where('organization_id', $this->organization->id)->firstOrFail();

        $membership->update(['active' => false, 'deactivated_at' => now()]);

        $this->actingAs($seller)->getJson($this->endpoint('products'))->assertForbidden();
    }

    public function test_last_administrator_cannot_be_deactivated(): void
    {
        $adminMembership = $this->admin->memberships()->where('organization_id', $this->organization->id)->firstOrFail();

        $this->actingAs($this->admin)->patchJson($this->endpoint("members/{$adminMembership->id}"), [
            'active' => false,
        ])->assertConflict();
    }

    public function test_admin_can_remove_member_without_history(): void
    {
        $seller = $this->member('seller');
        $membership = $seller->memberships()->where('organization_id', $this->organization->id)->firstOrFail();

        $this->actingAs($this->admin)->deleteJson($this->endpoint("members/{$membership->id}"))->assertOk();

        $this->assertDatabaseMissing('organization_memberships', [
            'id' => $membership->id,
        ]);
    }

    public function test_admin_cannot_remove_member_with_sales_history_due_to_audit_integrity(): void
    {
        $seller = $this->member('seller');
        $membership = $seller->memberships()->where('organization_id', $this->organization->id)->firstOrFail();

        $product = $this->product(10);
        app(SaleService::class)->create($this->organization, $seller, [
            'request_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response = $this->actingAs($this->admin)->deleteJson($this->endpoint("members/{$membership->id}"))->assertConflict();
        $this->assertStringContainsString('registros históricos', $response->json('message'));
        $this->assertDatabaseHas('organization_memberships', [
            'id' => $membership->id,
        ]);
    }

    public function test_last_administrator_cannot_be_removed(): void
    {
        $adminMembership = $this->admin->memberships()->where('organization_id', $this->organization->id)->firstOrFail();

        $this->actingAs($this->admin)->deleteJson($this->endpoint("members/{$adminMembership->id}"))->assertConflict();
    }
}

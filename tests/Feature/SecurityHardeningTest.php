<?php

namespace Tests\Feature;

use App\Models\SecurityAuditLog;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class SecurityHardeningTest extends BusinessTestCase
{
    public static function sensitiveRoutes(): array
    {
        return [
            'registration' => ['POST', '/api/v1/auth/register', 'registration'],
            'login' => ['POST', '/api/v1/auth/login', 'login'],
            'password recovery' => ['POST', '/api/v1/auth/forgot-password', 'password-recovery'],
            'email verification' => ['GET', '/api/v1/auth/email/verify/1/hash', 'email-verification'],
            'invitation inspection' => ['GET', '/api/v1/invitations/token', 'invitation-inspect'],
            'invitation acceptance' => ['POST', '/api/v1/invitations/token/accept', 'invitation-accept'],
            'profile update' => ['PATCH', '/api/v1/auth/profile', 'sensitive'],
            'member update' => ['PATCH', '/api/v1/organizations/1/members/1', 'sensitive'],
            'inventory write' => ['POST', '/api/v1/organizations/1/inventory-movements', 'business-write'],
            'sale cancellation' => ['POST', '/api/v1/organizations/1/sales/1/cancel', 'business-write'],
            'AI execution' => ['POST', '/api/v1/organizations/1/ai/tools/execute', 'ai-tools'],
        ];
    }

    #[DataProvider('sensitiveRoutes')]
    public function test_sensitive_routes_have_specific_and_global_rate_limits(string $method, string $uri, string $limiter): void
    {
        $route = app('router')->getRoutes()->match(Request::create($uri, $method));
        $middleware = $route->gatherMiddleware();

        $this->assertContains('throttle:api', $middleware);
        $this->assertContains("throttle:{$limiter}", $middleware);
    }

    public function test_cookie_and_cors_configuration_is_fail_closed(): void
    {
        $this->assertTrue(config('session.encrypt'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue(config('cors.supports_credentials'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertNotContains('*', config('cors.allowed_methods'));
        $this->assertNotContains('*', config('cors.allowed_headers'));
    }

    public function test_security_headers_and_request_identifier_are_present(): void
    {
        $response = $this->actingAs($this->admin)->getJson($this->endpoint('products'))->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Content-Security-Policy', config('security.headers.content_security_policy'));
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', (string) $response->headers->get('X-Request-ID'));
    }

    public function test_oversized_json_requests_are_rejected_before_controllers(): void
    {
        config(['security.max_request_bytes' => 1024]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => str_repeat('x', 2048),
        ])->assertStatus(413)->assertJsonPath('message', 'La solicitud supera el tamaño máximo permitido.');
    }

    public function test_production_api_errors_hide_internal_details(): void
    {
        Route::middleware('api')->get('/api/security-test/exception', function () {
            throw new RuntimeException('SQLSTATE secret-internal-detail');
        });
        $this->app['env'] = 'production';

        $response = $this->getJson('/api/security-test/exception')->assertStatus(500);

        $response->assertJsonPath('message', 'Ocurrió un error interno. Inténtalo nuevamente.');
        $response->assertJsonMissing(['SQLSTATE secret-internal-detail']);
        $this->assertNotEmpty($response->json('request_id'));
    }

    public function test_password_change_revokes_other_sessions_and_api_tokens(): void
    {
        DB::table('sessions')->insert([
            'id' => 'other-browser-session',
            'user_id' => $this->admin->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $this->admin->createToken('old-device');

        $this->actingAs($this->admin)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-browser-session']);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->admin->id]);
        $this->assertNotNull($this->admin->refresh()->remember_token);
    }

    public function test_deactivation_revokes_sessions_tokens_and_is_audited(): void
    {
        $seller = $this->member('seller');
        $membership = $seller->memberships()->firstOrFail();
        DB::table('sessions')->insert([
            'id' => 'seller-session',
            'user_id' => $seller->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $seller->createToken('seller-device');

        $this->actingAs($this->admin)->patchJson($this->endpoint("members/{$membership->id}"), [
            'active' => false,
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'seller-session']);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $seller->id]);
        $this->assertDatabaseHas('security_audit_logs', [
            'organization_id' => $this->organization->id,
            'actor_id' => $this->admin->id,
            'event' => 'membership.deactivated',
            'subject_id' => $membership->id,
        ]);
    }

    public function test_role_stock_and_cancellation_changes_create_tenant_scoped_audit_logs(): void
    {
        $seller = $this->member('seller');
        $membership = $seller->memberships()->firstOrFail();
        $this->actingAs($this->admin)->patchJson($this->endpoint("members/{$membership->id}"), [
            'role' => 'manager',
        ])->assertOk();

        $product = $this->product(10);
        $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'direction' => 'increase',
            'quantity' => 2,
            'reason' => 'Conteo físico de seguridad',
        ])->assertCreated();

        $sale = app(SaleService::class)->create($this->organization, $this->admin, [
            'request_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $this->postJson($this->endpoint("sales/{$sale->id}/cancel"), [
            'reason' => 'Anulación autorizada por control interno',
        ])->assertOk();

        foreach (['membership.role_changed', 'inventory.adjusted', 'sale.cancelled'] as $event) {
            $this->assertDatabaseHas('security_audit_logs', [
                'organization_id' => $this->organization->id,
                'actor_id' => $this->admin->id,
                'event' => $event,
            ]);
        }

        $this->getJson($this->endpoint('security-audit-logs'))
            ->assertOk()
            ->assertJsonPath('total', SecurityAuditLog::forOrganization($this->organization)->count());
        $this->actingAs($seller)->getJson($this->endpoint('security-audit-logs'))->assertForbidden();
    }

    public function test_password_recovery_has_an_independent_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertTooManyRequests();
    }
}

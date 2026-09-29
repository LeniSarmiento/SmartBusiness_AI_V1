<?php

namespace Tests\Feature;

class SecurityTest extends BusinessTestCase
{
    public function test_spa_requests_require_csrf_outside_testing_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/v1/auth/login', ['email' => $this->admin->email, 'password' => 'password'])->assertStatus(419);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'bad'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_unauthorized_origins_do_not_receive_cors_permission(): void
    {
        $response = $this->withHeader('Origin', 'https://untrusted.example')->options('/api/v1/auth/login', [], [
            'Access-Control-Request-Method' => 'POST',
        ]);
        // The CORS library may emit the one configured origin as a static header.
        $this->assertNotSame('https://untrusted.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}

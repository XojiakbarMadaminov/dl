<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorsTest extends TestCase
{
    public static function localOrigins(): array
    {
        return [
            ['http://localhost'],
            ['http://localhost:3000'],
            ['http://localhost:5173'],
            ['https://localhost:8443'],
            ['http://127.0.0.1'],
            ['http://127.0.0.1:4200'],
            ['http://[::1]:3000'],
            ['https://[::1]:5173'],
        ];
    }

    #[DataProvider('localOrigins')]
    public function test_local_frontend_preflight_allows_api_methods_and_bearer_headers(string $origin): void
    {
        $response = $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'PUT',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/v1/respondent/participations/1/pages/1');

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertHeader('Access-Control-Max-Age', '600');
        $this->assertStringContainsString('PUT', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('authorization', strtolower($response->headers->get('Access-Control-Allow-Headers')));
        $this->assertStringContainsString('content-type', strtolower($response->headers->get('Access-Control-Allow-Headers')));
    }

    public static function untrustedOrigins(): array
    {
        return [
            ['http://localhost.evil.example:3000'],
            ['http://evil-localhost:3000'],
            ['http://127.0.0.1.evil.example:3000'],
            ['http://user@localhost:3000'],
            ['https://untrusted.example'],
            ['file://localhost'],
            ['null'],
        ];
    }

    #[DataProvider('untrustedOrigins')]
    public function test_untrusted_origins_do_not_receive_cors_permission(string $origin): void
    {
        $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/v1/auth/login')->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_success_and_error_responses_remain_readable_from_localhost(): void
    {
        $origin = 'http://localhost:5173';
        $this->withHeader('Origin', $origin);
        $this->getJson('/api/v1/health')->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertHeader('Access-Control-Expose-Headers', 'Content-Disposition');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertHeader('Access-Control-Allow-Origin', $origin);
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertHeader('Access-Control-Allow-Origin', $origin);
        $this->getJson('/api/v1/non-existent-endpoint')->assertNotFound()->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_explicit_production_origin_can_be_allowed_without_localhost(): void
    {
        config()->set('cors.allowed_origins_patterns', []);
        config()->set('cors.allowed_origins', ['https://frontend.example']);

        $this->withHeader('Origin', 'https://frontend.example')->getJson('/api/v1/health')
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', 'https://frontend.example');
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/health')->assertHeader('Access-Control-Allow-Origin', 'https://frontend.example');
    }

    public function test_cors_does_not_apply_to_non_api_pages(): void
    {
        $this->withHeader('Origin', 'http://localhost:3000')->get('/up')
            ->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}

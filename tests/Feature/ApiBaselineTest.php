<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_v1_health_check_returns_success(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'version' => 'v1',
            ])
            ->assertJsonStructure([
                'status',
                'version',
                'timestamp',
            ]);
    }

    public function test_api_v1_route_contexts_are_accessible(): void
    {
        $this->getJson('/api/v1/auth/ping')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'context' => 'auth']);

        $this->getJson('/api/v1/business/ping')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'context' => 'business']);

        $this->getJson('/api/v1/respondent/ping')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'context' => 'respondent']);

        $this->getJson('/api/v1/admin/ping')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'context' => 'admin']);
    }

    public function test_api_404_returns_standard_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/non-existent-endpoint');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'fields',
                ],
            ])
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_api_validation_error_returns_standard_envelope(): void
    {
        Route::post('/api/v1/test-validation', function () {
            request()->validate([
                'title' => 'required',
            ]);
        });

        $response = $this->postJson('/api/v1/test-validation', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'fields' => ['title'],
                ],
            ]);
    }

    public function test_custom_api_exception_returns_specified_code_and_fields(): void
    {
        Route::get('/api/v1/test-custom-exception', function () {
            throw new ApiException(
                message: 'Active survey cannot be edited.',
                errorCode: 'SURVEY_NOT_EDITABLE',
                statusCode: 422,
                fields: ['status' => ['Immutable state.']]
            );
        });

        $response = $this->getJson('/api/v1/test-custom-exception');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'SURVEY_NOT_EDITABLE',
                    'message' => 'Active survey cannot be edited.',
                    'fields' => [
                        'status' => ['Immutable state.'],
                    ],
                ],
            ]);
    }

    public function test_sanctum_token_can_be_issued_and_used(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        Route::middleware('auth:sanctum')->get('/api/v1/test-protected', function () {
            return response()->json(['message' => 'authenticated', 'user_id' => auth()->id()]);
        });

        // Unauthenticated access
        $this->getJson('/api/v1/test-protected')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');

        // Authenticated access with bearer token
        $this->withToken($token)
            ->getJson('/api/v1/test-protected')
            ->assertStatus(200)
            ->assertJson([
                'message' => 'authenticated',
                'user_id' => $user->id,
            ]);
    }
}

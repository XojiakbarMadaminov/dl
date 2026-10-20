<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Enums\PlatformRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Organization\Actions\CreateOrganizationAction;
use App\Modules\Organization\Enums\OrganizationRole;
use App\Modules\Organization\Models\OrganizationMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityAndOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'locale' => 'uz',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'john@example.com')
            ->assertJsonPath('user.platform_role', 'user')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'locale']]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'platform_role' => 'user',
            'status' => 'active',
        ]);
    }

    public function test_business_registration_with_organization_creates_org_and_owner(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Biznes Egasi',
            'email' => 'owner@biznes.uz',
            'password' => 'SecurePass123!',
            'locale' => 'uz',
            'organization_name' => 'Katta Biznes MCHJ',
        ]);

        $response->assertStatus(201);
        $userId = $response->json('user.id');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Katta Biznes MCHJ',
            'created_by' => $userId,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $userId,
            'role' => 'owner',
        ]);
    }

    public function test_admin_privilege_cannot_be_self_assigned(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'SecurePass123!',
            'platform_role' => 'admin',
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'hacker@example.com')->firstOrFail();
        $this->assertEquals(PlatformRole::USER, $user->platform_role);
    }

    public function test_registration_validation_enforces_rules(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'invalid-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['fields' => ['name', 'email', 'password']]]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_registration_checks_uniqueness_after_normalizing_email(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate Owner',
            'email' => ' OWNER@EXAMPLE.COM ',
            'password' => 'SecurePass123!',
            'organization_name' => 'Duplicate Organization',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['fields' => ['email']]]);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_password_confirmation_is_checked_when_supplied(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'New User', 'email' => 'new@example.com',
            'password' => 'SecurePass123!', 'password_confirmation' => 'Different123!',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_uses_validated_device_name_and_logout_only_revokes_current_token(): void
    {
        $user = User::factory()->create(['email' => 'device@example.com', 'password' => 'SecurePass123!']);
        $otherToken = $user->createToken('other-device')->plainTextToken;
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => ' DEVICE@EXAMPLE.COM ', 'password' => 'SecurePass123!', 'device_name' => 'Android',
        ])->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'Android']);

        $this->withToken($login->json('token'))->postJson('/api/v1/auth/logout')->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($login->json('token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_device_names(): void
    {
        foreach ([['nested'], str_repeat('x', 256)] as $deviceName) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'device@example.com', 'password' => 'SecurePass123!', 'device_name' => $deviceName,
            ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_auth_rate_limit_is_shared_by_login_and_registration(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'missing@example.com', 'password' => 'WrongPassword!',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com', 'password' => 'WrongPassword!',
        ])->assertStatus(429)->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '20');
        $this->postJson('/api/v1/auth/register', [
            'name' => 'New User', 'email' => 'new@example.com', 'password' => 'SecurePass123!',
        ])->assertStatus(429)->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
        $this->assertDatabaseCount('users', 0);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com', 'password' => 'WrongPassword!',
        ])->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');
    }

    public function test_authenticated_user_can_get_profile_and_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $meResponse = $this->withToken($token)->getJson('/api/v1/auth/me');
        $meResponse->assertStatus(200)
            ->assertJsonPath('user.id', $user->id);

        $logoutResponse = $this->withToken($token)->postJson('/api/v1/auth/logout');
        $logoutResponse->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        app('auth')->forgetGuards();

        // After logout, token is revoked
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_user_can_create_organization_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/business/organizations', [
            'name' => 'Acme Research Corp',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('organization.name', 'Acme Research Corp')
            ->assertJsonPath('organization.current_user_role', 'owner')
            ->assertJsonPath('organization.created_by', $user->id);

        $organizationId = $response->json('organization.id');

        $this->assertDatabaseHas('organizations', [
            'id' => $organizationId,
            'name' => 'Acme Research Corp',
            'created_by' => $user->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_can_only_view_organizations_they_belong_to(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $action = new CreateOrganizationAction;
        $orgA = $action->execute('Org A', $userA);
        $orgB = $action->execute('Org B', $userB);

        // User A views Org A -> allowed
        $this->actingAs($userA)->getJson("/api/v1/business/organizations/{$orgA->id}")
            ->assertStatus(200)
            ->assertJsonPath('organization.name', 'Org A');

        // User A views Org B -> forbidden
        $this->actingAs($userA)->getJson("/api/v1/business/organizations/{$orgB->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // User A lists organizations -> only sees Org A
        $listResponse = $this->actingAs($userA)->getJson('/api/v1/business/organizations');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orgA->id);
    }

    public function test_only_owner_and_manager_can_update_organization(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $viewer = User::factory()->create();

        $action = new CreateOrganizationAction;
        $org = $action->execute('Original Org', $owner);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $manager->id,
            'role' => OrganizationRole::MANAGER,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $viewer->id,
            'role' => OrganizationRole::VIEWER,
        ]);

        // Manager can update
        $this->actingAs($manager)->patchJson("/api/v1/business/organizations/{$org->id}", [
            'name' => 'Updated by Manager',
        ])->assertStatus(200)
            ->assertJsonPath('organization.name', 'Updated by Manager');

        // Viewer cannot update
        $this->actingAs($viewer)->patchJson("/api/v1/business/organizations/{$org->id}", [
            'name' => 'Updated by Viewer',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_platform_admin_can_view_any_organization(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $action = new CreateOrganizationAction;
        $org = $action->execute('Private Org', $user);

        // Platform admin can view
        $this->actingAs($admin)->getJson("/api/v1/business/organizations/{$org->id}")
            ->assertStatus(200)
            ->assertJsonPath('organization.id', $org->id);
    }
}

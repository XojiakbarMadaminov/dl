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

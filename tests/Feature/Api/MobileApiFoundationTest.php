<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_api_version(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-API-Version', 'v1')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.version', 'v1');
    }

    public function test_active_user_with_api_permission_can_login(): void
    {
        $user = $this->createUser(UserRole::DRIVER);

        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload(
            $user->email,
            'driver-device-0001',
        ));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.bootstrap.user.role', UserRole::DRIVER->value)
            ->assertJsonPath('data.bootstrap.api.version', 'v1')
            ->assertJsonPath('data.bootstrap.scope.unrestricted', false);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'device_id' => 'driver-device-0001',
            'platform' => 'android',
        ]);
    }

    public function test_user_with_driver_and_sales_roles_can_login(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        $user->assignRole(UserRole::SALES_REPRESENTATIVE->value);

        $this->postJson('/api/v1/auth/login', $this->loginPayload(
            $user->email,
            'dual-field-device-0001',
        ))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'device_id' => 'dual-field-device-0001',
        ]);
    }

    public function test_non_field_role_cannot_login_even_with_api_permission(): void
    {
        $user = $this->createUser(UserRole::MANAGER);

        $this->postJson('/api/v1/auth/login', $this->loginPayload(
            $user->email,
            'manager-device-0001',
        ))
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'mobile_role_denied');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_existing_token_is_denied_after_field_roles_are_removed(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        $token = $user->createToken(
            'mobile:android:field-role-change',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;

        $user->syncRoles([UserRole::MANAGER->value]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'mobile_role_denied');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_credentials_use_standard_validation_response(): void
    {
        $user = $this->createUser(UserRole::DRIVER);

        $this->postJson('/api/v1/auth/login', [
            ...$this->loginPayload($user->email, 'driver-device-0002'),
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_protected_endpoint_requires_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        config()->set('mobile_api.login_rate_limit_per_minute', 2);
        $user = $this->createUser(UserRole::DRIVER);
        $payload = [
            ...$this->loginPayload($user->email, 'rate-limit-device'),
            'password' => 'wrong-password',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'http_429');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->createUser(UserRole::DRIVER, User::STATUS_INACTIVE);

        $this->postJson('/api/v1/auth/login', $this->loginPayload(
            $user->email,
            'driver-device-0003',
        ))
            ->assertForbidden()
            ->assertJsonPath('code', 'account_inactive');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_role_without_api_permission_cannot_login(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        Role::findByName(UserRole::DRIVER->value)
            ->revokePermissionTo(PermissionName::API_ACCESS->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->postJson('/api/v1/auth/login', $this->loginPayload(
            $user->email,
            'driver-device-0004',
        ))
            ->assertForbidden()
            ->assertJsonPath('code', 'api_access_denied');
    }

    public function test_me_returns_permissions_and_effective_scope(): void
    {
        $user = $this->createUser(UserRole::DRIVER);

        $token = $this->loginAndGetToken(
            $user,
            'driver-device-me-0001',
        );

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', UserRole::DRIVER->value)
            ->assertJsonPath('data.scope.unrestricted', false)
            ->assertJsonPath('data.scope.has_assignments', false);

        $this->assertContains(
            PermissionName::API_ACCESS->value,
            $response->json('data.user.permissions'),
        );
    }

    public function test_logging_in_again_on_same_device_rotates_token(): void
    {
        $user = $this->createUser(UserRole::DRIVER);

        $first = $this->loginAndGetToken($user, 'driver-device-rotate');
        $second = $this->loginAndGetToken($user, 'driver-device-rotate');

        $this->assertNotSame($first, $second);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_session_limit_removes_oldest_mobile_session(): void
    {
        config()->set('mobile_api.max_sessions', 2);
        $user = $this->createUser(UserRole::DRIVER);

        $this->loginAndGetToken($user, 'driver-device-limit-1');
        $this->loginAndGetToken($user, 'driver-device-limit-2');
        $this->loginAndGetToken($user, 'driver-device-limit-3');

        $this->assertSame(2, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'device_id' => 'driver-device-limit-1',
        ]);
    }

    public function test_user_can_list_and_revoke_only_their_own_sessions(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        $other = $this->createUser(UserRole::SALES_REPRESENTATIVE);

        $currentToken = $this->loginAndGetToken($user, 'driver-device-session-1');
        $this->loginAndGetToken($user, 'driver-device-session-2');
        $this->loginAndGetToken($other, 'sales-device-session-1');

        $sessionToRevoke = $user->tokens()
            ->where('device_id', 'driver-device-session-2')
            ->firstOrFail();
        $otherSession = $other->tokens()->firstOrFail();

        $this->withToken($currentToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data.sessions')
            ->assertJsonMissing([
                'id' => $otherSession->id,
            ]);

        $this->withToken($currentToken)
            ->deleteJson('/api/v1/auth/sessions/'.$sessionToRevoke->id)
            ->assertOk()
            ->assertJsonPath('data.current_session_revoked', false);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $sessionToRevoke->id,
        ]);

        $this->withToken($currentToken)
            ->deleteJson('/api/v1/auth/sessions/'.$otherSession->id)
            ->assertNotFound();
    }

    public function test_logout_and_logout_all_revoke_mobile_tokens(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        $first = $this->loginAndGetToken($user, 'driver-device-logout-1');

        $this->withToken($first)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        $second = $this->loginAndGetToken($user, 'driver-device-logout-2');
        $this->loginAndGetToken($user, 'driver-device-logout-3');

        $this->withToken($second)
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk()
            ->assertJsonPath('data.revoked_sessions', 2);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_token_without_required_ability_is_rejected(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        $token = $user->createToken('mobile:android:test', ['wrong:ability']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'token_ability_denied');
    }

    public function test_password_change_revokes_all_mobile_tokens(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        $this->loginAndGetToken($user, 'driver-device-password-change-1');
        $this->loginAndGetToken($user, 'driver-device-password-change-2');

        $this->assertSame(2, $user->tokens()->count());

        $user->update(['password' => 'new-secure-password']);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_token_is_revoked_when_account_becomes_inactive(): void
    {
        $user = $this->createUser(UserRole::DRIVER);
        $token = $this->loginAndGetToken($user, 'driver-device-inactive');
        $user->update(['status' => User::STATUS_INACTIVE]);

        $this->assertSame(0, $user->tokens()->count());

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    private function createUser(
        UserRole $role,
        string $status = User::STATUS_ACTIVE,
    ): User {
        return User::factory()->create([
            'role' => $role->value,
            'status' => $status,
        ]);
    }

    /** @return array<string, string|null> */
    private function loginPayload(string $email, string $deviceId): array
    {
        return [
            'email' => $email,
            'password' => 'password',
            'device_id' => $deviceId,
            'device_name' => 'اختبار Flutter',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ];
    }

    private function loginAndGetToken(User $user, string $deviceId): string
    {
        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload(
            $user->email,
            $deviceId,
        ));

        $response->assertOk();

        return (string) $response->json('data.token');
    }
}

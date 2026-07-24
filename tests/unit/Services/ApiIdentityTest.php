<?php

namespace App\Tests\Unit\Services;

use App\Services\ApiIdentity;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit coverage for the request-scoped API token identity.
 *
 * The critical contract is that the user array ApiIdentity builds matches the
 * shape Auth::login() writes to the session — otherwise role checks and
 * provider scoping behave differently for token callers than session callers.
 */
final class ApiIdentityTest extends CIUnitTestCase
{
    private function key(array $overrides = []): array
    {
        return array_merge([
            'id'          => 7,
            'business_id' => 1,
            'user_id'     => 42,
            'name'        => 'Zapier prod',
            'scopes'      => null,
        ], $overrides);
    }

    private function user(array $overrides = []): array
    {
        return array_merge([
            'id'            => 42,
            'name'          => 'Dana Provider',
            'email'         => 'dana@example.com',
            'role'          => 'provider',
            'profile_image' => null,
        ], $overrides);
    }

    public function testStartsUnauthenticated(): void
    {
        $identity = new ApiIdentity();

        $this->assertFalse($identity->isTokenRequest());
        $this->assertFalse($identity->isAuthenticated());
        $this->assertNull($identity->user());
        $this->assertNull($identity->userId());
        $this->assertNull($identity->businessId());
        $this->assertNull($identity->keyId());
        $this->assertSame([], $identity->roles());
    }

    public function testBuildsSessionShapedUserArray(): void
    {
        $identity = new ApiIdentity();
        $identity->setFromApiKey($this->key(), $this->user(), ['provider']);

        $user = $identity->user();

        // Exactly the keys Auth::login() writes to session()->get('user').
        $this->assertSame(
            ['id', 'name', 'email', 'role', 'roles', 'active_role', 'profile_image'],
            array_keys($user)
        );

        $this->assertSame(42, $user['id']);
        $this->assertSame('dana@example.com', $user['email']);
        $this->assertSame(['provider'], $user['roles']);
        $this->assertSame('provider', $user['active_role']);
        $this->assertTrue($identity->isTokenRequest());
        $this->assertSame(42, $identity->userId());
        $this->assertSame(7, $identity->keyId());
        $this->assertSame('Zapier prod', $identity->keyName());
    }

    public function testActiveRoleUsesHighestPrivilegeInTheRoleSet(): void
    {
        $identity = new ApiIdentity();
        // Compatibility primary role is the *lowest* of the set here — the
        // authoritative roles array must still win.
        $identity->setFromApiKey($this->key(), $this->user(['role' => 'staff']), ['staff', 'admin', 'provider']);

        $this->assertSame('admin', $identity->user()['active_role']);
        $this->assertSame(['staff', 'admin', 'provider'], $identity->roles());
    }

    public function testBusinessIdIsNeverBelowOne(): void
    {
        $identity = new ApiIdentity();
        $identity->setFromApiKey($this->key(['business_id' => 0]), $this->user(), ['admin']);

        $this->assertSame(1, $identity->businessId());
    }

    public function testUnscopedKeyInheritsRolePermissionsAndSatisfiesEveryScope(): void
    {
        $identity = new ApiIdentity();
        $identity->setFromApiKey($this->key(['scopes' => null]), $this->user(), ['admin']);

        $this->assertNull($identity->scopes());
        $this->assertTrue($identity->hasScope('appointments:read'));
        $this->assertTrue($identity->hasScope('anything:at:all'));
    }

    public function testScopedKeyOnlySatisfiesDeclaredScopes(): void
    {
        $identity = new ApiIdentity();
        $identity->setFromApiKey(
            $this->key(['scopes' => json_encode(['appointments:read', 'calendar:read'])]),
            $this->user(),
            ['admin']
        );

        $this->assertSame(['appointments:read', 'calendar:read'], $identity->scopes());
        $this->assertTrue($identity->hasScope('appointments:read'));
        $this->assertFalse($identity->hasScope('appointments:write'));
    }

    public function testMalformedScopesJsonFallsBackToInheritingPermissions(): void
    {
        $identity = new ApiIdentity();
        $identity->setFromApiKey($this->key(['scopes' => 'not-json']), $this->user(), ['admin']);

        $this->assertNull($identity->scopes());
    }

    public function testUnauthenticatedIdentityNeverSatisfiesAScope(): void
    {
        $this->assertFalse((new ApiIdentity())->hasScope('appointments:read'));
    }

    public function testResolveActiveRoleHelperMatchesTheLoginHierarchy(): void
    {
        helper('permissions');

        $this->assertSame('admin', resolve_active_role(['staff', 'admin'], 'staff'));
        $this->assertSame('provider', resolve_active_role(['provider'], 'staff'));
        $this->assertSame('staff', resolve_active_role(['staff'], 'staff'));
        // No authoritative roles → the compatibility primary role stands.
        $this->assertSame('admin', resolve_active_role([], 'admin'));
        $this->assertSame('', resolve_active_role([], null));
        // Unknown roles never outrank a known one.
        $this->assertSame('admin', resolve_active_role(['admin', 'nonsense'], ''));
    }
}

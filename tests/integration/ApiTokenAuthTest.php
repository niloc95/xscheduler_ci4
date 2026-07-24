<?php

namespace App\Tests\Integration;

use App\Models\ApiKeyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * End-to-end coverage for Bearer token authentication on the API surface.
 *
 * Guards the five things that made the token surface undocumentable:
 *   1. tokens actually reach appointments/calendar (not just settings)
 *   2. tokens are per-user, revocable and expirable (no shared secret)
 *   3. token requests carry an identity, so RBAC and scoping work
 *   4. a session cannot mask an invalid token
 *   5. /api/v1 is the canonical path and the unversioned alias still works
 */
final class ApiTokenAuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;

    private int $adminId;
    private int $staffId;
    private array $createdUserIds = [];
    private array $createdKeyIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSetupFlag();
        $this->configureTestingDatabaseEnvironment();

        $this->adminId = $this->seedUser('admin');
        $this->staffId = $this->seedUser('staff');
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if (!empty($this->createdKeyIds)) {
            $db->table('api_keys')->whereIn('id', $this->createdKeyIds)->delete();
        }

        if (!empty($this->createdUserIds)) {
            $db->table('user_roles')->whereIn('user_id', $this->createdUserIds)->delete();
            $db->table('users')->whereIn('id', $this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------
    // Blocker 1 — tokens reach the real resources, not just settings
    // -------------------------------------------------------------------

    public function testTokenReachesAppointmentsOnTheCanonicalVersionedPath(): void
    {
        $token = $this->issueKey($this->adminId);

        $result = $this->withHeaders($this->bearer($token))->get('/api/v1/appointments');

        $result->assertOK();
        $payload = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $payload);
    }

    public function testTokenReachesCalendarDayView(): void
    {
        $token = $this->issueKey($this->adminId);

        $result = $this->withHeaders($this->bearer($token))
            ->get('/api/v1/calendar/day?date=' . date('Y-m-d'));

        $result->assertOK();
    }

    public function testUnversionedAliasStillWorksForTokens(): void
    {
        $token = $this->issueKey($this->adminId);

        $this->withHeaders($this->bearer($token))->get('/api/appointments')->assertOK();
    }

    public function testMissingCredentialsAreRejected(): void
    {
        $result = $this->get('/api/v1/appointments');

        $result->assertStatus(401);
        $this->assertSame('unauthorized', json_decode($result->getJSON(), true)['error']['code'] ?? null);
    }

    // -------------------------------------------------------------------
    // Blocker 2 — keys are per-user, revocable, expirable
    // -------------------------------------------------------------------

    public function testTokenSecretIsNotStoredInPlaintext(): void
    {
        $model = new ApiKeyModel();
        $result = $model->generate($this->adminId, 'storage check');
        $this->createdKeyIds[] = (int) $result['record']['id'];

        $row = $model->find((int) $result['record']['id']);

        $this->assertStringStartsWith('xsk_', $result['plaintext']);
        $this->assertStringNotContainsString($row['key_hash'], $result['plaintext']);
        $this->assertTrue(password_verify(
            substr($result['plaintext'], strrpos($result['plaintext'], '_') + 1),
            $row['key_hash']
        ));
    }

    public function testRevokedKeyIsRejected(): void
    {
        $model = new ApiKeyModel();
        $result = $model->generate($this->adminId, 'to be revoked');
        $keyId = (int) $result['record']['id'];
        $this->createdKeyIds[] = $keyId;

        $this->withHeaders($this->bearer($result['plaintext']))->get('/api/v1/appointments')->assertOK();

        $model->revoke($keyId);

        $this->withHeaders($this->bearer($result['plaintext']))
            ->get('/api/v1/appointments')
            ->assertStatus(401);
    }

    public function testExpiredKeyIsRejected(): void
    {
        $token = $this->issueKey($this->adminId, [
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $this->withHeaders($this->bearer($token))
            ->get('/api/v1/appointments')
            ->assertStatus(401);
    }

    public function testKeyBoundToAnInactiveUserIsRejected(): void
    {
        $token = $this->issueKey($this->adminId);

        \Config\Database::connect('tests')
            ->table('users')
            ->where('id', $this->adminId)
            ->update(['status' => 'inactive']);

        $this->withHeaders($this->bearer($token))
            ->get('/api/v1/appointments')
            ->assertStatus(401);
    }

    public function testMalformedAndUnknownTokensAreRejected(): void
    {
        foreach (['nonsense', 'xsk_short_x', 'xsk_abcdefghijkl_wrongsecret'] as $bad) {
            $this->withHeaders($this->bearer($bad))
                ->get('/api/v1/appointments')
                ->assertStatus(401);
        }
    }

    // -------------------------------------------------------------------
    // Blocker 3 — token requests carry an identity, so RBAC applies
    // -------------------------------------------------------------------

    public function testRoleGatedRouteAllowsAKeyBoundToAnAdmin(): void
    {
        $token = $this->issueKey($this->adminId);

        $this->withHeaders($this->bearer($token))->get('/api/v1/users')->assertOK();
    }

    public function testRoleGatedRouteRejectsAKeyBoundToAStaffUser(): void
    {
        $token = $this->issueKey($this->staffId);

        $result = $this->withHeaders($this->bearer($token))->get('/api/v1/users');

        $result->assertStatus(403);
        $this->assertSame('forbidden', json_decode($result->getJSON(), true)['error']['code'] ?? null);
    }

    public function testTokenIdentityPopulatesTheRequestScopedHolder(): void
    {
        $token = $this->issueKey($this->adminId);

        $this->withHeaders($this->bearer($token))->get('/api/v1/appointments');

        $identity = service('apiIdentity');
        $this->assertTrue($identity->isTokenRequest());
        $this->assertSame($this->adminId, $identity->userId());
        $this->assertContains('admin', $identity->roles());
        $this->assertSame(1, $identity->businessId());
    }

    // -------------------------------------------------------------------
    // Blocker 4 — a session must not mask an invalid token
    // -------------------------------------------------------------------

    public function testInvalidTokenIsRejectedEvenWithAValidSession(): void
    {
        // Sanity check: this session on its own is accepted.
        $this->withSession($this->adminSession())->get('/api/v1/appointments')->assertOK();

        // With a bogus Authorization header the header decides — 401, not 200.
        $this->withSession($this->adminSession())
            ->withHeaders($this->bearer('xsk_abcdefghijkl_bogus'))
            ->get('/api/v1/appointments')
            ->assertStatus(401);
    }

    public function testNonBearerAuthorizationSchemesAreRejected(): void
    {
        // Basic auth was removed along with the hardcoded dev credentials.
        $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('dev:dev')])
            ->get('/api/v1/appointments')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function issueKey(int $userId, array $options = []): string
    {
        $result = (new ApiKeyModel())->generate($userId, 'test key', $options);
        $this->createdKeyIds[] = (int) $result['record']['id'];

        return $result['plaintext'];
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Api Token Admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'roles' => ['admin'],
                'active_role' => 'admin',
            ],
        ];
    }

    private function seedUser(string $role): int
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $payload = [
            'name' => 'Api Token ' . $role,
            'email' => 'api-token-' . $role . '-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => $role,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($db->fieldExists('status', 'users')) {
            $payload['status'] = 'active';
        }

        if ($db->fieldExists('is_active', 'users')) {
            $payload['is_active'] = 1;
        }

        $db->table('users')->insert($payload);
        $userId = (int) $db->insertID();
        $this->createdUserIds[] = $userId;

        // xs_user_roles is the authoritative role membership table.
        $db->table('user_roles')->insert([
            'user_id' => $userId,
            'role' => $role,
            'created_at' => $now,
        ]);

        return $userId;
    }

    private function ensureSetupFlag(): void
    {
        $flagPath = WRITEPATH . 'setup_complete.flag';

        if (!is_file($flagPath)) {
            file_put_contents($flagPath, 'test');
        }
    }

    private function configureTestingDatabaseEnvironment(): void
    {
        $envPath = ROOTPATH . '.env';
        if (!is_file($envPath)) {
            return;
        }

        $values = [];
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $trimmed, 2));
            $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }

        $mapping = [
            'database.tests.hostname' => $values['database.tests.hostname'] ?? $values['database.default.hostname'] ?? null,
            'database.tests.database' => $values['database.tests.database'] ?? null, // never fall back to the app/dev DB
            'database.tests.username' => $values['database.tests.username'] ?? $values['database.default.username'] ?? null,
            'database.tests.password' => $values['database.tests.password'] ?? $values['database.default.password'] ?? null,
            'database.tests.DBDriver' => $values['database.tests.DBDriver'] ?? $values['database.default.DBDriver'] ?? null,
            'database.tests.DBPrefix' => $values['database.tests.DBPrefix'] ?? $values['database.default.DBPrefix'] ?? 'xs_',
            'database.tests.port' => $values['database.tests.port'] ?? $values['database.default.port'] ?? '3306',
        ];

        foreach ($mapping as $key => $value) {
            if ($value === null) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

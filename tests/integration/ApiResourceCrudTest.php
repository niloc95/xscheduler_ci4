<?php

namespace App\Tests\Integration;

use App\Models\ApiKeyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * End-to-end coverage for the Phase A resource CRUD surface added for the
 * developer API portal: customers, categories, business-hours and provider
 * writes. Complements ApiTokenAuthTest (which covers the auth layer itself).
 *
 * Verifies for each resource:
 *   - a Bearer token reaches the endpoint on the canonical /api/v1 path
 *   - writes are gated to the right roles (403 for under-privileged keys)
 *   - the standard {data, meta} / {error} envelope is returned
 */
final class ApiResourceCrudTest extends CIUnitTestCase
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

        // SettingModel keeps a process-static request cache. The business-hours
        // endpoint reads/writes settings, so start and end each test with a
        // clean cache or a primed default leaks into settings-reading tests.
        \App\Models\SettingModel::clearRequestCache();

        $this->adminId = $this->seedUser('admin');
        $this->staffId = $this->seedUser('staff');
    }

    protected function tearDown(): void
    {
        \App\Models\SettingModel::clearRequestCache();

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
    // Categories — full CRUD (admin/provider write, any read)
    // -------------------------------------------------------------------

    public function testCategoryCrudLifecycleOnCanonicalPath(): void
    {
        $token = $this->issueKey($this->adminId);

        // Create
        $created = $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->post('/api/v1/categories', ['name' => 'Consultations', 'color' => '#3B82F6']);
        $created->assertStatus(201);
        $id = json_decode($created->getJSON(), true)['data']['id'] ?? null;
        $this->assertIsInt($id);

        // Read (list + show)
        $this->withHeaders($this->bearer($token))->get('/api/v1/categories')->assertOK();
        $show = $this->withHeaders($this->bearer($token))->get('/api/v1/categories/' . $id);
        $show->assertOK();
        $this->assertSame('Consultations', json_decode($show->getJSON(), true)['data']['name']);

        // Update
        $updated = $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->put('/api/v1/categories/' . $id, ['name' => 'Renamed', 'color' => '#10B981']);
        $updated->assertOK();
        $this->assertSame('Renamed', json_decode($updated->getJSON(), true)['data']['name']);

        // Delete
        $this->withHeaders($this->bearer($token))->delete('/api/v1/categories/' . $id)->assertOK();
    }

    public function testCategoryWriteRejectsStaffKey(): void
    {
        $token = $this->issueKey($this->staffId);

        $result = $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->post('/api/v1/categories', ['name' => 'Nope']);

        $result->assertStatus(403);
        $this->assertSame('forbidden', json_decode($result->getJSON(), true)['error']['code'] ?? null);
    }

    // -------------------------------------------------------------------
    // Customers — full CRUD (admin/provider/staff write)
    // -------------------------------------------------------------------

    public function testCustomerCrudLifecycle(): void
    {
        $token = $this->issueKey($this->adminId);

        $created = $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->post('/api/v1/customers', [
                'firstName' => 'Ada',
                'lastName'  => 'Lovelace',
                'email'     => 'ada-' . uniqid('', true) . '@example.com',
            ]);
        $created->assertStatus(201);
        $id = json_decode($created->getJSON(), true)['data']['id'] ?? null;
        $this->assertIsInt($id);

        $show = $this->withHeaders($this->bearer($token))->get('/api/v1/customers/' . $id);
        $show->assertOK();
        $this->assertSame('Ada', json_decode($show->getJSON(), true)['data']['firstName']);

        $updated = $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->patch('/api/v1/customers/' . $id, ['firstName' => 'Ada B', 'lastName' => 'Lovelace']);
        $updated->assertOK();

        $this->withHeaders($this->bearer($token))->delete('/api/v1/customers/' . $id)->assertOK();
    }

    public function testCustomerCreateRejectsInvalidEmail(): void
    {
        $token = $this->issueKey($this->adminId);

        $result = $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->post('/api/v1/customers', ['firstName' => 'Bad', 'email' => 'not-an-email']);

        $result->assertStatus(422);
        $this->assertSame('VALIDATION_ERROR', json_decode($result->getJSON(), true)['error']['code'] ?? null);
    }

    public function testCustomerCreateAllowedForStaffKey(): void
    {
        $token = $this->issueKey($this->staffId);

        $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->post('/api/v1/customers', [
                'firstName' => 'Grace',
                'email'     => 'grace-' . uniqid('', true) . '@example.com',
            ])
            ->assertStatus(201);
    }

    // -------------------------------------------------------------------
    // Business hours — read any authenticated, write admin only
    // -------------------------------------------------------------------

    public function testBusinessHoursReadableByAnyTokenAndWritableByAdmin(): void
    {
        $adminToken = $this->issueKey($this->adminId);
        $staffToken = $this->issueKey($this->staffId);

        $this->withHeaders($this->bearer($staffToken))->get('/api/v1/business-hours')->assertOK();

        $this->withHeaders($this->bearer($adminToken))
            ->withBodyFormat('json')
            ->put('/api/v1/business-hours', ['workStart' => '08:00', 'workEnd' => '17:00'])
            ->assertOK();

        $this->withHeaders($this->bearer($staffToken))
            ->withBodyFormat('json')
            ->put('/api/v1/business-hours', ['workStart' => '08:00', 'workEnd' => '17:00'])
            ->assertStatus(403);
    }

    public function testBusinessHoursRejectsInvalidWindow(): void
    {
        $token = $this->issueKey($this->adminId);

        $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->put('/api/v1/business-hours', ['workStart' => '17:00', 'workEnd' => '08:00'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------
    // Providers — writes gated to admin/provider
    // -------------------------------------------------------------------

    public function testProviderCreateRejectsStaffKey(): void
    {
        $token = $this->issueKey($this->staffId);

        $this->withHeaders($this->bearer($token))
            ->withBodyFormat('json')
            ->post('/api/v1/providers', ['name' => 'Dr Test', 'email' => 'dr-' . uniqid('', true) . '@example.com'])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------
    // Helpers (shared with ApiTokenAuthTest)
    // -------------------------------------------------------------------

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function issueKey(int $userId, array $options = []): string
    {
        $result = (new ApiKeyModel())->generate($userId, 'crud test key', $options);
        $this->createdKeyIds[] = (int) $result['record']['id'];

        return $result['plaintext'];
    }

    private function seedUser(string $role): int
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $payload = [
            'name' => 'Api Crud ' . $role,
            'email' => 'api-crud-' . $role . '-' . uniqid('', true) . '@example.com',
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
            'database.tests.database' => $values['database.tests.database'] ?? null,
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

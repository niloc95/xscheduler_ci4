<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Controller-level regression coverage for global search endpoints.
 */
final class SearchControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $providerId;
    private int $customerId;
    private int $serviceId;
    private array $appointmentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
        $this->ensureSetupFlag();
        $this->seedFixtureData();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if ($this->appointmentIds !== []) {
            $db->table('appointments')->whereIn('id', $this->appointmentIds)->delete();
        }

        if (isset($this->customerId)) {
            $db->table('customers')->where('id', $this->customerId)->delete();
        }

        if (isset($this->serviceId)) {
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        if (isset($this->adminId)) {
            $db->table('users')->where('id', $this->adminId)->delete();
        }

        parent::tearDown();
    }

    public function testSearchRequiresAuthenticatedJsonSession(): void
    {
        $result = $this->withHeaders(['Accept' => 'application/json'])->get('/search?q=searchterm');

        $result->assertStatus(401);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('Session expired. Please log in again.', $error['message'] ?? null);
        $this->assertSame('unauthenticated', $error['code'] ?? null);
    }

    public function testAuthenticatedSearchAndLegacyDashboardRouteReturnSearchBuckets(): void
    {
        $search = $this->withSession($this->adminSession())
            ->get('/search?q=Searchterm&limit=5');

        $search->assertOK();

        $searchPayload = json_decode($search->getJSON(), true);
        $this->assertTrue((bool) ($searchPayload['success'] ?? false));
        $this->assertSame(1, $searchPayload['counts']['customers'] ?? null);
        $this->assertSame(1, $searchPayload['counts']['appointments'] ?? null);
        $this->assertSame('Harper Searchterm', trim((string) ($searchPayload['appointments'][0]['customer_name'] ?? '')));

        $dashboard = $this->withSession($this->adminSession())
            ->get('/dashboard/search?q=Searchterm&limit=5');

        $dashboard->assertOK();

        $dashboardPayload = json_decode($dashboard->getJSON(), true);
        $this->assertTrue((bool) ($dashboardPayload['success'] ?? false));
        $this->assertSame($searchPayload['counts'] ?? null, $dashboardPayload['counts'] ?? null);
        $this->assertSame(
            $searchPayload['appointments'][0]['service_name'] ?? null,
            $dashboardPayload['appointments'][0]['service_name'] ?? null
        );
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Search Admin',
            'email' => 'search-admin-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->adminId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Searchable Facial',
            'description' => 'Service used to validate search controller responses',
            'category_id' => null,
            'duration_min' => 50,
            'price' => 130.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'Search Provider',
            'email' => 'search-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('customers')->insert([
            'first_name' => 'Harper',
            'last_name' => 'Searchterm',
            'email' => 'harper.searchterm.' . uniqid('', true) . '@example.com',
            'phone' => '+15550004444',
            'hash' => hash('sha256', uniqid('customer_', true)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerId = (int) $db->insertID();

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('appointment_', true)),
            'start_at' => '2026-03-25 13:00:00',
            'end_at' => '2026-03-25 13:50:00',
            'status' => 'confirmed',
            'notes' => 'Searchterm note for controller lookup',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->appointmentIds[] = (int) $db->insertID();
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Search Admin',
                'email' => 'search-admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function ensureSetupFlag(): void
    {
        $flagPath = WRITEPATH . 'setup_complete.flag';
        if (!is_file($flagPath)) {
            @mkdir(dirname($flagPath), 0777, true);
            file_put_contents($flagPath, '1');
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
            'database.tests.database' => $values['database.tests.database'] ?? $values['database.default.database'] ?? null,
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
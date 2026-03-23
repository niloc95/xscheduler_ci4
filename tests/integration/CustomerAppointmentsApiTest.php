<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for public customer appointments API endpoints.
 */
final class CustomerAppointmentsApiTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $providerId;
    private int $inactiveProviderId;
    private int $serviceId;
    private int $inactiveServiceId;
    private int $customerId;
    private string $customerHash;
    private array $appointmentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSetupFlag();
        $this->configureTestingDatabaseEnvironment();
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

        if (isset($this->inactiveServiceId)) {
            $db->table('services')->where('id', $this->inactiveServiceId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        if (isset($this->inactiveProviderId)) {
            $db->table('users')->where('id', $this->inactiveProviderId)->delete();
        }

        parent::tearDown();
    }

    public function testPublicHashHistoryAndAutofillEndpointsHideInternalCustomerId(): void
    {
        $history = $this->get('/api/customers/by-hash/' . $this->customerHash . '/appointments?status=completed,archived&per_page=1');
        $history->assertOK();

        $historyPayload = json_decode($history->getJSON(), true);
        $historyData = $historyPayload['data'] ?? [];

        $this->assertSame($this->customerHash, $historyData['customer_hash'] ?? null);
        $this->assertArrayNotHasKey('customer_id', $historyData);
        $this->assertSame(1, $historyData['pagination']['total'] ?? null);
        $this->assertCount(1, $historyData['data'] ?? []);
        $this->assertSame('completed', $historyData['data'][0]['status'] ?? null);
        $this->assertSame('Hash API Service', $historyData['data'][0]['service_name'] ?? null);
        $this->assertSame('Hash API Provider', $historyData['data'][0]['provider_name'] ?? null);

        $autofill = $this->get('/api/customers/by-hash/' . $this->customerHash . '/autofill');
        $autofill->assertOK();

        $autofillPayload = json_decode($autofill->getJSON(), true);
        $autofillData = $autofillPayload['data'] ?? [];

        $this->assertSame($this->customerHash, $autofillData['customer']['hash'] ?? null);
        $this->assertArrayNotHasKey('id', $autofillData['customer'] ?? []);
        $this->assertSame('Hash', $autofillData['customer']['last_name'] ?? null);
        $this->assertSame($this->providerId, $autofillData['preferences']['favorite_provider_id'] ?? null);
        $this->assertSame($this->serviceId, $autofillData['preferences']['favorite_service_id'] ?? null);
    }

    public function testPublicHashEndpointsReturnStableNotFoundContract(): void
    {
        $result = $this->get('/api/customers/by-hash/missing-public-hash/appointments');
        $result->assertStatus(404);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('NOT_FOUND', $error['code'] ?? null);
        $this->assertSame('Customer not found', $error['message'] ?? null);
        $this->assertSame('missing-public-hash', $error['details']['customer_hash'] ?? null);
    }

    public function testAdminSearchEndpointAcceptsQAliasAndNormalizesStatuses(): void
    {
        $result = $this->get('/api/appointments/search?q=Hash&status=no_show,archived&per_page=5');
        $result->assertOK();

        $payload = json_decode($result->getJSON(), true);
        $data = $payload['data'] ?? [];

        $this->assertSame('Hash', $data['filters']['search'] ?? null);
        $this->assertSame('no-show', $data['filters']['status'] ?? null);
        $this->assertSame(1, $data['pagination']['total'] ?? null);
        $this->assertCount(1, $data['data'] ?? []);
        $this->assertSame('no-show', $data['data'][0]['status'] ?? null);
        $this->assertSame('Casey Hash', $data['data'][0]['customer_name'] ?? null);
        $this->assertSame('Hash API Provider', $data['data'][0]['provider_name'] ?? null);
        $this->assertSame('Hash API Service', $data['data'][0]['service_name'] ?? null);
    }

    public function testFilterOptionsEndpointReturnsOnlyActiveProvidersServicesAndCanonicalStatuses(): void
    {
        $result = $this->get('/api/appointments/filters');
        $result->assertOK();

        $payload = json_decode($result->getJSON(), true);
        $data = $payload['data'] ?? [];

        $providers = $data['providers'] ?? [];
        $services = $data['services'] ?? [];
        $statuses = $data['statuses'] ?? [];

        $providerIds = array_map(static fn(array $provider): int => (int) ($provider['id'] ?? 0), $providers);
        $serviceIds = array_map(static fn(array $service): int => (int) ($service['id'] ?? 0), $services);
        $statusValues = array_map(static fn(array $status): ?string => $status['value'] ?? null, $statuses);

        $this->assertContains($this->providerId, $providerIds);
        $this->assertNotContains($this->inactiveProviderId, $providerIds);
        $this->assertContains($this->serviceId, $serviceIds);
        $this->assertNotContains($this->inactiveServiceId, $serviceIds);
        $this->assertSame(['pending', 'confirmed', 'completed', 'cancelled', 'no-show'], $statusValues);
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Hash API Provider',
            'email' => 'hash-api-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'Inactive Hash API Provider',
            'email' => 'inactive-hash-api-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'inactive',
            'is_active' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->inactiveProviderId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Hash API Service',
            'description' => 'Regression service for public customer appointment endpoints',
            'category_id' => null,
            'duration_min' => 45,
            'price' => 125.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Inactive Hash API Service',
            'description' => 'Inactive regression service for filter options endpoint',
            'category_id' => null,
            'duration_min' => 15,
            'price' => 25.00,
            'active' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->inactiveServiceId = (int) $db->insertID();

        $this->customerHash = hash('sha256', uniqid('customer_', true));
        $db->table('customers')->insert([
            'first_name' => 'Casey',
            'last_name' => 'Hash',
            'email' => 'hash-api-customer-' . uniqid('', true) . '@example.com',
            'phone' => '+15550009999',
            'hash' => $this->customerHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerId = (int) $db->insertID();

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('appointment_', true)),
            'start_at' => '2026-03-10 10:00:00',
            'end_at' => '2026-03-10 10:45:00',
            'status' => 'completed',
            'notes' => 'Completed public hash appointment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->appointmentIds[] = (int) $db->insertID();

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('appointment_', true)),
            'start_at' => '2026-03-20 10:00:00',
            'end_at' => '2026-03-20 10:45:00',
            'status' => 'no-show',
            'notes' => 'No-show public hash appointment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->appointmentIds[] = (int) $db->insertID();

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('appointment_', true)),
            'start_at' => '2026-03-18 10:00:00',
            'end_at' => '2026-03-18 10:45:00',
            'status' => 'cancelled',
            'notes' => 'Cancelled public hash appointment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->appointmentIds[] = (int) $db->insertID();
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
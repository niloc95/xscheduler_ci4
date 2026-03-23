<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for remaining booking-facing V1 provider/service APIs.
 */
final class ProvidersServicesApiV1Test extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $providerId;
    private int $otherProviderId;
    private int $serviceId;
    private int $inactiveServiceId;
    private int $otherProviderServiceId;
    private int $customerId;
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

        $db->table('providers_services')->whereIn('provider_id', [$this->providerId, $this->otherProviderId])->delete();

        if (isset($this->serviceId)) {
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if (isset($this->inactiveServiceId)) {
            $db->table('services')->where('id', $this->inactiveServiceId)->delete();
        }

        if (isset($this->otherProviderServiceId)) {
            $db->table('services')->where('id', $this->otherProviderServiceId)->delete();
        }

        if (isset($this->providerId) || isset($this->otherProviderId)) {
            $db->table('provider_schedules')->whereIn('provider_id', [$this->providerId, $this->otherProviderId])->delete();
            $db->table('users')->whereIn('id', [$this->providerId, $this->otherProviderId])->delete();
        }

        parent::tearDown();
    }

    public function testPublicProviderEndpointsReturnCatalogServicesAndAppointmentsForBookingFlows(): void
    {
        $providers = $this->get('/api/providers?includeColors=true&length=5');
        $providers->assertOK();

        $providersPayload = json_decode($providers->getJSON(), true);
        $providerItems = $providersPayload['data'] ?? [];
        $provider = $this->findItemById($providerItems, $this->providerId);

        $this->assertNotNull($provider);
        $this->assertSame('API Provider One', $provider['name'] ?? null);
        $this->assertSame('#2244AA', $provider['color'] ?? null);

        $services = $this->get('/api/v1/providers/' . $this->providerId . '/services');
        $services->assertOK();

        $servicesPayload = json_decode($services->getJSON(), true);
        $serviceItems = $servicesPayload['data'] ?? [];

        $this->assertCount(1, $serviceItems);
        $this->assertSame($this->serviceId, $serviceItems[0]['id'] ?? null);
        $this->assertSame('API Public Service', $serviceItems[0]['name'] ?? null);
        $this->assertSame(45, $serviceItems[0]['duration'] ?? null);
        $this->assertSame($this->providerId, $servicesPayload['meta']['providerId'] ?? null);
        $this->assertSame(1, $servicesPayload['meta']['total'] ?? null);

        $appointments = $this->get('/api/v1/providers/' . $this->providerId . '/appointments?month=2026-05&status=confirmed&service_id=' . $this->serviceId . '&futureOnly=true&per_page=5');
        $appointments->assertOK();

        $appointmentsPayload = json_decode($appointments->getJSON(), true);
        $appointmentItems = $appointmentsPayload['data'] ?? [];

        $this->assertCount(1, $appointmentItems);
        $this->assertSame('API Customer', $appointmentItems[0]['customerName'] ?? null);
        $this->assertSame('API Public Service', $appointmentItems[0]['serviceName'] ?? null);
        $this->assertSame('confirmed', $appointmentItems[0]['status'] ?? null);
        $this->assertSame($this->providerId, $appointmentsPayload['meta']['providerId'] ?? null);
        $this->assertSame(1, $appointmentsPayload['meta']['pagination']['total'] ?? null);
        $this->assertTrue((bool) ($appointmentsPayload['meta']['filters']['futureOnly'] ?? false));
    }

    public function testAuthenticatedServicesEndpointCanFilterByProviderMapping(): void
    {
        $result = $this->withSession($this->authenticatedSession())
            ->get('/api/v1/services?providerId=' . $this->providerId . '&length=10');

        $result->assertOK();

        $payload = json_decode($result->getJSON(), true);
        $items = $payload['data'] ?? [];

        $serviceIds = array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $items);

        $this->assertCount(2, $items);
        $this->assertContains($this->serviceId, $serviceIds);
        $this->assertContains($this->inactiveServiceId, $serviceIds);
        $this->assertNotContains($this->otherProviderServiceId, $serviceIds);
        $this->assertSame($this->providerId, $payload['meta']['providerId'] ?? null);
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'API Provider One',
            'email' => 'api-provider-one-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'color' => '#2244AA',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'API Provider Two',
            'email' => 'api-provider-two-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'color' => '#AA4422',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->otherProviderId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'API Public Service',
            'description' => 'Active service exposed by provider API',
            'category_id' => null,
            'duration_min' => 45,
            'price' => 80.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'API Inactive Service',
            'description' => 'Inactive service hidden from provider services list',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 50.00,
            'active' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->inactiveServiceId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Other Provider Service',
            'description' => 'Service mapped to another provider for provider filter assertions',
            'category_id' => null,
            'duration_min' => 60,
            'price' => 140.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->otherProviderServiceId = (int) $db->insertID();

        $db->table('providers_services')->insertBatch([
            [
                'provider_id' => $this->providerId,
                'service_id' => $this->serviceId,
                'created_at' => $now,
            ],
            [
                'provider_id' => $this->providerId,
                'service_id' => $this->inactiveServiceId,
                'created_at' => $now,
            ],
            [
                'provider_id' => $this->otherProviderId,
                'service_id' => $this->otherProviderServiceId,
                'created_at' => $now,
            ],
        ]);

        $db->table('customers')->insert([
            'first_name' => 'API',
            'last_name' => 'Customer',
            'email' => 'api-customer-' . uniqid('', true) . '@example.com',
            'phone' => '+15550909090',
            'hash' => hash('sha256', uniqid('api_customer_', true)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerId = (int) $db->insertID();

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('provider_appointment_', true)),
            'start_at' => '2026-05-10 09:00:00',
            'end_at' => '2026-05-10 09:45:00',
            'status' => 'confirmed',
            'notes' => 'Provider appointments API regression fixture',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->appointmentIds[] = (int) $db->insertID();
    }

    private function findItemById(array $items, int $id): ?array
    {
        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function authenticatedSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => 1,
            'user' => [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
        ];
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
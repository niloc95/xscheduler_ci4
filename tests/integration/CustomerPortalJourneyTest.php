<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for the public customer portal routes.
 */
final class CustomerPortalJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $providerId;
    private int $serviceId;
    private int $customerId;
    private string $customerHash;
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

        $db->table('settings')->whereIn('setting_key', ['localization.timezone'])->delete();

        parent::tearDown();
    }

    public function testPortalJsonIndexHistoryUpcomingAndAutofillFollowCustomerHashContract(): void
    {
        $index = $this->withHeaders(['Accept' => 'application/json'])
            ->get('/my-appointments/' . $this->customerHash . '?tab=past&page=1');

        $index->assertOK();

        $indexPayload = json_decode($index->getJSON(), true);

        $this->assertTrue((bool) ($indexPayload['success'] ?? false));
        $this->assertSame('Portal', $indexPayload['customer']['first_name'] ?? null);
        $this->assertArrayNotHasKey('id', $indexPayload['customer'] ?? []);
        $this->assertSame(1, $indexPayload['appointments']['pagination']['total'] ?? null);
        $this->assertCount(1, $indexPayload['appointments']['data'] ?? []);
        $this->assertSame('completed', $indexPayload['appointments']['data'][0]['status'] ?? null);
        $this->assertSame(2, $indexPayload['stats']['total'] ?? null);
        $this->assertCount(1, $indexPayload['upcoming'] ?? []);
        $this->assertSame('confirmed', $indexPayload['upcoming'][0]['status'] ?? null);

        $upcoming = $this->get('/my-appointments/' . $this->customerHash . '/upcoming?limit=1');
        $upcoming->assertOK();

        $upcomingPayload = json_decode($upcoming->getJSON(), true);
        $this->assertTrue((bool) ($upcomingPayload['success'] ?? false));
        $this->assertSame(1, $upcomingPayload['count'] ?? null);
        $this->assertCount(1, $upcomingPayload['data'] ?? []);
        $this->assertSame('confirmed', $upcomingPayload['data'][0]['status'] ?? null);

        $history = $this->get('/my-appointments/' . $this->customerHash . '/history?status=completed&per_page=5');
        $history->assertOK();

        $historyPayload = json_decode($history->getJSON(), true);
        $this->assertTrue((bool) ($historyPayload['success'] ?? false));
        $this->assertSame(1, $historyPayload['pagination']['total'] ?? null);
        $this->assertCount(1, $historyPayload['data'] ?? []);
        $this->assertSame('completed', $historyPayload['data'][0]['status'] ?? null);

        $autofill = $this->get('/my-appointments/' . $this->customerHash . '/autofill');
        $autofill->assertOK();

        $autofillPayload = json_decode($autofill->getJSON(), true);
        $this->assertTrue((bool) ($autofillPayload['success'] ?? false));
        $this->assertSame($this->customerHash, $autofillPayload['customer']['hash'] ?? null);
        $this->assertArrayNotHasKey('id', $autofillPayload['customer'] ?? []);
        $this->assertSame($this->providerId, $autofillPayload['preferences']['favorite_provider_id'] ?? null);
        $this->assertSame($this->serviceId, $autofillPayload['preferences']['favorite_service_id'] ?? null);
    }

    public function testPortalHistoryReturnsStableNotFoundContractForUnknownHash(): void
    {
        $response = $this->get('/my-appointments/missing-customer-hash/history');

        $response->assertStatus(404);

        $payload = json_decode($response->getJSON(), true);
        $this->assertSame('Customer not found', $payload['error'] ?? null);
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('settings')->where('setting_key', 'localization.timezone')->delete();
        $db->table('settings')->insert([
            'setting_key' => 'localization.timezone',
            'setting_value' => 'UTC',
            'setting_type' => 'string',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('users')->insert([
            'name' => 'Portal Provider',
            'email' => 'portal-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Portal Service',
            'description' => 'Regression service for customer portal routes',
            'category_id' => null,
            'duration_min' => 45,
            'price' => 120.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        $this->customerHash = hash('sha256', uniqid('portal_customer_', true));
        $db->table('customers')->insert([
            'first_name' => 'Portal',
            'last_name' => 'Guest',
            'email' => 'portal-customer-' . uniqid('', true) . '@example.com',
            'phone' => '+15550123456',
            'hash' => $this->customerHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerId = (int) $db->insertID();

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('portal_completed_', true)),
            'start_at' => '2026-03-01 09:00:00',
            'end_at' => '2026-03-01 09:45:00',
            'status' => 'completed',
            'notes' => 'Completed portal appointment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->appointmentIds[] = (int) $db->insertID();

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('portal_upcoming_', true)),
            'start_at' => '2026-05-12 14:00:00',
            'end_at' => '2026-05-12 14:45:00',
            'status' => 'confirmed',
            'notes' => 'Upcoming portal appointment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->appointmentIds[] = (int) $db->insertID();
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
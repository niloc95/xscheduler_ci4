<?php

namespace Tests\Unit\Services;

use App\Services\GlobalSearchService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class GlobalSearchServiceTest extends CIUnitTestCase
{
    public function testSearchReturnsCustomerAndAppointmentMatchesWithIsoTimes(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $appointmentIds = [];

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
        $providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Searchable Facial',
            'description' => 'Service used to validate global search matching',
            'category_id' => null,
            'duration_min' => 50,
            'price' => 130.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $serviceId = (int) $db->insertID();

        $db->table('customers')->insert([
            'first_name' => 'Harper',
            'last_name' => 'Searchterm',
            'email' => 'harper.searchterm.' . uniqid('', true) . '@example.com',
            'phone' => '+15550003333',
            'hash' => hash('sha256', uniqid('customer_', true)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        try {
            $db->table('appointments')->insert([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => '2026-03-25 13:00:00',
                'end_at' => '2026-03-25 13:50:00',
                'status' => 'confirmed',
                'notes' => 'Searchterm note for global lookup',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $service = new GlobalSearchService();
            $result = $service->search('Searchterm', 5);

            $this->assertCount(1, $result['customers'] ?? []);
            $this->assertCount(1, $result['appointments'] ?? []);
            $this->assertSame(1, $result['counts']['customers'] ?? null);
            $this->assertSame(1, $result['counts']['appointments'] ?? null);
            $this->assertSame(2, $result['counts']['total'] ?? null);
            $this->assertSame('Harper', $result['customers'][0]['first_name'] ?? null);
            $this->assertSame('Harper Searchterm', $result['appointments'][0]['customer_name'] ?? null);
            $this->assertSame('Searchable Facial', $result['appointments'][0]['service_name'] ?? null);
            $this->assertMatchesRegularExpression('/^2026-03-25T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $result['appointments'][0]['start_at'] ?? '');
            $this->assertMatchesRegularExpression('/^2026-03-25T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $result['appointments'][0]['end_at'] ?? '');
        } finally {
            if ($appointmentIds !== []) {
                $db->table('appointments')->whereIn('id', $appointmentIds)->delete();
            }
            $db->table('customers')->where('id', $customerId ?? 0)->delete();
            $db->table('services')->where('id', $serviceId ?? 0)->delete();
            $db->table('users')->where('id', $providerId ?? 0)->delete();
        }
    }

    public function testSearchReturnsEmptyBucketsForBlankQuery(): void
    {
        $service = new GlobalSearchService();
        $result = $service->search('   ');

        $this->assertSame([], $result['customers'] ?? null);
        $this->assertSame([], $result['appointments'] ?? null);
        $this->assertSame(0, $result['counts']['customers'] ?? null);
        $this->assertSame(0, $result['counts']['appointments'] ?? null);
        $this->assertSame(0, $result['counts']['total'] ?? null);
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
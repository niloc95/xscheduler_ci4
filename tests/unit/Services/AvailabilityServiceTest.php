<?php

namespace Tests\Unit\Services;

use App\Services\AvailabilityService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AvailabilityServiceTest extends CIUnitTestCase
{
    private int $providerId;
    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
        $this->seedFixtureData();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if (isset($this->locationId)) {
            $db->table('location_days')->where('location_id', $this->locationId)->delete();
            $db->table('locations')->where('id', $this->locationId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('provider_schedules')->where('provider_id', $this->providerId)->delete();
            $db->table('blocked_times')->where('provider_id', $this->providerId)->delete();
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        parent::tearDown();
    }

    public function testIsSlotAvailableReturnsBlockedReasonForOverlappingBlockedTime(): void
    {
        $service = new AvailabilityService();

        $result = $service->isSlotAvailable(
            $this->providerId,
            '2026-03-03 10:00:00',
            '2026-03-03 10:30:00',
            'UTC',
            null,
            $this->locationId
        );

        $this->assertFalse((bool) ($result['available'] ?? true));
        $this->assertSame([], $result['conflicts'] ?? null);
        $this->assertSame('Time period is blocked', $result['reason'] ?? null);
    }

    public function testIsSlotAvailableRejectsLocationOnNonOperatingDay(): void
    {
        $service = new AvailabilityService();

        $result = $service->isSlotAvailable(
            $this->providerId,
            '2026-03-04 09:00:00',
            '2026-03-04 09:30:00',
            'UTC',
            null,
            $this->locationId
        );

        $this->assertFalse((bool) ($result['available'] ?? true));
        $this->assertSame([], $result['conflicts'] ?? null);
        $this->assertSame('Location does not operate on this day', $result['reason'] ?? null);
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Availability Unit Provider',
            'email' => 'availability-unit-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('locations')->insert([
            'provider_id' => $this->providerId,
            'name' => 'Availability Unit Location',
            'address' => '1 Unit Test Way',
            'contact_number' => '0000000000',
            'is_primary' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->locationId = (int) $db->insertID();

        $db->table('location_days')->insert([
            'location_id' => $this->locationId,
            'day_of_week' => 2,
        ]);

        $db->table('provider_schedules')->insert([
            'provider_id' => $this->providerId,
            'location_id' => $this->locationId,
            'day_of_week' => 'tuesday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => null,
            'break_end' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('blocked_times')->insert([
            'provider_id' => $this->providerId,
            'start_at' => '2026-03-03 10:00:00',
            'end_at' => '2026-03-03 10:30:00',
            'reason' => 'Blocked unit-test period',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
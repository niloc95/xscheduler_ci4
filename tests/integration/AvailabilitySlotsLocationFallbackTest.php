<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression test for availability slot endpoint location fallback.
 *
 * Ensures API no longer returns 422 when provider has active locations and
 * location_id is omitted from query string.
 */
class AvailabilitySlotsLocationFallbackTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $providerId;
    private int $serviceId;
    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect('tests');

        $now = date('Y-m-d H:i:s');

        $providerEmail = 'availability-fallback-' . uniqid() . '@test.local';
        $db->table('users')->insert([
            'name' => 'Availability Fallback Provider',
            'email' => $providerEmail,
            'password_hash' => password_hash('test1234', PASSWORD_DEFAULT),
            'role' => 'provider',
            'is_active' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Availability Fallback Service',
            'description' => 'Regression service for location fallback',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 100.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        $db->table('locations')->insert([
            'provider_id' => $this->providerId,
            'name' => 'Primary Regression Location',
            'address' => '1 Test Street',
            'contact_number' => '0000000000',
            'is_primary' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->locationId = (int) $db->insertID();

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

        // Add a UTC blocked period to validate UTC overlap filtering.
        $db->table('blocked_times')->insert([
            'provider_id' => $this->providerId,
            'start_at' => '2026-03-03 10:00:00',
            'end_at' => '2026-03-03 10:30:00',
            'reason' => 'UTC overlap regression check',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function testSlotsEndpointSucceedsWithoutLocationIdWhenProviderHasActiveLocations(): void
    {
        $date = '2026-03-03'; // Tuesday

        $result = $this->get(sprintf(
            '/api/availability/slots?provider_id=%d&service_id=%d&date=%s&timezone=UTC',
            $this->providerId,
            $this->serviceId,
            $date
        ));

        $result->assertOK();

        $payload = json_decode($result->getJSON(), true);

        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertEquals($this->providerId, (int) ($payload['data']['provider_id'] ?? 0));
        $this->assertEquals($this->serviceId, (int) ($payload['data']['service_id'] ?? 0));
        $this->assertEquals($this->locationId, (int) ($payload['data']['location_id'] ?? 0));
        $this->assertArrayHasKey('slots', $payload['data']);
        $this->assertIsArray($payload['data']['slots']);

        $slots = $payload['data']['slots'];
        $this->assertNotEmpty($slots, 'Expected at least one available slot in response');

        $starts = array_column($slots, 'start');
        $this->assertNotContains('10:00', $starts, 'Blocked UTC period should remove 10:00 slot');

        foreach ($slots as $slot) {
            $this->assertArrayHasKey('start', $slot);
            $this->assertArrayHasKey('end', $slot);
            $this->assertArrayHasKey('startTime', $slot);
            $this->assertArrayHasKey('endTime', $slot);

            $this->assertMatchesRegularExpression('/(\\+00:00|Z)$/', (string) $slot['startTime']);
            $this->assertMatchesRegularExpression('/(\\+00:00|Z)$/', (string) $slot['endTime']);

            $this->assertLessThan(
                strtotime((string) $slot['endTime']),
                strtotime((string) $slot['startTime']),
                'Slot start time must be earlier than slot end time'
            );
        }
    }
}

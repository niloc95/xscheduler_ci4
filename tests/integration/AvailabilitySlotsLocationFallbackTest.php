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

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if (isset($this->providerId)) {
            $db->table('blocked_times')->where('provider_id', $this->providerId)->delete();
            $db->table('provider_schedules')->where('provider_id', $this->providerId)->delete();
            $db->table('business_hours')->where('provider_id', $this->providerId)->delete();
        }

        if (isset($this->locationId)) {
            $db->table('location_days')->where('location_id', $this->locationId)->delete();
            $db->table('locations')->where('id', $this->locationId)->delete();
        }

        if (isset($this->serviceId)) {
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        parent::tearDown();
    }

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

        $db->table('business_hours')->insert([
            'provider_id' => $this->providerId,
            'weekday' => 2,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'breaks_json' => null,
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

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsArray($payload['data']);
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

    public function testCheckEndpointReturnsBlockedReasonWithoutExplicitLocationId(): void
    {
        $result = $this->withBodyFormat('json')->post('/api/availability/check', [
            'provider_id' => $this->providerId,
            'start_time' => '2026-03-03 10:00:00',
            'end_time' => '2026-03-03 10:30:00',
            'timezone' => 'UTC',
        ]);

        $result->assertOK();

        $payload = json_decode($result->getJSON(), true);
        $data = $payload['data'] ?? [];

        $this->assertFalse((bool) ($data['available'] ?? true));
        $this->assertSame([], $data['conflicts'] ?? null);
        $this->assertSame('Time period is blocked', $data['reason'] ?? null);
        $this->assertMatchesRegularExpression('/^2026-.*T.*(\+00:00|Z)$/', $data['checked_at'] ?? '');
    }
}

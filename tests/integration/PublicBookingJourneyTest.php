<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Controller-level regression coverage for the public booking lifecycle.
 */
final class PublicBookingJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $providerId;
    private int $serviceId;
    private array $appointmentIds = [];
    private array $customerIds = [];

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
            $db->table('notification_queue')->whereIn('appointment_id', $this->appointmentIds)->delete();
            if ($db->tableExists('notification_delivery_logs')) {
                $db->table('notification_delivery_logs')->whereIn('appointment_id', $this->appointmentIds)->delete();
            }
            $db->table('appointments')->whereIn('id', $this->appointmentIds)->delete();
        }

        if ($this->customerIds !== []) {
            $db->table('customers')->whereIn('id', $this->customerIds)->delete();
        }

        if (isset($this->serviceId)) {
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('business_hours')->where('provider_id', $this->providerId)->delete();
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        parent::tearDown();
    }

    public function testPublicBookingCanBeCreatedLookedUpAndRescheduledWithTokenRotation(): void
    {
        $this->primeCsrfCookie();

        $email = 'public.booking.' . uniqid('', true) . '@example.com';
        $initialStart = '2026-05-18T11:00:00+00:00';
        $rescheduledStart = '2026-05-19T14:00:00+00:00';

        $create = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/booking', [
                'provider_id' => $this->providerId,
                'service_id' => $this->serviceId,
                'slot_start' => $initialStart,
                'first_name' => 'Pat',
                'last_name' => 'Guest',
                'email' => $email,
                'phone' => '+15551238888',
                'notes' => 'Initial public booking request',
            ]);

        $create->assertStatus(201);

        $createPayload = json_decode($create->getJSON(), true);
        $created = $createPayload['data'] ?? [];
        $initialToken = (string) ($created['reference'] ?? '');

        $this->assertNotSame('', $initialToken);
        $this->assertSame($this->providerId, (int) ($created['provider_id'] ?? 0));
        $this->assertSame($this->serviceId, (int) ($created['service_id'] ?? 0));
        $this->assertSame($email, $created['customer']['email'] ?? null);
        $this->assertSame('Pat', $created['customer']['first_name'] ?? null);
        $this->assertSame($initialStart, $created['start'] ?? null);
        $this->assertSame('pending', $created['status'] ?? null);

        $db = \Config\Database::connect('tests');
        $appointment = $db->table('appointments')->where('public_token', $initialToken)->get()->getRowArray();
        $this->assertNotNull($appointment);

        $appointmentId = (int) ($appointment['id'] ?? 0);
        $customerId = (int) ($appointment['customer_id'] ?? 0);
        $this->assertGreaterThan(0, $appointmentId);
        $this->assertGreaterThan(0, $customerId);
        $this->appointmentIds[] = $appointmentId;
        $this->customerIds[] = $customerId;

        $lookup = $this->get('/booking/' . $initialToken . '?email=' . rawurlencode($email));
        $lookup->assertOK();

        $lookupPayload = json_decode($lookup->getJSON(), true);
        $lookupData = $lookupPayload['data'] ?? [];

        $this->assertSame($initialToken, $lookupData['reference'] ?? null);
        $this->assertSame($initialStart, $lookupData['start'] ?? null);
        $this->assertSame('Pat', $lookupData['customer']['first_name'] ?? null);
        $this->assertSame('Guest', $lookupData['customer']['last_name'] ?? null);
        $this->assertTrue((bool) ($lookupData['can_reschedule'] ?? false));

        $forbiddenLookup = $this->get('/booking/' . $initialToken . '?email=' . rawurlencode('wrong@example.com'));
        $forbiddenLookup->assertStatus(403);

        $forbiddenPayload = json_decode($forbiddenLookup->getJSON(), true);
        $this->assertSame('Contact verification failed.', $forbiddenPayload['error'] ?? null);

        $this->primeCsrfCookie();

        $reschedule = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->patch('/booking/' . $initialToken, [
                'email' => $email,
                'slot_start' => $rescheduledStart,
                'notes' => 'Rescheduled by customer',
            ]);

        $reschedule->assertOK();

        $reschedulePayload = json_decode($reschedule->getJSON(), true);
        $updated = $reschedulePayload['data'] ?? [];
        $newToken = (string) ($updated['reference'] ?? '');

        $this->assertNotSame('', $newToken);
        $this->assertNotSame($initialToken, $newToken);
        $this->assertSame($rescheduledStart, $updated['start'] ?? null);
        $this->assertSame('Rescheduled by customer', $updated['notes'] ?? null);
        $this->assertSame($email, $updated['customer']['email'] ?? null);

        $oldTokenLookup = $this->get('/booking/' . $initialToken . '?email=' . rawurlencode($email));
        $oldTokenLookup->assertStatus(404);

        $oldTokenPayload = json_decode($oldTokenLookup->getJSON(), true);
        $this->assertSame('We could not find a booking for that reference.', $oldTokenPayload['error'] ?? null);

        $newTokenLookup = $this->get('/booking/' . $newToken . '?email=' . rawurlencode($email));
        $newTokenLookup->assertOK();

        $newTokenPayload = json_decode($newTokenLookup->getJSON(), true);
        $newLookup = $newTokenPayload['data'] ?? [];

        $this->assertSame($newToken, $newLookup['reference'] ?? null);
        $this->assertSame($rescheduledStart, $newLookup['start'] ?? null);
        $this->assertSame('Rescheduled by customer', $newLookup['notes'] ?? null);

        $persisted = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertSame($newToken, $persisted['public_token'] ?? null);
        $this->assertSame('2026-05-19 14:00:00', $persisted['start_at'] ?? null);
        $this->assertSame('2026-05-19 14:30:00', $persisted['end_at'] ?? null);
        $this->assertSame('Rescheduled by customer', $persisted['notes'] ?? null);
    }

    public function testPublicBookingRescheduleRejectsAppointmentsInsidePolicyWindow(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $token = 'policy-window-' . bin2hex(random_bytes(6));

        $this->seedSetting($db, 'business.reschedule', '12h', 'string');

        $db->table('customers')->insert([
            'first_name' => 'Policy',
            'last_name' => 'Window',
            'email' => 'policy.window.' . uniqid('', true) . '@example.com',
            'phone' => '+15551239999',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();
        $this->customerIds[] = $customerId;

        $startAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+6 hours')
            ->format('Y-m-d H:i:s');
        $endAt = (new \DateTimeImmutable($startAt, new \DateTimeZone('UTC')))
            ->modify('+30 minutes')
            ->format('Y-m-d H:i:s');

        $db->table('appointments')->insert([
            'customer_id' => $customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'confirmed',
            'public_token' => $token,
            'notes' => 'Inside reschedule policy window',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $appointmentId = (int) $db->insertID();
        $this->appointmentIds[] = $appointmentId;

        $lookup = $this->get('/booking/' . $token . '?email=' . rawurlencode($db->table('customers')->where('id', $customerId)->get()->getRowArray()['email'] ?? ''));
        $lookup->assertOK();
        $lookupPayload = json_decode($lookup->getJSON(), true);
        $this->assertFalse((bool) (($lookupPayload['data']['can_reschedule'] ?? true)));

        $this->primeCsrfCookie();

        $response = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->patch('/booking/' . $token, [
                'email' => $db->table('customers')->where('id', $customerId)->get()->getRowArray()['email'] ?? null,
                'slot_start' => '2026-05-19T14:00:00+00:00',
                'notes' => 'Attempted late reschedule',
            ]);

        $response->assertStatus(403);

        $payload = json_decode($response->getJSON(), true);
        $this->assertSame('This appointment is too close to reschedule online.', $payload['error'] ?? null);
        $this->assertSame([], $payload['details'] ?? []);

        $persisted = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertSame($token, $persisted['public_token'] ?? null);
        $this->assertSame($startAt, $persisted['start_at'] ?? null);
        $this->assertSame('Inside reschedule policy window', $persisted['notes'] ?? null);
    }

    private function csrfJsonHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN' => $this->csrfToken(),
        ];
    }

    private function csrfCookieName(): string
    {
        return config('Security')->cookieName;
    }

    private function csrfToken(): string
    {
        return csrf_hash();
    }

    private function primeCsrfCookie(): void
    {
        $_COOKIE[$this->csrfCookieName()] = $this->csrfToken();
        $_SERVER['HTTP_COOKIE'] = $this->csrfCookieName() . '=' . $this->csrfToken();
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Public Booking Provider',
            'email' => 'public-booking-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Public Booking Service',
            'description' => 'Regression service for the public booking journey',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 95.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        $db->table('business_hours')->insert([
            'provider_id' => $this->providerId,
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'breaks_json' => null,
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

        $this->seedSetting($db, 'localization.timezone', 'UTC', 'string');
        $this->seedSetting($db, 'booking.email_display', '1', 'boolean');
        $this->seedSetting($db, 'booking.email_required', '1', 'boolean');
        $this->seedSetting($db, 'booking.first_names_display', '1', 'boolean');
        $this->seedSetting($db, 'booking.first_names_required', '0', 'boolean');
        $this->seedSetting($db, 'booking.surname_display', '1', 'boolean');
        $this->seedSetting($db, 'booking.surname_required', '0', 'boolean');
        $this->seedSetting($db, 'booking.phone_display', '1', 'boolean');
        $this->seedSetting($db, 'booking.phone_required', '0', 'boolean');
        $this->seedSetting($db, 'booking.address_display', '0', 'boolean');
        $this->seedSetting($db, 'booking.address_required', '0', 'boolean');
        $this->seedSetting($db, 'booking.notes_display', '1', 'boolean');
        $this->seedSetting($db, 'booking.notes_required', '0', 'boolean');
        $this->seedSetting($db, 'business.reschedule', '24h', 'string');
    }

    private function seedSetting($db, string $key, string $value, string $type): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $db->table('settings')->where('setting_key', $key)->get()->getRowArray();

        $payload = [
            'setting_value' => $value,
            'setting_type' => $type,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            $db->table('settings')->where('setting_key', $key)->update($payload);
            return;
        }

        $db->table('settings')->insert($payload + [
            'setting_key' => $key,
            'created_at' => $now,
        ]);
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
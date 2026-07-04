<?php

namespace App\Tests\Integration;

use App\Services\BookingSettingsService;
use App\Services\PhoneNumberService;
use App\Services\Settings\SettingsApiService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Security regression tests for the public booking flow and booking settings.
 *
 * Covers:
 *  - Provider-service mismatch rejection (hidden-field tampering)
 *  - Strict E.164 phone validation
 *  - Honeypot detection
 *  - Booking-settings whitelist enforcement
 *  - Custom-field metadata sanitization
 *  - Canonical error-envelope shape from BookingController
 */
final class PublicBookingSecurityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;

    private int $providerId;
    private int $serviceId;
    private int $otherServiceId;
    private int $locationId;
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

        if (isset($this->otherServiceId)) {
            $db->table('services')->where('id', $this->otherServiceId)->delete();
        }

        if (isset($this->serviceId)) {
            $db->table('providers_services')->where('service_id', $this->serviceId)->delete();
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if (isset($this->providerId)) {
            if (isset($this->locationId)) {
                $db->table('location_days')->where('location_id', $this->locationId)->delete();
                $db->table('locations')->where('id', $this->locationId)->delete();
            }
            $db->table('business_hours')->where('provider_id', $this->providerId)->delete();
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Provider-service mismatch — hidden-field tampering
    // -------------------------------------------------------------------------

    public function testBookingRejectedWhenProviderDoesNotOfferService(): void
    {
        $this->primeCsrfCookie();

        $email = 'tamper-svc-' . uniqid('', true) . '@example.com';

        $response = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/booking', [
                'provider_id' => $this->providerId,
                'location_id' => $this->locationId,
                // Deliberately send a service that is NOT assigned to this provider.
                'service_id'  => $this->otherServiceId,
                'slot_start'  => '2026-05-20T09:00:00+00:00',
                'first_name'  => 'Tamper',
                'last_name'   => 'User',
                'email'       => $email,
            ]);

        $response->assertStatus(422);
        $payload = json_decode($response->getJSON(), true);
        // Must use canonical error envelope.
        $this->assertArrayHasKey('error', $payload);
        $this->assertArrayHasKey('message', $payload['error']);
        $this->assertStringContainsStringIgnoringCase('provider', (string) $payload['error']['message']);
    }

    // -------------------------------------------------------------------------
    // Honeypot detection
    // -------------------------------------------------------------------------

    public function testBookingRejectedWhenHoneypotFieldIsPopulated(): void
    {
        $this->primeCsrfCookie();

        $response = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/booking', [
                'provider_id' => $this->providerId,
                'location_id' => $this->locationId,
                'service_id'  => $this->serviceId,
                'slot_start'  => '2026-05-20T10:00:00+00:00',
                'first_name'  => 'Bot',
                'last_name'   => 'Spammer',
                'email'       => 'bot@example.com',
                // Bot fills the honeypot field.
                'website'     => 'http://spam.example.com',
            ]);

        // Must be 4xx — the fake success message is not disclosed.
        $this->assertGreaterThanOrEqual(400, $response->response()->getStatusCode());
        $this->assertLessThan(500, $response->response()->getStatusCode());
    }

    public function testLegitimateBookingWithoutHoneypotFieldSucceeds(): void
    {
        $this->primeCsrfCookie();

        $email = 'legit-hp-' . uniqid('', true) . '@example.com';

        $response = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/booking', [
                'provider_id' => $this->providerId,
                'location_id' => $this->locationId,
                'service_id'  => $this->serviceId,
                'slot_start'  => '2026-05-20T11:00:00+00:00',
                'first_name'  => 'Legit',
                'last_name'   => 'User',
                'email'       => $email,
                // website field absent — legitimate client.
            ]);

        $response->assertStatus(201);
        $payload = json_decode($response->getJSON(), true);
        $this->assertArrayHasKey('data', $payload);

        if (isset($payload['data']['appointment']['id'])) {
            $this->appointmentIds[] = (int) $payload['data']['appointment']['id'];
        }
    }

    public function testBookingRejectsInvalidPhoneWhenProvided(): void
    {
        $this->primeCsrfCookie();

        $response = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/booking', [
                'provider_id' => $this->providerId,
                'location_id' => $this->locationId,
                'service_id'  => $this->serviceId,
                'slot_start'  => '2026-05-20T11:30:00+00:00',
                'first_name'  => 'Phone',
                'last_name'   => 'Invalid',
                'email'       => 'phone-invalid-' . uniqid('', true) . '@example.com',
                'phone'       => '08252922',
                'phone_country_code' => '+27',
            ]);

        $response->assertStatus(422);
        $payload = json_decode($response->getJSON(), true);
        $this->assertSame('invalid', $payload['error']['details']['phone'] ?? null);
        $this->assertStringContainsStringIgnoringCase('E.164', (string) ($payload['error']['message'] ?? ''));
    }

    // -------------------------------------------------------------------------
    // Canonical error envelope shape
    // -------------------------------------------------------------------------

    public function testErrorResponseUsesCanonicalEnvelope(): void
    {
        $this->primeCsrfCookie();

        // Missing required fields triggers a validation error.
        $response = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/booking', []);

        $this->assertGreaterThanOrEqual(400, $response->response()->getStatusCode());

        $payload = json_decode($response->getJSON(), true);
        $this->assertArrayHasKey('error', $payload);
        $this->assertIsArray($payload['error'], 'error value must be an object (canonical envelope)');
        $this->assertArrayHasKey('message', $payload['error']);
    }

    public function testErrorResponseIncludesCsrfRefresh(): void
    {
        $this->primeCsrfCookie();

        $response = $this->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/booking', []);

        $payload = json_decode($response->getJSON(), true);
        $this->assertArrayHasKey('csrf', $payload);
        $this->assertArrayHasKey('name', $payload['csrf']);
        $this->assertArrayHasKey('value', $payload['csrf']);
    }

    // -------------------------------------------------------------------------
    // PhoneNumberService — strict E.164 validation
    // -------------------------------------------------------------------------

    public function testPhoneNumberServiceRejectsObviouslyInvalidNumbers(): void
    {
        $svc = new PhoneNumberService();

        // All zeros → should return null after synthesizing a sub-7-digit string.
        $this->assertNull($svc->normalize('0000000000'));

        // Too short after stripping country code (< 10 digits).
        $this->assertNull($svc->normalize('+123'));

        // Empty string.
        $this->assertNull($svc->normalize(''));

        // More than 15 digits — must be truncated or rejected.
        // normalize() caps at 15 so the result must be max 16 chars ('+' + 15 digits).
        $result = $svc->normalize('+12345678901234567890');
        if ($result !== null) {
            $this->assertLessThanOrEqual(16, strlen($result));
        }
    }

    public function testPhoneNumberServiceAcceptsValidE164(): void
    {
        $svc = new PhoneNumberService();

        $this->assertSame('+15551234567', $svc->normalize('+15551234567'));
        $this->assertSame('+27831234567', $svc->normalize('+27831234567'));
    }

    public function testPhoneNumberServiceNormalizesLocalNumber(): void
    {
        $svc = new PhoneNumberService();

        // Local 10-digit number with country code hint.
        $result = $svc->normalize('0831234567', '+27');
        $this->assertNotNull($result);
        $this->assertStringStartsWith('+', (string) $result);
        // Digits-only portion must be 7–15.
        $digits = substr((string) $result, 1);
        $this->assertGreaterThanOrEqual(7, strlen($digits));
        $this->assertLessThanOrEqual(15, strlen($digits));
    }

    // -------------------------------------------------------------------------
    // Booking-settings whitelist
    // -------------------------------------------------------------------------

    public function testSettingsApiRejectsUnknownBookingKeys(): void
    {
        $svc = new SettingsApiService();

        $count = $svc->updateSettings([
            // A known, allowed key — must be processed.
            'booking.email_required' => '1',
            // Unknown keys — must be silently skipped.
            'booking.injected_key'   => 'evil_value',
            'booking.custom_field_99_title' => 'overflow',
        ], null);

        // Only the one valid key should have been updated.
        $this->assertSame(1, $count);
    }

    public function testSettingsApiAllowsAllKnownBookingKeys(): void
    {
        $svc = new SettingsApiService();

        $count = $svc->updateSettings([
            'booking.first_names_display'  => '1',
            'booking.email_required'       => '1',
            'booking.custom_field_1_title' => 'Preferred Name',
            'booking.custom_field_1_type'  => 'text',
            'booking.custom_field_1_enabled' => '1',
            'booking.custom_field_1_required' => '0',
        ], null);

        $this->assertSame(6, $count);
    }

    // -------------------------------------------------------------------------
    // Custom-field metadata sanitization
    // -------------------------------------------------------------------------

    public function testBookingSettingsServiceStripsHtmlFromCustomFieldTitle(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        // Store an XSS-shaped title in xs_settings.
        $key = 'booking.custom_field_3_enabled';
        $keyTitle = 'booking.custom_field_3_title';
        $keyType  = 'booking.custom_field_3_type';
        $keyReq   = 'booking.custom_field_3_required';

        foreach ([$key, $keyTitle, $keyType, $keyReq] as $k) {
            $db->table('settings')->where('setting_key', $k)->delete();
        }

        $db->table('settings')->insert(['setting_key' => $key,      'setting_value' => '1',                                  'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('settings')->insert(['setting_key' => $keyTitle, 'setting_value' => '<script>alert(1)</script>My Field', 'setting_type' => 'string',  'created_at' => $now, 'updated_at' => $now]);
        $db->table('settings')->insert(['setting_key' => $keyType,  'setting_value' => 'text',                               'setting_type' => 'string',  'created_at' => $now, 'updated_at' => $now]);
        $db->table('settings')->insert(['setting_key' => $keyReq,   'setting_value' => '0',                                  'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now]);

        $svc = new BookingSettingsService();
        $config = $svc->getCustomFieldConfiguration();

        $field = $config['custom_field_3'] ?? null;
        $this->assertNotNull($field, 'Custom field 3 must be returned when enabled');

        $title = (string) ($field['title'] ?? '');
        $this->assertStringNotContainsString('<script>', $title);
        $this->assertStringNotContainsString('</script>', $title);
        // Text content of the script body will remain — it is escaped by the view layer.
        // The important invariant is that no HTML tags survive.
        $this->assertStringContainsString('My Field', $title);

        // Clean up.
        foreach ([$key, $keyTitle, $keyType, $keyReq] as $k) {
            $db->table('settings')->where('setting_key', $k)->delete();
        }
    }

    public function testBookingSettingsServiceFallsBackToTextForUnsupportedType(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $key  = 'booking.custom_field_4_enabled';
        $keyType = 'booking.custom_field_4_type';

        foreach ([$key, $keyType] as $k) {
            $db->table('settings')->where('setting_key', $k)->delete();
        }

        $db->table('settings')->insert(['setting_key' => $key,     'setting_value' => '1',         'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('settings')->insert(['setting_key' => $keyType, 'setting_value' => 'date_range', 'setting_type' => 'string',  'created_at' => $now, 'updated_at' => $now]);

        $svc = new BookingSettingsService();
        $config = $svc->getCustomFieldConfiguration();

        $this->assertSame('text', $config['custom_field_4']['type'] ?? 'NOT_FOUND');

        foreach ([$key, $keyType] as $k) {
            $db->table('settings')->where('setting_key', $k)->delete();
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function csrfJsonHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN'     => $this->csrfToken(),
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
            'database.tests.database' => $values['database.tests.database'] ?? null, // never fall back to the app/dev DB
            'database.tests.username' => $values['database.tests.username'] ?? $values['database.default.username'] ?? null,
            'database.tests.password' => $values['database.tests.password'] ?? $values['database.default.password'] ?? null,
            'database.tests.DBDriver' => $values['database.tests.DBDriver'] ?? $values['database.default.DBDriver'] ?? null,
            'database.tests.DBPrefix' => $values['database.tests.DBPrefix'] ?? $values['database.default.DBPrefix'] ?? 'xs_',
            'database.tests.port'     => $values['database.tests.port']     ?? $values['database.default.port']     ?? '3306',
        ];

        foreach ($mapping as $key => $value) {
            if ($value === null) {
                continue;
            }
            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function seedFixtureData(): void
    {
        $db  = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        // Provider
        $db->table('users')->insert([
            'name'          => 'Security Test Provider',
            'email'         => 'sec-test-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role'          => 'provider',
            'status'        => 'active',
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        // Location
        $db->table('locations')->insert([
            'provider_id'    => $this->providerId,
            'name'           => 'Security Test Location',
            'address'        => '1 Security Test Street',
            'contact_number' => '+10000000000',
            'is_primary'     => 1,
            'is_active'      => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $this->locationId = (int) $db->insertID();

        // Service assigned to this provider
        $db->table('services')->insert([
            'name'        => 'Security Test Service',
            'duration_min'=> 30,
            'price'       => 0,
            'active'      => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        // Link service → provider
        $db->table('providers_services')->insert([
            'provider_id' => $this->providerId,
            'service_id'  => $this->serviceId,
        ]);

        // A second service NOT assigned to this provider — used for tamper tests.
        $db->table('services')->insert([
            'name'         => 'Other Service (Not Offered)',
            'duration_min' => 30,
            'price'        => 0,
            'active'       => 1,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
        $this->otherServiceId = (int) $db->insertID();
        // Intentionally NOT linked to this provider.

        // Business hours — Tuesday (weekday 2) and Wednesday (weekday 3) for test slot coverage
        $db->table('business_hours')->insert([
            'provider_id' => $this->providerId,
            'weekday'     => 2,
            'start_time'  => '09:00:00',
            'end_time'    => '17:00:00',
            'breaks_json' => null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $db->table('business_hours')->insert([
            'provider_id' => $this->providerId,
            'weekday'     => 3,
            'start_time'  => '09:00:00',
            'end_time'    => '17:00:00',
            'breaks_json' => null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // Location operating days: Tuesday (2) and Wednesday (3)
        $db->table('location_days')->insert(['location_id' => $this->locationId, 'day_of_week' => 2]);
        $db->table('location_days')->insert(['location_id' => $this->locationId, 'day_of_week' => 3]);

        $this->seedSetting($db, 'localization.timezone',           'UTC',  'string');
        $this->seedSetting($db, 'business.work_start',             '09:00','string');
        $this->seedSetting($db, 'business.work_end',               '17:00','string');
        $this->seedSetting($db, 'booking.email_display',           '1',    'boolean');
        $this->seedSetting($db, 'booking.email_required',          '1',    'boolean');
        $this->seedSetting($db, 'booking.first_names_display',     '1',    'boolean');
        $this->seedSetting($db, 'booking.first_names_required',    '0',    'boolean');
        $this->seedSetting($db, 'booking.surname_display',         '1',    'boolean');
        $this->seedSetting($db, 'booking.surname_required',        '0',    'boolean');
        $this->seedSetting($db, 'booking.phone_display',           '1',    'boolean');
        $this->seedSetting($db, 'booking.phone_required',          '0',    'boolean');
        $this->seedSetting($db, 'booking.address_display',         '0',    'boolean');
        $this->seedSetting($db, 'booking.address_required',        '0',    'boolean');
        $this->seedSetting($db, 'booking.notes_display',           '1',    'boolean');
        $this->seedSetting($db, 'booking.notes_required',          '0',    'boolean');
        $this->seedSetting($db, 'business.reschedule',             '24h',  'string');
        $this->seedSetting($db, 'business.cancel',                 '24h',  'string');
    }

    private function seedSetting($db, string $key, string $value, string $type): void
    {
        $now      = date('Y-m-d H:i:s');
        $existing = $db->table('settings')->where('setting_key', $key)->get()->getRowArray();

        $payload = [
            'setting_value' => $value,
            'setting_type'  => $type,
            'updated_at'    => $now,
        ];

        if ($existing !== null) {
            $db->table('settings')->where('setting_key', $key)->update($payload);
            return;
        }

        $db->table('settings')->insert($payload + [
            'setting_key' => $key,
            'created_at'  => $now,
        ]);
    }
}

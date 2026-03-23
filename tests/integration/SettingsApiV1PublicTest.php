<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for public V1 settings endpoints.
 */
final class SettingsApiV1PublicTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSetupFlag();
        $this->configureTestingDatabaseEnvironment();
    }

    public function testLocalizationEndpointReturnsCompatibilityKeysWithoutAuthentication(): void
    {
        $db = \Config\Database::connect('tests');
        $keys = [
            'localization.timezone',
            'localization.time_format',
            'localization.first_day',
            'localization.currency',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $now = date('Y-m-d H:i:s');
            $db->table('settings')->insertBatch([
                [
                    'setting_key' => 'localization.timezone',
                    'setting_value' => 'Europe/Amsterdam',
                    'setting_type' => 'string',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'setting_key' => 'localization.time_format',
                    'setting_value' => '12h',
                    'setting_type' => 'string',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'setting_key' => 'localization.first_day',
                    'setting_value' => 'monday',
                    'setting_type' => 'string',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'setting_key' => 'localization.currency',
                    'setting_value' => 'EUR',
                    'setting_type' => 'string',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $result = $this->get('/api/v1/settings/localization');

            $result->assertOK();

            $payload = json_decode($result->getJSON(), true);
            $data = $payload['data'] ?? [];

            $this->assertSame('Europe/Amsterdam', $data['timezone'] ?? null);
            $this->assertSame('12h', $data['timeFormat'] ?? null);
            $this->assertTrue((bool) ($data['is12Hour'] ?? false));
            $this->assertSame(1, $data['firstDayOfWeek'] ?? null);
            $this->assertSame(1, $data['first_day_of_week'] ?? null);
            $this->assertSame('EUR', $data['context']['currency'] ?? null);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testBookingEndpointReturnsVisibleAndRequiredFieldConfigurationWithoutAuthentication(): void
    {
        $db = \Config\Database::connect('tests');
        $keys = [
            'booking.email_display',
            'booking.email_required',
            'booking.phone_display',
            'booking.phone_required',
            'booking.custom_field_1_enabled',
            'booking.custom_field_1_title',
            'booking.custom_field_1_type',
            'booking.custom_field_1_required',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $now = date('Y-m-d H:i:s');
            $db->table('settings')->insertBatch([
                ['setting_key' => 'booking.email_display', 'setting_value' => '1', 'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.email_required', 'setting_value' => '1', 'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.phone_display', 'setting_value' => '0', 'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.phone_required', 'setting_value' => '0', 'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.custom_field_1_enabled', 'setting_value' => '1', 'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.custom_field_1_title', 'setting_value' => 'Referral Source', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.custom_field_1_type', 'setting_value' => 'text', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.custom_field_1_required', 'setting_value' => '1', 'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $result = $this->get('/api/v1/settings/booking');

            $result->assertOK();

            $payload = json_decode($result->getJSON(), true);
            $data = $payload['data'] ?? [];

            $this->assertTrue((bool) ($data['fieldConfiguration']['email']['display'] ?? false));
            $this->assertTrue((bool) ($data['fieldConfiguration']['email']['required'] ?? false));
            $this->assertFalse((bool) ($data['fieldConfiguration']['phone']['display'] ?? true));
            $this->assertContains('email', $data['visibleFields'] ?? []);
            $this->assertNotContains('phone', $data['visibleFields'] ?? []);
            $this->assertContains('email', $data['requiredFields'] ?? []);
            $this->assertSame('Referral Source', $data['customFields']['custom_field_1']['title'] ?? null);
            $this->assertTrue((bool) ($data['customFields']['custom_field_1']['required'] ?? false));
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testCalendarConfigEndpointReturnsSchedulerSettingsWithoutAuthentication(): void
    {
        $db = \Config\Database::connect('tests');
        $keys = [
            'localization.timezone',
            'localization.time_format',
            'localization.first_day',
            'localization.locale',
            'booking.day_start',
            'booking.day_end',
            'calendar.slot_duration',
            'calendar.default_view',
            'calendar.show_weekends',
            'business.blocked_periods',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $now = date('Y-m-d H:i:s');
            $db->table('settings')->insertBatch([
                ['setting_key' => 'localization.timezone', 'setting_value' => 'Europe/Amsterdam', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'localization.time_format', 'setting_value' => '12h', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'localization.first_day', 'setting_value' => 'monday', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'localization.locale', 'setting_value' => 'nl', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.day_start', 'setting_value' => '07:30:00', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'booking.day_end', 'setting_value' => '18:15:00', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'calendar.slot_duration', 'setting_value' => '20', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'calendar.default_view', 'setting_value' => 'month', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'calendar.show_weekends', 'setting_value' => '0', 'setting_type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
                ['setting_key' => 'business.blocked_periods', 'setting_value' => json_encode([['start' => '2026-06-01 09:00:00', 'end' => '2026-06-01 12:00:00']]), 'setting_type' => 'json', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $result = $this->get('/api/v1/settings/calendar-config');

            $result->assertOK();

            $payload = json_decode($result->getJSON(), true);
            $data = $payload['data'] ?? [];

            $this->assertSame('month', $data['defaultView'] ?? null);
            $this->assertSame(1, $data['firstDay'] ?? null);
            $this->assertSame('00:20:00', $data['slotDuration'] ?? null);
            $this->assertSame('07:30', $data['slotMinTime'] ?? null);
            $this->assertSame('18:15', $data['slotMaxTime'] ?? null);
            $this->assertSame('h:mm a', $data['timeFormat'] ?? null);
            $this->assertFalse((bool) ($data['showWeekends'] ?? true));
            $this->assertSame('Europe/Amsterdam', $data['timezone'] ?? null);
            $this->assertSame('nl', $data['locale'] ?? null);
            $this->assertSame([['start' => '2026-06-01 09:00:00', 'end' => '2026-06-01 12:00:00']], $data['blockedPeriods'] ?? null);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testBusinessHoursEndpointReturnsDefaultAndStoredHoursWithoutAuthentication(): void
    {
        $result = $this->get('/api/v1/settings/business-hours');

        $result->assertOK();

        $payload = json_decode($result->getJSON(), true);
        $data = $payload['data'] ?? [];

        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day) {
            $this->assertArrayHasKey($day, $data);
            $this->assertIsBool($data[$day]['isWorkingDay'] ?? null);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $data[$day]['startTime'] ?? '');
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $data[$day]['endTime'] ?? '');
            $this->assertIsArray($data[$day]['breaks'] ?? null);
        }

        $this->assertFalse($data['sunday']['isWorkingDay'] ?? true);
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
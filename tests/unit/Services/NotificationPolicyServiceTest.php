<?php

namespace Tests\Unit\Services;

use App\Services\NotificationPolicyService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationPolicyServiceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');
        $db->table('business_notification_rules')->where('business_id', 1)->delete();
        $db->table('business_integrations')->where('business_id', 1)->delete();

        parent::tearDown();
    }

    public function testGetRulesReturnsCatalogMatrixWithPersistedOverrides(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('business_notification_rules')->insert([
            'business_id' => 1,
            'event_type' => 'appointment_reminder',
            'channel' => 'email',
            'is_enabled' => 1,
            'reminder_offset_minutes' => 45,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = new NotificationPolicyService();
        $rules = $service->getRules();

        $this->assertArrayHasKey('appointment_pending', $rules);
        $this->assertArrayHasKey('email', $rules['appointment_pending']);
        $this->assertArrayHasKey('appointment_confirmed', $rules);
        $this->assertArrayHasKey('email', $rules['appointment_confirmed']);
        $this->assertSame(1, $rules['appointment_reminder']['email']['is_enabled']);
        $this->assertSame(45, (int) $rules['appointment_reminder']['email']['reminder_offset_minutes']);
        $this->assertSame(0, $rules['appointment_cancelled']['sms']['is_enabled']);
        $this->assertNull($rules['appointment_cancelled']['sms']['reminder_offset_minutes']);
    }

    public function testGetIntegrationStatusMapsPersistedRowsAndBuildPreviewUsesChannelTemplates(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('business_integrations')->insert([
            'business_id' => 1,
            'channel' => 'email',
            'provider_name' => 'smtp',
            'encrypted_config' => 'configured',
            'is_active' => 1,
            'health_status' => 'ok',
            'last_tested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = new NotificationPolicyService();
        $status = $service->getIntegrationStatus();

        $this->assertTrue((bool) $status['email']['configured']);
        $this->assertTrue((bool) $status['email']['is_active']);
        $this->assertSame('smtp', $status['email']['provider_name']);
        $this->assertSame('', $status['email']['health_status']);
        $this->assertFalse((bool) $status['sms']['configured']);
        $this->assertStringContainsString('Subject: Appointment Confirmed', $service->buildPreview('appointment_confirmed', 'email'));
        $this->assertStringContainsString('Reply STOP to opt out.', $service->buildPreview('appointment_reminder', 'sms'));
        $this->assertStringContainsString('WhatsApp templates required', $service->buildPreview('appointment_confirmed', 'whatsapp'));
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
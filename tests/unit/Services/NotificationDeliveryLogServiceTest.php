<?php

namespace Tests\Unit\Services;

use App\Services\NotificationDeliveryLogService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationDeliveryLogServiceTest extends CIUnitTestCase
{
    public function testLogAttemptPersistsProviderAndNormalizesAttemptNumber(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $service = new NotificationDeliveryLogService();
        $correlationId = 'corr-' . bin2hex(random_bytes(4));

        $db->table('xs_business_integrations')
            ->where('business_id', 1)
            ->where('channel', 'sms')
            ->delete();
        $db->table('xs_notification_delivery_logs')
            ->where('correlation_id', $correlationId)
            ->delete();

        try {
            $db->table('xs_business_integrations')->insert([
                'business_id' => 1,
                'channel' => 'sms',
                'provider_name' => 'twilio',
                'encrypted_config' => '{}',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $service->logAttempt(
                1,
                99,
                $correlationId,
                'sms',
                'appointment_confirmed',
                null,
                '+15550003333',
                'failed',
                0,
                'Gateway timeout'
            );

            $row = $db->table('xs_notification_delivery_logs')
                ->where('correlation_id', $correlationId)
                ->get()
                ->getRowArray();

            $this->assertSame('twilio', $row['provider'] ?? null);
            $this->assertSame('failed', $row['status'] ?? null);
            $this->assertSame('1', (string) ($row['attempt'] ?? ''));
            $this->assertSame('Gateway timeout', $row['error_message'] ?? null);
        } finally {
            $db->table('xs_business_integrations')
                ->where('business_id', 1)
                ->where('channel', 'sms')
                ->delete();
            $db->table('xs_notification_delivery_logs')
                ->where('correlation_id', $correlationId)
                ->delete();
        }
    }

    public function testGetProviderNameReturnsNullWhenIntegrationMissing(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $service = new NotificationDeliveryLogService();

        $this->assertNull($service->getProviderName(999999, 'email'));
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
<?php

namespace Tests\Unit\Services;

use App\Services\NotificationOptOutService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationOptOutServiceTest extends CIUnitTestCase
{
    public function testOptOutInsertsThenUpdatesExistingRecipientReason(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $service = new NotificationOptOutService();
        $recipient = 'guest@example.com';

        $db->table('xs_notification_opt_outs')
            ->where('business_id', 1)
            ->where('channel', 'email')
            ->where('recipient', $recipient)
            ->delete();

        try {
            $insert = $service->optOut(1, 'email', $recipient, 'user_request');

            $this->assertTrue($insert['ok'] ?? false);
            $this->assertTrue($insert['inserted'] ?? false);
            $this->assertTrue($service->isOptedOut(1, 'email', $recipient));

            $update = $service->optOut(1, 'email', $recipient, 'complaint');

            $this->assertTrue($update['ok'] ?? false);
            $this->assertTrue($update['updated'] ?? false);

            $row = $db->table('xs_notification_opt_outs')
                ->where('business_id', 1)
                ->where('channel', 'email')
                ->where('recipient', $recipient)
                ->get()
                ->getRowArray();

            $this->assertSame('complaint', $row['reason'] ?? null);
        } finally {
            $db->table('xs_notification_opt_outs')
                ->where('business_id', 1)
                ->where('channel', 'email')
                ->where('recipient', $recipient)
                ->delete();
        }
    }

    public function testIsOptedOutReturnsFalseForBlankRecipient(): void
    {
        $service = new NotificationOptOutService();

        $this->assertFalse($service->isOptedOut(1, 'email', ''));
        $this->assertFalse($service->isOptedOut(1, 'email', '   '));
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
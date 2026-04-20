<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotifyColumnExistsTest extends CIUnitTestCase
{
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTestingDatabaseEnvironment();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect();

        if ($this->userIds !== []) {
            $db->table('users')->whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    public function testNotifyOnAppointmentsColumnExistsOnUsersTable(): void
    {
        $db = \Config\Database::connect();

        $this->assertTrue(
            $db->fieldExists('notify_on_appointments', 'users') || $db->fieldExists('notify_on_appointments', 'xs_users'),
            'Expected notify_on_appointments column to exist on users table.'
        );
    }

    public function testNotifyOnAppointmentsDefaultIsEnabledForNewUser(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Notify Default Provider ' . uniqid('', true),
            'email' => 'notify-default-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (int) $db->insertID();
        $this->userIds[] = $userId;

        $row = $db->table('users')
            ->select('notify_on_appointments')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertSame(1, (int) ($row['notify_on_appointments'] ?? 0));
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

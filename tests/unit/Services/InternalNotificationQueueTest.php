<?php

namespace Tests\Unit\Services;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class InternalNotificationQueueTest extends CIUnitTestCase
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
            $db->table('provider_staff_assignments')
                ->whereIn('provider_id', $this->userIds)
                ->orWhereIn('staff_id', $this->userIds)
                ->delete();

            $db->table('users')->whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    public function testGetNotifiableUsersForProviderIncludesOnlyEnabledRecipients(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $providerId = $this->insertUser('provider', 1, $now);
        $staffEnabledId = $this->insertUser('staff', 1, $now);
        $staffDisabledId = $this->insertUser('staff', 0, $now);

        $db->table('provider_staff_assignments')->insertBatch([
            [
                'provider_id' => $providerId,
                'staff_id' => $staffEnabledId,
                'assigned_by' => null,
                'status' => 'active',
                'assigned_at' => $now,
            ],
            [
                'provider_id' => $providerId,
                'staff_id' => $staffDisabledId,
                'assigned_by' => null,
                'status' => 'active',
                'assigned_at' => $now,
            ],
        ]);

        $users = (new UserModel())->getNotifiableUsersForProvider($providerId);
        $recipientIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $users);

        $this->assertContains($providerId, $recipientIds, 'Provider should be included when notify_on_appointments=1.');
        $this->assertContains($staffEnabledId, $recipientIds, 'Assigned active staff should be included when notify_on_appointments=1.');
        $this->assertNotContains($staffDisabledId, $recipientIds, 'Staff with notify_on_appointments=0 must be excluded.');
    }

    private function insertUser(string $role, int $notifyOnAppointments, string $now): int
    {
        $db = \Config\Database::connect();

        $db->table('users')->insert([
            'name' => ucfirst($role) . ' Internal ' . uniqid('', true),
            'email' => $role . '-internal-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => $role,
            'status' => 'active',
            'is_active' => 1,
            'notify_on_appointments' => $notifyOnAppointments,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (int) $db->insertID();
        $this->userIds[] = $userId;

        return $userId;
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

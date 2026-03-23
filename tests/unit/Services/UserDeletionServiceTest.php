<?php

namespace Tests\Unit\Services;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use App\Services\UserDeletionService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UserDeletionServiceTest extends CIUnitTestCase
{
    public function testDeleteUserByIdBlocksDeletionOfLastActiveAdmin(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $otherActiveAdmins = $db->table('users')
            ->select('id')
            ->where('role', 'admin')
            ->where('is_active', 1)
            ->get()
            ->getResultArray();
        $otherActiveAdminIds = array_values(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $otherActiveAdmins));

        if ($otherActiveAdminIds !== []) {
            $db->table('users')->whereIn('id', $otherActiveAdminIds)->update([
                'is_active' => 0,
                'status' => 'inactive',
                'updated_at' => $now,
            ]);
        }

        $db->table('users')->insert([
            'name' => 'Last Admin',
            'email' => 'last-admin-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $adminId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'Guard Admin',
            'email' => 'guard-admin-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'inactive',
            'is_active' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $guardAdminId = (int) $db->insertID();

        try {
            $service = new UserDeletionService(new UserModel(), new AuditLogModel());
            $result = $service->deleteUserById($guardAdminId, $adminId);

            $this->assertFalse((bool) ($result['success'] ?? true));
            $this->assertSame('LAST_ADMIN', $result['blockCode'] ?? null);
            $this->assertSame('Cannot delete the last active administrator. Promote another admin first.', $result['message'] ?? null);
            $this->assertSame(1, $result['impact']['adminCount'] ?? null);

            $remaining = $db->table('users')->where('id', $adminId)->get()->getRowArray();
            $this->assertNotNull($remaining);
            $this->assertSame('admin', $remaining['role'] ?? null);
        } finally {
            $db->table('users')->where('id', $adminId ?? 0)->delete();
            $db->table('users')->where('id', $guardAdminId ?? 0)->delete();
            if ($otherActiveAdminIds !== []) {
                $db->table('users')->whereIn('id', $otherActiveAdminIds)->update([
                    'is_active' => 1,
                    'status' => 'active',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function testBuildPreviewForUserIdReturnsNotFoundForMissingTarget(): void
    {
        $service = new UserDeletionService(new UserModel(), new AuditLogModel());
        $result = $service->buildPreviewForUserId(123456, 987654321);

        $this->assertFalse((bool) ($result['success'] ?? true));
        $this->assertSame(404, $result['statusCode'] ?? null);
        $this->assertSame('User not found.', $result['message'] ?? null);
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
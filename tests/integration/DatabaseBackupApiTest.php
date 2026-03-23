<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for the admin database-backup API journey.
 */
final class DatabaseBackupApiTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDirectory = WRITEPATH . 'backups';

        $this->configureTestingDatabaseEnvironment();
        $this->ensureSetupFlag();
        $this->ensureBackupDirectory();
        $this->clearBackupDirectory();
        $this->ensureAdminUser();
        $this->seedBackupSetting(false);
    }

    protected function tearDown(): void
    {
        $this->clearBackupDirectory();

        parent::tearDown();
    }

    public function testStatusRequiresAdminSession(): void
    {
        $result = $this->get('/api/database-backup/status');

        $result->assertStatus(401);

        $payload = json_decode($result->getJSON(), true);

        $this->assertSame('Authentication required. Please log in.', $payload['error']['message'] ?? null);
        $this->assertSame('unauthenticated', $payload['error']['code'] ?? null);
    }

    public function testDownloadRequiresAdminSession(): void
    {
        $result = $this->get('/api/database-backup/download/backup-2026-03-20_101500-aaaaaaaaaaaaaaaa.sql.gz');

        $result->assertStatus(401);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('unauthenticated', $error['code'] ?? null);
        $this->assertSame('Authentication required. Please log in.', $error['message'] ?? null);
    }

    public function testAdminCanToggleBackupsAndReadUpdatedStatus(): void
    {
        $toggle = $this->withSession($this->adminSession())
            ->withBodyFormat('json')
            ->post(
                '/api/database-backup/toggle',
                ['enabled' => true]
            );

        $toggle->assertOK();

        $togglePayload = json_decode($toggle->getJSON(), true);
        $this->assertTrue((bool) ($togglePayload['enabled'] ?? false));
        $this->assertTrue((bool) ($togglePayload['data']['enabled'] ?? false));
        $this->assertSame('Database backups enabled', $togglePayload['message'] ?? null);

        $status = $this->withSession($this->adminSession())
            ->get('/api/database-backup/status');

        $status->assertOK();

        $statusPayload = json_decode($status->getJSON(), true);
        $statusData = $statusPayload['data'] ?? [];

        $this->assertTrue((bool) ($statusData['backup_enabled'] ?? false));
        $this->assertSame('MySQLi', $statusData['database']['type'] ?? null);
        $this->assertNotSame('', $statusData['database']['host'] ?? '');
    }

    public function testAdminCannotCreateBackupWhenMasterSwitchDisabled(): void
    {
        $create = $this->withSession($this->adminSession())
            ->post('/api/database-backup/create');

        $create->assertStatus(403);

        $payload = json_decode($create->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('FORBIDDEN', $error['code'] ?? null);
        $this->assertSame('Database backups are disabled. Enable in Settings -> Database.', $error['message'] ?? null);
    }

    public function testAdminCanListAndDeleteBackups(): void
    {
        $older = $this->writeBackupFixture('backup-2026-03-20_101500-aaaaaaaaaaaaaaaa.sql.gz', 'older backup fixture');
        $newer = $this->writeBackupFixture('backup-2026-03-21_084500-bbbbbbbbbbbbbbbb.sql.gz', 'newer backup fixture');

        touch($older, strtotime('2026-03-20 10:15:00'));
        touch($newer, strtotime('2026-03-21 08:45:00'));

        $list = $this->withSession($this->adminSession())
            ->get('/api/database-backup/list');

        $list->assertOK();

        $listPayload = json_decode($list->getJSON(), true);
        $backups = $listPayload['data']['backups'] ?? [];

        $this->assertCount(2, $backups);
        $this->assertSame('backup-2026-03-21_084500-bbbbbbbbbbbbbbbb.sql.gz', $backups[0]['filename'] ?? null);
        $this->assertSame('backup-2026-03-20_101500-aaaaaaaaaaaaaaaa.sql.gz', $backups[1]['filename'] ?? null);
        $this->assertSame(2, $listPayload['data']['total'] ?? null);

        $delete = $this->withSession($this->adminSession())
            ->delete('/api/database-backup/delete/backup-2026-03-21_084500-bbbbbbbbbbbbbbbb.sql.gz');

        $delete->assertOK();

        $deletePayload = json_decode($delete->getJSON(), true);
        $this->assertSame('Backup deleted successfully', $deletePayload['message'] ?? null);
        $this->assertFileDoesNotExist($newer);
        $this->assertFileExists($older);

        $afterDelete = $this->withSession($this->adminSession())
            ->get('/api/database-backup/list');

        $afterDelete->assertOK();

        $afterDeletePayload = json_decode($afterDelete->getJSON(), true);
        $remaining = $afterDeletePayload['data']['backups'] ?? [];

        $this->assertCount(1, $remaining);
        $this->assertSame('backup-2026-03-20_101500-aaaaaaaaaaaaaaaa.sql.gz', $remaining[0]['filename'] ?? null);
    }

    public function testAdminDeleteRejectsInvalidFilenameAndMissingBackup(): void
    {
        $invalid = $this->withSession($this->adminSession())
            ->delete('/api/database-backup/delete/not-a-backup.txt');

        $invalid->assertStatus(422);

        $invalidPayload = json_decode($invalid->getJSON(), true);
        $invalidError = $invalidPayload['error'] ?? [];

        $this->assertSame('VALIDATION_ERROR', $invalidError['code'] ?? null);
        $this->assertSame('Validation failed', $invalidError['message'] ?? null);
        $this->assertSame('Invalid filename', $invalidError['details'] ?? null);

        $missing = $this->withSession($this->adminSession())
            ->delete('/api/database-backup/delete/backup-2026-03-22_120000-cccccccccccccccc.sql.gz');

        $missing->assertStatus(404);

        $missingPayload = json_decode($missing->getJSON(), true);
        $missingError = $missingPayload['error'] ?? [];

        $this->assertSame('NOT_FOUND', $missingError['code'] ?? null);
        $this->assertSame('Backup file not found', $missingError['message'] ?? null);
        $this->assertNull($missingError['details'] ?? null);
    }

    public function testAdminDownloadRejectsInvalidFilenameAndMissingBackup(): void
    {
        $invalid = $this->withSession($this->adminSession())
            ->get('/api/database-backup/download/not-a-backup.txt');

        $invalid->assertStatus(422);

        $invalidPayload = json_decode($invalid->getJSON(), true);
        $invalidError = $invalidPayload['error'] ?? [];

        $this->assertSame('VALIDATION_ERROR', $invalidError['code'] ?? null);
        $this->assertSame('Validation failed', $invalidError['message'] ?? null);
        $this->assertSame('Invalid filename', $invalidError['details'] ?? null);

        $missing = $this->withSession($this->adminSession())
            ->get('/api/database-backup/download/backup-2026-03-22_120000-dddddddddddddddd.sql.gz');

        $missing->assertStatus(404);

        $missingPayload = json_decode($missing->getJSON(), true);
        $missingError = $missingPayload['error'] ?? [];

        $this->assertSame('NOT_FOUND', $missingError['code'] ?? null);
        $this->assertSame('Backup file not found', $missingError['message'] ?? null);
        $this->assertNull($missingError['details'] ?? null);
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => 1,
            'user' => [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function ensureSetupFlag(): void
    {
        $flagPath = WRITEPATH . 'setup_complete.flag';

        if (!is_file($flagPath)) {
            file_put_contents($flagPath, 'test');
        }
    }

    private function ensureBackupDirectory(): void
    {
        if (!is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0755, true);
        }
    }

    private function clearBackupDirectory(): void
    {
        if (!is_dir($this->backupDirectory)) {
            return;
        }

        foreach (glob($this->backupDirectory . DIRECTORY_SEPARATOR . 'backup-*.sql*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function seedBackupSetting(bool $enabled): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $existing = $db->table('settings')
            ->where('setting_key', 'database.allow_backup')
            ->get()
            ->getRowArray();

        $payload = [
            'setting_value' => $enabled ? '1' : '0',
            'setting_type' => 'bool',
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            $db->table('settings')
                ->where('setting_key', 'database.allow_backup')
                ->update($payload);
            return;
        }

        $db->table('settings')->insert($payload + [
            'setting_key' => 'database.allow_backup',
            'created_at' => $now,
        ]);
    }

    private function ensureAdminUser(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $existing = $db->table('users')
            ->where('id', 1)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return;
        }

        $db->table('users')->insert([
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin-backup-test@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function writeBackupFixture(string $filename, string $contents): string
    {
        $path = $this->backupDirectory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $contents);

        return $path;
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
<?php

namespace App\Tests\Integration;

use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ProfileJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
        $this->ensureSetupFlag();
        $this->seedProfileUser();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if (isset($this->userId)) {
            $db->table('audit_logs')->where('target_id', $this->userId)->orWhere('user_id', $this->userId)->delete();
            $db->table('provider_staff_assignments')->where('provider_id', $this->userId)->orWhere('staff_id', $this->userId)->delete();
            $db->table('provider_schedules')->where('provider_id', $this->userId)->delete();
            $db->table('users')->where('id', $this->userId)->delete();
        }

        parent::tearDown();
    }

    public function testProfilePageRendersRealAccountDataAndAuditActivity(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('audit_logs')->insertBatch([
            [
                'user_id' => $this->userId,
                'action' => 'user_login',
                'target_type' => 'user',
                'target_id' => $this->userId,
                'old_value' => null,
                'new_value' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'created_at' => $now,
            ],
            [
                'user_id' => $this->userId,
                'action' => 'user_updated',
                'target_type' => 'user',
                'target_id' => $this->userId,
                'old_value' => json_encode(['name' => 'Journey Profile']),
                'new_value' => json_encode(['name' => 'Journey Profile']),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'created_at' => date('Y-m-d H:i:s', strtotime($now . ' +1 minute')),
            ],
        ]);

        $response = $this->withSession($this->profileSession())
            ->get('/profile');

        $response->assertOK();
        $response->assertSee('Account summary');
        $response->assertSee('Profile settings');
        $response->assertSee('Journey Profile');
        $response->assertSee('journey-profile@example.com');
        $response->assertSee('Signed in');
        $response->assertSee('Profile updated');
        $response->assertDontSee('Privacy Settings');
        $response->assertDontSee('System backup completed successfully');
    }

    public function testProfileUpdateEndpointPersistsChangesAndWritesAuditLog(): void
    {
        $db = \Config\Database::connect('tests');
        $newEmail = 'journey-profile-updated-' . uniqid('', true) . '@example.com';

        $this->primeCsrfCookie();

        $response = $this->withSession($this->profileSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/profile/update-profile', [
                $this->csrfTokenName() => $this->csrfToken(),
                'first_name' => 'Journey',
                'last_name' => 'Profile Updated',
                'email' => $newEmail,
                'phone' => '+15551234567',
            ]);

        $response->assertOK();

        $payload = json_decode($response->getJSON(), true);
        $this->assertTrue((bool) ($payload['success'] ?? false));
        $this->assertSame('Profile updated successfully.', $payload['message'] ?? null);
        $this->assertStringContainsString('/profile#profile', $payload['redirect'] ?? '');

        $updatedUser = $db->table('users')->where('id', $this->userId)->get()->getRowArray();
        $this->assertNotNull($updatedUser);
        $this->assertSame('Journey Profile Updated', $updatedUser['name'] ?? null);
        $this->assertSame($newEmail, $updatedUser['email'] ?? null);
        $this->assertSame('+15551234567', $updatedUser['phone'] ?? null);

        $auditLog = $db->table('audit_logs')
            ->where('target_id', $this->userId)
            ->where('action', 'user_updated')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditLog);
        $newValue = json_decode($auditLog['new_value'] ?? '[]', true);
        $this->assertSame('Journey Profile Updated', $newValue['name'] ?? null);
        $this->assertSame($newEmail, $newValue['email'] ?? null);
    }

    public function testPasswordChangeEndpointUpdatesHashAndWritesAuditLog(): void
    {
        $db = \Config\Database::connect('tests');

        $this->primeCsrfCookie();

        $response = $this->withSession($this->profileSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/profile/change-password', [
                $this->csrfTokenName() => $this->csrfToken(),
                'current_password' => 'password123',
                'new_password' => 'new-password-123',
                'confirm_password' => 'new-password-123',
            ]);

        $response->assertOK();

        $payload = json_decode($response->getJSON(), true);
        $this->assertTrue((bool) ($payload['success'] ?? false));
        $this->assertSame('Password updated successfully.', $payload['message'] ?? null);
        $this->assertStringContainsString('/profile#password', $payload['redirect'] ?? '');

        $updatedUser = $db->table('users')->where('id', $this->userId)->get()->getRowArray();
        $this->assertNotNull($updatedUser);
        $this->assertTrue(password_verify('new-password-123', $updatedUser['password_hash'] ?? ''));

        $auditLog = $db->table('audit_logs')
            ->where('target_id', $this->userId)
            ->where('action', 'password_changed')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditLog);
    }

    public function testProviderCanUpdateNotificationPreferenceAndPageReflectsIt(): void
    {
        $db = \Config\Database::connect('tests');

        if (!$this->hasUsersColumn($db, 'notify_on_appointments')) {
            $this->markTestSkipped('xs_users.notify_on_appointments is not available in this schema.');
        }

        $this->primeCsrfCookie();

        $response = $this->withSession($this->profileSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/profile/update-notifications', [
                $this->csrfTokenName() => $this->csrfToken(),
                'notify_on_appointments' => '1',
            ]);

        $response->assertOK();

        $payload = json_decode($response->getJSON(), true);
        $this->assertTrue((bool) ($payload['success'] ?? false));
        $this->assertSame('Appointment notifications enabled.', $payload['message'] ?? null);
        $this->assertStringContainsString('/profile#notifications', $payload['redirect'] ?? '');

        $updatedUser = $db->table('users')->where('id', $this->userId)->get()->getRowArray();
        $this->assertNotNull($updatedUser);
        $this->assertSame('1', (string) ($updatedUser['notify_on_appointments'] ?? '0'));

        $auditLog = $db->table('audit_logs')
            ->where('target_id', $this->userId)
            ->where('action', 'notification_preferences_updated')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditLog);

        $page = $this->withSession($this->profileSession())
            ->get('/profile');

        $page->assertOK();
        $page->assertSee('Notification settings');
        $page->assertSee('Email me about my appointment queue');
        $this->assertMatchesRegularExpression('/id="profile_notify_on_appointments"[^>]*checked/', $page->getBody());
    }

    private function profileSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->userId,
            'user' => [
                'name' => 'Journey Profile',
                'email' => 'journey-profile@example.com',
                'role' => 'provider',
                'roles' => ['provider'],
                'active_role' => 'provider',
            ],
        ];
    }

    private function activeUserColumns($db, bool $active): array
    {
        $columns = [];

        if ($this->hasUsersColumn($db, 'status')) {
            $columns['status'] = $active ? 'active' : 'inactive';
        }

        if ($this->hasUsersColumn($db, 'is_active')) {
            $columns['is_active'] = $active ? 1 : 0;
        }

        return $columns;
    }

    private function optionalUserColumns($db, array $values): array
    {
        $columns = [];

        foreach ($values as $column => $value) {
            if ($this->hasUsersColumn($db, $column)) {
                $columns[$column] = $value;
            }
        }

        return $columns;
    }

    private function hasUsersColumn($db, string $column): bool
    {
        if (!method_exists($db, 'fieldExists')) {
            return true;
        }

        return $db->fieldExists($column, 'users') || $db->fieldExists($column, $db->prefixTable('users'));
    }

    private function ajaxHeaders(): array
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

    private function csrfTokenName(): string
    {
        return config('Security')->tokenName;
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

    private function seedProfileUser(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Journey Profile',
            'email' => 'journey-profile@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'phone' => '+15550001111',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true) + $this->optionalUserColumns($db, [
            'last_login' => $now,
            'notify_on_appointments' => 0,
        ]));

        $this->userId = (int) $db->insertID();
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

        $dbConfig = config(\Config\Database::class);
        foreach (['hostname', 'database', 'username', 'password', 'DBDriver', 'DBPrefix', 'port'] as $field) {
            $envKey = 'database.tests.' . $field;
            $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            $dbConfig->tests[$field] = $field === 'port' ? (int) $value : $value;
            $dbConfig->default[$field] = $field === 'port' ? (int) $value : $value;
        }

        foreach (DatabaseConfig::getConnections() as $connection) {
            try {
                $connection->close();
            } catch (\Throwable) {
            }
        }

        $reflection = new \ReflectionClass(DatabaseConfig::class);
        $instances = $reflection->getProperty('instances');
        $instances->setAccessible(true);
        $instances->setValue([]);
    }
}
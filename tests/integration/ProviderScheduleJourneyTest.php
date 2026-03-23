<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for provider schedule controller routes.
 */
final class ProviderScheduleJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $providerId;
    private int $otherProviderId;
    private int $staffId;

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

        $db->table('provider_schedules')->whereIn('provider_id', [$this->providerId, $this->otherProviderId])->delete();
        $db->table('settings')->whereIn('setting_key', [
            'localization.timezone',
            'localization.time_format',
            'localization.first_day',
            'localization.currency',
        ])->delete();
        $db->table('users')->whereIn('id', [$this->adminId, $this->providerId, $this->otherProviderId, $this->staffId])->delete();

        parent::tearDown();
    }

    public function testProviderCanSaveAdminCanViewAndDeleteScheduleAndOtherUsersAreForbidden(): void
    {
        $initial = $this->withSession($this->providerSession($this->providerId, 'Schedule Provider'))
            ->get('/providers/' . $this->providerId . '/schedule');

        $initial->assertOK();

        $initialPayload = json_decode($initial->getJSON(), true);
        $this->assertSame($this->providerId, $initialPayload['provider_id'] ?? null);
        $this->assertSame([], $initialPayload['schedule'] ?? null);
        $this->assertSame('12h', $initialPayload['localization']['time_format'] ?? null);

        $this->primeCsrfCookie();

        $save = $this->withSession($this->providerSession($this->providerId, 'Schedule Provider'))
            ->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/providers/' . $this->providerId . '/schedule', [
                'schedule' => [
                    'monday' => [
                        'is_active' => true,
                        'start_time' => '9:00 AM',
                        'end_time' => '5:00 PM',
                        'break_start' => '12:00 PM',
                        'break_end' => '01:00 PM',
                    ],
                    'tuesday' => [
                        'is_active' => false,
                    ],
                ],
            ]);

        $save->assertOK();

        $savePayload = json_decode($save->getJSON(), true);
        $this->assertSame('success', $savePayload['status'] ?? null);
        $this->assertSame('Schedule updated.', $savePayload['message'] ?? null);
        $this->assertArrayHasKey('monday', $savePayload['schedule'] ?? []);

        $db = \Config\Database::connect('tests');
        $row = $db->table('provider_schedules')->where('provider_id', $this->providerId)->where('day_of_week', 'monday')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('09:00:00', $row['start_time'] ?? null);
        $this->assertSame('17:00:00', $row['end_time'] ?? null);
        $this->assertSame('12:00:00', $row['break_start'] ?? null);
        $this->assertSame('13:00:00', $row['break_end'] ?? null);

        $adminView = $this->withSession($this->adminSession())
            ->get('/providers/' . $this->providerId . '/schedule');

        $adminView->assertOK();

        $adminPayload = json_decode($adminView->getJSON(), true);
        $this->assertSame('monday', $adminPayload['schedule']['monday']['day_of_week'] ?? null);

        $this->primeCsrfCookie();

        $forbidden = $this->withSession($this->providerSession($this->otherProviderId, 'Other Provider'))
            ->withHeaders($this->csrfJsonHeaders())
            ->withBodyFormat('json')
            ->post('/providers/' . $this->providerId . '/schedule', [
                'schedule' => [
                    'friday' => [
                        'is_active' => true,
                        'start_time' => '09:00',
                        'end_time' => '10:00',
                    ],
                ],
            ]);

        $forbidden->assertStatus(403);

        $forbiddenPayload = json_decode($forbidden->getJSON(), true);
        $this->assertSame('error', $forbiddenPayload['status'] ?? null);
        $this->assertSame('Not permitted to update this schedule.', $forbiddenPayload['message'] ?? null);

        $this->primeCsrfCookie();

        $delete = $this->withSession($this->adminSession())
            ->withHeaders($this->csrfJsonHeaders())
            ->delete('/providers/' . $this->providerId . '/schedule');

        $delete->assertOK();

        $deletePayload = json_decode($delete->getJSON(), true);
        $this->assertSame('success', $deletePayload['status'] ?? null);
        $this->assertSame('Schedule deleted.', $deletePayload['message'] ?? null);
        $this->assertSame(0, $db->table('provider_schedules')->where('provider_id', $this->providerId)->countAllResults());
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        foreach ([
            ['Journey Admin', 'admin'],
            ['Schedule Provider', 'provider'],
            ['Other Provider', 'provider'],
            ['Schedule Staff', 'staff'],
        ] as $index => [$name, $role]) {
            $db->table('users')->insert([
                'name' => $name,
                'email' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid('', true) . '@example.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'role' => $role,
                'status' => 'active',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $id = (int) $db->insertID();
            if ($index === 0) {
                $this->adminId = $id;
            } elseif ($index === 1) {
                $this->providerId = $id;
            } elseif ($index === 2) {
                $this->otherProviderId = $id;
            } else {
                $this->staffId = $id;
            }
        }

        $db->table('settings')->whereIn('setting_key', [
            'localization.timezone',
            'localization.time_format',
            'localization.first_day',
            'localization.currency',
        ])->delete();

        $db->table('settings')->insertBatch([
            ['setting_key' => 'localization.timezone', 'setting_value' => 'UTC', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'localization.time_format', 'setting_value' => '12h', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'localization.first_day', 'setting_value' => 'monday', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'localization.currency', 'setting_value' => 'USD', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Journey Admin',
                'email' => 'journey-admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function providerSession(int $userId, string $name): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $userId,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => strtolower(str_replace(' ', '-', $name)) . '@example.com',
                'role' => 'provider',
            ],
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

    private function csrfJsonHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN' => $this->csrfToken(),
        ];
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
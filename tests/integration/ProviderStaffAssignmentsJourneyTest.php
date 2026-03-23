<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for provider/staff assignment controllers.
 */
final class ProviderStaffAssignmentsJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $providerId;
    private int $providerTwoId;
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
        $db->table('provider_staff_assignments')->whereIn('provider_id', [$this->providerId, $this->providerTwoId])->delete();
        $db->table('users')->whereIn('id', [$this->adminId, $this->providerId, $this->providerTwoId, $this->staffId])->delete();

        parent::tearDown();
    }

    public function testProviderAndAdminCanManageAssignmentsAcrossBothControllerDirections(): void
    {
        $this->primeCsrfCookie();

        $assign = $this->withSession($this->providerSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/provider-staff/assign', [
                $this->csrfTokenName() => $this->csrfToken(),
                'provider_id' => $this->providerId,
                'staff_id' => $this->staffId,
            ]);

        $assign->assertStatus(201);

        $assignPayload = json_decode($assign->getJSON(), true);
        $this->assertTrue((bool) ($assignPayload['success'] ?? false));
        $this->assertSame('Staff assigned successfully.', $assignPayload['message'] ?? null);
        $this->assertCount(1, $assignPayload['staff'] ?? []);
        $this->assertSame($this->staffId, (int) ($assignPayload['staff'][0]['id'] ?? 0));

        $duplicate = $this->withSession($this->providerSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/provider-staff/assign', [
                $this->csrfTokenName() => $this->csrfToken(),
                'provider_id' => $this->providerId,
                'staff_id' => $this->staffId,
            ]);

        $duplicate->assertStatus(409);

        $providerList = $this->withSession($this->providerSession())
            ->get('/provider-staff/provider/' . $this->providerId);

        $providerList->assertOK();

        $providerListPayload = json_decode($providerList->getJSON(), true);
        $this->assertTrue((bool) ($providerListPayload['success'] ?? false));
        $this->assertCount(1, $providerListPayload['staff'] ?? []);
        $this->assertSame($this->staffId, (int) ($providerListPayload['staff'][0]['id'] ?? 0));

        $staffSelfList = $this->withSession($this->staffSession())
            ->get('/staff-providers/staff/' . $this->staffId);

        $staffSelfList->assertOK();

        $staffListPayload = json_decode($staffSelfList->getJSON(), true);
        $this->assertSame('ok', $staffListPayload['status'] ?? null);
        $this->assertCount(1, $staffListPayload['providers'] ?? []);
        $this->assertSame($this->providerId, (int) ($staffListPayload['providers'][0]['id'] ?? 0));

        $this->primeCsrfCookie();

        $adminAssign = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/staff-providers/assign', [
                $this->csrfTokenName() => $this->csrfToken(),
                'staff_id' => $this->staffId,
                'provider_id' => $this->providerTwoId,
            ]);

        $adminAssign->assertStatus(201);

        $adminAssignPayload = json_decode($adminAssign->getJSON(), true);
        $this->assertSame('Provider assigned successfully.', $adminAssignPayload['message'] ?? null);
        $this->assertCount(2, $adminAssignPayload['providers'] ?? []);

        $this->primeCsrfCookie();

        $remove = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/staff-providers/remove', [
                $this->csrfTokenName() => $this->csrfToken(),
                'staff_id' => $this->staffId,
                'provider_id' => $this->providerId,
            ]);

        $remove->assertOK();

        $removePayload = json_decode($remove->getJSON(), true);
        $this->assertSame('Provider removed successfully.', $removePayload['message'] ?? null);
        $this->assertCount(1, $removePayload['providers'] ?? []);
        $this->assertSame($this->providerTwoId, (int) ($removePayload['providers'][0]['id'] ?? 0));

        $db = \Config\Database::connect('tests');
        $this->assertSame(1, $db->table('provider_staff_assignments')->where('staff_id', $this->staffId)->countAllResults());
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        foreach ([
            ['Assignment Admin', 'admin'],
            ['Assignment Provider', 'provider'],
            ['Assignment Provider Two', 'provider'],
            ['Assignment Staff', 'staff'],
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
                $this->providerTwoId = $id;
            } else {
                $this->staffId = $id;
            }
        }
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Assignment Admin',
                'email' => 'assignment-admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function providerSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->providerId,
            'user' => [
                'id' => $this->providerId,
                'name' => 'Assignment Provider',
                'email' => 'assignment-provider@example.com',
                'role' => 'provider',
            ],
        ];
    }

    private function staffSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->staffId,
            'user' => [
                'id' => $this->staffId,
                'name' => 'Assignment Staff',
                'email' => 'assignment-staff@example.com',
                'role' => 'staff',
            ],
        ];
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
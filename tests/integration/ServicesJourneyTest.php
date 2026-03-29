<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ServicesJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $providerId;
    private int $existingCategoryId;
    private ?int $createdCategoryId = null;
    private ?int $serviceId = null;

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

        if ($this->serviceId !== null) {
            $db->table('providers_services')->where('service_id', $this->serviceId)->delete();
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if ($this->createdCategoryId !== null) {
            $db->table('categories')->where('id', $this->createdCategoryId)->delete();
        }

        if (isset($this->existingCategoryId)) {
            $db->table('categories')->where('id', $this->existingCategoryId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        if (isset($this->adminId)) {
            $db->table('users')->where('id', $this->adminId)->delete();
        }

        parent::tearDown();
    }

    public function testAdminCanCreateUpdateAndDeleteServiceWithProviderAssignments(): void
    {
        $this->primeCsrfCookie();

        $create = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/services/store', [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Journey Service ' . uniqid(),
                'description' => 'Created by services journey test',
                'duration_min' => '45',
                'price' => '95.50',
                'active' => '1',
                'provider_ids' => [(string) $this->providerId],
                'new_category_name' => 'Journey Category ' . uniqid(),
                'new_category_description' => 'Created inline while storing a service',
                'new_category_color' => '#0EA5E9',
            ]);

        $create->assertOK();

        $createPayload = json_decode($create->getJSON(), true);
        $this->assertTrue((bool) ($createPayload['success'] ?? false), $create->getBody());
        $this->assertSame('/services', parse_url((string) ($createPayload['redirect'] ?? ''), PHP_URL_PATH));

        $this->serviceId = (int) ($createPayload['id'] ?? 0);
        $this->assertGreaterThan(0, $this->serviceId);

        $db = \Config\Database::connect('tests');
        $service = $db->table('services')->where('id', $this->serviceId)->get()->getRowArray();

        $this->assertNotNull($service);
        $this->assertSame('Created by services journey test', $service['description'] ?? null);
        $this->assertSame('45', (string) ($service['duration_min'] ?? ''));
        $this->assertSame('95.50', number_format((float) ($service['price'] ?? 0), 2, '.', ''));

        $this->createdCategoryId = isset($service['category_id']) ? (int) $service['category_id'] : null;
        $this->assertNotNull($this->createdCategoryId);

        $createdCategory = $db->table('categories')->where('id', $this->createdCategoryId)->get()->getRowArray();
        $this->assertNotNull($createdCategory);
        $this->assertStringStartsWith('Journey Category ', $createdCategory['name'] ?? '');

        $providerLinks = $db->table('providers_services')
            ->where('service_id', $this->serviceId)
            ->orderBy('provider_id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame([$this->providerId], array_map(static fn(array $row): int => (int) $row['provider_id'], $providerLinks));

        $this->primeCsrfCookie();

        $update = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/services/update/' . $this->serviceId, [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Journey Service Updated',
                'description' => 'Updated by services journey test',
                'duration_min' => '60',
                'price' => '120.00',
                'category_id' => (string) $this->existingCategoryId,
                'active' => '1',
                'provider_ids' => [],
            ]);

        $update->assertOK();

        $updatePayload = json_decode($update->getJSON(), true);
        $this->assertTrue((bool) ($updatePayload['success'] ?? false), $update->getBody());

        $updatedService = $db->table('services')->where('id', $this->serviceId)->get()->getRowArray();
        $this->assertSame('Journey Service Updated', $updatedService['name'] ?? null);
        $this->assertSame('Updated by services journey test', $updatedService['description'] ?? null);
        $this->assertSame((string) $this->existingCategoryId, (string) ($updatedService['category_id'] ?? ''));

        $providerLinksAfterUpdate = $db->table('providers_services')
            ->where('service_id', $this->serviceId)
            ->get()
            ->getResultArray();
        $this->assertSame([], $providerLinksAfterUpdate);

        $this->primeCsrfCookie();

        $delete = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/services/delete/' . $this->serviceId, [
                $this->csrfTokenName() => $this->csrfToken(),
            ]);

        $delete->assertOK();

        $deletePayload = json_decode($delete->getJSON(), true);
        $this->assertTrue((bool) ($deletePayload['success'] ?? false), $delete->getBody());
        $this->assertNull($db->table('services')->where('id', $this->serviceId)->get()->getRowArray());
        $this->assertSame([], $db->table('providers_services')->where('service_id', $this->serviceId)->get()->getResultArray());
        $this->serviceId = null;
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Services Journey Admin',
            'email' => 'services-journey-admin-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));
        $this->adminId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'Services Journey Provider',
            'email' => 'services-journey-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));
        $this->providerId = (int) $db->insertID();

        $db->table('categories')->insert([
            'name' => 'Existing Journey Category',
            'description' => 'Pre-seeded category for service update coverage',
            'color' => '#22C55E',
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->existingCategoryId = (int) $db->insertID();
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Services Journey Admin',
                'email' => 'services-journey-admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function activeUserColumns($db, bool $active): array
    {
        $columns = [];

        if ($db->fieldExists('status', 'users')) {
            $columns['status'] = $active ? 'active' : 'inactive';
        }

        if ($db->fieldExists('is_active', 'users')) {
            $columns['is_active'] = $active ? 1 : 0;
        }

        return $columns;
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

    private function ajaxHeaders(): array
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
            @mkdir(dirname($flagPath), 0777, true);
            file_put_contents($flagPath, '1');
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
            'database.tests.port' => $values['database.tests.port'] ?? $values['database.default.port'] ?? null,
        ];

        foreach ($mapping as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

    }
}
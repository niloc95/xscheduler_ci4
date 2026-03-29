<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Controller-level regression coverage for customer management CRUD/search/history flows.
 */
final class CustomerManagementJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $providerId;
    private int $serviceId;
    private ?int $customerId = null;
    private ?string $customerHash = null;
    private array $appointmentIds = [];

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

        $bookingSettingKeys = [
            'booking.first_names_display',
            'booking.first_names_required',
            'booking.surname_display',
            'booking.surname_required',
            'booking.email_display',
            'booking.email_required',
            'booking.phone_display',
            'booking.phone_required',
            'booking.address_display',
            'booking.address_required',
            'booking.notes_display',
            'booking.notes_required',
        ];
        for ($i = 1; $i <= 6; $i++) {
            $bookingSettingKeys[] = "booking.custom_field_{$i}_enabled";
            $bookingSettingKeys[] = "booking.custom_field_{$i}_required";
        }
        $db->table('settings')->whereIn('setting_key', $bookingSettingKeys)->delete();

        if ($this->appointmentIds !== []) {
            $db->table('appointments')->whereIn('id', $this->appointmentIds)->delete();
        }

        if ($this->customerId !== null) {
            $db->table('customers')->where('id', $this->customerId)->delete();
        }

        if (isset($this->serviceId)) {
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        if (isset($this->adminId)) {
            $db->table('users')->where('id', $this->adminId)->delete();
        }

        parent::tearDown();
    }

    public function testAdminCanCreateUpdateSearchAndViewCustomerHistory(): void
    {
        $email = 'customer-journey-' . uniqid('', true) . '@example.com';

        $this->primeCsrfCookie();

        $create = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/customer-management/store', [
                $this->csrfTokenName() => $this->csrfToken(),
                'first_name' => 'Casey',
                'last_name' => 'Journey',
                'email' => $email,
                'phone' => '+15550007777',
                'notes' => 'Created from customer management journey',
            ]);

        $create->assertOK();

        $createPayload = json_decode($create->getJSON(), true);
        $this->assertTrue((bool) ($createPayload['success'] ?? false), $create->getBody());
        $this->assertSame('Customer created successfully.', $createPayload['message'] ?? null);
        $this->assertStringContainsString('/customer-management', $createPayload['redirect'] ?? '');

        $db = \Config\Database::connect('tests');
        $customer = $db->table('customers')->where('email', $email)->get()->getRowArray();

        $this->assertNotNull($customer);
        $this->customerId = (int) $customer['id'];
        $this->customerHash = (string) ($customer['hash'] ?? '');
        if ($this->customerHash === '') {
            $this->markTestIncomplete('customers.hash is unavailable in the active test schema.');
        }
        $this->assertSame('Casey', $customer['first_name'] ?? null);

        $this->primeCsrfCookie();

        $update = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/customer-management/update/' . $this->customerHash, [
                $this->csrfTokenName() => $this->csrfToken(),
                'first_name' => 'Casey Updated',
                'last_name' => 'Journey',
                'email' => $email,
                'phone' => '+15550008888',
                'notes' => 'Updated from customer management journey',
            ]);

        $update->assertOK();

        $updatePayload = json_decode($update->getJSON(), true);
        $this->assertTrue((bool) ($updatePayload['success'] ?? false));
        $this->assertSame('Customer updated successfully.', $updatePayload['message'] ?? null);

        $updatedCustomer = $db->table('customers')->where('id', $this->customerId)->get()->getRowArray();
        $this->assertSame('Casey Updated', $updatedCustomer['first_name'] ?? null);
        $this->assertSame('+15550008888', $updatedCustomer['phone'] ?? null);

        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', uniqid('customer_journey_appointment_', true)),
            'start_at' => '2031-02-14 09:00:00',
            'end_at' => '2031-02-14 10:00:00',
            'status' => 'confirmed',
            'notes' => 'History fixture appointment',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->appointmentIds[] = (int) $db->insertID();

        $search = $this->withSession($this->adminSession())
            ->get('/customer-management/search?q=Casey&limit=5');

        $search->assertOK();

        $searchPayload = json_decode($search->getJSON(), true);
        $this->assertTrue((bool) ($searchPayload['success'] ?? false));
        $this->assertSame(1, $searchPayload['count'] ?? null);
        $this->assertSame('Casey Updated', $searchPayload['customers'][0]['first_name'] ?? null);

        $history = $this->withSession($this->adminSession())
            ->get('/customer-management/history/' . $this->customerHash);

        $history->assertOK();

        $body = $history->getBody();
        $this->assertStringContainsString('Casey Updated Journey', $body);
        $this->assertStringContainsString('Customer Strategy Session', $body);
        $this->assertStringContainsString('History Provider', $body);
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $bookingSettings = [
            ['setting_key' => 'booking.first_names_display', 'setting_value' => '1', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.first_names_required', 'setting_value' => '0', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.surname_display', 'setting_value' => '1', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.surname_required', 'setting_value' => '0', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.email_display', 'setting_value' => '1', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.email_required', 'setting_value' => '1', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.phone_display', 'setting_value' => '1', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.phone_required', 'setting_value' => '0', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.address_display', 'setting_value' => '0', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.address_required', 'setting_value' => '0', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.notes_display', 'setting_value' => '1', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'booking.notes_required', 'setting_value' => '0', 'setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
        ];
        for ($i = 1; $i <= 6; $i++) {
            $bookingSettings[] = [
                'setting_key' => "booking.custom_field_{$i}_enabled",
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $bookingSettings[] = [
                'setting_key' => "booking.custom_field_{$i}_required",
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $db->table('settings')->whereIn('setting_key', array_column($bookingSettings, 'setting_key'))->delete();
        $db->table('settings')->insertBatch($bookingSettings);

        $db->table('users')->insert([
            'name' => 'Customer Journey Admin',
            'email' => 'customer-journey-admin-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));
        $this->adminId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'History Provider',
            'email' => 'customer-journey-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));
        $this->providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Customer Strategy Session',
            'description' => 'Used by customer management journey coverage',
            'category_id' => null,
            'duration_min' => 60,
            'price' => 150.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Customer Journey Admin',
                'email' => 'customer-journey-admin@example.com',
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
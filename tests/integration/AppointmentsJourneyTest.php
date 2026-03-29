<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class AppointmentsJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $providerId;
    private int $serviceId;
    private array $appointmentIds = [];
    private array $customerIds = [];

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

        if ($this->appointmentIds !== []) {
            $db->table('notification_queue')->whereIn('appointment_id', $this->appointmentIds)->delete();
            if ($db->tableExists('notification_delivery_logs')) {
                $db->table('notification_delivery_logs')->whereIn('appointment_id', $this->appointmentIds)->delete();
            }
            $db->table('appointments')->whereIn('id', $this->appointmentIds)->delete();
        }

        if ($this->customerIds !== []) {
            $db->table('customers')->whereIn('id', $this->customerIds)->delete();
        }

        if (isset($this->serviceId)) {
            $db->table('services')->where('id', $this->serviceId)->delete();
        }

        if (isset($this->providerId)) {
            $db->table('business_hours')->where('provider_id', $this->providerId)->delete();
            $db->table('users')->where('id', $this->providerId)->delete();
        }

        if (isset($this->adminId)) {
            $db->table('users')->where('id', $this->adminId)->delete();
        }

        parent::tearDown();
    }

    public function testAdminCanCreateAndUpdateAppointmentThroughAjaxFormEndpoints(): void
    {
        $slotDate = date('Y-m-d', strtotime('+30 days'));
        $slotMinute = str_pad((string) random_int(0, 58), 2, '0', STR_PAD_LEFT);
        $slotTime = '10:' . $slotMinute;
        $updatedSlotTime = '11:' . $slotMinute;

        $this->primeCsrfCookie();

        $create = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/appointments/store', [
                $this->csrfTokenName() => $this->csrfToken(),
                'provider_id' => (string) $this->providerId,
                'service_id' => (string) $this->serviceId,
                'appointment_date' => $slotDate,
                'appointment_time' => $slotTime,
                'customer_first_name' => 'Journey',
                'customer_last_name' => 'Customer',
                'customer_email' => 'appointments-journey-' . uniqid('', true) . '@example.com',
                'customer_phone' => '+15551234567',
                'customer_address' => '100 Test Street',
                'notes' => 'Created from journey test',
                'client_timezone' => 'UTC',
                'client_offset' => '0',
                'custom_field_1' => 'Window seat',
            ]);

        $create->assertOK();

        $createPayload = json_decode($create->getJSON(), true);
        $appointmentId = (int) ($createPayload['appointmentId'] ?? 0);

        $this->assertTrue((bool) ($createPayload['success'] ?? false));
        $this->assertGreaterThan(0, $appointmentId);
        $this->assertStringContainsString('/appointments', $createPayload['redirect'] ?? '');
        $this->appointmentIds[] = $appointmentId;

        $db = \Config\Database::connect('tests');
        $appointment = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertNotNull($appointment);
        $this->assertSame('pending', $appointment['status'] ?? null);

        $customer = $db->table('customers')->where('email', $createPayload['success'] ? $this->extractCreatedEmail($create) : '')->get()->getRowArray();
        if ($customer !== null) {
            $this->customerIds[] = (int) $customer['id'];
        }

        $appointmentHash = (string) ($appointment['hash'] ?? '');
        if ($appointmentHash === '') {
            $this->markTestIncomplete('appointments.hash is unavailable in the active test schema.');
        }

        $this->primeCsrfCookie();

        $update = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/appointments/update/' . $appointmentHash, [
                $this->csrfTokenName() => $this->csrfToken(),
                '_method' => 'PUT',
                'provider_id' => (string) $this->providerId,
                'service_id' => (string) $this->serviceId,
                'appointment_date' => $slotDate,
                'appointment_time' => $updatedSlotTime,
                'status' => 'confirmed',
                'customer_first_name' => 'Journey Updated',
                'customer_last_name' => 'Customer',
                'customer_email' => $customer['email'] ?? $this->extractCreatedEmail($create),
                'customer_phone' => '+15557654321',
                'customer_address' => '101 Updated Street',
                'notes' => 'Updated from journey test',
                'client_timezone' => 'UTC',
                'client_offset' => '0',
                'custom_field_1' => 'Aisle seat',
            ]);

        $update->assertOK();

        $updatePayload = json_decode($update->getJSON(), true);
        $this->assertTrue((bool) ($updatePayload['success'] ?? false));
        $this->assertSame('Appointment updated successfully!', $updatePayload['message'] ?? null);

        $updatedAppointment = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertSame('confirmed', $updatedAppointment['status'] ?? null);
        $this->assertSame('Updated from journey test', $updatedAppointment['notes'] ?? null);

        $updatedCustomer = $db->table('customers')->where('id', $updatedAppointment['customer_id'])->get()->getRowArray();
        $this->assertNotNull($updatedCustomer);
        $this->customerIds[] = (int) $updatedCustomer['id'];
        $this->assertSame('Journey Updated', $updatedCustomer['first_name'] ?? null);
        $this->assertSame('+15557654321', $updatedCustomer['phone'] ?? null);
        $this->assertSame('{"custom_field_1":"Aisle seat"}', $updatedCustomer['custom_fields'] ?? null);
    }

    public function testStoreReturnsAjaxValidationErrorsForInvalidPayload(): void
    {
        $this->primeCsrfCookie();

        $response = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/appointments/store', [
                $this->csrfTokenName() => $this->csrfToken(),
                'provider_id' => '',
                'service_id' => '',
                'appointment_date' => '2031-01-06',
                'appointment_time' => '10:00',
                'client_timezone' => 'UTC',
                'client_offset' => '0',
            ]);

        $response->assertStatus(422);

        $payload = json_decode($response->getJSON(), true);
        $this->assertFalse((bool) ($payload['success'] ?? true));
        $this->assertSame('Validation failed', $payload['message'] ?? null);
        $this->assertArrayHasKey('provider_id', $payload['errors'] ?? []);
        $this->assertArrayHasKey('service_id', $payload['errors'] ?? []);
    }

    public function testNonAjaxStoreRedirectsAndPersistsAppointmentForValidPayload(): void
    {
        $email = 'appointments-journey-nonajax-' . uniqid('', true) . '@example.com';
        $slotDate = date('Y-m-d', strtotime('+31 days'));
        $slotMinute = str_pad((string) random_int(0, 58), 2, '0', STR_PAD_LEFT);
        $slotTime = '10:' . $slotMinute;

        $this->primeCsrfCookie();

        $response = $this->withSession($this->adminSession())
            ->post('/appointments/store', [
                $this->csrfTokenName() => $this->csrfToken(),
                'provider_id' => (string) $this->providerId,
                'service_id' => (string) $this->serviceId,
                'appointment_date' => $slotDate,
                'appointment_time' => $slotTime,
                'customer_first_name' => 'Non Ajax',
                'customer_last_name' => 'Customer',
                'customer_email' => $email,
                'customer_phone' => '+15550112233',
                'customer_address' => '200 Redirect Street',
                'notes' => 'Non-AJAX booking path',
                'client_timezone' => 'UTC',
                'client_offset' => '0',
            ]);

        $response->assertStatus(302);
        $response->assertRedirectTo('/appointments');

        $db = \Config\Database::connect('tests');
        $customer = $db->table('customers')->where('email', $email)->get()->getRowArray();
        $this->assertNotNull($customer);
        $this->customerIds[] = (int) $customer['id'];

        $appointment = $db->table('appointments')
            ->where('customer_id', $customer['id'])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($appointment);
        $this->appointmentIds[] = (int) $appointment['id'];
        $this->assertSame((string) $this->providerId, (string) ($appointment['provider_id'] ?? ''));
        $this->assertSame((string) $this->serviceId, (string) ($appointment['service_id'] ?? ''));
        $this->assertSame('pending', $appointment['status'] ?? null);
        $this->assertSame('Non-AJAX booking path', $appointment['notes'] ?? null);
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Appointments Journey Admin',
                'email' => 'appointments-journey-admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN' => $this->csrfToken(),
            'X-Client-Timezone' => 'UTC',
            'X-Client-Offset' => '0',
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

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Appointments Journey Admin',
            'email' => 'appointments-journey-admin-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));
        $this->adminId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'Appointments Journey Provider',
            'email' => 'appointments-journey-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));
        $this->providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Appointments Journey Service',
            'description' => 'Regression service for appointments web journey',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 125.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            $existingHours = $db->table('business_hours')
                ->where('provider_id', $this->providerId)
                ->where('weekday', $weekday)
                ->get()
                ->getRowArray();

            if ($existingHours === null) {
                $db->table('business_hours')->insert([
                    'provider_id' => $this->providerId,
                    'weekday' => $weekday,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'breaks_json' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->seedSetting($db, 'localization.timezone', 'UTC', 'string');
        $this->seedSetting($db, 'notifications.default_language', 'English', 'string');
    }

    private function seedSetting($db, string $key, string $value, string $type): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $db->table('settings')->where('setting_key', $key)->get()->getRowArray();

        $payload = [
            'setting_value' => $value,
            'setting_type' => $type,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            $db->table('settings')->where('setting_key', $key)->update($payload);
            return;
        }

        $db->table('settings')->insert($payload + [
            'setting_key' => $key,
            'created_at' => $now,
        ]);
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

    private function extractCreatedEmail($createResponse): string
    {
        $payload = json_decode($createResponse->getJSON(), true);
        $appointmentId = (int) ($payload['appointmentId'] ?? 0);
        if ($appointmentId <= 0) {
            return '';
        }

        $db = \Config\Database::connect('tests');
        $appointment = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        if ($appointment === null) {
            return '';
        }

        $customer = $db->table('customers')->where('id', $appointment['customer_id'] ?? 0)->get()->getRowArray();

        return (string) ($customer['email'] ?? '');
    }
}
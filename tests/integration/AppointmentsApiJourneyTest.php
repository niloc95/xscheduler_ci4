<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Controller-level regression coverage for the appointments API journey.
 */
final class AppointmentsApiJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

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
            $db->table('users')->where('id', $this->providerId)->delete();
            $db->table('business_hours')->where('provider_id', $this->providerId)->delete();
        }

        parent::tearDown();
    }

    public function testCreateThenMutateAppointmentThroughApi(): void
    {
        $create = $this->withBodyFormat('json')->post('/api/appointments', [
            'name' => 'Pat Doe',
            'email' => 'pat.doe.api.' . uniqid() . '@example.com',
            'phone' => '+15551230000',
            'providerId' => $this->providerId,
            'serviceId' => $this->serviceId,
            'date' => '2026-05-18',
            'start' => '10:00',
            'notes' => 'Initial API booking',
            'timezone' => 'UTC',
        ]);

        $create->assertStatus(201);

        $createPayload = json_decode($create->getJSON(), true);
        $appointmentId = (int) ($createPayload['data']['appointmentId'] ?? 0);

        $this->assertGreaterThan(0, $appointmentId);
        $this->appointmentIds[] = $appointmentId;

        $show = $this->get('/api/appointments/' . $appointmentId);
        $show->assertOK();

        $showPayload = json_decode($show->getJSON(), true);
        $showData = $showPayload['data'] ?? [];

        $this->assertSame($appointmentId, (int) ($showData['id'] ?? 0));
        $this->assertSame('pending', $showData['status'] ?? null);
        $this->assertSame('Initial API booking', $showData['notes'] ?? null);
        $this->assertSame('Pat Doe', trim((string) ($showData['customer_name'] ?? '')));

        $notes = $this->withBodyFormat('json')->patch('/api/appointments/' . $appointmentId . '/notes', [
            'notes' => 'Updated from API notes endpoint',
        ]);

        $notes->assertOK();

        $notesPayload = json_decode($notes->getJSON(), true);
        $this->assertSame($appointmentId, (int) ($notesPayload['data']['id'] ?? 0));
        $this->assertSame('Updated from API notes endpoint', $notesPayload['data']['notes'] ?? null);
        $this->assertSame('Appointment notes updated successfully', $notesPayload['meta']['message'] ?? null);

        $status = $this->withBodyFormat('json')->patch('/api/appointments/' . $appointmentId . '/status', [
            'status' => 'confirmed',
        ]);

        $status->assertOK();

        $statusPayload = json_decode($status->getJSON(), true);
        $this->assertSame($appointmentId, (int) ($statusPayload['data']['id'] ?? 0));
        $this->assertSame('confirmed', $statusPayload['data']['status'] ?? null);
        $this->assertSame('Appointment status updated successfully', $statusPayload['meta']['message'] ?? null);

        $finalShow = $this->get('/api/appointments/' . $appointmentId);
        $finalShow->assertOK();

        $finalPayload = json_decode($finalShow->getJSON(), true);
        $finalData = $finalPayload['data'] ?? [];

        $this->assertSame('confirmed', $finalData['status'] ?? null);
        $this->assertSame('Updated from API notes endpoint', $finalData['notes'] ?? null);

        $db = \Config\Database::connect('tests');
        $createdAppointment = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertSame('confirmed', $createdAppointment['status'] ?? null);
        $this->assertSame('Updated from API notes endpoint', $createdAppointment['notes'] ?? null);

        $customer = $db->table('customers')->where('email', $showData['customer_email'] ?? '')->get()->getRowArray();
        $this->assertNotNull($customer);
        $this->customerIds[] = (int) $customer['id'];
    }

    public function testDuplicateBookingConflictAndInvalidStatusAreReturnedWithStableApiContracts(): void
    {
        $email = 'pat.doe.conflict.' . uniqid() . '@example.com';

        $create = $this->withBodyFormat('json')->post('/api/appointments', [
            'name' => 'Pat Conflict',
            'email' => $email,
            'phone' => '+15551230001',
            'providerId' => $this->providerId,
            'serviceId' => $this->serviceId,
            'date' => '2026-05-18',
            'start' => '11:00',
            'notes' => 'Original booking for conflict test',
            'timezone' => 'UTC',
        ]);

        $create->assertStatus(201);

        $createPayload = json_decode($create->getJSON(), true);
        $appointmentId = (int) ($createPayload['data']['appointmentId'] ?? 0);

        $this->assertGreaterThan(0, $appointmentId);
        $this->appointmentIds[] = $appointmentId;

        $duplicate = $this->withBodyFormat('json')->post('/api/appointments', [
            'name' => 'Pat Conflict Duplicate',
            'email' => 'pat.doe.conflict.duplicate.' . uniqid() . '@example.com',
            'phone' => '+15551230002',
            'providerId' => $this->providerId,
            'serviceId' => $this->serviceId,
            'date' => '2026-05-18',
            'start' => '11:00',
            'notes' => 'Duplicate booking attempt',
            'timezone' => 'UTC',
        ]);

        $duplicate->assertStatus(409);

        $duplicatePayload = json_decode($duplicate->getJSON(), true);
        $duplicateError = $duplicatePayload['error'] ?? [];

        $this->assertSame('CONFLICT', $duplicateError['code'] ?? null);
        $this->assertSame('Conflicts with 1 existing appointment(s)', $duplicateError['message'] ?? null);
        $this->assertSame([], $duplicateError['details'] ?? null);

        $invalidStatus = $this->withBodyFormat('json')->patch('/api/appointments/' . $appointmentId . '/status', [
            'status' => 'archived',
        ]);

        $invalidStatus->assertStatus(400);

        $invalidStatusPayload = json_decode($invalidStatus->getJSON(), true);
        $invalidStatusError = $invalidStatusPayload['error'] ?? [];

        $this->assertSame('BAD_REQUEST', $invalidStatusError['code'] ?? null);
        $this->assertSame('Invalid status', $invalidStatusError['message'] ?? null);
        $this->assertSame(
            ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'],
            $invalidStatusError['details']['valid_statuses'] ?? null
        );

        $db = \Config\Database::connect('tests');
        $appointment = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertSame('pending', $appointment['status'] ?? null);

        $customer = $db->table('customers')->where('email', $email)->get()->getRowArray();
        $this->assertNotNull($customer);
        $this->customerIds[] = (int) $customer['id'];
    }

    public function testRescheduleConflictReturnsUnprocessableAndLeavesOriginalSlotIntact(): void
    {
        $firstEmail = 'pat.reschedule.first.' . uniqid() . '@example.com';
        $secondEmail = 'pat.reschedule.second.' . uniqid() . '@example.com';

        $firstCreate = $this->withBodyFormat('json')->post('/api/appointments', [
            'name' => 'Pat Reschedule First',
            'email' => $firstEmail,
            'phone' => '+15551230003',
            'providerId' => $this->providerId,
            'serviceId' => $this->serviceId,
            'date' => '2026-05-18',
            'start' => '13:00',
            'notes' => 'First appointment before reschedule attempt',
            'timezone' => 'UTC',
        ]);

        $firstCreate->assertStatus(201);

        $firstPayload = json_decode($firstCreate->getJSON(), true);
        $firstAppointmentId = (int) ($firstPayload['data']['appointmentId'] ?? 0);
        $this->assertGreaterThan(0, $firstAppointmentId);
        $this->appointmentIds[] = $firstAppointmentId;

        $secondCreate = $this->withBodyFormat('json')->post('/api/appointments', [
            'name' => 'Pat Reschedule Second',
            'email' => $secondEmail,
            'phone' => '+15551230004',
            'providerId' => $this->providerId,
            'serviceId' => $this->serviceId,
            'date' => '2026-05-18',
            'start' => '14:00',
            'notes' => 'Blocking appointment for reschedule conflict',
            'timezone' => 'UTC',
        ]);

        $secondCreate->assertStatus(201);

        $secondPayload = json_decode($secondCreate->getJSON(), true);
        $secondAppointmentId = (int) ($secondPayload['data']['appointmentId'] ?? 0);
        $this->assertGreaterThan(0, $secondAppointmentId);
        $this->appointmentIds[] = $secondAppointmentId;

        $reschedule = $this->withBodyFormat('json')->patch('/api/appointments/' . $firstAppointmentId, [
            'date' => '2026-05-18',
            'start' => '14:00',
            'timezone' => 'UTC',
        ]);

        $reschedule->assertStatus(422);

        $reschedulePayload = json_decode($reschedule->getJSON(), true);
        $rescheduleError = $reschedulePayload['error'] ?? [];

        $this->assertSame('UNPROCESSABLE_ENTITY', $rescheduleError['code'] ?? null);
        $this->assertSame('Conflicts with 1 existing appointment(s)', $rescheduleError['message'] ?? null);
        $this->assertSame([], $rescheduleError['details'] ?? null);

        $db = \Config\Database::connect('tests');
        $firstAppointment = $db->table('appointments')->where('id', $firstAppointmentId)->get()->getRowArray();
        $secondAppointment = $db->table('appointments')->where('id', $secondAppointmentId)->get()->getRowArray();

        $this->assertSame('2026-05-18 13:00:00', $firstAppointment['start_at'] ?? null);
        $this->assertSame('2026-05-18 13:30:00', $firstAppointment['end_at'] ?? null);
        $this->assertSame('First appointment before reschedule attempt', $firstAppointment['notes'] ?? null);
        $this->assertSame('2026-05-18 14:00:00', $secondAppointment['start_at'] ?? null);
        $this->assertSame('2026-05-18 14:30:00', $secondAppointment['end_at'] ?? null);

        $firstCustomer = $db->table('customers')->where('email', $firstEmail)->get()->getRowArray();
        $secondCustomer = $db->table('customers')->where('email', $secondEmail)->get()->getRowArray();
        $this->assertNotNull($firstCustomer);
        $this->assertNotNull($secondCustomer);
        $this->customerIds[] = (int) $firstCustomer['id'];
        $this->customerIds[] = (int) $secondCustomer['id'];
    }

    public function testDeleteCancelsAppointmentAndMissingAppointmentReturnsNotFound(): void
    {
        $email = 'pat.delete.' . uniqid() . '@example.com';

        $create = $this->withBodyFormat('json')->post('/api/appointments', [
            'name' => 'Pat Delete',
            'email' => $email,
            'phone' => '+15551230005',
            'providerId' => $this->providerId,
            'serviceId' => $this->serviceId,
            'date' => '2026-05-18',
            'start' => '15:00',
            'notes' => 'Appointment to cancel through API delete',
            'timezone' => 'UTC',
        ]);

        $create->assertStatus(201);

        $createPayload = json_decode($create->getJSON(), true);
        $appointmentId = (int) ($createPayload['data']['appointmentId'] ?? 0);
        $this->assertGreaterThan(0, $appointmentId);
        $this->appointmentIds[] = $appointmentId;

        $delete = $this->delete('/api/appointments/' . $appointmentId);
        $delete->assertOK();

        $deletePayload = json_decode($delete->getJSON(), true);
        $this->assertSame(['ok' => true], $deletePayload['data'] ?? null);
        $this->assertSame('Appointment cancelled successfully', $deletePayload['meta']['message'] ?? null);

        $db = \Config\Database::connect('tests');
        $appointment = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertSame('cancelled', $appointment['status'] ?? null);
        $this->assertSame('Appointment to cancel through API delete', $appointment['notes'] ?? null);

        $show = $this->get('/api/appointments/' . $appointmentId);
        $show->assertOK();

        $showPayload = json_decode($show->getJSON(), true);
        $this->assertSame('cancelled', $showPayload['data']['status'] ?? null);

        $missingDelete = $this->delete('/api/appointments/999999');
        $missingDelete->assertStatus(404);

        $missingPayload = json_decode($missingDelete->getJSON(), true);
        $missingError = $missingPayload['error'] ?? [];
        $this->assertSame('NOT_FOUND', $missingError['code'] ?? null);
        $this->assertSame('Appointment not found', $missingError['message'] ?? null);
        $this->assertSame(['appointment_id' => 999999], $missingError['details'] ?? null);

        $customer = $db->table('customers')->where('email', $email)->get()->getRowArray();
        $this->assertNotNull($customer);
        $this->customerIds[] = (int) $customer['id'];
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $providerEmail = 'appointments-api-provider-' . uniqid('', true) . '@example.com';
        $db->table('users')->insert([
            'name' => 'Appointments API Provider',
            'email' => $providerEmail,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Appointments API Service',
            'description' => 'Regression service for appointments API journey',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 100.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();

        $weekday = 1;
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
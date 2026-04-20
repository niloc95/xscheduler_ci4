<?php

namespace Tests\Unit\Services;

use App\Services\AppointmentBookingService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AppointmentRescheduleNotificationTest extends CIUnitTestCase
{
    private array $userIds = [];
    private array $customerIds = [];
    private array $serviceIds = [];
    private array $appointmentIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTestingDatabaseEnvironment();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect();

        if ($this->appointmentIds !== []) {
            $db->table('notification_queue')->whereIn('appointment_id', $this->appointmentIds)->delete();
            $db->table('appointments')->whereIn('id', $this->appointmentIds)->delete();
        }

        if ($this->serviceIds !== []) {
            $db->table('services')->whereIn('id', $this->serviceIds)->delete();
        }

        if ($this->customerIds !== []) {
            $db->table('customers')->whereIn('id', $this->customerIds)->delete();
        }

        if ($this->userIds !== []) {
            $db->table('provider_staff_assignments')
                ->whereIn('provider_id', $this->userIds)
                ->orWhereIn('staff_id', $this->userIds)
                ->delete();

            $db->table('users')->whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    public function testRescheduleEventQueuesInternalRecipients(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $providerId = $this->insertUser('provider', 1, $now);
        $staffId = $this->insertUser('staff', 1, $now);

        $db->table('provider_staff_assignments')->insert([
            'provider_id' => $providerId,
            'staff_id' => $staffId,
            'assigned_by' => null,
            'status' => 'active',
            'assigned_at' => $now,
        ]);

        $serviceId = $this->insertService($now);
        $customerId = $this->insertCustomer($now);
        $appointmentId = $this->insertAppointment($customerId, $providerId, $serviceId, $now);

        $service = new AppointmentBookingServiceHarness();
        $service->triggerQueue($appointmentId, 'appointment_rescheduled');

        $internalRows = $db->table('notification_queue')
            ->select('recipient_user_id, recipient_type, event_type')
            ->where('appointment_id', $appointmentId)
            ->where('recipient_type', 'internal')
            ->where('event_type', 'appointment_rescheduled')
            ->get()
            ->getResultArray();

        $recipientIds = array_map(static fn(array $row): int => (int) ($row['recipient_user_id'] ?? 0), $internalRows);

        $this->assertGreaterThanOrEqual(2, count($internalRows), 'Expected internal queue entries for provider and assigned staff.');
        $this->assertContains($providerId, $recipientIds);
        $this->assertContains($staffId, $recipientIds);
    }

    private function insertUser(string $role, int $notifyOnAppointments, string $now): int
    {
        $db = \Config\Database::connect();

        $db->table('users')->insert([
            'name' => ucfirst($role) . ' Reschedule ' . uniqid('', true),
            'email' => $role . '-reschedule-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => $role,
            'status' => 'active',
            'is_active' => 1,
            'notify_on_appointments' => $notifyOnAppointments,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $db->insertID();
        $this->userIds[] = $id;

        return $id;
    }

    private function insertService(string $now): int
    {
        $db = \Config\Database::connect();

        $db->table('services')->insert([
            'name' => 'Reschedule Service ' . uniqid('', true),
            'description' => 'Reschedule notification regression fixture',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 75.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $db->insertID();
        $this->serviceIds[] = $id;

        return $id;
    }

    private function insertCustomer(string $now): int
    {
        $db = \Config\Database::connect();

        $db->table('customers')->insert([
            'first_name' => 'Reschedule',
            'last_name' => 'Customer',
            'email' => 'reschedule-customer-' . uniqid('', true) . '@example.com',
            'phone' => '+15551112222',
            'hash' => hash('sha256', uniqid('reschedule_customer_', true)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $db->insertID();
        $this->customerIds[] = $id;

        return $id;
    }

    private function insertAppointment(int $customerId, int $providerId, int $serviceId, string $now): int
    {
        $db = \Config\Database::connect();
        $start = new \DateTimeImmutable('+2 days', new \DateTimeZone('UTC'));
        $end = $start->modify('+30 minutes');

        $db->table('appointments')->insert([
            'customer_id' => $customerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'hash' => hash('sha256', uniqid('reschedule_appointment_', true)),
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $end->format('Y-m-d H:i:s'),
            'status' => 'confirmed',
            'notes' => 'Reschedule queue regression fixture',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $db->insertID();
        $this->appointmentIds[] = $id;

        return $id;
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

final class AppointmentBookingServiceHarness extends AppointmentBookingService
{
    public function triggerQueue(int $appointmentId, string $event): void
    {
        $this->queueNotifications($appointmentId, ['email'], $event);
    }
}

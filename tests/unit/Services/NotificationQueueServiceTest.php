<?php

namespace Tests\Unit\Services;

use App\Services\NotificationQueueService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationQueueServiceTest extends CIUnitTestCase
{
    private array $appointmentIds = [];
    private array $customerIds = [];
    private array $providerIds = [];
    private array $serviceIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if ($this->appointmentIds !== []) {
            $db->table('notification_queue')->whereIn('appointment_id', $this->appointmentIds)->delete();
            $db->table('appointments')->whereIn('id', $this->appointmentIds)->delete();
        }

        $db->table('business_notification_rules')->where('business_id', 1)->delete();
        $db->table('business_integrations')->where('business_id', 1)->delete();

        if ($this->customerIds !== []) {
            $db->table('customers')->whereIn('id', $this->customerIds)->delete();
        }

        if ($this->serviceIds !== []) {
            $db->table('services')->whereIn('id', $this->serviceIds)->delete();
        }

        if ($this->providerIds !== []) {
            $db->table('users')->whereIn('id', $this->providerIds)->delete();
        }

        parent::tearDown();
    }

    public function testEnqueueAppointmentEventDeduplicatesByIdempotencyKey(): void
    {
        $appointmentId = $this->seedAppointment('+2 days');
        $service = new NotificationQueueService();

        $first = $service->enqueueAppointmentEvent(1, 'email', 'appointment_confirmed', $appointmentId);
        $second = $service->enqueueAppointmentEvent(1, 'email', 'appointment_confirmed', $appointmentId);

        $count = \Config\Database::connect('tests')
            ->table('notification_queue')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_confirmed')
            ->countAllResults();

        $this->assertTrue($first['ok']);
        $this->assertTrue((bool) ($first['inserted'] ?? false));
        $this->assertTrue($second['ok']);
        $this->assertFalse((bool) ($second['inserted'] ?? true));
        $this->assertSame(1, $count);
    }

    public function testEnqueueAppointmentRescheduledAllowsNewQueueEntryAfterTimeChanges(): void
    {
        $appointmentId = $this->seedAppointment('+2 days');
        $service = new NotificationQueueService();
        $db = \Config\Database::connect('tests');

        $first = $service->enqueueAppointmentEvent(1, 'email', 'appointment_rescheduled', $appointmentId);
        $second = $service->enqueueAppointmentEvent(1, 'email', 'appointment_rescheduled', $appointmentId);

        $baseCount = $db->table('notification_queue')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_rescheduled')
            ->countAllResults();

        $existing = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertIsArray($existing);

        $startAt = new \DateTimeImmutable((string) ($existing['start_at'] ?? ''), new \DateTimeZone('UTC'));
        $endAt = new \DateTimeImmutable((string) ($existing['end_at'] ?? ''), new \DateTimeZone('UTC'));

        $db->table('appointments')
            ->where('id', $appointmentId)
            ->update([
                'start_at' => $startAt->modify('+1 day')->format('Y-m-d H:i:s'),
                'end_at' => $endAt->modify('+1 day')->format('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s', time() + 2),
            ]);

        $third = $service->enqueueAppointmentEvent(1, 'email', 'appointment_rescheduled', $appointmentId);

        $finalCount = $db->table('notification_queue')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_rescheduled')
            ->countAllResults();

        $this->assertTrue($first['ok']);
        $this->assertTrue((bool) ($first['inserted'] ?? false));
        $this->assertTrue($second['ok']);
        $this->assertFalse((bool) ($second['inserted'] ?? true));
        $this->assertSame(1, $baseCount);
        $this->assertTrue($third['ok']);
        $this->assertTrue((bool) ($third['inserted'] ?? false));
        $this->assertSame(2, $finalCount);
    }

    public function testEnqueueInternalRescheduledAllowsNewQueueEntryAfterTimeChanges(): void
    {
        $appointmentId = $this->seedAppointment('+2 days');
        $service = new NotificationQueueService();
        $db = \Config\Database::connect('tests');
        $recipientUserId = $this->providerIds[count($this->providerIds) - 1] ?? 0;

        $first = $service->enqueueInternalEvent(1, 'email', 'appointment_rescheduled', $appointmentId, (int) $recipientUserId);
        $second = $service->enqueueInternalEvent(1, 'email', 'appointment_rescheduled', $appointmentId, (int) $recipientUserId);

        $baseCount = $db->table('notification_queue')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_rescheduled')
            ->where('recipient_type', 'internal')
            ->where('recipient_user_id', (int) $recipientUserId)
            ->countAllResults();

        $existing = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertIsArray($existing);

        $startAt = new \DateTimeImmutable((string) ($existing['start_at'] ?? ''), new \DateTimeZone('UTC'));
        $endAt = new \DateTimeImmutable((string) ($existing['end_at'] ?? ''), new \DateTimeZone('UTC'));

        $db->table('appointments')
            ->where('id', $appointmentId)
            ->update([
                'start_at' => $startAt->modify('+1 day')->format('Y-m-d H:i:s'),
                'end_at' => $endAt->modify('+1 day')->format('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s', time() + 2),
            ]);

        $third = $service->enqueueInternalEvent(1, 'email', 'appointment_rescheduled', $appointmentId, (int) $recipientUserId);

        $finalCount = $db->table('notification_queue')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_rescheduled')
            ->where('recipient_type', 'internal')
            ->where('recipient_user_id', (int) $recipientUserId)
            ->countAllResults();

        $this->assertTrue($first['ok']);
        $this->assertTrue((bool) ($first['inserted'] ?? false));
        $this->assertTrue($second['ok']);
        $this->assertFalse((bool) ($second['inserted'] ?? true));
        $this->assertSame(1, $baseCount);
        $this->assertTrue($third['ok']);
        $this->assertTrue((bool) ($third['inserted'] ?? false));
        $this->assertSame(2, $finalCount);
    }

    public function testEnqueueDueRemindersQueuesOnlyDueAppointmentsForActiveChannels(): void
    {
        // Booked a week ago → the 60-min reminder becoming due now is a legitimate
        // catch-up (dueAt >= created_at), not an immediate post-booking echo.
        $dueAppointmentId = $this->seedAppointment('+30 minutes', '-7 days');
        $laterAppointmentId = $this->seedAppointment('+3 hours');
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('business_notification_rules')->insert([
            'business_id' => 1,
            'event_type' => 'appointment_reminder',
            'channel' => 'email',
            'is_enabled' => 1,
            'reminder_offset_minutes' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $db->table('business_notification_rules')->insert([
            'business_id' => 1,
            'event_type' => 'appointment_reminder',
            'channel' => 'sms',
            'is_enabled' => 1,
            'reminder_offset_minutes' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('business_integrations')->insert([
            'business_id' => 1,
            'channel' => 'email',
            'provider_name' => 'smtp',
            'encrypted_config' => 'configured',
            'is_active' => 1,
            'health_status' => 'ok',
            'last_tested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = new NotificationQueueService();

        $stats = $service->enqueueDueReminders();

        $rows = $db->table('notification_queue')
            ->select('appointment_id, channel, event_type')
            ->where('event_type', 'appointment_reminder')
            ->where('recipient_type', 'customer')
            ->whereIn('appointment_id', [$dueAppointmentId, $laterAppointmentId])
            ->orderBy('appointment_id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertGreaterThanOrEqual(2, $stats['scanned']);
        $this->assertSame(1, $stats['enqueued']);
        $this->assertGreaterThanOrEqual(1, $stats['skipped']);
        $this->assertSame([
            [
                'appointment_id' => $dueAppointmentId,
                'channel' => 'email',
                'event_type' => 'appointment_reminder',
            ],
        ], array_map(static fn(array $row): array => [
            'appointment_id' => (int) $row['appointment_id'],
            'channel' => (string) $row['channel'],
            'event_type' => (string) $row['event_type'],
        ], $rows));
        $this->assertNotSame($dueAppointmentId, $laterAppointmentId);
    }

    public function testEnqueueDueRemindersSupportsMultipleOffsetsPerChannel(): void
    {
        $db = \Config\Database::connect('tests');
        $ruleFields = $db->getFieldNames('business_notification_rules');
        if (!in_array('reminder_offsets_json', $ruleFields, true)) {
            $this->markTestSkipped('reminder_offsets_json column is not available in test schema.');
        }

        // Booked 7 days ago → both the 3-day (4320) and 60-min offset windows fall
        // after creation, so both are legitimate catch-ups rather than post-booking echoes.
        $appointmentId = $this->seedAppointment('+30 minutes', '-7 days');
        $now = date('Y-m-d H:i:s');

        $db->table('business_notification_rules')->insert([
            'business_id' => 1,
            'event_type' => 'appointment_reminder',
            'channel' => 'email',
            'is_enabled' => 1,
            'reminder_offset_minutes' => 60,
            'reminder_offsets_json' => json_encode([4320, 60]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('business_integrations')->insert([
            'business_id' => 1,
            'channel' => 'email',
            'provider_name' => 'smtp',
            'encrypted_config' => 'configured',
            'is_active' => 1,
            'health_status' => 'ok',
            'last_tested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = new NotificationQueueService();
        $stats = $service->enqueueDueReminders();

        $rows = $db->table('notification_queue')
            ->select('idempotency_key, recipient_type, channel, event_type')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_reminder')
            ->where('recipient_type', 'customer')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertGreaterThanOrEqual(1, $stats['scanned']);
        $this->assertSame(2, (int) ($stats['enqueued'] ?? 0));
        $this->assertCount(2, $rows);

        // Idempotency keys carry a trailing ':sch:{schedule_fingerprint}' segment; strip it so
        // the assertion targets the stable offset identity rather than the live fingerprint.
        $keys = array_column($rows, 'idempotency_key');
        $baseKeys = array_map(
            static fn(string $k): string => preg_replace('/:sch:[0-9a-f]+$/', '', $k),
            $keys
        );
        $this->assertContains('email:appointment_reminder:appt:' . $appointmentId . ':off:offset:4320', $baseKeys);
        $this->assertContains('email:appointment_reminder:appt:' . $appointmentId . ':off:offset:60', $baseKeys);
    }

    /**
     * A freshly-booked appointment whose reminder offset windows already elapsed
     * before the booking existed must NOT fire those reminders immediately — they
     * would just echo the confirmation. Regression guard for the "second email sent
     * immediately after booking" report.
     */
    public function testEnqueueDueRemindersSuppressesOffsetWindowsThatPassedBeforeBooking(): void
    {
        $db = \Config\Database::connect('tests');
        $ruleFields = $db->getFieldNames('business_notification_rules');
        if (!in_array('reminder_offsets_json', $ruleFields, true)) {
            $this->markTestSkipped('reminder_offsets_json column is not available in test schema.');
        }

        // Booked NOW, starting in 30 minutes. Both the 3-day (4320) and 60-min offset
        // windows are already in the past relative to created_at → both must be suppressed.
        $appointmentId = $this->seedAppointment('+30 minutes');
        $now = date('Y-m-d H:i:s');

        $db->table('business_notification_rules')->insert([
            'business_id' => 1,
            'event_type' => 'appointment_reminder',
            'channel' => 'email',
            'is_enabled' => 1,
            'reminder_offset_minutes' => 60,
            'reminder_offsets_json' => json_encode([4320, 60]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('business_integrations')->insert([
            'business_id' => 1,
            'channel' => 'email',
            'provider_name' => 'smtp',
            'encrypted_config' => 'configured',
            'is_active' => 1,
            'health_status' => 'ok',
            'last_tested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = new NotificationQueueService();
        $stats = $service->enqueueDueReminders();

        $rows = $db->table('notification_queue')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_reminder')
            ->get()
            ->getResultArray();

        $this->assertSame(0, (int) ($stats['enqueued'] ?? 0));
        $this->assertCount(0, $rows);
    }

    public function testEnqueueDueRemindersIgnoresReminderSentFlagForQueueControl(): void
    {
        $db = \Config\Database::connect('tests');
        // Booked a week ago so the due 60-min reminder is a legitimate catch-up.
        $appointmentId = $this->seedAppointment('+30 minutes', '-7 days');
        $now = date('Y-m-d H:i:s');

        $appointmentFields = $db->getFieldNames('appointments');
        if (!in_array('reminder_sent', $appointmentFields, true)) {
            $this->markTestSkipped('reminder_sent column is not available in test schema.');
        }

        $db->table('appointments')
            ->where('id', $appointmentId)
            ->update([
                'reminder_sent' => 1,
                'updated_at' => $now,
            ]);

        $db->table('business_notification_rules')->insert([
            'business_id' => 1,
            'event_type' => 'appointment_reminder',
            'channel' => 'email',
            'is_enabled' => 1,
            'reminder_offset_minutes' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('business_integrations')->insert([
            'business_id' => 1,
            'channel' => 'email',
            'provider_name' => 'smtp',
            'encrypted_config' => 'configured',
            'is_active' => 1,
            'health_status' => 'ok',
            'last_tested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = new NotificationQueueService();
        $stats = $service->enqueueDueReminders();

        $rows = $db->table('notification_queue')
            ->select('appointment_id, event_type, recipient_type')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_reminder')
            ->where('recipient_type', 'customer')
            ->get()
            ->getResultArray();

        $this->assertGreaterThanOrEqual(1, $stats['scanned']);
        $this->assertCount(1, $rows);
        $this->assertSame($appointmentId, (int) $rows[0]['appointment_id']);
    }

    public function testEnqueueDueRemindersCreatesNewIdentityAfterScheduleChanges(): void
    {
        $db = \Config\Database::connect('tests');
        // Booked a week ago so the due 60-min reminder is a legitimate catch-up.
        $appointmentId = $this->seedAppointment('+30 minutes', '-7 days');
        $now = date('Y-m-d H:i:s');

        $db->table('business_notification_rules')->insert([
            'business_id' => 1,
            'event_type' => 'appointment_reminder',
            'channel' => 'email',
            'is_enabled' => 1,
            'reminder_offset_minutes' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('business_integrations')->insert([
            'business_id' => 1,
            'channel' => 'email',
            'provider_name' => 'smtp',
            'encrypted_config' => 'configured',
            'is_active' => 1,
            'health_status' => 'ok',
            'last_tested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = new NotificationQueueService();

        $first = $service->enqueueDueReminders();

        $existing = $db->table('appointments')->where('id', $appointmentId)->get()->getRowArray();
        $this->assertIsArray($existing);

        $startAt = new \DateTimeImmutable((string) ($existing['start_at'] ?? ''), new \DateTimeZone('UTC'));
        $endAt = new \DateTimeImmutable((string) ($existing['end_at'] ?? ''), new \DateTimeZone('UTC'));

        $db->table('appointments')
            ->where('id', $appointmentId)
            ->update([
                'start_at' => $startAt->modify('+5 minutes')->format('Y-m-d H:i:s'),
                'end_at' => $endAt->modify('+5 minutes')->format('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s', strtotime($now) + 5),
            ]);

        $second = $service->enqueueDueReminders();

        $rows = $db->table('notification_queue')
            ->select('idempotency_key')
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_reminder')
            ->where('recipient_type', 'customer')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame(1, (int) ($first['enqueued'] ?? 0));
        $this->assertSame(1, (int) ($second['enqueued'] ?? 0));
        $this->assertCount(2, $rows);
        $this->assertCount(2, array_unique(array_column($rows, 'idempotency_key')));
    }

    /**
     * @param string      $relativeStart   strtotime-relative start_at (e.g. '+30 minutes')
     * @param string|null $relativeCreated  strtotime-relative created_at; defaults to now.
     *                                       Pass a past value (e.g. '-7 days') to simulate a
     *                                       booking whose reminder offset windows are legitimate
     *                                       catch-ups rather than immediate post-booking echoes.
     */
    private function seedAppointment(string $relativeStart, ?string $relativeCreated = null): int
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $appointmentCreatedAt = $relativeCreated !== null
            ? (new \DateTimeImmutable($relativeCreated, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')
            : $now;

        $db->table('users')->insert([
            'name' => 'Queue Service Provider ' . uniqid('', true),
            'email' => 'queue-provider-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $providerId = (int) $db->insertID();
        $this->providerIds[] = $providerId;

        $db->table('services')->insert([
            'name' => 'Queue Service Item ' . uniqid('', true),
            'description' => 'Notification queue direct regression fixture',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 75.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $serviceId = (int) $db->insertID();
        $this->serviceIds[] = $serviceId;

        $db->table('customers')->insert([
            'first_name' => 'Queue',
            'last_name' => 'Customer',
            'email' => 'queue-customer-' . uniqid('', true) . '@example.com',
            'phone' => '+15550008888',
            'hash' => hash('sha256', uniqid('queue_customer_', true)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();
        $this->customerIds[] = $customerId;

        $startAt = new \DateTimeImmutable($relativeStart, new \DateTimeZone('UTC'));
        $endAt = $startAt->modify('+30 minutes');

        $db->table('appointments')->insert([
            'customer_id' => $customerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'hash' => hash('sha256', uniqid('queue_appointment_', true)),
            'start_at' => $startAt->format('Y-m-d H:i:s'),
            'end_at' => $endAt->format('Y-m-d H:i:s'),
            'status' => 'confirmed',
            'notes' => 'Notification queue regression fixture',
            'created_at' => $appointmentCreatedAt,
            'updated_at' => $now,
        ]);

        $appointmentId = (int) $db->insertID();
        $this->appointmentIds[] = $appointmentId;

        return $appointmentId;
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
    }
}
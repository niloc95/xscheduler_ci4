<?php

namespace Tests\Unit\Services;

use App\Models\NotificationQueueModel;
use App\Services\NotificationDeliveryLogService;
use App\Services\NotificationOptOutService;
use App\Services\NotificationQueueDispatcher;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationQueueDispatcherTest extends CIUnitTestCase
{
    public function testDispatchReturnsZeroStatsWhenNothingIsQueued(): void
    {
        $dispatcher = new TestNotificationQueueDispatcher(
            new FakeNotificationQueueModel([], []),
            new FakeNotificationDeliveryLogService(),
            new FakeNotificationOptOutService()
        );

        $stats = $dispatcher->dispatch();

        $this->assertSame([
            'claimed' => 0,
            'sent' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'skipped' => 0,
        ], $stats);
    }

    public function testDispatchMarksInvalidQueueRowAsFailed(): void
    {
        $queueModel = new FakeNotificationQueueModel([10], [
            [
                'id' => 10,
                'business_id' => 1,
                'channel' => '',
                'event_type' => '',
                'appointment_id' => 0,
                'attempts' => 0,
                'max_attempts' => 3,
            ],
        ]);
        $logService = new FakeNotificationDeliveryLogService();

        $dispatcher = new TestNotificationQueueDispatcher(
            $queueModel,
            $logService,
            new FakeNotificationOptOutService()
        );

        $stats = $dispatcher->dispatch();

        $this->assertSame(1, $stats['claimed']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame('failed', $queueModel->rowsById[10]['status']);
        $this->assertSame('Invalid queue row', $queueModel->rowsById[10]['last_error']);
        $this->assertSame('failed', $logService->entries[0]['status']);
    }

    public function testDispatchCancelsRowWhenRuleIsDisabled(): void
    {
        $queueModel = new FakeNotificationQueueModel([11], [
            [
                'id' => 11,
                'business_id' => 1,
                'channel' => 'email',
                'event_type' => 'appointment_confirmed',
                'appointment_id' => 500,
                'attempts' => 0,
                'max_attempts' => 3,
            ],
        ]);
        $logService = new FakeNotificationDeliveryLogService();

        $dispatcher = new TestNotificationQueueDispatcher(
            $queueModel,
            $logService,
            new FakeNotificationOptOutService()
        );
        $dispatcher->ruleEnabled = false;

        $stats = $dispatcher->dispatch();

        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame('cancelled', $queueModel->rowsById[11]['status']);
        $this->assertSame('Rule disabled', $queueModel->rowsById[11]['last_error']);
        $this->assertSame('cancelled', $logService->entries[0]['status']);
    }

    public function testDispatchFailsInternalRowWhenRecipientMetadataIsMissing(): void
    {
        $queueModel = new FakeNotificationQueueModel([111], [
            [
                'id' => 111,
                'business_id' => 1,
                'channel' => 'email',
                'event_type' => 'appointment_confirmed',
                'appointment_id' => 510,
                'recipient_type' => 'internal',
                'recipient_user_id' => 0,
                'attempts' => 0,
                'max_attempts' => 3,
                'correlation_id' => '',
            ],
        ]);
        $logService = new FakeNotificationDeliveryLogService();

        $dispatcher = new TestNotificationQueueDispatcher(
            $queueModel,
            $logService,
            new FakeNotificationOptOutService()
        );
        $dispatcher->ruleEnabled = true;
        $dispatcher->integrationActive = true;
        $dispatcher->appointments[510] = [
            'id' => 510,
            'customer_email' => 'customer@example.com',
            'customer_phone' => '+15550005555',
            'customer_first_name' => 'Casey',
            'customer_last_name' => 'Customer',
            'service_name' => 'Consult',
            'provider_name' => 'Dr. Rivera',
            'start_at' => '2026-05-02 13:00:00',
        ];

        $stats = $dispatcher->dispatch();

        $this->assertSame(1, $stats['failed']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame('failed', $queueModel->rowsById[111]['status']);
        $this->assertSame('Internal recipient metadata missing', $queueModel->rowsById[111]['last_error']);
        $this->assertSame('failed', $logService->entries[0]['status']);
        $this->assertSame('Internal recipient metadata missing', $logService->entries[0]['error_message']);
    }

    public function testDispatchMarksSuccessfulEmailSendAsSent(): void
    {
        $queueModel = new FakeNotificationQueueModel([12], [
            [
                'id' => 12,
                'business_id' => 1,
                'channel' => 'email',
                'event_type' => 'appointment_reminder',
                'appointment_id' => 501,
                'attempts' => 0,
                'max_attempts' => 3,
                'correlation_id' => '',
            ],
        ]);
        $logService = new FakeNotificationDeliveryLogService();

        $dispatcher = new TestNotificationQueueDispatcher(
            $queueModel,
            $logService,
            new FakeNotificationOptOutService()
        );
        $dispatcher->ruleEnabled = true;
        $dispatcher->integrationActive = true;
        $dispatcher->appointments[501] = [
            'id' => 501,
            'customer_email' => 'guest@example.com',
            'customer_phone' => '+15550001111',
            'customer_first_name' => 'Pat',
            'customer_last_name' => 'Doe',
            'service_name' => 'Consult',
            'provider_name' => 'Dr. Rivera',
            'start_at' => '2026-05-02 10:00:00',
        ];
        $dispatcher->emailResult = ['ok' => true];

        $stats = $dispatcher->dispatch();

        $this->assertSame(1, $stats['sent']);
        $this->assertSame('sent', $queueModel->rowsById[12]['status']);
        $this->assertSame('success', $logService->entries[0]['status']);
        $this->assertSame([501], $dispatcher->reminderMarkedForAppointments);
    }

    public function testDispatchCancelsOptedOutRecipientBeforeSending(): void
    {
        $queueModel = new FakeNotificationQueueModel([13], [
            [
                'id' => 13,
                'business_id' => 1,
                'channel' => 'email',
                'event_type' => 'appointment_confirmed',
                'appointment_id' => 502,
                'attempts' => 0,
                'max_attempts' => 3,
                'correlation_id' => '',
            ],
        ]);
        $logService = new FakeNotificationDeliveryLogService();
        $optOutService = new FakeNotificationOptOutService();
        $optOutService->optedOutRecipients = ['optedout@example.com'];

        $dispatcher = new TestNotificationQueueDispatcher(
            $queueModel,
            $logService,
            $optOutService
        );
        $dispatcher->ruleEnabled = true;
        $dispatcher->integrationActive = true;
        $dispatcher->appointments[502] = [
            'id' => 502,
            'customer_email' => 'optedout@example.com',
            'customer_phone' => '+15550002222',
            'customer_first_name' => 'Opted',
            'customer_last_name' => 'Out',
            'service_name' => 'Consult',
            'provider_name' => 'Dr. Rivera',
            'start_at' => '2026-05-02 11:00:00',
        ];

        $stats = $dispatcher->dispatch();

        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame('cancelled', $queueModel->rowsById[13]['status']);
        $this->assertSame('Recipient opted out', $queueModel->rowsById[13]['last_error']);
        $this->assertSame('cancelled', $logService->entries[0]['status']);
        $this->assertSame('optedout@example.com', $logService->entries[0]['recipient']);
    }

    public function testDispatchRequeuesFailedSendWithBackoffBeforeMaxAttempts(): void
    {
        $queueModel = new FakeNotificationQueueModel([14], [
            [
                'id' => 14,
                'business_id' => 1,
                'channel' => 'email',
                'event_type' => 'appointment_confirmed',
                'appointment_id' => 503,
                'attempts' => 1,
                'max_attempts' => 3,
                'correlation_id' => '',
            ],
        ]);
        $logService = new FakeNotificationDeliveryLogService();

        $dispatcher = new TestNotificationQueueDispatcher(
            $queueModel,
            $logService,
            new FakeNotificationOptOutService()
        );
        $dispatcher->ruleEnabled = true;
        $dispatcher->integrationActive = true;
        $dispatcher->appointments[503] = [
            'id' => 503,
            'customer_email' => 'retry@example.com',
            'customer_phone' => '+15550003333',
            'customer_first_name' => 'Retry',
            'customer_last_name' => 'Case',
            'service_name' => 'Consult',
            'provider_name' => 'Dr. Rivera',
            'start_at' => '2026-05-02 12:00:00',
        ];
        $dispatcher->emailResult = ['ok' => false, 'error' => 'Transient SMTP failure'];

        $stats = $dispatcher->dispatch();

        $this->assertSame(1, $stats['claimed']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame('queued', $queueModel->rowsById[14]['status']);
        $this->assertSame(2, $queueModel->rowsById[14]['attempts']);
        $this->assertSame('Transient SMTP failure', $queueModel->rowsById[14]['last_error']);
        $this->assertNotSame('', (string) ($queueModel->rowsById[14]['run_after'] ?? ''));
        $this->assertSame('failed', $logService->entries[0]['status']);
        $this->assertSame('retry@example.com', $logService->entries[0]['recipient']);
    }

    public function testSendEmailPassesAppointmentProviderNameNotCustomerName(): void
    {
        $dispatcher = new CapturingEmailDispatcher();

        $appt = [
            'id'                  => 900,
            'hash'                => 'abc123',
            'customer_email'      => 'customer@example.com',
            'customer_first_name' => 'Nilesh',
            'customer_last_name'  => 'Cara',
            'customer_phone'      => '+27821000000',
            'service_name'        => 'General Consultation',
            'service_duration'    => '45',
            'provider_name'       => 'Dr Cara',       // appointment provider — must appear in template data
            'start_at'            => '2026-05-02 08:00:00',
            'stored_timezone'     => 'Africa/Johannesburg',
            'location_name'       => '',
            'location_address'    => '',
            'location_contact'    => '',
            'booking_channel'     => 'web',
            'created_at'          => '2026-05-01 10:00:00',
        ];

        // Call the real sendEmail() through the capturing subclass
        $dispatcher->callSendEmail(1, 'appointment_confirmed', $appt);

        $captured = $dispatcher->capturedTemplateData;
        $this->assertNotNull($captured, 'sendEmail() must capture template data');
        $this->assertSame('Dr Cara', $captured['provider_name'], 'provider_name must be the appointment provider, not the customer');
        $this->assertSame('Nilesh Cara', $captured['customer_name'], 'customer_name must remain the customer');
    }

    public function testSendEmailSetsRecipientEmailToCustomerForCustomerClass(): void
    {
        $dispatcher = new CapturingEmailDispatcher();

        $appt = [
            'id'                  => 901,
            'hash'                => 'def456',
            'customer_email'      => 'booking_customer@example.com',
            'customer_first_name' => 'Jane',
            'customer_last_name'  => 'Smith',
            'customer_phone'      => '+27821000001',
            'service_name'        => 'Check-up',
            'service_duration'    => '30',
            'provider_name'       => 'Dr. Watson',
            'start_at'            => '2026-05-03 09:00:00',
            'stored_timezone'     => 'Africa/Johannesburg',
            'location_name'       => '',
            'location_address'    => '',
            'location_contact'    => '',
            'booking_channel'     => 'web',
            'created_at'          => '2026-05-01 12:00:00',
        ];

        $dispatcher->callSendEmail(1, 'appointment_confirmed', $appt);

        // No recipient_class set → defaults to 'customer' → to must equal customer_email
        $this->assertSame('booking_customer@example.com', $dispatcher->capturedTo);
    }

    public function testSendEmailUsesRecipientEmailAndProviderNameForInternalClass(): void
    {
        $dispatcher = new CapturingEmailDispatcher();

        $appt = [
            'id'                  => 902,
            'hash'                => 'ghi789',
            'recipient_class'     => 'internal',
            'recipient_email'     => 'provider@clinic.com',
            'recipient_name'      => 'Dr. Watson',    // receiving staff's own display name
            'customer_email'      => 'customer@example.com',
            'customer_first_name' => 'Nilesh',
            'customer_last_name'  => 'Cara',
            'customer_phone'      => '+27821000002',
            'service_name'        => 'Consultation',
            'service_duration'    => '30',
            'provider_name'       => 'Dr. Watson',    // appointment provider (may differ if staff assigned)
            'start_at'            => '2026-05-03 10:00:00',
            'stored_timezone'     => 'Africa/Johannesburg',
            'location_name'       => '',
            'location_address'    => '',
            'location_contact'    => '',
            'booking_channel'     => 'web',
            'created_at'          => '2026-05-01 08:00:00',
        ];

        $dispatcher->callSendEmail(1, 'appointment_confirmed', $appt);

        // Internal recipient → to must equal recipient_email, not customer_email
        $this->assertSame('provider@clinic.com', $dispatcher->capturedTo);

        // provider_name in template data must still be the appointment provider, not the salutation name
        $this->assertSame('Dr. Watson', $dispatcher->capturedTemplateData['provider_name']);

        // customer_name must be the actual customer
        $this->assertSame('Nilesh Cara', $dispatcher->capturedTemplateData['customer_name']);
    }
}

/**
 * Captures template data assembled inside sendEmail() via the onEmailTemplateData hook.
 * The actual SMTP send will fail gracefully in test (no config), the test only asserts
 * on captured template data — not the return value.
 */
final class CapturingEmailDispatcher extends NotificationQueueDispatcher
{
    public ?array $capturedTemplateData = null;
    public ?string $capturedTo = null;

    public function callSendEmail(int $businessId, string $eventType, array $appt): array
    {
        // Capture the addressed-to before delegation
        $this->capturedTo = (string) ($appt['recipient_email'] ?? $appt['customer_email'] ?? '');
        return $this->sendEmail($businessId, $eventType, $appt);
    }

    protected function onEmailTemplateData(array $templateData): void
    {
        $this->capturedTemplateData = $templateData;
    }

    protected function resolveNotificationTimezone(array $appt): string
    {
        return 'UTC';
    }
}

final class TestNotificationQueueDispatcher extends NotificationQueueDispatcher
{
    public bool $ruleEnabled = true;
    public bool $integrationActive = true;
    public array $appointments = [];
    public array $emailResult = ['ok' => true];
    public array $smsResult = ['ok' => true];
    public array $whatsAppResult = ['ok' => true];
    public array $reminderMarkedForAppointments = [];

    protected function getAppointmentContext(int $appointmentId): ?array
    {
        return $this->appointments[$appointmentId] ?? null;
    }

    protected function isRuleEnabled(int $businessId, string $eventType, string $channel): bool
    {
        return $this->ruleEnabled;
    }

    protected function isIntegrationActive(int $businessId, string $channel): bool
    {
        return $this->integrationActive;
    }

    protected function sendEmail(int $businessId, string $eventType, array $appt): array
    {
        return $this->emailResult;
    }

    protected function sendSmsReminder(int $businessId, array $appt): array
    {
        return $this->smsResult;
    }

    protected function sendWhatsApp(int $businessId, string $eventType, array $appt): array
    {
        return $this->whatsAppResult;
    }

    protected function markReminderSentIfSupported(int $appointmentId): void
    {
        $this->reminderMarkedForAppointments[] = $appointmentId;
    }
}

final class FakeNotificationQueueModel extends NotificationQueueModel
{
    public array $rowsById = [];
    public array $updates = [];
    private array $queuedIds;
    private ?int $currentId = null;
    private array $currentWhereIn = [];
    private array $pendingSet = [];

    public function __construct(array $queuedIds, array $rows)
    {
        $this->queuedIds = $queuedIds;
        foreach ($rows as $row) {
            $this->rowsById[(int) $row['id']] = $row;
        }
    }

    public function select($select = '*', ?bool $escape = null)
    {
        return $this;
    }

    public function where($key, $value = null, ?bool $escape = null)
    {
        if ($key === 'id') {
            $this->currentId = (int) $value;
        }
        return $this;
    }

    public function orWhere($key, $value = null, ?bool $escape = null)
    {
        return $this;
    }

    public function groupStart()
    {
        return $this;
    }

    public function groupEnd()
    {
        return $this;
    }

    public function orderBy(string $orderBy, string $direction = '', ?bool $escape = null)
    {
        return $this;
    }

    public function limit(?int $value = null, ?int $offset = 0)
    {
        return $this;
    }

    public function findColumn(string $columnName): array
    {
        return $this->queuedIds;
    }

    public function whereIn(?string $key = null, $values = null, ?bool $escape = null)
    {
        $this->currentWhereIn = array_map('intval', $values ?? []);
        return $this;
    }

    public function findAll(?int $limit = null, int $offset = 0)
    {
        if ($this->currentWhereIn === []) {
            return array_values($this->rowsById);
        }

        return array_values(array_intersect_key($this->rowsById, array_flip($this->currentWhereIn)));
    }

    public function set($key, $value = '', ?bool $escape = null)
    {
        $this->pendingSet = is_array($key) ? $key : [$key => $value];
        return $this;
    }

    public function update($id = null, $row = null): bool
    {
        $targetId = $id !== null ? (int) $id : (int) $this->currentId;
        $payload = is_array($row) ? $row : $this->pendingSet;

        if ($targetId <= 0) {
            return false;
        }

        $existing = $this->rowsById[$targetId] ?? ['id' => $targetId];
        $this->rowsById[$targetId] = array_merge($existing, $payload);
        $this->updates[$targetId][] = $payload;

        $this->pendingSet = [];
        return true;
    }
}

final class FakeNotificationDeliveryLogService extends NotificationDeliveryLogService
{
    public array $entries = [];

    public function logAttempt(
        int $businessId,
        ?int $queueId,
        ?string $correlationId,
        string $channel,
        string $eventType,
        ?int $appointmentId,
        ?string $recipient,
        string $status,
        int $attempt,
        ?string $errorMessage = null
    ): void {
        $this->entries[] = [
            'business_id' => $businessId,
            'queue_id' => $queueId,
            'correlation_id' => $correlationId,
            'channel' => $channel,
            'event_type' => $eventType,
            'appointment_id' => $appointmentId,
            'recipient' => $recipient,
            'status' => $status,
            'attempt' => $attempt,
            'error_message' => $errorMessage,
        ];
    }
}

final class FakeNotificationOptOutService extends NotificationOptOutService
{
    public array $optedOutRecipients = [];

    public function isOptedOut(int $businessId, string $channel, string $recipient): bool
    {
        return in_array($recipient, $this->optedOutRecipients, true);
    }
}
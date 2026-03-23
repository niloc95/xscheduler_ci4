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
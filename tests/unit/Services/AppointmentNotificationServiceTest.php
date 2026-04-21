<?php

namespace Tests\Unit\Services;

use App\Services\AppointmentNotificationService;
use App\Services\NotificationQueueDispatcher;
use App\Services\NotificationQueueService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AppointmentNotificationServiceTest extends CIUnitTestCase
{
    public function testSendDueReminderEmailsDelegatesToCanonicalQueueFlow(): void
    {
        $queueService = new FakeLegacyReminderQueueService([
            'scanned' => 4,
            'enqueued' => 2,
            'skipped' => 1,
        ]);
        $dispatcher = new FakeLegacyReminderDispatcher([
            'claimed' => 2,
            'sent' => 3,
            'failed' => 1,
            'cancelled' => 2,
            'skipped' => 4,
        ]);

        $service = new TestAppointmentNotificationService($queueService, $dispatcher);

        $stats = $service->sendDueReminderEmails(7);

        $this->assertSame([7], $queueService->businessIds);
        $this->assertSame([
            ['businessId' => 7, 'limit' => 100, 'eventType' => 'appointment_reminder'],
        ], $dispatcher->calls);
        $this->assertSame([
            'scanned' => 4,
            'sent' => 3,
            'skipped' => 8,
        ], $stats);
    }

    public function testSendDueReminderEmailsSkipsDispatchWhenNothingWasEnqueued(): void
    {
        $queueService = new FakeLegacyReminderQueueService([
            'scanned' => 2,
            'enqueued' => 0,
            'skipped' => 2,
        ]);
        $dispatcher = new FakeLegacyReminderDispatcher([
            'claimed' => 99,
            'sent' => 99,
            'failed' => 99,
            'cancelled' => 99,
            'skipped' => 99,
        ]);

        $service = new TestAppointmentNotificationService($queueService, $dispatcher);

        $stats = $service->sendDueReminderEmails(3);

        $this->assertSame([3], $queueService->businessIds);
        $this->assertSame([], $dispatcher->calls);
        $this->assertSame([
            'scanned' => 2,
            'sent' => 0,
            'skipped' => 2,
        ], $stats);
    }
}

final class TestAppointmentNotificationService extends AppointmentNotificationService
{
    public function __construct(
        private readonly NotificationQueueService $queueService,
        private readonly NotificationQueueDispatcher $dispatcher,
    ) {
    }

    protected function getNotificationQueueService(): NotificationQueueService
    {
        return $this->queueService;
    }

    protected function getNotificationQueueDispatcher(): NotificationQueueDispatcher
    {
        return $this->dispatcher;
    }
}

final class FakeLegacyReminderQueueService extends NotificationQueueService
{
    public array $businessIds = [];

    public function __construct(private readonly array $stats)
    {
    }

    public function enqueueDueReminders(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $this->businessIds[] = $businessId;
        return $this->stats;
    }
}

final class FakeLegacyReminderDispatcher extends NotificationQueueDispatcher
{
    public array $calls = [];

    public function __construct(private readonly array $stats)
    {
    }

    public function dispatch(int $businessId = self::BUSINESS_ID_DEFAULT, int $limit = 100, ?string $eventType = null): array
    {
        $this->calls[] = [
            'businessId' => $businessId,
            'limit' => $limit,
            'eventType' => $eventType,
        ];

        return $this->stats;
    }
}
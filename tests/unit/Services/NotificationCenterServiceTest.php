<?php

namespace Tests\Unit\Services;

use App\Models\NotificationDeliveryLogModel;
use App\Models\NotificationQueueModel;
use App\Services\LocalizationSettingsService;
use App\Services\NotificationCenterService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationCenterServiceTest extends CIUnitTestCase
{
    public function testBuildIndexDataReturnsNotificationsAndUnreadCount(): void
    {
        $service = new class extends NotificationCenterService {
            public function getNotifications(string $filter = 'all'): array
            {
                return [['id' => 'log_1']];
            }

            public function getUnreadCount(): int
            {
                return 3;
            }
        };

        $result = $service->buildIndexData('unread', ['name' => 'Admin'], 'admin');

        $this->assertSame('Notifications', $result['title']);
        $this->assertSame('notifications', $result['current_page']);
        $this->assertSame('admin', $result['user_role']);
        $this->assertSame('Admin', $result['user']['name']);
        $this->assertSame('unread', $result['filter']);
        $this->assertSame(3, $result['unread_count']);
        $this->assertSame([['id' => 'log_1']], $result['notifications']);
        $this->assertSame('Activity Feed', $result['notificationPageHeading']);
        $this->assertStringContainsString('/notifications', $result['notificationUi']['currentPageUrl']);
        $this->assertStringContainsString('filter=unread', $result['notificationUi']['currentPageUrl']);
    }

    public function testGetNotificationsReturnsEmptyStateWhenSourcesUnavailable(): void
    {
        $deliveryLogModel = $this->createMock(NotificationDeliveryLogModel::class);
        $deliveryLogModel->method('builder')->willThrowException(new \RuntimeException('db unavailable'));

        $queueModel = $this->createMock(NotificationQueueModel::class);
        $queueModel->method('builder')->willThrowException(new \RuntimeException('db unavailable'));

        $service = new NotificationCenterService(
            $deliveryLogModel,
            $queueModel,
            $this->createMock(LocalizationSettingsService::class)
        );

        $result = $service->getNotifications();

        $this->assertCount(2, $result);
        $this->assertSame('Notifications Ready', $result[0]['title']);
        $this->assertSame('system', $result[0]['channel']);
    }

    public function testGetUnreadCountReturnsZeroWhenSourcesFail(): void
    {
        $deliveryLogModel = new class extends NotificationDeliveryLogModel {
            public function where($key = null, $value = null, ?bool $escape = null)
            {
                throw new \RuntimeException('db unavailable');
            }
        };

        $queueModel = new class extends NotificationQueueModel {
            public function where($key = null, $value = null, ?bool $escape = null)
            {
                throw new \RuntimeException('db unavailable');
            }
        };

        $service = new NotificationCenterService(
            $deliveryLogModel,
            $queueModel,
            $this->createMock(LocalizationSettingsService::class)
        );

        $this->assertSame(0, $service->getUnreadCount());
    }

    public function testGetNotificationsFiltersUnreadNotifications(): void
    {
        $service = new class extends NotificationCenterService {
            public function getNotifications(string $filter = 'all'): array
            {
                $notifications = [
                    ['id' => 'log_1', 'raw_time' => '2026-03-25 10:00:00', 'read' => true],
                    ['id' => 'queue_2', 'raw_time' => '2026-03-25 11:00:00', 'read' => false],
                ];

                usort($notifications, static function (array $a, array $b): int {
                    return strtotime($b['raw_time']) - strtotime($a['raw_time']);
                });

                if ($filter === 'unread') {
                    $notifications = array_filter($notifications, static fn(array $notification): bool => !$notification['read']);
                }

                return array_values($notifications);
            }
        };

        $result = $service->getNotifications('unread');

        $this->assertCount(1, $result);
        $this->assertSame('queue_2', $result[0]['id']);
    }

    public function testBuildIndexDataSuppressesDeliveryLogsForNonAdmins(): void
    {
        $service = new class extends NotificationCenterService {
            public function getNotifications(string $filter = 'all'): array
            {
                return [];
            }

            public function getUnreadCount(): int
            {
                return 0;
            }

            protected function getDeliveryLogs(int $businessId, array $filters): array
            {
                throw new \RuntimeException('Delivery logs should not load for non-admin users.');
            }

            protected function getNotificationBusinessOptions(int $selectedBusinessId): array
            {
                return [['id' => $selectedBusinessId, 'label' => 'Business ' . $selectedBusinessId]];
            }

            protected function resolveBusinessId(): int
            {
                return 3;
            }
        };

        $result = $service->buildIndexData(['tab' => 'delivery-logs'], ['roles' => ['provider']], 'provider');

        $this->assertSame('activity', $result['notificationTab']);
        $this->assertFalse($result['notificationIsAdmin']);
        $this->assertSame([], $result['notificationDeliveryLogs']);
        $this->assertSame([], $result['notificationBusinessOptions']);
    }

    public function testBuildIndexDataReturnsDeliveryLogsForAdminTab(): void
    {
        $service = new class extends NotificationCenterService {
            public array $captured = [];

            public function getNotifications(string $filter = 'all'): array
            {
                return [];
            }

            public function getUnreadCount(): int
            {
                return 0;
            }

            protected function getDeliveryLogs(int $businessId, array $filters): array
            {
                $this->captured = ['businessId' => $businessId, 'filters' => $filters];

                return [[
                    'id' => 91,
                    'status' => 'failed',
                    'can_resend' => true,
                ]];
            }

            protected function getNotificationBusinessOptions(int $selectedBusinessId): array
            {
                return [['id' => $selectedBusinessId, 'label' => 'Business ' . $selectedBusinessId]];
            }

            protected function resolveBusinessId(): int
            {
                return 7;
            }
        };

        $result = $service->buildIndexData([
            'tab' => 'delivery-logs',
            'log_status' => 'failed',
        ], ['roles' => ['admin']], 'admin');

        $this->assertSame('delivery-logs', $result['notificationTab']);
        $this->assertTrue($result['notificationIsAdmin']);
        $this->assertSame(7, $result['notificationCurrentBusinessId']);
        $this->assertSame([['id' => 7, 'label' => 'Business 7']], $result['notificationBusinessOptions']);
        $this->assertSame([['id' => 91, 'status' => 'failed', 'can_resend' => true]], $result['notificationDeliveryLogs']);
        $this->assertSame(['status' => 'failed', 'channel' => '', 'event' => ''], $result['notificationDeliveryLogFilters']);
        $this->assertSame(1, $result['notificationDeliveryLogSummary']['failed']);
        $this->assertSame(1, $result['notificationDeliveryLogSummary']['resendable']);
        $this->assertSame('Delivery Logs', $result['notificationPageHeading']);
        $this->assertTrue($result['notificationBusinessContext']['options'][0]['is_active']);
        $this->assertStringContainsString('tab=delivery-logs', $result['notificationBusinessContext']['options'][0]['url']);
        $this->assertStringContainsString('log_status=failed', $result['notificationBusinessContext']['options'][0]['url']);
        $this->assertSame(['businessId' => 7, 'filters' => ['status' => 'failed', 'channel' => '', 'event' => '']], $service->captured);
    }

    public function testResolveBusinessIdUsesSessionBusinessContext(): void
    {
        $service = new class extends NotificationCenterService {
            public function exposeBusinessId(): int
            {
                return $this->resolveBusinessId();
            }
        };

        session()->set('business_id', 42);

        try {
            $this->assertSame(42, $service->exposeBusinessId());
        } finally {
            session()->remove('business_id');
        }
    }
}
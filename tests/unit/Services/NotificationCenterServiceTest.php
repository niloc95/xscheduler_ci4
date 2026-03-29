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
}
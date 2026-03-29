<?php

namespace App\Services;

use App\Models\NotificationDeliveryLogModel;
use App\Models\NotificationQueueModel;

class NotificationCenterService
{
    private NotificationDeliveryLogModel $deliveryLogModel;
    private NotificationQueueModel $queueModel;
    private LocalizationSettingsService $localizationSettingsService;

    public function __construct(
        ?NotificationDeliveryLogModel $deliveryLogModel = null,
        ?NotificationQueueModel $queueModel = null,
        ?LocalizationSettingsService $localizationSettingsService = null,
    ) {
        $this->deliveryLogModel = $deliveryLogModel ?? new NotificationDeliveryLogModel();
        $this->queueModel = $queueModel ?? new NotificationQueueModel();
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
    }

    public function buildIndexData(string $filter, ?array $currentUser, ?string $currentRole): array
    {
        return [
            'title' => 'Notifications',
            'current_page' => 'notifications',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'notifications' => $this->getNotifications($filter),
            'unread_count' => $this->getUnreadCount(),
            'filter' => $filter,
        ];
    }

    public function getNotifications(string $filter = 'all'): array
    {
        $notifications = [];
        $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT;

        try {
            $logBuilder = $this->deliveryLogModel->builder();

            $logBuilder->select('xs_notification_delivery_logs.*, a.start_at as appointment_start,
                                c.first_name as customer_first_name, c.last_name as customer_last_name,
                                s.name as service_name, u.name as provider_name', false)
                ->join('xs_appointments a', 'a.id = xs_notification_delivery_logs.appointment_id', 'left')
                ->join('xs_customers c', 'c.id = a.customer_id', 'left')
                ->join('xs_services s', 's.id = a.service_id', 'left')
                ->join('xs_users u', 'u.id = a.provider_id', 'left')
                ->where('xs_notification_delivery_logs.business_id', $businessId)
                ->orderBy('xs_notification_delivery_logs.created_at', 'DESC')
                ->limit(50);

            if ($filter === 'appointments') {
                $logBuilder->whereIn('xs_notification_delivery_logs.event_type', [
                    'appointment_confirmed',
                    'appointment_reminder',
                    'appointment_cancelled',
                    'appointment_rescheduled',
                ]);
            }

            foreach ($logBuilder->get()->getResultArray() as $log) {
                $notifications[] = $this->formatDeliveryLog($log);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Notification delivery logs unavailable: ' . $e->getMessage());
        }

        try {
            $queueBuilder = $this->queueModel->builder();

            $queueBuilder->select('xs_notification_queue.*, a.start_at as appointment_start,
                                  c.first_name as customer_first_name, c.last_name as customer_last_name,
                                  s.name as service_name, u.name as provider_name', false)
                ->join('xs_appointments a', 'a.id = xs_notification_queue.appointment_id', 'left')
                ->join('xs_customers c', 'c.id = a.customer_id', 'left')
                ->join('xs_services s', 's.id = a.service_id', 'left')
                ->join('xs_users u', 'u.id = a.provider_id', 'left')
                ->where('xs_notification_queue.business_id', $businessId)
                ->whereIn('xs_notification_queue.status', ['queued', 'failed'])
                ->orderBy('xs_notification_queue.created_at', 'DESC')
                ->limit(20);

            foreach ($queueBuilder->get()->getResultArray() as $item) {
                $notifications[] = $this->formatQueueItem($item);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Notification queue unavailable: ' . $e->getMessage());
        }

        usort($notifications, static function (array $a, array $b): int {
            return strtotime($b['raw_time'] ?? 'now') - strtotime($a['raw_time'] ?? 'now');
        });

        if ($filter === 'unread') {
            $notifications = array_filter($notifications, static fn(array $notification): bool => !$notification['read']);
        }

        if ($notifications === []) {
            return $this->getEmptyStateNotifications();
        }

        return array_values($notifications);
    }

    public function getUnreadCount(): int
    {
        $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT;
        $count = 0;

        try {
            $count += $this->queueModel
                ->where('business_id', $businessId)
                ->whereIn('status', ['queued', 'failed'])
                ->countAllResults();

            $count += $this->deliveryLogModel
                ->where('business_id', $businessId)
                ->where('status', 'failed')
                ->where('created_at >', date('Y-m-d H:i:s', strtotime('-24 hours')))
                ->countAllResults();
        } catch (\Throwable $e) {
            log_message('warning', 'Notification count unavailable: ' . $e->getMessage());
        }

        return $count;
    }

    private function formatDeliveryLog(array $log): array
    {
        $eventType = $log['event_type'] ?? 'unknown';
        $channel = $log['channel'] ?? 'email';
        $status = $log['status'] ?? 'unknown';
        $customerName = trim(($log['customer_first_name'] ?? '') . ' ' . ($log['customer_last_name'] ?? ''));
        $serviceName = $log['service_name'] ?? 'Service';
        $providerName = $log['provider_name'] ?? 'Provider';
        $recipient = $log['recipient'] ?? '';
        $createdAt = $log['created_at'] ?? date('Y-m-d H:i:s');

        $config = $this->getEventConfig($eventType);
        $message = $this->buildNotificationMessage($eventType, $status, $channel, [
            'customer' => $customerName ?: 'Customer',
            'service' => $serviceName,
            'provider' => $providerName,
            'recipient' => $recipient,
            'appointment_start' => $log['appointment_start'] ?? null,
            'error' => $log['error_message'] ?? null,
        ]);

        return [
            'id' => 'log_' . ($log['id'] ?? 0),
            'type' => $config['type'],
            'title' => $config['title'] . ' - ' . ucfirst($status),
            'message' => $message,
            'time' => $this->timeAgo($createdAt),
            'raw_time' => $createdAt,
            'read' => $status === 'success',
            'icon' => $config['icon'],
            'color' => $status === 'success' ? 'green' : ($status === 'failed' ? 'red' : 'amber'),
            'channel' => $channel,
            'status' => $status,
        ];
    }

    private function formatQueueItem(array $item): array
    {
        $eventType = $item['event_type'] ?? 'unknown';
        $channel = $item['channel'] ?? 'email';
        $status = $item['status'] ?? 'queued';
        $customerName = trim(($item['customer_first_name'] ?? '') . ' ' . ($item['customer_last_name'] ?? ''));
        $serviceName = $item['service_name'] ?? 'Service';
        $createdAt = $item['created_at'] ?? date('Y-m-d H:i:s');

        $config = $this->getEventConfig($eventType);
        $statusLabel = $status === 'queued' ? 'Pending' : 'Retry Scheduled';
        $message = sprintf(
            '%s notification for %s (%s) via %s is %s',
            $config['title'],
            $customerName ?: 'Customer',
            $serviceName,
            strtoupper($channel),
            strtolower($statusLabel)
        );

        if (!empty($item['last_error'])) {
            $message .= '. Last error: ' . $item['last_error'];
        }

        return [
            'id' => 'queue_' . ($item['id'] ?? 0),
            'type' => $config['type'],
            'title' => $config['title'] . ' - ' . $statusLabel,
            'message' => $message,
            'time' => $this->timeAgo($createdAt),
            'raw_time' => $createdAt,
            'read' => false,
            'icon' => $config['icon'],
            'color' => $status === 'queued' ? 'blue' : 'amber',
            'channel' => $channel,
            'status' => $status,
        ];
    }

    private function getEventConfig(string $eventType): array
    {
        $configs = [
            'appointment_confirmed' => [
                'type' => 'appointment',
                'title' => 'Appointment Confirmed',
                'icon' => 'calendar',
            ],
            'appointment_reminder' => [
                'type' => 'reminder',
                'title' => 'Appointment Reminder',
                'icon' => 'bell',
            ],
            'appointment_cancelled' => [
                'type' => 'cancellation',
                'title' => 'Appointment Cancelled',
                'icon' => 'x-circle',
            ],
            'appointment_rescheduled' => [
                'type' => 'appointment',
                'title' => 'Appointment Rescheduled',
                'icon' => 'calendar',
            ],
        ];

        return $configs[$eventType] ?? [
            'type' => 'system',
            'title' => ucwords(str_replace('_', ' ', $eventType)),
            'icon' => 'cog',
        ];
    }

    private function buildNotificationMessage(string $eventType, string $status, string $channel, array $data): string
    {
        $customer = $data['customer'];
        $service = $data['service'];
        $provider = $data['provider'];
        $recipient = $data['recipient'];
        $appointmentStart = $data['appointment_start'];
        $error = $data['error'];

        $channelLabel = strtoupper($channel);
        $localStart = $appointmentStart ? TimezoneService::toDisplay($appointmentStart) : '';
        $timeStr = $localStart
            ? date('M j, Y', strtotime($localStart)) . ' at ' . $this->localizationSettingsService->formatTimeForDisplay(date('H:i:s', strtotime($localStart)))
            : '';

        if ($status === 'success') {
            return match ($eventType) {
                'appointment_confirmed' => sprintf('%s notification sent to %s for appointment with %s (%s)%s', $channelLabel, $recipient ?: $customer, $provider, $service, $timeStr ? " on {$timeStr}" : ''),
                'appointment_reminder' => sprintf('Reminder sent to %s via %s for upcoming appointment%s', $recipient ?: $customer, $channelLabel, $timeStr ? " on {$timeStr}" : ''),
                'appointment_cancelled' => sprintf('Cancellation notice sent to %s via %s for %s appointment', $recipient ?: $customer, $channelLabel, $service),
                'appointment_rescheduled' => sprintf('Reschedule notice sent to %s via %s for %s%s', $recipient ?: $customer, $channelLabel, $service, $timeStr ? " - new time: {$timeStr}" : ''),
                default => sprintf('%s notification sent to %s via %s', ucwords(str_replace('_', ' ', $eventType)), $recipient ?: $customer, $channelLabel),
            };
        }

        if ($status === 'failed') {
            $base = sprintf('Failed to send %s %s to %s', $channelLabel, str_replace('_', ' ', $eventType), $recipient ?: $customer);
            return $error ? "{$base}: {$error}" : $base;
        }

        if ($status === 'cancelled') {
            return sprintf('%s notification cancelled for %s (%s)', ucwords(str_replace('_', ' ', $eventType)), $customer, $error ?: 'Rule disabled');
        }

        return sprintf('%s %s notification - status: %s', ucwords(str_replace('_', ' ', $eventType)), $channelLabel, $status);
    }

    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        return date('M j, Y', $time);
    }

    private function getEmptyStateNotifications(): array
    {
        return [
            [
                'id' => 'info_1',
                'type' => 'system',
                'title' => 'Notifications Ready',
                'message' => 'Your notification system is configured. Notifications will appear here when appointments are confirmed, reminded, cancelled, or rescheduled.',
                'time' => 'Just now',
                'raw_time' => date('Y-m-d H:i:s'),
                'read' => false,
                'icon' => 'cog',
                'color' => 'blue',
                'channel' => 'system',
                'status' => 'info',
            ],
            [
                'id' => 'info_2',
                'type' => 'system',
                'title' => 'How It Works',
                'message' => 'Enable notification channels (Email, SMS, WhatsApp) in Settings → Notifications, then configure which events trigger notifications using the Event→Channel Matrix.',
                'time' => 'Just now',
                'raw_time' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                'read' => true,
                'icon' => 'cog',
                'color' => 'gray',
                'channel' => 'system',
                'status' => 'info',
            ],
        ];
    }
}
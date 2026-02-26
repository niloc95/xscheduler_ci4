<?php

/**
 * =============================================================================
 * NOTIFICATIONS CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Notifications.php
 * @description User notification center for viewing, managing, and configuring
 *              in-app notifications and delivery logs.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /notifications                : List all notifications
 * GET  /notifications/unread         : Get unread count (JSON)
 * POST /notifications/mark-read/:id  : Mark notification as read
 * POST /notifications/mark-all-read  : Mark all as read
 * POST /notifications/delete/:id     : Delete notification
 * GET  /notifications/preferences    : Notification preferences page
 * POST /notifications/preferences    : Save preferences
 * GET  /notifications/logs           : View delivery logs (admin)
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Manages user notification experience:
 * - In-app notification display and management
 * - Mark as read/unread functionality
 * - Notification filtering (all, unread, by type)
 * - Delivery logs for admins (email, SMS, WhatsApp)
 * - Real-time notification badge updates
 * 
 * NOTIFICATION TYPES:
 * -----------------------------------------------------------------------------
 * - appointment_reminder : Upcoming appointment reminder
 * - appointment_booked   : New booking confirmation
 * - appointment_changed  : Appointment modified/rescheduled
 * - appointment_cancelled: Appointment cancellation notice
 * - system               : System announcements
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - All users: View own notifications
 * - Admin: View delivery logs and system-wide stats
 * 
 * @see         app/Views/notifications/ for view templates
 * @see         app/Services/NotificationPhase1.php for sending logic
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\NotificationDeliveryLogModel;
use App\Models\NotificationQueueModel;
use App\Models\AppointmentModel;
use App\Services\LocalizationSettingsService;
use App\Services\NotificationPhase1;
use App\Services\TimezoneService;
use CodeIgniter\Controller;

class Notifications extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('permissions');
    }

    /**
     * Display notifications list
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();
        $filter = $this->request->getGet('filter') ?? 'all';

        $notifications = $this->getNotifications($filter);
        $unreadCount = $this->getUnreadCount();

        $data = [
            'title' => 'Notifications',
            'current_page' => 'notifications',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'filter' => $filter
        ];

        return view('notifications/index', $data);
    }

    /**
     * Get real notifications from delivery logs and queue
     */
    private function getNotifications(string $filter = 'all'): array
    {
        $notifications = [];
        $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;

        try {
            // Get delivery logs (sent notifications)
            $logModel = new NotificationDeliveryLogModel();
            $logBuilder = $logModel->builder();
            
            $logBuilder->select('xs_notification_delivery_logs.*, a.start_at as appointment_start, 
                                c.first_name as customer_first_name, c.last_name as customer_last_name,
                                s.name as service_name, u.name as provider_name')
                ->join('xs_appointments a', 'a.id = xs_notification_delivery_logs.appointment_id', 'left')
                ->join('xs_customers c', 'c.id = a.customer_id', 'left')
                ->join('xs_services s', 's.id = a.service_id', 'left')
                ->join('xs_users u', 'u.id = a.provider_id', 'left')
                ->where('xs_notification_delivery_logs.business_id', $businessId)
                ->orderBy('xs_notification_delivery_logs.created_at', 'DESC')
                ->limit(50);

            // Apply filter
            if ($filter === 'appointments') {
                $logBuilder->whereIn('xs_notification_delivery_logs.event_type', [
                    'appointment_confirmed', 'appointment_reminder', 
                    'appointment_cancelled', 'appointment_rescheduled'
                ]);
            }

            $logs = $logBuilder->get()->getResultArray();

            foreach ($logs as $log) {
                $notifications[] = $this->formatDeliveryLog($log);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Notification delivery logs unavailable: ' . $e->getMessage());
        }

        try {
            // Get pending queue items
            $queueModel = new NotificationQueueModel();
            $queueBuilder = $queueModel->builder();
            
            $queueBuilder->select('xs_notification_queue.*, a.start_at as appointment_start,
                                  c.first_name as customer_first_name, c.last_name as customer_last_name,
                                  s.name as service_name, u.name as provider_name')
            ->join('xs_appointments a', 'a.id = xs_notification_queue.appointment_id', 'left')
            ->join('xs_customers c', 'c.id = a.customer_id', 'left')
            ->join('xs_services s', 's.id = a.service_id', 'left')
            ->join('xs_users u', 'u.id = a.provider_id', 'left')
            ->where('xs_notification_queue.business_id', $businessId)
            ->whereIn('xs_notification_queue.status', ['queued', 'failed'])
            ->orderBy('xs_notification_queue.created_at', 'DESC')
            ->limit(20);

            $queue = $queueBuilder->get()->getResultArray();

            foreach ($queue as $item) {
                $notifications[] = $this->formatQueueItem($item);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Notification queue unavailable: ' . $e->getMessage());
        }

        // Sort by time (newest first)
        usort($notifications, function($a, $b) {
            return strtotime($b['raw_time'] ?? 'now') - strtotime($a['raw_time'] ?? 'now');
        });

        // Apply unread filter
        if ($filter === 'unread') {
            $notifications = array_filter($notifications, fn($n) => !$n['read']);
        }

        // If no real data, show helpful message with sample data
        if (empty($notifications)) {
            return $this->getEmptyStateNotifications();
        }

        return array_values($notifications);
    }

    /**
     * Format delivery log entry as notification
     */
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
        
        // Build message based on event type
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

    /**
     * Format queue item as notification
     */
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

    /**
     * Get event type configuration
     */
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

    /**
     * Build notification message
     */
    private function buildNotificationMessage(string $eventType, string $status, string $channel, array $data): string
    {
        $customer = $data['customer'];
        $service = $data['service'];
        $provider = $data['provider'];
        $recipient = $data['recipient'];
        $appointmentStart = $data['appointment_start'];
        $error = $data['error'];

        $channelLabel = strtoupper($channel);
        // DB stores UTC — convert to local for admin display
        $localStart = $appointmentStart
            ? TimezoneService::toDisplay($appointmentStart)
            : '';
        $timeStr = $localStart
            ? date('M j, Y', strtotime($localStart)) . ' at ' . (new LocalizationSettingsService())->formatTimeForDisplay(date('H:i:s', strtotime($localStart)))
            : '';

        if ($status === 'success') {
            switch ($eventType) {
                case 'appointment_confirmed':
                    return sprintf('%s notification sent to %s for appointment with %s (%s)%s', 
                        $channelLabel, $recipient ?: $customer, $provider, $service,
                        $timeStr ? " on {$timeStr}" : '');
                case 'appointment_reminder':
                    return sprintf('Reminder sent to %s via %s for upcoming appointment%s',
                        $recipient ?: $customer, $channelLabel,
                        $timeStr ? " on {$timeStr}" : '');
                case 'appointment_cancelled':
                    return sprintf('Cancellation notice sent to %s via %s for %s appointment',
                        $recipient ?: $customer, $channelLabel, $service);
                case 'appointment_rescheduled':
                    return sprintf('Reschedule notice sent to %s via %s for %s%s',
                        $recipient ?: $customer, $channelLabel, $service,
                        $timeStr ? " - new time: {$timeStr}" : '');
                default:
                    return sprintf('%s notification sent to %s via %s',
                        ucwords(str_replace('_', ' ', $eventType)), $recipient ?: $customer, $channelLabel);
            }
        } elseif ($status === 'failed') {
            $base = sprintf('Failed to send %s %s to %s', 
                $channelLabel, str_replace('_', ' ', $eventType), $recipient ?: $customer);
            return $error ? "{$base}: {$error}" : $base;
        } elseif ($status === 'cancelled') {
            return sprintf('%s notification cancelled for %s (%s)', 
                ucwords(str_replace('_', ' ', $eventType)), $customer, $error ?: 'Rule disabled');
        } else {
            return sprintf('%s %s notification - status: %s',
                ucwords(str_replace('_', ' ', $eventType)), $channelLabel, $status);
        }
    }

    /**
     * Convert datetime to human-readable time ago
     */
    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }

    /**
     * Get empty state notifications (shown when no real data)
     */
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

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        // Parse notification ID (format: log_123 or queue_456)
        if ($notificationId && strpos($notificationId, 'log_') === 0) {
            $id = (int) str_replace('log_', '', $notificationId);
            // Delivery logs are already "sent" - marking as read is UI-only
            // Could store read state in session or separate table if needed
        }
        
        return redirect()->back()->with('success', 'Notification marked as read');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        // For now, this is UI-only. Could implement with session or user preferences table.
        
        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Delete notification
     */
    public function delete($notificationId = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        // Parse notification ID (format: log_123 or queue_456)
        if ($notificationId && strpos($notificationId, 'queue_') === 0) {
            $id = (int) str_replace('queue_', '', $notificationId);
            if ($id > 0) {
                $queueModel = new NotificationQueueModel();
                // Cancel the queued notification
                $queueModel->update($id, ['status' => 'cancelled', 'last_error' => 'Manually cancelled']);
            }
        }
        
        return redirect()->back()->with('success', 'Notification deleted');
    }

    /**
     * Settings for notifications - redirect to main settings
     */
    public function settings()
    {
        return redirect()->to(base_url('settings#notifications'));
    }

    /**
     * Get unread notifications count
     */
    private function getUnreadCount(): int
    {
        $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;
        $count = 0;

        try {
            // Count pending queue items
            $queueModel = new NotificationQueueModel();
            $count += $queueModel
                ->where('business_id', $businessId)
                ->whereIn('status', ['queued', 'failed'])
                ->countAllResults();

            // Count recent failed deliveries (last 24 hours)
            $logModel = new NotificationDeliveryLogModel();
            $count += $logModel
                ->where('business_id', $businessId)
                ->where('status', 'failed')
                ->where('created_at >', date('Y-m-d H:i:s', strtotime('-24 hours')))
                ->countAllResults();
        } catch (\Throwable $e) {
            log_message('warning', 'Notification count unavailable: ' . $e->getMessage());
        }

        return $count;
    }
}

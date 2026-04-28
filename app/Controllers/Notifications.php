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
 * @see         app/Services/NotificationPolicyService.php for policy logic
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\NotificationQueueModel;
use App\Services\Appointment\AppointmentManualNotificationService;
use App\Services\NotificationCenterService;

class Notifications extends BaseController
{
    protected NotificationCenterService $notificationCenterService;
    private ?AppointmentManualNotificationService $appointmentManualNotificationService = null;

    public function __construct(
        ?NotificationCenterService $notificationCenterService = null,
        ?AppointmentManualNotificationService $appointmentManualNotificationService = null,
    )
    {
        $this->notificationCenterService = $notificationCenterService ?? new NotificationCenterService();
        $this->appointmentManualNotificationService = $appointmentManualNotificationService;
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

        return view('notifications/index', $this->notificationCenterService->buildIndexData(
            $this->request->getGet() ?? [],
            is_array($currentUser) ? $currentUser : null,
            $currentRole
        ));
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

        return $this->respondAction(true, 'Notification marked as read');
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

        return $this->respondAction(true, 'All notifications marked as read');
    }

    /**
     * Delete notification
     */
    public function delete($notificationId = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        if (!has_role('admin')) {
            return $this->respondAction(false, 'Only administrators can cancel queued notifications.', 403);
        }

        // Parse notification ID (format: log_123 or queue_456)
        if ($notificationId && strpos($notificationId, 'queue_') === 0) {
            $id = (int) str_replace('queue_', '', $notificationId);
            if ($id > 0) {
                $queueModel = new NotificationQueueModel();
                $updated = $queueModel->builder()
                    ->where('id', $id)
                    ->where('business_id', current_business_id())
                    ->update([
                        'status' => 'cancelled',
                        'last_error' => 'Manually cancelled',
                    ]);

                if ($updated) {
                    return $this->respondAction(true, 'Queued notification cancelled.');
                }
            }
        }

        return $this->respondAction(false, 'Unable to cancel the selected notification.', 404);
    }

    public function resend()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        if (!has_role('admin')) {
            return $this->respondAction(false, 'Only administrators can resend notifications.', 403);
        }

        $appointmentId = (int) ($this->request->getPost('appointment_id') ?? 0);
        $channel = trim((string) ($this->request->getPost('channel') ?? ''));
        $eventType = trim((string) ($this->request->getPost('event_type') ?? ''));

        if ($appointmentId <= 0 || $channel === '') {
            return $this->respondAction(false, 'Appointment and channel are required to resend a notification.', 400, [
                'appointment_id' => $appointmentId,
                'channel' => $channel,
            ]);
        }

        $result = $this->getAppointmentManualNotificationService()->send($appointmentId, $channel, $eventType !== '' ? $eventType : null);
        if (!($result['success'] ?? false)) {
            return $this->respondAction(
                false,
                (string) ($result['message'] ?? 'Failed to resend notification.'),
                (int) ($result['statusCode'] ?? 400),
                is_array($result['errors'] ?? null) ? $result['errors'] : []
            );
        }

        return $this->respondAction(true, (string) ($result['data']['message'] ?? 'Notification resent successfully.'));
    }

    /**
     * Settings for notifications - redirect to main settings
     */
    public function settings()
    {
        return redirect()->to(base_url('settings#notifications'));
    }

    private function getAppointmentManualNotificationService(): AppointmentManualNotificationService
    {
        if ($this->appointmentManualNotificationService === null) {
            $this->appointmentManualNotificationService = new AppointmentManualNotificationService();
        }

        return $this->appointmentManualNotificationService;
    }

    private function respondAction(bool $success, string $message, int $statusCode = 200, array $errors = [])
    {
        $redirect = $this->resolveRedirectTarget();

        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode($statusCode)->setJSON([
                'success' => $success,
                'message' => $message,
                'redirect' => $redirect,
                'errors' => $errors,
            ]);
        }

        return redirect()->to($redirect)->with($success ? 'success' : 'error', $message);
    }

    private function resolveRedirectTarget(): string
    {
        $fallback = base_url('notifications');
        $target = trim((string) ($this->request->getPost('redirect_to')
            ?? $this->request->getGet('redirect_to')
            ?? $this->request->getServer('HTTP_REFERER')
            ?? $fallback));

        if ($target === '') {
            return $fallback;
        }

        if (str_starts_with($target, base_url('/'))) {
            return $target;
        }

        if (str_starts_with($target, '/')) {
            return base_url(ltrim($target, '/'));
        }

        return $fallback;
    }

}

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
use App\Services\NotificationCenterService;

class Notifications extends BaseController
{
    protected NotificationCenterService $notificationCenterService;

    public function __construct(?NotificationCenterService $notificationCenterService = null)
    {
        $this->notificationCenterService = $notificationCenterService ?? new NotificationCenterService();
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
        return view('notifications/index', $this->notificationCenterService->buildIndexData($filter, $currentUser, $currentRole));
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

}

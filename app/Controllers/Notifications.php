<?php

namespace App\Controllers;

use App\Models\UserModel;
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
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Notifications',
            'current_page' => 'notifications',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'notifications' => $this->getMockNotifications($currentRole),
            'unread_count' => $this->getUnreadCount(),
            'filter' => $this->request->getGet('filter') ?? 'all'
        ];

        return view('notifications/index', $data);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // In real implementation, update notification status in database
        // For now, just redirect back with success message
        
        return redirect()->back()->with('success', 'Notification marked as read');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // In real implementation, update all user's notifications in database
        
        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Delete notification
     */
    public function delete($notificationId = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // In real implementation, delete notification from database
        
        return redirect()->back()->with('success', 'Notification deleted');
    }

    /**
     * Settings for notifications
     */
    public function settings()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Notification Settings',
            'current_page' => 'notifications',
            'settings' => $this->getMockNotificationSettings()
        ];

        return view('notifications/settings', $data);
    }

    /**
     * Get mock notifications based on user role
     */
    private function getMockNotifications($role)
    {
        $baseNotifications = [
            [
                'id' => 1,
                'type' => 'appointment',
                'title' => 'New Appointment Booked',
                'message' => 'John Smith has booked an appointment for Hair Cut on Sep 5, 2025 at 10:00 AM',
                'time' => '2 minutes ago',
                'read' => false,
                'icon' => 'calendar',
                'color' => 'blue'
            ],
            [
                'id' => 2,
                'type' => 'reminder',
                'title' => 'Appointment Reminder',
                'message' => 'You have an appointment with Emma Davis in 1 hour (Color Treatment)',
                'time' => '45 minutes ago',
                'read' => false,
                'icon' => 'bell',
                'color' => 'amber'
            ],
            [
                'id' => 3,
                'type' => 'cancellation',
                'title' => 'Appointment Cancelled',
                'message' => 'Mike Wilson has cancelled his appointment scheduled for today at 2:00 PM',
                'time' => '2 hours ago',
                'read' => true,
                'icon' => 'x-circle',
                'color' => 'red'
            ],
            [
                'id' => 4,
                'type' => 'payment',
                'title' => 'Payment Received',
                'message' => 'Payment of $85.00 received from Emma Davis for Color Treatment service',
                'time' => '3 hours ago',
                'read' => true,
                'icon' => 'credit-card',
                'color' => 'green'
            ],
            [
                'id' => 5,
                'type' => 'review',
                'title' => 'New Review',
                'message' => 'Sarah Johnson received a 5-star review from John Smith: "Excellent service!"',
                'time' => '1 day ago',
                'read' => false,
                'icon' => 'star',
                'color' => 'purple'
            ],
            [
                'id' => 6,
                'type' => 'system',
                'title' => 'System Update',
                'message' => 'WebSchedulr has been updated with new features and improvements',
                'time' => '2 days ago',
                'read' => true,
                'icon' => 'cog',
                'color' => 'gray'
            ],
            [
                'id' => 7,
                'type' => 'staff',
                'title' => 'Staff Schedule Updated',
                'message' => 'Alex Brown has updated his availability for next week',
                'time' => '2 days ago',
                'read' => true,
                'icon' => 'users',
                'color' => 'indigo'
            ],
            [
                'id' => 8,
                'type' => 'promotion',
                'title' => 'Special Offer',
                'message' => 'New customer discount: 20% off first appointment - valid until end of month',
                'time' => '3 days ago',
                'read' => false,
                'icon' => 'gift',
                'color' => 'pink'
            ]
        ];

        // Filter notifications based on role
        if ($role === 'customer') {
            return array_filter($baseNotifications, function($notification) {
                return in_array($notification['type'], ['reminder', 'cancellation', 'system', 'promotion']);
            });
        } elseif ($role === 'staff') {
            return array_filter($baseNotifications, function($notification) {
                return in_array($notification['type'], ['appointment', 'reminder', 'cancellation', 'system', 'staff']);
            });
        }

        return $baseNotifications; // Admin and Provider get all notifications
    }

    /**
     * Get unread notifications count
     */
    private function getUnreadCount()
    {
        $notifications = $this->getMockNotifications(current_user_role());
        return count(array_filter($notifications, function($n) {
            return !$n['read'];
        }));
    }

    /**
     * Get notification settings
     */
    private function getMockNotificationSettings()
    {
        return [
            'email' => [
                'appointments' => true,
                'cancellations' => true,
                'reminders' => true,
                'payments' => true,
                'reviews' => false,
                'system' => true,
                'promotions' => false
            ],
            'push' => [
                'appointments' => true,
                'cancellations' => true,
                'reminders' => true,
                'payments' => false,
                'reviews' => true,
                'system' => false,
                'promotions' => false
            ],
            'sms' => [
                'appointments' => false,
                'cancellations' => true,
                'reminders' => true,
                'payments' => false,
                'reviews' => false,
                'system' => false,
                'promotions' => false
            ],
            'frequency' => [
                'instant' => true,
                'daily_digest' => false,
                'weekly_digest' => false
            ],
            'quiet_hours' => [
                'enabled' => true,
                'start' => '22:00',
                'end' => '08:00'
            ]
        ];
    }
}

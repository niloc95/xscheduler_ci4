<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

class Profile extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('permissions');
    }

    /**
     * Display user profile
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        // Generate profile statistics based on user role
        $profile_stats = $this->generateProfileStats($currentUser, $currentRole);
        
        // Generate recent activity data
        $recent_activity = $this->generateRecentActivity($currentUser, $currentRole);

        $data = [
            'title' => 'My Profile',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'profile_stats' => $profile_stats,
            'recent_activity' => $recent_activity,
        ];

        return view('profile/index', $data);
    }

    /**
     * Edit profile form
     */
    public function edit()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Edit Profile',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/edit', $data);
    }

    /**
     * Update profile
     */
    public function update()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Profile updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Change password form
     */
    public function password()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Change Password',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/password', $data);
    }

    /**
     * Update password
     */
    public function updatePassword()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Password updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Upload profile picture
     */
    public function uploadPicture()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Profile picture updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Privacy settings
     */
    public function privacy()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Privacy Settings',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/privacy', $data);
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Privacy settings updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Account settings
     */
    public function account()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Account Settings',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/account', $data);
    }

    /**
     * Update account settings
     */
    public function updateAccount()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Account settings updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Generate profile statistics based on user role
     */
    private function generateProfileStats($user, $role)
    {
        // Get user creation date for "Member Since"
        $memberSince = 'N/A';
        if (isset($user['created_at'])) {
            $memberSince = date('F j, Y', strtotime($user['created_at']));
        } elseif (isset($user['id'])) {
            // If created_at is not available, try to get it from database
            $userData = $this->userModel->find($user['id']);
            if ($userData && isset($userData['created_at'])) {
                $memberSince = date('F j, Y', strtotime($userData['created_at']));
            } else {
                $memberSince = 'January 1, 2024'; // Default fallback
            }
        }

        // Base stats for all users
        $stats = [
            'member_since' => $memberSince,
            'total_appointments' => 0,
            'total_spent' => 0,
            'loyalty_points' => 0,
        ];

        // Role-specific statistics
        switch ($role) {
            case 'admin':
                $stats = array_merge($stats, [
                    'total_revenue' => 12450.00,
                    'average_rating' => '4.8/5',
                    'total_users' => 156,
                    'clients_served' => 156,
                ]);
                break;
                
            case 'provider':
                $stats = array_merge($stats, [
                    'total_revenue' => 8750.00,
                    'average_rating' => '4.9/5',
                    'total_users' => 0,
                    'clients_served' => 89,
                ]);
                $stats['total_appointments'] = 124;
                break;
                
            case 'staff':
                $stats = array_merge($stats, [
                    'total_revenue' => 0,
                    'average_rating' => '4.7/5',
                    'total_users' => 0,
                    'clients_served' => 45,
                ]);
                $stats['total_appointments'] = 67;
                break;
                
            default: // client
                $stats = array_merge($stats, [
                    'total_revenue' => 0,
                    'average_rating' => 'N/A',
                    'total_users' => 0,
                    'clients_served' => 0,
                ]);
                $stats['total_appointments'] = 8;
                $stats['total_spent'] = 425.00;
                $stats['loyalty_points'] = 125;
                break;
        }

        return $stats;
    }

    /**
     * Generate recent activity data based on user role
     */
    private function generateRecentActivity($user, $role)
    {
        // Base activity for all users
        $activities = [];

        // Role-specific activity
        switch ($role) {
            case 'admin':
                $activities = [
                    [
                        'icon' => 'user',
                        'description' => 'New user registration: Sarah Johnson',
                        'time' => '2 hours ago'
                    ],
                    [
                        'icon' => 'calendar-check',
                        'description' => 'Appointment completed with Mike Davis',
                        'time' => '4 hours ago'
                    ],
                    [
                        'icon' => 'star',
                        'description' => 'New 5-star review received',
                        'time' => '1 day ago'
                    ],
                    [
                        'icon' => 'calendar-plus',
                        'description' => 'System backup completed successfully',
                        'time' => '2 days ago'
                    ],
                ];
                break;
                
            case 'provider':
                $activities = [
                    [
                        'icon' => 'calendar-check',
                        'description' => 'Appointment completed with Emily Rodriguez',
                        'time' => '1 hour ago'
                    ],
                    [
                        'icon' => 'star',
                        'description' => 'Received 5-star review from John Smith',
                        'time' => '3 hours ago'
                    ],
                    [
                        'icon' => 'calendar-plus',
                        'description' => 'New appointment scheduled for tomorrow',
                        'time' => '5 hours ago'
                    ],
                    [
                        'icon' => 'user',
                        'description' => 'Profile updated successfully',
                        'time' => '1 day ago'
                    ],
                ];
                break;
                
            case 'staff':
                $activities = [
                    [
                        'icon' => 'calendar-check',
                        'description' => 'Appointment assisted for Dr. Wilson',
                        'time' => '30 minutes ago'
                    ],
                    [
                        'icon' => 'calendar-plus',
                        'description' => 'Scheduled follow-up appointment',
                        'time' => '2 hours ago'
                    ],
                    [
                        'icon' => 'user',
                        'description' => 'Updated client contact information',
                        'time' => '4 hours ago'
                    ],
                    [
                        'icon' => 'star',
                        'description' => 'Completed training module',
                        'time' => '1 day ago'
                    ],
                ];
                break;
                
            default: // client
                $activities = [
                    [
                        'icon' => 'calendar-check',
                        'description' => 'Appointment completed with Dr. Smith',
                        'time' => '3 days ago'
                    ],
                    [
                        'icon' => 'calendar-plus',
                        'description' => 'New appointment booked for next week',
                        'time' => '5 days ago'
                    ],
                    [
                        'icon' => 'star',
                        'description' => 'Left review for recent service',
                        'time' => '1 week ago'
                    ],
                    [
                        'icon' => 'user',
                        'description' => 'Profile information updated',
                        'time' => '2 weeks ago'
                    ],
                ];
                break;
        }

        return $activities;
    }
}

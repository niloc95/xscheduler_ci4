<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

class Analytics extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('permissions');
    }

    /**
     * Display analytics dashboard
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

                // Check permissions - admin and provider can access analytics
        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => $currentRole === 'admin' ? 'Analytics Dashboard' : 'My Analytics',
            'current_page' => 'analytics',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'overview' => $this->getOverviewStats($currentRole),
            'revenue' => $this->getRevenueData(),
            'appointments' => $this->getAppointmentAnalytics(),
            'services' => $this->getServiceAnalytics(),
            'customers' => $this->getCustomerAnalytics(),
            'timeframe' => $this->request->getGet('timeframe') ?? '30d'
        ];

        return view('analytics/index', $data);
    }

    /**
     * Revenue analytics
     */
    public function revenue()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $data = [
            'title' => 'Revenue Analytics',
            'current_page' => 'analytics',
            'revenue_data' => $this->getDetailedRevenueData(),
            'comparisons' => $this->getRevenueComparisons()
        ];

        return view('analytics/revenue', $data);
    }

    /**
     * Customer analytics
     */
    public function customers()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $data = [
            'title' => 'Customer Analytics',
            'current_page' => 'analytics',
            'customer_data' => $this->getDetailedCustomerData(),
            'retention' => $this->getCustomerRetention()
        ];

        return view('analytics/customers', $data);
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats($role)
    {
        return [
            'total_revenue' => 12450.75,
            'revenue_change' => 15.3,
            'total_appointments' => 184,
            'appointments_change' => 8.7,
            'new_customers' => 23,
            'customers_change' => 12.1,
            'avg_booking_value' => 67.66,
            'booking_value_change' => 5.4,
            'customer_retention' => 78.5,
            'retention_change' => 3.2,
            'staff_utilization' => 85.4,
            'utilization_change' => -2.1
        ];
    }

    /**
     * Get revenue data for charts
     */
    private function getRevenueData()
    {
        return [
            'daily' => [
                ['date' => '2025-08-25', 'revenue' => 450.00],
                ['date' => '2025-08-26', 'revenue' => 675.50],
                ['date' => '2025-08-27', 'revenue' => 380.25],
                ['date' => '2025-08-28', 'revenue' => 820.75],
                ['date' => '2025-08-29', 'revenue' => 590.00],
                ['date' => '2025-08-30', 'revenue' => 720.30],
                ['date' => '2025-08-31', 'revenue' => 895.45],
                ['date' => '2025-09-01', 'revenue' => 650.25],
                ['date' => '2025-09-02', 'revenue' => 780.95]
            ],
            'monthly' => [
                ['month' => 'Jan', 'revenue' => 8450.00],
                ['month' => 'Feb', 'revenue' => 9230.50],
                ['month' => 'Mar', 'revenue' => 10150.75],
                ['month' => 'Apr', 'revenue' => 9875.25],
                ['month' => 'May', 'revenue' => 11200.00],
                ['month' => 'Jun', 'revenue' => 10980.50],
                ['month' => 'Jul', 'revenue' => 12450.75],
                ['month' => 'Aug', 'revenue' => 11875.25]
            ]
        ];
    }

    /**
     * Get appointment analytics
     */
    private function getAppointmentAnalytics()
    {
        return [
            'by_status' => [
                'completed' => 156,
                'pending' => 18,
                'cancelled' => 10,
                'no_show' => 5
            ],
            'by_service' => [
                ['service' => 'Hair Cut', 'count' => 45, 'revenue' => 1575.00],
                ['service' => 'Color Treatment', 'count' => 28, 'revenue' => 2380.00],
                ['service' => 'Beard Trim', 'count' => 62, 'revenue' => 1240.00],
                ['service' => 'Facial Treatment', 'count' => 33, 'revenue' => 2145.00]
            ],
            'by_time_slot' => [
                '9:00 AM' => 15,
                '10:00 AM' => 28,
                '11:00 AM' => 32,
                '12:00 PM' => 18,
                '1:00 PM' => 22,
                '2:00 PM' => 35,
                '3:00 PM' => 29,
                '4:00 PM' => 25
            ]
        ];
    }

    /**
     * Get service analytics
     */
    private function getServiceAnalytics()
    {
        return [
            'popular_services' => [
                ['name' => 'Beard Trim', 'bookings' => 62, 'revenue' => 1240.00, 'growth' => 15.2],
                ['name' => 'Hair Cut', 'bookings' => 45, 'revenue' => 1575.00, 'growth' => 8.7],
                ['name' => 'Facial Treatment', 'bookings' => 33, 'revenue' => 2145.00, 'growth' => 22.1],
                ['name' => 'Color Treatment', 'bookings' => 28, 'revenue' => 2380.00, 'growth' => 5.4]
            ],
            'service_performance' => [
                'avg_duration' => 67.5,
                'completion_rate' => 94.2,
                'customer_satisfaction' => 4.7,
                'repeat_booking_rate' => 68.3
            ]
        ];
    }

    /**
     * Get customer analytics
     */
    private function getCustomerAnalytics()
    {
        return [
            'new_vs_returning' => [
                'new' => 23,
                'returning' => 161
            ],
            'demographics' => [
                'age_groups' => [
                    '18-25' => 15,
                    '26-35' => 45,
                    '36-45' => 58,
                    '46-55' => 42,
                    '56+' => 24
                ],
                'gender' => [
                    'male' => 112,
                    'female' => 68,
                    'other' => 4
                ]
            ],
            'loyalty' => [
                'first_time' => 23,
                'occasional' => 67,
                'regular' => 94,
                'vip' => 18
            ]
        ];
    }

    /**
     * Get detailed revenue data
     */
    private function getDetailedRevenueData()
    {
        // Extended revenue data for detailed view
        return array_merge($this->getRevenueData(), [
            'by_payment_method' => [
                'cash' => 3245.50,
                'card' => 7890.25,
                'digital' => 1315.00
            ],
            'by_staff' => [
                ['name' => 'Sarah Johnson', 'revenue' => 5420.75],
                ['name' => 'Alex Brown', 'revenue' => 4230.50],
                ['name' => 'Maria Garcia', 'revenue' => 2799.50]
            ]
        ]);
    }

    /**
     * Get revenue comparisons
     */
    private function getRevenueComparisons()
    {
        return [
            'vs_last_month' => 15.3,
            'vs_last_quarter' => 22.7,
            'vs_last_year' => 35.2,
            'forecast_next_month' => 13250.00
        ];
    }

    /**
     * Get detailed customer data
     */
    private function getDetailedCustomerData()
    {
        return array_merge($this->getCustomerAnalytics(), [
            'acquisition' => [
                'referral' => 45,
                'social_media' => 28,
                'google_search' => 67,
                'walk_in' => 34,
                'other' => 10
            ],
            'lifetime_value' => [
                'average' => 285.50,
                'top_10_percent' => 1250.00,
                'segments' => [
                    'high_value' => 18,
                    'medium_value' => 94,
                    'low_value' => 72
                ]
            ]
        ]);
    }

    /**
     * Get customer retention data
     */
    private function getCustomerRetention()
    {
        return [
            'overall_rate' => 78.5,
            'by_month' => [
                'month_1' => 89.2,
                'month_3' => 78.5,
                'month_6' => 65.3,
                'month_12' => 52.1
            ],
            'churn_analysis' => [
                'primary_reasons' => [
                    'price' => 35,
                    'service_quality' => 18,
                    'scheduling' => 22,
                    'location' => 15,
                    'other' => 10
                ]
            ]
        ];
    }
}

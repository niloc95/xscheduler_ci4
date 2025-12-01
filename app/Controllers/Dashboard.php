<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;

class Dashboard extends BaseController
{
    protected $userModel;
    protected $serviceModel;
    protected $appointmentModel;
    protected $customerModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->appointmentModel = new AppointmentModel();
        $this->customerModel = new CustomerModel();
    }

    /**
     * Check if the application setup has been completed
     */
    private function isSetupCompleted(): bool
    {
        helper('setup');
        return is_setup_completed();
    }

    public function index()
    {
        // Check if setup is completed first
        if (!$this->isSetupCompleted()) {
            return redirect()->to('/setup')->with('info', 'Please complete the initial setup first.');
        }

        try {
            // Get current user (from session or fallback to first admin user)
            $currentUser = session()->get('user');
            
            // If no user in session, get the first admin user from database
            if (!$currentUser) {
                $adminUser = $this->userModel->getFirstAdmin();
                if ($adminUser) {
                    $currentUser = [
                        'name' => $adminUser['name'],
                        'role' => $adminUser['role'],
                        'email' => $adminUser['email']
                    ];
                } else {
                    // Fallback if no admin user exists
                    $currentUser = [
                        'name' => 'System Administrator',
                        'role' => 'admin',
                        'email' => 'admin@webschedulr.com'
                    ];
                }
            }

            // Get real statistics from database
            $userStats = $this->userModel->getStats();
            $appointmentStats = $this->appointmentModel->getStats();
            $serviceStats = $this->serviceModel->getStats();

            // Calculate trends for dashboard cards
            $userTrend = $this->userModel->getTrend();
            $appointmentTrend = $this->appointmentModel->getTrend();
            $pendingTrend = $this->appointmentModel->getPendingTrend();
            $revenueTrend = $this->appointmentModel->getRevenueTrend();

            // Calculate revenue
            $monthlyRevenue = $this->appointmentModel->getRevenue('month');
            $weeklyRevenue = $this->appointmentModel->getRevenue('week');

            // Get recent activities (appointments)
            $recentActivities = $this->appointmentModel->getRecentActivity();

            // Format recent activities for display
            $formattedActivities = [];
            foreach ($recentActivities as $activity) {
                $action = '';
                $status_class = 'active';
                
                switch ($activity['status']) {
                    case 'booked':
                        $action = 'Scheduled appointment for ' . $activity['service_name'];
                        $status_class = 'active';
                        break;
                    case 'completed':
                        $action = 'Completed appointment for ' . $activity['service_name'];
                        $status_class = 'active';
                        break;
                    case 'cancelled':
                        $action = 'Cancelled appointment for ' . $activity['service_name'];
                        $status_class = 'cancelled';
                        break;
                    case 'rescheduled':
                        $action = 'Rescheduled appointment for ' . $activity['service_name'];
                        $status_class = 'pending';
                        break;
                }

                $formattedActivities[] = [
                    'user_name' => $activity['customer_name'],
                    'activity' => $action,
                    'status' => $status_class,
                    'date' => date('Y-m-d', strtotime($activity['updated_at']))
                ];
            }

            $data = [
                'user' => $currentUser,
                'stats' => [
                    'total_users' => $userStats['total'],
                    'active_sessions' => $appointmentStats['upcoming'], // Using upcoming appointments as active sessions
                    'pending_tasks' => $appointmentStats['today'], // Today's appointments as pending tasks
                    'revenue' => round($monthlyRevenue, 2)
                ],
                // Trend data for dashboard cards (real month-over-month calculations)
                'trends' => [
                    'users' => $userTrend,
                    'appointments' => $appointmentTrend,
                    'pending' => $pendingTrend,
                    'revenue' => $revenueTrend
                ],
                // Provide services list for embedded scheduler section
                'servicesList' => $this->serviceModel->orderBy('name', 'ASC')->findAll(),
                'detailed_stats' => [
                    'users' => $userStats,
                    'appointments' => $appointmentStats,
                    'services' => $serviceStats,
                    'revenue' => [
                        'monthly' => $monthlyRevenue,
                        'weekly' => $weeklyRevenue,
                        'today' => $this->appointmentModel->getRevenue('today')
                    ]
                ],
                'recent_activities' => $formattedActivities
            ];

            // Use the populated dashboard view (dashboard.php); dashboard_fixed.php is empty in this branch.
            return view('dashboard', $data);
        } catch (\Exception $e) {
            // If there's an error, return a simple message with database fallback
            log_message('error', 'Dashboard Error: ' . $e->getMessage());
            
            // Fallback to mock data if database is not available
            $fallbackData = [
                'user' => [
                    'name' => 'System Administrator',
                    'role' => 'admin',
                    'email' => 'admin@webschedulr.com'
                ],
                'stats' => [
                    'total_users' => 0,
                    'active_sessions' => 0,
                    'pending_tasks' => 0,
                    'revenue' => 0
                ],
                'trends' => [
                    'users' => ['percentage' => 0, 'direction' => 'neutral', 'current' => 0, 'previous' => 0],
                    'appointments' => ['percentage' => 0, 'direction' => 'neutral', 'current' => 0, 'previous' => 0],
                    'pending' => ['percentage' => 0, 'direction' => 'neutral', 'current' => 0, 'previous' => 0],
                    'revenue' => ['percentage' => 0, 'direction' => 'neutral', 'current' => 0, 'previous' => 0]
                ],
                'recent_activities' => [],
                'servicesList' => []
            ];
            
            return view('dashboard', $fallbackData);
        }
    }

    public function api()
    {
        try {
            // Real-time stats API endpoint for AJAX requests
            $userStats = $this->userModel->getStats();
            $appointmentStats = $this->appointmentModel->getStats();
            $monthlyRevenue = $this->appointmentModel->getRevenue('month');

            $stats = [
                'total_users' => $userStats['total'],
                'active_sessions' => $appointmentStats['upcoming'],
                'pending_tasks' => $appointmentStats['today'],
                'revenue' => round($monthlyRevenue, 2)
            ];

            return $this->response->setJSON($stats);
        } catch (\Exception $e) {
            // Fallback to mock data for API
            $stats = [
                'total_users' => 0,
                'active_sessions' => 0,
                'pending_tasks' => 0,
                'revenue' => 0
            ];
            
            return $this->response->setJSON($stats);
        }
    }

    public function charts()
    {
        try {
            // Real chart data from database
            $userGrowth = $this->userModel->getUserGrowthData(6);
            $appointmentWeekly = $this->appointmentModel->getChartData('week');
            $statusDistribution = $this->appointmentModel->getStatusDistribution();

            $chartData = [
                'userGrowth' => $userGrowth,
                'activity' => $statusDistribution,
                'appointments' => $appointmentWeekly
            ];

            return $this->response->setJSON($chartData);
        } catch (\Exception $e) {
            // Fallback chart data
            $chartData = [
                'userGrowth' => [
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    'data' => [0, 0, 0, 0, 0, 0]
                ],
                'activity' => [
                    'labels' => ['No Data'],
                    'data' => [1]
                ],
                'appointments' => [
                    'labels' => ['No Data'],
                    'data' => [0]
                ]
            ];

            return $this->response->setJSON($chartData);
        }
    }

    public function test()
    {
        // Simple test endpoint
        return view('test/welcome_message');
    }

    public function simple()
    {
        // Very basic dashboard without complex assets
        $data = [
            'title' => 'WebSchedulr Dashboard',
            'message' => 'Dashboard is working!',
            'stats' => [
                'users' => 150,
                'sessions' => 45,
                'tasks' => 23
            ]
        ];
        
        return view('test/dashboard_test', $data);
    }

    /**
     * Detailed analytics endpoint
     */
    public function analytics()
    {
        try {
            $data = [
                'users' => [
                    'total' => $this->userModel->getStats(),
                    'recent' => $this->userModel->getRecentUsers(10),
                    'growth' => $this->userModel->getUserGrowthData(12)
                ],
                'appointments' => [
                    'stats' => $this->appointmentModel->getStats(),
                    'recent' => $this->appointmentModel->getRecentAppointments(20),
                    'weekly_data' => $this->appointmentModel->getChartData('week'),
                    'monthly_data' => $this->appointmentModel->getChartData('month'),
                    'status_distribution' => $this->appointmentModel->getStatusDistribution()
                ],
                'services' => [
                    'stats' => $this->serviceModel->getStats(),
                    'popular' => $this->serviceModel->getPopularServices(10)
                ],
                'revenue' => [
                    'today' => $this->appointmentModel->getRevenue('today'),
                    'week' => $this->appointmentModel->getRevenue('week'),
                    'month' => $this->appointmentModel->getRevenue('month')
                ]
            ];

            return $this->response->setJSON($data);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Unable to fetch analytics data',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Database status check for dashboard
     */
    public function status()
    {
        try {
            $db = \Config\Database::connect();
            
            // Check if tables exist
            $tables = ['users', 'services', 'appointments'];
            $tableStatus = [];
            
            foreach ($tables as $table) {
                $tableStatus[$table] = $db->tableExists($table);
            }
            
            // Get quick counts
            $counts = [];
            if ($tableStatus['users']) {
                $counts['users'] = $this->userModel->countAll();
            }
            if ($tableStatus['services']) {
                $counts['services'] = $this->serviceModel->countAll();
            }
            if ($tableStatus['appointments']) {
                $counts['appointments'] = $this->appointmentModel->countAll();
            }
            
            return $this->response->setJSON([
                'database_connected' => true,
                'tables' => $tableStatus,
                'counts' => $counts
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'database_connected' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function realData()
    {
        try {
            // Get current user (for now using session or default admin)
            $currentUser = session()->get('user') ?? [
                'name' => 'System Administrator',
                'role' => 'admin',
                'email' => 'admin@webschedulr.com'
            ];

            // Get real statistics from database
            $userStats = $this->userModel->getStats();
            $appointmentStats = $this->appointmentModel->getStats();
            $serviceStats = $this->serviceModel->getStats();

            // Calculate revenue
            $monthlyRevenue = $this->appointmentModel->getRevenue('month');

            // Get recent activities (appointments)
            $recentActivities = $this->appointmentModel->getRecentActivity();

            // Format recent activities for display
            $formattedActivities = [];
            foreach ($recentActivities as $activity) {
                $action = '';
                $status_class = 'active';
                
                switch ($activity['status']) {
                    case 'booked':
                        $action = 'Scheduled appointment for ' . $activity['service_name'];
                        $status_class = 'active';
                        break;
                    case 'completed':
                        $action = 'Completed appointment for ' . $activity['service_name'];
                        $status_class = 'active';
                        break;
                    case 'cancelled':
                        $action = 'Cancelled appointment for ' . $activity['service_name'];
                        $status_class = 'cancelled';
                        break;
                    case 'rescheduled':
                        $action = 'Rescheduled appointment for ' . $activity['service_name'];
                        $status_class = 'pending';
                        break;
                }

                $formattedActivities[] = [
                    'user_name' => $activity['customer_name'],
                    'activity' => $action,
                    'status' => $status_class,
                    'date' => date('Y-m-d', strtotime($activity['updated_at']))
                ];
            }

            $data = [
                'user' => $currentUser,
                'stats' => [
                    'total_users' => $userStats['total'],
                    'active_sessions' => $appointmentStats['upcoming'], // Using upcoming appointments as active sessions
                    'pending_tasks' => $appointmentStats['today'], // Today's appointments as pending tasks
                    'revenue' => round($monthlyRevenue, 2)
                ],
                'recent_activities' => $formattedActivities
            ];

            return view('dashboard_real_data', $data);
        } catch (\Exception $e) {
            // If there's an error, return a simple message with database fallback
            log_message('error', 'Dashboard Real Data Error: ' . $e->getMessage());
            
            // Fallback to mock data if database is not available
            $fallbackData = [
                'user' => [
                    'name' => 'System Administrator',
                    'role' => 'admin',
                    'email' => 'admin@webschedulr.com'
                ],
                'stats' => [
                    'total_users' => 0,
                    'active_sessions' => 0,
                    'pending_tasks' => 0,
                    'revenue' => 0
                ],
                'recent_activities' => []
            ];
            
            return view('dashboard_real_data', $fallbackData);
        }
    }

    public function test_db()
    {
        try {
            $userCount = $this->userModel->countAll();
            $serviceCount = $this->serviceModel->countAll();
            $appointmentCount = $this->appointmentModel->countAll();
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Database connection working',
                'counts' => [
                    'users' => $userCount,
                    'services' => $serviceCount,
                    'appointments' => $appointmentCount
                ]
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Global search endpoint for header search
     * Searches both customers and appointments
     */
    public function search()
    {
        // Completely bypass CI4's response system
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Disable the debug toolbar entirely
        if (defined('CI_DEBUG') && CI_DEBUG) {
            ini_set('display_errors', '0');
        }
        
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'success' => false]);
            exit(0);
        }

        $q = trim((string) $this->request->getGet('q'));
        
        try {
            $customers = [];
            $appointments = [];
            
            if ($q !== '') {
                // Search customers
                $customers = $this->customerModel->search(['q' => $q, 'limit' => 10]);
                
                // Search appointments - search by customer name, service name, or notes
                $appointmentsQuery = $this->appointmentModel
                    ->select('xs_appointments.*, 
                             CONCAT(xs_customers.first_name, " ", xs_customers.last_name) as customer_name,
                             xs_customers.email as customer_email,
                             xs_services.name as service_name')
                    ->join('xs_customers', 'xs_customers.id = xs_appointments.customer_id', 'left')
                    ->join('xs_services', 'xs_services.id = xs_appointments.service_id', 'left')
                    ->groupStart()
                        ->like('xs_customers.first_name', $q)
                        ->orLike('xs_customers.last_name', $q)
                        ->orLike('xs_customers.email', $q)
                        ->orLike('xs_services.name', $q)
                        ->orLike('xs_appointments.notes', $q)
                    ->groupEnd()
                    ->orderBy('xs_appointments.start_time', 'DESC')
                    ->limit(10);
                
                $appointments = $appointmentsQuery->findAll();
            }

            echo json_encode([
                'success' => true,
                'customers' => $customers,
                'appointments' => $appointments,
                'counts' => [
                    'customers' => count($customers),
                    'appointments' => count($appointments),
                    'total' => count($customers) + count($appointments)
                ]
            ]);
            exit(0);
        } catch (\Exception $e) {
            log_message('error', '[Dashboard::search] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            ]);
            exit(0);
        }
    }
}

<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Services\DashboardService;
use App\Services\AuthorizationService;

class Dashboard extends BaseController
{
    protected $userModel;
    protected $serviceModel;
    protected $appointmentModel;
    protected $customerModel;
    protected $dashboardService;
    protected $authService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->appointmentModel = new AppointmentModel();
        $this->customerModel = new CustomerModel();
        $this->dashboardService = new DashboardService();
        $this->authService = new AuthorizationService();
    }

    /**
     * Dashboard landing page
     */
    public function index()
    {
        // Check if setup is completed first
        if (!is_setup_completed()) {
            return redirect()->to('/setup')->with('info', 'Please complete the initial setup first.');
        }

        try {
            // Get current user from session
            $currentUser = session()->get('user');
            $userId = session()->get('user_id');
            
            // If no user in session or missing required data, redirect to login
            if (!$currentUser || !$userId || !session()->get('isLoggedIn')) {
                session()->destroy();
                return redirect()->to('/login')->with('error', 'Please log in to access the dashboard.');
            }

            // Extract user info for authorization
            $userRole = $this->authService->getUserRole($currentUser);
            $providerId = $this->authService->getProviderId($currentUser);

            // Enforce dashboard access
            $this->authService->enforce(
                $this->authService->canViewDashboardMetrics($userRole),
                'You do not have permission to view the dashboard'
            );

            // Get provider scope for data filtering
            $providerScope = $this->authService->getProviderScope($userRole, $providerId);

            // Get dashboard context
            $context = $this->dashboardService->getDashboardContext($userId, $userRole, $providerId);

            // Get today's metrics (with caching)
            $metrics = $this->dashboardService->getCachedMetrics($providerScope);

            // Get today's schedule
            $schedule = $this->dashboardService->getTodaySchedule($providerScope);

            // Get alerts
            $alerts = $this->dashboardService->getAlerts($providerScope);

            // Get upcoming appointments
            $upcoming = $this->dashboardService->getUpcomingAppointments($providerScope);

            // Get provider availability
            $availability = $this->dashboardService->getProviderAvailability($providerScope);

            // Get booking status (admin only)
            $bookingStatus = null;
            if ($this->authService->canViewBookingStatus($userRole)) {
                $bookingStatus = $this->dashboardService->getBookingStatus();
            }

            // Legacy stats for backward compatibility with existing views
            $userStats = $this->userModel->getStats();
            $appointmentStats = $this->appointmentModel->getStats();
            $serviceStats = $this->serviceModel->getStats();
            $monthlyRevenue = $this->appointmentModel->getRevenue('month');
            $weeklyRevenue = $this->appointmentModel->getRevenue('week');

            // Build view data
            $data = [
                'user' => $currentUser,
                'context' => $context,
                'metrics' => $metrics,
                'schedule' => $schedule,
                'alerts' => $alerts,
                'upcoming' => $upcoming,
                'availability' => $availability,
                'booking_status' => $bookingStatus,
                'provider_scope' => $providerScope,
                
                // Legacy data for backward compatibility
                'stats' => [
                    'total_users' => $userStats['total'],
                    'active_sessions' => $appointmentStats['upcoming'],
                    'pending_tasks' => $appointmentStats['today'],
                    'revenue' => round($monthlyRevenue, 2)
                ],
                'trends' => [
                    'users' => $this->userModel->getTrend(),
                    'appointments' => $this->appointmentModel->getTrend(),
                    'pending' => $this->appointmentModel->getPendingTrend(),
                    'revenue' => $this->appointmentModel->getRevenueTrend()
                ],
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
                'recent_activities' => $this->dashboardService->formatRecentActivities(
                    $this->appointmentModel->getRecentActivity()
                )
            ];

            // Use the refactored landing view with TailAdmin-style components
            // To revert: change 'landing_refactored' back to 'landing'
            return view('dashboard/landing', $data);
            
        } catch (\RuntimeException $e) {
            // Authorization error - show error page instead of redirecting to avoid loops
            log_message('warning', 'Dashboard Authorization Error: ' . $e->getMessage() . ' | User: ' . json_encode(session()->get('user')) . ' | Role: ' . ($this->authService->getUserRole(session()->get('user'))));
            
            // Don't redirect to login if user is already logged in (causes loop)
            // Instead return a 403 response with error details
            return $this->response->setStatusCode(403)->setBody(
                '<h1>Access Denied</h1>' .
                '<p>' . esc($e->getMessage()) . '</p>' .
                '<p>Your role: ' . esc($this->authService->getUserRole(session()->get('user'))) . '</p>' .
                '<p>User data: ' . esc(json_encode(session()->get('user'))) . '</p>' .
                '<p><a href="/logout">Logout</a> | <a href="/">Home</a></p>'
            );
            
        } catch (\Exception $e) {
            // If there's an error, log with full context and return fallback
            log_message('error', 'Dashboard Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            
            // In development, show detailed error
            if (ENVIRONMENT === 'development') {
                throw $e;
            }
            
            // Fallback to mock data if database is not available
            $fallbackData = [
                'user' => [
                    'name' => 'System Administrator',
                    'role' => 'admin',
                    'email' => 'admin@webschedulr.com'
                ],
                'context' => [
                    'business_name' => 'WebSchedulr',
                    'current_date' => date('Y-m-d'),
                    'timezone' => 'UTC',
                    'user_role' => 'admin'
                ],
                'metrics' => [
                    'total' => 0,
                    'upcoming' => 0,
                    'pending' => 0,
                    'cancelled' => 0
                ],
                'schedule' => [],
                'alerts' => [],
                'upcoming' => [],
                'availability' => [],
                'booking_status' => null,
                'stats' => [
                    'total_users' => 0,
                    'active_sessions' => 0,
                    'pending_tasks' => 0,
                    'revenue' => 0
                ],
                'trends' => [
                    'users' => ['percentage' => 0, 'direction' => 'neutral'],
                    'appointments' => ['percentage' => 0, 'direction' => 'neutral'],
                    'pending' => ['percentage' => 0, 'direction' => 'neutral'],
                    'revenue' => ['percentage' => 0, 'direction' => 'neutral']
                ],
                'recent_activities' => [],
                'servicesList' => []
            ];
            
            return view('dashboard', $fallbackData);
        }
    }

    /**
     * Real-time stats API endpoint for AJAX requests
     */
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

    /**
     * API endpoint for dashboard metrics
     * Used by landing view for real-time updates
     */
    public function apiMetrics()
    {
        // Set JSON header
        $this->response->setHeader('Content-Type', 'application/json');
        
        try {
            // Get current user from session
            $currentUser = session()->get('user');
            if (!$currentUser) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Please log in to access dashboard metrics'
                ]);
            }

            // Get user info
            $userRole = $this->authService->getUserRole($currentUser);
            $providerId = $this->authService->getProviderId($currentUser);
            
            // Check authorization
            if (!$this->authService->canViewDashboardMetrics($userRole)) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to view dashboard metrics'
                ]);
            }
            
            $providerScope = $this->authService->getProviderScope($userRole, $providerId);

            // Get fresh metrics (bypass cache for API calls)
            $metrics = $this->dashboardService->getTodayMetrics($providerScope);

            return $this->response->setJSON([
                'success' => true,
                'data' => $metrics,
                'timestamp' => time()
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Dashboard API Metrics Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Internal Server Error',
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to fetch metrics',
                'data' => [
                    'total' => 0,
                    'upcoming' => 0,
                    'pending' => 0,
                    'cancelled' => 0
                ]
            ]);
        }
    }

    public function charts()
    {
        try {
            // Get period from query parameter (default: month)
            $period = $this->request->getGet('period') ?? 'month';
            
            // Validate period
            $validPeriods = ['day', 'week', 'month', 'year'];
            if (!in_array($period, $validPeriods)) {
                $period = 'month';
            }
            
            // Get chart data based on period
            $appointmentGrowth = $this->appointmentModel->getAppointmentGrowth($period);
            $providerServices = $this->appointmentModel->getProviderServicesByPeriod($period);
            // Using new consolidated status method with colors
            $statusDistribution = $this->appointmentModel->getStatusStats([
                'format' => 'chart',
                'includeColors' => true
            ]);

            $chartData = [
                'appointmentGrowth' => $appointmentGrowth,
                'servicesByProvider' => $providerServices,
                'statusDistribution' => $statusDistribution,
                'period' => $period
            ];

            return $this->response->setJSON($chartData);
        } catch (\Exception $e) {
            // Fallback chart data
            $chartData = [
                'appointmentGrowth' => [
                    'labels' => ['No Data'],
                    'data' => [0]
                ],
                'servicesByProvider' => [
                    'labels' => ['No Data'],
                    'data' => [0]
                ],
                'statusDistribution' => [
                    'labels' => ['No Data'],
                    'data' => [0],
                    'colors' => ['#9aa0a6']
                ],
                'error' => $e->getMessage()
            ];

            return $this->response->setJSON($chartData);
        }
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
                    // Using consolidated getAppointmentGrowth() method
                    'weekly_data' => $this->appointmentModel->getAppointmentGrowth('week'),
                    'monthly_data' => $this->appointmentModel->getAppointmentGrowth('month'),
                    // Using consolidated status method
                    'status_distribution' => $this->appointmentModel->getStatusStats(['format' => 'chart'])
                ],
                'services' => [
                    'stats' => $this->serviceModel->getStats(),
                    'popular' => $this->serviceModel->getPopularServices(10)
                ],
                'revenue' => [
                    // Using getRealRevenue instead of deprecated getRevenue
                    'today' => $this->appointmentModel->getRealRevenue('today'),
                    'week' => $this->appointmentModel->getRealRevenue('week'),
                    'month' => $this->appointmentModel->getRealRevenue('month')
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

    /**
     * Global search endpoint for header search
     * Searches both customers and appointments
     */
    public function search()
    {
        // Redirect to dedicated Search controller
        $searchController = new \App\Controllers\Search();
        return $searchController->dashboard();
    }
}

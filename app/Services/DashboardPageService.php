<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;

class DashboardPageService
{
    protected UserModel $userModel;
    protected ServiceModel $serviceModel;
    protected AppointmentModel $appointmentModel;
    protected DashboardService $dashboardService;
    protected AuthorizationService $authService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->appointmentModel = new AppointmentModel();
        $this->dashboardService = new DashboardService();
        $this->authService = new AuthorizationService();
    }

    /**
     * @return array|\CodeIgniter\HTTP\RedirectResponse
     */
    public function resolveLandingSession()
    {
        $currentUser = session()->get('user');
        $userId = session()->get('user_id');

        if (!$currentUser || !$userId || !session()->get('isLoggedIn')) {
            session()->destroy();

            return redirect()->to(base_url('auth/login'))->with('error', 'Please log in to access the dashboard.');
        }

        $userRole = $this->authService->getUserRole($currentUser);
        $providerId = $this->authService->getProviderId($currentUser);
        $providerScope = $this->authService->getProviderScope($userRole, $providerId);

        $this->authService->enforce(
            $this->authService->canViewDashboardMetrics($userRole),
            'You do not have permission to view the dashboard'
        );

        return [
            'currentUser' => $currentUser,
            'userRole' => $userRole,
            'providerId' => $providerId,
            'providerScope' => $providerScope,
        ];
    }

    public function buildLandingViewData(array $sessionData): array
    {
        $currentUser = $sessionData['currentUser'];
        $userRole = $sessionData['userRole'];
        $providerId = $sessionData['providerId'];
        $providerScope = $sessionData['providerScope'];
        $userId = (int) session()->get('user_id');
        $scopeProviderId = is_array($providerScope)
            ? ($providerScope['provider_id'] ?? $providerId)
            : $providerScope;

        $context = $this->dashboardService->getDashboardContext($userId, $userRole, $providerId);
        $metrics = $this->dashboardService->getCachedMetrics($scopeProviderId);
        $schedule = $this->dashboardService->getTodaySchedule($scopeProviderId);
        $alerts = $this->dashboardService->getAlerts($scopeProviderId);
        $upcoming = $this->dashboardService->getUpcomingAppointments($scopeProviderId);
        $availability = $this->dashboardService->getProviderAvailability($scopeProviderId);
        $userStats = $this->userModel->getStats();
        $appointmentStats = $this->appointmentModel->getStats();
        $serviceStats = $this->serviceModel->getStats();
        $monthlyRevenue = $this->appointmentModel->getRevenue('month');
        $weeklyRevenue = $this->appointmentModel->getRevenue('week');

        return [
            'user' => $currentUser,
            'context' => $context,
            'metrics' => $metrics,
            'schedule' => $schedule,
            'alerts' => $alerts,
            'upcoming' => $upcoming,
            'availability' => $availability,
            'booking_status' => $this->authService->canViewBookingStatus($userRole)
                ? $this->dashboardService->getBookingStatus()
                : null,
            'provider_scope' => $providerScope,
            'stats' => [
                'total_users' => $userStats['total'],
                'active_sessions' => $appointmentStats['upcoming'],
                'pending_tasks' => $appointmentStats['today'],
                'revenue' => round($monthlyRevenue, 2),
            ],
            'trends' => [
                'users' => $this->userModel->getTrend(),
                'appointments' => $this->appointmentModel->getTrend(),
                'pending' => $this->appointmentModel->getPendingTrend(),
                'revenue' => $this->appointmentModel->getRevenueTrend(),
            ],
            'servicesList' => $this->serviceModel->orderBy('name', 'ASC')->findAll(),
            'detailed_stats' => [
                'users' => $userStats,
                'appointments' => $appointmentStats,
                'services' => $serviceStats,
                'revenue' => [
                    'monthly' => $monthlyRevenue,
                    'weekly' => $weeklyRevenue,
                    'today' => $this->appointmentModel->getRevenue('today'),
                ],
            ],
            'recent_activities' => $this->dashboardService->formatRecentActivities(
                $this->appointmentModel->getRecentActivity()
            ),
        ];
    }

    public function getFallbackLandingViewData(): array
    {
        return [
            'user' => [
                'name' => 'System Administrator',
                'role' => 'admin',
                'email' => 'admin@webschedulr.com',
            ],
            'context' => [
                'business_name' => 'WebSchedulr',
                'current_date' => date('Y-m-d'),
                'timezone' => 'UTC',
                'user_role' => 'admin',
            ],
            'metrics' => [
                'total' => 0,
                'upcoming' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'cancelled' => 0,
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
                'revenue' => 0,
            ],
            'trends' => [
                'users' => ['percentage' => 0, 'direction' => 'neutral'],
                'appointments' => ['percentage' => 0, 'direction' => 'neutral'],
                'pending' => ['percentage' => 0, 'direction' => 'neutral'],
                'revenue' => ['percentage' => 0, 'direction' => 'neutral'],
            ],
            'recent_activities' => [],
            'servicesList' => [],
        ];
    }

    public function getAccessDeniedViewData(string $message): array
    {
        return [
            'message' => $message,
            'logoutUrl' => base_url('auth/logout'),
            'homeUrl' => base_url(),
        ];
    }

    public function getStatsSummary(): array
    {
        $userStats = $this->userModel->getStats();
        $appointmentStats = $this->appointmentModel->getStats();
        $monthlyRevenue = $this->appointmentModel->getRevenue('month');

        return [
            'total_users' => $userStats['total'],
            'active_sessions' => $appointmentStats['upcoming'],
            'pending_tasks' => $appointmentStats['today'],
            'revenue' => round($monthlyRevenue, 2),
        ];
    }

    public function getStatsFallbackPayload(): array
    {
        return [
            'total_users' => 0,
            'active_sessions' => 0,
            'pending_tasks' => 0,
            'revenue' => 0,
        ];
    }

    public function getMetricsEndpointResponse(): array
    {
        $currentUser = session()->get('user');
        if (!$currentUser) {
            return [
                'statusCode' => 401,
                'payload' => [
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Please log in to access dashboard metrics',
                ],
            ];
        }

        $userRole = $this->authService->getUserRole($currentUser);
        $providerId = $this->authService->getProviderId($currentUser);

        if (!$this->authService->canViewDashboardMetrics($userRole)) {
            return [
                'statusCode' => 403,
                'payload' => [
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to view dashboard metrics',
                ],
            ];
        }

        $providerScope = $this->authService->getProviderScope($userRole, $providerId);
        $metrics = $this->dashboardService->getTodayMetrics($providerScope);

        return [
            'statusCode' => 200,
            'payload' => [
                'success' => true,
                'data' => $metrics,
                'timestamp' => time(),
            ],
        ];
    }

    public function getMetricsErrorPayload(string $message): array
    {
        return [
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => ENVIRONMENT === 'development' ? $message : 'Failed to fetch metrics',
            'data' => [
                'total' => 0,
                'upcoming' => 0,
                'pending' => 0,
                'cancelled' => 0,
            ],
        ];
    }
}
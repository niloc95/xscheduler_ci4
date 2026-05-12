<?php

/**
 * =============================================================================
 * ANALYTICS CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Analytics.php
 * @description Business intelligence and reporting dashboard with charts,
 *              metrics, and exportable data for appointments, revenue, and trends.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /analytics                    : Main analytics dashboard
 * GET  /analytics/appointments       : Appointment statistics and trends
 * GET  /analytics/revenue            : Revenue reports and projections
 * GET  /analytics/services           : Service popularity analysis
 * GET  /analytics/export             : Export data to CSV/PDF
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides comprehensive business analytics:
 * - Appointment volume over time (daily/weekly/monthly)
 * - Revenue tracking and forecasting
 * - Service popularity rankings
 * - Provider performance metrics
 * - Customer acquisition and retention rates
 * - Peak hours and booking patterns
 * 
 * DATA VISUALIZATIONS:
 * -----------------------------------------------------------------------------
 * - Line charts: Trends over time
 * - Bar charts: Comparisons (services, providers)
 * - Pie charts: Distribution (services, statuses)
 * - KPI cards: Key metrics summary
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Admin: Full access to all analytics
 * - Provider: Access to own metrics only
 * 
 * @see         app/Views/analytics/ for dashboard templates
 * @see         resources/js/analytics.js for chart rendering
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ServiceModel;
use App\Models\LocationModel;
use App\Services\BookingMetricsService;
use CodeIgniter\Controller;

class Analytics extends BaseController
{
    protected $userModel;
    protected $appointmentModel;
    protected $customerModel;
    protected $serviceModel;
    protected $locationModel;

    protected BookingMetricsService $bookingMetrics;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->appointmentModel = new AppointmentModel();
        $this->customerModel = new CustomerModel();
        $this->serviceModel = new ServiceModel();
        $this->locationModel = new LocationModel();
        $this->bookingMetrics = new BookingMetricsService($this->appointmentModel);
        helper(['permissions', 'currency']);
    }

    /**
     * Display analytics dashboard
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

                // Check permissions - admin and provider can access analytics
        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();
        $timeframe = $this->request->getGet('timeframe') ?? '30d';
        $tab = $this->request->getGet('tab') ?? 'overview';
        $providerId = $currentRole === 'provider'
            ? (int) session()->get('user_id')
            : $this->sanitizeOptionalInt($this->request->getGet('provider_id'));
        $providerServiceFilterId = $this->sanitizeOptionalInt($this->request->getGet('provider_service_id'));
        $providerLocationFilterId = $this->sanitizeOptionalInt($this->request->getGet('provider_location_id'));

        $data = [
            'title' => $currentRole === 'admin' ? 'Analytics Dashboard' : 'My Analytics',
            'current_page' => 'analytics',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'overview' => $this->getOverviewStats($currentRole, $timeframe, $providerId),
            'revenue' => $this->getRevenueData($timeframe, $providerId),
            'revenue_data' => $this->getDetailedRevenueData($timeframe, $providerId, $providerServiceFilterId, $providerLocationFilterId),
            'comparisons' => $this->getRevenueComparisons($timeframe, $providerId),
            'appointments' => $this->getAppointmentAnalytics($timeframe, $providerId),
            'services' => $this->getServiceAnalytics($timeframe, $providerId),
            'customers' => $this->getCustomerAnalytics($timeframe, $providerId),
            'provider_filters' => [
                'selected' => [
                    'provider_id' => $providerId,
                    'service_id' => $providerServiceFilterId,
                    'location_id' => $providerLocationFilterId,
                ],
                'provider_options' => $this->getProviderFilterOptions(),
                'service_options' => $this->getProviderServiceFilterOptions($providerId),
                'location_options' => $this->getProviderLocationFilterOptions($providerId),
            ],
            'timeframe' => $timeframe,
            'tab' => in_array($tab, ['overview', 'revenue', 'customers', 'providers'], true) ? $tab : 'overview',
        ];

        return view('analytics/index', $data);
    }

    /**
     * Revenue analytics
     */
    public function revenue()
    {
        $timeframe = $this->request->getGet('timeframe') ?? '30d';
        return redirect()->to(base_url('analytics?' . http_build_query([
            'tab' => 'revenue',
            'timeframe' => $timeframe,
        ])));
    }

    /**
     * Customer analytics
     */
    public function customers()
    {
        $timeframe = $this->request->getGet('timeframe') ?? '30d';
        return redirect()->to(base_url('analytics?' . http_build_query([
            'tab' => 'customers',
            'timeframe' => $timeframe,
        ])));
    }

    /**
     * Get overview statistics - REAL DATA
     */
    private function getOverviewStats($role, $timeframe = '30d', ?int $providerId = null)
    {
        try {
            $window = $this->getTimeframeWindow($timeframe);
            $currentRevenue = $this->appointmentModel->getRevenueForDateRange($window['current_start'], $window['current_end'], $providerId);
            $previousRevenue = $this->appointmentModel->getRevenueForDateRange($window['previous_start'], $window['previous_end'], $providerId);
            $revenueChange = $previousRevenue > 0
                ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
                : ($currentRevenue > 0 ? 100 : 0);

            $currentStats = $this->appointmentModel->getStatsForDateRange($window['current_start'], $window['current_end'], $providerId);
            $previousStats = $this->appointmentModel->getStatsForDateRange($window['previous_start'], $window['previous_end'], $providerId);
            $appointmentChange = $previousStats['total'] > 0
                ? round((($currentStats['total'] - $previousStats['total']) / $previousStats['total']) * 100, 1)
                : ($currentStats['total'] > 0 ? 100 : 0);

            $customerPeriod = $window['customer_period'];
            $newCustomers  = $this->customerModel->getNewCustomers($customerPeriod, $providerId);
            $customerTrend = $this->customerModel->getGrowthTrend($providerId);

            // Avg booking value derived from already-fetched revenue ÷ appointment counts
            $avgBookingValue  = ($currentStats['total'] ?? 0) > 0 ? round($currentRevenue / $currentStats['total'], 2) : 0;
            $prevAvgValue     = ($previousStats['total'] ?? 0) > 0 ? $previousRevenue / $previousStats['total'] : 0;
            $bookingValueChange = $prevAvgValue > 0
                ? round((($avgBookingValue - $prevAvgValue) / $prevAvgValue) * 100, 1)
                : ($avgBookingValue > 0 ? 100 : 0);

            // Customer retention — provider-scoped
            $customerRetention = $this->customerModel->getRetentionRate($providerId);

            // Staff utilization — current and previous periods
            $userStats     = $this->userModel->getStats();
            $providerCount = $providerId !== null ? 1 : ($userStats['providers'] ?? 1);
            $maxCapacity   = max(1, $providerCount) * max(20, (int) ceil($window['days'] / 30 * 160));

            $staffUtilization = $maxCapacity > 0 ? min(100, round((($currentStats['total'] ?? 0) / $maxCapacity) * 100, 1)) : 0;
            $prevUtilization  = $maxCapacity > 0 ? min(100, round((($previousStats['total'] ?? 0) / $maxCapacity) * 100, 1)) : 0;
            $utilizationChange = $prevUtilization > 0
                ? round((($staffUtilization - $prevUtilization) / $prevUtilization) * 100, 1)
                : ($staffUtilization > 0 ? 100 : 0);

            return [
                'total_revenue'        => $currentRevenue,
                'revenue_change'       => $revenueChange,
                'total_appointments'   => $currentStats['total'] ?? 0,
                'appointments_change'  => $appointmentChange,
                'new_customers'        => $newCustomers,
                'customers_change'     => $customerTrend['percentage'] ?? 0,
                'avg_booking_value'    => $avgBookingValue,
                'booking_value_change' => $bookingValueChange,
                'customer_retention'   => $customerRetention,
                'retention_change'     => 0, // Requires time-bounded retention snapshots
                'staff_utilization'    => $staffUtilization,
                'utilization_change'   => $utilizationChange,
            ];
        } catch (\Exception $e) {
            log_message('error', 'Analytics getOverviewStats error: ' . $e->getMessage());
            // Return zeros on error
            return [
                'total_revenue' => 0,
                'revenue_change' => 0,
                'total_appointments' => 0,
                'appointments_change' => 0,
                'new_customers' => 0,
                'customers_change' => 0,
                'avg_booking_value' => 0,
                'booking_value_change' => 0,
                'customer_retention' => 0,
                'retention_change' => 0,
                'staff_utilization' => 0,
                'utilization_change' => 0
            ];
        }
    }

    /**
     * Get revenue data for charts - REAL DATA
     */
    private function getRevenueData($timeframe = '30d', ?int $providerId = null)
    {
        try {
            $window = $this->getTimeframeWindow($timeframe);
            return [
                'daily' => $this->appointmentModel->getDailyRevenue($window['days'], $providerId),
                'monthly' => $this->appointmentModel->getMonthlyRevenue($window['months'], $providerId)
            ];
        } catch (\Exception $e) {
            log_message('error', 'Analytics getRevenueData error: ' . $e->getMessage());
            return [
                'daily' => [],
                'monthly' => []
            ];
        }
    }

    /**
     * Get appointment analytics - REAL DATA
     */
    private function getAppointmentAnalytics($timeframe = '30d', ?int $providerId = null)
    {
        try {
            $window = $this->getTimeframeWindow($timeframe);
            return [
                // Using new consolidated status method
                'by_status' => $this->appointmentModel->getStatusStats([
                    'format' => 'simple',
                    'provider_id' => $providerId,
                    'days' => $window['days'],
                ]),
                'by_service' => $this->bookingMetrics->getByService(10, $providerId),
                'by_time_slot' => $this->appointmentModel->getByTimeSlot($providerId, $window['days'])
            ];
        } catch (\Exception $e) {
            log_message('error', 'Analytics getAppointmentAnalytics error: ' . $e->getMessage());
            return [
                'by_status' => [],
                'by_service' => [],
                'by_time_slot' => []
            ];
        }
    }

    /**
     * Get service analytics - REAL DATA
     */
    private function getServiceAnalytics($timeframe = '30d', ?int $providerId = null)
    {
        try {
            $popularServices = $this->bookingMetrics->getPopularServices(10, $providerId);
            $performance = $this->serviceModel->getPerformanceMetrics($providerId);
            
            // Format popular services for view
            $formattedServices = [];
            foreach ($popularServices as $service) {
                $formattedServices[] = [
                    'name' => $service['name'],
                    'bookings' => (int)$service['bookings'],
                    'revenue' => (float)$service['revenue'],
                    'growth' => (float)($service['growth'] ?? 0)
                ];
            }

            return [
                'popular_services' => $formattedServices,
                'service_performance' => $performance
            ];
        } catch (\Exception $e) {
            log_message('error', 'Analytics getServiceAnalytics error: ' . $e->getMessage());
            return [
                'popular_services' => [],
                'service_performance' => [
                    'avg_duration' => 0,
                    'completion_rate' => 0,
                    'customer_satisfaction' => 0,
                    'repeat_booking_rate' => 0
                ]
            ];
        }
    }

    /**
     * Get customer analytics - REAL DATA
     */
    private function getCustomerAnalytics($timeframe = '30d', ?int $providerId = null)
    {
        try {
            $newVsReturning = $this->customerModel->getNewVsReturning($providerId);
            $loyalty = $this->customerModel->getLoyaltySegments($providerId);

            return [
                'new_vs_returning' => $newVsReturning,
                'demographics' => [
                    // Demographics would require additional fields in customers table
                    'age_groups' => [],
                    'gender' => []
                ],
                'loyalty' => $loyalty
            ];
        } catch (\Exception $e) {
            log_message('error', 'Analytics getCustomerAnalytics error: ' . $e->getMessage());
            return [
                'new_vs_returning' => ['new' => 0, 'returning' => 0],
                'demographics' => ['age_groups' => [], 'gender' => []],
                'loyalty' => ['first_time' => 0, 'occasional' => 0, 'regular' => 0, 'vip' => 0]
            ];
        }
    }

    /**
     * Get detailed revenue data - REAL DATA
     */
    private function getDetailedRevenueData($timeframe = '30d', ?int $providerId = null, ?int $serviceFilterId = null, ?int $locationFilterId = null)
    {
        try {
            $revenueData = $this->getRevenueData($timeframe, $providerId);
            $window = $this->getTimeframeWindow($timeframe);
            $providerCondition = $providerId !== null ? ' AND a.provider_id = ' . (int) $providerId : '';
            $serviceCondition = $serviceFilterId !== null ? ' AND a.service_id = ' . (int) $serviceFilterId : '';
            $locationCondition = $locationFilterId !== null ? ' AND a.location_id = ' . (int) $locationFilterId : '';
            $startAtUtc = $this->localDateTimeToUtc($window['current_start'] . ' 00:00:00');
            $endAtUtc = $this->localDateTimeToUtc($window['current_end'] . ' 23:59:59');
            $localTz = \App\Services\TimezoneService::businessTimezone();
            $offset = (new \DateTime('now', new \DateTimeZone($localTz)))->format('P');
            
            // Get revenue by staff (providers)
            $db = \Config\Database::connect();
            $staffRevenueQuery = $db->query("
                SELECT 
                    u.name,
                    COALESCE(SUM(s.price), 0) as revenue
                FROM xs_appointments a
                JOIN xs_users u ON a.provider_id = u.id
                LEFT JOIN xs_services s ON a.service_id = s.id
                WHERE a.status NOT IN ('cancelled', 'no-show', 'noshow')
                AND a.start_at >= '{$startAtUtc}'
                AND a.start_at <= '{$endAtUtc}'
                {$providerCondition}
                {$serviceCondition}
                {$locationCondition}
                GROUP BY u.id, u.name
                ORDER BY revenue DESC
                LIMIT 10
            ");

            $providerSummaryRows = $db->query("
                SELECT
                    a.provider_id,
                    u.name AS provider_name,
                    COUNT(a.id) AS total_appointments,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed_appointments,
                    COALESCE(SUM(CASE WHEN a.status NOT IN ('cancelled', 'no-show', 'noshow') THEN s.price ELSE 0 END), 0) AS revenue
                FROM xs_appointments a
                JOIN xs_users u ON a.provider_id = u.id
                LEFT JOIN xs_services s ON a.service_id = s.id
                WHERE a.start_at >= '{$startAtUtc}'
                AND a.start_at <= '{$endAtUtc}'
                {$providerCondition}
                {$serviceCondition}
                {$locationCondition}
                GROUP BY a.provider_id, u.name
                ORDER BY revenue DESC, total_appointments DESC
            ")->getResultArray();

            $providerServiceRows = $db->query("
                SELECT
                    a.provider_id,
                    COALESCE(s.name, 'Unknown Service') AS service_name,
                    COUNT(a.id) AS appointment_count
                FROM xs_appointments a
                LEFT JOIN xs_services s ON a.service_id = s.id
                WHERE a.start_at >= '{$startAtUtc}'
                AND a.start_at <= '{$endAtUtc}'
                {$providerCondition}
                {$serviceCondition}
                {$locationCondition}
                GROUP BY a.provider_id, service_name
                ORDER BY appointment_count DESC
            ")->getResultArray();

            $providerLocationRows = $db->query("
                SELECT
                    a.provider_id,
                    COALESCE(NULLIF(TRIM(a.location_name), ''), l.name, 'Unassigned') AS location_name,
                    COUNT(a.id) AS appointment_count
                FROM xs_appointments a
                LEFT JOIN xs_locations l ON a.location_id = l.id
                WHERE a.start_at >= '{$startAtUtc}'
                AND a.start_at <= '{$endAtUtc}'
                {$providerCondition}
                {$serviceCondition}
                {$locationCondition}
                GROUP BY a.provider_id, l.id, a.location_name
                ORDER BY appointment_count DESC
            ")->getResultArray();

            $providerBusyHourRows = $db->query("
                SELECT
                    a.provider_id,
                    DATE_FORMAT(CONVERT_TZ(a.start_at, '+00:00', '{$offset}'), '%l:00 %p') AS time_slot,
                    HOUR(CONVERT_TZ(a.start_at, '+00:00', '{$offset}')) AS hour,
                    COUNT(*) AS appointment_count
                FROM xs_appointments a
                WHERE a.status IN ('pending', 'confirmed', 'completed')
                AND a.start_at >= '{$startAtUtc}'
                AND a.start_at <= '{$endAtUtc}'
                {$providerCondition}
                {$serviceCondition}
                {$locationCondition}
                GROUP BY a.provider_id, hour, time_slot
                ORDER BY a.provider_id, hour
            ")->getResultArray();

            $busyHourDistributionRows = $db->query("
                SELECT
                    DATE_FORMAT(CONVERT_TZ(a.start_at, '+00:00', '{$offset}'), '%l:00 %p') AS time_slot,
                    HOUR(CONVERT_TZ(a.start_at, '+00:00', '{$offset}')) AS hour,
                    COUNT(*) AS appointment_count
                FROM xs_appointments a
                WHERE a.status IN ('pending', 'confirmed', 'completed')
                AND a.start_at >= '{$startAtUtc}'
                AND a.start_at <= '{$endAtUtc}'
                {$providerCondition}
                {$serviceCondition}
                {$locationCondition}
                GROUP BY hour, time_slot
                ORDER BY hour
            ")->getResultArray();

            $topServiceByProvider = [];
            foreach ($providerServiceRows as $row) {
                $providerKey = (int) ($row['provider_id'] ?? 0);
                if (!isset($topServiceByProvider[$providerKey])) {
                    $topServiceByProvider[$providerKey] = [
                        'name' => (string) ($row['service_name'] ?? 'Unknown Service'),
                        'count' => (int) ($row['appointment_count'] ?? 0),
                    ];
                }
            }

            $topLocationByProvider = [];
            foreach ($providerLocationRows as $row) {
                $providerKey = (int) ($row['provider_id'] ?? 0);
                if (!isset($topLocationByProvider[$providerKey])) {
                    $topLocationByProvider[$providerKey] = [
                        'name' => (string) ($row['location_name'] ?? 'Unassigned'),
                        'count' => (int) ($row['appointment_count'] ?? 0),
                    ];
                }
            }

            $windowDays = max(1, (int) ($window['days'] ?? 30));
            $capacityPerProvider = $windowDays * 8;
            $busyHoursByProvider = [];
            foreach ($providerBusyHourRows as $row) {
                $providerKey = (int) ($row['provider_id'] ?? 0);
                if (!isset($busyHoursByProvider[$providerKey])) {
                    $busyHoursByProvider[$providerKey] = [];
                }

                $busyHoursByProvider[$providerKey][] = [
                    'slot' => (string) ($row['time_slot'] ?? ''),
                    'count' => (int) ($row['appointment_count'] ?? 0),
                ];
            }

            $busyHoursDistribution = [];
            foreach ($busyHourDistributionRows as $row) {
                $slot = (string) ($row['time_slot'] ?? '');
                if ($slot === '') {
                    continue;
                }

                $busyHoursDistribution[$slot] = (int) ($row['appointment_count'] ?? 0);
            }

            $providerBreakdown = [];
            foreach ($providerSummaryRows as $row) {
                $providerKey = (int) ($row['provider_id'] ?? 0);
                $totalAppointments = (int) ($row['total_appointments'] ?? 0);
                $utilization = $capacityPerProvider > 0
                    ? min(100.0, round(($totalAppointments / $capacityPerProvider) * 100, 1))
                    : 0.0;

                $busyHourLabel = 'N/A';
                $busyHourCount = 0;
                foreach ($busyHoursByProvider[$providerKey] ?? [] as $candidate) {
                    if ((int) $candidate['count'] > $busyHourCount) {
                        $busyHourCount = (int) $candidate['count'];
                        $busyHourLabel = (string) $candidate['slot'];
                    }
                }

                $providerBreakdown[] = [
                    'provider_id' => $providerKey,
                    'provider_name' => (string) ($row['provider_name'] ?? 'Unknown Provider'),
                    'service_name' => $topServiceByProvider[$providerKey]['name'] ?? 'N/A',
                    'location_name' => $topLocationByProvider[$providerKey]['name'] ?? 'N/A',
                    'utilization' => $utilization,
                    'revenue' => (float) ($row['revenue'] ?? 0),
                    'total_appointments' => $totalAppointments,
                    'completed_appointments' => (int) ($row['completed_appointments'] ?? 0),
                    'busy_hour_label' => $busyHourLabel,
                    'busy_hour_count' => $busyHourCount,
                ];
            }
            
            return array_merge($revenueData, [
                'by_payment_method' => [
                    // Would need payment method field in appointments
                    'total' => $this->appointmentModel->getRevenueForDateRange($window['current_start'], $window['current_end'], $providerId)
                ],
                'by_staff' => $staffRevenueQuery->getResultArray(),
                'provider_breakdown' => $providerBreakdown,
                'busy_hours_distribution' => $busyHoursDistribution,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Analytics getDetailedRevenueData error: ' . $e->getMessage());
            return [
                'daily' => [],
                'monthly' => [],
                'by_payment_method' => ['total' => 0],
                'by_staff' => [],
                'provider_breakdown' => [],
                'busy_hours_distribution' => [],
            ];
        }
    }

    private function sanitizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    private function getProviderFilterOptions(): array
    {
        return array_map(static fn(array $provider) => [
            'id' => (int) ($provider['id'] ?? 0),
            'name' => (string) ($provider['name'] ?? 'Unknown Provider'),
        ], $this->userModel->getProviders());
    }

    private function getProviderServiceFilterOptions(?int $providerId = null): array
    {
        if ($providerId !== null) {
            return array_map(static fn(array $service) => [
                'id' => (int) ($service['id'] ?? 0),
                'name' => (string) ($service['name'] ?? 'Unknown Service'),
            ], $this->serviceModel->getActiveByProvider($providerId));
        }

        return array_map(static fn(array $service) => [
            'id' => (int) ($service['id'] ?? 0),
            'name' => (string) ($service['name'] ?? 'Unknown Service'),
        ], $this->serviceModel->where('active', 1)->orderBy('name', 'ASC')->findAll());
    }

    private function getProviderLocationFilterOptions(?int $providerId = null): array
    {
        $locations = $providerId !== null
            ? $this->locationModel->getProviderLocations($providerId, true)
            : $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();

        return array_map(static fn(array $location) => [
            'id' => (int) ($location['id'] ?? 0),
            'name' => (string) ($location['name'] ?? 'Unknown Location'),
        ], $locations);
    }

    /**
     * Get revenue comparisons - REAL DATA
     */
    private function getRevenueComparisons($timeframe = '30d', ?int $providerId = null)
    {
        try {
            $window = $this->getTimeframeWindow($timeframe);
            $currentMonth = $this->appointmentModel->getRevenueForDateRange($window['current_start'], $window['current_end'], $providerId);
            $lastMonth = $this->appointmentModel->getRevenueForDateRange($window['previous_start'], $window['previous_end'], $providerId);
            
            $vsLastMonth = $lastMonth > 0 
                ? round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1) 
                : ($currentMonth > 0 ? 100 : 0);

            // Quarter: current 3 months vs previous 3 months
            $now     = new \DateTime();
            $qEnd    = $now->format('Y-m-d');
            $qStart  = (clone $now)->modify('-3 months')->format('Y-m-d');
            $pqEnd   = (clone $now)->modify('-3 months')->format('Y-m-d');
            $pqStart = (clone $now)->modify('-6 months')->format('Y-m-d');

            $currentQuarter = $this->appointmentModel->getRevenueForDateRange($qStart, $qEnd, $providerId);
            $prevQuarter    = $this->appointmentModel->getRevenueForDateRange($pqStart, $pqEnd, $providerId);
            $vsLastQuarter  = $prevQuarter > 0
                ? round((($currentQuarter - $prevQuarter) / $prevQuarter) * 100, 1)
                : ($currentQuarter > 0 ? 100 : 0);

            // Year: current 12 months vs previous 12 months
            $yEnd    = $now->format('Y-m-d');
            $yStart  = (clone $now)->modify('-1 year')->format('Y-m-d');
            $pyEnd   = (clone $now)->modify('-1 year')->format('Y-m-d');
            $pyStart = (clone $now)->modify('-2 years')->format('Y-m-d');

            $currentYear = $this->appointmentModel->getRevenueForDateRange($yStart, $yEnd, $providerId);
            $prevYear    = $this->appointmentModel->getRevenueForDateRange($pyStart, $pyEnd, $providerId);
            $vsLastYear  = $prevYear > 0
                ? round((($currentYear - $prevYear) / $prevYear) * 100, 1)
                : ($currentYear > 0 ? 100 : 0);

            // Forecast: trailing 3-month average (replaces naive 5% flat projection)
            $m1s  = (new \DateTime('first day of last month'))->format('Y-m-d');
            $m1e  = (new \DateTime('last day of last month'))->format('Y-m-d');
            $m2s  = (new \DateTime('first day of 2 months ago'))->format('Y-m-d');
            $m2e  = (new \DateTime('last day of 2 months ago'))->format('Y-m-d');
            $m3s  = (new \DateTime('first day of 3 months ago'))->format('Y-m-d');
            $m3e  = (new \DateTime('last day of 3 months ago'))->format('Y-m-d');

            $rev1              = $this->appointmentModel->getRevenueForDateRange($m1s, $m1e, $providerId);
            $rev2              = $this->appointmentModel->getRevenueForDateRange($m2s, $m2e, $providerId);
            $rev3              = $this->appointmentModel->getRevenueForDateRange($m3s, $m3e, $providerId);
            $forecastNextMonth = round(($rev1 + $rev2 + $rev3) / 3, 2);

            return [
                'current_total'       => $currentMonth,
                'previous_total'      => $lastMonth,
                'vs_last_month'       => $vsLastMonth,
                'vs_last_quarter'     => $vsLastQuarter,
                'vs_last_year'        => $vsLastYear,
                'forecast_next_month' => $forecastNextMonth,
            ];
        } catch (\Exception $e) {
            log_message('error', 'Analytics getRevenueComparisons error: ' . $e->getMessage());
            return [
                'vs_last_month' => 0,
                'vs_last_quarter' => 0,
                'vs_last_year' => 0,
                'forecast_next_month' => 0
            ];
        }
    }

    /**
     * Get detailed customer data - REAL DATA
     */
    private function getDetailedCustomerData()
    {
        try {
            $customerData = $this->getCustomerAnalytics();
            $stats = $this->customerModel->getStats();
            
            return array_merge($customerData, [
                'acquisition' => [
                    // Would need acquisition source field in customers table
                    'total' => $stats['total']
                ],
                'lifetime_value' => [
                    'average' => $this->appointmentModel->getAverageBookingValue(),
                    'segments' => $customerData['loyalty']
                ]
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Analytics getDetailedCustomerData error: ' . $e->getMessage());
            return [
                'new_vs_returning' => ['new' => 0, 'returning' => 0],
                'acquisition' => ['total' => 0],
                'lifetime_value' => ['average' => 0, 'segments' => []]
            ];
        }
    }

    /**
     * Get customer retention data - REAL DATA
     */
    private function getCustomerRetention()
    {
        try {
            $retentionRate = $this->customerModel->getRetentionRate();
            
            return [
                'overall_rate' => $retentionRate,
                'by_month' => [
                    // Simplified - would need more complex queries for cohort analysis
                    'month_1' => $retentionRate,
                    'month_3' => round($retentionRate * 0.9, 1),
                    'month_6' => round($retentionRate * 0.8, 1),
                    'month_12' => round($retentionRate * 0.7, 1)
                ],
                'churn_analysis' => [
                    // Would need cancellation reason field
                    'primary_reasons' => []
                ]
            ];
        } catch (\Exception $e) {
            log_message('error', 'Analytics getCustomerRetention error: ' . $e->getMessage());
            return [
                'overall_rate' => 0,
                'by_month' => [],
                'churn_analysis' => ['primary_reasons' => []]
            ];
        }
    }

    private function getTimeframeWindow(string $timeframe): array
    {
        $now = new \DateTime('now', new \DateTimeZone(\App\Services\TimezoneService::businessTimezone()));

        return match ($timeframe) {
            '7d' => $this->buildWindowConfig($now, 7, 1, 'week'),
            '3m' => $this->buildWindowConfig($now, 90, 6, 'month'),
            '1y' => $this->buildWindowConfig($now, 365, 12, 'month'),
            default => $this->buildWindowConfig($now, 30, 3, 'month'),
        };
    }

    private function buildWindowConfig(\DateTime $now, int $days, int $months, string $customerPeriod): array
    {
        $currentEnd = clone $now;
        $currentStart = (clone $now)->modify('-' . max(0, $days - 1) . ' days');
        $previousEnd = (clone $currentStart)->modify('-1 day');
        $previousStart = (clone $previousEnd)->modify('-' . max(0, $days - 1) . ' days');

        return [
            'days' => $days,
            'months' => $months,
            'customer_period' => $customerPeriod,
            'current_start' => $currentStart->format('Y-m-d'),
            'current_end' => $currentEnd->format('Y-m-d'),
            'previous_start' => $previousStart->format('Y-m-d'),
            'previous_end' => $previousEnd->format('Y-m-d'),
        ];
    }

    private function localDateTimeToUtc(string $localDateTime): string
    {
        $timezone = new \DateTimeZone(\App\Services\TimezoneService::businessTimezone());
        $date = new \DateTime($localDateTime, $timezone);
        $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->format('Y-m-d H:i:s');
    }
}

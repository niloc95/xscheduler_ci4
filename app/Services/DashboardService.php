<?php

/**
 * =============================================================================
 * DASHBOARD SERVICE
 * =============================================================================
 * 
 * @file        app/Services/DashboardService.php
 * @description Business logic service for dashboard data aggregation.
 *              Provides role-scoped statistics, metrics, and calendar data.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Centralizes all dashboard data logic:
 * - Appointment statistics (today, upcoming, completed)
 * - Calendar events for week/month views
 * - Revenue metrics and projections
 * - Provider/staff performance data
 * - Customer activity summaries
 * 
 * ROLE-BASED SCOPING:
 * -----------------------------------------------------------------------------
 * - Admin: Sees all data across all providers
 * - Provider: Sees only own data and assigned staff
 * - Staff: Sees only own assigned appointments
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - getDashboardData()      : Full dashboard payload
 * - getTodayAppointments()  : Today's appointment list
 * - getUpcomingAppointments(): Future appointments
 * - getAppointmentStats()   : KPI statistics
 * - getCalendarEvents()     : Events for calendar views
 * - getRevenueStats()       : Revenue summary
 * 
 * CACHING:
 * -----------------------------------------------------------------------------
 * Dashboard data is cached and invalidated when:
 * - Appointments are created/updated/deleted
 * - Schedule changes occur
 * - Settings are modified
 * 
 * @see         app/Controllers/Dashboard.php for view controller
 * @see         app/Controllers/Api/Dashboard.php for API
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\CustomerModel;
use App\Models\LocationModel;
use App\Models\ProviderScheduleModel;
use App\Models\BusinessHourModel;
use App\Services\Appointment\AppointmentStatus;
use App\Services\AvailabilityService;

/**
 * Dashboard Service
 * 
 * Centralized business logic for dashboard data aggregation.
 * Handles role-based data scoping and authorization enforcement.
 * 
 * @package App\Services
 */
class DashboardService
{
    protected AppointmentModel $appointmentModel;
    protected UserModel $userModel;
    protected ServiceModel $serviceModel;
    protected CustomerModel $customerModel;
    protected LocationModel $locationModel;
    protected ProviderScheduleModel $providerScheduleModel;
    protected BusinessHourModel $businessHourModel;
    protected LocalizationSettingsService $localizationService;
    protected AvailabilityService $availabilityService;
    protected AppointmentDashboardContextService $appointmentDashboardContextService;

    public function __construct(
        ?AppointmentModel $appointmentModel = null,
        ?UserModel $userModel = null,
        ?ServiceModel $serviceModel = null,
        ?CustomerModel $customerModel = null,
        ?LocationModel $locationModel = null,
        ?ProviderScheduleModel $providerScheduleModel = null,
        ?BusinessHourModel $businessHourModel = null,
        ?LocalizationSettingsService $localizationService = null,
        ?AvailabilityService $availabilityService = null,
        ?AppointmentDashboardContextService $appointmentDashboardContextService = null
    )
    {
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->userModel = $userModel ?? new UserModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
        $this->customerModel = $customerModel ?? new CustomerModel();
        $this->locationModel = $locationModel ?? new LocationModel();
        $this->providerScheduleModel = $providerScheduleModel ?? new ProviderScheduleModel();
        $this->businessHourModel = $businessHourModel ?? new BusinessHourModel();
        $this->localizationService = $localizationService ?? new LocalizationSettingsService();
        $this->availabilityService = $availabilityService ?? new AvailabilityService();
        $this->appointmentDashboardContextService = $appointmentDashboardContextService ?? new AppointmentDashboardContextService();
    }

    /**
     * Get dashboard context for authenticated user
     * 
     * @param int $userId User ID
     * @param string $userRole User role (admin, provider, staff)
     * @param int|null $providerId Provider ID (for providers/staff)
     * @return array Dashboard context data
     */
    public function getDashboardContext(int $userId, string $userRole, ?int $providerId = null): array
    {
        $user = $this->userModel->find($userId);
        $businessName = env('app.name', 'WebSchedulr');
        
        // Get timezone from settings
        $timezone = $this->localizationService->getTimezone();

        // "Today" must be evaluated in the business timezone, not the server's —
        // near midnight these differ by a full day. There is no date-format
        // setting; see LocalizationSettingsService.
        $currentDate = (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d');

        return array_merge([
            'business_name' => $businessName,
            'current_date' => $currentDate,
            'timezone' => $timezone,
            'user_name' => $user['name'] ?? 'User',
            'user_email' => $user['email'] ?? '',
            'user_role' => $userRole,
            'user_id' => $userId,
            'provider_id' => $providerId,
        ], $this->appointmentDashboardContextService->build($userRole, $userId, is_array($user) ? $user : null));
    }

    /**
     * Get today's metrics (server-side calculation)
     * 
     * Calculates key metrics for today:
     * - Total appointments today
     * - Upcoming appointments (next 4 hours)
     * - Pending/unconfirmed appointments
     * - Cancelled/rescheduled today
     * 
     * @param int|int[]|null $providerId Scope: null=admin (all), int=provider, int[]=staff
     * @return array Metrics data
     */
    public function getTodayMetrics(int|array|null $providerId = null): array
    {
        // Staff with no assignments: return zero metrics immediately
        if (is_array($providerId) && empty($providerId)) {
            return ['total' => 0, 'upcoming' => 0, 'pending' => 0, 'cancelled' => 0, 'confirmed' => 0];
        }

        // Calculate local-today boundaries in UTC for correct business-day queries
        $localTz = $this->localizationService->getTimezone();
        $tz = new \DateTimeZone($localTz);
        $utcTz = new \DateTimeZone('UTC');
        $localNow = new \DateTime('now', $tz);
        $localToday = $localNow->format('Y-m-d');
        // Convert local day boundaries to UTC for DB query
        $dayStartUtc = (new \DateTime($localToday . ' 00:00:00', $tz))->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $dayEndUtc   = (new \DateTime($localToday . ' 23:59:59', $tz))->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $nowUtc = (clone $localNow)->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $upcomingWindowUtc = (clone $localNow)->modify('+4 hours')->setTimezone($utcTz)->format('Y-m-d H:i:s');

        // Build a fresh scoped builder per metric to avoid accumulating WHERE clauses
        // across sequential countAllResults(false) calls.
        $scopedBuilder = function () use ($providerId) {
            $builder = $this->appointmentModel->builder();
            if (is_array($providerId)) {
                $builder->whereIn('provider_id', $providerId);
            } elseif ($providerId !== null) {
                $builder->where('provider_id', $providerId);
            }

            return $builder;
        };

        // Total appointments today
        $total = $scopedBuilder()
            ->where('start_at >=', $dayStartUtc)
            ->where('start_at <=', $dayEndUtc)
            ->countAllResults();

        // Upcoming appointments (next 4 hours)
        $upcoming = $scopedBuilder()
            ->where('start_at >=', $nowUtc)
            ->where('start_at <=', $upcomingWindowUtc)
            ->whereIn('xs_appointments.status', AppointmentStatus::UPCOMING)
            ->countAllResults();

        // Pending confirmation
        $pending = $scopedBuilder()
            ->where('start_at >=', $dayStartUtc)
            ->where('start_at <=', $dayEndUtc)
            ->where('xs_appointments.status', AppointmentStatus::PENDING)
            ->countAllResults();

        // Cancelled/rescheduled today
        $cancelled = $scopedBuilder()
            ->where('start_at >=', $dayStartUtc)
            ->where('start_at <=', $dayEndUtc)
            ->whereIn('xs_appointments.status', [AppointmentStatus::CANCELLED, AppointmentStatus::NO_SHOW])
            ->countAllResults();

        // Confirmed today
        $confirmed = $scopedBuilder()
            ->where('start_at >=', $dayStartUtc)
            ->where('start_at <=', $dayEndUtc)
            ->where('xs_appointments.status', AppointmentStatus::CONFIRMED)
            ->countAllResults();

        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'confirmed' => $confirmed,
        ];
    }

    /**
     * Count customers for the KPI card.
     *
     * Customers are a global entity (not provider-scoped in this app), so the
     * count is business-wide regardless of the viewer's provider scope.
     *
     * @return int Total customers
     */
    public function getCustomerCount(): int
    {
        return $this->customerModel->countAllCustomers();
    }

    /**
     * Count appointments awaiting payment for the KPI card.
     *
     * "Pending payment" = payment_status 'pending' (deposit due) on an
     * appointment that has not been cancelled or marked no-show. Provider-scoped
     * like the other dashboard metrics.
     *
     * @param int|int[]|null $providerId Scope: null=admin (all), int=provider, int[]=staff
     * @return int Pending-payment appointment count
     */
    public function getPendingPaymentsCount(int|array|null $providerId = null): int
    {
        // Staff with no assignments: nothing to pay
        if (is_array($providerId) && empty($providerId)) {
            return 0;
        }

        // Defensive: payment columns are added by a conditional migration.
        $db = \Config\Database::connect();
        $hasPaymentStatus = method_exists($db, 'fieldExists')
            ? $db->fieldExists('payment_status', 'xs_appointments')
            : true;
        if (!$hasPaymentStatus) {
            return 0;
        }

        $builder = $this->appointmentModel->builder();
        if (is_array($providerId)) {
            $builder->whereIn('provider_id', $providerId);
        } elseif ($providerId !== null) {
            $builder->where('provider_id', $providerId);
        }

        return (int) $builder
            ->where('xs_appointments.payment_status', 'pending')
            ->whereNotIn('xs_appointments.status', ['cancelled', 'no-show', 'noshow'])
            ->countAllResults();
    }

    /**
     * Get today's schedule snapshot (business hours only)
     * 
     * Returns appointments grouped by provider for today.
     * Includes time range, customer name, and status.
     * 
     * @param int|int[]|null $providerId Scope: null=admin (all), int=provider, int[]=staff
     * @return array Schedule data grouped by provider
     */
    public function getTodaySchedule(int|array|null $providerId = null): array
    {
        // Staff with no assignments: return empty immediately
        if (is_array($providerId) && empty($providerId)) {
            return [];
        }

        // Calculate local-today boundaries in UTC
        $localTz = $this->localizationService->getTimezone();
        $tz = new \DateTimeZone($localTz);
        $utcTz = new \DateTimeZone('UTC');
        $localNow = new \DateTime('now', $tz);
        $localToday = $localNow->format('Y-m-d');
        $dayStartUtc = (new \DateTime($localToday . ' 00:00:00', $tz))->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $dayEndUtc   = (new \DateTime($localToday . ' 23:59:59', $tz))->setTimezone($utcTz)->format('Y-m-d H:i:s');
        
        $builder = $this->appointmentModel->builder();
        $builder->select('
            xs_appointments.*,
            xs_users.name as provider_name,
            xs_customers.first_name,
            xs_customers.last_name,
            xs_services.name as service_name
        ')
        ->join('xs_users', 'xs_users.id = xs_appointments.provider_id', 'left')
        ->join('xs_customers', 'xs_customers.id = xs_appointments.customer_id', 'left')
        ->join('xs_services', 'xs_services.id = xs_appointments.service_id', 'left')
        ->where('xs_appointments.start_at >=', $dayStartUtc)
        ->where('xs_appointments.start_at <=', $dayEndUtc)
        ->whereIn('xs_appointments.status', ['pending', 'confirmed', 'completed'])
        ->orderBy('xs_appointments.provider_id', 'ASC')
        ->orderBy('xs_appointments.start_at', 'ASC');

        // Apply provider scope
        if (is_array($providerId)) {
            $builder->whereIn('xs_appointments.provider_id', $providerId);
        } elseif ($providerId !== null) {
            $builder->where('xs_appointments.provider_id', $providerId);
        }

        $appointments = $builder->get()->getResultArray();

        $schedule = [];
        foreach ($appointments as $appt) {
            $providerName = $appt['provider_name'] ?? 'Unknown Provider';
            if (!isset($schedule[$providerName])) {
                $schedule[$providerName] = [];
            }

            // Convert UTC times to local timezone for display
            $localStart = \App\Services\TimezoneService::toDisplay($appt['start_at'], $localTz);
            $localEnd = \App\Services\TimezoneService::toDisplay($appt['end_at'], $localTz);

            $schedule[$providerName][] = [
                'id' => $appt['id'],
                'hash' => $appt['hash'] ?? null,
                'start_at' => $this->localizationService->formatTimeForDisplay(date('H:i:s', strtotime($localStart))),
                'end_at' => $this->localizationService->formatTimeForDisplay(date('H:i:s', strtotime($localEnd))),
                'customer_name' => trim(($appt['first_name'] ?? '') . ' ' . ($appt['last_name'] ?? '')),
                'service_name' => $appt['service_name'] ?? '',
                'status' => $appt['status'],
            ];
        }

        return $schedule;
    }

    /**
     * Get actionable alerts for user
     * 
     * Returns alerts that require user action:
     * - Appointments awaiting confirmation
     * - Missing working hours
     * - Booking page disabled
     * - Upcoming blocked periods
     * - Overbooking conflicts
     * 
     * @param int|int[]|null $providerId Scope: null=admin (all), int=provider, int[]=staff
     * @return array Alert data with action URLs
     */
    public function getAlerts(int|array|null $providerId = null): array
    {
        // Staff with no assignments: no alerts
        if (is_array($providerId) && empty($providerId)) {
            return [];
        }

        $alerts = [];

        // Check for pending confirmations
        $builder = $this->appointmentModel->builder();
        if (is_array($providerId)) {
            $builder->whereIn('provider_id', $providerId);
        } elseif ($providerId !== null) {
            $builder->where('provider_id', $providerId);
        }
        
        // Use local-today boundary in UTC for alert cutoff
        $localTz = $this->localizationService->getTimezone();
        $tz = new \DateTimeZone($localTz);
        $utcTz = new \DateTimeZone('UTC');
        $localNow = new \DateTime('now', $tz);
        $localToday = $localNow->format('Y-m-d');
        $dayStartUtc = (new \DateTime($localToday . ' 00:00:00', $tz))->setTimezone($utcTz)->format('Y-m-d H:i:s');

        $pendingCount = $this->countPendingConfirmationAlerts($providerId, $dayStartUtc);

        if ($pendingCount > 0) {
            $alerts[] = [
                'type' => 'confirmation_pending',
                'severity' => 'warning',
                'message' => "{$pendingCount} appointment(s) awaiting confirmation",
                'action_label' => 'Review Appointments',
                'action_url' => base_url('/appointments?filter=pending'),
            ];
        }

        return $alerts;
    }

    protected function countPendingConfirmationAlerts(int|array|null $providerId, string $dayStartUtc): int
    {
        $builder = $this->appointmentModel->builder();

        if (is_array($providerId)) {
            $builder->whereIn('provider_id', $providerId);
        } elseif ($providerId !== null) {
            $builder->where('provider_id', $providerId);
        }

        return (int) $builder
            ->where('xs_appointments.status', 'pending')
            ->where('start_at >=', $dayStartUtc)
            ->countAllResults();
    }

    /**
     * Get upcoming appointments (next 7 days, max 10)
     * 
     * @param int|int[]|null $providerId Scope: null=admin (all), int=provider, int[]=staff
     * @return array Upcoming appointments
     */
    public function getUpcomingAppointments(int|array|null $providerId = null): array
    {
        if (is_array($providerId) && empty($providerId)) {
            return [];
        }

        // Calculate local today and next-week boundaries in UTC
        $localTz = $this->localizationService->getTimezone();
        $tz = new \DateTimeZone($localTz);
        $utcTz = new \DateTimeZone('UTC');
        $localNow = new \DateTime('now', $tz);
        $localToday = $localNow->format('Y-m-d');
        $localNextWeek = (clone $localNow)->modify('+7 days')->format('Y-m-d');
        $dayStartUtc = (new \DateTime($localToday . ' 00:00:00', $tz))->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $weekEndUtc  = (new \DateTime($localNextWeek . ' 23:59:59', $tz))->setTimezone($utcTz)->format('Y-m-d H:i:s');

        $builder = $this->appointmentModel->builder();
        $builder->select('
            xs_appointments.*,
            xs_users.name as provider_name,
            xs_customers.first_name,
            xs_customers.last_name,
            xs_services.name as service_name
        ')
        ->join('xs_users', 'xs_users.id = xs_appointments.provider_id', 'left')
        ->join('xs_customers', 'xs_customers.id = xs_appointments.customer_id', 'left')
        ->join('xs_services', 'xs_services.id = xs_appointments.service_id', 'left')
        ->where('xs_appointments.start_at >=', $dayStartUtc)
        ->where('xs_appointments.start_at <=', $weekEndUtc)
        ->whereIn('xs_appointments.status', AppointmentStatus::UPCOMING)
        ->orderBy('xs_appointments.start_at', 'ASC')
        ->limit(10);

        // Apply provider scope
        if (is_array($providerId)) {
            $builder->whereIn('xs_appointments.provider_id', $providerId);
        } elseif ($providerId !== null) {
            $builder->where('xs_appointments.provider_id', $providerId);
        }

        $appointments = $builder->get()->getResultArray();

        // Format for display
        $formatted = [];
        foreach ($appointments as $appt) {
            // Convert UTC times to local timezone for display
            $localStart = \App\Services\TimezoneService::toDisplay($appt['start_at'], $localTz);

            $formatted[] = [
                'id' => $appt['id'],
                'date' => date('Y-m-d', strtotime($localStart)),
                'time' => date('H:i', strtotime($localStart)),
                'customer' => trim(($appt['first_name'] ?? '') . ' ' . ($appt['last_name'] ?? '')),
                'provider' => $appt['provider_name'] ?? 'Unknown',
                'service' => $appt['service_name'] ?? '',
                'status' => $appt['status'],
            ];
        }

        return $formatted;
    }

    /**
     * Get provider availability for today
     * 
     * Returns current status and next available slot for each provider.
     * 
     * States:
     * - working: Currently in working hours and available
     * - on_break: Currently on break
     * - off: Not working today or past working hours
     * 
     * @param int|int[]|null $providerId Scope: null=admin (all), int=provider, int[]=staff
     * @return array Provider availability data
     */
    public function getProviderAvailability(int|array|null $providerId = null): array
    {
        // Staff with no assignments: return empty immediately
        if (is_array($providerId) && empty($providerId)) {
            return [];
        }

        $db = \Config\Database::connect();
        $usersHasIsActive = method_exists($db, 'fieldExists') ? $db->fieldExists('is_active', 'xs_users') : true;
        $usersHasStatus = method_exists($db, 'fieldExists') ? $db->fieldExists('status', 'xs_users') : true;

        $builder = $this->userModel->builder();
        // Queries xs_user_roles (the multi-role table), not xs_users.role — correct per auth-rbac contract.
        $userRolesTable = $db->prefixTable('user_roles');
        $builder->select('id, name, color')
            ->whereIn('id', static function (\CodeIgniter\Database\BaseBuilder $sub) use ($userRolesTable): void {
                $sub->select('user_id')->from($userRolesTable)->where('role', 'provider');
            });

        if ($usersHasIsActive) {
            $builder->where('is_active', true);
        } elseif ($usersHasStatus) {
            $builder->where('status', 'active');
        }

        if (is_array($providerId)) {
            $builder->whereIn('id', $providerId);
        } elseif ($providerId !== null) {
            $builder->where('id', $providerId);
        }

        $providers = $builder->get()->getResultArray();

        $now = new \DateTime();
        $currentTime = $now->format('H:i:s');
        $dayOfWeek = strtolower($now->format('l'));
        $weekdayNum = (int) $now->format('w');

        $availability = [];
        $timezone = $this->localizationService->getTimezone();
        $today = (new \DateTimeImmutable('today', new \DateTimeZone($timezone)))->format('Y-m-d');
        foreach ($providers as $provider) {
            // Get provider's working hours for today
            $workingHours = $this->getProviderWorkingHours($provider['id'], $dayOfWeek, $weekdayNum);
            
            // Determine status
            $status = 'off';
            if ($workingHours) {
                $startTime = $workingHours['start_time'];
                $endTime = $workingHours['end_time'];
                $breaks = $workingHours['breaks'] ?? [];

                if ($currentTime >= $startTime && $currentTime < $endTime) {
                    // Within working hours - check if on break
                    $status = 'working';
                    foreach ($breaks as $break) {
                        $breakStart = $break['start'] ?? null;
                        $breakEnd = $break['end'] ?? null;
                        if ($breakStart && $breakEnd) {
                            // Normalize to H:i:s
                            if (strlen($breakStart) === 5) $breakStart .= ':00';
                            if (strlen($breakEnd) === 5) $breakEnd .= ':00';
                            
                            if ($currentTime >= $breakStart && $currentTime < $breakEnd) {
                                $status = 'on_break';
                                break;
                            }
                        }
                    }
                } elseif ($currentTime < $startTime) {
                    // Before working hours started
                    $status = 'off';
                }
                // If currentTime >= endTime, status remains 'off'
            }

            $nextSlot = $this->getNextAvailableSlotDetails($provider['id']);

            // Build provider-specific booking options used by dashboard cards.
            $providerServiceOptions = $this->getProviderServiceOptions($provider['id']);
            if (empty($providerServiceOptions)) {
                continue; // provider has no active services — exclude from dashboard grid
            }
            $providerServices = array_column($providerServiceOptions, 'name');
            $defaultServiceId = $providerServiceOptions[0]['id'] ?? null;

            $locationOptions = $this->getProviderLocationOptions($provider['id']);
            $slotsForDate = $this->getProviderSlotsForDate(
                (int) $provider['id'],
                $today,
                $defaultServiceId,
                null,
                null
            );

            $availability[] = [
                'id' => $provider['id'],
                'name' => $provider['name'],
                'status' => $status,
                'next_slot' => $nextSlot,
                'color' => $provider['color'] ?? '#3B82F6',
                'services' => $providerServices,
                'service_options' => $providerServiceOptions,
                'default_service_id' => $defaultServiceId,
                'location_options' => $locationOptions,
                'slots_date' => $today,
                'slots_for_date' => $slotsForDate,
            ];
        }

        return $availability;
    }

    /**
     * Get services associated with a provider
     * 
     * @param int $providerId
     * @return array List of service names
     */
    protected function getProviderServices(int $providerId): array
    {
        $serviceOptions = $this->getProviderServiceOptions($providerId);
        return array_column($serviceOptions, 'name');
    }

    /**
     * Get service options for a provider as structured objects.
     *
     * @param int $providerId
     * @return array<int, array{id:int,name:string,duration_min:int|null}>
     */
    protected function getProviderServiceOptions(int $providerId): array
    {
        $db = \Config\Database::connect();
        $providerServicesTable = $db->prefixTable('providers_services');
        $hasProviderServices = method_exists($db, 'tableExists')
            ? ($db->tableExists('providers_services') || $db->tableExists($providerServicesTable))
            : true;
        $servicesHasIsActive = method_exists($db, 'fieldExists') ? $db->fieldExists('is_active', 'xs_services') : true;

        try {
            if ($hasProviderServices) {
                $builder = $db->table($providerServicesTable . ' ps');
                $builder->select('s.id, s.name, s.duration_min')
                    ->join('xs_services s', 's.id = ps.service_id')
                    ->where('ps.provider_id', $providerId);

                if ($servicesHasIsActive) {
                    $builder->where('s.is_active', true);
                }

                $builder->orderBy('s.duration_min', 'ASC')->orderBy('s.name', 'ASC');
                $services = $builder->get()->getResultArray();

                return array_map(static function (array $service): array {
                    return [
                        'id' => (int) ($service['id'] ?? 0),
                        'name' => (string) ($service['name'] ?? ''),
                        'duration_min' => isset($service['duration_min']) ? (int) $service['duration_min'] : null,
                    ];
                }, array_values(array_filter($services, static fn(array $service): bool => !empty($service['id']) && !empty($service['name']))));
            }
        } catch (\Throwable $e) {
            // Dashboard must not leak global services when provider mapping fails.
        }

        return [];
    }

    /**
     * Get location options for a provider.
     *
     * @param int $providerId
     * @return array<int, array{id:int,name:string}>
     */
    protected function getProviderLocationOptions(int $providerId): array
    {
        $db = \Config\Database::connect();
        $hasLocationsTable = method_exists($db, 'tableExists') ? $db->tableExists('xs_locations') : true;
        if (!$hasLocationsTable) {
            return [];
        }

        try {
            $locations = $this->locationModel->getProviderLocations($providerId, true);

            return array_map(static function (array $location): array {
                return [
                    'id' => (int) ($location['id'] ?? 0),
                    'name' => (string) ($location['name'] ?? ''),
                ];
            }, array_values(array_filter($locations, static fn(array $location): bool => !empty($location['id']) && !empty($location['name']))));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get provider slots for a specific date and optional service/location filters.
     *
     * @param int $providerId
     * @param string $date
     * @param int|null $serviceId
     * @param int|null $locationId
     * @param int|null $limit
     * @return array<int, array{time:string,end_time:string,start_time:string,end_time_iso:string}>
     */
    public function getProviderSlotsForDate(
        int $providerId,
        string $date,
        ?int $serviceId = null,
        ?int $locationId = null,
        ?int $limit = null
    ): array {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }

        $allowedServiceIds = array_map('intval', array_column($this->getProviderServiceOptions($providerId), 'id'));
        if ($serviceId !== null && !in_array((int) $serviceId, $allowedServiceIds, true)) {
            return [];
        }

        $serviceId ??= $this->getProviderDefaultServiceId($providerId);
        if (!$serviceId) {
            return [];
        }

        $timezone = $this->localizationService->getTimezone();
        $bufferMinutes = $this->availabilityService->getBufferTime($providerId);
        $slots = $this->availabilityService->getAvailableSlots(
            $providerId,
            $date,
            $serviceId,
            $bufferMinutes,
            $timezone,
            null,
            $locationId
        );

        if ($limit !== null && $limit >= 0) {
            $slots = array_slice($slots, 0, $limit);
        }

        return array_map(static function (array $slot): array {
            $start = $slot['start'] ?? null;
            $end = $slot['end'] ?? null;
            $startFormatted = $slot['startFormatted'] ?? ($start instanceof \DateTimeInterface ? $start->format('H:i') : '');
            $endFormatted = $slot['endFormatted'] ?? ($end instanceof \DateTimeInterface ? $end->format('H:i') : '');

            return [
                'time' => (string) $startFormatted,
                'end_time' => (string) $endFormatted,
                'start_time' => $start instanceof \DateTimeInterface ? $start->format('c') : '',
                'end_time_iso' => $end instanceof \DateTimeInterface ? $end->format('c') : '',
            ];
        }, $slots);
    }

    /**
     * Get booking system operational status (Owner only)
     * 
     * Returns high-level status indicators:
     * - Booking page enabled/disabled
     * - Manual confirmation enabled/disabled
     * - Notification channels active
     * - Public booking link reachable
     * 
     * @return array Booking system status
     */
    public function getBookingStatus(): array
    {
        $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT;
        $heartbeat = (new NotificationReminderHeartbeatService())->getStatus(300);
        $db = \Config\Database::connect();
        $rulesTable = $db->prefixTable('business_notification_rules');
        $integrationsTable = $db->prefixTable('business_integrations');
        $queueTable = $db->prefixTable('notification_queue');
        $logsTable = $db->prefixTable('notification_delivery_logs');

        $ruleRows = $db->table($rulesTable)
            ->select('channel, is_enabled, reminder_offset_minutes, reminder_offsets_json')
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->get()
            ->getResultArray();

        $integrationRows = $db->table($integrationsTable)
            ->select('channel, is_active')
            ->where('business_id', $businessId)
            ->get()
            ->getResultArray();

        $enabledRuleChannels = [];
        $configuredOffsets = [];
        foreach ($ruleRows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel === '' || (int) ($row['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $enabledRuleChannels[] = $channel;
            $offsets = [];
            $offsetsJson = $row['reminder_offsets_json'] ?? null;
            if (is_string($offsetsJson) && $offsetsJson !== '') {
                $decoded = json_decode($offsetsJson, true);
                if (is_array($decoded)) {
                    $offsets = array_map(static fn($value): int => (int) $value, $decoded);
                }
            }
            if ($offsets === [] && isset($row['reminder_offset_minutes']) && $row['reminder_offset_minutes'] !== null) {
                $offsets = [(int) $row['reminder_offset_minutes']];
            }

            foreach ($offsets as $offset) {
                if ($offset >= 0) {
                    $configuredOffsets[] = $offset;
                }
            }
        }

        $activeIntegrations = [];
        foreach ($integrationRows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel !== '' && (int) ($row['is_active'] ?? 0) === 1) {
                $activeIntegrations[] = $channel;
            }
        }

        $enabledRuleChannels = array_values(array_unique($enabledRuleChannels));
        sort($enabledRuleChannels);
        $activeIntegrations = array_values(array_unique($activeIntegrations));
        sort($activeIntegrations);
        $configuredOffsets = array_values(array_unique($configuredOffsets));
        rsort($configuredOffsets);

        $activeReminderChannels = array_values(array_intersect($enabledRuleChannels, $activeIntegrations));
        sort($activeReminderChannels);

        $queuedCount = (int) $db->table($queueTable)
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'queued')
            ->countAllResults();

        $sentToday = (int) $db->table($queueTable)
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'sent')
            ->where('updated_at >=', gmdate('Y-m-d 00:00:00'))
            ->countAllResults();

        $lastDelivery = $db->table($logsTable)
            ->select('created_at, channel')
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'success')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getFirstRow('array');

        $reminderSummary = 'Automation active';
        $reminderTone = 'success';

        if ($enabledRuleChannels === []) {
            $reminderSummary = 'No reminder rules enabled';
            $reminderTone = 'warning';
        } elseif ($activeReminderChannels === []) {
            $reminderSummary = 'Rules enabled but integrations inactive';
            $reminderTone = 'warning';
        } elseif (($heartbeat['last_run_ts'] ?? null) === null) {
            $reminderSummary = 'Waiting for first heartbeat run';
            $reminderTone = 'warning';
        } elseif (!empty($heartbeat['is_stale'])) {
            $reminderSummary = $queuedCount > 0 ? 'Heartbeat stale with queued reminders' : 'Heartbeat stale';
            $reminderTone = $queuedCount > 0 ? 'danger' : 'warning';
        }

        return [
            'booking_enabled' => true,
            'confirmation_enabled' => false,
            'email_enabled' => in_array('email', $activeReminderChannels, true),
            'whatsapp_enabled' => in_array('whatsapp', $activeReminderChannels, true),
            'booking_url' => base_url('/booking'),
            'reminders' => [
                'summary' => $reminderSummary,
                'tone' => $reminderTone,
                'last_run_label' => $this->formatDashboardStatusTimestamp($heartbeat['last_run_ts'] ?? null),
                'last_delivery_label' => $this->formatDashboardStatusTimestamp($lastDelivery['created_at'] ?? null),
                'queued_count' => $queuedCount,
                'sent_today' => $sentToday,
                'enabled_rule_channels' => $enabledRuleChannels,
                'active_channels' => $activeReminderChannels,
                'configured_offsets' => $configuredOffsets,
                'is_locked' => !empty($heartbeat['is_locked']),
                'heartbeat_interval_seconds' => (int) ($heartbeat['interval_seconds'] ?? 300),
            ],
        ];
    }

    /**
     * @param int|string|null $value
     */
    protected function formatDashboardStatusTimestamp($value): string
    {
        if ($value === null || $value === '') {
            return 'Not recorded yet';
        }

        $timezone = new \DateTimeZone($this->localizationService->getTimezone());

        try {
            if (is_numeric($value)) {
                $dt = (new \DateTimeImmutable('@' . (int) $value))->setTimezone($timezone);
            } else {
                $dt = new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
                $dt = $dt->setTimezone($timezone);
            }
        } catch (\Throwable $e) {
            return 'Unavailable';
        }

        return $dt->format('M j, Y g:i A T');
    }

    /**
     * Helper: Get next available slot details for provider
     * 
     * Uses AvailabilityService to respect provider schedule, breaks,
     * blocked times, appointment conflicts, buffer time, and service duration.
     *
     * @param int $providerId
     * @return array Slot details
     */
    protected function getNextAvailableSlotDetails(int $providerId): array
    {
        $timezone = $this->localizationService->getTimezone();
        $tz = new \DateTimeZone($timezone);
        $today = (new \DateTimeImmutable('today', $tz))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('tomorrow', $tz))->format('Y-m-d');

        $serviceId = $this->getProviderDefaultServiceId($providerId);
        if (!$serviceId) {
            return [
                'has_slot' => false,
                'no_slots_today' => true,
                'is_today' => false,
                'date' => null,
                'time' => null,
                'label' => 'No slots available today'
            ];
        }

        $bufferMinutes = $this->availabilityService->getBufferTime($providerId);

        // Check for slots today
        $todaySlots = $this->availabilityService->getAvailableSlots(
            $providerId,
            $today,
            $serviceId,
            $bufferMinutes,
            $timezone
        );

        if (!empty($todaySlots)) {
            $slot = $todaySlots[0];
            $time = $slot['startFormatted'] ?? $slot['start']->format('H:i');
            return [
                'has_slot' => true,
                'no_slots_today' => false,
                'is_today' => true,
                'date' => $today,
                'time' => $time,
                'label' => 'Today at ' . $time
            ];
        }

        // Look ahead for the next available slot (up to 30 days)
        $daysAhead = 30;
        $nextSlot = null;
        $nextDate = null;

        for ($i = 1; $i <= $daysAhead; $i++) {
            $date = (new \DateTimeImmutable($today, $tz))->modify('+' . $i . ' days')->format('Y-m-d');
            $slots = $this->availabilityService->getAvailableSlots(
                $providerId,
                $date,
                $serviceId,
                $bufferMinutes,
                $timezone
            );

            if (!empty($slots)) {
                $nextSlot = $slots[0];
                $nextDate = $date;
                break;
            }
        }

        if ($nextSlot && $nextDate) {
            $time = $nextSlot['startFormatted'] ?? $nextSlot['start']->format('H:i');
            if ($nextDate === $tomorrow) {
                $label = 'Tomorrow at ' . $time;
            } else {
                // No date-format setting exists; see LocalizationSettingsService.
                $dateLabel = (new \DateTimeImmutable($nextDate, $tz))->format('M j, Y');
                $label = $dateLabel . ' at ' . $time;
            }

            return [
                'has_slot' => true,
                'no_slots_today' => true,
                'is_today' => false,
                'date' => $nextDate,
                'time' => $time,
                'label' => $label
            ];
        }

        return [
            'has_slot' => false,
            'no_slots_today' => true,
            'is_today' => false,
            'date' => null,
            'time' => null,
            'label' => 'No slots available today'
        ];
    }

    /**
     * Helper: Get provider default service ID (shortest active service)
     * 
     * @param int $providerId
     * @return int|null
     */
    protected function getProviderDefaultServiceId(int $providerId): ?int
    {
        $db = \Config\Database::connect();
        $providerServicesTable = $db->prefixTable('providers_services');
        $hasProviderServices = method_exists($db, 'tableExists')
            ? ($db->tableExists('providers_services') || $db->tableExists($providerServicesTable))
            : true;
        $servicesHasIsActive = method_exists($db, 'fieldExists') ? $db->fieldExists('is_active', 'xs_services') : true;

        try {
            if ($hasProviderServices) {
                $builder = $db->table($providerServicesTable . ' ps');
                $builder->select('s.id, s.duration_min')
                    ->join('xs_services s', 's.id = ps.service_id')
                    ->where('ps.provider_id', $providerId);

                if ($servicesHasIsActive) {
                    $builder->where('s.is_active', true);
                }

                $builder->orderBy('s.duration_min', 'ASC')
                    ->orderBy('s.id', 'ASC')
                    ->limit(1);

                $service = $builder->get()->getRowArray();
                if ($service && isset($service['id'])) {
                    return (int) $service['id'];
                }
            }
        } catch (\Throwable $e) {
            // Ignore and return null below
        }

        // Do not fall back to global services: default must be provider-assigned.
        return null;
    }

    /**
     * Get provider working hours for a specific day
     * 
     * @param int $providerId
     * @param string $dayOfWeek Day name (monday, tuesday, etc.)
     * @param int $weekdayNum Weekday number (0=Sunday, 6=Saturday)
     * @return array|null Working hours array or null if not working
     */
    private function getProviderWorkingHours(int $providerId, string $dayOfWeek, int $weekdayNum): ?array
    {
        // First check xs_provider_schedules (provider-specific)
        $schedule = $this->providerScheduleModel
            ->where('provider_id', $providerId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', 1)
            ->first();

        if ($schedule) {
            $breaks = [];
            if (!empty($schedule['break_start']) && !empty($schedule['break_end'])) {
                $breaks[] = [
                    'start' => $schedule['break_start'],
                    'end' => $schedule['break_end']
                ];
            }
            return [
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'breaks' => $breaks
            ];
        }

        // Fallback to xs_business_hours
        $businessHours = $this->businessHourModel
            ->where('provider_id', $providerId)
            ->where('weekday', $weekdayNum)
            ->first();

        if ($businessHours) {
            $breaks = [];
            if (!empty($businessHours['breaks_json'])) {
                $breaks = json_decode($businessHours['breaks_json'], true) ?: [];
            }
            return [
                'start_time' => $businessHours['start_time'],
                'end_time' => $businessHours['end_time'],
                'breaks' => $breaks
            ];
        }

        return null;
    }

    /**
     * Cache helper for metrics (5-minute TTL)
     * 
     * @param int|int[]|null $providerId
     * @return array
     */
    public function getCachedMetrics(int|array|null $providerId = null): array
    {
        // Use a stable cache key: for arrays, sort and join IDs to get a consistent key
        if (is_array($providerId)) {
            $sorted = $providerId;
            sort($sorted);
            $cacheKey = "dashboard_metrics_staff_" . implode('_', $sorted);
        } else {
            $cacheKey = "dashboard_metrics_" . ($providerId ?? 'admin');
        }
        
        return $this->rememberCache($cacheKey, 300, function() use ($providerId) {
            return $this->getTodayMetrics($providerId);
        });
    }

    /**
     * Invalidate dashboard cache
     * Call this when appointments are created/updated/cancelled
     * 
     * @param int|null $providerId
     */
    public function invalidateCache(?int $providerId = null): void
    {
        $cacheKey = "dashboard_metrics_" . ($providerId ?? 'admin');
        $this->deleteCacheKey($cacheKey);
        
        // Also invalidate admin cache if provider cache is invalidated
        if ($providerId !== null) {
            $this->deleteCacheKey('dashboard_metrics_admin');
        }
    }

    protected function rememberCache(string $cacheKey, int $ttl, callable $resolver): array
    {
        return cache()->remember($cacheKey, $ttl, $resolver);
    }

    protected function deleteCacheKey(string $cacheKey): void
    {
        cache()->delete($cacheKey);
    }

    /**
     * Format recent activities for display
     * Converts activity records into formatted array with status classes
     * 
     * @param array $activities Raw activity records
     * @return array Formatted activities with labels and status classes
     */
    public function formatRecentActivities(array $activities): array
    {
        $formatted = [];
        
        foreach ($activities as $activity) {
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

            $formatted[] = [
                'user_name' => $activity['customer_name'],
                'activity' => $action,
                'status' => $status_class,
                'date' => date('Y-m-d', strtotime($activity['updated_at']))
            ];
        }

        return $formatted;
    }
}

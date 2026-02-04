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
use App\Models\ProviderScheduleModel;
use App\Models\BusinessHourModel;
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
    protected ProviderScheduleModel $providerScheduleModel;
    protected BusinessHourModel $businessHourModel;
    protected LocalizationSettingsService $localizationService;
    protected AvailabilityService $availabilityService;

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->customerModel = new CustomerModel();
        $this->providerScheduleModel = new ProviderScheduleModel();
        $this->businessHourModel = new BusinessHourModel();
        $this->localizationService = new LocalizationSettingsService();
        $this->availabilityService = new AvailabilityService();
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
        
        // Get localized date format
        $localizationContext = $this->localizationService->getContext();
        $dateFormat = $localizationContext['date_format'] ?? 'Y-m-d';
        $currentDate = date($dateFormat);
        
        // Get timezone from settings
        $timezone = env('app.timezone', 'UTC');

        return [
            'business_name' => $businessName,
            'current_date' => $currentDate,
            'timezone' => $timezone,
            'user_name' => $user['name'] ?? 'User',
            'user_email' => $user['email'] ?? '',
            'user_role' => $userRole,
            'user_id' => $userId,
            'provider_id' => $providerId,
        ];
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
     * @param int|null $providerId Provider ID for scope filtering (null for admin)
     * @return array Metrics data
     */
    public function getTodayMetrics(?int $providerId = null): array
    {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $upcomingWindow = date('Y-m-d H:i:s', strtotime('+4 hours'));

        $builder = $this->appointmentModel->builder();

        // Apply provider scope if provided
        if ($providerId !== null) {
            $builder->where('provider_id', $providerId);
        }

        // Total appointments today
        $total = $builder
            ->where('DATE(start_time)', $today)
            ->countAllResults(false);

        // Upcoming appointments (next 4 hours)
        $upcoming = $builder
            ->where('DATE(start_time)', $today)
            ->where('start_time >=', $now)
            ->where('start_time <=', $upcomingWindow)
            ->whereIn('xs_appointments.status', ['pending', 'confirmed'])
            ->countAllResults(false);

        // Pending confirmation
        $pending = $builder
            ->where('DATE(start_time)', $today)
            ->where('xs_appointments.status', 'pending')
            ->countAllResults(false);

        // Cancelled/rescheduled today
        $cancelled = $builder
            ->where('DATE(start_time)', $today)
            ->whereIn('xs_appointments.status', ['cancelled', 'no-show'])
            ->countAllResults(false);

        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'pending' => $pending,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * Get today's schedule snapshot (business hours only)
     * 
     * Returns appointments grouped by provider for today.
     * Includes time range, customer name, and status.
     * 
     * @param int|null $providerId Provider ID for scope filtering (null for admin)
     * @return array Schedule data grouped by provider
     */
    public function getTodaySchedule(?int $providerId = null): array
    {
        $today = date('Y-m-d');
        
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
        ->where('DATE(xs_appointments.start_time)', $today)
        ->whereIn('xs_appointments.status', ['pending', 'confirmed', 'completed'])
        ->orderBy('xs_appointments.provider_id', 'ASC')
        ->orderBy('xs_appointments.start_time', 'ASC');

        // Apply provider scope
        if ($providerId !== null) {
            $builder->where('xs_appointments.provider_id', $providerId);
        }

        $appointments = $builder->get()->getResultArray();

        // Group by provider
        $schedule = [];
        foreach ($appointments as $appt) {
            $providerName = $appt['provider_name'] ?? 'Unknown Provider';
            if (!isset($schedule[$providerName])) {
                $schedule[$providerName] = [];
            }

            $schedule[$providerName][] = [
                'id' => $appt['id'],
                'hash' => $appt['hash'] ?? null,
                'start_time' => $this->localizationService->formatTimeForDisplay(date('H:i:s', strtotime($appt['start_time']))),
                'end_time' => $this->localizationService->formatTimeForDisplay(date('H:i:s', strtotime($appt['end_time']))),
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
     * @param int|null $providerId Provider ID for scope filtering
     * @return array Alert data with action URLs
     */
    public function getAlerts(?int $providerId = null): array
    {
        $alerts = [];

        // Check for pending confirmations
        $builder = $this->appointmentModel->builder();
        if ($providerId !== null) {
            $builder->where('provider_id', $providerId);
        }
        
        $pendingCount = $builder
            ->where('xs_appointments.status', 'pending')
            ->where('DATE(start_time) >=', date('Y-m-d'))
            ->countAllResults();

        if ($pendingCount > 0) {
            $alerts[] = [
                'type' => 'confirmation_pending',
                'severity' => 'warning',
                'message' => "{$pendingCount} appointment(s) awaiting confirmation",
                'action_label' => 'Review Appointments',
                'action_url' => base_url('/appointments?filter=pending'),
            ];
        }

        // Check for providers without working hours (Admin only)
        // TODO: Re-enable when working_hours column is added to xs_users table
        /*
        if ($providerId === null) {
            $providersWithoutHours = $this->userModel
                ->where('role', 'provider')
                ->where('is_active', true)
                ->where('working_hours IS NULL')
                ->countAllResults();

            if ($providersWithoutHours > 0) {
                $alerts[] = [
                    'type' => 'missing_hours',
                    'severity' => 'error',
                    'message' => "{$providersWithoutHours} provider(s) have no working hours set",
                    'action_label' => 'Configure Hours',
                    'action_url' => base_url('/user-management?filter=no-hours'),
                ];
            }
        }
        */

        // TODO: Add more alert types as needed:
        // - Booking page disabled
        // - Upcoming holidays/blocked periods
        // - Overbooking conflicts
        // - Low appointment capacity

        return $alerts;
    }

    /**
     * Get upcoming appointments (next 7 days, max 10)
     * 
     * @param int|null $providerId Provider ID for scope filtering
     * @return array Upcoming appointments
     */
    public function getUpcomingAppointments(?int $providerId = null): array
    {
        $today = date('Y-m-d');
        $nextWeek = date('Y-m-d', strtotime('+7 days'));

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
        ->where('DATE(xs_appointments.start_time) >=', $today)
        ->where('DATE(xs_appointments.start_time) <=', $nextWeek)
        ->whereIn('xs_appointments.status', ['pending', 'confirmed'])
        ->orderBy('xs_appointments.start_time', 'ASC')
        ->limit(10);

        // Apply provider scope
        if ($providerId !== null) {
            $builder->where('xs_appointments.provider_id', $providerId);
        }

        $appointments = $builder->get()->getResultArray();

        // Format for display
        $formatted = [];
        foreach ($appointments as $appt) {
            $formatted[] = [
                'id' => $appt['id'],
                'date' => date('Y-m-d', strtotime($appt['start_time'])),
                'time' => date('H:i', strtotime($appt['start_time'])),
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
     * @param int|null $providerId Provider ID for scope filtering
     * @return array Provider availability data
     */
    public function getProviderAvailability(?int $providerId = null): array
    {
        $builder = $this->userModel->builder();
        $builder->select('id, name, color')
            ->where('role', 'provider')
            ->where('is_active', true);

        if ($providerId !== null) {
            $builder->where('id', $providerId);
        }

        $providers = $builder->get()->getResultArray();

        $now = new \DateTime();
        $currentTime = $now->format('H:i:s');
        $dayOfWeek = strtolower($now->format('l'));
        $weekdayNum = (int) $now->format('w');

        $availability = [];
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
            
            // Get provider's services
            $providerServices = $this->getProviderServices($provider['id']);

            $availability[] = [
                'id' => $provider['id'],
                'name' => $provider['name'],
                'status' => $status,
                'next_slot' => $nextSlot,
                'color' => $provider['color'] ?? '#3B82F6',
                'services' => $providerServices,
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
        $db = \Config\Database::connect();
        $hasProviderServices = method_exists($db, 'tableExists') ? $db->tableExists('xs_provider_services') : true;
        $servicesHasIsActive = method_exists($db, 'fieldExists') ? $db->fieldExists('is_active', 'xs_services') : true;
        
        // Check if provider_services table exists (linking table)
        try {
            if ($hasProviderServices) {
                $builder = $db->table('xs_provider_services ps');
                $builder->select('s.name')
                    ->join('xs_services s', 's.id = ps.service_id')
                    ->where('ps.provider_id', $providerId);

                if ($servicesHasIsActive) {
                    $builder->where('s.is_active', true);
                }
                
                $services = $builder->get()->getResultArray();
                return array_column($services, 'name');
            }
            
            // No provider_services table, fall through to general services fallback
        } catch (\Exception $e) {
            // Exception occurred, fall through to fallback
        }
        
        // Fallback: try to get all active services if no provider_services table
        try {
            $builder = $db->table('xs_services');
            $builder->select('name');

            if ($servicesHasIsActive) {
                $builder->where('is_active', true);
            }

            $builder->limit(5);
            
            $services = $builder->get()->getResultArray();
            return array_column($services, 'name');
        } catch (\Exception $e2) {
            return [];
        }
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
        // TODO: Implement based on actual settings storage
        // For now, return placeholder data
        
        return [
            'booking_enabled' => true, // From settings
            'confirmation_enabled' => false, // From settings
            'email_enabled' => true, // Check email configuration
            'whatsapp_enabled' => false, // Check WhatsApp configuration
            'booking_url' => base_url('/public/booking'),
        ];
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
                $context = $this->localizationService->getContext();
                $dateFormat = $context['date_format'] ?? 'M j, Y';
                $dateLabel = (new \DateTimeImmutable($nextDate, $tz))->format($dateFormat);
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
        $hasProviderServices = method_exists($db, 'tableExists') ? $db->tableExists('xs_provider_services') : true;
        $servicesHasIsActive = method_exists($db, 'fieldExists') ? $db->fieldExists('is_active', 'xs_services') : true;

        try {
            if ($hasProviderServices) {
                $builder = $db->table('xs_provider_services ps');
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
            // Ignore and fallback below
        }

        if ($servicesHasIsActive) {
            $service = $this->serviceModel
                ->where('is_active', true)
                ->orderBy('duration_min', 'ASC')
                ->orderBy('id', 'ASC')
                ->first();
        } else {
            $service = $this->serviceModel
                ->orderBy('duration_min', 'ASC')
                ->orderBy('id', 'ASC')
                ->first();
        }

        return $service['id'] ?? null;
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
     * Adjust candidate time to skip break periods
     * 
     * @param string $candidateTime Time in H:i:s format
     * @param array $breaks Array of break periods
     * @param string $endTime Working hours end time
     * @return string|null Adjusted time or null if no valid time found
     */
    private function adjustForBreaks(string $candidateTime, array $breaks, string $endTime): ?string
    {
        foreach ($breaks as $break) {
            $breakStart = $break['start'] ?? null;
            $breakEnd = $break['end'] ?? null;
            
            if (!$breakStart || !$breakEnd) {
                continue;
            }

            // Normalize break times to H:i:s format
            if (strlen($breakStart) === 5) $breakStart .= ':00';
            if (strlen($breakEnd) === 5) $breakEnd .= ':00';

            // If candidate is during break, move to end of break
            if ($candidateTime >= $breakStart && $candidateTime < $breakEnd) {
                $candidateTime = $breakEnd;
            }
        }

        // Check if adjusted time is still within working hours
        if ($candidateTime >= $endTime) {
            return null;
        }

        return $candidateTime;
    }

    /**
     * Cache helper for metrics (5-minute TTL)
     * 
     * @param int|null $providerId
     * @return array
     */
    public function getCachedMetrics(?int $providerId = null): array
    {
        $cacheKey = "dashboard_metrics_" . ($providerId ?? 'admin');
        
        return cache()->remember($cacheKey, 300, function() use ($providerId) {
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
        cache()->delete($cacheKey);
        
        // Also invalidate admin cache if provider cache is invalidated
        if ($providerId !== null) {
            cache()->delete("dashboard_metrics_admin");
        }
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

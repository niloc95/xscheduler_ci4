<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\CustomerModel;
use App\Models\ProviderScheduleModel;
use App\Models\BusinessHourModel;

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

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->customerModel = new CustomerModel();
        $this->providerScheduleModel = new ProviderScheduleModel();
        $this->businessHourModel = new BusinessHourModel();
        $this->localizationService = new LocalizationSettingsService();
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
                'start_time' => date('H:i', strtotime($appt['start_time'])),
                'end_time' => date('H:i', strtotime($appt['end_time'])),
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

            $nextSlot = $this->getNextAvailableSlot($provider['id']);
            
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
        
        // Check if provider_services table exists (linking table)
        try {
            $builder = $db->table('xs_provider_services ps');
            $builder->select('s.name')
                ->join('xs_services s', 's.id = ps.service_id')
                ->where('ps.provider_id', $providerId)
                ->where('s.is_active', true);
            
            $services = $builder->get()->getResultArray();
            return array_column($services, 'name');
        } catch (\Exception $e) {
            // Fallback: try to get all active services if no provider_services table
            try {
                $builder = $db->table('xs_services');
                $builder->select('name')
                    ->where('is_active', true)
                    ->limit(5);
                
                $services = $builder->get()->getResultArray();
                return array_column($services, 'name');
            } catch (\Exception $e2) {
                return [];
            }
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
     * Helper: Get next available slot for provider
     * 
     * Returns the next available time slot respecting:
     * - Provider's working hours (from xs_provider_schedules or xs_business_hours)
     * - Break times
     * - Existing appointments
     * 
     * @param int $providerId
     * @return string|null Time string (HH:MM) or null if not working today
     */
    protected function getNextAvailableSlot(int $providerId): ?string
    {
        $today = date('Y-m-d');
        $now = new \DateTime();
        $currentTime = $now->format('H:i:s');
        $dayOfWeek = strtolower($now->format('l')); // 'monday', 'tuesday', etc.
        $weekdayNum = (int) $now->format('w'); // 0=Sunday, 1=Monday, etc.

        // Step 1: Get provider's working hours for today
        $workingHours = $this->getProviderWorkingHours($providerId, $dayOfWeek, $weekdayNum);
        
        if (!$workingHours) {
            // Provider not working today
            return null;
        }

        $startTime = $workingHours['start_time'];
        $endTime = $workingHours['end_time'];
        $breaks = $workingHours['breaks'] ?? [];

        // Step 2: If current time is past end time, provider is done for the day
        if ($currentTime >= $endTime) {
            return null;
        }

        // Step 3: Get today's future appointments for this provider
        $appointments = $this->appointmentModel
            ->where('provider_id', $providerId)
            ->where('DATE(start_time)', $today)
            ->where('start_time >', $now->format('Y-m-d H:i:s'))
            ->whereIn('xs_appointments.status', ['pending', 'confirmed'])
            ->orderBy('start_time', 'ASC')
            ->findAll();

        // Step 4: Determine the earliest available slot
        // Start from either now + 30 min (buffer) or working hours start, whichever is later
        $bufferTime = (clone $now)->modify('+30 minutes')->format('H:i:s');
        $candidateTime = max($bufferTime, $startTime);

        // If candidate is past end time, no slots available
        if ($candidateTime >= $endTime) {
            return null;
        }

        // Step 5: Check if candidate time is during a break
        $candidateTime = $this->adjustForBreaks($candidateTime, $breaks, $endTime);
        if ($candidateTime === null || $candidateTime >= $endTime) {
            return null;
        }

        // Step 6: Check if candidate time conflicts with appointments
        if (empty($appointments)) {
            // No appointments, candidate time is available
            return substr($candidateTime, 0, 5); // Return HH:MM format
        }

        // Check if there's a gap before the first appointment
        $firstApptStart = date('H:i:s', strtotime($appointments[0]['start_time']));
        if ($candidateTime < $firstApptStart) {
            return substr($candidateTime, 0, 5);
        }

        // Find a gap between appointments
        foreach ($appointments as $i => $apt) {
            $aptEnd = date('H:i:s', strtotime($apt['end_time']));
            
            // Check if there's another appointment after this one
            if (isset($appointments[$i + 1])) {
                $nextAptStart = date('H:i:s', strtotime($appointments[$i + 1]['start_time']));
                
                // Is there a gap?
                if ($aptEnd < $nextAptStart) {
                    // Check the gap is not during break
                    $gapStart = $this->adjustForBreaks($aptEnd, $breaks, $endTime);
                    if ($gapStart !== null && $gapStart < $nextAptStart && $gapStart < $endTime) {
                        return substr($gapStart, 0, 5);
                    }
                }
            } else {
                // This is the last appointment, check if there's time after
                $afterLast = $this->adjustForBreaks($aptEnd, $breaks, $endTime);
                if ($afterLast !== null && $afterLast < $endTime) {
                    return substr($afterLast, 0, 5);
                }
            }
        }

        // No available slots found
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
}

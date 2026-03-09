<?php

/**
 * =============================================================================
 * TODAY VIEW SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/TodayViewService.php
 * @description Builds the server-side render model for the "Today" view.
 *              Uses the shared Calendar Engine (CalendarRangeService,
 *              TimeGridService, EventLayoutService) for consistency.
 *
 * OUTPUT MODEL:
 * ─────────────────────────────────────────────────────────────────
 * {
 *   date:             'Y-m-d',
 *   dayName:          'Monday',
 *   dayLabel:         'Monday, March 10, 2026',
 *   weekdayName:      'monday',
 *   weekday:          int (0=Sun … 6=Sat),
 *   isToday:          bool,
 *   isPast:           bool,
 *   businessHours:    { startTime: 'HH:MM', endTime: 'HH:MM' },
 *   providerColumns:  [
 *     {
 *       provider: { id, name, color },
 *       workingHours: { startTime, endTime, source, isActive },
 *       grid: {
 *         dayStart: 'HH:MM',
 *         dayEnd: 'HH:MM',
 *         slots: [
 *           {
 *             time: 'HH:MM',
 *             index: 0,
 *             hour: 8,
 *             minute: 0,
 *             isHourMark: true,
 *             appointments: [ ... ],
 *             _topPx: 0,
 *             _heightPx: 60
 *           },
 *           // ... more slots
 *         ]
 *       },
 *       appointments: [ ...positioned with _column, _columns_total, etc. ]
 *     }
 *   ],
 *   appointments:     [ ...all appointments flat ],
 *   totalAppointments: int,
 * }
 *
 * SHARED ENGINE SERVICES:
 * ─────────────────────────────────────────────────────────────────
 * 1. CalendarRangeService
 *    - getRange('today', $date) → returns single date
 *
 * 2. TimeGridService
 *    - generateDayGrid($date, $providerSchedule) → time slots
 *    - Uses ProviderScheduleModel override if available
 *
 * 3. EventLayoutService
 *    - resolveLayout($appointments) → positioned events with columns
 *
 * 4. SlotInjectionTrait
 *    - injectIntoSlots($slots, $events) → events placed in grid
 *
 * @package     App\Services\Calendar
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services\Calendar;

use App\Services\Appointment\AppointmentQueryService;
use App\Services\Appointment\AppointmentFormatterService;
use App\Models\ProviderScheduleModel;

class TodayViewService
{
    use SlotInjectionTrait;

    private CalendarRangeService $range;
    private TimeGridService $timeGrid;
    private EventLayoutService $eventLayout;
    private AppointmentQueryService $appointmentQuery;
    private AppointmentFormatterService $appointmentFormatter;
    private ProviderScheduleModel $providerSchedule;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?TimeGridService $timeGrid = null,
        ?EventLayoutService $eventLayout = null,
        ?AppointmentQueryService $appointmentQuery = null,
        ?AppointmentFormatterService $appointmentFormatter = null,
        ?ProviderScheduleModel $providerSchedule = null
    ) {
        $this->range = $range ?? new CalendarRangeService();
        $this->timeGrid = $timeGrid ?? new TimeGridService();
        $this->eventLayout = $eventLayout ?? new EventLayoutService();
        $this->appointmentQuery = $appointmentQuery ?? new AppointmentQueryService();
        $this->appointmentFormatter = $appointmentFormatter ?? new AppointmentFormatterService();
        $this->providerSchedule = $providerSchedule ?? new ProviderScheduleModel();
    }

    /**
     * Build the complete Today View render model.
     *
     * @param string $date ISO date string (Y-m-d)
     * @param int|null $businessId Business ID for scoping
     * @param array $providerIds Filter by specific provider IDs (null = all)
     * @return array Render model
     */
    public function build(string $date, ?int $businessId = null, ?array $providerIds = null): array
    {
        // Get today's configuration
        $dateObj = new \DateTime($date);
        $weekday = (int) $dateObj->format('w'); // 0=Sun, 6=Sat

        // Get business hours
        $businessHours = $this->getBusinessHours();

        // Query appointments for today
        $appointments = $this->appointmentQuery->getForRange(
            $date,
            $date,
            [
                'provider_ids' => $providerIds,
                'business_id'  => $businessId,
            ]
        );

        $formatted = $this->appointmentFormatter->formatManyForCalendar($appointments);

        if (!empty($providerIds)) {
            $providerFilter = array_map('intval', $providerIds);
            $formatted = array_values(array_filter(
                $formatted,
                static fn(array $apt): bool => in_array((int) ($apt['provider_id'] ?? 0), $providerFilter, true)
            ));
        }

        // Get providers (filtered by IDs if provided)
        $providers = $this->getProvidersForDate($businessId, $weekday, $providerIds);

        // Build per-provider columns with grids and appointments
        $providerColumns = [];
        foreach ($providers as $provider) {
            $providerColumns[] = $this->buildProviderColumn($provider, $date, $formatted);
        }

        // Build base grid for timeline reference
        $baseGrid = $this->timeGrid->generateDayGrid($date);

        return [
            'date'              => $date,
            'dayName'           => $dateObj->format('l'),
            'dayLabel'          => $dateObj->format('l, F j, Y'),
            'weekdayName'       => strtolower($dateObj->format('l')),
            'weekday'           => $weekday,
            'isToday'           => $date === date('Y-m-d'),
            'isPast'            => strtotime($date) < strtotime('today'),
            'businessHours'     => $businessHours,
            'grid'              => $baseGrid, // Reference grid
            'providerColumns'   => $providerColumns,
            'appointments'      => $formatted,
            'totalAppointments' => count($formatted),
        ];
    }

    /**
     * Build a single provider's column with grid and positioned appointments.
     *
     * @param array $provider Provider data
     * @param string $date ISO date
     * @param array $allAppointments All formatted appointments for today
     * @return array Provider column structure
     */
    private function buildProviderColumn(array $provider, string $date, array $allAppointments): array
    {
        // Get provider's working hours for this date
        $weekday = (int) (new \DateTime($date))->format('w');
        $workingHours = $this->getProviderWorkingHours($provider['id'], $weekday);

        // Generate time grid based on provider's hours
        $grid = $this->timeGrid->generateDayGridWithProviderHours($date, $workingHours);

        // Filter appointments for this provider
        $providerAppointments = array_filter(
            $allAppointments,
            fn($apt) => ($apt['provider_id'] ?? null) === $provider['id']
        );

        // Resolve overlapping layout
        $positioned = $this->eventLayout->resolveLayout($providerAppointments);

        // Inject appointments into time slots
        $gridWithAppointments = $this->injectIntoSlots(
            $grid['slots'] ?? [],
            $positioned,
            $grid,
            $this->timeGrid->getResolution()
        );

        return [
            'provider'         => [
                'id'    => $provider['id'],
                'name'  => $provider['name'],
                'color' => $provider['color'] ?? '#999999',
            ],
            'workingHours'     => $workingHours,
            'grid'             => [
                'dayStart' => $grid['dayStart'] ?? '08:00',
                'dayEnd'   => $grid['dayEnd'] ?? '17:00',
                'slots'    => $gridWithAppointments,
            ],
            'appointments'     => $positioned,
        ];
    }

    /**
     * Get providers active on a specific date.
     *
     * @param int|null $businessId
     * @param int $weekday Day of week (0-6)
     * @param array|null $filterIds Filter by specific provider IDs
     * @return array Provider records
     */
    private function getProvidersForDate(?int $businessId, int $weekday, ?array $filterIds = null): array
    {
        // Load providers from database
        $providerModel = new \App\Models\UserModel();
        $query = $providerModel->where('role', 'provider');

        if ($filterIds) {
            $query = $query->whereIn('id', $filterIds);
        }

        $providers = $query->findAll();

        return array_filter($providers, fn($p) => $this->isProviderActiveOnDay($p['id'], $weekday));
    }

    /**
     * Check if provider is active on a specific day.
     *
     * @param int $providerId
     * @param int $weekday
     * @return bool
     */
    private function isProviderActiveOnDay(int $providerId, int $weekday): bool
    {
        $schedule = $this->providerSchedule
            ->where('provider_id', $providerId)
            ->where('day_of_week', $weekday)
            ->first();

        return $schedule ? (bool) $schedule['is_active'] : true;
    }

    /**
     * Get provider's working hours for a specific day.
     * Falls back to business hours if no provider schedule exists.
     *
     * @param int $providerId
     * @param int $weekday
     * @return array Working hours { startTime, endTime, breakStart, breakEnd, source, isActive }
     */
    private function getProviderWorkingHours(int $providerId, int $weekday): array
    {
        // Try provider-specific schedule first
        $schedule = $this->providerSchedule
            ->where('provider_id', $providerId)
            ->where('day_of_week', $weekday)
            ->first();

        if ($schedule) {
            return [
                'startTime'  => substr($schedule['start_time'], 0, 5),
                'endTime'    => substr($schedule['end_time'], 0, 5),
                'breakStart' => $schedule['break_start'] ? substr($schedule['break_start'], 0, 5) : null,
                'breakEnd'   => $schedule['break_end'] ? substr($schedule['break_end'], 0, 5) : null,
                'source'     => 'provider_schedule',
                'isActive'   => (bool) $schedule['is_active'],
            ];
        }

        // Fall back to business hours
        return $this->getBusinessHours();
    }

    /**
     * Get default business hours.
     *
     * @return array { startTime, endTime, source }
     */
    private function getBusinessHours(): array
    {
        // Get from SettingModel
        $settingModel = new \App\Models\SettingModel();

        return [
            'startTime'  => $settingModel->getValue('booking.day_start', '08:00'),
            'endTime'    => $settingModel->getValue('booking.day_end', '17:00'),
            'breakStart' => $settingModel->getValue('booking.break_start', '12:00'),
            'breakEnd'   => $settingModel->getValue('booking.break_end', '13:00'),
            'source'     => 'business_hours',
            'isActive'   => true,
        ];
    }
}

<?php

/**
 * =============================================================================
 * DAY VIEW SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/DayViewService.php
 * @description Builds the complete server-side render model for the day view.
 *              Replaces client-side slot generation (slot-engine.js::generateSlots).
 *
 * OUTPUT MODEL:
 * ─────────────────────────────────────────────────────────────────
 * {
 *   date:             'Y-m-d',
 *   dayName:          'Thursday',
 *   dayLabel:         'Thursday, February 26, 2026',
 *   weekdayName:      'thursday',       // lowercase day name string
 *   weekday:          int,              // 0=Sun … 6=Sat
 *   isToday:          bool,
 *   isPast:           bool,
 *   businessHours:    { startTime: 'HH:MM', endTime: 'HH:MM' },
 *   grid:             { dayStart, dayEnd, slots: [...] },  // base grid (all providers)
 *   providerColumns:  [
 *     { provider: { id, name }, grid: { dayStart, dayEnd, slots: [...] } },
 *     ...
 *   ],
 *   appointments:     [ ...formatted ],
 *   totalAppointments: int,
 * }
 *
 * @see         CalendarRangeService  — date/grid generation
 * @see         AppointmentQueryService — data retrieval
 * @see         AppointmentFormatterService — normalization
 * @package     App\Services\Calendar
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services\Calendar;

use App\Models\UserModel;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\Appointment\AppointmentFormatterService;
use App\Models\ProviderScheduleModel;

class DayViewService
{
    use SlotInjectionTrait;
    use ProviderWorkingHoursTrait;
    private CalendarRangeService      $range;
    private AppointmentQueryService   $query;
    private AppointmentFormatterService $formatter;
    private TimeGridService $timeGrid;
    private EventLayoutService $eventLayout;
    private ProviderScheduleModel $providerScheduleModel;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?AppointmentQueryService $query = null,
        ?AppointmentFormatterService $formatter = null,
        ?TimeGridService $timeGrid = null,
        ?EventLayoutService $eventLayout = null,
        ?ProviderScheduleModel $providerScheduleModel = null
    ) {
        $this->range     = $range     ?? new CalendarRangeService();
        $this->query     = $query     ?? new AppointmentQueryService();
        $this->formatter = $formatter ?? new AppointmentFormatterService();
        $this->timeGrid  = $timeGrid ?? new TimeGridService($this->range);
        $this->eventLayout = $eventLayout ?? new EventLayoutService();
        $this->providerScheduleModel = $providerScheduleModel ?? new ProviderScheduleModel();
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the day view render model.
     *
     * @param string     $date    Y-m-d
     * @param array      $filters provider_id, service_id, location_id, status,
     *                            user_role, scope_to_user_id
     * @param array|null $appointments Pre-formatted appointment rows (optional)
     * @return array Full render model (JSON-safe)
     */
    public function build(string $date, array $filters = [], ?array $appointments = null): array
    {
        // 1. Generate base time grid using business hours
        $grid = $this->timeGrid->generateDayGrid($date);

        // 2. Fetch appointments for the day
        if ($appointments === null) {
            $rows      = $this->query->getForRange($date, $date, $filters);
            $formatted = $this->formatter->formatManyForCalendar($rows);
        } else {
            $formatted = $appointments;
        }

        // 3. Group appointments by provider
        $providers = [];
        $providerMap = []; // provider_id => index
        $requestedProviderIds = $this->extractRequestedProviderIds($filters);
        
        // First pass: identify unique providers
        foreach ($formatted as $event) {
            $pid = $event['provider_id'] ?? 0;
            if (!isset($providerMap[$pid])) {
                $providerMap[$pid] = count($providers);
                $providers[] = [
                    'id' => $pid,
                    'name' => $event['provider_name'] ?? 'Unknown Provider',
                    'appointments' => []
                ];
            }
            $providers[$providerMap[$pid]]['appointments'][] = $event;
        }

        foreach ($this->fetchProvidersByIds($requestedProviderIds) as $provider) {
            $pid = (int) ($provider['id'] ?? 0);
            if ($pid <= 0 || isset($providerMap[$pid])) {
                continue;
            }

            $providerMap[$pid] = count($providers);
            $providers[] = [
                'id' => $pid,
                'name' => $provider['name'] ?? 'Unknown Provider',
                'appointments' => [],
            ];
        }

        // If no appointments, create a default empty provider column
        if (empty($providers)) {
            $providers[] = [
                'id' => 0,
                'name' => 'All Providers',
                'appointments' => []
            ];
        }

        // 4. Get day-of-week for provider schedule lookup
        $dateObj = new \DateTimeImmutable($date);
        $dayOfWeek = (int) $dateObj->format('w'); // 0=Sun, 6=Sat

        // 5. Inject appointments into matching time slots PER PROVIDER
        $providerColumns = [];
        $positionedAppointments = [];
        foreach ($providers as $provider) {
            // Get provider-specific working hours
            $workingHours = $this->getProviderWorkingHours($provider['id'], $dayOfWeek);
            
            // Generate provider-specific time grid
            $providerGrid = $this->timeGrid->generateDayGridWithProviderHours(
                $date,
                $workingHours
            );
            
            // SHARED ENGINE: Resolve overlapping appointments
            $positioned = $this->eventLayout->resolveLayout($provider['appointments']);
            foreach ($positioned as $event) {
                $positionedAppointments[] = $event;
            }
            
            // Inject positioned appointments into time slots
            $providerGrid['slots'] = $this->injectIntoSlots(
                $providerGrid['slots'],
                $positioned,
                $providerGrid,
                $this->timeGrid->getResolution()
            );
            
            $providerColumns[] = [
                'provider' => [
                    'id' => $provider['id'],
                    'name' => $provider['name']
                ],
                'workingHours' => $workingHours,
                'grid' => $providerGrid
            ];
        }

        // 6. Build day metadata
        $dayInfo  = $this->range->normalizeDayOfWeek($date);
        $today    = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $isToday  = $date === $today;
        $isPast   = $date < $today;

        return [
            'date'             => $date,
            'dayName'          => $dateObj->format('l'),          // 'Thursday'
            'dayLabel'         => $dateObj->format('l, F j, Y'),  // 'Thursday, February 26, 2026'
            'weekdayName'      => $dayInfo['string'],
            'weekday'          => $dayInfo['int'],
            'isToday'          => $isToday,
            'isPast'           => $isPast,
            'businessHours'    => [
                'startTime' => $this->timeGrid->getDayStart(),
                'endTime'   => $this->timeGrid->getDayEnd(),
            ],
            'grid'             => $grid, // Keep base grid for backward compatibility
            'providerColumns'  => $providerColumns, // New multi-column layout with EventLayoutService positioning
            'appointments'     => $positionedAppointments,
            'totalAppointments'=> count($positionedAppointments),
        ];
    }

    /**
     * @return int[]
     */
    private function extractRequestedProviderIds(array $filters): array
    {
        $providerIds = [];

        if (!empty($filters['provider_id'])) {
            $providerIds[] = (int) $filters['provider_id'];
        }

        foreach ((array) ($filters['provider_ids'] ?? []) as $providerId) {
            $providerIds[] = (int) $providerId;
        }

        $providerIds = array_values(array_unique(array_filter($providerIds, static fn (int $providerId): bool => $providerId > 0)));

        return $providerIds;
    }

    /**
     * @param int[] $providerIds
     * @return array<int, array{id:int,name:string}>
     */
    private function fetchProvidersByIds(array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }

        $rows = (new UserModel())
            ->select('id, name')
            ->whereIn('id', $providerIds)
            ->findAll();

        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => (string) ($row['name'] ?? 'Unknown Provider'),
            ];
        }

        $ordered = [];
        foreach ($providerIds as $providerId) {
            if (isset($rowsById[$providerId])) {
                $ordered[] = $rowsById[$providerId];
            }
        }

        return $ordered;
    }

}

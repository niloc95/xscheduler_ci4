# Calendar Engine: Service API Reference

Quick reference for all Calendar Engine services.

---

## CalendarRangeService

Pure date/grid structure generation. No DB queries, no business logic.

### generateDaySlots(date: string, startTime: string, endTime: string, resolution: int): array

Generate time slots for a single day.

**Parameters**:
- `date` — 'Y-m-d'
- `startTime` — 'HH:MM'
- `endTime` — 'HH:MM'
- `resolution` — Minutes per slot (e.g., 30)

**Returns**:
```php
[
    [
        'time'    => 'HH:MM',    // e.g. '08:00'
        'minutes' => 0,          // offset from day start in minutes
        'label'   => '8:00 AM',  // formatted label
        'isHour'  => true,       // on the hour mark
        'isHalf'  => false,      // on a half-hour mark
        'topPx'   => 0,          // 2px per minute offset from grid top
    ],
    [
        'time'    => '08:30',
        'minutes' => 30,
        'label'   => '8:30 AM',
        'isHour'  => false,
        'isHalf'  => true,
        'topPx'   => 60,
    ],
    // ...
]
```

`appointments` is **not** part of the CalendarRangeService output. It is injected by `SlotInjectionTrait::injectIntoSlots()` after events are positioned.

---

### generateWeekRange(date: string): array

Get 7-day week starting from the configured first day of week.

**Parameters**:
- `date` — Any date in the desired week

**Returns**: Array of 7 day objects:
```php
[
    [
        'date'        => 'Y-m-d',
        'dayNumber'   => 1,          // Day within month
        'weekday'     => 1,          // 0=Sun, 6=Sat
        'weekdayName' => 'Monday',
        'monthName'   => 'March',
        'fullDate'    => 'March 10, 2026',
        'isToday'     => true,
        'isPast'      => false,
        'isFuture'    => false,
    ],
    // ... 6 more days
]
```

`startDate`, `endDate`, and `weekLabel` are added by `WeekViewService::build()`, not by this method.

---

### generateMonthGrid(year: int, month: int): array

Generate 42-cell month grid (6 weeks × 7 days).

**Parameters**:
- `year` — e.g., 2026
- `month` — 1-12

**Returns**:
```php
[
    'year' => 2026,
    'month' => 3,
    'monthName' => 'March',
    'monthLabel' => 'March 2026',
    'startDate' => 'Y-m-d',
    'endDate' => 'Y-m-d',
    'weeks' => [
        [
            [
                'date' => 'Y-m-d',
                'dayNumber' => 1,        // Day within month
                'weekday' => 0,
                'weekdayName' => 'Sunday',
                'week' => 1,             // Week number in grid
                'isCurrentMonth' => false, // Is in target month?
                'isToday' => false,
                'isPast' => false,
                'isFuture' => true,
                'hasMore' => false,
                'moreCount' => 0,
                'appointmentCount' => 0,
                'appointments' => []
            ],
            // ... 6 more cells per week
        ],
        // ... 5 more weeks
    ]
]
```

---

### normalizeDayOfWeek(date: string): array

Convert date to normalized day-of-week format.

**Parameters**:
- `date` — 'Y-m-d'

**Returns**:
```php
[
    'int' => 1,        // 0=Sun, 1=Mon, ..., 6=Sat
    'string' => 'Monday'
]
```

---

## TimeGridService

### generateDayGrid(date: string): array

Generate time grid for day using **business hours**.

**Parameters**:
- `date` — 'Y-m-d'

**Returns**: See `generateDaySlots` above

**Reads from Settings**:
- `business.work_start` — global business open time (default: '08:00')
- `business.work_end` — global business close time (default: '17:00')
- `booking.time_resolution` — slot increment in minutes (default: 30)

`business.work_start` / `business.work_end` are the authoritative global bounds. `booking.day_start` / `booking.day_end` control the calendar grid display window and are a separate concern. See `Agent_Context_v2.md §8.9.4`.

---

### generateDayGridWithProviderHours(date: string, providerHours: array): array

Generate time grid for day using **provider-specific hours** constrained by business hours.

**Parameters**:
- `date` — 'Y-m-d'
- `providerHours` — Array with keys:
  - `startTime` — 'HH:MM' (e.g., '09:00')
  - `endTime` — 'HH:MM' (e.g., '14:00')
  - `isActive` — bool (false = provider not working)
  - `breakStart` — (optional) 'HH:MM'
  - `breakEnd` — (optional) 'HH:MM'

**Returns**: See `generateDaySlots` above

**Logic**:
```
IF providerHours.isActive === false
  THEN use business hours
ELSE
    use the overlap of providerHours.startTime/endTime and business hours

IF no overlap exists
    THEN treat the day as unavailable
```

---

### getDayStart(): string

Get default day start time.

**Returns**: 'HH:MM' (e.g., '08:00')

---

### getDayEnd(): string

Get default day end time.

**Returns**: 'HH:MM' (e.g., '18:00')

---

### getResolution(): int

Get default time slot resolution.

**Returns**: Minutes (e.g., 30)

---

## EventLayoutService

### resolveLayout(events: array): array

Detect overlapping events and assign column positions.

**Parameters**:
- `events` — Array of appointments with:
  - `id` — int
  - `start_at` or `start_datetime` or `startDateTime` — ISO string or DateTime
  - `end_at` or `end_datetime` or `endDateTime` — ISO string or DateTime
  - (any other fields preserved)

**Returns**: Same events with added fields:
```php
[
    [
        'id' => 12,
        'start_at' => '2026-03-10T09:00:00Z',
        'end_at' => '2026-03-10T10:00:00Z',
        '_column' => 0,              // Column index (0-based)
        '_columns_total' => 2,       // Total columns in this cluster
        '_column_width_pct' => 50,   // Percentage width
        '_column_left_pct' => 0,     // Percentage left offset
        // ... original fields
    ],
    // ... more events
]
```

**Algorithm**: Sweep-line
1. Sort events by start time, then end time
2. Track active intervals
3. Assign each event to lowest available column
4. Calculate cluster width

**Time Complexity**: O(n log n) for sorting + O(n²) for assignment
**Space Complexity**: O(n)

---

## SlotInjectionTrait

Used by: DayViewService, WeekViewService, TodayViewService

### injectIntoSlots(slots: array, events: array, grid: array, resolution: int): array

Place appointments into time slots with positioning.

**Parameters**:
- `slots` — Array from TimeGridService
- `events` — Formatted appointments (with `_column`, `_columns_total`)
- `grid` — Grid metadata with `dayStart`, `dayEnd`, `pixelsPerMinute`
- `resolution` — Slot duration in minutes

**Returns**: Slots (from CalendarRangeService) with `appointments` array injected. Each event in `appointments` receives additional position metadata:

```php
// Slot structure (from CalendarRangeService, with appointments added)
[
    'time'    => '08:00',
    'minutes' => 0,
    'label'   => '8:00 AM',
    'isHour'  => true,
    'isHalf'  => false,
    'topPx'   => 0,
    'appointments' => [
        [
            'id'       => 12,
            'start_at' => '2026-03-10T08:00:00Z',
            'end_at'   => '2026-03-10T09:00:00Z',
            // From EventLayoutService:
            '_column'          => 0,
            '_columns_total'   => 2,
            '_column_width_pct'=> 50.0,
            '_column_left_pct' => 0.0,
            // Added by SlotInjectionTrait:
            '_topPx'    => 0,      // Y position in pixels
            '_heightPx' => 120,    // Height in pixels (min 15)
            '_startMin' => 0,      // Start minute offset from grid top
            '_endMin'   => 60,     // End minute offset
            '_colIndex' => 0,      // Mirrors _column
            '_colCount' => 2,      // Mirrors _columns_total
            '_widthPct' => 50.0,   // Mirrors _column_width_pct
            '_leftPct'  => 0.0,    // Mirrors _column_left_pct
            // ... original event fields
        ]
    ]
],
```

---

## View Services

### TodayViewService

New service for "Today" view.

#### build(date: string, businessId?: int, providerIds?: array): array

Build today's view model.

**Parameters**:
- `date` — 'Y-m-d' (typically today, but can be any date)
- `businessId` — Filter by business (optional)
- `providerIds` — Filter by specific providers (optional)

**Returns**:
```php
[
    'date' => '2026-03-10',
    'dayName' => 'Tuesday',
    'dayLabel' => 'Tuesday, March 10, 2026',
    'weekdayName' => 'tuesday',
    'weekday' => 2,
    'isToday' => true,
    'isPast' => false,
    'businessHours' => [
        'startTime' => '08:00',
        'endTime' => '17:00'
    ],
    'grid' => [...],  // Reference grid
    'providerColumns' => [
        [
            'provider' => [
                'id' => 1,
                'name' => 'Dr. Smith',
                'color' => '#ff6b6b'
            ],
            'workingHours' => [
                'startTime' => '09:00',
                'endTime' => '17:00',
                'breakStart' => '12:00',
                'breakEnd' => '13:00',
                'source' => 'provider_schedule',
                'isActive' => true
            ],
            'grid' => [
                'dayStart' => '09:00',
                'dayEnd' => '17:00',
                'slots' => [...]
            ],
            'appointments' => [...]
        ]
    ],
    'appointments' => [...all flat],
    'totalAppointments' => 5
]
```

---

### DayViewService

#### build(date: string, filters?: array, appointments?: array): array

Build day view model for any date.

**Parameters**:
- `date` — 'Y-m-d'
- `filters` — Optional filters:
  - `provider_id` → int
  - `service_id` → int
  - `location_id` → int
  - `status` → string
  - `user_role` → 'admin' | 'provider' | 'staff'
  - `scope_to_user_id` → int
- `appointments` — Pre-formatted appointments (optional, for testing)

**Returns**: Same as TodayViewService

---

### WeekViewService

#### build(date: string, filters?: array): array

Build week view model.

**Parameters**:
- `date` — Any date in desired week
- `filters` — Same as DayViewService

**Returns**:
```php
[
    'startDate' => '2026-03-09',
    'endDate' => '2026-03-15',
    'weekLabel' => 'Mar 9 – 15, 2026',
    'businessHours' => [...],
    'slotDuration' => 30,
    'days' => [
        [
            'date' => '2026-03-09',
            'dayNumber' => 9,
            'monthName' => 'March',
            'fullDate' => 'Monday, March 9, 2026',
            'weekday' => 1,
            'weekdayName' => 'Monday',
            'isToday' => true,
            'isPast' => false,
            'dayGrid' => [...],
            'providerColumns' => [...],
            'appointments' => [...],
            'appointmentCount' => 3
        ],
        // ... 6 more days
    ],
    'appointments' => [...all flat],
    'totalAppointments' => 21
]
```

---

### MonthViewService

#### build(year: int, month: int, filters?: array): array

Build month view model.

**Parameters**:
- `year` — e.g., 2026
- `month` — 1-12
- `filters` — Optional (for fetching appointments)

**Returns**:
```php
[
    'year' => 2026,
    'month' => 3,
    'monthName' => 'March',
    'monthLabel' => 'March 2026',
    'startDate' => 'Y-m-d',
    'endDate' => 'Y-m-d',
    'weeks' => [
        [
            [
                'date' => 'Y-m-d',
                'dayNumber' => 10,
                'weekday' => 1,
                'weekdayName' => 'Monday',
                'week' => 2,
                'isCurrentMonth' => true,
                'isToday' => true,
                'isPast' => false,
                'isFuture' => false,
                'appointments' => [
                    {
                        'id' => 12,
                        'title' => 'Appointment with Dr. Smith',
                        'start' => '2026-03-10T09:00:00Z',
                        'end' => '2026-03-10T10:00:00Z',
                        // ... other fields
                    }
                ],
                'appointmentCount' => 1,
                'hasMore' => false,
                'moreCount' => 0
            ],
            // ... 6 more cells
        ],
        // ... 5 more weeks
    ]
]
```

---

## Priority: Time Boundaries

When generating provider working hours (`ProviderWorkingHoursTrait::getProviderWorkingHours()`), the system checks in this order:

1. **ProviderScheduleModel** (provider-specific)
   - `xs_provider_schedules WHERE provider_id = ? AND day_of_week = ? AND is_active = 1`
   - If found and active, use provider's start/end/break times

2. **BusinessHourModel** (provider-scoped business hours)
   - `xs_business_hours WHERE provider_id = ? AND weekday = ?`
   - **Every row in `xs_business_hours` has a `provider_id`** — there are no global rows in this table. An unfiltered query returns an arbitrary provider's row, not a system-wide default. See `Agent_Context_v2.md §8.7.1`.

3. **SettingModel** (global outer bounds)
   - `business.work_start` → global open time
   - `business.work_end` → global close time

First match wins per provider. Global business bounds from `xs_settings` are applied by `AvailabilityService::constrainToBusinessHours()` as an outer limit on any provider's window.

**Known active debt:** `ProviderWorkingHoursTrait::getBusinessHours()` falls back to `$settings->getValue('booking.day_start', '08:00')`. `SettingModel` has no `getValue()` method — this path throws `BadMethodCallException` for the placeholder provider (id=0). Fix: replace with `SettingModel::getByKeys(['booking.day_start','booking.day_end'])`. See `Agent_Context_v2.md §8.7.4`.

---

## Error Handling

### TimeGridService
- Invalid date → DateTimeImmutable exception
- Invalid time format → TimeGridService::generateDaySlots() may return empty slots
- Missing settings → Uses hardcoded defaults ('08:00', '17:00', 30)

### EventLayoutService
- Empty events array → Returns empty array (no error)
- Missing start_at/end_at → Uses numeric 0 (epoch), may produce wrong results
- Mixed datetime formats → Handles intelligently (string, DateTime, numeric)

### SlotInjectionTrait
- Appointments outside time grid → Ignored (not injected)
- Negative or invalid pixel values → Rendered as-is (frontend responsibility)

---

## Performance Notes

### EventLayoutService
- Suitable for up to 1000 appointments per call
- Beyond that, consider client-side rendering

### TimeGridService
- Queries DB once per view (fast)
- Caches settings in memory

### SlotInjectionTrait
- Linear scan of slots and appointments O(n*m)
- For large grids, consider indexing

---

## Testing Helpers

### Test EventLayoutService
```php
$service = new EventLayoutService();

// 2 overlapping events
$events = [
    ['id' => 1, 'start_at' => '2026-03-10T09:00:00Z', 'end_at' => '2026-03-10T10:00:00Z'],
    ['id' => 2, 'start_at' => '2026-03-10T09:30:00Z', 'end_at' => '2026-03-10T10:30:00Z'],
];
$positioned = $service->resolveLayout($events);

assert($positioned[0]['_column'] === 0);
assert($positioned[0]['_columns_total'] === 2);
assert($positioned[1]['_column'] === 1);
assert($positioned[1]['_columns_total'] === 2);
```

### Test TimeGridService
```php
$service = new TimeGridService();
$grid = $service->generateDayGrid('2026-03-10');

// Check slot count (30-min resolution: 8am-5pm = 18 hours = 36 slots)
assert(count($grid['slots']) === 18);

// Check first slot
assert($grid['slots'][0]['time'] === '08:00');
assert($grid['slots'][0]['_topPx'] === 0);
```

---

## Deprecations

### AppointmentConflictService
- **Status**: Transitional (still in active use via `AvailabilityService`)
- **Reason**: Wrapper alias around `ConflictService` kept for compatibility while migration completes
- **Migration Target**:
    - Calendar overlaps → `EventLayoutService`
    - Booking validation → `ConflictService` (direct injection once tests are migrated)



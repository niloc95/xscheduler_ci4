# Calendar Engine: Service API Reference

Quick reference for all Calendar Engine services.

---

## CalendarRangeService

### getRange(view: string, date: DateTime): array

Calculates date range for a calendar view.

**Parameters**:
- `view` — 'today' | 'day' | 'week' | 'month'
- `date` — Current date context

**Returns**:
```php
[
    'startDate' => 'Y-m-d',  // First date in range
    'endDate' => 'Y-m-d',    // Last date in range
    'days' => [              // Only for day/today views
        ['date' => 'Y-m-d', 'dayNumber' => 1, ...]
    ],
    'weeks' => [             // Only for month view
        [                    // 6 weeks × 7 days
            ['date' => 'Y-m-d', 'isCurrentMonth' => true, ...]
        ]
    ]
]
```

---

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
    'dayStart' => '08:00',
    'dayEnd' => '17:00',
    'slots' => [
        [
            'time' => '08:00',
            'index' => 0,
            'hour' => 8,
            'minute' => 0,
            'isHourMark' => true,
            '_topPx' => 0,
            '_heightPx' => 60,
            'appointments' => []  // Filled by SlotInjectionTrait
        ],
        [
            'time' => '08:30',
            'index' => 1,
            'hour' => 8,
            'minute' => 30,
            'isHourMark' => false,
            '_topPx' => 30,
            '_heightPx' => 60,
            'appointments' => []
        ]
        // ... more slots
    ]
]
```

---

### generateWeekRange(date: string): array

Get 7-day week starting from first day of week.

**Parameters**:
- `date` — Any date in the desired week

**Returns**:
```php
[
    'startDate' => 'Y-m-d',  // Monday (or configured first day)
    'endDate' => 'Y-m-d',    // Sunday
    'days' => [
        [
            'date' => 'Y-m-d',
            'dayNumber' => 1,        // Day within month
            'weekday' => 1,          // 0=Sun, 6=Sat
            'weekdayName' => 'Monday',
            'monthName' => 'March',
            'fullDate' => 'March 10, 2026',
            'isToday' => true,
            'isPast' => false,
            'isFuture' => false
        ],
        // ... 6 more days
    ]
]
```

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
- `calendar.day_start` (default: '08:00')
- `calendar.day_end` (default: '18:00')
- `booking.time_resolution` (default: 30)

---

### generateDayGridWithProviderHours(date: string, providerHours: array): array

Generate time grid for day using **provider-specific hours** with business hours fallback.

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
  use providerHours.startTime and providerHours.endTime
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

**Returns**: Slots with appointments injected:
```php
[
    [
        'time' => '08:00',
        'index' => 0,
        'hour' => 8,
        'minute' => 0,
        'isHourMark' => true,
        '_topPx' => 0,
        '_heightPx' => 60,
        'appointments' => [
            [
                'id' => 12,
                'start_at' => '2026-03-10T08:00:00Z',
                'end_at' => '2026-03-10T09:00:00Z',
                '_column' => 0,
                '_columns_total' => 2,
                '_column_width_pct' => 50,
                '_column_left_pct' => 0,
                '_topPx' => 0,        // Absolute position
                '_heightPx' => 60,    // Absolute height
                // ... other fields
            ]
        ]
    ],
    // ... more slots
]
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
  - `provider_ids` → int[]
  - `service_ids` → int[]
  - `location_ids` → int[]
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

When generating time grids, the system checks in this order:

1. **ProviderScheduleModel** (highest)
   - SELECT FROM xs_provider_schedules WHERE provider_id=? AND day_of_week=? AND is_active=1
   
2. **BusinessHourModel** (middle)
   - SELECT FROM xs_business_hours WHERE day_of_week=?
   
3. **SettingModel** (defaults)
   - `booking.day_start` → '08:00'
   - `booking.day_end` → '17:00'

First match wins. If provider schedule exists and is_active=true, it's used. Otherwise fall back to business hours.

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

---

## Changelog

### v1.0.0
- ✅ EventLayoutService created
- ✅ TodayViewService created
- ✅ TimeGridService enhanced with `generateDayGridWithProviderHours()`
- ✅ DayViewService refactored to use EventLayoutService
- ✅ WeekViewService updated for EventLayoutService
- ✅ MonthViewService unchanged (compatible)


# Unified Calendar Engine Architecture

## Overview

The calendar system now provides a **Single Calendar Engine** used by all four view types:
- **Today View** — Today's appointments for providers
- **Day View** — Any single day's appointments
- **Week View** — 7-day week with per-day grids
- **Month View** — 42-cell calendar grid

This document explains the architecture, how it eliminates duplication, and how to extend it.

---

## Problem Solved

### Before
- **Timeline Hours Mismatch**: Day view rendered 08:00-18:00 while provider schedule was 08:00-17:00
- **Overlapping Logic Duplication**: Each view had its own appointment overlap detection
- **Time Grid Inconsistency**: Month view didn't use TimeGridService at all
- **Maintenance Burden**: Changes to time calculations required updates in 4+ places

### After
- **Single Source of Truth**: All views use shared services for time grids and layout
- **Provider-Driven Timelines**: Timeline hours come from ProviderScheduleModel with business hours fallback
- **Unified Overlap Resolution**: EventLayoutService solves overlaps consistently everywhere
- **Easy Maintenance**: Changes to layout logic apply to all views automatically

---

## Calendar Engine Architecture

```
┌─────────────────────────────────────────────────────────────┐
│           VIEW LAYER (Rendering)                            │
├─────────────────────────────────────────────────────────────┤
│   TodayViewService   DayViewService   WeekViewService       │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│           SHARED CALENDAR ENGINE                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  CalendarRangeService  ──┐                                  │
│  - getRange()           │  Date/grid structure              │
│  - normalizeDayOfWeek() │  generation                       │
│                         │                                    │
│  TimeGridService  ──────┐                                  │
│  - generateDayGrid()    │  Time slot generation            │
│  - generateDayGridWith  │  with provider schedule          │
│    ProviderHours()      │  override capability             │
│                         │                                    │
│  EventLayoutService  ───┐                                  │
│  - resolveLayout()      │  Overlap detection &             │
│                         │  column positioning              │
│                         │                                    │
│  SlotInjectionTrait  ───┐                                  │
│  - injectIntoSlots()    │  Appointment placement           │
│                         │  into time grid                   │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│           DATA MODELS & QUERIES                             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  AppointmentQueryService     — Fetch with filters           │
│  AppointmentFormatterService — Normalize format             │
│  ProviderScheduleModel       — Provider hours per day       │
│  SettingModel                — Business hour defaults        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Core Services

### 1. CalendarRangeService
**Responsibility**: Calculate date ranges for each view type.

**Methods**:
```php
public function getRange(string $view, DateTime $date): array
  // Returns:
  // {
  //   'startDate' => 'Y-m-d',
  //   'endDate' => 'Y-m-d',
  //   'days' => [...],
  //   'weeks' => [...]  // Month view only
  // }

public function generateDaySlots(string $date, string $startTime, string $endTime, int $resolution): array
  // Returns array of time slots for a single day

public function generateWeekRange(string $date): array
  // Returns 7 DateTimeImmutable objects starting from first day of week

public function generateMonthGrid(int $year, int $month): array
  // Returns 42-cell grid (6 weeks × 7 days)
```

**Used By**: All view services

---

### 2. TimeGridService
**Responsibility**: Generate time slot grids with configurable provider hours.

**Key Feature**: Provider schedule override
- Query ProviderScheduleModel first
- Constrain provider hours to business hours before generating availability
- Fall back to business hours if no provider schedule exists
- Support inactive days (show "Not Working")

**Methods**:
```php
public function generateDayGrid(string $date): array
  // Uses business hours (default)
  // Returns grid with slots from day_start to day_end

public function generateDayGridWithProviderHours(string $date, array $providerHours): array
  // Uses provider-specific hours
  // Priority:
  // 1. providerHours['startTime'] / ['endTime'] intersected with business hours
  // 2. Business hours (fallback when no provider schedule exists)
```

When provider hours and business hours do not overlap, that date should be treated as unavailable for slot generation.

**Example Output**:
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
            'appointments' => []  // Injected later
        ],
        // ... more slots
    ]
]
```

---

### 3. EventLayoutService
**Responsibility**: Solve overlapping appointments and assign column positions.

**Algorithm**: Sweep-line algorithm
1. Sort events by start time
2. Track active intervals at each time point
3. Assign each event to the lowest available column
4. Calculate column width for side-by-side rendering

**Example**:
```php
Input:
  Event A: 09:00-10:00
  Event B: 09:00-10:00  (overlaps A)
  Event C: 10:00-11:00  (doesn't overlap)

Output:
  Event A: _column = 0, _columns_total = 2, _column_width_pct = 50
  Event B: _column = 1, _columns_total = 2, _column_width_pct = 50
  Event C: _column = 0, _columns_total = 1, _column_width_pct = 100
```

**Method**:
```php
public function resolveLayout(array $events): array
  // Returns events with positioning metadata:
  // - _column  : column index (0-based)
  // - _columns_total : total columns in cluster
  // - _column_width_pct : width percentage for rendering
  // - _column_left_pct : left offset percentage
```

---

### 4. SlotInjectionTrait
**Responsibility**: Place appointments into time slots.

**Used By**: All view services that render time grids (Today, Day, Week)

**Method**:
```php
protected function injectIntoSlots(
    array $slots,
    array $events,
    array $grid,
    int $resolution
): array
  // Returns slots with appointments positioned:
  // - slot['appointments'] contains positioned events
  // - Each event has _topPx and _heightPx for absolute positioning
```

---

## View Services

### TodayViewService (NEW)
**Purpose**: Display today's appointments for providers.

**Uses Shared Engine**:
1. CalendarRangeService → Get today's date
2. TimeGridService → Generate provider-specific grid
3. EventLayoutService → Resolve overlaps
4. SlotInjectionTrait → Place appointments in slots

**Example**:
```php
$service = new TodayViewService();
$model = $service->build('2026-03-09', businessId: 1);
// Returns: {
//   date, dayName, dayLabel, weekday,
//   providerColumns: [
//     {
//       provider: { id, name, color },
//       workingHours: { startTime, endTime, source },
//       grid: { dayStart, dayEnd, slots: [...] }
//     }
//   ]
// }
```

---

### DayViewService (Refactored)
**Purpose**: Display any single day's appointments.

**Changes**:
- Now uses `EventLayoutService::resolveLayout()` for overlaps
- Now uses `TimeGridService::generateDayGridWithProviderHours()` for provider-specific timelines
- Maintains backward compatibility

**Example**:
```php
$service = new DayViewService();
$model = $service->build('2026-03-15', ['provider_id' => 5]);
// Automatically uses provider #5's schedule from xs_provider_schedules
```

---

### WeekViewService (Updated)
**Purpose**: Display 7-day week view.

**Architecture**:
- Uses CalendarRangeService to get week bounds
- Delegates to DayViewService for each day
- Automatically inherits all DayViewService improvements

**No Breaking Changes** — Output format unchanged

---

### MonthViewService (Unchanged)
**Purpose**: Display 42-cell month grid.

**Note**: MonthViewService does NOT use TimeGridService because:
- Month cells show appointment counts, not time grids
- Per-cell time resolution would be excessive
- Uses CalendarRangeService for grid structure only

---

## Priority: Time Boundaries

**The system resolves time boundaries in this order**:

```
1. ProviderScheduleModel (highest priority)
   ├─ SELECT * FROM xs_provider_schedules
   ├─ WHERE provider_id = ? AND day_of_week = ?
   └─ Returns: {startTime, endTime, breakStart, breakEnd, isActive}

2. BusinessHourModel (fallback)
   ├─ SELECT * FROM xs_business_hours
   ├─ WHERE day_of_week = ?
   └─ Returns: {startTime, endTime}

3. SettingModel (defaults)
   ├─ Gets: booking.day_start, booking.day_end
   └─ Returns: '08:00', '17:00'
```

**Example**:
```php
// If provider schedule says 09:00-14:00 for Monday:
// → Timeline renders 09:00-14:00 ✓

// If provider schedule doesn't exist for Monday:
// → Timeline renders business hours (e.g., 08:00-17:00) ✓

// If provider schedule says isActive=false:
// → Provider column shows "Not Working" overlay ✓
```

---

## Elimination of Duplication

### Before
```
DayViewService       → Custom overlap detection
WeekViewService      → Custom overlap detection
MonthViewService     → No overlap detection (different problem)
TodayViewService     → (didn't exist)
```

### After
```
DayViewService  ──┐
WeekViewService ──┼──→ EventLayoutService (shared)
TodayViewService ─┘

DayViewService       → Uses TimeGridService
WeekViewService      → Delegates to DayViewService
TodayViewService     → Uses TimeGridService
MonthViewService     → Uses CalendarRangeService only
```

**Result**: Single implementation for overlaps, imported everywhere.

---

## Service Dependencies Graph

```
Consumers (Views):
  TodayViewService
  DayViewService
  WeekViewService
  MonthViewService
       ↓     ↓
    Shared Engine Layer:
    CalendarRangeService
    TimeGridService
    EventLayoutService
    SlotInjectionTrait
       ↓     ↓     ↓
    Data Layer:
    AppointmentQueryService
    AppointmentFormatterService
    ProviderScheduleModel
    SettingModel
```

---

## Implementation Example: Multi-Provider Day View

**Scenario**: Day view with 3 providers, 2 have overlapping appointments.

**Flow**:

```php
// 1. Controller
$dayViewService = new DayViewService();
$model = $dayViewService->build('2026-03-10', ['provider_ids' => [1, 2, 3]]);

// 2. DayViewService::build()
//    a. Get appointments for all 3 providers
$appointments = $this->appointmentQuery->getForRange('2026-03-10', '2026-03-10');
//    b. Format appointments
$formatted = $this->appointmentFormatter->formatManyForCalendar($appointments);

// 3. For each provider:
foreach ($providers as $provider) {
    // a. Get provider's working hours
    $workingHours = $this->getProviderWorkingHours($provider['id'], $dayOfWeek);
    // Result: { startTime: '08:00', endTime: '17:00', source: 'provider_schedule', isActive: true }
    
    // b. Generate provider-specific time grid
    $grid = $this->timeGrid->generateDayGridWithProviderHours(
        '2026-03-10',
        $workingHours
    );
    // Result: slots from 08:00 to 17:00
    
    // c. Filter appointments for this provider
    $providerAppointments = array_filter($formatted, 
        fn($apt) => $apt['provider_id'] === $provider['id']
    );
    
    // d. SHARED ENGINE: Resolve overlaps
    $positioned = $this->eventLayout->resolveLayout($providerAppointments);
    // Result: Each event has _column, _columns_total, _column_width_pct
    
    // e. Inject into time slots
    $gridWithAppointments = $this->injectIntoSlots(
        $grid['slots'],
        $positioned,
        $grid,
        30 // resolution
    );
    // Result: Each slot has appointments array with _topPx and _heightPx
    
    // f. Build provider column
    $providerColumns[] = [
        'provider' => ['id' => $provider['id'], 'name' => $provider['name']],
        'workingHours' => $workingHours,
        'grid' => ['dayStart' => '08:00', 'dayEnd' => '17:00', 'slots' => $gridWithAppointments]
    ];
}

// 4. Return complete model
return [
    'date' => '2026-03-10',
    'providerColumns' => $providerColumns,
    'appointments' => $formatted,
    // ... other metadata
];
```

**Frontend Rendering** (simplified):
```javascript
// For each provider column:
providerColumns.forEach(column => {
    // Render timeline based on column.grid.dayStart to column.grid.dayEnd
    // For each slot with appointments:
    column.grid.slots.forEach(slot => {
        if (slot.appointments.length > 0) {
            slot.appointments.forEach(apt => {
                // Render appointment at:
                // - height: apt._heightPx
                // - top: apt._topPx
                // - left: (apt._column_left_pct)%
                // - width: (apt._column_width_pct)%
            });
        }
    });
});
```

---

## Adding a New View

To add a new calendar view (e.g., Agenda View):

### Step 1: Understand Your Requirements
- What date range? (week, month, custom?)
- Do you need time slots? (day/week) or counts? (month)
- Do you need overlapping layout?

### Step 2: Inherit from Shared Engine
```php
class AgendaViewService
{
    private CalendarRangeService $range;
    private TimeGridService $timeGrid;
    private EventLayoutService $eventLayout;
    private AppointmentQueryService $query;
    private AppointmentFormatterService $formatter;
    
    // Constructor injection
    // Just instantiate the shared services
}
```

### Step 3: Use the Services
```php
public function build(string $dateFrom, string $dateTo)
{
    // Get date range
    $range = $this->range->getRange('custom', new DateTime($dateFrom));
    
    // Get appointments
    $appointments = $this->query->getForRange($dateFrom, $dateTo);
    $formatted = $this->formatter->formatManyForCalendar($appointments);
    
    // Resolve overlaps if needed
    $positioned = $this->eventLayout->resolveLayout($formatted);
    
    // Return your custom format
    return ['events' => $positioned];
}
```

---

## Testing Strategy

### Unit Tests

**EventLayoutService**:
- Test overlap detection (2 overlapping, 3 overlapping, non-overlapping)
- Verify column assignment correctness
- Verify column width percentages

**TimeGridService**:
- Test default business hours
- Test provider schedule override
- Test inactive day handling

**TodayViewService**:
- Test single provider grid generation
- Test multi-provider layout
- Test working hours from ProviderScheduleModel

**DayViewService**:
- Test appointment formatting
- Test provider-specific time grids
- Test backward compatibility (old clients)

### Integration Tests

- Day view with 2 overlapping providers
- Week view rendering correctly across days
- Month view returning appointment counts
- Provider schedule override working

### Browser Tests

- Today view: Timeline hours match provider schedule
- Day view: Overlapping appointments render side-by-side
- Week view: Each day shows correct provider columns
- Month view: Appointment counts correct

---

## Performance Considerations

### Query Optimization
- `AppointmentQueryService::getForRange()` uses indexed queries
- Single query per date range (not per provider)
- Results grouped efficiently in memory

### Memory Usage
- EventLayoutService uses O(n log n) for sorting
- Sweep-line algorithm O(n) time, O(n) space
- Total service memory: linear with appointment count

### Recommendations
- Cache provider schedules (loaded frequently)
- Batch month appointments in 2-3 queries (not per-day)
- Consider Redis for frequently accessed date ranges

---

## Service File Structure

```
app/Services/Calendar/
├── CalendarRangeService.php       ← Date/grid generation
├── TimeGridService.php            ← Time slot generation
├── EventLayoutService.php         ← Overlap resolution (NEW)
├── SlotInjectionTrait.php         ← Appointment placement
├── TodayViewService.php           ← Today view (NEW)
├── DayViewService.php             ← Day view (refactored)
├── WeekViewService.php            ← Week view (updated)
└── MonthViewService.php           ← Month view (unchanged)

app/Services/
├── CalendarConfigService.php      ← View preferences (used by TimeGridService)
└── ... other services

app/Services/Appointment/
├── AppointmentQueryService.php    ← Data retrieval
├── AppointmentFormatterService.php ← Normalization
└── AppointmentConflictService.php ← Transitional alias (migrate to direct ConflictService usage)

app/Services/
└── ConflictService.php            ← Used for booking validation
```

---

## Migration Guide: From Old to New

### For Controllers
No change required. Services work identically.

### For Views/Templates
No change required. Output formats preserved.

### For API Clients
- `DayViewService` output unchanged
- `WeekViewService` output unchanged
- `MonthViewService` output unchanged
- New: `TodayViewService` available for new endpoints

### For Custom Calendar Views
Use `EventLayoutService::resolveLayout()` instead of custom overlap detection.

```php
// OLD:
$overlaps = $this->detectOverlaps($appointments);
$positioned = $this->assignColumns($overlaps);

// NEW:
$positioned = (new EventLayoutService())->resolveLayout($appointments);
```

---

## FAQ

**Q: Can I use a different time resolution (15 min vs 30 min)?**
A: Yes. TimeGridService reads from settings:
```php
'booking.time_resolution' => 15 // minutes
```

**Q: What if a provider has no schedule record?**
A: Falls back to business hours, then CalendarConfigService defaults.

**Q: Can I exclude certain providers from a view?**
A: Yes, pass `['provider_ids' => [1, 3, 5]]` to filter.

**Q: What happens if an appointment spans multiple time zones?**
A: Convert to business timezone before reaching these services. TimezoneService handles conversion.

**Q: Can EventLayoutService handle all-day events?**
A: Not currently. All-day would require separate rendering path.

---

## Summary

The Unified Calendar Engine provides:
- ✅ Single source of truth for date/time calculations
- ✅ Consistent overlap resolution across all views
- ✅ Provider-specific working hours with fallback chain
- ✅ Clean service abstraction
- ✅ Easy to test and extend
- ✅ Zero duplication

All four calendar views now share the same underlying architecture, making the system more maintainable and consistent.

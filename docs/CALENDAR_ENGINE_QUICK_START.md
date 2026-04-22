# Calendar Engine: Quick Start Guide for Controllers

Fast reference for using the Unified Calendar Engine in your controllers and services.

---

## Today View

```php
<?php

namespace App\Controllers\Api;

use App\Services\Calendar\TodayViewService;

class TodayController extends BaseApiController
{
    private TodayViewService $todayView;

    public function __construct()
    {
        $this->todayView = new TodayViewService();
    }

    public function index()
    {
        $date = date('Y-m-d');  // Today
        $model = $this->todayView->build($date);

        return $this->respond($model);
    }

    public function byDate()
    {
        $date = $this->request->getVar('date');  // 'Y-m-d'
        $model = $this->todayView->build($date);

        return $this->respond($model);
    }

    public function byProvider()
    {
        $date = $this->request->getVar('date');
        $providerIds = $this->request->getVar('provider_ids');  // [1, 2, 3]

        $model = $this->todayView->build(
            $date,
            businessId: null,
            providerIds: $providerIds
        );

        return $this->respond($model);
    }
}
```

---

## Day View

```php
<?php

namespace App\Controllers\Api;

use App\Services\Calendar\DayViewService;

class DayController extends BaseApiController
{
    private DayViewService $dayView;

    public function __construct()
    {
        $this->dayView = new DayViewService();
    }

    public function index()
    {
        $date = $this->request->getVar('date');  // 'Y-m-d'

        $model = $this->dayView->build($date, [
            'provider_id' => $this->request->getVar('provider_id'),
        ]);

        return $this->respond($model);
    }

    public function byWeekday()
    {
        $weekday = $this->request->getVar('weekday');  // 'monday', 'tuesday', etc.
        $date = $this->getNextOccurrence($weekday);

        $model = $this->dayView->build($date);

        return $this->respond($model);
    }

    private function getNextOccurrence(string $weekday): string
    {
        // Calculate next occurrence of weekday
        $now = new DateTime();
        $target = new DateTime();
        $dayOfWeek = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ][$weekday] ?? 1;

        while ($target->format('w') != $dayOfWeek) {
            $target->modify('+1 day');
        }

        return $target->format('Y-m-d');
    }
}
```

---

## Week View

```php
<?php

namespace App\Controllers\Api;

use App\Services\Calendar\WeekViewService;

class WeekController extends BaseApiController
{
    private WeekViewService $weekView;

    public function __construct()
    {
        $this->weekView = new WeekViewService();
    }

    public function index()
    {
        $date = $this->request->getVar('date');  // Any date in the week

        $model = $this->weekView->build($date, [
            'provider_id' => $this->request->getVar('provider_id'),
        ]);

        return $this->respond($model);
    }

    public function current()
    {
        // Current week
        $model = $this->weekView->build(date('Y-m-d'));

        return $this->respond($model);
    }

    public function next()
    {
        // Next week
        $date = (new DateTime('+1 week'))->format('Y-m-d');
        $model = $this->weekView->build($date);

        return $this->respond($model);
    }

    public function previous()
    {
        // Previous week
        $date = (new DateTime('-1 week'))->format('Y-m-d');
        $model = $this->weekView->build($date);

        return $this->respond($model);
    }
}
```

---

## Month View

```php
<?php

namespace App\Controllers\Api;

use App\Services\Calendar\MonthViewService;

class MonthController extends BaseApiController
{
    private MonthViewService $monthView;

    public function __construct()
    {
        $this->monthView = new MonthViewService();
    }

    public function index()
    {
        $year = (int) $this->request->getVar('year');
        $month = (int) $this->request->getVar('month');

        $model = $this->monthView->build($year, $month);

        return $this->respond($model);
    }

    public function current()
    {
        $now = new DateTime();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('m');

        $model = $this->monthView->build($year, $month);

        return $this->respond($model);
    }

    public function byDate()
    {
        // Get month for a given date
        $date = $this->request->getVar('date');  // 'Y-m-d'
        $dt = new DateTime($date);

        $model = $this->monthView->build(
            (int) $dt->format('Y'),
            (int) $dt->format('m')
        );

        return $this->respond($model);
    }
}
```

---

## Using EventLayoutService Directly

If you need to resolve overlaps outside of the view services:

```php
<?php

use App\Services\Calendar\EventLayoutService;

class CustomScheduler
{
    private EventLayoutService $eventLayout;

    public function __construct()
    {
        $this->eventLayout = new EventLayoutService();
    }

    public function layoutAppointments($appointments)
    {
        // Appointments must have 'start_at' and 'end_at' fields
        $positioned = $this->eventLayout->resolveLayout($appointments);

        foreach ($positioned as $appointment) {
            echo "Appointment {$appointment['id']}: ";
            echo "Column {$appointment['_column']} ";
            echo "of {$appointment['_columns_total']} ";
            echo "width {$appointment['_column_width_pct']}%\n";
        }

        return $positioned;
    }
}
```

---

## Using TimeGridService Directly

If you need custom time grids:

```php
<?php

use App\Services\Calendar\TimeGridService;

class CustomGridBuilder
{
    private TimeGridService $timeGrid;

    public function __construct()
    {
        $this->timeGrid = new TimeGridService();
    }

    public function buildBusinessHoursGrid($date)
    {
        // Uses business hours (08:00-18:00)
        $grid = $this->timeGrid->generateDayGrid($date);

        echo "Grid: {$grid['dayStart']} to {$grid['dayEnd']}\n";
        echo "Slots: " . count($grid['slots']) . "\n";

        return $grid;
    }

    public function buildProviderGrid($date, $providerId)
    {
        // Get provider's working hours
        $schedule = app('db')->table('xs_provider_schedules')
            ->where('provider_id', $providerId)
            ->where('day_of_week', (int) (new DateTime($date))->format('w'))
            ->first();

        if (!$schedule) {
            // No provider schedule, use business hours
            return $this->timeGrid->generateDayGrid($date);
        }

        // Use provider-specific hours
        $workingHours = [
            'startTime' => substr($schedule->start_time, 0, 5),
            'endTime' => substr($schedule->end_time, 0, 5),
            'isActive' => (bool) $schedule->is_active,
        ];

        return $this->timeGrid->generateDayGridWithProviderHours(
            $date,
            $workingHours
        );
    }
}
```

---

## Common Filters

All view services accept a `$filters` parameter:

```php
$model = $this->dayView->build($date, [
    // Filter by single provider
    'provider_id' => 5,

    // Filter by multiple providers
    'provider_ids' => [1, 3, 5],

    // Filter by service
    'service_id' => 10,

    // Filter by service(s)
    'service_ids' => [10, 15],

    // Filter by appointment status
    'status' => 'confirmed',

    // Filter by location
    'location_id' => 2,

    // Role-based filtering (for authorization)
    'user_role' => 'provider',
    'scope_to_user_id' => 5,  // Only if role='provider'
]);
```

---

## Frontend Integration

### Typical Frontend Flow

```javascript
// 1. Fetch day view
const response = await fetch('/api/calendar/day?date=2026-03-10');
const model = await response.json();

// 2. Iterate provider columns
model.providerColumns.forEach(column => {
    const { provider, grid } = column;
    
    // 3. Render timeline header
    console.log(`Provider: ${provider.name}`);
    
    // 4. Render time grid
    grid.slots.forEach(slot => {
        if (slot.appointments.length > 0) {
            // Render appointments
            slot.appointments.forEach(apt => {
                console.log(`
                    Appointment: ${apt.title}
                    Height: ${apt._heightPx}px
                    Top: ${apt._topPx}px
                    Column: ${apt._column} of ${apt._columns_total}
                    Width: ${apt._column_width_pct}%
                `);
            });
        }
    });
});
```

### CSS for Side-by-Side Appointments

```css
.time-slot {
    position: relative;
    height: 60px;  /* Must match hour height */
}

.appointment {
    position: absolute;
    left: calc(var(--column-left-pct) * 1%);
    width: calc(var(--column-width-pct) * 1%);
    height: calc(var(--height-px) * 1px);
    top: calc(var(--top-px) * 1px);
}
```

```javascript
// In frontend renderer
slot.appointments.forEach(apt => {
    const el = document.createElement('div');
    el.className = 'appointment';
    el.style.setProperty('--column-left-pct', apt._column_left_pct);
    el.style.setProperty('--column-width-pct', apt._column_width_pct);
    el.style.setProperty('--height-px', apt._heightPx);
    el.style.setProperty('--top-px', apt._topPx);
    el.textContent = apt.title;
    
    slotContainer.appendChild(el);
});
```

---

## Error Handling

```php
<?php

try {
    $model = $this->dayView->build('2026-03-10', [
        'provider_id' => 5,
    ]);

    return $this->respond($model);
} catch (\InvalidArgumentException $e) {
    // Invalid date format
    return $this->failValidationError($e->getMessage());
} catch (\Exception $e) {
    // Database error, etc.
    log_message('error', $e->getMessage());
    return $this->failServerError('Could not build calendar view');
}
```

---

## Testing Examples

### Unit Test: EventLayoutService

```php
<?php

use PHPUnit\Framework\TestCase;
use App\Services\Calendar\EventLayoutService;

class EventLayoutServiceTest extends TestCase
{
    private EventLayoutService $service;

    protected function setUp(): void
    {
        $this->service = new EventLayoutService();
    }

    public function testNonOverlappingEvents()
    {
        $events = [
            ['id' => 1, 'start_at' => '09:00', 'end_at' => '10:00'],
            ['id' => 2, 'start_at' => '10:00', 'end_at' => '11:00'],
        ];

        $result = $this->service->resolveLayout($events);

        $this->assertEquals(0, $result[0]['_column']);
        $this->assertEquals(1, $result[0]['_columns_total']);
        $this->assertEquals(100, $result[0]['_column_width_pct']);
    }

    public function testOverlappingEvents()
    {
        $events = [
            ['id' => 1, 'start_at' => '09:00', 'end_at' => '10:00'],
            ['id' => 2, 'start_at' => '09:30', 'end_at' => '10:30'],
        ];

        $result = $this->service->resolveLayout($events);

        $this->assertEquals(0, $result[0]['_column']);
        $this->assertEquals(2, $result[0]['_columns_total']);
        $this->assertEquals(50, $result[0]['_column_width_pct']);

        $this->assertEquals(1, $result[1]['_column']);
        $this->assertEquals(2, $result[1]['_columns_total']);
        $this->assertEquals(50, $result[1]['_column_width_pct']);
    }
}
```

### Integration Test: DayViewService

```php
<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\Calendar\DayViewService;

class DayViewServiceTest extends CIUnitTestCase
{
    private DayViewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DayViewService();
    }

    public function testBuildDayView()
    {
        $model = $this->service->build('2026-03-10');

        $this->assertArrayHasKey('date', $model);
        $this->assertArrayHasKey('providerColumns', $model);
        $this->assertArrayHasKey('appointments', $model);

        $this->assertIsArray($model['providerColumns']);
        $this->assertIsArray($model['appointments']);
    }

    public function testProviderScheduleOverride()
    {
        // Insert test provider schedule
        $this->db->table('xs_provider_schedules')->insert([
            'provider_id' => 1,
            'day_of_week' => 2,  // Tuesday
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => 1,
        ]);

        $model = $this->service->build('2026-03-10');  // Tuesday

        $providerColumn = $model['providerColumns'][0];
        $this->assertEquals('09:00', $providerColumn['grid']['dayStart']);
        $this->assertEquals('17:00', $providerColumn['grid']['dayEnd']);
    }
}
```

---

## Debugging Tips

### Print Calendar Range
```php
$range = new CalendarRangeService();
$model = $range->getRange('week', new DateTime('2026-03-10'));
echo json_encode($model, JSON_PRETTY_PRINT);
```

### Print Time Grid
```php
$timeGrid = new TimeGridService();
$grid = $timeGrid->generateDayGrid('2026-03-10');
echo "Day: {$grid['dayStart']} to {$grid['dayEnd']}, ";
echo "Slots: " . count($grid['slots']) . "\n";
```

### Print Positioned Appointments
```php
$layout = new EventLayoutService();
$positioned = $layout->resolveLayout($appointments);

foreach ($positioned as $apt) {
    echo "Apt {$apt['id']}: Col {$apt['_column']}/{$apt['_columns_total']}, ";
    echo "Width {$apt['_column_width_pct']}%, Left {$apt['_column_left_pct']}%\n";
}
```

---

## Performance Tips

1. **Cache provider schedules** — They change infrequently
   ```php
   Cache::remember('provider_schedules_' . $providerId, 3600, fn() => 
       $schedule->where('provider_id', $providerId)->findAll()
   );
   ```

2. **Batch fetch appointments** — Get entire month/week in one query
   ```php
   $query->getForRange($startDate, $endDate, $filters);
   ```

3. **Limit past appointments** — Exclude old data
   ```php
   $filters['after'] = date('Y-m-d', strtotime('-1 month'));
   ```

4. **Paginate for large datasets**
   ```php
   $filters['limit'] = 100;
   $filters['offset'] = 0;
   ```

---

## Common Mistakes

### ❌ Passing wrong date format
```php
// Wrong
$model = $this->dayView->build('3/10/2026');

// Correct
$model = $this->dayView->build('2026-03-10');
```

### ❌ Not providing start_at/end_at for overlap detection
```php
// EventLayoutService requires these fields
$appointments = [
    ['id' => 1],  // ❌ Missing start_at and end_at
];

// Correct
$appointments = [
    ['id' => 1, 'start_at' => '09:00', 'end_at' => '10:00'],
];
```

### ❌ Using old day-of-week names
```php
// Old (WRONG for DayViewService)
$hours = $this->getProviderWorkingHours(5, 'monday');

// New (CORRECT)
$hours = $this->getProviderWorkingHours(5, 2);  // 0=Sun, 2=Tue, etc.
```

---

## Summary Checklist

- ✅ Use TodayViewService for today's appointments
- ✅ Use DayViewService for any single day
- ✅ Use WeekViewService for 7-day week
- ✅ Use MonthViewService for month grid
- ✅ Use EventLayoutService for overlap resolution
- ✅ Use TimeGridService for custom time grids
- ✅ Pass 'Y-m-d' format dates always
- ✅ Handle provider schedules automatically (system checks ProviderScheduleModel first)
- ✅ Render side-by-side with `_column`, `_columns_total`, `_column_width_pct`

Done! 🎉

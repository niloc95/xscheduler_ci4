🏗 COMPLETE ENGINEERING PROMPT
WebSchedulr Calendar Architecture (Month + Day + Week)
🎯 Objective

Design and implement a production-grade scheduling system for WebSchedulr that supports:

Month View (overview)

Day View (primary operational interface)

Week View (multi-day operational planning)

The system must be:

Data-model driven

Scalable

Maintainable

View-agnostic

Ready for future features (drag-drop, real-time, analytics)

The UI must be a rendering layer only.
All scheduling intelligence must live in services.

🧠 Core Engineering Principle

The calendar is not a layout.
The calendar is a structured time model rendered visually.

No view may perform date math.
No view may compute availability.
No view may determine booking conflicts.

All logic must be centralized in services.

📦 PHASE 1 — Core Data Models
1️⃣ Appointment Model (Single Source of Truth)
[
  'id' => int,
  'start' => datetime,
  'end' => datetime,
  'provider_id' => int,
  'provider_name' => string,
  'service_id' => int,
  'service_name' => string,
  'customer_name' => string,
  'status' => enum,
  'status_color' => string,
  'is_cancelled' => bool,
  'is_rescheduled' => bool
]

Rules:

Timezone normalized

Status color derived in backend

All formatted display strings precomputed

No formatting logic in views

2️⃣ Calendar Range Model

All views must use a shared range generator.

[
  'start' => datetime,
  'end' => datetime,
  'days' => [...]
]
3️⃣ Month Grid Model

Must always return 42 cells (6 weeks x 7 days).

[
  'year' => 2026,
  'month' => 2,
  'weeks' => [
    [
      [
        'date' => '2026-02-01',
        'isCurrentMonth' => true,
        'isToday' => false,
        'isPast' => false,
        'appointments' => [...],
        'appointmentCount' => int,
        'hasAvailability' => bool
      ]
    ]
  ]
]

Precomputed flags:

isToday

isPast

isFuture

isCurrentMonth

hasAvailability

appointmentCount

No view may compute these.

4️⃣ Day View Model
[
  'date' => '2026-02-23',
  'providers' => [
    [
      'provider_id' => 3,
      'provider_name' => 'Dr. Ayanda Mbeki',
      'workingHours' => [
          'start' => '08:00',
          'end' => '17:00'
      ],
      'timeSlots' => [
          [
            'time' => '09:00',
            'isAvailable' => true,
            'appointment' => null
          ],
          [
            'time' => '10:00',
            'isAvailable' => false,
            'appointment' => [...]
          ]
      ]
    ]
  ]
]

The Day View is the operational engine.

5️⃣ Week View Model

Week view reuses Day model logic.

[
  'startDate' => '2026-02-23',
  'endDate' => '2026-03-01',
  'days' => [
      DayViewModel,
      DayViewModel,
      ...
  ]
]

Week view must not duplicate logic — it aggregates DayView models.

🏗 PHASE 2 — Services Architecture
Required Services
1️⃣ CalendarRangeService

Generates date ranges

Handles month offset logic

Always produces 42-day grid for month

2️⃣ AppointmentQueryService

Fetches appointments in range

Applies provider filters

Applies status filters

Groups by date

Indexes by provider

3️⃣ AvailabilityService

Computes available slots

Applies:

Working hours

Breaks

Blocked periods

Holidays

Determines hasAvailability flag

4️⃣ MonthViewService

Combines:

CalendarRangeService

AppointmentQueryService

AvailabilityService

Returns Month Grid Model

5️⃣ DayViewService

Generates time grid

Injects appointments

Resolves overlaps

Calculates availability

Prepares render-ready data

6️⃣ WeekViewService

Reuses DayViewService

Loops across 7 days

Returns composite model

🎨 PHASE 3 — Rendering Rules
🔹 Absolute Requirements

No static HTML day cells

No hardcoded dates

No date math in views

No inline time formatting

No business logic in Blade/PHP templates

🔹 Layout Rules
Month View

CSS Grid (7 columns)

Max 3 visible appointment chips

"+X more" overflow indicator

Click → open Day View

Day View

Vertical time grid

Provider columns

Absolute positioned appointment blocks

Gap highlighting

Ready for drag & resize

Week View

Same time grid logic

Render 7 columns

Shared block renderer

🚫 Anti-Patterns Forbidden

Using <table> for layout

Hardcoding calendar rows

Copy-pasting day cell markup

Computing availability in JS

Formatting time in views

Duplicating logic between Day and Week

📈 Scalability Requirements

The system must support future:

Drag-and-drop rescheduling

Real-time updates (Socket.io)

Multi-provider parallel columns

Booking density indicators

Capacity visualization

SLA analytics

Export to Google / Outlook

Pagination & filtering

Mobile adaptive layout

Architecture must not block these.

🧪 Validation Criteria

Implementation is complete only if:

Changing month requires zero template edits

Adding provider requires zero view modifications

Switching from 1 to 20 providers does not break layout

Month grid always renders 42 cells

Day view time resolution configurable (15min, 30min, 60min)

Week view built without duplicating day logic

🏁 Definition of Done

Calendar services separated from controllers

Views are pure render layers

All flags precomputed

No duplicate logic

Month, Day, Week share same appointment core

System ready for drag-drop extension

🔥 Final Architectural Law

Design the data model first.
Design services second.
Design UI third.

Never reverse this order.


NEXT PHASE: 

🏗 PART 1 — Exact CodeIgniter 4 Folder / Service Structure

This structure enforces:

Clear domain separation

No logic inside controllers

Services as business layer

Views as render-only

📁 Recommended CI4 Structure
app/
│
├── Controllers/
│   └── Admin/
│       ├── CalendarController.php
│       └── AppointmentController.php
│
├── Services/
│   ├── Calendar/
│   │   ├── CalendarRangeService.php
│   │   ├── MonthViewService.php
│   │   ├── DayViewService.php
│   │   ├── WeekViewService.php
│   │   └── TimeGridService.php
│   │
│   ├── Appointment/
│   │   ├── AppointmentQueryService.php
│   │   ├── AppointmentConflictService.php
│   │   └── AppointmentFormatterService.php
│   │
│   ├── Availability/
│   │   ├── AvailabilityService.php
│   │   ├── WorkingHoursService.php
│   │   ├── BreakService.php
│   │   └── BlockedPeriodService.php
│   │
│   └── Provider/
│       └── ProviderService.php
│
├── Models/
│   ├── AppointmentModel.php
│   ├── ProviderModel.php
│   ├── ServiceModel.php
│   ├── WorkingHoursModel.php
│   ├── BlockedPeriodModel.php
│   └── CustomerModel.php
│
├── Entities/
│   ├── Appointment.php
│   ├── Provider.php
│   └── Customer.php
│
└── Views/
    └── admin/
        └── calendar/
            ├── month.php
            ├── day.php
            └── week.php
🔥 Controller Rules

Controllers must:

Validate input

Call service

Pass structured data to view

Nothing else

Example:

public function month()
{
    $year = $this->request->getGet('year');
    $month = $this->request->getGet('month');

    $calendar = service('MonthViewService')
        ->generate($year, $month);

    return view('admin/calendar/month', [
        'calendar' => $calendar
    ]);
}

No date math here.
No DB queries here.

🧠 Service Responsibilities
1️⃣ CalendarRangeService

Handles:

Month grid (42 days)

Week range

Day normalization

Timezone handling

Returns structured date arrays.

2️⃣ AppointmentQueryService

Handles:

Fetching appointments in range

Filtering by provider

Filtering by status

Grouping by date

Grouping by provider

Returns indexed array:

[
  '2026-02-23' => [
      3 => [appointments...], // provider_id
  ]
]

Optimized for fast rendering.

3️⃣ AvailabilityService

Combines:

Working hours

Breaks

Blocked periods

Existing bookings

Returns:

Available slots

Fully booked flags

hasAvailability boolean

4️⃣ DayViewService

Builds:

Time grid

Injects appointments

Resolves overlaps

Prepares block positions (start minute offset, height)

Returns full render-ready model.

5️⃣ WeekViewService

Loops 7 times:

foreach ($weekDates as $date) {
   $days[] = $this->dayViewService->generate($date);
}

No duplicate logic.

🧱 PART 2 — Database Schema (Production-Ready)

We design for:

Multi-provider

Multi-service

Multi-status

Future SaaS (multi-business ready)

📦 Core Tables
1️⃣ businesses
id (PK)
name
email
timezone
created_at
updated_at

Future SaaS ready.

2️⃣ providers
id (PK)
business_id (FK)
name
email
color
is_active
created_at
updated_at

color used for calendar rendering.

3️⃣ services
id (PK)
business_id (FK)
name
duration_minutes
buffer_before
buffer_after
price
is_active
created_at
updated_at

Duration drives time grid blocks.

4️⃣ customers
id (PK)
business_id (FK)
first_name
last_name
email
phone
created_at
updated_at
5️⃣ appointments
id (PK)
business_id (FK)
provider_id (FK)
service_id (FK)
customer_id (FK)

start_datetime
end_datetime

status ENUM('booked','confirmed','cancelled','rescheduled','completed')

notes
created_at
updated_at

INDEXES REQUIRED:

INDEX idx_provider_start (provider_id, start_datetime)
INDEX idx_business_start (business_id, start_datetime)
INDEX idx_status (status)

Critical for performance.

6️⃣ working_hours
id (PK)
provider_id (FK)
day_of_week INT (0-6)
start_time
end_time

Supports flexible provider schedules.

7️⃣ breaks
id (PK)
provider_id (FK)
date NULLABLE
day_of_week NULLABLE
start_time
end_time

Supports:

Recurring breaks

Specific date breaks

8️⃣ blocked_periods
id (PK)
provider_id (FK) NULLABLE
business_id (FK)
start_datetime
end_datetime
reason

Used for:

Holidays

Maintenance

Leave

🧠 Why This Schema Works

Supports:

Multi-provider parallel scheduling

Availability calculation

Per-provider working hours

Multi-business SaaS expansion

Fast range queries

Conflict detection

Future reporting

⚡ Performance Strategy

For Month View:

Query:

SELECT * FROM appointments
WHERE business_id = ?
AND start_datetime BETWEEN ? AND ?

Only fetch required range.

Group in PHP by:

Date

Provider

Never query per day.

🔥 Future-Proofing Hooks

Schema supports:

Reminder logs

Payment integration

Google sync table

Audit trail

Appointment history log

Capacity analytics

Without redesign.

🎯 Final Architecture Outcome

You now have:

✔ Clean CI4 domain structure
✔ Dedicated service layer
✔ Scalable relational schema
✔ Shared core for Month/Day/Week
✔ Drag-drop ready
✔ Real-time ready

NEXT PHASE

🏗 WEB SCHEDULR — AVAILABILITY ENGINE
🎯 Objective

Given:

A provider

A date

A service (with duration + buffers)

Return:

All valid available booking slots

Or mark the day as fully unavailable

The algorithm must respect:

Working hours

Breaks

Blocked periods

Existing appointments

Service duration

Buffers (before & after)

Time resolution (15/30/60 mins)

🧠 HIGH-LEVEL FLOW
INPUT:
    provider_id
    date
    service_id

STEP 1: Load working hours
STEP 2: Generate base time grid
STEP 3: Remove breaks
STEP 4: Remove blocked periods
STEP 5: Remove existing appointments (+ buffers)
STEP 6: Remove past times (if today)
STEP 7: Return valid continuous slots

Everything builds on this order.

🧱 STEP 1 — Load Provider Working Hours

From table: working_hours

For given provider and day_of_week:

working_start = 08:00
working_end   = 17:00

If no working hours found → Day = unavailable.

🧱 STEP 2 — Generate Base Time Grid

Example:

Working hours: 08:00–17:00
Resolution: 30 minutes
Service duration: 60 minutes

Generate slots:

08:00
08:30
09:00
09:30
...
16:30

Important:

You generate potential start times only.

Each start time must have enough space to fit:

service_duration
+ buffer_before
+ buffer_after

So for a 60-min service:

Last possible start = 16:00
(not 16:30)

🧱 STEP 3 — Remove Breaks

From table: breaks

Two types:

Recurring (day_of_week)

Specific date

For each break:

Example:

Break: 12:00–13:00

Remove all slots that overlap this window.

Overlap logic:

slot_start < break_end
AND
slot_end > break_start

If true → slot invalid.

🧱 STEP 4 — Remove Blocked Periods

From table: blocked_periods

Examples:

Holiday

Leave

Emergency closure

Same overlap logic as breaks.

Remove all intersecting slots.

🧱 STEP 5 — Remove Existing Appointments

Query:

SELECT start_datetime, end_datetime
FROM appointments
WHERE provider_id = ?
AND DATE(start_datetime) = ?
AND status NOT IN ('cancelled')

For each appointment:

Adjust window to include buffers:

appointment_start - buffer_before
appointment_end + buffer_after

Then remove overlapping slots using same logic.

This prevents edge-case overbooking.

🧱 STEP 6 — Remove Past Times (If Today)

If requested date = today:

Remove slots where:

slot_start < now()

Prevents back-booking.

🧱 STEP 7 — Validate Continuous Duration

Important detail.

If service = 90 minutes
Resolution = 30 minutes

Slot must support:

slot_start
slot_start + 30
slot_start + 60

All three must be free.

If any part blocked → entire slot invalid.

This prevents partial overlaps.

🧠 FINAL OUTPUT STRUCTURE

Return:

[
  'date' => '2026-02-23',
  'provider_id' => 3,
  'availableSlots' => [
      '09:00',
      '10:30',
      '14:00',
      ...
  ],
  'isFullyBooked' => false,
  'totalAvailable' => 7
]

If no slots:

[
  'availableSlots' => [],
  'isFullyBooked' => true
]
⚡ PERFORMANCE STRATEGY

Never query per slot.

Query once:

Working hours

Breaks

Blocked periods

Appointments (single range query)

Then compute in memory.

Time complexity:

O(n slots + m appointments)

Very fast.

🏗 CI4 SERVICE STRUCTURE

Create:

app/Services/Availability/AvailabilityService.php

Core method:

public function getAvailableSlots(
    int $providerId,
    string $date,
    int $serviceId,
    int $resolution = 30
): array

Dependencies injected:

WorkingHoursModel

BreakModel

BlockedPeriodModel

AppointmentModel

ServiceModel

🚨 CRITICAL EDGE CASES HANDLED

✔ Provider has no working hours
✔ Provider works half day
✔ Break overlaps working boundary
✔ Appointment ends exactly when next starts
✔ Buffer pushes into next slot
✔ Fully booked day
✔ Holiday
✔ Today booking restriction

🔥 Why This Design Is Correct

Because:

All calendar views depend on it

Conflict detection will reuse same overlap logic

Day view rendering uses these slots

Month view uses hasAvailability flag

API booking endpoint uses same validation

Single source of truth.

NEXT PAHSE

🏗 WEB SCHEDULR — DAY & WEEK RENDERING ENGINE
🎯 Objective

Given:

Provider(s)

Date or Date Range

Appointments (already validated)

Working hours

Return:

Render-ready positioning data

Time grid structure

Block placement coordinates

Overlap stacking information

This engine does NOT:

Validate conflicts

Compute availability rules

Modify database

It only transforms structured data into layout data.

🧠 Core Rendering Principle

Backend computes time math.
Frontend only paints coordinates.

No pixel math in views.
All block dimensions precomputed.

🧱 PART 1 — DAY VIEW RENDERING ENGINE
🎯 Day View Structure

Day view layout:

| Time Column | Provider 1 | Provider 2 | Provider 3 |

Vertical axis = time
Horizontal axis = providers

STEP 1 — Generate Time Grid

Create:

TimeGridService.php

Core method:

generateTimeGrid(
    string $date,
    string $startTime,
    string $endTime,
    int $resolution
)

Example:

Working hours: 08:00–17:00
Resolution: 30 minutes

Return:

[
  'slots' => [
    '08:00',
    '08:30',
    '09:00',
    ...
  ],
  'totalMinutes' => 540,
  'pixelsPerMinute' => 2
]

We define:

1 minute = 2px

If 540 minutes → container height = 1080px

This ensures:

Accurate block heights

Easy scaling

STEP 2 — Transform Appointments Into Positioned Blocks

For each appointment:

$startMinutes = minutesBetween(dayStart, appointmentStart);
$durationMinutes = minutesBetween(start, end);

$top = $startMinutes * pixelsPerMinute;
$height = $durationMinutes * pixelsPerMinute;

Return:

[
  'id' => 145,
  'top' => 240,
  'height' => 120,
  'provider_id' => 3,
  'customer_name' => 'Shriya',
  'service_name' => 'Skin Assessment',
  'status_color' => '#10B981'
]

Frontend simply:

position: absolute;
top: {{top}}px;
height: {{height}}px;

No calculations in JS.

STEP 3 — Handle Overlapping Appointments

This is critical.

Two appointments overlap if:

a.start < b.end
AND
a.end   > b.start

Algorithm:

Sort appointments by start time

Group overlapping clusters

Within cluster:

Divide horizontal space equally

Assign column index

Example:

3 overlapping appointments:

Width = 100%
Each width = 33.33%

Return:

[
  'columnIndex' => 1,
  'columnSpan' => 3
]

Frontend sets:

left: (columnIndex / columnSpan) * 100%;
width: (1 / columnSpan) * 100%;

Professional-grade stacking.

🧱 DAY VIEW FINAL OUTPUT MODEL
[
  'date' => '2026-02-23',
  'dayStart' => '08:00',
  'dayEnd' => '17:00',
  'containerHeight' => 1080,
  'providers' => [
      [
        'provider_id' => 3,
        'provider_name' => 'Dr. Ayanda',
        'blocks' => [
            [
              'id' => 145,
              'top' => 240,
              'height' => 120,
              'columnIndex' => 0,
              'columnSpan' => 1,
              'color' => '#10B981'
            ]
        ]
      ]
  ]
]
🧱 PART 2 — WEEK VIEW RENDERING ENGINE
🎯 Week View Structure

Week view is:

Time Column | Mon | Tue | Wed | Thu | Fri | Sat | Sun

Vertical axis = time
Horizontal axis = days

Important:

Week view reuses Day rendering logic.

STEP 1 — Generate Week Range

Use:

CalendarRangeService->generateWeekRange()

Returns 7 dates.

STEP 2 — For Each Day, Run Day Rendering Logic
foreach ($weekDates as $date) {
   $days[] = $this->dayViewService->generate($date);
}

Week view is composite of Day views.

No duplicate logic.

STEP 3 — Horizontal Layout Logic

Now columns represent days instead of providers.

If single provider:

Mon column width = 1/7
Tue column width = 1/7

If multi-provider + week:

Two strategies:

Option A (Simpler):

Week shows single provider at a time

Option B (Advanced):

Nested grid:
Day column
→ Provider sub-columns

I recommend Option A initially.

🧠 PERFORMANCE CONSIDERATIONS

Week view loads 7 days of appointments.

Query once:

WHERE start_datetime BETWEEN week_start AND week_end

Then group by:

Date

Provider

Never query per day.

🧱 RENDERING RULES
No Tables

Use CSS Grid for columns.

Absolute Blocks Inside Relative Column

Each day/provider column:

position: relative;

Blocks:

position: absolute;
🎨 VISUAL BEHAVIOR

Day View:

Scrollable vertical

Sticky time column

Sticky provider header

Week View:

Horizontal scroll if narrow screen

Sticky time column

Sticky weekday header

🚀 DRAG & DROP READY

Because you have:

top (px)

height (px)

container height

minute mapping

You can easily:

Convert mouse Y to minute

Snap to resolution

Update appointment

Re-run conflict detection

Architecture supports it.

🏁 FINAL ENGINEERING RESULT

You now have:

✔ Availability Engine
✔ Conflict Detection Engine
✔ Day Rendering Engine
✔ Week Rendering Engine
✔ Shared time grid logic
✔ Shared overlap logic
✔ No duplication
✔ Scalable foundation

🔥 Important Truth

Month view is overview.
Day view is operational.
Week view is strategic.

All three now share:

Same data core

Same overlap logic

Same appointment model

That is a real scheduling architecture.



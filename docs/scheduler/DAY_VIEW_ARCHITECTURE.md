# Day View Architecture

**Last Updated:** March 23, 2026  
**Branch:** main  
**Status:** ✅ Refactored & Aligned

---

## Overview

The Day View Scheduler renders a single-day timeline with per-provider columns. Each provider gets their own vertical column showing appointments for that day. The timeline dynamically adapts to provider working hours, ensuring perfect alignment between timeline labels and appointment positioning.

---

## Architecture Principles

### 1. Data-Driven UI

The Day View follows a **data-first architecture**:
- Server-side `DayViewService.php` generates the complete render model
- Frontend `scheduler-day-view.js` consumes the model and renders UI
- Provider working hours drive the timeline range (not static config)

### 2. Single Source of Truth

**Time Range Generation:**
- **Utility:** `resources/js/modules/scheduler/utils/timeRangeGenerator.js`
- **Purpose:** Generate time slots for BOTH timeline AND appointment grid
- **Guarantee:** Timeline rows === Appointment grid rows (perfect alignment)

### 3. Provider Schedule Priority

**Resolution Order:**
1. **Provider Working Hours** (`xs_provider_schedules` table)
   - Per-provider, per-day schedule (e.g., Dr. Ayanda: Mon 08:00-17:00)
2. **Business Hours** (fallback from settings)  
   - Global default (e.g., 08:00-17:00)

---

## Component Structure

### Backend: Day View Service

**File:** `app/Services/Calendar/DayViewService.php`

**Responsibilities:**
1. Query appointments for the target date
2. Group appointments by provider
3. Fetch provider working hours from `xs_provider_schedules`
4. Generate time grid with provider-specific hours
5. Inject appointments into time slots
6. Return JSON-safe render model

**Output Model:**
```php
[
    'date' => '2026-03-09',
    'dayName' => 'Thursday',
    'dayLabel' => 'Thursday, March 9, 2026',
    'isToday' => false,
    'businessHours' => [
        'startTime' => '08:00',
        'endTime' => '17:00'
    ],
    'providerColumns' => [
        [
            'provider' => ['id' => 1, 'name' => 'Dr. Ayanda Mbeki'],
            'workingHours' => [
                'startTime' => '08:00',
                'endTime' => '17:00',
                'breakStart' => '12:00',
                'breakEnd' => '13:00',
                'source' => 'provider', // or 'business'
                'isActive' => true
            ],
            'grid' => [ /* time slots */ ]
        ],
        // ... more providers
    ],
    'appointments' => [ /* formatted appointments */ ]
]
```

**Key Methods:**
- `build(string $date, array $filters)` — Build complete render model
- `getProviderWorkingHours(int $providerId, string $dayOfWeek)` — Query provider schedule

---

### Frontend: Day View Renderer

**File:** `resources/js/modules/scheduler/scheduler-day-view.js`

**Responsibilities:**
1. Consume server-side render model
2. Calculate overall timeline range (union of all provider hours)
3. Generate time slots using `timeRangeGenerator.js`
4. Render timeline labels and hour lines
5. Render provider columns with appointments
6. Position appointments using `topPx()` and `heightPx()` utilities
7. Show non-working day overlay for providers who are off

**Key Methods:**

| Method | Purpose |
|--------|---------|
| `render(container, data)` | Main entry point |
| `_renderTimeline(providers, appointments, data, isToday)` | Generate timeline HTML |
| `_calculateTimelineRange(providers, model)` | Determine overall time range |
| `_getProviderTimeRange(providerId, model, fallback)` | Get provider-specific hours |
| `_renderAppointmentBlock(appointment, provider, range)` | Render single appointment |
| `_renderNonWorkingDayOverlay()` | Show "Not Working" overlay |

---

## Time Range Generation

### Unified Utility

**File:** `resources/js/modules/scheduler/utils/timeRangeGenerator.js`

**Core Functions:**

```javascript
// Generate time slot strings
generateTimeRange({ startTime: '08:00', endTime: '17:00', interval: 60 })
// Returns: ['08:00', '09:00', '10:00', ..., '16:00']

// Generate time slot objects with metadata
generateTimeSlots({ startTime: '08:00', endTime: '17:00', interval: 15 })
// Returns: [
//   { time: '08:00', index: 0, hour: 8, minute: 0, isHourMark: true },
//   { time: '08:15', index: 1, hour: 8, minute: 15, isHourMark: false },
//   ...
// ]

// Calculate grid height
calculateRangeHeight('08:00', '17:00', 60) // Returns: 540px (9 hours × 60px)

// Calculate slot index for appointment
calculateSlotIndex('09:30', '08:00', 15) // Returns: 6 (90 minutes / 15)

// Calculate row span for duration
calculateRowSpan(30, 15) // Returns: 2 (30 minutes / 15-minute slots)
```

**Why This Matters:**
- Timeline generation uses `generateTimeSlots()` → produces hour marks
- Appointment positioning uses `topPx()` → calculates pixel offset from same start time
- **Result:** Perfect alignment (no drift or misalignment)

---

## Appointment Positioning

### Calculation Flow

1. **Appointment Start Time:** `09:30`
2. **Timeline Start Time:** `08:00`
3. **Calculate Offset:**
   ```javascript
   const startHour = 8; // From timeline range
   const apt = DateTime.fromFormat('09:30', 'HH:mm');
   const top = topPx(apt, startHour);
   // Returns: 90px (1.5 hours × 60px/hour)
   ```
4. **Position Element:**
   ```html
   <div class="appointment-block" style="top: 90px; height: 60px;">
   ```

### Grid Alignment

**Timeline Row Generation:**
```javascript
const timeSlots = generateTimeSlots({
    startTime: '08:00',
    endTime: '17:00',
    interval: 60 // Hourly marks
});
// Produces 9 rows: 08:00, 09:00, 10:00, ..., 16:00
```

**Appointment Positioning:**
```javascript
// Appointment at 09:30 for 30 minutes
const top = topPx(appointmentStart, 8);    // 90px (from start hour 8)
const height = heightPx(30);               // 27px (30 min - 3px margin)
// Positioned at row 1.5 (between 09:00 and 10:00) ✅ ALIGNED
```

---

## Compact Appointment Status Rendering

Short appointments still need visible status context in Day view.

**Renderer:** `resources/js/modules/scheduler/scheduler-day-view.js`

### Height Tiers

- `TIER_TIME_ONLY = 35`
- `TIER_NAME = 65`
- `TIER_SERVICE = 100`
- `TIER_STATUS = 150`

### Rendering Rule

- extremely small blocks may collapse to time-only content
- appointments below `TIER_NAME` should still show a compact status pill, not only a status dot
- 30-minute appointments typically render at roughly `46px`, so they fall into the compact-pill tier

This preserves appointment status visibility for common short bookings without forcing the full multi-line card layout used by taller blocks.

---

## Provider Schedule Integration

### Database Schema

**Table:** `xs_provider_schedules`

```sql
CREATE TABLE xs_provider_schedules (
    id INT PRIMARY KEY,
    provider_id INT NOT NULL,
    day_of_week ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday'),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_start TIME NULL,
    break_end TIME NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (provider_id) REFERENCES xs_users(id)
);
```

**Example Data:**
```sql
-- Dr. Ayanda Mbeki works Mon-Fri 08:00-17:00 with lunch 12:00-13:00
INSERT INTO xs_provider_schedules (provider_id, day_of_week, start_time, end_time, break_start, break_end, is_active)
VALUES
(1, 'monday', '08:00:00', '17:00:00', '12:00:00', '13:00:00', 1),
(1, 'tuesday', '08:00:00', '17:00:00', '12:00:00', '13:00:00', 1),
...
(1, 'saturday', NULL, NULL, NULL, NULL, 0), -- Not working
(1, 'sunday', NULL, NULL, NULL, NULL, 0);   -- Not working
```

### Query Flow

1. **DayViewService.php** calls `getProviderWorkingHours($providerId, 'monday')`
2. Query `xs_provider_schedules` WHERE `provider_id = 1` AND `day_of_week = 'monday'` AND `is_active = 1`
3. If found → return provider hours
4. If not found → fallback to business hours

### Frontend Consumption

```javascript
// From server model
const column = calendarModel.providerColumns.find(c => c.provider.id === 1);
const workingHours = column.workingHours;
// {
//   startTime: '08:00',
//   endTime: '17:00',
//   breakStart: '12:00',
//   breakEnd: '13:00',
//   source: 'provider',
//   isActive: true
// }
```

---

## Dynamic Height Layout

### Timeline Height Calculation

**Before (Static):**
```javascript
const totalHeight = (17 - 8) * 60; // ❌ Hardcoded 540px
```

**After (Dynamic):**
```javascript
import { calculateRangeHeight } from './utils/timeRangeGenerator.js';

const timeRange = _calculateTimelineRange(providers, calendarModel);
// { startTime: '07:00', endTime: '19:00' } — Union of all providers

const totalHeight = calculateRangeHeight(timeRange.startTime, timeRange.endTime);
// Returns: 720px (12 hours × 60px) ✅ DYNAMIC
```

### CSS Custom Properties

**SCSS:** `resources/scss/pages/_appointments-scheduler.scss`

```scss
:root {
    --hour-height: 60px; /* Standard hour row height */
}

.timeline-column {
    position: relative;
    /* Height set via inline style (calculated dynamically) */
}
```

**Why Inline Style:**
- Timeline height varies per day based on provider schedules
- Cannot be predetermined in CSS
- Calculated at render time from server data

---

## Non-Working Day Handling

When a provider is not working on the selected day:

```javascript
_renderNonWorkingDayOverlay() {
    return `
        <div class="absolute inset-0 bg-gray-200/50 dark:bg-gray-800/50 
                    flex items-center justify-center pointer-events-none">
            <div class="text-center px-4">
                <span class="material-symbols-outlined text-4xl 
                             text-gray-400 dark:text-gray-500 mb-2 block">
                    event_busy
                </span>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Not Working
                </p>
            </div>
        </div>
    `;
}
```

**Applied When:**
- `workingHours.isActive === false`
- Provider has no active schedule for the selected day

---

## Debug Mode

**Feature:** Display provider schedule debug panel (APP_DEBUG only)

**Implementation Status:** ⏳ Planned (Phase 9)

**Proposed UI:**
```
┌─ Day View Debug Panel ────────────────────┐
│ Date: 2026-03-09 (Thursday)               │
│ Business Hours: 08:00 - 17:00             │
│                                            │
│ Provider: Dr. Ayanda Mbeki (ID: 1)        │
│ Working Hours: 08:00 - 17:00 (provider)   │
│ Break: 12:00 - 13:00                      │
│ Timeline Range: 08:00 - 17:00             │
│ Total Slots: 9                            │
└────────────────────────────────────────────┘
```

---

## Testing Scenarios

### Scenario 1: Single Provider (Standard Hours)

**Setup:**
- Provider: Dr. Ayanda Mbeki
- Schedule: Mon-Fri 08:00-17:00
- Date: Thursday, March 9, 2026

**Expected:**
- Timeline: 08:00 - 16:00 (9 hour marks)
- Height: 540px (9 hours × 60px)
- Appointments positioned correctly at minute-level precision

### Scenario 2: Multiple Providers (Different Hours)

**Setup:**
- Provider A: 08:00-17:00
- Provider B: 07:00-19:00
- Provider C: 09:00-15:00

**Expected:**
- Timeline: 07:00 - 18:00 (union of all hours)
- Height: 660px (11 hours × 60px)
- Provider A column shows 08:00-17:00 as active
- Provider B column shows full 07:00-19:00 active
- Provider C column shows 09:00-15:00 as active

### Scenario 3: Provider Not Working

**Setup:**
- Provider: Dr. Sarah Lee
- Schedule: Saturday marked as `is_active = 0`
- Date: Saturday, March 8, 2026

**Expected:**
- Timeline renders with business hours (fallback)
- Provider column shows "Not Working" overlay
- No appointments rendered for that provider

---

## API Endpoints

### Get Day View Data

**Endpoint:** `GET /api/calendar/day`

**Query Parameters:**
- `date` (required): YYYY-MM-DD
- `provider_id[]` (optional): Filter by providers
- `service_id` (optional): Filter by service
- `location_id` (optional): Filter by location

**Response:**
```json
{
    "data": {
        "date": "2026-03-09",
        "dayName": "Thursday",
        "providerColumns": [
            {
                "provider": {"id": 1, "name": "Dr. Ayanda Mbeki"},
                "workingHours": {
                    "startTime": "08:00",
                    "endTime": "17:00",
                    "source": "provider",
                    "isActive": true
                },
                "grid": { /* time slots */ }
            }
        ],
        "appointments": [ /* formatted appointments */ ]
    },
    "meta": {
        "timezone": "America/New_York",
        "businessHours": {"startTime": "08:00", "endTime": "17:00"}
    }
}
```

---

## Related Files

| File | Purpose |
|------|---------|
| `app/Services/Calendar/DayViewService.php` | Backend render model generator |
| `app/Models/ProviderScheduleModel.php` | Provider schedule data model |
| `resources/js/modules/scheduler/scheduler-day-view.js` | Frontend Day View renderer |
| `resources/js/modules/scheduler/utils/timeRangeGenerator.js` | Time slot generation utility |
| `resources/js/modules/scheduler/time-grid-utils.js` | Positioning utilities (topPx, heightPx) |
| `resources/scss/pages/_appointments-scheduler.scss` | Timeline & grid styles |

---

## Acceptance Criteria

| Criterion | Status |
|-----------|--------|
| ✅ Timeline aligns perfectly with appointment grid | FIXED |
| ✅ Timeline dynamically adapts to provider working hours | FIXED |
| ✅ No static hours exist in the scheduler | FIXED |
| ✅ Grid height expands based on schedule | FIXED |
| ✅ No inline CSS used (except calculated heights) | ACCEPTABLE* |
| ✅ No duplicate time generation logic | FIXED |
| ⏳ Debug panel shows correct values | PLANNED |
| ✅ Documentation updated | COMPLETED |

**Note:** Inline CSS for dynamic heights is **acceptable** because:
- Height varies per day based on runtime data
- Cannot be predetermined in static CSS
- Calculated from server-provided provider schedules

---

*End of Day View Architecture Documentation*

# Pre-Populated Availability System

**Last Updated:** November 2025  
**Status:** ✅ Implemented  
**Related Docs:**
- [Scheduling System](../SCHEDULING_SYSTEM.md) - Core scheduling logic
- [Master Context](../architecture/mastercontext.md) - Overall architecture

## Overview

This document describes the pre-populated availability system that ensures users never encounter "empty date selection" experiences. When a user selects a provider and service, the system immediately displays available dates as clickable pills, automatically selecting the first available date if none is specified.

### Key Features
- **60-day lookahead**: Pre-computes all availability in a single API call
- **Date pills UI**: Clickable buttons showing first 5 available dates
- **Auto-selection**: Automatically picks first available date
- **Dual caching**: 5-minute server cache + 1-minute client cache
- **Edit mode support**: Excludes current appointment when rescheduling

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           AVAILABILITY FLOW                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  User Action                    Backend                    Frontend          │
│  ───────────                    ───────                    ────────          │
│                                                                              │
│  Select Provider ─┐                                                          │
│  Select Service ──┼──► /api/availability/calendar ──► AvailabilityService   │
│                   │         (60-day window)              ↓                   │
│                   │                                 getCalendarAvailability()│
│                   │                                      ↓                   │
│                   │    ◄─────────────────────────── JSON Response            │
│                   ↓                                                          │
│            ┌──────────────────────────────────────┐                          │
│            │   Calendar State (normalized)        │                          │
│            │   ├── availableDates: string[]       │                          │
│            │   ├── slotsByDate: {date: slots[]}   │                          │
│            │   ├── defaultDate: string            │                          │
│            │   └── timezone: string               │                          │
│            └──────────────────────────────────────┘                          │
│                   ↓                                                          │
│            ┌──────────────────────────────────────┐                          │
│            │   UI Components                      │                          │
│            │   ├── Date Pills (first 5 dates)     │                          │
│            │   ├── Time Slot Grid                 │                          │
│            │   └── No-Availability Warning        │                          │
│            └──────────────────────────────────────┘                          │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## API Endpoints

### Calendar Endpoint (Admin)
```
GET /api/availability/calendar
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| provider_id | int | Yes | Provider ID |
| service_id | int | Yes | Service ID |
| days | int | No | Window size (default: 60) |
| start_date | string | No | Start date (YYYY-MM-DD) |
| exclude_appointment_id | int | No | Exclude existing appointment slots |

**Sample Response:**
```json
{
  "data": {
    "availableDates": ["2024-01-15", "2024-01-16", "2024-01-18"],
    "slotsByDate": {
      "2024-01-15": [
        {"start": "2024-01-15T09:00:00", "startFormatted": "09:00", "endFormatted": "10:00", "available": true},
        {"start": "2024-01-15T10:00:00", "startFormatted": "10:00", "endFormatted": "11:00", "available": true}
      ],
      "2024-01-16": [
        {"start": "2024-01-16T14:00:00", "startFormatted": "14:00", "endFormatted": "15:00", "available": true}
      ]
    },
    "default_date": "2024-01-15",
    "start_date": "2024-01-15",
    "end_date": "2024-03-15",
    "timezone": "America/New_York",
    "generated_at": "2024-01-14T10:30:00Z"
  }
}
```

### Calendar Endpoint (Public)
```
GET /public/booking/calendar
```

Same parameters and response structure as admin endpoint, but accessible without authentication.

## Backend Implementation

### AvailabilityService

**File:** `app/Services/AvailabilityService.php`

**Key Method:** `getCalendarAvailability()`

```php
public function getCalendarAvailability(
    int $providerId,
    int $serviceId,
    string $startDate = null,
    int $days = 60,
    ?int $excludeAppointmentId = null
): array
```

**Features:**
- 5-minute server-side cache (reduces DB load)
- Cache key sanitization (handles timezone slashes)
- DateTime/DateTimeImmutable compatibility
- Respects existing appointment exclusion for edit flows

**Cache Key Format:**
```
availability_calendar_{providerId}_{serviceId}_{date}_{days}_{sanitizedTimezone}_{buffer}_{excludeId}
```

### Controller Methods

**Admin API:** `app/Controllers/Api/Availability.php::calendar()`
**Public API:** `app/Controllers/PublicSite/BookingController.php::calendar()`

## Frontend Implementation

### Shared Module

**File:** `resources/js/modules/calendar/calendar-utils.js`

Provides common utilities for both admin and public interfaces:

```javascript
// Normalize API response
normalizeCalendarPayload(source)

// Get time string from slot
slotTimeValue(slot) // Returns "09:00"

// Get display label
slotLabel(slot) // Returns "09:00 - 10:00"

// Get slots for a specific date
getSlotsForDate(calendar, date)

// Format date for display
formatDateShort(dateStr) // Returns "Mon, Jan 15"

// Select best available date
selectAvailableDate(calendar, desiredDate)

// Generate cache key
buildCalendarCacheKey(providerId, serviceId, startDate, excludeId)
```

### Admin Implementation

**File:** `resources/js/modules/appointments/time-slots-ui.js`

```javascript
import { normalizeCalendarPayload, slotTimeValue, ... } from '../calendar/calendar-utils.js';

export function initTimeSlotsUI(options) {
  // Client-side cache (1 minute TTL)
  const calendarCache = new Map();
  
  // Fetch calendar data
  async function fetchCalendar(providerId, serviceId, startDate, forceRefresh)
  
  // Render clickable date pills
  function renderAvailableDatesHint(availableDates)
  
  // Auto-select first available date
  function ensureDateFromCalendar(calendar, desiredDate)
}
```

**UI Elements Added to form.php:**
```html
<div id="available-dates-hint" class="hidden mb-4">
  <p class="text-xs text-green-600 dark:text-green-400 mb-2 flex items-center gap-1">
    <span class="material-symbols-outlined text-sm">event_available</span>
    <span>Next available dates:</span>
  </p>
  <div id="available-dates-pills" class="flex flex-wrap gap-2"></div>
</div>
<div id="no-availability-warning" class="hidden mb-4 p-3 bg-yellow-50 ...">
  No availability found in the next 60 days...
</div>
```

### Public Booking Implementation

**File:** `resources/js/public-booking.js`

Features:
- Provider-specific services dropdown
- Calendar state management with `createCalendarState()`
- Date pills for quick date selection
- Slot selection with visual feedback

## Data Flow

### 1. Provider/Service Selection
```
User selects provider → fetchProviderServices() → Update service dropdown
User selects service → fetchCalendar() → Normalize response → Render date pills
```

### 2. Calendar Loading
```
fetchCalendar()
  ├── Check client cache (1 min TTL)
  ├── If miss: GET /api/availability/calendar
  ├── Server checks cache (5 min TTL)
  │   └── If miss: AvailabilityService.getCalendarAvailability()
  ├── Normalize response
  ├── Store in client cache
  └── Return normalized calendar
```

### 3. Date Selection
```
renderAvailableDatesHint(availableDates)
  ├── If no dates: Show warning, hide pills
  └── If dates exist:
      ├── Render first 5 as clickable pills
      ├── Show "+N more" indicator
      └── On pill click: Update date input, reload slots
```

### 4. Auto-Selection
```
loadSlots()
  ├── Fetch calendar
  ├── If date not set or invalid: selectAvailableDate()
  │   └── Returns first available or default_date
  ├── Update date input
  └── Render slot grid for selected date
```

## Caching Strategy

### Server-Side (AvailabilityService)
- **TTL:** 5 minutes
- **Scope:** Per provider/service/date/timezone combination
- **Invalidation:** Manual cache clear or TTL expiry

### Client-Side (JavaScript)
- **TTL:** 1 minute
- **Scope:** Per browser tab/session
- **Invalidation:** Page reload or TTL expiry

## UI/UX Specifications

### Date Pills
- Display first 5 available dates
- Format: "Mon, Jan 15" (short weekday, abbreviated month)
- Style: Green background, clickable, hover effect
- "+N more" indicator for additional dates

### No Availability State
- Yellow warning banner
- Clear messaging about 60-day window
- Suggests checking back later or contacting support

### Auto-Selection Behavior
- When provider/service changes: Clear date input
- When loading slots: Auto-select first available if no date set
- Preserve user selection if still available in new calendar

## Files Modified

### Backend
- `app/Services/AvailabilityService.php` - Core availability calculation
- `app/Controllers/Api/Availability.php` - Admin calendar endpoint
- `app/Controllers/PublicSite/BookingController.php` - Public calendar endpoint

### Frontend
- `resources/js/modules/calendar/calendar-utils.js` - Shared utilities (NEW)
- `resources/js/modules/appointments/time-slots-ui.js` - Admin time slots UI
- `resources/js/public-booking.js` - Public booking SPA
- `app/Views/appointments/form.php` - Admin form template

## Testing

### Manual Testing Checklist
- [ ] Select provider → service dropdown updates
- [ ] Select service → date pills appear
- [ ] Click date pill → slot grid updates
- [ ] No availability → warning shown
- [ ] Edit appointment → current time slot preserved
- [ ] Change provider → date clears, calendar reloads

### Edge Cases
- Provider with no services
- Service with no availability in 60-day window
- Timezone transitions (DST)
- Concurrent bookings consuming last slot

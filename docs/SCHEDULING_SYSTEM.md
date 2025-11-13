# Scheduling System Documentation

## Overview
The scheduling system dynamically calculates provider availability by combining all relevant business rules, provider-specific settings, and service requirements. It ensures appointments are only booked during valid time slots.

## Architecture

### Core Components

#### 1. AvailabilityService (`app/Services/AvailabilityService.php`)
Central service that combines all scheduling variables to calculate available time slots.

**Key Methods:**
- `getAvailableSlots($providerId, $date, $serviceId, $bufferMinutes, $timezone)` - Returns array of available time slots
- `isSlotAvailable($providerId, $startTime, $endTime, $timezone, $excludeAppointmentId)` - Validates if a specific slot is bookable
- `getProviderHoursForDate($providerId, $date)` - Gets working hours with breaks for a specific date
- `getBusyPeriods($providerId, $date)` - Collects all appointments and blocked times
- `generateCandidateSlots()` - Creates time slots respecting service duration, buffer time, and breaks
- `getBufferTime($providerId)` - Returns configured buffer minutes (0, 15, or 30)
- `isDateBlocked($date)` - Checks if date is a blocked period (holiday/closure)

**Features:**
- Timezone-aware calculations using DateTime and DateTimeZone
- Comprehensive logging for debugging
- Caching for blocked periods
- Conflict detection with detailed reasons
- Respects all scheduling constraints simultaneously

#### 2. Availability API Controller (`app/Controllers/Api/Availability.php`)
RESTful API endpoints for availability operations.

**Endpoints:**

##### GET `/api/availability/slots`
Get available time slots for a provider on a specific date.

**Parameters:**
- `provider_id` (required): Provider ID
- `date` (required): Date in Y-m-d format
- `service_id` (required): Service ID
- `buffer_minutes` (optional): Buffer time between appointments (0, 15, 30)
- `timezone` (optional): Timezone for calculations

**Response:**
```json
{
  "ok": true,
  "data": {
    "date": "2025-11-13",
    "provider_id": 2,
    "service_id": 1,
    "buffer_minutes": 15,
    "slots": [
      {
        "start": "09:00",
        "end": "10:00",
        "startTime": "2025-11-13T09:00:00+02:00",
        "endTime": "2025-11-13T10:00:00+02:00"
      }
    ],
    "total_slots": 8,
    "timezone": "Africa/Johannesburg"
  }
}
```

##### POST `/api/availability/check`
Check if a specific time slot is available.

**JSON Body:**
```json
{
  "provider_id": 2,
  "start_time": "2025-11-13 09:00:00",
  "end_time": "2025-11-13 10:00:00",
  "timezone": "Africa/Johannesburg",
  "exclude_appointment_id": 5
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "available": true,
    "conflicts": [],
    "reason": "",
    "checked_at": "2025-11-13T08:45:00+02:00"
  }
}
```

##### GET `/api/availability/summary`
Get availability summary for a provider across multiple days.

**Parameters:**
- `provider_id` (required): Provider ID
- `start_date` (required): Start date in Y-m-d format
- `end_date` (required): End date in Y-m-d format
- `service_id` (required): Service ID

**Response:**
```json
{
  "ok": true,
  "data": {
    "2025-11-13": {
      "total_slots": 8,
      "available_slots": 5,
      "working_day": true,
      "date": "2025-11-13",
      "day_of_week": "Wednesday"
    }
  }
}
```

##### GET `/api/availability/next-available`
Find the next available slot for a provider starting from a given date.

**Parameters:**
- `provider_id` (required): Provider ID
- `service_id` (required): Service ID
- `from_date` (optional): Start date (defaults to today)
- `days_ahead` (optional): Days to search (default 30, max 90)

**Response:**
```json
{
  "ok": true,
  "data": {
    "found": true,
    "slot": {
      "start": "09:00",
      "end": "10:00",
      "date": "2025-11-14",
      "startTime": "2025-11-14T09:00:00+02:00",
      "endTime": "2025-11-14T10:00:00+02:00"
    },
    "days_from_now": 1,
    "searched_until": "2025-11-14"
  }
}
```

### Database Tables

#### xs_business_hours
Weekly working hours per provider with breaks.

```sql
provider_id INT
weekday INT (0-6, Sunday=0)
start_time TIME
end_time TIME
breaks_json TEXT -- JSON array: [{"start": "12:00", "end": "13:00"}]
```

#### xs_provider_schedules
Day-specific provider schedules (overrides business_hours).

```sql
provider_id INT
day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')
start_time TIME
end_time TIME
break_start TIME
break_end TIME
is_active TINYINT (default 1)
```

#### xs_blocked_times
Blocked time periods (provider-specific or global).

```sql
id INT PRIMARY KEY
provider_id INT NULL -- NULL = global block
start_time DATETIME
end_time DATETIME
reason VARCHAR(255)
```

#### xs_settings
System-wide settings including buffer time.

```sql
setting_key VARCHAR(100) UNIQUE
setting_value TEXT
setting_type VARCHAR(20)
```

**Key Settings:**
- `business.buffer_time` (integer): Buffer minutes between appointments (0, 15, or 30)
- `business.blocked_periods` (JSON): Array of blocked date ranges (holidays/closures)

### Scheduling Logic Flow

#### 1. Slot Generation
```
1. Get date and provider → Determine working hours
2. Check provider_schedules for specific day → Use if exists
3. Fallback to business_hours for weekday → Use if provider_schedules not found
4. Extract breaks from provider_schedules or business_hours.breaks_json
5. Generate candidate slots from start to end time:
   - Slot duration = service.duration_min
   - Skip break periods
   - Add buffer_time between slots
6. Return array of valid time slots
```

#### 2. Availability Validation
```
1. Check if date is blocked (holidays/closures) → REJECT if true
2. Get provider working hours for date → REJECT if no hours
3. Check if slot falls within working hours → REJECT if outside
4. Check if slot overlaps with break times → REJECT if overlaps
5. Get all busy periods (appointments + blocked_times) for date
6. Check if slot overlaps with any busy period → REJECT if conflicts
7. Apply buffer time before/after existing appointments
8. Return available=true if all checks pass
```

#### 3. Appointment Booking
```
1. Validate input (provider, service, customer, times)
2. Call AvailabilityService->isSlotAvailable()
3. If not available → Redirect with error message
4. If available → Create appointment in database
5. Log success and redirect with confirmation
```

## Scheduling Variables

### 1. Business Hours (Global)
Default working hours per weekday for each provider.
- Stored in `xs_business_hours`
- Includes multiple breaks per day (JSON array)
- Used when no provider-specific schedule exists

### 2. Provider Schedules (Specific)
Day-specific working hours that override business hours.
- Stored in `xs_provider_schedules`
- One break period per day
- Can be enabled/disabled with `is_active`
- Takes precedence over business_hours

### 3. Blocked Periods (Global)
Holidays, closures, or other unavailable dates.
- Stored in `xs_settings` as `business.blocked_periods` JSON
- Format: `[{"start": "2025-12-25", "end": "2025-12-26", "reason": "Christmas"}]`
- Applies to all providers

### 4. Blocked Times (Provider-Specific or Global)
Specific time periods that are unavailable.
- Stored in `xs_blocked_times`
- Can be provider-specific (`provider_id` set) or global (`provider_id` NULL)
- Datetime ranges with reason
- Examples: vacation, meetings, maintenance

### 5. Break Times
Periods within working hours where bookings are not allowed.
- Stored in `xs_business_hours.breaks_json` (multiple breaks)
- Or in `xs_provider_schedules` (break_start, break_end) (single break)
- Examples: lunch break, team meetings

### 6. Buffer Time
Spacing between appointments for preparation/cleanup.
- Stored in `xs_settings` as `business.buffer_time`
- Values: 0, 15, or 30 minutes
- Applied between all appointments
- Can be overridden per-request in API

### 7. Service Duration
How long each service takes.
- Stored in `xs_services.duration_min`
- Determines slot length
- Affects end time calculation

### 8. Existing Appointments
Already booked time slots.
- Stored in `xs_appointments`
- Creates busy periods that block availability
- Checked with buffer time before/after

## Integration Points

### Appointments Controller
`app/Controllers/Appointments.php`

**store() Method:**
```php
// Validate availability before creating appointment
$availabilityService = new \App\Services\AvailabilityService();
$availabilityCheck = $availabilityService->isSlotAvailable(
    $providerId,
    $startTimeUtc,
    $endTimeUtc,
    $clientTimezone
);

if (!$availabilityCheck['available']) {
    return redirect()->back()
        ->withInput()
        ->with('error', 'This time slot is not available. ' . $availabilityCheck['reason']);
}
```

### Frontend Integration (TODO)
`resources/js/modules/scheduler/*.js`

**Fetch Available Slots:**
```javascript
async function loadAvailableSlots(providerId, date, serviceId) {
  const response = await fetch(`/api/availability/slots?provider_id=${providerId}&date=${date}&service_id=${serviceId}`);
  const data = await response.json();
  return data.data.slots;
}
```

**Check Specific Slot:**
```javascript
async function validateSlot(providerId, startTime, endTime) {
  const response = await fetch('/api/availability/check', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      provider_id: providerId,
      start_time: startTime,
      end_time: endTime,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
    })
  });
  const data = await response.json();
  return data.data.available;
}
```

## Routes Configuration

**File:** `app/Config/Routes.php`

```php
// Availability API - Comprehensive slot availability calculation
$routes->get('availability/slots', 'Api\\Availability::slots');
$routes->post('availability/check', 'Api\\Availability::check');
$routes->get('availability/summary', 'Api\\Availability::summary');
$routes->get('availability/next-available', 'Api\\Availability::nextAvailable');
```

## Testing

### Test Scenarios

1. **Basic Availability**
   - Provider has business hours 9:00-17:00
   - Service duration: 60 minutes
   - Expected: Slots from 9:00-10:00, 10:00-11:00, etc.

2. **With Breaks**
   - Business hours: 9:00-17:00
   - Break: 12:00-13:00 (lunch)
   - Expected: Slots skip 12:00-13:00 period

3. **With Buffer Time**
   - Buffer time: 15 minutes
   - Appointment at 10:00-11:00
   - Expected: Next slot starts at 11:15 (not 11:00)

4. **Blocked Periods**
   - Holiday: 2025-12-25
   - Expected: No slots available on that date

5. **Provider-Specific Schedule**
   - Monday: 9:00-13:00 (half day)
   - Expected: Slots only until 13:00 on Mondays

6. **Existing Appointments**
   - Appointment: 14:00-15:00
   - Expected: 14:00-15:00 slot not available

7. **Blocked Times**
   - Provider vacation: 2025-11-20 to 2025-11-22
   - Expected: No slots during vacation period

### Manual Testing

#### Test GET /api/availability/slots
```bash
curl "http://localhost/api/availability/slots?provider_id=2&date=2025-11-13&service_id=1"
```

#### Test POST /api/availability/check
```bash
curl -X POST http://localhost/api/availability/check \
  -H "Content-Type: application/json" \
  -d '{
    "provider_id": 2,
    "start_time": "2025-11-13 09:00:00",
    "end_time": "2025-11-13 10:00:00"
  }'
```

#### Test GET /api/availability/summary
```bash
curl "http://localhost/api/availability/summary?provider_id=2&start_date=2025-11-13&end_date=2025-11-20&service_id=1"
```

#### Test GET /api/availability/next-available
```bash
curl "http://localhost/api/availability/next-available?provider_id=2&service_id=1&days_ahead=30"
```

## Future Enhancements

### 1. Frontend Calendar Integration
- Update calendar to fetch only available slots
- Gray out unavailable times
- Show real-time availability
- Prevent selection of invalid slots

### 2. Settings UI
- Add buffer time dropdown in settings page
- UI for managing blocked periods
- Per-provider buffer time settings

### 3. Advanced Features
- Recurring blocked times (e.g., team meeting every Monday)
- Multi-provider appointments
- Resource booking (rooms, equipment)
- Waitlist for unavailable slots
- Smart suggestions for alternative slots

### 4. Performance Optimizations
- Cache calculated slots per provider/date
- Eager load related data with joins
- Index optimization for date range queries
- Background calculation for popular providers

### 5. Notifications
- Email notifications when slots become available
- Provider notifications of new bookings
- Customer reminders with buffer time considered

## Troubleshooting

### No Slots Available
**Possible Causes:**
1. Provider has no business_hours or provider_schedules configured
2. Date is blocked (holiday/closure)
3. All slots are booked
4. Break time covers entire working hours
5. Service duration longer than available time

**Debug Steps:**
1. Check provider_schedules and business_hours tables
2. Verify blocked_periods setting
3. Check existing appointments for date
4. Review break times configuration
5. Check service duration vs working hours

### Slots Not Respecting Buffer Time
**Possible Causes:**
1. buffer_time setting not configured
2. Buffer time not passed to API
3. AvailabilityService not using buffer correctly

**Debug Steps:**
1. Check xs_settings for business.buffer_time
2. Review API call parameters
3. Check AvailabilityService logs
4. Verify generateCandidateSlots logic

### Double Bookings
**Possible Causes:**
1. Appointments controller not calling isSlotAvailable()
2. Race condition (simultaneous bookings)
3. Timezone mismatch

**Debug Steps:**
1. Check Appointments::store() has validation
2. Add database-level unique constraints
3. Verify timezone handling in both validation and insert

## Implementation Checklist

- [x] Create AvailabilityService.php
- [x] Create Api/Availability.php controller
- [x] Add API routes in Routes.php
- [x] Add buffer_time to xs_settings table
- [x] Integrate isSlotAvailable() in Appointments::store()
- [ ] Update frontend calendar to use /api/availability/slots
- [ ] Add buffer_time UI to settings page
- [ ] Create comprehensive test dataset
- [ ] End-to-end testing
- [ ] Performance testing with large datasets
- [ ] Documentation review and updates

## References

- AvailabilityService: `app/Services/AvailabilityService.php`
- Availability API: `app/Controllers/Api/Availability.php`
- Routes: `app/Config/Routes.php` (lines 187-190)
- Appointments Controller: `app/Controllers/Appointments.php`
- Database Models:
  - `app/Models/BusinessHourModel.php`
  - `app/Models/ProviderScheduleModel.php`
  - `app/Models/BlockedTimeModel.php`
  - `app/Models/AppointmentModel.php`
  - `app/Models/ServiceModel.php`

## Change Log

### 2025-11-13 - Initial Implementation
- Created AvailabilityService with comprehensive slot calculation
- Added 4 API endpoints (slots, check, summary, next-available)
- Added buffer_time setting to database (default: 0)
- Integrated availability validation in appointment booking
- Added routes for all availability endpoints
- Documentation created

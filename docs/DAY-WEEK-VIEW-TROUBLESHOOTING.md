# Day/Week View Troubleshooting Guide

## Issue: Appointments Not Showing in Day/Week Views

### Symptoms
- ✅ Month view shows appointments (as dots/pills on dates)
- ❌ Day view shows empty time slots
- ❌ Week view shows empty time slots
- OR appointments appear at wrong times (e.g., midnight instead of scheduled time)

---

## Root Cause Checklist

### 1. **Database Data Issues** ⚠️ MOST COMMON

#### Check if appointments exist:
```sql
-- Show all appointments with their times
SELECT 
    id,
    provider_id,
    service_id,
    customer_id,
    start_time,
    end_time,
    status,
    DATE(start_time) as appt_date,
    TIME(start_time) as appt_start,
    TIME(end_time) as appt_end
FROM xs_appointments
WHERE start_time >= CURDATE()
ORDER BY start_time
LIMIT 10;
```

#### Expected Output:
```
| id | provider_id | service_id | start_time          | end_time            | status    |
|----|-------------|------------|---------------------|---------------------|-----------|
| 1  | 1           | 2          | 2025-10-03 10:00:00 | 2025-10-03 11:00:00 | confirmed |
| 2  | 2           | 1          | 2025-10-03 14:00:00 | 2025-10-03 15:30:00 | booked    |
```

#### Common Issues:
- ❌ **NULL start_time/end_time**: Appointments won't render
- ❌ **All times are 00:00:00**: Will show at midnight only
- ❌ **Old dates**: Won't appear in current calendar view
- ❌ **Invalid dates**: e.g., "0000-00-00 00:00:00"

#### Fix NULL or zero times:
```sql
-- Update appointments with missing times (set to 9 AM - 10 AM)
UPDATE xs_appointments
SET 
    start_time = CONCAT(DATE(created_at), ' 09:00:00'),
    end_time = CONCAT(DATE(created_at), ' 10:00:00')
WHERE start_time IS NULL 
   OR start_time = '0000-00-00 00:00:00'
   OR TIME(start_time) = '00:00:00';
```

---

### 2. **Provider/Service Filter Issues**

#### Symptoms:
- API returns empty array even though appointments exist
- Filter dropdowns show "All providers" / "All services"
- Calendar shows "No appointments"

#### Check Filters in Browser Console:
```javascript
// Check current filter state
console.log('Filters:', {
  provider: document.getElementById('scheduler-filter-provider')?.value,
  service: document.getElementById('scheduler-filter-service')?.value
});

// Check API request URL
// Look for Network tab → appointments → Request URL
// Should look like: /api/v1/appointments?start=2025-10-01&end=2025-10-31
// NOT: /api/v1/appointments?providerId=999&serviceId=888&start=...
```

#### Fix:
1. **Clear filters** - Click "Clear Filters" button
2. **Select "All" from dropdowns** (top option)
3. **Refresh calendar** - Click refresh button

#### Verify API returns data:
```bash
# Test API endpoint directly (no filters)
curl "http://localhost:8081/api/v1/appointments?start=2025-10-01&end=2025-10-31"

# Should return JSON with appointments array
{
  "data": [
    {
      "id": 1,
      "start": "2025-10-03 10:00:00",
      "end": "2025-10-03 11:00:00",
      ...
    }
  ]
}
```

---

### 3. **Datetime Parsing/Timezone Issues**

#### Symptoms:
- Appointments show 4-6 hours off from scheduled time
- 10:00 AM appointment shows at 2:00 PM or 6:00 AM
- Console shows: `parsed: "2025-10-03T14:00:00.000Z"` but local is "10:00"

#### Check Browser Console:
```javascript
[scheduler] Parsed datetime: {
  original: "2025-10-03 10:00:00",
  iso: "2025-10-03T10:00:00",
  parsed: "2025-10-03T14:00:00.000Z",  // ❌ UTC = 10:00 EDT (wrong!)
  localTime: "10:00:00 AM"              // ✅ Local time (correct)
}
```

#### Root Cause:
JavaScript `new Date("2025-10-03T10:00:00")` **without timezone** is parsed as UTC, not local time.

#### Solution:
Add timezone offset to datetime string:

```javascript
// WRONG (parsed as UTC):
new Date("2025-10-03T10:00:00")

// RIGHT (parsed as local time):
new Date("2025-10-03T10:00:00-04:00")  // EDT
new Date("2025-10-03T10:00:00-05:00")  // EST
```

#### Fix in `scheduler-dashboard.js`:
```javascript
function normalizeEventDateTime(value) {
  if (!value) return null;
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) return null;

    // Convert MySQL format to ISO-8601 with local timezone
    let candidate = trimmed.includes(' ') && !trimmed.includes('T')
      ? trimmed.replace(' ', 'T')
      : trimmed;
    
    // Add timezone offset if not present
    if (!candidate.includes('+') && !candidate.includes('Z')) {
      const offset = -(new Date().getTimezoneOffset());
      const hours = Math.floor(Math.abs(offset) / 60);
      const minutes = Math.abs(offset) % 60;
      const sign = offset >= 0 ? '+' : '-';
      const tz = `${sign}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
      candidate += tz;
    }

    const parsed = new Date(candidate);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }
  // ... rest of function
}
```

---

### 4. **FullCalendar Configuration Issues**

#### Check Calendar Options:
```javascript
// In browser console
window.__CALENDAR_INSTANCE__?.getOption('slotMinTime')  // Should be "00:00:00" or business hours start
window.__CALENDAR_INSTANCE__?.getOption('slotMaxTime')  // Should be "24:00:00" or business hours end
window.__CALENDAR_INSTANCE__?.getOption('scrollTime')   // Scroll position (e.g., "08:00:00")
```

#### Common Issues:
- ❌ `slotMinTime: "09:00"` but appointment at "08:00" → won't show
- ❌ `slotMaxTime: "17:00"` but appointment at "18:00" → won't show
- ❌ Business hours restricting visible range

#### Fix:
```javascript
{
  slotMinTime: "00:00:00",  // Show full day
  slotMaxTime: "24:00:00",
  scrollTime: "08:00:00",   // Scroll to 8 AM on load
  businessHours: false,     // Don't restrict to business hours
}
```

---

### 5. **Event Mapping Issues**

#### Check Mapped Events:
```javascript
// In browser console
const events = window.__CALENDAR_INSTANCE__?.getEvents();
console.table(events.map(e => ({
  id: e.id,
  title: e.title,
  start: e.start?.toISOString(),
  end: e.end?.toISOString(),
  allDay: e.allDay
})));
```

#### Expected Output:
```
| id  | title       | start                    | end                      | allDay |
|-----|-------------|--------------------------|--------------------------|--------|
| "1" | "Haircut"   | "2025-10-03T10:00:00..." | "2025-10-03T11:00:00..." | false  |
| "2" | "Massage"   | "2025-10-03T14:00:00..." | "2025-10-03T15:30:00..." | false  |
```

#### Common Issues:
- ❌ `allDay: true` → Shows as all-day banner, not in time slot
- ❌ `start: null` → Event won't render
- ❌ `start` is a string, not Date object → FullCalendar error

#### Fix in `mapAppointmentToEvent`:
```javascript
function mapAppointmentToEvent(item) {
  const normalizedStart = normalizeEventDateTime(item.start);
  const normalizedEnd = normalizeEventDateTime(item.end);
  
  // Debug invalid datetimes
  if (!normalizedStart || !normalizedEnd) {
    console.error('[scheduler] Invalid datetime:', {
      id: item.id,
      start: item.start,
      end: item.end
    });
    return null;  // Don't add invalid events
  }
  
  return {
    id: String(item.id ?? ''),
    title: item.title || 'Appointment',
    start: normalizedStart,  // MUST be Date object
    end: normalizedEnd,      // MUST be Date object
    allDay: false,           // Force timed event
    extendedProps: {
      status: (item.status || '').toLowerCase(),
      raw: item,
    },
  };
}

// In event source, filter out null events:
const events = Array.isArray(items) 
  ? items.map(mapAppointmentToEvent).filter(Boolean)
  : [];
```

---

## Diagnostic Workflow

### Step 1: Check Database
```sql
SELECT COUNT(*) as total,
       COUNT(CASE WHEN start_time IS NULL THEN 1 END) as null_start,
       COUNT(CASE WHEN TIME(start_time) = '00:00:00' THEN 1 END) as midnight,
       COUNT(CASE WHEN start_time >= CURDATE() THEN 1 END) as future
FROM xs_appointments;
```

### Step 2: Check API Response
```bash
# Open browser DevTools → Network tab
# Filter: "appointments"
# Check Response tab
```

Expected:
```json
{
  "data": [
    {
      "id": 1,
      "start": "2025-10-03 10:00:00",  // ✅ Has time component
      "end": "2025-10-03 11:00:00",
      "providerId": 1,
      "serviceId": 2
    }
  ]
}
```

### Step 3: Check Console Logs
```javascript
// Look for:
[scheduler] Parsed datetime: { ... }
[scheduler] Sample appointment data: { ... }
[scheduler] Sample mapped event: { ... }
[scheduler] Event mounted: { ... }
```

### Step 4: Inspect Calendar Events
```javascript
window.__CALENDAR_INSTANCE__?.getEvents()
```

### Step 5: Check Event Elements
```javascript
// Open DevTools → Elements tab
// Search for: fc-timegrid-event
// Should see: <a class="fc-timegrid-event ...">
```

---

## Quick Fixes

### Fix 1: Reset Filters
```javascript
document.getElementById('scheduler-filter-provider').value = '';
document.getElementById('scheduler-filter-service').value = '';
document.getElementById('scheduler-apply').click();
```

### Fix 2: Force Calendar Refresh
```javascript
window.__CALENDAR_INSTANCE__?.refetchEvents();
```

### Fix 3: Verify Event Data
```javascript
// Fetch appointments directly
fetch('/api/v1/appointments?start=2025-10-01&end=2025-10-31')
  .then(r => r.json())
  .then(d => console.table(d.data));
```

### Fix 4: Test Datetime Parsing
```javascript
const test = "2025-10-03 10:00:00";
const converted = test.replace(' ', 'T');
const parsed = new Date(converted);
console.log({
  original: test,
  converted,
  parsed: parsed.toISOString(),
  local: parsed.toLocaleTimeString(),
  valid: !isNaN(parsed.getTime())
});
```

---

## Expected vs Actual

### ✅ Working Calendar (Day View)
```
08:00 ┌─────────────────────────┐
      │                         │
09:00 ├─────────────────────────┤
      │                         │
10:00 ├─────────────────────────┤
      │ 🟢 John Doe - Haircut   │ ← Event at 10:00
11:00 ├─────────────────────────┤
      │                         │
12:00 ├─────────────────────────┤
```

### ❌ Broken Calendar (Day View)
```
08:00 ┌─────────────────────────┐
      │                         │
09:00 ├─────────────────────────┤
      │                         │
10:00 ├─────────────────────────┤
      │                         │ ← Empty (should show event)
11:00 ├─────────────────────────┤
      │                         │
12:00 ├─────────────────────────┤
```

---

## Still Not Working?

### Create Test Appointment
```sql
INSERT INTO xs_appointments (
    provider_id,
    service_id,
    customer_id,
    user_id,
    start_time,
    end_time,
    status,
    created_at,
    updated_at
) VALUES (
    1,  -- provider_id (must exist in xs_users)
    1,  -- service_id (must exist in xs_services)
    1,  -- customer_id (must exist in xs_customers)
    1,  -- user_id
    '2025-10-03 14:00:00',  -- 2 PM today
    '2025-10-03 15:00:00',  -- 3 PM today
    'confirmed',
    NOW(),
    NOW()
);
```

### Verify It Shows:
1. Refresh calendar page
2. Switch to **Day** view
3. Navigate to **today** (October 3, 2025)
4. Should see appointment at **2:00 PM**

---

## Success Criteria

✅ **Day View**: Events appear in correct hour slots
✅ **Week View**: Events appear on correct day + hour
✅ **Month View**: Events appear on correct dates
✅ **Console**: No errors, shows "Parsed datetime" logs
✅ **API**: Returns appointments with proper `start`/`end` times
✅ **Database**: All appointments have valid `start_time`/`end_time`

---

**Last Updated**: October 3, 2025  
**Related**: DAY-WEEK-VIEW-DIAGNOSTIC.md

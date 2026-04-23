# Day/Week View Diagnostic Guide

## Issue Description
Appointments are not rendering in the correct time slots in Day and Week views.

## Root Cause Analysis

### Expected Behavior
- **Database**: Stores times as `YYYY-MM-DD HH:MM:SS` (e.g., `2025-10-03 10:00:00`)
- **API**: Returns `start` and `end` in same format
- **JavaScript**: Must convert to ISO-8601 (`2025-10-03T10:00:00`) for FullCalendar
- **FullCalendar**: Renders events in correct time slots based on ISO datetime

### Data Flow

```
Database (xs_appointments)
    start_time: "2025-10-03 10:00:00"
    end_time:   "2025-10-03 11:00:00"
           ↓
API (/api/v1/appointments)
    { 
      start: "2025-10-03 10:00:00",
      end: "2025-10-03 11:00:00"
    }
           ↓
JavaScript (normalizeEventDateTime)
    Converts: "2025-10-03 10:00:00" → "2025-10-03T10:00:00"
    Parses: new Date("2025-10-03T10:00:00")
    Returns: Date object
           ↓
FullCalendar (mapAppointmentToEvent)
    {
      start: Date(2025-10-03T10:00:00),
      end: Date(2025-10-03T11:00:00)
    }
           ↓
Calendar Rendering
    Day View:   Shows at 10:00 AM
    Week View:  Shows at 10:00 AM on Oct 3
    Month View: Shows on Oct 3
```

---

## Diagnostic Steps

### Step 1: Check Browser Console
1. Open `/scheduler` in your browser
2. Open DevTools (F12) → Console tab
3. Look for log messages starting with `[scheduler]`

### Expected Console Output:
```javascript
[scheduler] Parsed datetime: {
  original: "2025-10-03 10:00:00",
  iso: "2025-10-03T10:00:00",
  parsed: "2025-10-03T14:00:00.000Z",  // UTC representation
  localTime: "10:00:00 AM"
}
```

### Step 2: Inspect Event Data
1. Switch to **Day** or **Week** view
2. In Console, type: `window.__CALENDAR_INSTANCE__?.getEvents()`
3. Check the `start` and `end` properties

### Expected Event Structure:
```javascript
{
  id: "123",
  title: "Appointment",
  start: Date Wed Oct 03 2025 10:00:00 GMT-0400,  // Actual Date object
  end: Date Wed Oct 03 2025 11:00:00 GMT-0400,
  extendedProps: { ... }
}
```

### Step 3: Verify API Response
1. Open Network tab in DevTools
2. Filter by `appointments`
3. Click on the request
4. Check Response tab

### Expected API Response:
```json
{
  "data": [
    {
      "id": 1,
      "start": "2025-10-03 10:00:00",
      "end": "2025-10-03 11:00:00",
      "status": "confirmed",
      ...
    }
  ]
}
```

---

## Common Issues & Fixes

### Issue 1: Events appear at wrong time (e.g., 10:00 shows at 02:00)
**Cause**: Timezone mismatch - datetime being parsed as UTC instead of local time

**Fix**: Add timezone suffix to datetime string
```javascript
// WRONG: Parsed as UTC
new Date("2025-10-03T10:00:00")  // → 10:00 UTC = 06:00 EDT

// RIGHT: Parsed as local time
new Date("2025-10-03T10:00:00-04:00")  // → 10:00 EDT
```

**Solution**:
```javascript
// In normalizeEventDateTime function
const candidate = trimmed.includes(' ') && !trimmed.includes('T')
  ? trimmed.replace(' ', 'T') + getLocalTimezoneOffset()  // Add timezone
  : trimmed;

function getLocalTimezoneOffset() {
  const offset = -(new Date().getTimezoneOffset());
  const hours = Math.floor(Math.abs(offset) / 60);
  const minutes = Math.abs(offset) % 60;
  const sign = offset >= 0 ? '+' : '-';
  return `${sign}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}
```

### Issue 2: Events show at 00:00 (midnight)
**Cause**: Datetime parsing failed, FullCalendar uses date-only

**Check Console For**:
```javascript
[scheduler] Failed to parse datetime: {
  original: "2025-10-03 10:00:00",
  parsed: Invalid Date
}
```

**Solution**: Verify space → 'T' conversion is happening

### Issue 3: Events don't appear at all
**Cause**: `start` or `end` is null after normalization

**Check Console For**:
```javascript
[scheduler] Invalid datetime for appointment: {
  id: 123,
  raw_start: "2025-10-03 10:00:00",
  normalized_start: null
}
```

**Solution**: Check `normalizeEventDateTime` logic

### Issue 4: API returns null/empty start_time
**Cause**: Database column is NULL

**Fix**: Update database:
```sql
UPDATE xs_appointments 
SET start_time = CONCAT(DATE(created_at), ' 09:00:00')
WHERE start_time IS NULL;
```

---

## Testing Checklist

### ✅ Pre-Test Verification
- [ ] Build succeeded (`npm run build`)
- [ ] PHP server running (`php spark serve`)
- [ ] Database has appointments with valid `start_time`/`end_time`

### ✅ Browser Testing
- [ ] Open `/scheduler` (logged in)
- [ ] Calendar loads without errors
- [ ] Switch to **Day** view
- [ ] Verify appointments show at correct times
- [ ] Switch to **Week** view
- [ ] Verify appointments show at correct times
- [ ] Switch to **Month** view
- [ ] Verify appointments show on correct dates

### ✅ Console Verification
- [ ] No errors in console
- [ ] See `[scheduler] Parsed datetime` logs
- [ ] Each log shows correct `localTime`
- [ ] `window.__CALENDAR_INSTANCE__?.getEvents()` returns valid dates

---

## Quick Test Script

Paste this into browser console when calendar is loaded:

```javascript
// Test datetime normalization
function testDatetimeNormalization() {
  const testCases = [
    "2025-10-03 10:00:00",  // MySQL format
    "2025-10-03T10:00:00",  // ISO format
    "2025-10-03 14:30:00",  // Afternoon
    "2025-10-03 08:00:00",  // Morning
  ];
  
  console.group('Datetime Normalization Tests');
  testCases.forEach(input => {
    const normalized = input.includes(' ') && !input.includes('T')
      ? input.replace(' ', 'T')
      : input;
    const parsed = new Date(normalized);
    console.log({
      input,
      normalized,
      parsed: parsed.toISOString(),
      localTime: parsed.toLocaleTimeString(),
      valid: !isNaN(parsed.getTime())
    });
  });
  console.groupEnd();
}

// Test calendar events
function testCalendarEvents() {
  const calendar = window.__CALENDAR_INSTANCE__;
  if (!calendar) {
    console.error('Calendar not initialized');
    return;
  }
  
  const events = calendar.getEvents();
  console.group(`Calendar Events (${events.length} total)`);
  events.forEach(event => {
    console.log({
      id: event.id,
      title: event.title,
      start: {
        iso: event.start?.toISOString(),
        local: event.start?.toLocaleString()
      },
      end: {
        iso: event.end?.toISOString(),
        local: event.end?.toLocaleString()
      }
    });
  });
  console.groupEnd();
}

// Run tests
testDatetimeNormalization();
testCalendarEvents();
```

---

## Expected Results

### ✅ Success Indicators
1. **Console shows parsed datetimes** with correct local times
2. **Day view** renders appointments in correct hour slots
3. **Week view** renders appointments in correct day + hour slots
4. **No "Invalid datetime" warnings** in console
5. **Events array** contains valid Date objects (not null)

### ❌ Failure Indicators
1. **All appointments at 00:00** (midnight)
2. **Appointments 4-6 hours off** (timezone issue)
3. **"Failed to parse datetime"** errors in console
4. **Events array** has null `start`/`end`
5. **Calendar shows "No appointments"** despite data

---

## Debugging Commands

```javascript
// Get calendar instance
window.__CALENDAR_INSTANCE__

// Get all events
window.__CALENDAR_INSTANCE__?.getEvents()

// Get current view
window.__CALENDAR_INSTANCE__?.view

// Check timezone
Intl.DateTimeFormat().resolvedOptions().timeZone

// Test date parsing
new Date("2025-10-03T10:00:00")

// Get locale time offset
new Date().getTimezoneOffset()  // Minutes from UTC (negative for EDT)
```

---

## Next Steps

### If appointments still show at wrong times:
1. Check console logs for timezone offset
2. Verify database `start_time` values
3. Test API response format
4. Add timezone suffix to ISO string

### If appointments don't show at all:
1. Check for null `start`/`end` in events array
2. Verify API returns data
3. Check `mapAppointmentToEvent` function
4. Ensure calendar has events source

---

**Last Updated**: October 3, 2025  
**Status**: Diagnostic mode active (enhanced logging enabled)

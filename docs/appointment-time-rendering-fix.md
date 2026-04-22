# Appointment Time Rendering Fix

**Issue:** Appointments not rendering in correct time slots in week/day views  
**Date:** October 2, 2025  
**Status:** 🔧 In Progress - Debugging

---

## Problem Description

### Symptoms
- All appointments appear in 08:00-09:00 time slot in week and day views
- Actual appointment times from database are ignored
- Month view may display correctly (date-only rendering)
- Makes schedule unreliable and unusable

### Expected Behavior
- Appointment at 14:00-15:00 in database should render at 14:00-15:00 in calendar
- Time slots should match database values exactly
- No timezone shifts or default time application

---

## Investigation Steps

### 1. Data Flow Analysis

**Database → API → Frontend → FullCalendar**

```
MySQL Database
└─ appointments table
   ├─ start_time: DATETIME (e.g., "2025-10-02 14:00:00")
   └─ end_time: DATETIME (e.g., "2025-10-02 15:00:00")
          ↓
API Controller (Appointments.php)
└─ Returns: { start: "2025-10-02 14:00:00", end: "2025-10-02 15:00:00" }
          ↓
Scheduler Service (scheduler-service.js)
└─ getAppointments() → unwraps API response
          ↓
Dashboard (scheduler-dashboard.js)
└─ normalizeEventDateTime() → converts to ISO 8601
   └─ "2025-10-02 14:00:00" → "2025-10-02T14:00:00"
          ↓
FullCalendar
└─ Interprets datetime and renders in time slot
```

### 2. Code Locations

#### A. Database Schema
**File:** `app/Database/Migrations/2025-07-13-120300_CreateAppointmentsTable.php`
```php
'start_time' => [
    'type' => 'DATETIME',
    'null' => false,
],
'end_time' => [
    'type' => 'DATETIME',
    'null' => false,
],
```

#### B. API Controller
**File:** `app/Controllers/Api/Appointments.php`
```php
$events = array_map(function($appointment) {
    return [
        'id' => $appointment['id'],
        'title' => $appointment['customer_name'] ?? 'Appointment #' . $appointment['id'],
        'start' => $appointment['start_time'], // From DB: "2025-10-02 14:00:00"
        'end' => $appointment['end_time'],     // From DB: "2025-10-02 15:00:00"
        // ... other fields
    ];
}, $appointments);
```

#### C. Frontend Normalization
**File:** `resources/js/scheduler-dashboard.js`
```javascript
function normalizeEventDateTime(value) {
  if (!value) return null;
  if (typeof value === 'string') {
    const trimmed = value.trim();
    // Convert "2025-10-02 14:00:00" → "2025-10-02T14:00:00"
    if (trimmed.includes(' ') && !trimmed.includes('T')) {
      return trimmed.replace(' ', 'T');
    }
    return trimmed;
  }
  return null;
}
```

---

## Debugging Measures Implemented

### Debug Logging Added

**Location 1:** Event fetching
```javascript
// In eventSources callback
if (Array.isArray(items) && items.length > 0) {
  console.log('[scheduler] Sample appointment data:', {
    raw: items[0],
    startStr: info.startStr,
    endStr: info.endStr
  });
}
```

**Location 2:** Event mapping
```javascript
// After mapping
if (events.length > 0) {
  console.log('[scheduler] Sample mapped event:', events[0]);
}
```

**Location 3:** Invalid datetime detection
```javascript
// In mapAppointmentToEvent
if (!normalizedStart || !normalizedEnd) {
  console.warn('[scheduler] Invalid datetime for appointment:', {
    id: item.id,
    raw_start: item.start,
    raw_end: item.end,
    normalized_start: normalizedStart,
    normalized_end: normalizedEnd
  });
}
```

### How to Use Debug Logs

1. Open browser DevTools (F12)
2. Go to Console tab
3. Load calendar in week or day view
4. Look for log entries:
   - `[scheduler] Sample appointment data:` - Shows raw API response
   - `[scheduler] Sample mapped event:` - Shows after normalization
   - `[scheduler] Invalid datetime` - Shows if datetime is null/invalid

### Expected Console Output

**Healthy:**
```javascript
[scheduler] Sample appointment data: {
  raw: {
    id: 123,
    start: "2025-10-02 14:00:00",
    end: "2025-10-02 15:00:00",
    status: "booked",
    // ...
  },
  startStr: "2025-09-29",
  endStr: "2025-10-06"
}

[scheduler] Sample mapped event: {
  id: "123",
  title: "John Smith",
  start: "2025-10-02T14:00:00",  // ✅ Proper ISO format with time
  end: "2025-10-02T15:00:00",
  extendedProps: { ... }
}
```

**Problematic:**
```javascript
// Missing time component
start: "2025-10-02"  // ❌ Date only, no time

// Wrong time
start: "2025-10-02T08:00:00"  // ❌ All defaulting to 08:00

// Null values
start: null  // ❌ No datetime at all
```

---

## Potential Root Causes

### 1. Database Data Issue ❓
**Hypothesis:** Database might have all appointments at 08:00  
**Check:**
```sql
SELECT id, start_time, end_time, status 
FROM xs_appointments 
LIMIT 10;
```
**Expected:** Varied times (09:00, 10:30, 14:00, etc.)  
**Problematic:** All start_time showing 08:00:00

**Fix if true:** Update database with correct times

### 2. API Response Format Issue ❓
**Hypothesis:** CodeIgniter might be formatting datetimes incorrectly  
**Check:** Look at console log for raw API response  
**Expected:** `"2025-10-02 14:00:00"`  
**Problematic:** `"2025-10-02"` or `"2025-10-02 08:00:00"` for all

**Fix if true:** Adjust MySQL query or datetime formatting in PHP

### 3. Timezone Conversion Issue ❓
**Hypothesis:** Browser converting times to different timezone  
**Check:** Look for timezone in datetime strings  
**Expected:** `"2025-10-02T14:00:00"` (local time assumed)  
**Problematic:** FullCalendar interpreting as UTC, shifting by timezone offset

**Fix if true:** Add explicit timezone or use `timeZone` config in FullCalendar

### 4. FullCalendar Configuration Issue ❓
**Hypothesis:** Calendar config forcing all events to 08:00  
**Check:** Review calendar initialization  
**Expected:** No `defaultTimedEventDuration` or `forceEventDuration` overriding times  
**Problematic:** Config setting all events to same time

**Fix if true:** Remove conflicting configuration options

### 5. Event Content Override Issue ❓
**Hypothesis:** Custom `eventContent` rendering hiding actual times  
**Check:** Inspect event elements in browser  
**Expected:** Events positioned at correct pixel offset for their time  
**Problematic:** All events at same top position

**Fix if true:** CSS or positioning issue, not data issue

---

## Solutions by Root Cause

### Solution 1: Database Correction
```sql
-- Update appointments with test data
UPDATE xs_appointments 
SET start_time = '2025-10-02 10:00:00', 
    end_time = '2025-10-02 11:00:00'
WHERE id = 1;

UPDATE xs_appointments 
SET start_time = '2025-10-02 14:00:00', 
    end_time = '2025-10-02 15:00:00'
WHERE id = 2;
```

### Solution 2: Explicit Timezone Handling
```javascript
// In normalizeEventDateTime
function normalizeEventDateTime(value) {
  if (!value) return null;
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (trimmed.includes(' ') && !trimmed.includes('T')) {
      // Add timezone offset to make it explicit
      const isoString = trimmed.replace(' ', 'T');
      // Option A: Treat as local time (no Z suffix)
      return isoString;
      // Option B: Treat as UTC (add Z suffix)
      // return isoString + 'Z';
    }
    return trimmed;
  }
  return null;
}
```

### Solution 3: FullCalendar Timezone Config
```javascript
// In calendar initialization
const calendar = new Calendar(element, {
  timeZone: 'local',  // Force local timezone interpretation
  // ... other options
});
```

### Solution 4: API DateTime Formatting
```php
// In Appointments.php
'start' => date('c', strtotime($appointment['start_time'])),  // ISO 8601 with timezone
'end' => date('c', strtotime($appointment['end_time'])),
```

---

## Testing Checklist

### Pre-Test Setup
- [ ] Run `npm run build` to compile debug changes
- [ ] Hard refresh browser (Cmd+Shift+R)
- [ ] Open DevTools Console

### Database Verification
- [ ] Check raw database data has varied times (not all 08:00)
- [ ] Verify DATETIME fields contain time component (HH:MM:SS)
- [ ] Confirm no timezone conversion in MySQL

### API Response Verification
- [ ] Check console log shows raw API response
- [ ] Verify `start` and `end` contain time component
- [ ] Confirm format is "YYYY-MM-DD HH:MM:SS"

### Frontend Verification
- [ ] Check mapped events have ISO format "YYYY-MM-DDTHH:MM:SS"
- [ ] Verify no null start/end times
- [ ] Confirm times match database values

### Visual Verification
- [ ] Create test appointment at 10:00-11:00
- [ ] Create test appointment at 14:00-15:00
- [ ] View in week view
- [ ] Confirm appointments appear at correct times (not all at 08:00)
- [ ] View in day view
- [ ] Confirm same correct positioning

---

## API Enhancement (Completed) ✅

Updated `app/Controllers/Api/Appointments.php` to include:
- Customer names (from customers table join)
- Service names (from services table join)
- Provider names (from users table join)

This improves event display but doesn't fix time slot rendering issue.

**Changes:**
```php
$builder->select('appointments.*, 
                 CONCAT(c.first_name, " ", c.last_name) as customer_name,
                 s.name as service_name,
                 CONCAT(p.first_name, " ", p.last_name) as provider_name')
        ->join('customers c', 'c.id = appointments.customer_id', 'left')
        ->join('services s', 's.id = appointments.service_id', 'left')
        ->join('users p', 'p.id = appointments.provider_id', 'left')
```

---

## Next Steps

1. **User Action Required:** Check browser console for debug logs
2. **Report findings:**
   - What does `[scheduler] Sample appointment data` show?
   - What does `[scheduler] Sample mapped event` show?
   - Are there any `[scheduler] Invalid datetime` warnings?
3. **Database check:** Query appointments table directly to verify times
4. **Based on findings:** Apply appropriate solution from above

---

## Status

**Current:** Debug logging deployed, waiting for console output  
**Files Modified:** 2
- `resources/js/scheduler-dashboard.js` - Added debug logging
- `app/Controllers/Api/Appointments.php` - Enhanced with joins

**Build:** ✅ Successful (1.76s)  
**Next:** User to check console and report findings

---

**Last Updated:** October 2, 2025

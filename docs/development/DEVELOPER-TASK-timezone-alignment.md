# 🧾 Developer Task: Appointment Timezone Alignment

**Status:** 🔴 Investigation Required  
**Priority:** HIGH (Blocking appointment display)  
**Estimated Time:** 2-4 hours  
**Created:** October 24, 2025  
**Assigned To:** Development Team  

---

## 📋 Task Overview

Investigate and fix timezone mismatch between frontend (calendar) and backend (stored appointment time). Users report appointments created for 10:30 AM displaying in the 08:00-09:00 time slot (2-hour offset).

**Root Cause Hypothesis:** 
- Backend stores times in UTC (configured in `App.php`)
- Frontend displays times without proper UTC→local timezone conversion
- Missing or incorrect timezone header attachment on API requests

---

## ✅ Acceptance Criteria

- [ ] Timezone setting (Africa/Johannesburg) is correctly saved/loaded from settings database
- [ ] X-Client-Timezone header is attached to ALL API requests from frontend
- [ ] X-Client-Offset header contains correct UTC offset in minutes
- [ ] TimezoneService reads session timezone before converting times
- [ ] Appointments stored in database are in UTC
- [ ] Calendar renders appointments in selected/local timezone
- [ ] No double conversions occurring (UTC→Local→UTC)
- [ ] Test cases pass for multiple timezones (Africa/Johannesburg, America/New_York, Europe/London, Asia/Tokyo)

---

## 🔍 Verification Checklist

### 1️⃣ Settings Verification

**Location:** `/settings` page → Localization section

**Tasks:**
- [ ] Navigate to Settings page
- [ ] Verify "Timezone" dropdown exists with Africa/Johannesburg as default
- [ ] Select `Africa/Johannesburg` (SAST +2:00)
- [ ] Click Save
- [ ] Refresh page
- [ ] Confirm Africa/Johannesburg is still selected

**Database Verification:**
```sql
-- Verify timezone is saved
SELECT key, value FROM xs_settings 
WHERE key = 'localization.timezone';

-- Expected result:
-- localization.timezone | Africa/Johannesburg
```

**API Verification:**
```bash
# Test settings API endpoint
curl -X GET http://localhost:8080/api/v1/settings \
  -H "Accept: application/json"

# Response should include:
# "localization.timezone": "Africa/Johannesburg"
```

---

### 2️⃣ Frontend Header Attachment

**Location:** `resources/js/utils/timezone-helper.js`

**Function:** `attachTimezoneHeaders()`

**Verification Steps:**

1. **Check Function Implementation:**
```javascript
// Should look like this:
export function attachTimezoneHeaders() {
  const timezone = getBrowserTimezone();
  const offset = getTimezoneOffset();
  
  return {
    'X-Client-Timezone': timezone,
    'X-Client-Offset': offset.toString()
  };
}
```

2. **Verify Headers on All Requests:**

Open Browser DevTools → Network Tab, then:
- [ ] Create new appointment
- [ ] Check all API requests (GET, POST, PUT)
- [ ] Look for `X-Client-Timezone` header
- [ ] Look for `X-Client-Offset` header
- [ ] Verify values are correct:
  - Timezone: `Africa/Johannesburg` (or browser timezone)
  - Offset: `-120` (for SAST = UTC+2, so offset is -120 minutes)

**Example in Network Tab:**
```
Request Headers:
X-Client-Timezone: Africa/Johannesburg
X-Client-Offset: -120
Content-Type: application/json
```

3. **Console Verification:**
```javascript
// In browser console:
window.DEBUG_TIMEZONE.logInfo();

// Should output something like:
// Browser Timezone: Africa/Johannesburg
// UTC Offset: -120 minutes
// Offset (hours): UTC+2
// Current Local Time: Fri Oct 24 2025 14:30:00 GMT+0200
// Current UTC Time: Fri Oct 24 2025 12:30:00 GMT
```

---

### 3️⃣ Session Timezone Reading

**Location:** `app/Services/TimezoneService.php`

**Function:** `getSessionTimezone()` (private method)

**Verification Steps:**

1. **Check Session Storage:**
```php
// Add temporary debug code in any controller:
echo 'Session Timezone: ' . session()->get('client_timezone');
echo 'App Timezone: ' . config('App')->appTimezone;
```

2. **Verify Middleware Captures Headers:**

Search for middleware that processes `X-Client-Timezone` header:
```bash
# Look for middleware implementation
find app/Middleware -type f -name "*.php" | xargs grep -l "X-Client-Timezone"
```

**Expected middleware output in logs:**
```
[DEBUG] Client timezone detected: Africa/Johannesburg
[DEBUG] Client offset detected: -120 minutes
```

3. **Test Session Storage:**
```php
// After API request with headers, check:
$timezone = session()->get('client_timezone');
if ($timezone) {
    echo "✅ Timezone stored in session: $timezone";
} else {
    echo "❌ Timezone NOT in session - middleware may not be running";
}
```

---

### 4️⃣ TimezoneService Time Conversion

**Location:** `app/Services/TimezoneService.php`

**Methods to Verify:**

#### A. `toUTC()` - Local → UTC
```php
// Test conversion
$localTime = '2025-10-24 10:30:00';
$timezone = 'Africa/Johannesburg';
$utcTime = TimezoneService::toUTC($localTime, $timezone);

echo "Local: $localTime → UTC: $utcTime";
// Expected: 2025-10-24 08:30:00 (2 hours earlier)
```

**Verification Checklist:**
- [ ] Takes local time + timezone
- [ ] Returns UTC time (2 hours earlier for Africa/Johannesburg)
- [ ] Handles DST correctly
- [ ] Has error logging for invalid timezones

#### B. `fromUTC()` - UTC → Local
```php
// Test conversion
$utcTime = '2025-10-24 08:30:00';
$timezone = 'Africa/Johannesburg';
$localTime = TimezoneService::fromUTC($utcTime, $timezone);

echo "UTC: $utcTime → Local: $localTime";
// Expected: 2025-10-24 10:30:00 (2 hours later)
```

**Verification Checklist:**
- [ ] Takes UTC time + timezone
- [ ] Returns local time (2 hours later for Africa/Johannesburg)
- [ ] Handles DST correctly
- [ ] Has error logging for invalid timezones

#### C. `getOffsetMinutes()` - Get Timezone Offset
```php
// Test offset calculation
$offset = TimezoneService::getOffsetMinutes('Africa/Johannesburg');

echo "Offset: $offset minutes";
// Expected: -120 (UTC+2 = -120 minutes from UTC perspective)
```

**Verification Checklist:**
- [ ] Returns correct offset for given timezone
- [ ] Accounts for DST (different offset in winter/summer)
- [ ] Matches browser offset from X-Client-Offset header

---

### 5️⃣ Appointment Storage - UTC Verification

**Location:** Database `xs_appointments` table

**Database Verification:**

```sql
-- Check appointment times are in UTC
SELECT 
    id,
    start_time,
    end_time,
    status,
    TIMEDIFF(NOW(), start_time) as time_diff
FROM xs_appointments 
WHERE DATE(start_time) = CURDATE()
ORDER BY start_time ASC;
```

**Expected Results:**
- `start_time` and `end_time` should always be in UTC
- If appointment was created at 10:30 Africa/Johannesburg (SAST +2:00):
  - Database should show: `2025-10-24 08:30:00`
  - NOT: `2025-10-24 10:30:00`

**Verification Checklist:**
- [ ] All appointment times are UTC
- [ ] No mix of timezones in database
- [ ] Oldest appointments are also in UTC

---

### 6️⃣ Calendar Rendering - Display Time Verification

**Location:** `resources/js/modules/appointments/appointments-calendar.js`

**Steps:**

1. **Check FullCalendar Configuration:**
```javascript
// In appointments-calendar.js, find calendar initialization:
const calendar = new Calendar(containerEl, {
    // ✅ CORRECT:
    timeZone: 'UTC',  // Store in UTC
    // ❌ WRONG:
    timeZone: 'local',  // Would double-convert
    
    // Then convert when rendering
    events: appointments.map(apt => ({
        ...apt,
        // Backend provides UTC times
        start: apt.start_time,  // e.g., '2025-10-24T08:30:00Z'
        end: apt.end_time,
        // Frontend's fromUTC converts to local for display
    }))
});
```

2. **Test Calendar Display:**
- [ ] Create appointment: 10:30 AM Africa/Johannesburg
- [ ] Save appointment
- [ ] Go to Calendar page
- [ ] Verify appointment shows in 10:30 time slot (not 08:00)
- [ ] Hover over appointment
- [ ] Confirm tooltip shows correct local time

3. **Test with Multiple Timezones:**
```javascript
// In browser console:
// 1. Check what timezone is configured
window.DEBUG_TIMEZONE.logInfo();

// 2. Check an event's times
const event = calendar.getEvents()[0];
window.DEBUG_TIMEZONE.logEvent(event);

// 3. Verify the times are correct
```

---

### 7️⃣ API Request/Response Flow

**Full Appointment Creation Flow:**

```
┌─────────────────────────────────────────────────────────────┐
│ 1. USER SELECTS TIME IN FORM (10:30 AM)                    │
│    - Browser timezone: Africa/Johannesburg (UTC+2)          │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 2. JAVASCRIPT CONVERTS LOCAL → UTC                          │
│    - toBrowserUTC('2025-10-24 10:30:00')                    │
│    → '2025-10-24T08:30:00Z' (ISO UTC)                       │
│    - Adds headers:                                           │
│      X-Client-Timezone: Africa/Johannesburg                 │
│      X-Client-Offset: -120                                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 3. API RECEIVES REQUEST                                      │
│    - Headers captured by middleware                          │
│    - Stored in session:                                      │
│      session('client_timezone') = 'Africa/Johannesburg'     │
│      session('client_offset_minutes') = -120                │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 4. BACKEND STORES IN DATABASE (UTC)                         │
│    - Request contains: 2025-10-24T08:30:00Z (UTC)          │
│    - Store directly: 2025-10-24 08:30:00                    │
│    - DO NOT convert again!                                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 5. API RESPONSE (TO CALENDAR)                               │
│    - Return UTC times from database:                         │
│    - start_time: '2025-10-24T08:30:00Z' (UTC)              │
│    - end_time: '2025-10-24T09:30:00Z' (UTC)                │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 6. CALENDAR RENDERS (LOCAL TIME)                            │
│    - fromUTC('2025-10-24T08:30:00Z')                        │
│    → '2025-10-24 10:30:00' (Local display)                  │
│    - Shows in 10:30 time slot ✅                            │
└──────────────────────┬──────────────────────────────────────┘
```

**Testing This Flow:**

1. Open DevTools → Network Tab
2. Create appointment at 10:30
3. Check POST request body → Verify time is 08:30 (UTC)
4. Check API response → Verify time stored as 08:30
5. Refresh calendar → Verify displays as 10:30

---

## 🐛 Common Issues & Fixes

### Issue 1: Double Conversion (UTC → Local → UTC)
**Symptom:** Times get increasingly offset with each action

**Fix in Appointments Controller:**
```php
// ❌ WRONG - Converting twice:
$localTime = $_POST['start_time']; // Already in UTC from frontend
$utcTime = TimezoneService::toUTC($localTime); // Converting UTC to UTC!

// ✅ CORRECT - Store as-is:
$localTime = $_POST['start_time']; // Already UTC from frontend
$appointment->start_time = $localTime; // Store directly
```

### Issue 2: Missing Headers on Requests
**Symptom:** X-Client-Timezone header not appearing in Network tab

**Fix in Frontend:**
```javascript
// ❌ WRONG - Not attaching headers:
fetch('/api/appointments', {
    method: 'POST',
    body: JSON.stringify(data)
});

// ✅ CORRECT - Attach timezone headers:
import { attachTimezoneHeaders } from './utils/timezone-helper.js';

fetch('/api/appointments', {
    method: 'POST',
    headers: {
        ...attachTimezoneHeaders(),  // Add this!
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
});
```

### Issue 3: Middleware Not Running
**Symptom:** Session timezone is empty/null

**Check in `app/Config/Middleware.php`:**
```php
// Middleware should be registered:
public array $aliases = [
    // ... other middleware
    'timezone' => \App\Middleware\TimezoneDetection::class,  // Must exist
];

public array $required = [
    'timezone',  // Must be in required list
];
```

### Issue 4: Wrong Timezone Offset Math
**Symptom:** Offset always positive/negative, wrong calculations

**Fix in TimezoneService:**
```php
// Timezone offsets are NEGATIVE for east of UTC
// Africa/Johannesburg (UTC+2):
//   - Offset from UTC: +2 hours
//   - In minutes: +120 minutes
//   - But PHP getOffset returns negative: -120 seconds
//   - So negate: -(-120 / 60) = -120 minutes

return -($tz->getOffset($date) / 60);  // Correct
```

---

## 🧪 Test Cases

### Test 1: Africa/Johannesburg Timezone
```
GIVEN: User in Africa/Johannesburg (UTC+2)
WHEN: Creates appointment at 10:30 AM local
THEN: 
  - Frontend converts to 08:30 UTC
  - Database stores 08:30 UTC
  - Calendar displays 10:30 AM local
EXPECTED OFFSET: -120 minutes
```

### Test 2: New York Timezone (EDT)
```
GIVEN: User in America/New_York (EDT = UTC-4)
WHEN: Creates appointment at 2:00 PM local
THEN:
  - Frontend converts to 18:00 UTC
  - Database stores 18:00 UTC
  - Calendar displays 2:00 PM local
EXPECTED OFFSET: 240 minutes
```

### Test 3: London Timezone (BST)
```
GIVEN: User in Europe/London (BST = UTC+1)
WHEN: Creates appointment at 12:00 PM local
THEN:
  - Frontend converts to 11:00 UTC
  - Database stores 11:00 UTC
  - Calendar displays 12:00 PM local
EXPECTED OFFSET: -60 minutes
```

### Test 4: Cross-Timezone User Migration
```
GIVEN: Appointment created in Africa/Johannesburg at 10:30 AM
WHEN: User changes timezone to America/New_York
AND: Views same appointment
THEN:
  - UTC time in database unchanged: 08:30
  - Calendar displays: 4:30 AM (EDT = UTC-4)
  - Display time differs but appointment is consistent
```

---

## 🔧 Debugging Commands

### 1. Check Settings in Database
```sql
SELECT * FROM xs_settings 
WHERE key LIKE 'localization%'
ORDER BY key;
```

### 2. Check Appointment Times in Database
```sql
SELECT 
    id,
    start_time,
    end_time,
    DATE_ADD(start_time, INTERVAL 2 HOUR) as 'If Johannesburg (UTC+2)',
    DATE_SUB(start_time, INTERVAL 4 HOUR) as 'If NYC (UTC-4)'
FROM xs_appointments
WHERE DATE(start_time) = CURDATE()
LIMIT 5;
```

### 3. Test Timezone Conversion in PHP
```bash
# Create temporary test script
php -r "
require 'vendor/autoload.php';
require 'app/Services/TimezoneService.php';

use App\Services\TimezoneService;

echo 'Test: Local 10:30 AM Johannesburg to UTC:\n';
\$utc = TimezoneService::toUTC('2025-10-24 10:30:00', 'Africa/Johannesburg');
echo 'Result: ' . \$utc . '\n';
echo 'Expected: 2025-10-24 08:30:00\n';
"
```

### 4. Browser Console Debug
```javascript
// Check timezone detection
window.DEBUG_TIMEZONE.logInfo();

// Check event times
const firstEvent = document.querySelector('[data-eventid]');
if (firstEvent) {
    window.DEBUG_TIMEZONE.logEvent(firstEvent);
}

// Test conversion
window.DEBUG_TIMEZONE.logTime('2025-10-24T08:30:00Z');
```

### 5. Network Request Analysis
```javascript
// Intercept fetch to log headers
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const [resource, config] = args;
    console.log('Fetch URL:', resource);
    console.log('Headers:', config?.headers);
    return originalFetch.apply(this, args);
};
```

---

## 📋 Implementation Checklist

- [ ] **Settings Verification**
  - [ ] Timezone dropdown works on settings page
  - [ ] Africa/Johannesburg is saved to database
  - [ ] Timezone persists after page refresh
  - [ ] API endpoint returns saved timezone

- [ ] **Frontend Headers**
  - [ ] timezone-helper.js exports attachTimezoneHeaders()
  - [ ] Function returns X-Client-Timezone and X-Client-Offset
  - [ ] Headers attached to all API requests
  - [ ] DevTools shows headers in Network tab

- [ ] **Backend Session**
  - [ ] Middleware captures timezone headers
  - [ ] Session stores client_timezone
  - [ ] getSessionTimezone() returns correct value
  - [ ] Fallback to app timezone works

- [ ] **Timezone Service**
  - [ ] toUTC() converts local to UTC correctly
  - [ ] fromUTC() converts UTC to local correctly
  - [ ] getOffsetMinutes() returns correct offset
  - [ ] Handles DST transitions

- [ ] **Database**
  - [ ] All appointment times are UTC
  - [ ] No mix of timezones
  - [ ] Conversions work for historical data

- [ ] **Calendar Display**
  - [ ] Events display in local timezone
  - [ ] No 2-hour offset
  - [ ] Multiple timezones work correctly
  - [ ] Debug utilities accessible in console

- [ ] **Testing**
  - [ ] All 4 test cases pass
  - [ ] No double conversions
  - [ ] Cross-timezone migration works
  - [ ] Logs show correct conversions

---

## 📞 Support & References

**Related Documentation:**
- `docs/development/timezone-fix.md` - Full technical guide
- `docs/development/timezone-implementation-guide.md` - Quick reference
- `app/Services/TimezoneService.php` - Backend service code
- `resources/js/utils/timezone-helper.js` - Frontend utilities

**Key Files to Review:**
1. `app/Services/TimezoneService.php` - Core service
2. `resources/js/utils/timezone-helper.js` - Frontend helpers
3. `resources/js/modules/appointments/appointments-calendar.js` - Calendar config
4. `app/Views/settings.php` - Settings UI
5. `app/Config/App.php` - App timezone setting (line 136)

**Contact:** Development Team Lead

---

**Last Updated:** October 24, 2025  
**Status:** Ready for Investigation  
**Next Steps:** Follow checklist items in order, documenting findings in your PR description

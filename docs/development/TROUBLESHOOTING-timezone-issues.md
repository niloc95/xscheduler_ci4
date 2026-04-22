# 🔧 Timezone Alignment - Troubleshooting Guide

**Quick reference for diagnosing and fixing timezone issues**

---

## 🎯 Symptom-Based Quick Diagnosis

### Symptom 1: Appointment shows 2 hours EARLIER than entered

**Example:** Enter 10:30 AM → Displays as 08:30 AM

**Likely Causes (in order of probability):**

1. **Frontend not converting UTC to local** (70% chance)
   - Calendar receives UTC time: `08:30:00Z`
   - Displays as-is without converting to local
   - Fix: Use `fromUTC()` before display

2. **X-Client-Timezone header missing** (20% chance)
   - Backend doesn't know user's timezone
   - Assumes UTC
   - Fix: Call `attachTimezoneHeaders()` on fetch

3. **Session timezone not set** (10% chance)
   - Middleware not running
   - Backend uses app default (UTC)
   - Fix: Check middleware registration

**Step-by-Step Fix:**
```
1. Check DevTools Network tab
   ├─ X-Client-Timezone present? 
   │  ├─ No → Add attachTimezoneHeaders() to fetch
   │  └─ Yes → Continue
   └─ Continue

2. Check browser console
   ├─ Run: window.DEBUG_TIMEZONE.logInfo()
   ├─ Offset should be negative (e.g., -120 for Jo'burg)
   │  ├─ Offset is positive? → Math is wrong, negate it
   │  └─ Continue
   └─ Continue

3. Check calendar configuration
   ├─ timeZone should be 'UTC' (not 'local')
   ├─ Events should be converted via fromUTC()
   │  ├─ Not using fromUTC? → Add it
   │  └─ Continue
   └─ Issue should be fixed!
```

---

### Symptom 2: Appointment shows 2 hours LATER than entered

**Example:** Enter 10:30 AM → Displays as 12:30 PM

**Likely Causes:**

1. **toUTC() not called before saving** (40%)
   - Local time stored directly in database
   - Display thinks it's UTC and adds offset
   - Fix: Call toUTC() when receiving form data

2. **Double conversion: toUTC() called twice** (40%)
   - Already converted to UTC
   - Then converted again
   - Fix: Remove duplicate toUTC() call

3. **Wrong offset math** (20%)
   - Offset calculated with wrong sign
   - Fix: Check offset is -120 for Africa/Johannesburg

**Diagnostic Query:**
```sql
-- Check if times in DB are UTC or local
SELECT start_time FROM xs_appointments LIMIT 1;

-- For 10:30 AM Johannesburg appointment:
-- If DB shows 08:30 → Correct (UTC)
-- If DB shows 10:30 → Wrong (local, needs toUTC)
```

---

### Symptom 3: Headers not appearing in Network tab

**Example:** X-Client-Timezone missing from all requests

**Likely Causes:**

1. **attachTimezoneHeaders() not called** (80%)
   - Fetch doesn't use timezone headers
   - Fix: Add to all fetch calls

2. **CORS blocking headers** (15%)
   - Browser or server rejecting headers
   - Fix: Add to CORS config

3. **Function not exported** (5%)
   - timezone-helper.js not exporting function
   - Fix: Check `export function attachTimezoneHeaders()`

**Quick Check:**
```javascript
// In browser console:
import { attachTimezoneHeaders } from '/resources/js/utils/timezone-helper.js';
const headers = attachTimezoneHeaders();
console.log(headers);
// Should output: { 'X-Client-Timezone': '...', 'X-Client-Offset': '...' }
```

---

### Symptom 4: Settings timezone not saving

**Example:** Select Africa/Johannesburg → Save → Reload → Reverts to default

**Likely Causes:**

1. **Settings form not submitting correctly** (50%)
   - Form validation failing
   - AJAX not working
   - Fix: Check browser console for errors

2. **Database not updating** (30%)
   - INSERT failing
   - Permission issue
   - Fix: Check database logs

3. **Settings being cached** (20%)
   - Old value cached in PHP/JS
   - Fix: Clear cache, refresh with F5

**Quick Check:**
```sql
-- Verify setting in database
SELECT * FROM xs_settings 
WHERE key = 'localization.timezone';

-- If empty or wrong value → DB issue
-- If correct → Cache issue
```

---

## 🔍 Detailed Troubleshooting Flowchart

```
START: Timezone Misalignment
│
├─► Q: What time offset are you seeing?
│   ├─► -2 hours (08:30 instead of 10:30)
│   │   └─► GO TO: "2-Hour Earlier Flow"
│   ├─► +2 hours (12:30 instead of 10:30)
│   │   └─► GO TO: "2-Hour Later Flow"
│   └─► Other offset
│       └─► GO TO: "Offset Math Check"
│
├─► Q: Are headers appearing?
│   ├─► No headers
│   │   └─► ISSUE: attachTimezoneHeaders() not called
│   │       FIX: Add to all fetch() calls
│   │       FILE: resources/js/modules/appointments/appointments-form.js
│   ├─► Headers present but wrong values
│   │   └─► ISSUE: Wrong timezone or offset calculation
│   │       CHECK: window.DEBUG_TIMEZONE.logInfo()
│   └─► Headers correct
│       └─► GO TO: "Backend Check"
│
├─► Q: Is session timezone set?
│   ├─► session('client_timezone') = NULL
│   │   └─► ISSUE: Middleware not running
│   │       CHECK: app/Config/Middleware.php
│   │       VERIFY: TimezoneDetection middleware registered
│   │       FILE: app/Middleware/TimezoneDetection.php
│   ├─► session('client_timezone') = 'Africa/Johannesburg'
│   │   └─► GO TO: "Conversion Check"
│   └─► session('client_timezone') = Wrong value
│       └─► ISSUE: Browser timezone detection failing
│           FIX: Check Intl.DateTimeFormat() in console
│
├─► Q: Are times in database UTC?
│   ├─► SELECT start_time = 08:30:00 (UTC) ✅
│   │   └─► GO TO: "Display Check"
│   ├─► SELECT start_time = 10:30:00 (Local) ❌
│   │   └─► ISSUE: toUTC() not called before saving
│   │       FIX: Add in Appointments::store()
│   │       FILE: app/Controllers/Appointments.php
│   │       CODE: $apt->start_time = TimezoneService::toUTC($localTime);
│   └─► SELECT times = mixed (some UTC, some local) ❌
│       └─► ISSUE: Inconsistent conversion logic
│           FIX: Audit all appointment creation paths
│
├─► Q: Does calendar display correct time?
│   ├─► YES ✅
│   │   └─► FIXED! Issue resolved.
│   ├─► NO ❌
│   │   └─► ISSUE: Frontend not converting UTC to local
│   │       CHECK: fromUTC() called before display?
│   │       FILE: resources/js/modules/appointments/appointments-calendar.js
│   │       CODE: const localTime = fromUTC(apt.start_time);
│   └─► Partially (some events correct, some wrong)
│       └─► ISSUE: Inconsistent conversion in calendar
│           FIX: Ensure all events use fromUTC()
│
└─► VERIFICATION: Run all test cases
    ├─► Test 1: Africa/Johannesburg (UTC+2) ✅
    ├─► Test 2: America/New_York (EDT = UTC-4) ✅
    ├─► Test 3: Europe/London (BST = UTC+1) ✅
    ├─► Test 4: Asia/Tokyo (JST = UTC+9) ✅
    └─► All pass? → Done!
```

---

## 🔄 "2-Hour Earlier" Flow (Time is 2 hours too early)

**Scenario:** 10:30 AM displays as 08:30 AM

**Root Cause:** Frontend displaying UTC time without converting to local

```
┌─────────────────────────────────────────────┐
│ Step 1: VERIFY DATABASE                     │
├─────────────────────────────────────────────┤
│ Query:                                      │
│ SELECT start_time FROM xs_appointments     │
│ WHERE id = 1;                              │
│                                             │
│ Should show: 08:30:00 (UTC) ✅             │
│ Or show: 10:30:00 (local) ❌               │
├─────────────────────────────────────────────┤
│ IF shows 08:30 (UTC):                       │
│  └─► Continue to Step 2                    │
│ IF shows 10:30 (local):                    │
│  └─► PROBLEM: toUTC() not called           │
│  └─► FIX: Add TimezoneService::toUTC()     │
│      in Appointments controller            │
│  └─► SKIP to Step 4 to test fix            │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Step 2: CHECK FRONTEND CONVERSION           │
├─────────────────────────────────────────────┤
│ Browser Console:                            │
│ window.DEBUG_TIMEZONE.logInfo()            │
│                                             │
│ Check: Offset value                         │
│ Should be: -120 (for Africa/Jo'burg) ✅   │
│ If positive: Offset math is wrong ❌       │
├─────────────────────────────────────────────┤
│ IF offset wrong:                            │
│  └─► PROBLEM: getOffsetMinutes() math      │
│  └─► FIX: Negate the offset:               │
│       return -($tz->getOffset($date) / 60) │
│  └─► SKIP to Step 4 to test fix            │
│ IF offset correct:                         │
│  └─► Continue to Step 3                    │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Step 3: CHECK CALENDAR DISPLAY              │
├─────────────────────────────────────────────┤
│ In appointments-calendar.js:                │
│ Look for where events are processed        │
│                                             │
│ Current code likely:                        │
│ start: apt.start_time  // 08:30:00Z (UTC) │
│                                             │
│ Should be:                                  │
│ start: fromUTC(apt.start_time)             │
│        // → 10:30:00 (local) ✅            │
├─────────────────────────────────────────────┤
│ IF missing fromUTC():                       │
│  └─► PROBLEM: UTC not converted to local   │
│  └─► FIX: Wrap in fromUTC()                │
│  └─► Continue to Step 4                    │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Step 4: TEST THE FIX                        │
├─────────────────────────────────────────────┤
│ 1. Apply the fix from Step 2-3             │
│ 2. Build: npm run build                    │
│ 3. Refresh calendar: F5                    │
│ 4. Create new appointment: 10:30 AM        │
│ 5. Check display: Should show 10:30 AM ✅  │
│                   NOT 08:30 AM ❌          │
└─────────────────────────────────────────────┘
```

---

## 🔄 "2-Hour Later" Flow (Time is 2 hours too late)

**Scenario:** 10:30 AM displays as 12:30 PM

**Root Cause:** Time stored as local in database, then offset added again on display

```
┌─────────────────────────────────────────────┐
│ Step 1: VERIFY DATABASE                     │
├─────────────────────────────────────────────┤
│ Query:                                      │
│ SELECT start_time FROM xs_appointments     │
│ WHERE id = 1;                              │
│                                             │
│ Should show: 08:30:00 (UTC) ✅             │
│ Or shows: 10:30:00 (local) ❌              │
├─────────────────────────────────────────────┤
│ IF shows 10:30:00:                          │
│  └─► PROBLEM: toUTC() not called on save   │
│  └─► FIX: In Appointments controller:      │
│       $apt->start_time =                   │
│         TimezoneService::toUTC(             │
│           $_POST['start_time']              │
│         );                                  │
│  └─► Continue to test                      │
│ IF shows 08:30:00:                          │
│  └─► PROBLEM: Elsewhere in pipeline        │
│  └─► Continue to Step 2                    │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Step 2: CHECK FOR DOUBLE CONVERSION         │
├─────────────────────────────────────────────┤
│ Search code for: toUTC()                    │
│ In: app/Controllers/Appointments.php       │
│                                             │
│ Pattern to look for:                        │
│ ❌ $time = TimezoneService::toUTC(...);    │
│    ... later ...                            │
│    $apt->start_time = $time;               │
│    $apt->save();                            │
│                                             │
│ Then in same code:                          │
│ ❌ $apt->start_time =                       │
│      TimezoneService::toUTC($apt->start_time) │
│                                             │
│ If found: DOUBLE CONVERSION! Remove one.   │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Step 3: CHECK API RESPONSE                  │
├─────────────────────────────────────────────┤
│ DevTools Network Tab:                       │
│ 1. Create appointment: 10:30 AM            │
│ 2. POST /api/appointments response         │
│ 3. Check returned start_time              │
│                                             │
│ Should return: 08:30:00Z (UTC) ✅         │
│ OR returns: 10:30:00 (local) ❌            │
├─────────────────────────────────────────────┤
│ IF shows local time:                        │
│  └─► PROBLEM: API returning local time     │
│  └─► FIX: Return UTC from controller       │
│       return json_encode([                 │
│         'start_time' =>                    │
│         $apt->start_time  // Should be UTC │
│       ]);                                   │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Step 4: TEST THE FIX                        │
├─────────────────────────────────────────────┤
│ 1. Apply fix from above steps              │
│ 2. Build: npm run build                    │
│ 3. Refresh calendar: F5                    │
│ 4. Create new appointment: 10:30 AM        │
│ 5. Check:                                  │
│    - Database should show 08:30 UTC ✅    │
│    - Calendar should display 10:30 AM ✅  │
│    - Should NOT display 12:30 PM ❌       │
└─────────────────────────────────────────────┘
```

---

## 🧮 Offset Math Verification

**Understanding Timezone Offsets:**

```
Africa/Johannesburg = UTC+2
→ 2 hours ahead of UTC
→ When UTC is 08:00, Johannesburg is 10:00
→ Local time = UTC time + 2 hours
→ UTC time = Local time - 2 hours

Offset in minutes = -120
(Negative because east of UTC)

JavaScript: new Date().getTimezoneOffset()
→ Returns -120 for Africa/Johannesburg ✅

PHP: $tz->getOffset($date)
→ Returns -7200 seconds (negative convention!)
→ Must negate: -(-7200 / 60) = -120 ✅
```

**Test Your Offset Math:**

```php
// Test in PHP:
$tz = new DateTimeZone('Africa/Johannesburg');
$date = new DateTime('now', $tz);
$offsetSeconds = $tz->getOffset($date);
$offsetMinutes = -($offsetSeconds / 60);  // Negate!

echo "Offset: $offsetMinutes minutes";  // Should be -120

// Verify:
$expectedHours = 2;
$expectedMinutes = $expectedHours * -60;  // = -120 ✅
```

---

## 📊 Testing Checklist by Timezone

### ✅ Test Case: Africa/Johannesburg (UTC+2)

| Step | Action | Expected | Result |
|------|--------|----------|--------|
| 1 | Enter time in form | 10:30 AM | ✓ |
| 2 | Check frontend converts | toBrowserUTC() → 08:30:00Z | ✓ |
| 3 | Check headers sent | X-Client-Offset: -120 | ✓ |
| 4 | Check database | start_time: 08:30:00 | ✓ |
| 5 | Check display | Calendar shows 10:30 AM | ✓ |
| 6 | Check console debug | window.DEBUG_TIMEZONE works | ✓ |

### ✅ Test Case: America/New_York (EDT = UTC-4)

| Step | Action | Expected | Result |
|------|--------|----------|--------|
| 1 | Set timezone | America/New_York | ✓ |
| 2 | Enter time | 2:00 PM | ✓ |
| 3 | Check backend converts | toUTC() → 18:00:00Z (UTC) | ✓ |
| 4 | Check database | start_time: 18:00:00 | ✓ |
| 5 | Check calendar | Displays 2:00 PM | ✓ |
| 6 | Check offset math | X-Client-Offset: 240 | ✓ |

### ✅ Test Case: Europe/London (BST = UTC+1)

| Step | Action | Expected | Result |
|------|--------|----------|--------|
| 1 | Set timezone | Europe/London | ✓ |
| 2 | Enter time | 3:00 PM | ✓ |
| 3 | Check backend converts | toUTC() → 14:00:00Z (UTC) | ✓ |
| 4 | Check database | start_time: 14:00:00 | ✓ |
| 5 | Check calendar | Displays 3:00 PM | ✓ |
| 6 | Check offset math | X-Client-Offset: -60 | ✓ |

---

## 🎓 Learning Path

**If you're new to timezone handling:**

1. **Read First:** `docs/development/timezone-implementation-guide.md`
   - Explains concepts clearly
   - Includes working examples
   - ~350 lines, 15 min read

2. **Understand Architecture:** `docs/development/timezone-fix.md`
   - Full technical guide
   - Implementation steps with code
   - Testing procedures
   - ~550 lines, 30 min read

3. **Reference Code:**
   - Backend: `app/Services/TimezoneService.php` (302 lines)
   - Frontend: `resources/js/utils/timezone-helper.js` (365 lines)
   - Calendar: `resources/js/modules/appointments/appointments-calendar.js` (654 lines)

4. **Practice:**
   - Follow test cases in order
   - Use debug utilities in console
   - Document what you learn

---

**Last Updated:** October 24, 2025  
**Version:** 1.0  
**Difficulty:** Medium  
**Est. Time:** 2-4 hours

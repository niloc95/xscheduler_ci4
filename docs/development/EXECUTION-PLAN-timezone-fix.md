# 🧭 Execution Plan: Appointment Timezone Alignment Fix

**Issue:** Appointments display 2 hours earlier (08:30 instead of 10:30)  
**Root Cause:** UTC/Local timezone conversion mismatch  
**Status:** Ready for Implementation  
**Date:** October 24, 2025

---

## 🎯 Task at a Glance

| Aspect | Current State | Expected State |
|--------|---------------|-----------------|
| **User Input** | 10:30 AM (local) | 10:30 AM (local) |
| **Database** | ❓ Unknown | 08:30 UTC (2 hrs earlier) |
| **Calendar Display** | 08:30 AM ❌ | 10:30 AM ✅ |
| **Timezone** | Africa/Johannesburg | Africa/Johannesburg |
| **UTC Offset** | ? | -120 minutes (UTC+2) |

---

## 📋 Execution Checklist (Step-by-Step)

### ✅ PHASE 1: Diagnosis (30 minutes)

#### Step 1: Verify Browser Timezone Detection

**In Browser Console:**
```javascript
// 1. Check Intl API works
console.log(Intl.DateTimeFormat().resolvedOptions().timeZone);
// Expected: Africa/Johannesburg

// 2. Load timezone helper and check
import { getBrowserTimezone, getTimezoneOffset } from '/resources/js/utils/timezone-helper.js';
console.log('Timezone:', getBrowserTimezone());
// Expected: Africa/Johannesburg

console.log('Offset:', getTimezoneOffset());
// Expected: -120 (minutes from UTC)
```

**Result:** ✅ Pass / ❌ Fail → Document finding

---

#### Step 2: Verify Headers Are Being Sent

**In DevTools → Network Tab:**
1. Open Network tab (F12)
2. Create new appointment at 10:30
3. Look for POST request to `/api/appointments` or `/appointments/store`
4. Click on request → Headers section
5. Look for:
   - `X-Client-Timezone: Africa/Johannesburg` ✅
   - `X-Client-Offset: -120` ✅

**If Headers Missing:**
```javascript
// In browser console, test manually:
import { attachTimezoneHeaders } from '/resources/js/utils/timezone-helper.js';
const headers = attachTimezoneHeaders();
console.log(headers);
// Should output: {X-Client-Timezone: 'Africa/Johannesburg', X-Client-Offset: '-120'}
```

**Result:** ✅ Headers present / ❌ Headers missing → Document finding

---

#### Step 3: Check Settings Persistence

**Step-by-Step:**
1. Navigate to `/settings`
2. Find "Localization" section
3. Find "Timezone" dropdown
4. Confirm `Africa/Johannesburg` is selected
5. Click "Save"
6. Refresh page (F5)
7. Check if still selected

**Database Verification:**
```sql
-- Check if setting is saved
SELECT key, value FROM xs_settings 
WHERE key = 'localization.timezone';

-- Expected: 
-- localization.timezone | Africa/Johannesburg
```

**Result:** ✅ Settings saved / ❌ Settings not persisted → Document finding

---

#### Step 4: Check Session Timezone

**Add Debug Code to Any Controller (Temporary):**
```php
// In app/Controllers/Appointments.php, add to any method:
echo "Session TZ: " . session()->get('client_timezone') . "<br>";
echo "App TZ: " . config('App')->appTimezone . "<br>";
echo "Settings TZ: " . (new \App\Services\LocalizationSettingsService())->get('localization.timezone') . "<br>";
```

**Expected Output:**
```
Session TZ: Africa/Johannesburg
App TZ: UTC
Settings TZ: Africa/Johannesburg
```

**Result:** ✅ Session correct / ❌ Session null/wrong → Document finding

---

#### Step 5: Check Database Appointment Times

**Query:**
```sql
-- Get most recent appointment
SELECT 
    id,
    start_time,
    end_time,
    TIMESTAMPDIFF(MINUTE, start_time, NOW()) as minutes_ago
FROM xs_appointments 
ORDER BY created_at DESC 
LIMIT 1;
```

**Analysis:**
- If user created appointment at 10:30 AM local (Africa/Johannesburg):
  - ✅ Database shows `08:30:00` = Correct (UTC)
  - ❌ Database shows `10:30:00` = Wrong (local time stored instead of UTC)

**Result:** ✅ UTC stored / ❌ Local time stored → Document finding

---

### ✅ PHASE 2: Root Cause Identification (20 minutes)

**Based on PHASE 1 Results, Identify Issue:**

```
IF Headers missing:
  → ISSUE: attachTimezoneHeaders() not called
  → FIX: Add to fetch() calls
  
IF Headers present but session null:
  → ISSUE: Middleware not capturing headers
  → FIX: Check middleware registration
  
IF Times in DB are local (10:30):
  → ISSUE: toUTC() not called before save
  → FIX: Add TimezoneService::toUTC() in controller
  
IF Times in DB are UTC (08:30):
  → ISSUE: Display not converting back from UTC
  → FIX: Add fromUTC() in calendar display
  
IF Settings not persisting:
  → ISSUE: Settings form validation/save
  → FIX: Check form submission & database insert
```

---

### ✅ PHASE 3: Implementation (60-120 minutes)

#### Fix 1: Ensure Headers Are Sent

**File:** `resources/js/modules/appointments/appointments-form.js` (or wherever appointments are created)

**Find:** The fetch call that creates/saves appointments

**Current (Likely):**
```javascript
fetch('/api/appointments', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(formData)
})
```

**Fix - Add Timezone Headers:**
```javascript
import { attachTimezoneHeaders } from '/resources/js/utils/timezone-helper.js';

fetch('/api/appointments', {
    method: 'POST',
    headers: {
        ...attachTimezoneHeaders(),  // ← ADD THIS
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(formData)
})
```

**Verification:** Check DevTools Network → POST request → Headers → Should see X-Client-Timezone

---

#### Fix 2: Ensure Middleware Captures Headers

**File:** `app/Config/Middleware.php`

**Find:** Middleware registration section

**Verify:**
```php
public array $required = [
    // ... other middleware
    'timezone',  // ← Should be registered
];

public $aliases = [
    'timezone' => \App\Middleware\TimezoneDetection::class,  // ← Should exist
];
```

**If Missing:**
1. Check if `app/Middleware/TimezoneDetection.php` exists
2. If not, create it (see docs/development/timezone-fix.md)
3. Register in Middleware.php

**Verification:** Check logs for: `[DEBUG] Client timezone detected: Africa/Johannesburg`

---

#### Fix 3: Ensure Times Stored in UTC

**File:** `app/Controllers/Appointments.php`

**Find:** The `store()` method that saves appointments

**Current (Likely):**
```php
public function store()
{
    $startTime = $this->request->getPost('start_time'); // "10:30:00"
    $endTime = $this->request->getPost('end_time');     // "11:30:00"
    
    $appointment = new AppointmentModel();
    $appointment->start_time = $startTime;  // ❌ Storing local time!
    $appointment->end_time = $endTime;
    $appointment->save();
}
```

**Fix - Convert to UTC Before Saving:**
```php
use App\Services\TimezoneService;

public function store()
{
    $startTime = $this->request->getPost('start_time'); // "10:30:00" (local)
    $endTime = $this->request->getPost('end_time');     // "11:30:00" (local)
    
    // ✅ Convert to UTC before storing
    $timezone = session()->get('client_timezone') ?? 'Africa/Johannesburg';
    $startTimeUTC = TimezoneService::toUTC($startTime, $timezone);
    $endTimeUTC = TimezoneService::toUTC($endTime, $timezone);
    
    $appointment = new AppointmentModel();
    $appointment->start_time = $startTimeUTC;   // ✅ Now UTC
    $appointment->end_time = $endTimeUTC;        // ✅ Now UTC
    $appointment->save();
}
```

**Verification:** After creating appointment, check DB shows 08:30 (UTC)

---

#### Fix 4: Ensure Calendar Displays in Local Timezone

**File:** `resources/js/modules/appointments/appointments-calendar.js`

**Find:** Where events are processed for display (likely in `initAppointmentsCalendar()`)

**Current (Likely):**
```javascript
const calendar = new Calendar(containerEl, {
    events: appointments.map(apt => ({
        id: apt.id,
        title: apt.title,
        start: apt.start_time,  // ❌ UTC as-is: 08:30:00Z
        end: apt.end_time,      // ❌ UTC as-is: 09:30:00Z
        // ... other properties
    }))
});
```

**Fix - Convert UTC to Local Before Display:**
```javascript
import { fromUTC } from '/resources/js/utils/timezone-helper.js';

const calendar = new Calendar(containerEl, {
    events: appointments.map(apt => ({
        id: apt.id,
        title: apt.title,
        start: fromUTC(apt.start_time),  // ✅ Converts 08:30Z → 10:30 (local)
        end: fromUTC(apt.end_time),      // ✅ Converts 09:30Z → 11:30 (local)
        // ... other properties
    }))
});
```

**Verification:** Refresh calendar → Appointment displays at 10:30 slot

---

#### Fix 5: Ensure Settings Override Works Globally

**File:** `app/Services/LocalizationSettingsService.php` or where settings are loaded

**Verify this logic:**
```php
public function getTimezone()
{
    // 1. Check session (client sent via header)
    if (session()->has('client_timezone')) {
        return session()->get('client_timezone');
    }
    
    // 2. Check settings (user selected in /settings)
    $settingsTz = $this->get('localization.timezone');
    if ($settingsTz) {
        session()->set('client_timezone', $settingsTz);
        return $settingsTz;
    }
    
    // 3. Fallback to app default
    return config('App')->appTimezone ?? 'UTC';
}
```

**Verification:** All parts of app use this method to get timezone

---

### ✅ PHASE 4: Testing (30 minutes)

#### Test Case 1: Basic Flow

```
STEP 1: Set timezone
  → Go to /settings
  → Set timezone to Africa/Johannesburg
  → Click Save
  → Refresh

STEP 2: Create appointment
  → Go to Appointments/Calendar
  → Create appointment: 10:30 - 11:30
  → Check DevTools Network → X-Client-Timezone: Africa/Johannesburg

STEP 3: Verify database
  → Run: SELECT start_time FROM xs_appointments ORDER BY id DESC LIMIT 1;
  → Should show: 08:30:00 (UTC)

STEP 4: Verify display
  → Refresh calendar
  → Find appointment in calendar
  → Should be in 10:30 slot, NOT 08:30 slot
```

**Result:** ✅ Pass / ❌ Fail → Document issue

---

#### Test Case 2: Cross-Timezone

```
STEP 1: Create appointment in Africa/Johannesburg
  → Timezone: Africa/Johannesburg
  → Appointment: 10:30 - 11:30
  → Verify: Displays at 10:30 ✅

STEP 2: Change timezone to America/New_York (EDT = UTC-4)
  → Go to /settings
  → Change timezone to America/New_York
  → Save

STEP 3: View same appointment
  → Should now display at: 04:30 (UTC-4 vs UTC+2 = 6 hour difference)
  → Appointment should still be the same UTC time in database
  → But display changes based on user's timezone ✅
```

**Result:** ✅ Pass / ❌ Fail → Document issue

---

#### Test Case 3: Browser Detection Fallback

```
STEP 1: Create appointment (headers should work)
  → Verify X-Client-Timezone header present ✅

STEP 2: Simulate old browser (no Intl)
  → In console: Delete Intl object
  → Create new appointment
  → Should fallback gracefully
  → Verify: Still works or shows error (acceptable)
```

**Result:** ✅ Pass / ⚠️ Fallback works / ❌ Fails

---

### ✅ PHASE 5: Validation (15 minutes)

**Success Criteria - All Must Pass:**

- [ ] Browser timezone detected: Africa/Johannesburg
- [ ] X-Client-Timezone header in all API requests
- [ ] X-Client-Offset header shows -120 minutes
- [ ] Settings timezone persists across page refreshes
- [ ] Session contains correct client_timezone
- [ ] Appointment times stored as UTC in database
- [ ] Calendar displays appointment in correct local time slot
- [ ] No double conversions occurring
- [ ] No console errors or warnings
- [ ] Test case 1 passes: 10:30 displays as 10:30
- [ ] Test case 2 passes: Timezone change updates display
- [ ] Test case 3 passes: Fallback graceful

---

## 🔧 Quick Command Reference

### Browser Console Commands

```javascript
// Check timezone detection
import { getBrowserTimezone, getTimezoneOffset } from '/resources/js/utils/timezone-helper.js';
console.log('TZ:', getBrowserTimezone(), 'Offset:', getTimezoneOffset());

// Check headers
import { attachTimezoneHeaders } from '/resources/js/utils/timezone-helper.js';
console.log(attachTimezoneHeaders());

// Test conversion
import { toBrowserUTC, fromUTC } from '/resources/js/utils/timezone-helper.js';
console.log('Local 10:30 → UTC:', toBrowserUTC('2025-10-24 10:30:00'));
console.log('UTC 08:30 → Local:', fromUTC('2025-10-24T08:30:00Z'));

// Debug timezone (if available)
if (window.DEBUG_TIMEZONE) {
    window.DEBUG_TIMEZONE.logInfo();
}
```

### Database Commands

```sql
-- Check settings
SELECT * FROM xs_settings WHERE key LIKE 'localization.timezone';

-- Check appointment times (UTC)
SELECT id, start_time, end_time FROM xs_appointments 
ORDER BY created_at DESC LIMIT 3;

-- Verify offset math
-- For Africa/Johannesburg (UTC+2):
-- When UTC is 08:30, local should be 10:30
SELECT 
    '2025-10-24 08:30:00' as utc_time,
    DATE_ADD('2025-10-24 08:30:00', INTERVAL 2 HOUR) as johannesburg_local;
```

### PHP Testing

```php
// Test timezone conversion
use App\Services\TimezoneService;

$local = '2025-10-24 10:30:00';
$tz = 'Africa/Johannesburg';
$utc = TimezoneService::toUTC($local, $tz);
echo "Local $local → UTC $utc"; // Should show 08:30:00

$back = TimezoneService::fromUTC($utc, $tz);
echo "UTC $utc → Local $back"; // Should show 10:30:00
```

---

## 🐛 If Something Goes Wrong

### Symptom: Appointment still shows 2 hours earlier

**Check List:**
1. [ ] Are headers being sent? (DevTools Network tab)
2. [ ] Is database storing UTC? (Run SQL query)
3. [ ] Is calendar converting from UTC? (Check appointments-calendar.js)
4. [ ] Is there a double conversion? (Check store() method)

**Most Likely Cause:** Frontend not converting UTC → Local for display

**Quick Fix:**
```javascript
// In appointments-calendar.js, wrap start/end with fromUTC():
start: fromUTC(apt.start_time),  // Add this line
end: fromUTC(apt.end_time)       // Add this line
```

---

### Symptom: Settings timezone not saving

**Check List:**
1. [ ] Is form submitting? (Check Network tab POST)
2. [ ] Is database updated? (Run SQL SELECT)
3. [ ] Is page refreshing? (Try F5)

**Most Likely Cause:** Form validation or permission issue

**Quick Fix:**
1. Check browser console for errors
2. Check server logs for database errors
3. Verify form field name matches: `name="timezone"`

---

### Symptom: Headers missing from requests

**Check List:**
1. [ ] Is timezone-helper imported? (Check imports at top of file)
2. [ ] Is attachTimezoneHeaders() called? (Search for fetch/axios calls)
3. [ ] Is it before request is sent? (Check order in fetch config)

**Most Likely Cause:** attachTimezoneHeaders() not being called

**Quick Fix:**
```javascript
// Find all fetch() calls and add:
headers: {
    ...attachTimezoneHeaders(),  // ← Add this
    'Content-Type': 'application/json'
}
```

---

## 📊 Expected Results After Fix

| Scenario | Before Fix | After Fix |
|----------|-----------|----------|
| User enters 10:30 AM | ❌ Displays 08:30 AM | ✅ Displays 10:30 AM |
| User views in Jo'burg | ❌ Shows 08:30 | ✅ Shows 10:30 |
| User changes to NY | ❌ Still wrong | ✅ Shows 04:30 (correct for UTC-4) |
| Database stores | ❌ 10:30 local | ✅ 08:30 UTC |
| Headers sent | ❌ Missing | ✅ X-Client-Timezone present |
| Session timezone | ❌ NULL | ✅ Africa/Johannesburg |

---

## ✨ Summary

**Problem:** 2-hour timezone offset (10:30 displays as 08:30)

**Root Cause:** Frontend displaying UTC times without converting to local timezone

**Solution:** 
1. Ensure headers sent (frontend)
2. Ensure middleware captures timezone (backend)
3. Ensure times stored as UTC (backend)
4. Ensure display converts UTC → Local (frontend)

**Time to Fix:** 1-2 hours total

**Success Indicator:** Appointment at 10:30 AM displays at 10:30 AM on calendar

---

**Need Help?** Reference `docs/development/DEVELOPER-TASK-timezone-alignment.md` for detailed explanations and more examples.


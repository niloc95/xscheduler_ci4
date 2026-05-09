# 🎯 Developer Quick Start Card

**Print this page and keep it handy while working on the timezone fix**

---

## 🚨 THE PROBLEM IN 30 SECONDS

```
User enters: 10:30 AM
Calendar shows: 08:30 AM ← 2 hours WRONG!
Why: UTC time not converting to local timezone
```

---

## ⚡ 5-MINUTE DIAGNOSIS

### In Browser Console:
```javascript
// 1. Check timezone detection
import { getBrowserTimezone, getTimezoneOffset } from '/resources/js/utils/timezone-helper.js';
console.log('TZ:', getBrowserTimezone());       // Should: Africa/Johannesburg
console.log('Offset:', getTimezoneOffset());    // Should: -120

// 2. Check headers
import { attachTimezoneHeaders } from '/resources/js/utils/timezone-helper.js';
console.log(attachTimezoneHeaders());           // Should see X-Client-Timezone

// 3. Check debug tools
window.DEBUG_TIMEZONE.logInfo();                // Shows timezone details
```

### In DevTools Network Tab:
```
1. Create appointment
2. Look at POST request
3. Find headers:
   X-Client-Timezone: Africa/Johannesburg  ✅
   X-Client-Offset: -120                   ✅
```

### In Database:
```sql
SELECT start_time FROM xs_appointments ORDER BY id DESC LIMIT 1;

Should show: 08:30:00 (UTC)  ✅
Should NOT show: 10:30:00 (local) ❌
```

---

## 🔧 5 POSSIBLE FIXES

### Fix 1: Headers Missing?
```javascript
// FILE: resources/js/modules/appointments/appointments-form.js
// FIND: fetch('/api/appointments', { ... })
// ADD: ...attachTimezoneHeaders() to headers

fetch('/api/appointments', {
    method: 'POST',
    headers: {
        ...attachTimezoneHeaders(),  // ← ADD THIS
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
})
```

### Fix 2: Database Storing Local Time?
```php
// FILE: app/Controllers/Appointments.php
// FIND: store() method
// ADD: Use TimezoneService::toUTC()

use App\Services\TimezoneService;

$startTime = $this->request->getPost('start_time'); // 10:30:00
$timezone = session()->get('client_timezone') ?? 'Africa/Johannesburg';
$startTimeUTC = TimezoneService::toUTC($startTime, $timezone); // 08:30:00

$appointment->start_time = $startTimeUTC;  // Store UTC
```

### Fix 3: Calendar Showing UTC?
```javascript
// FILE: resources/js/modules/appointments/appointments-calendar.js
// FIND: Where events are mapped
// ADD: Use fromUTC() for display

import { fromUTC } from '/resources/js/utils/timezone-helper.js';

events: appointments.map(apt => ({
    start: fromUTC(apt.start_time),  // Convert 08:30Z → 10:30 local
    end: fromUTC(apt.end_time)
}))
```

### Fix 4: Settings Not Saving?
```javascript
// FILE: Check form in browser
// GO TO: /settings
// TEST: Select Africa/Johannesburg → Save → Refresh
// VERIFY: Still selected?
```

### Fix 5: Middleware Not Registered?
```php
// FILE: app/Config/Middleware.php
// VERIFY: These lines exist:

public array $required = [
    'timezone',  // ← Should be here
];

public $aliases = [
    'timezone' => \App\Middleware\TimezoneDetection::class,  // ← Should exist
];
```

---

## ✅ QUICK TEST

```
1. Go to /settings → Set Africa/Johannesburg → Save
2. Create appointment: 10:30 - 11:30
3. Check Network tab: X-Client-Timezone header present?
4. Check database: start_time = 08:30:00 UTC?
5. Refresh calendar: Shows at 10:30 slot?

All yes? → ✅ FIXED
Any no? → Check which fix applies
```

---

## 🧪 VERIFICATION CHECKLIST

Before you declare it fixed:

- [ ] Browser detects Africa/Johannesburg
- [ ] X-Client-Timezone header in all requests
- [ ] X-Client-Offset is -120 minutes
- [ ] Settings timezone persists on reload
- [ ] Session contains correct timezone
- [ ] Database times stored in UTC (08:30)
- [ ] Calendar displays in local time (10:30)
- [ ] No double conversions
- [ ] No console errors
- [ ] Test with different timezone (US/NY, Europe/London)

---

## 📊 THE CONVERSION FLOW

```
User Input (Frontend):
  10:30 AM local
       ↓
Convert to UTC:
  toBrowserUTC('10:30:00') → '08:30:00Z'
       ↓
Send to API with headers:
  X-Client-Timezone: Africa/Johannesburg
  X-Client-Offset: -120
       ↓
Backend receives:
  TimezoneService::toUTC() validates & uses session timezone
       ↓
Store in Database:
  08:30:00 UTC ✅
       ↓
Retrieve from Database:
  08:30:00 UTC
       ↓
Display on Calendar:
  fromUTC('08:30:00Z') → '10:30:00' local ✅
       ↓
User sees: 10:30 AM ✅
```

---

## 🆘 STUCK?

| Problem | Solution |
|---------|----------|
| Headers not in Network tab | Add `...attachTimezoneHeaders()` to fetch |
| Database shows 10:30 local | Add `TimezoneService::toUTC()` before save |
| Calendar shows 08:30 UTC | Add `fromUTC()` when rendering events |
| Settings don't save | Check form validation, try clearing browser cache |
| Session timezone is null | Verify middleware is registered in Middleware.php |
| Offset wrong sign | Check: Africa/Jo'burg = UTC+2 = offset -120 (negative!) |

---

## 📚 DETAILED GUIDES

**For step-by-step guidance:**
→ `docs/development/EXECUTION-PLAN-timezone-fix.md`

**For troubleshooting by symptom:**
→ `docs/development/TROUBLESHOOTING-timezone-issues.md`

**For complete reference:**
→ `docs/development/DEVELOPER-TASK-timezone-alignment.md`

---

## ⏱️ TIME BREAKDOWN

- Diagnosis: 30 min
- Identify fix: 20 min
- Implement: 60 min
- Test: 30 min
- **TOTAL: 2 hours average**

---

**Remember:** 
- All times in DB = UTC ✅
- All display = Local timezone ✅
- Always convert when storing ✅
- Always convert when displaying ✅
- Headers = Communication ✅

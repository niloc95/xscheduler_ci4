# ⚡ Quick Developer Checklist - Timezone Alignment

**Print this page for quick reference during investigation**

---

## 🚀 Start Here (5 min)

- [ ] Read this checklist (you're doing it!)
- [ ] Open `docs/development/DEVELOPER-TASK-timezone-alignment.md` in full
- [ ] Open browser DevTools → Network Tab
- [ ] Open terminal → ready to run SQL queries
- [ ] Set up phone/second monitor for cross-timezone testing

---

## 🔴 CRITICAL: The 2-Hour Problem Explained

**What's happening:**
```
User enters: 10:30 AM (local Africa/Johannesburg)
             ↓
Should display: 10:30 AM
             ↓
Actually displays: 08:30 AM (2-hour offset)
```

**Why it happens:**
- Frontend sends UTC: `08:30:00Z` ✅ Correct
- Backend stores UTC: `08:30:00Z` ✅ Correct
- Frontend displays UTC as-is: `08:30:00` ❌ Wrong!

**Solution:** Frontend must convert UTC → local before display

---

## 🧪 Quick Test (15 min)

### Step 1: Check Settings
```bash
# In browser:
1. Go to /settings
2. Find "Localization" → "Timezone"
3. Select "Africa/Johannesburg"
4. Click Save
5. Refresh page
6. Confirm still selected
```

### Step 2: Check Headers
```bash
# In DevTools Network Tab:
1. Create test appointment at 10:30 AM
2. Look at POST request
3. Find headers section
4. LOOK FOR:
   X-Client-Timezone: Africa/Johannesburg  ← Must exist
   X-Client-Offset: -120  ← Must exist
5. Check request body → time should be 08:30:00Z (UTC)
```

### Step 3: Check Database
```sql
-- Run in database tool:
SELECT start_time, end_time 
FROM xs_appointments 
WHERE DATE(start_time) = CURDATE() 
LIMIT 1;

-- Should show: 08:30:00 (not 10:30:00)
```

### Step 4: Check Display
```bash
# In browser calendar:
1. Refresh calendar page
2. Find the 10:30 AM appointment
3. Is it in the 10:30 slot? → ✅ FIXED
4. Is it in the 08:30 slot? → ❌ BUG
```

---

## 🔍 Investigation Flowchart

```
┌─ Settings saved?
│  ├─ No? → Fix settings UI
│  └─ Yes ↓
├─ Headers sent?
│  ├─ No? → Fix attachTimezoneHeaders()
│  └─ Yes ↓
├─ Session populated?
│  ├─ No? → Fix middleware
│  └─ Yes ↓
├─ Times in UTC?
│  ├─ No? → Fix toUTC() call
│  └─ Yes ↓
├─ Display correct?
│  ├─ No? → Fix fromUTC() call
│  └─ Yes ↓
└─ ✅ FIXED!
```

---

## 📝 Common Issues Quick-Fix

| Issue | Symptom | Quick Fix |
|-------|---------|-----------|
| Missing headers | No X-Client-Timezone in DevTools | Call `attachTimezoneHeaders()` in fetch |
| Wrong offset | Offset is wrong sign | Negate: `-(getOffset / 60)` |
| Double conversion | Time gets worse with each save | Don't call toUTC() if already UTC |
| Empty session | session('client_timezone') is null | Check middleware is registered |
| Wrong timezone | Wrong time every time | Select correct timezone in settings |

---

## 🔧 Key Commands

### Database Check
```sql
-- Check settings
SELECT * FROM xs_settings WHERE key LIKE 'localization.timezone';

-- Check times
SELECT id, start_time, end_time FROM xs_appointments LIMIT 3;

-- Check offset math
-- Africa/Johannesburg = UTC+2, so offset should be -120
SELECT (2 * -60) as expected_offset_minutes;
```

### Browser Console
```javascript
// Check timezone
window.DEBUG_TIMEZONE.logInfo();

// Check conversion
window.DEBUG_TIMEZONE.logTime('2025-10-24T08:30:00Z');
// Should show: UTC: 2025-10-24T08:30:00Z
// Local: 2025-10-24 10:30:00 (in Johannesburg)
```

### Backend Test
```php
// In any controller, add temporary code:
use App\Services\TimezoneService;

$utc = TimezoneService::toUTC('2025-10-24 10:30:00', 'Africa/Johannesburg');
echo "Local 10:30 → UTC: $utc"; // Should be 08:30:00

$local = TimezoneService::fromUTC('2025-10-24 08:30:00', 'Africa/Johannesburg');
echo "UTC 08:30 → Local: $local"; // Should be 10:30:00
```

---

## 🎯 Priority Order

**1. CHECK** (Read-only, 20 min)
- [ ] Settings page - Is timezone saved?
- [ ] DevTools - Are headers sent?
- [ ] Database - Are times UTC?
- [ ] Console - Does debug work?

**2. FIX** (Make changes, 30 min)
- [ ] Missing headers → Add `attachTimezoneHeaders()`
- [ ] Missing session → Check middleware
- [ ] Wrong conversion → Fix `toUTC()` / `fromUTC()`
- [ ] Wrong display → Fix calendar config

**3. TEST** (Verify, 30 min)
- [ ] Create appointment in one timezone
- [ ] Verify display matches
- [ ] Change timezone
- [ ] Verify display updates
- [ ] Check database still UTC

**4. DOCUMENT** (Record findings, 20 min)
- [ ] Note what was broken
- [ ] Note what you fixed
- [ ] Add test case
- [ ] Create PR with detailed description

---

## ✅ Definition of Done

Task complete when:
- [ ] All 7 verification sections pass (from full task doc)
- [ ] All 4 test cases pass
- [ ] Appointment created at 10:30 displays at 10:30 (not 08:30)
- [ ] Works in multiple timezones
- [ ] No console errors
- [ ] Code reviewed and approved

---

## 🆘 Stuck? 

1. **Check the full task doc:** `docs/development/DEVELOPER-TASK-timezone-alignment.md`
2. **Review examples:** `docs/development/timezone-implementation-guide.md`
3. **Search logs:** Look for `[timezone-helper]` or `[TIMEZONE]` in browser/server logs
4. **Browser console:** Run `window.DEBUG_TIMEZONE.logInfo()` to see current state
5. **Ask:** Contact dev team lead with findings from checklist

---

## 📊 Progress Tracker

**Section 1: Settings** ___/✅  
**Section 2: Headers** ___/✅  
**Section 3: Session** ___/✅  
**Section 4: Service** ___/✅  
**Section 5: Database** ___/✅  
**Section 6: Calendar** ___/✅  
**Section 7: Flow** ___/✅  

**Tests Passed:** ___/4  
**Total Progress:** ___%

---

**Time Started:** ________  
**Time Ended:** ________  
**Issues Found:** ________  
**Fixes Applied:** ________  

---

Last Updated: October 24, 2025

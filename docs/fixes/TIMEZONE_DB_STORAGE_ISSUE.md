# 🔴 CRITICAL: Database Timezone Storage Issue

**Date**: October 26, 2025  
**Discovered During**: Calendar sync investigation  
**Severity**: CRITICAL - Data integrity issue

---

## 🔍 Problem Summary

**User Report:**
- Booked appointment for: **10:30 AM - 11:00 AM**
- Calendar shows appointment at: **08:00 AM**
- Modal shows appointment at: **12:30 PM**
- All three times are DIFFERENT for the same appointment!

**Root Cause Found:**
Database is storing **LOCAL TIME** instead of **UTC**, despite code attempting to convert to UTC.

---

## 📊 Evidence

### Database Content:
```sql
SELECT id, start_time, end_time, created_at 
FROM xs_appointments 
ORDER BY id DESC LIMIT 1;

+----+---------------------+---------------------+---------------------+
| id | start_time          | end_time            | created_at          |
+----+---------------------+---------------------+---------------------+
|  1 | 2025-10-24 10:30:00 | 2025-10-24 11:30:00 | 2025-10-23 17:32:00 |
+----+---------------------+---------------------+---------------------+
```

### Settings:
```sql
SELECT setting_key, setting_value FROM xs_settings WHERE setting_key = 'localization.timezone';
+-----------------------+---------------------+
| localization.timezone | Africa/Johannesburg |
+-----------------------+---------------------+
```

### Timezone: Africa/Johannesburg
- **Offset**: UTC+2 (SAST - South Africa Standard Time)
- **No DST**: South Africa does not observe daylight saving time

---

## 🧮 Expected vs Actual

### What SHOULD happen:
1. User books: **10:30 AM SAST** (local time in Africa/Johannesburg)
2. Backend converts: **10:30 SAST → 08:30 UTC** (subtract 2 hours)
3. Database stores: **`2025-10-24 08:30:00`** (UTC)
4. API returns: **`2025-10-24 08:30:00`** (UTC)
5. FullCalendar converts: **08:30 UTC → 10:30 SAST** (add 2 hours)
6. Calendar displays: **10:30 AM** ✅
7. Modal displays: **10:30 AM** ✅

### What ACTUALLY happens:
1. User books: **10:30 AM SAST**
2. Backend stores: **`2025-10-24 10:30:00`** (LOCAL TIME, NOT UTC!) ❌
3. API returns: **`2025-10-24 10:30:00`**
4. FullCalendar interprets as UTC and converts: **10:30 UTC → 12:30 SAST** (add 2 hours) ❌
5. Calendar displays: **08:00 AM** (???) ❌
6. Modal displays: **12:30 PM** ❌

---

## 🔎 Code Analysis

### Backend Code (`app/Controllers/Appointments.php`):
```php
// Line 210-238
$clientTimezone = $this->resolveClientTimezone();
$startTimeLocal = $appointmentDate . ' ' . $appointmentTime . ':00';

// Create DateTime in client timezone
$startDateTime = new \DateTime($startTimeLocal, new \DateTimeZone($clientTimezone));

// Calculate end time
$endDateTime = clone $startDateTime;
$endDateTime->modify('+' . (int) $service['duration_min'] . ' minutes');

// Convert to UTC for storage
$startTimeUtc = TimezoneService::toUTC($startDateTime->format('Y-m-d H:i:s'), $clientTimezone);
$endTimeUtc = TimezoneService::toUTC($endDateTime->format('Y-m-d H:i:s'), $clientTimezone);

// Create appointment
$appointmentData = [
    'start_time' => $startTimeUtc,  // Should be UTC
    'end_time' => $endTimeUtc,      // Should be UTC
    // ...
];
```

**This code LOOKS correct** - it calls `TimezoneService::toUTC()` to convert.

### TimezoneService (`app/Services/TimezoneService.php`):
```php
public static function toUTC($localTime, $timezone = null)
{
    if (!$timezone) {
        $timezone = self::getSessionTimezone();
    }
    
    try {
        $tz = new DateTimeZone($timezone);
        $date = new DateTime($localTime, $tz);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
        log_message('error', 'TimezoneService::toUTC - Conversion error: ' . $e->getMessage());
        // Fallback: assume input is already UTC
        return $localTime;  // ⚠️ DANGEROUS FALLBACK!
    }
}
```

**Potential Issues:**
1. ✅ Code structure is correct
2. ❓ Exception might be caught silently
3. ❓ `$timezone` might be NULL or invalid
4. ❓ `resolveClientTimezone()` might not be working

---

## 🧪 Diagnosis Tests

### Test 1: Check if timezone is being resolved
```php
// In Appointments::store(), add before toUTC():
log_message('debug', '[TEST] Client timezone: ' . $clientTimezone);
log_message('debug', '[TEST] Start time local: ' . $startDateTime->format('Y-m-d H:i:s'));
log_message('debug', '[TEST] Calling toUTC with timezone: ' . $clientTimezone);
```

### Test 2: Test TimezoneService directly
```php
// In a test script:
$local = '2025-10-24 10:30:00';
$tz = 'Africa/Johannesburg';
$utc = TimezoneService::toUTC($local, $tz);
echo "Local: $local ($tz)\n";
echo "UTC: $utc\n";
// Expected output: UTC: 2025-10-24 08:30:00
```

### Test 3: Check what's in session
```php
// In resolveClientTimezone():
log_message('debug', '[TEST] Header timezone: ' . $headerTimezone);
log_message('debug', '[TEST] Post timezone: ' . $postTimezone);
log_message('debug', '[TEST] Session timezone: ' . session()->get('client_timezone'));
log_message('debug', '[TEST] Resolved to: ' . $timezoneCandidate);
```

---

## 🔧 Potential Fixes

### Fix 1: Force explicit timezone in database
**Problem**: MySQL `DATETIME` doesn't store timezone information.  
**Solution**: Use `TIMESTAMP` instead (stores UTC automatically) or explicitly convert.

```sql
-- Option A: Change column type
ALTER TABLE xs_appointments 
MODIFY COLUMN start_time TIMESTAMP NOT NULL,
MODIFY COLUMN end_time TIMESTAMP NOT NULL;

-- Option B: Add timezone indicator column
ALTER TABLE xs_appointments
ADD COLUMN timezone VARCHAR(64) DEFAULT 'UTC' AFTER end_time;
```

### Fix 2: Verify TimezoneService is working
Add explicit logging to PROVE conversion is happening:

```php
// In Appointments::store()
log_message('info', '[TIMEZONE] Before conversion: ' . $startTimeLocal . ' (' . $clientTimezone . ')');
$startTimeUtc = TimezoneService::toUTC($startDateTime->format('Y-m-d H:i:s'), $clientTimezone);
log_message('info', '[TIMEZONE] After conversion: ' . $startTimeUtc . ' (UTC)');

// In TimezoneService::toUTC()
log_message('debug', '[TimezoneService::toUTC] Input: ' . $localTime . ' | Timezone: ' . $timezone);
$result = $date->format('Y-m-d H:i:s');
log_message('debug', '[TimezoneService::toUTC] Output: ' . $result);
return $result;
```

### Fix 3: Check resolveClientTimezone()
```php
private function resolveClientTimezone(): string
{
    $headerTimezone = trim((string) $this->request->getHeaderLine('X-Client-Timezone'));
    $postTimezone = (string) $this->request->getPost('client_timezone');
    
    $timezoneCandidate = $headerTimezone ?: $postTimezone;
    
    // ADD THIS LOGGING:
    log_message('debug', '[resolveClientTimezone] Header: ' . $headerTimezone);
    log_message('debug', '[resolveClientTimezone] Post: ' . $postTimezone);
    log_message('debug', '[resolveClientTimezone] Candidate: ' . $timezoneCandidate);
    
    // Validate before using
    if ($timezoneCandidate && TimezoneService::isValidTimezone($timezoneCandidate)) {
        log_message('debug', '[resolveClientTimezone] Using: ' . $timezoneCandidate);
        return $timezoneCandidate;
    }
    
    // Fallback
    $fallback = 'Africa/Johannesburg'; // Or from settings
    log_message('warning', '[resolveClientTimezone] Invalid/missing timezone, using fallback: ' . $fallback);
    return $fallback;
}
```

---

## 🚨 Impact Assessment

### Current Impact:
- ❌ **All appointments** are likely stored in local time, not UTC
- ❌ **Calendar display** is incorrect (off by timezone offset)
- ❌ **Modal display** is incorrect (double conversion)
- ❌ **Multi-timezone support** is completely broken
- ❌ **Data integrity** compromised - can't tell if times are UTC or local

### Affected Features:
- Appointment creation ❌
- Appointment display ❌
- Calendar rendering ❌
- Appointment editing ❌
- Appointment reminders ❌ (if based on time)
- Reporting ❌ (if time-based)

---

## 📝 Action Plan

### Immediate Actions (CRITICAL):
1. ✅ **Add comprehensive logging** to prove where conversion fails
2. ⚠️ **Test TimezoneService** with known inputs
3. ⚠️ **Check database** for existing appointments - are they UTC or local?
4. ⚠️ **Determine data migration** strategy if all existing data is wrong

### Short-term (This Week):
1. Fix the timezone conversion bug
2. Add validation to ensure timezone is always valid
3. Add explicit timezone column to database for clarity
4. Test appointment creation with logging enabled

### Long-term (This Month):
1. Migrate existing appointment data to UTC (if needed)
2. Add database tests for timezone handling
3. Add UI indicator showing timezone (e.g., "10:30 AM SAST")
4. Document timezone handling in developer docs

---

## 🧪 Testing Script

Create: `tests/test_timezone_conversion.php`

```php
<?php

require 'vendor/autoload.php';

use App\Services\TimezoneService;

// Test 1: SAST to UTC
$local = '2025-10-24 10:30:00';
$tz = 'Africa/Johannesburg';
$utc = TimezoneService::toUTC($local, $tz);

echo "Test 1: SAST → UTC\n";
echo "  Input:  $local ($tz)\n";
echo "  Output: $utc (UTC)\n";
echo "  Expected: 2025-10-24 08:30:00\n";
echo "  Result: " . ($utc === '2025-10-24 08:30:00' ? '✅ PASS' : '❌ FAIL') . "\n\n";

// Test 2: UTC to SAST
$utc = '2025-10-24 08:30:00';
$local = TimezoneService::fromUTC($utc, $tz);

echo "Test 2: UTC → SAST\n";
echo "  Input:  $utc (UTC)\n";
echo "  Output: $local ($tz)\n";
echo "  Expected: 2025-10-24 10:30:00\n";
echo "  Result: " . ($local === '2025-10-24 10:30:00' ? '✅ PASS' : '❌ FAIL') . "\n\n";

// Test 3: Check offset
$offset = TimezoneService::getOffsetMinutes($tz);

echo "Test 3: Timezone Offset\n";
echo "  Timezone: $tz\n";
echo "  Offset: $offset minutes\n";
echo "  Expected: -120 (UTC+2 = -120 in PHP convention)\n";
echo "  Result: " . ($offset === -120 ? '✅ PASS' : '❌ FAIL') . "\n";
```

Run: `php tests/test_timezone_conversion.php`

---

## 🎯 Success Criteria

After fix is implemented:

✅ Test script passes all 3 tests  
✅ New appointment created for 10:30 AM stores as 08:30 UTC in database  
✅ Calendar displays appointment at 10:30 AM  
✅ Modal displays appointment at 10:30 AM  
✅ Logs show clear conversion: "10:30 SAST → 08:30 UTC"  
✅ Existing appointments display correctly (after migration)  

---

## 📚 Related Files

- `app/Controllers/Appointments.php` - Appointment creation
- `app/Services/TimezoneService.php` - UTC conversion logic
- `app/Controllers/Api/Appointments.php` - API response
- `resources/js/modules/appointments/appointments-calendar.js` - Calendar rendering
- `resources/js/utils/timezone-helper.js` - Frontend timezone utilities

---

**Status**: 🔴 **INVESTIGATION IN PROGRESS**  
**Next Step**: Add logging and create test script to identify exact failure point

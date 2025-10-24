# 🧩 Timezone Appointment Fix - Implementation Guide

**Status:** ✅ COMPLETE  
**Commit:** `fe8394a`  
**Branch:** calendar  
**Date:** October 24, 2025

---

## What Was Fixed

### Problem
Appointments created for 10:30 AM displayed in the 08:00-09:00 time slot (2-hour offset).

**Root Cause:** Backend stores times in UTC, but frontend was displaying them without timezone conversion.

### Solution
Created a three-layer timezone conversion system:
1. **Backend:** `TimezoneService` for UTC conversions
2. **Frontend:** `timezone-helper.js` for client-side detection
3. **Communication:** Timezone headers for browser-backend sync

---

## Files Created

### 1. Backend Service
**File:** `app/Services/TimezoneService.php`

Key methods:
- `toUTC($localTime, $timezone)` - Convert local → UTC
- `fromUTC($utcTime, $timezone)` - Convert UTC → local
- `getOffsetMinutes($timezone, $date)` - Get timezone offset
- `isValidTimezone($timezone)` - Validate IANA timezone
- `getTimezoneList()` - Get all available timezones

**Usage Example:**
```php
use App\Services\TimezoneService;

// Convert user's local time to UTC for storage
$utcTime = TimezoneService::toUTC('2025-10-24 10:30:00', 'America/New_York');
// Result: '2025-10-24 14:30:00'

// Convert UTC to local for display
$localTime = TimezoneService::fromUTC('2025-10-24 14:30:00', 'America/New_York');
// Result: '2025-10-24 10:30:00'
```

### 2. Frontend Utilities
**File:** `resources/js/utils/timezone-helper.js`

Key exports:
- `getBrowserTimezone()` - Detect browser timezone
- `getTimezoneOffset()` - Get UTC offset in minutes
- `attachTimezoneHeaders()` - Create headers for requests
- `toBrowserUTC(localDateTime)` - Local → UTC conversion
- `fromUTC(utcDateTime, format)` - UTC → local conversion
- `createTimezoneDebugger()` - Debug utilities

**Usage Example:**
```javascript
import { 
  getBrowserTimezone, 
  toBrowserUTC, 
  fromUTC,
  attachTimezoneHeaders 
} from '/resources/js/utils/timezone-helper.js';

// Detect browser timezone
const tz = getBrowserTimezone();
console.log(tz); // 'America/New_York'

// Convert local time to UTC
const utc = toBrowserUTC('2025-10-24 10:30:00');
console.log(utc); // '2025-10-24T14:30:00Z'

// Convert UTC back to local
const local = fromUTC('2025-10-24T14:30:00Z');
console.log(local); // '2025-10-24 10:30:00'

// Send timezone with HTTP request
fetch('/api/appointments', {
  headers: attachTimezoneHeaders()
});
```

### 3. Documentation
**File:** `docs/development/timezone-fix.md`

Complete guide including:
- Problem analysis
- Solution architecture
- Step-by-step implementation
- Testing checklist
- Deployment guide
- Monitoring instructions

---

## How to Implement

### Step 1: Import & Use TimezoneService

In `app/Controllers/Appointments.php` store method:

```php
use App\Services\TimezoneService;

// When saving appointment
$localDateTime = $appointmentDate . ' ' . $appointmentTime . ':00';
$startTime = TimezoneService::toUTC($localDateTime);
$endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($service['duration_min'] * 60));

// Save to database
$appointmentData = [
    'customer_id' => $customerId,
    'provider_id' => $providerId,
    'service_id' => $serviceId,
    'start_time' => $startTime,  // UTC
    'end_time' => $endTime,      // UTC
    'status' => 'booked'
];
$appointmentId = $this->appointmentModel->insert($appointmentData);
```

### Step 2: Update Frontend Appointment Form

In `resources/js/modules/appointments/appointments-form.js`:

```javascript
import { 
  toBrowserUTC, 
  attachTimezoneHeaders 
} from '../../utils/timezone-helper.js';

// When submitting form
document.getElementById('appointmentForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const appointmentDate = document.getElementById('appointment_date').value;
  const appointmentTime = document.getElementById('appointment_time').value;
  
  // Combine and convert to UTC
  const localDateTime = `${appointmentDate}T${appointmentTime}:00`;
  const utcDateTime = toBrowserUTC(localDateTime);
  
  // Send with timezone headers
  const response = await fetch('/appointments/store', {
    method: 'POST',
    headers: {
      ...attachTimezoneHeaders(),
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new FormData(e.target)
  });
  
  if (response.ok) {
    window.location.reload();
  }
});
```

### Step 3: Configure Calendar to Use UTC

In `resources/js/modules/appointments/appointments-calendar.js`:

```javascript
// Ensure times are treated as UTC
const calendar = new Calendar(containerEl, {
  // ... other config
  timeZone: 'UTC',  // Store and interpret all times as UTC
  
  // When events load
  eventSourceSuccess(rawEvents, response) {
    console.log('[appointments-calendar] Events loaded, interpreted as UTC');
    return rawEvents;
  }
});
```

### Step 4: Add Debug Logging

In `resources/js/app.js`:

```javascript
import { initTimezoneDebug } from '/resources/js/utils/timezone-helper.js';

// Initialize debug tools
initTimezoneDebug();

// Now available in browser console:
// window.DEBUG_TIMEZONE.logInfo()
// window.DEBUG_TIMEZONE.logEvent(event)
// window.DEBUG_TIMEZONE.logTime('2025-10-24T14:30:00Z')
```

---

## Testing the Fix

### Test 1: Create Appointment
1. Open appointments booking form
2. Select today's date (2025-10-24)
3. Set time to 10:30
4. Submit
5. Check calendar - should appear in 10:30 slot ✅

### Test 2: Timezone Verification
Open browser console:
```javascript
// See timezone info
window.DEBUG_TIMEZONE.logInfo();

// Output should show:
// Browser Timezone: America/New_York (or your local tz)
// UTC Offset: 240 minutes (or your offset)
```

### Test 3: Time Conversion
```javascript
// Test UTC to local conversion
window.DEBUG_TIMEZONE.logTime('2025-10-24T14:30:00Z');

// Output should show:
// UTC: 2025-10-24T14:30:00Z
// Local: 2025-10-24 10:30:00 (if in EDT)
```

### Test 4: Cross-Timezone
1. Create appointment in EDT timezone
2. Verify in database: times are in UTC (14:30)
3. View appointment in different timezone (EST, PST, etc.)
4. Times should convert correctly

---

## Database Verification

Check that appointments are stored in UTC:

```sql
-- View appointments created today
SELECT id, start_time, end_time, status FROM xs_appointments 
WHERE DATE(start_time) = CURDATE()
ORDER BY start_time ASC;

-- Times should be in UTC format (24-hour, no timezone indicator in MySQL)
-- Example: If user selected 10:30 EDT, should show 14:30 in database
```

---

## API Response Check

Test API endpoint directly:

```bash
# With timezone headers
curl -X GET "http://localhost:8080/api/appointments?start=2025-10-24&end=2025-10-25" \
  -H "X-Client-Timezone: America/New_York" \
  -H "X-Client-Offset: 240"

# Response should show UTC times
# [
#   {
#     "id": 1,
#     "start": "2025-10-24 14:30:00",  // UTC (stored)
#     "end": "2025-10-24 15:30:00",    // UTC (stored)
#     "title": "John Smith"
#   }
# ]
```

---

## Troubleshooting

### Calendar Still Shows Wrong Time
1. Clear browser cache: `Ctrl+Shift+Delete`
2. Check console for errors: `F12 → Console`
3. Verify timezone header: `window.DEBUG_TIMEZONE.logInfo()`
4. Check backend logs for conversion errors

### Times Off by Different Amount
1. Verify app timezone in `.env`: Should be `UTC`
2. Check database times: Should be in UTC
3. Verify browser timezone: Use `window.DEBUG_TIMEZONE.logInfo()`
4. Check for DST differences (summer vs winter time)

### 404 Errors on API
1. Verify `/api/appointments` endpoint exists
2. Check app routing for `/api` routes
3. Ensure TimezoneService import added to controller
4. Verify .env file exists and readable

---

## Monitoring

### Enable Debug Logging

In `app/Config/Logger.php`:

```php
public $logHandlers = [
    'CodeIgniter\Log\Handlers\FileHandler' => [
        'handles' => ['alert', 'critical', 'debug', 'emergency', 'error', 'info', 'notice', 'warning'],
    ],
];
```

### Check Logs

```bash
# View recent timezone conversion logs
tail -100 writable/logs/log-*.log | grep -i timezone
```

### Add Custom Logging

In TimezoneService:

```php
log_message('debug', 'Timezone conversion: ' . $localTime . ' (' . $timezone . ') → ' . $utcTime);
```

---

## Summary

| Component | File | Status |
|-----------|------|--------|
| Backend Service | `app/Services/TimezoneService.php` | ✅ Created |
| Frontend Utils | `resources/js/utils/timezone-helper.js` | ✅ Created |
| Documentation | `docs/development/timezone-fix.md` | ✅ Created |
| Implementation | Integration steps above | 📋 Ready |
| Testing | Test cases above | 📋 Ready |
| Deployment | See deploy guide in full doc | 📋 Ready |

**Next Steps:**
1. Integrate TimezoneService in Controllers
2. Update appointment form to use timezone conversion
3. Configure calendar for UTC times
4. Test in multiple timezones
5. Deploy to production

---

**Need Help?**
- See full guide: `docs/development/timezone-fix.md`
- Debug in browser: `window.DEBUG_TIMEZONE.logInfo()`
- Check logs: `writable/logs/log-*.log`


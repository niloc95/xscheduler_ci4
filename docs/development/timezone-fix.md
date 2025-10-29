# Timezone Mismatch Fix - Appointment Time Offset Bug

**Issue:** Appointments created for 10:30 display in the 08:00 slot (2-hour offset)

**Root Cause:** Timezone mismatch between UTC (backend) and user's local time (frontend)

**Status:** 🔧 IN PROGRESS  
**Date:** October 24, 2025

---

## Problem Analysis

### Symptom
- **Expected:** Appointment created for 10:30 appears in 10:30-11:30 slot
- **Actual:** Appointment appears in 08:00-09:00 slot (2-hour offset)
- **Indicator:** 2-hour difference suggests EDT/EST timezone at client, UTC at server

### Root Cause Chain

1. **Backend Stores in UTC:**
   - `app/Config/App.php` sets `$appTimezone = 'UTC'`
   - Database stores times as-is in UTC
   - When user enters 10:30 local time, backend saves as 10:30 UTC

2. **Frontend Interprets as Different Timezone:**
   - FullCalendar reads from API response (10:30 UTC)
   - JavaScript converts 10:30 UTC to browser's local time
   - If browser is EDT (UTC-4), displays as 06:30
   - If browser is EST (UTC-5), displays as 05:30
   - **Actual observation: 2-hour offset → showing at 08:00 instead of 10:30**

3. **Appointment Creation Flow:**
   ```
   User Input (10:30 Local)
       ↓
   JavaScript (no conversion)
       ↓
   Sent to backend as "10:30"
       ↓
   Backend stores as 10:30 UTC (WRONG - should convert to UTC first)
       ↓
   Database: 10:30 UTC
       ↓
   API returns: 10:30 (as stored)
       ↓
   Frontend displays at browser's local time interpretation
   ```

---

## Solution Architecture

### Three-Layer Fix Required

**Layer 1: Backend - Normalize Incoming Times**
- Detect browser timezone from request
- Convert user's local time to UTC before storage
- Store consistently in UTC

**Layer 2: Database - Store in UTC**
- All times stored as UTC (already configured)
- Timestamps include explicit UTC designation

**Layer 3: Frontend - Interpret as UTC**
- FullCalendar configured to treat times as UTC
- Convert UTC to browser's local time for display
- Send UTC times when creating appointments

---

## Implementation Steps

### Step 1: Add Timezone Detection Middleware

**File: `app/Middleware/TimezoneDetection.php` (NEW)**

```php
<?php

namespace App\Middleware;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Closure;

class TimezoneDetection
{
    /**
     * Handle timezone detection from client
     * 
     * Expected header format:
     * X-Client-Timezone: America/New_York
     * X-Client-Offset: -240  (in minutes from UTC)
     */
    public function before(RequestInterface &$request, ?Closure $next = null)
    {
        // Get timezone from client (sent by JavaScript)
        $clientTimezone = $request->getHeaderLine('X-Client-Timezone');
        $clientOffset = $request->getHeaderLine('X-Client-Offset');
        
        // Store in session for later use
        if ($clientTimezone) {
            session()->set('client_timezone', $clientTimezone);
            log_message('debug', "Client timezone detected: {$clientTimezone}");
        }
        
        if ($clientOffset !== '') {
            session()->set('client_offset_minutes', (int)$clientOffset);
            log_message('debug', "Client offset detected: {$clientOffset} minutes");
        }
        
        return $next($request);
    }

    public function after(RequestInterface &$request, ResponseInterface &$response, ?Closure $next = null)
    {
        return $next($request);
    }
}
```

### Step 2: Add Frontend Timezone Helper

**File: `resources/js/utils/timezone-helper.js` (NEW)**

```javascript
/**
 * Client-side timezone detection and conversion utilities
 */

/**
 * Detect browser's timezone
 * @returns {string} IANA timezone identifier (e.g., 'America/New_York')
 */
export function getBrowserTimezone() {
  // Use Intl API for timezone detection (modern browsers)
  if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
  }
  
  // Fallback: calculate offset
  const offset = new Date().getTimezoneOffset();
  console.warn('[timezone-helper] Could not detect timezone via Intl, using offset:', offset);
  return `UTC${offset > 0 ? '-' : '+'}${Math.abs(offset / 60)}`;
}

/**
 * Get timezone offset in minutes
 * @returns {number} Minutes offset from UTC (negative for east of UTC)
 */
export function getTimezoneOffset() {
  return new Date().getTimezoneOffset();
}

/**
 * Send timezone info to server via headers
 * @param {XMLHttpRequest|fetch} client - HTTP client to configure
 */
export function attachTimezoneHeaders() {
  const timezone = getBrowserTimezone();
  const offset = getTimezoneOffset();
  
  // Store for fetch interceptor
  window.__timezone = { timezone, offset };
  
  console.log('[timezone-helper] Browser timezone:', timezone, 'Offset:', offset, 'minutes');
  
  return {
    'X-Client-Timezone': timezone,
    'X-Client-Offset': offset.toString()
  };
}

/**
 * Convert local time to UTC
 * @param {Date|string} localDateTime - Local datetime
 * @param {string} timezone - IANA timezone (if different from browser)
 * @returns {string} ISO string in UTC
 */
export function toUTC(localDateTime, timezone = null) {
  const date = new Date(localDateTime);
  if (isNaN(date.getTime())) {
    throw new Error(`Invalid date: ${localDateTime}`);
  }
  
  // If specific timezone provided, would need Temporal API (future)
  // For now, assume localDateTime is in browser's timezone
  
  return date.toISOString();
}

/**
 * Convert UTC time to local display string
 * @param {string} utcDateTime - UTC datetime (ISO string or YYYY-MM-DD HH:mm:ss)
 * @param {string} format - Format string (default: 'YYYY-MM-DD HH:mm:ss')
 * @returns {string} Formatted local time
 */
export function fromUTC(utcDateTime, format = 'YYYY-MM-DD HH:mm:ss') {
  const date = new Date(utcDateTime + 'Z'); // Add Z to ensure UTC interpretation
  
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');
  
  return format
    .replace('YYYY', year)
    .replace('MM', month)
    .replace('DD', day)
    .replace('HH', hours)
    .replace('mm', minutes)
    .replace('ss', seconds);
}
```

### Step 3: Update Appointment Creation

**File: `resources/js/modules/appointments/appointments-form.js`**

```javascript
// Add at top of file
import { 
  toBrowserUTC, 
  attachTimezoneHeaders 
} from '../../utils/timezone-helper.js';

// In form submission handler
document.getElementById('appointmentForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const appointmentDate = document.getElementById('appointment_date').value;
  const appointmentTime = document.getElementById('appointment_time').value;
  
  // Combine date and time as local datetime
  const localDateTime = `${appointmentDate}T${appointmentTime}:00`;
  
  // Convert to UTC for storage
  const utcDateTime = toBrowserUTC(localDateTime);
  
  // Split into start and end times
  const serviceDuration = parseInt(document.getElementById('service_id').dataset.duration || 60);
  const startTime = utcDateTime.split('T')[0] + ' ' + utcDateTime.split('T')[1].substring(0, 5) + ':00';
  const endDateTime = new Date(new Date(utcDateTime).getTime() + serviceDuration * 60000);
  const endTime = endDateTime.toISOString().split('T')[0] + ' ' + 
                  endDateTime.toISOString().split('T')[1].substring(0, 8);
  
  // Submit with timezone headers
  const formData = new FormData(e.target);
  
  try {
    const response = await fetch('/appointments/store', {
      method: 'POST',
      headers: {
        ...attachTimezoneHeaders(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    });
    
    if (response.ok) {
      // Success - refresh calendar
      window.location.reload();
    }
  } catch (error) {
    console.error('Failed to create appointment:', error);
  }
});
```

### Step 4: Backend Time Conversion

**File: `app/Services/TimezoneService.php` (NEW)**

```php
<?php

namespace App\Services;

use DateTime;
use DateTimeZone;

class TimezoneService
{
    /**
     * Convert local time to UTC
     * 
     * @param string $localTime Local datetime (YYYY-MM-DD HH:mm:ss or ISO 8601)
     * @param string|null $timezone User's timezone (IANA format, e.g., America/New_York)
     * @return string UTC datetime (YYYY-MM-DD HH:mm:ss)
     */
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
            log_message('error', "Timezone conversion error: {$e->getMessage()}");
            // Fallback: assume input is already UTC
            return $localTime;
        }
    }
    
    /**
     * Convert UTC time to local timezone
     * 
     * @param string $utcTime UTC datetime (YYYY-MM-DD HH:mm:ss)
     * @param string|null $timezone Target timezone
     * @return string Local datetime
     */
    public static function fromUTC($utcTime, $timezone = null)
    {
        if (!$timezone) {
            $timezone = self::getSessionTimezone();
        }
        
        try {
            $date = new DateTime($utcTime, new DateTimeZone('UTC'));
            $tz = new DateTimeZone($timezone);
            $date->setTimezone($tz);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            log_message('error', "Timezone conversion error: {$e->getMessage()}");
            return $utcTime;
        }
    }
    
    /**
     * Get timezone offset in minutes
     * 
     * @param string $timezone IANA timezone
     * @param DateTime|null $date Reference date (for DST handling)
     * @return int Offset in minutes
     */
    public static function getOffsetMinutes($timezone, $date = null)
    {
        if (!$date) {
            $date = new DateTime('now');
        }
        
        try {
            $tz = new DateTimeZone($timezone);
            $date->setTimezone($tz);
            return -($tz->getOffset($date) / 60); // Negate because PHP offset is opposite
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get timezone from session or client header
     * 
     * @return string IANA timezone identifier
     */
    private static function getSessionTimezone()
    {
        $session = session();
        
        if ($session->has('client_timezone')) {
            return $session->get('client_timezone');
        }
        
        // Fallback to app timezone
        return config('App')->appTimezone;
    }
}
```

### Step 5: Update Appointment Store Method

**File: `app/Controllers/Appointments.php`**

Update the store method to convert times:

```php
// In store() method, replace the time calculation:

// OLD CODE:
// $startTime = $appointmentDate . ' ' . $appointmentTime . ':00';
// $endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($service['duration_min'] * 60));

// NEW CODE:
use App\Services\TimezoneService;

// Combine date and time from local input
$localDateTime = $appointmentDate . ' ' . $appointmentTime . ':00';

// Convert to UTC for storage
$startTime = TimezoneService::toUTC($localDateTime);

// Calculate end time in UTC
$endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($service['duration_min'] * 60));
```

### Step 6: Configure FullCalendar Timezone

**File: `resources/js/modules/appointments/appointments-calendar.js`**

Update calendar initialization:

```javascript
// Replace timeZone configuration:
const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const calendar = new Calendar(containerEl, {
  // ... other config
  
  // Set timezone explicitly
  timeZone: 'UTC',  // Store and interpret all times as UTC
  
  // Ensure event dates are treated as UTC
  datesSet(info) {
    console.log('[appointments-calendar] Calendar dates changed:', 
      info.startStr, 'to', info.endStr);
  },
  
  // Parse event times correctly
  eventSourceSuccess(rawEvents, response) {
    console.log('[appointments-calendar] Events loaded, interpreting as UTC');
    return rawEvents;
  }
});
```

### Step 7: Add Console Logging

**File: `resources/js/app.js`**

Add logging helper:

```javascript
// Add at app initialization
window.DEBUG_TIMEZONE = {
  browser: Intl.DateTimeFormat().resolvedOptions().timeZone,
  offset: new Date().getTimezoneOffset(),
  now: new Date().toISOString(),
  localNow: new Date().toString(),
  
  logEvent(event) {
    console.group(`%c[DEBUG] Event: ${event.title}`, 'color: blue; font-weight: bold');
    console.log('ID:', event.id);
    console.log('Start (UTC):', event.startStr);
    console.log('End (UTC):', event.endStr);
    console.log('Browser timezone:', this.browser);
    console.log('Browser offset:', this.offset, 'minutes');
    console.groupEnd();
  }
};

// In calendar initialization
if (window.appointmentsCalendar) {
  window.appointmentsCalendar.on('eventDidMount', (info) => {
    window.DEBUG_TIMEZONE.logEvent(info.event);
  });
}
```

---

## Testing Checklist

### Backend Tests
- [ ] `TimezoneService::toUTC()` converts EDT to UTC correctly
- [ ] `TimezoneService::fromUTC()` converts UTC to local correctly  
- [ ] Appointment stored with UTC timestamps
- [ ] API returns UTC times
- [ ] Logs show timezone detection

### Frontend Tests
- [ ] Browser timezone detected correctly
- [ ] Timezone headers sent to server
- [ ] Calendar displays times in browser's local timezone
- [ ] Create appointment form submits converted UTC time
- [ ] Console shows DEBUG_TIMEZONE info
- [ ] Appointment appears in correct slot

### Cross-Timezone Tests
- [ ] Appointment created in EDT displays correctly in EDT timezone
- [ ] Same appointment shows correct time when viewed from UTC
- [ ] Daylight saving time transitions handled correctly
- [ ] International timezones tested (Tokyo, London, Sydney)

---

## Deployment Checklist

- [ ] Add middleware to `app/Config/Middleware.php`
- [ ] Update `resources/js/modules/appointments/appointments-form.js`
- [ ] Update `resources/js/modules/appointments/appointments-calendar.js`
- [ ] Update `app/Controllers/Appointments.php` store method
- [ ] Create new `TimezoneService` class
- [ ] Add timezone headers in fetch calls
- [ ] Run database migrations (if any)
- [ ] Clear browser cache and rebuild assets
- [ ] Test in multiple timezones
- [ ] Monitor logs for timezone issues

---

## Monitoring & Debugging

### Enable Debug Output
```javascript
// In browser console
window.DEBUG_TIMEZONE.logEvent(lastClickedEvent);

// Or in PHP logs
log_message('debug', 'Timezone: ' . session()->get('client_timezone'));
```

### Check Database Times
```sql
-- Verify appointments stored in UTC
SELECT id, start_time, end_time, status FROM xs_appointments 
WHERE DATE(start_time) = CURDATE()
ORDER BY start_time ASC;
```

### API Response Check
```bash
# Call API directly to see times
curl -H "X-Client-Timezone: America/New_York" \
     -H "X-Client-Offset: 240" \
     "http://localhost:8080/api/appointments?start=2025-10-24&end=2025-10-25"
```

---

## Summary

**Problem:** 2-hour time offset in calendar display  
**Cause:** Backend UTC vs Frontend Local timezone mismatch  
**Solution:** Three-layer conversion (detect → convert → store → retrieve)  
**Status:** Ready for implementation  
**Priority:** 🔴 HIGH (blocking users)


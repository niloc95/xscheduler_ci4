# Calendar Debug Instructions

## Problem
Appointments exist in database but are not visible in the frontend calendar UI.

## Database Verification ✅
```bash
# Appointments in database:
ID: 1 | Date: 2025-10-24 10:30:00 | Customer: Nayna Parshotam
ID: 2 | Date: 2025-10-26 08:30:00 | Customer: Test User
```

## API Verification ✅
```bash
curl "http://localhost:8080/api/appointments?start=2025-10-20&end=2025-10-31"
# Returns 2 appointments in correct JSON format
```

## Frontend Debug Steps

### Step 1: Clear Browser Cache
1. Open the appointments page: http://localhost:8080/appointments
2. Hard refresh: `Cmd + Shift + R` (Mac) or `Ctrl + Shift + R` (Windows/Linux)

### Step 2: Check Browser Console
Open Developer Tools (F12) and look for these console messages:

**Expected Console Output:**
```
🚀 Initializing Custom Scheduler...
⚙️  Loading settings...
✅ Settings loaded
🌍 Timezone: America/New_York
📊 Loading data...
🔄 Loading appointments from: /api/appointments?start=2025-09-28&end=2025-11-01
📥 Raw API response: {data: Array(2), meta: {...}}
📦 Extracted appointments array: Array(2) [{...}, {...}]
📅 Appointments loaded: 2
📋 Appointment details: [{id: "1", ...}, {id: "2", ...}]
✅ Data loaded
🎨 Rendering view...
✅ Custom scheduler initialized
📋 Summary:
   - Providers: X
   - Appointments: 2
   - View: month
   - Timezone: America/New_York
🎨 Rendering view: month
🔍 Filtered appointments for display: 2
👥 Visible providers: [2]
📋 All appointments: 2
🗓️ MonthView.render called
   Current date: 2025-10-27T...
   Appointments received: 2
   Appointments data: [{...}, {...}]
   Providers: X
📅 Appointments grouped by date: {2025-10-24: [...], 2025-10-26: [...]}
```

### Step 3: Check for Errors

**Common Issues to Look For:**

1. **No appointments loaded (count is 0)**
   - Check console for "📅 Appointments loaded: 0"
   - Verify API URL is correct
   - Check Network tab for failed requests

2. **Appointments filtered out**
   - Check "🔍 Filtered appointments for display: 0"
   - Verify provider IDs match: visible providers should include provider ID 2
   - Run in console: `window.scheduler.visibleProviders`

3. **Date mismatch**
   - Verify current month is October 2025
   - Check console for date range in API URL
   - Should be loading: `start=2025-09-28&end=2025-11-01` (covers full October)

4. **Timezone issue**
   - Appointments stored as: 2025-10-24T10:30:00Z (UTC)
   - Should convert to local timezone for display
   - Check startDateTime in console logs

### Step 4: Manual Browser Console Tests

Open browser console and run:

```javascript
// 1. Check scheduler instance
window.scheduler

// 2. Check appointments loaded
window.scheduler.appointments
// Expected: Array(2) with appointment objects

// 3. Check visible providers
Array.from(window.scheduler.visibleProviders)
// Expected: [2] (or array including 2)

// 4. Check filtered appointments
window.scheduler.getFilteredAppointments()
// Expected: Array(2) with appointments

// 5. Re-render manually
window.scheduler.render()

// 6. Check appointment grouping
window.scheduler.views.month.appointmentsByDate
// Expected: Object with keys "2025-10-24" and "2025-10-26"

// 7. Reload appointments manually
await window.scheduler.loadAppointments()
// Should return Array(2)
```

### Step 5: Network Tab Verification

1. Open Network tab in DevTools
2. Refresh page
3. Find request to `/api/appointments?start=...&end=...`
4. Verify:
   - Status: 200 OK
   - Response contains 2 appointments
   - Response headers include `Content-Type: application/json`

### Step 6: Element Inspection

1. Right-click calendar area
2. Select "Inspect Element"
3. Look for:
   - `<div id="appointments-inline-calendar">` should exist
   - Inside should be `.scheduler-month-view`
   - Inside that should be day cells with class `.scheduler-day-cell`
   - Day cells for Oct 24 and 26 should contain `.scheduler-appointment` divs

**Expected HTML Structure:**
```html
<div id="appointments-inline-calendar">
  <div class="scheduler-month-view">
    <div class="grid grid-cols-7">
      <!-- Day cells -->
      <div class="scheduler-day-cell" data-date="2025-10-24">
        <div class="day-number">24</div>
        <div class="day-appointments">
          <div class="scheduler-appointment" data-appointment-id="1">
            10:30 AM Nayna Parshotam
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

## Quick Fixes to Try

### Fix 1: Force Today's Month
In console:
```javascript
window.scheduler.currentDate = DateTime.now().setZone('America/New_York')
await window.scheduler.loadAppointments()
window.scheduler.render()
```

### Fix 2: Clear Provider Filter
In console:
```javascript
window.scheduler.visibleProviders.clear()
window.scheduler.providers.forEach(p => window.scheduler.visibleProviders.add(p.id))
window.scheduler.render()
```

### Fix 3: Manually Set October 2025
In console:
```javascript
window.scheduler.currentDate = DateTime.fromISO('2025-10-27', {zone: 'America/New_York'})
await window.scheduler.loadAppointments()
window.scheduler.render()
```

## What to Report Back

Please provide:
1. **Browser console output** (copy/paste or screenshot)
2. **Network tab response** for `/api/appointments` request
3. **Current month displayed** on calendar
4. **Results of manual console tests** above
5. **HTML structure** of calendar div (right-click → Inspect)

This will help identify exactly where the data flow is breaking.

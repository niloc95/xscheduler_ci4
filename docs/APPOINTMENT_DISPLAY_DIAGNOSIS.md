# Appointment Display Issue - Diagnosis & Resolution

**Date:** November 15, 2025  
**Issue:** Appointments created but not appearing in calendar; only first appointment visible

## Investigation Summary

### ✅ What's Working

1. **Database Layer** 
   - Appointments ARE being created successfully in `xs_appointments` table
   - Latest appointment: ID #13 (created 2025-11-14 13:38:43)
   - Database query: `SELECT * FROM xs_appointments` returns 10 appointments

2. **API Layer**
   - `GET /api/appointments?start=2025-11-01&end=2025-11-30` returns appointments correctly
   - Response includes all fields: id, hash, title, start, end, providerId, serviceId, customerId, status, etc.
   - Provider colors included in response
   - Example: Provider ID 2 has 7 appointments in November

3. **Availability Checking**
   - `POST /api/availability/check` correctly detects conflicts
   - Test: Slot 2025-11-17 13:30-14:30 correctly shows as unavailable (appointment #12 exists)
   - Conflict detection working with detailed reasons

### ❓ Potential Issues

1. **Frontend Provider Filtering**
   - Calendar uses `visibleProviders` Set to filter which appointments display
   - Appointments are filtered by: `this.visibleProviders.has(providerId)`
   - **Hypothesis:** Provider IDs in appointments might not match IDs in visibleProviders Set

2. **Type Mismatch**
   - Providers API returns IDs as integers: `{"id": 2, "name": "Paul Smith..."}`
   - Appointments API returns: `"providerId": 2` (also integer)
   - Code attempts to normalize: `const providerId = typeof apt.providerId === 'string' ? parseInt(apt.providerId, 10) : apt.providerId;`

3. **Initialization Timing**
   - Calendar initializes with: `await Promise.all([loadCalendarConfig(), loadProviders(), loadAppointments()])`
   - All providers added to visibleProviders AFTER data loads
   - Possible race condition if appointments load but providers don't?

## Current System Architecture

### Appointment Creation Endpoints

**Three separate endpoints create appointments:**

1. **Form Submission** - `POST /appointments/store`
   - Controller: `Appointments::store()`
   - Used by: Create Appointment form (`/appointments/create`)
   - Redirects to: `/appointments?refresh=timestamp`
   - ✅ Validates availability before insert
   - ✅ Creates customer if needed
   - ✅ Converts local time to UTC for storage

2. **Legacy API** - `POST /api/book`
   - Controller: `Scheduler::book()`
   - Used by: Legacy integrations
   - ✅ Uses AvailabilityService for slot validation
   - ✅ Creates customer via CustomerModel::findOrCreateByEmail()
   - Status: Marked as legacy, redirects to Appointments module

3. **Modern API** - `POST /api/appointments`
   - Controller: `Api\Appointments::create()`
   - Uses: `SchedulingService::createAppointment()`
   - ✅ Validates availability
   - ✅ JSON-based API response

**No Route Conflicts:** All three endpoints have different paths and purposes.

### Appointment Display Flow

```
1. User navigates to /appointments
2. View loads: appointments/index.php
3. JavaScript initializes: SchedulerCore('#appointments-inline-calendar')
4. Calendar calls:
   a. GET /api/v1/settings/calendarConfig
   b. GET /api/providers?includeColors=true
   c. GET /api/appointments?start=YYYY-MM-DD&end=YYYY-MM-DD
5. Providers added to visibleProviders Set
6. Appointments filtered by providerId ∈ visibleProviders
7. Month/Week/Day view renders filtered appointments
```

## Enhanced Logging (Applied)

Added detailed console logging to diagnose the filtering issue:

**File: `resources/js/modules/scheduler/scheduler-core.js`**

```javascript
// In init() method:
console.log('📋 Raw providers data:', this.providers);
this.providers.forEach(p => {
    const providerId = typeof p.id === 'string' ? parseInt(p.id, 10) : p.id;
    this.visibleProviders.add(providerId);
    console.log(`   ✓ Adding provider ${p.name} (ID: ${providerId}) to visible set`);
});
console.log('📊 Appointments provider IDs:', this.appointments.map(a => `${a.id}: provider ${a.providerId}`));

// In getFilteredAppointments():
console.log('🔍 Filtering appointments...');
console.log('   Total appointments:', this.appointments.length);
console.log('   Visible providers:', Array.from(this.visibleProviders));
// ... detailed per-appointment logging ...
if (filtered.length === 0 && this.appointments.length > 0) {
    console.warn('⚠️  NO APPOINTMENTS VISIBLE - All filtered out!');
}
```

## Next Steps

### 1. Check Browser Console (REQUIRED)

**Open browser console and navigate to `/appointments`**, then look for:

```
📋 Raw providers data: (should show 4 providers with IDs 2, 6, 8, 9)
✓ Adding provider Paul Smith. MD - Nilo 2 (ID: 2) to visible set
✓ Adding provider Joe Soap (ID: 6) to visible set
... etc ...
✅ Visible providers initialized: [2, 6, 8, 9]
📊 Appointments provider IDs: ["9: provider 2", "11: provider 2", ...]
📥 Raw API response: (should show appointments array)
📦 Extracted appointments array: (should have 7+ appointments)
🔍 Filtering appointments...
   Total appointments: 7
   Visible providers: [2, 6, 8, 9]
   Appointment 9: providerId=2, converted=2, visible=true
   ...
📊 Filter result: 7 of 7 appointments visible
```

**If you see:**
- ✅ `visible=true` for all appointments → Issue is in rendering, not filtering
- ❌ `visible=false` → Provider ID mismatch (e.g., providerId=2 but visibleProviders has "2" as string)
- ❌ `Total appointments: 0` → API not returning data or wrong date range
- ❌ `Visible providers: []` → Providers not loading

### 2. Common Fixes Based on Console Output

**Scenario A: Appointments load but all filtered out**
```javascript
// Symptom: visible=false for all appointments
// Fix: Provider ID type mismatch - ensure both are integers
```

**Scenario B: Appointments array is empty**
```javascript
// Symptom: Total appointments: 0
// Check: Is date range correct? Are you viewing November 2025?
// Fix: Navigate to correct month or check API response
```

**Scenario C: Providers array is empty**
```javascript
// Symptom: Visible providers: []
// Fix: Check if /api/providers is being blocked or returning errors
```

**Scenario D: Browser shows CORS or 401 errors**
```javascript
// Symptom: Network tab shows red errors
// Fix: Check authentication, API filters, or CORS configuration
```

### 3. Verify After Creating Appointment

After creating a new appointment:

1. Check database: `SELECT * FROM xs_appointments ORDER BY id DESC LIMIT 1;`
2. Check API: `curl "http://localhost:8081/api/appointments?start=2025-11-01&end=2025-11-30"`
3. Refresh `/appointments` page with browser console open
4. Look for the new appointment ID in console logs

### 4. If Issue Persists

**Clear browser cache and rebuild:**
```bash
cd /Volumes/Nilo_512GB/projects/xscheduler_ci4
npm run build
# Then hard refresh browser (Cmd+Shift+R on Mac)
```

**Check for JavaScript errors:**
- Open browser DevTools → Console tab
- Look for red error messages
- Check Network tab for failed API calls

## Code Quality Notes

### No Major Duplications Found

- Three appointment creation endpoints serve different purposes (form, legacy API, modern API)
- All use shared services: `AvailabilityService`, `CustomerModel`, `AppointmentModel`
- Customer creation consolidated via `CustomerModel::findOrCreateByEmail()`
- Availability logic centralized in `AvailabilityService`

### Previous Refactoring (Nov 14, 2025)

- ✅ Eliminated SlotGenerator duplication (~160 lines)
- ✅ Consolidated to AvailabilityService (~540 lines)
- ✅ Created CustomerModel helper to avoid duplicate customer creation logic
- ✅ Updated Scheduler.php, SchedulingService.php, Api/V1/Availabilities.php

## Files Modified Today

1. `/Volumes/Nilo_512GB/projects/xscheduler_ci4/resources/js/modules/scheduler/scheduler-core.js`
   - Added enhanced logging to diagnose provider filtering
   - Lines 76-82: Log each provider being added to visibleProviders
   - Lines 214-234: Enhanced getFilteredAppointments() with detailed warnings

2. `/Volumes/Nilo_512GB/projects/xscheduler_ci4/docs/APPOINTMENT_DISPLAY_DIAGNOSIS.md`
   - This document

## Expected Outcome

After following the steps above, one of these should be true:

1. **Console reveals the exact issue** (e.g., provider ID type mismatch, empty array, etc.)
2. **Appointments appear correctly** after hard refresh
3. **Specific error message** points to authentication, CORS, or API configuration issue

## Contact

If console logs show unexpected behavior, please share:
- Full console output when loading `/appointments`
- Network tab showing `/api/appointments` response
- Any red error messages

---

**Status:** Diagnostic logging deployed, awaiting browser console output to identify root cause.

# Phase 2 Manual Test Plan

## Overview
This document outlines the manual testing steps to verify Phase 2 service layer integration.

## Prerequisites
- Development server running: `php spark serve`
- Access to http://localhost:8080
- Database has sample business_hours data
- Settings table configured with calendar and booking preferences

## Test 1: Calendar Configuration API

### Steps
1. Navigate to: `http://localhost:8080/api/v1/settings/calendar-config`
2. Verify JSON response contains:
   - `initialView`: "timeGridWeek" or configured view
   - `firstDay`: 0-6 (day of week)
   - `slotDuration`: "00:30:00" format
   - `slotMinTime`, `slotMaxTime`: "HH:MM" format
   - `slotLabelFormat`, `eventTimeFormat`: 12hr or 24hr config
   - `businessHours`: array with daysOfWeek, startTime, endTime
   - `timeZone`: configured timezone
   - `locale`: language code

### Expected Result
✅ API returns complete configuration object
✅ Business hours reflect database xs_business_hours table
✅ Time format matches setting (12hr/24hr)

## Test 2: Appointments Calendar View

### Steps
1. Navigate to: `http://localhost:8080/appointments`
2. Verify calendar loads without errors
3. Check browser console for any JavaScript errors
4. Verify:
   - Calendar displays correct initial view (week/month/day)
   - Time slots match configured slot duration
   - Time labels show correct format (12hr vs 24hr)
   - Business hours are highlighted/shaded
   - Calendar starts on correct day of week
   - Timezone displays correct local times

### Expected Result
✅ Calendar renders successfully with database-driven configuration
✅ No console errors related to calendar initialization
✅ Visual display matches settings from API endpoint

## Test 3: Booking Form Dynamic Fields

### Steps
1. Navigate to: `http://localhost:8080/appointments/create`
2. Inspect the "Customer Information" section
3. Verify field visibility based on settings:
   - First Name (display/hide based on setting)
   - Last Name (display/hide)
   - Email (display/hide)
   - Phone (display/hide)
   - Address (display/hide)
   - Notes (display/hide)
4. Check required indicators (`*`) appear only on required fields
5. Verify custom fields section (if configured):
   - Custom field 1-6 render if configured
   - Field type matches (text, textarea, checkbox)
   - Labels display correctly
   - Required indicators appear if set

### Expected Result
✅ Only configured fields display
✅ Required indicators match settings
✅ Custom fields render with correct types
✅ Form layout is responsive and clean

## Test 4: Settings Integration

### Steps
1. Log in as admin
2. Navigate to Settings > Booking Settings
3. Toggle a customer field display (e.g., turn off "Phone")
4. Save settings
5. Navigate to `/appointments/create` (may need to refresh)
6. Verify phone field is now hidden

### Expected Result
✅ Settings changes reflect immediately in booking form
✅ No errors when saving settings
✅ Dynamic rendering works correctly

## Test 5: Custom Field Configuration

### Steps
1. In Settings > Booking Settings, configure a custom field:
   - Label: "Preferred Contact Method"
   - Type: "text"
   - Required: true
   - Display: true
2. Save settings
3. Visit `/appointments/create`
4. Verify custom field appears with label and required indicator

### Expected Result
✅ Custom field displays correctly
✅ Field type matches configuration
✅ Required validation works

## Browser Console Checks

### Check for:
- No 404 errors for JavaScript files
- No errors in calendar initialization
- API call to `/api/v1/settings/calendar-config` succeeds
- Calendar config is applied before rendering

### Expected Console Output
```javascript
// Should see successful API call
GET http://localhost:8080/api/v1/settings/calendar-config [200 OK]

// Calendar should initialize without errors
// No red error messages in console
```

## Database Verification

### Quick SQL Checks
```sql
-- Verify business hours exist
SELECT * FROM xs_business_hours;

-- Verify calendar settings
SELECT * FROM xs_settings 
WHERE setting_key LIKE 'calendar.%';

-- Verify booking field settings
SELECT * FROM xs_settings 
WHERE setting_key LIKE 'booking.require_%' 
   OR setting_key LIKE 'booking.display_%'
   OR setting_key LIKE 'booking.custom_field_%';
```

## Issue Reporting Template

If issues are found, document:
- **Step**: Which test step failed
- **Expected**: What should happen
- **Actual**: What actually happened
- **Console Errors**: Any JavaScript/PHP errors
- **Screenshots**: Visual evidence of issue
- **Database State**: Relevant settings values

## Completion Checklist

- [ ] Test 1: Calendar Config API verified
- [ ] Test 2: Calendar view loads with correct config
- [ ] Test 3: Booking form fields render dynamically
- [ ] Test 4: Settings changes reflect in form
- [ ] Test 5: Custom fields work correctly
- [ ] No console errors
- [ ] No PHP errors in debug bar
- [ ] Business hours highlight correctly
- [ ] Time format displays correctly (12/24hr)
- [ ] All settings-driven features working

## Phase 2 Sign-Off

Once all tests pass:
1. Document any issues found and fixed
2. Update PHASE_2_COMPLETE.md with summary
3. Commit final Phase 2 work
4. Ready to proceed to Phase 3: Frontend Wiring

---

**Testing Date**: _________________  
**Tester**: _________________  
**Result**: ☐ Pass ☐ Fail (see notes)  
**Notes**:

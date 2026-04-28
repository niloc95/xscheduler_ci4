# Testing Guide: Appointment Updates

## Overview
This guide helps verify that all three appointment update methods work correctly after the architectural improvements.

---

## Test 1: Modal Quick Status Update ✅

**Purpose**: Verify modal status dropdown updates persist to database

**Steps**:
1. Navigate to `/appointments`
2. Click on any appointment in the calendar
3. Modal should open showing appointment details
4. Change status using the dropdown (e.g., "Pending" → "Confirmed")
5. Click the **Save** button (appears when status changes)
6. Modal should close and show success toast
7. Reload the page
8. Click the same appointment - verify status persisted

**Expected Behavior**:
- ✅ Status dropdown shows current status
- ✅ Save button appears when status changes
- ✅ API call to `/api/appointments/:id/status` succeeds
- ✅ Calendar refreshes automatically
- ✅ Status persists after page reload

**Check Logs**:
```bash
tail -50 writable/logs/log-$(date +%Y-%m-%d).log | grep -i "status"
```

---

## Test 2: Edit Page Full Form Update ✅

**Purpose**: Verify edit page form saves all fields including status

**Steps**:
1. Navigate to `/appointments`
2. Click any appointment to open modal
3. Click the **Edit** button
4. You should be redirected to `/appointments/edit/{hash}`
5. Form should be pre-populated with current values
6. Change the status dropdown (e.g., "Completed" → "Pending")
7. Optionally change other fields (customer name, notes, etc.)
8. Click **Save Changes**
9. Should redirect to `/appointments` with success message
10. Find the same appointment - verify all changes persisted

**Expected Behavior**:
- ✅ Edit button redirects to edit page
- ✅ Form shows current appointment data
- ✅ Status dropdown shows current status
- ✅ Form validation passes (no 'booked' status errors)
- ✅ All changed fields save correctly
- ✅ Status persists after save
- ✅ Redirects with success message

**Check Logs**:
```bash
tail -100 writable/logs/log-$(date +%Y-%m-%d).log | grep "Appointments::update"
```

Look for:
```
Status from form: pending
Current appointment status: completed
Appointment data to save: {"provider_id":1,"service_id":2,...,"status":"pending",...}
Successfully updated appointment #123
```

---

## Test 3: Drag-Drop Reschedule ✅

**Purpose**: Verify drag-drop rescheduling works correctly

**Steps**:
1. Navigate to `/appointments`
2. Switch to **Week** or **Day** view (drag-drop works best here)
3. Find an appointment block
4. Click and hold on the appointment
5. Drag it to a different time slot
6. Release mouse button
7. Confirmation dialog should appear
8. Click **OK** to confirm
9. Calendar should refresh showing appointment in new time

**Expected Behavior**:
- ✅ Appointment becomes draggable
- ✅ Time slots highlight when hovering
- ✅ Confirmation dialog shows old and new times
- ✅ API call to `/api/appointments/:id` succeeds
- ✅ Calendar refreshes with new time
- ✅ Times persist after page reload

**Check Logs**:
```bash
tail -50 writable/logs/log-$(date +%Y-%m-%d).log | grep -i "reschedule\|drag"
```

---

## Test 4: Validation Consistency ✅

**Purpose**: Ensure status values are consistent across all methods

**Valid Status Values** (after fixes):
- `pending`
- `confirmed`
- `completed`
- `cancelled`
- `no-show`

**Test**:
1. Modal status dropdown should only show these 5 options
2. Edit form status dropdown should only show these 5 options
3. Try to save appointment with each status - all should work
4. No validation errors about 'booked' status

**Check Files**:
- ✅ `app/Controllers/Appointments.php` line ~463: No 'booked' in validation
- ✅ `app/Controllers/Api/Appointments.php` line ~474: Valid statuses array
- ✅ `app/Views/appointments/edit.php` line ~267: No 'booked' option
- ✅ `resources/js/.../appointment-details-modal.js` line ~136: No 'booked' option

---

## Troubleshooting

### Modal Status Not Saving

**Symptoms**: Status changes in modal but reverts after page reload

**Debug Steps**:
1. Open browser DevTools → Network tab
2. Change status in modal
3. Look for API call to `/api/appointments/{id}/status`
4. Check response: Should be `200 OK` with `{"data":{"ok":true},...}`
5. If 400/500 error, check response body for error message

**Check**:
```javascript
// In browser console after status change
fetch('/api/appointments/1/status', {
    method: 'PATCH',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({status: 'confirmed'})
}).then(r => r.json()).then(console.log)
```

### Edit Form Status Not Saving

**Symptoms**: Status changes in edit form but reverts after submit

**Debug Steps**:
1. Open edit form
2. Open browser DevTools → Console
3. Right-click "Save Changes" button → Inspect
4. Check that form has: `<select id="status" name="status">`
5. Submit form and check server logs

**Check Logs**:
```bash
tail -200 writable/logs/log-$(date +%Y-%m-%d).log | grep -A5 -B5 "Appointments::update"
```

Look for validation errors or missing status field.

### Drag-Drop Not Working

**Symptoms**: Can't drag appointments or drops don't save

**Debug Steps**:
1. Check browser console for JavaScript errors
2. Verify appointments have `draggable="true"` attribute
3. Check that DragDropManager is initialized
4. Look for API call to `/api/appointments/{id}` with PATCH method

**Check Console**:
```javascript
// In browser console
window.scheduler.dragDropManager
// Should be defined
```

---

## Database Verification

To manually verify status changes persisted:

```sql
-- Check recent appointments and their statuses
SELECT id, customer_first_name, status, start_time, updated_at 
FROM xs_appointments 
ORDER BY updated_at DESC 
LIMIT 10;

-- Check specific appointment
SELECT * FROM xs_appointments WHERE id = 123;
```

---

## Success Criteria

All tests pass when:
- ✅ Modal status changes persist to database
- ✅ Edit form status changes persist to database  
- ✅ Drag-drop reschedules persist to database
- ✅ No validation errors for valid statuses
- ✅ No console errors in browser
- ✅ No server errors in logs
- ✅ All three update flows work independently without conflicts

---

## Next Steps After Testing

If all tests pass:
1. ✅ Architecture is clean and working
2. ✅ Documentation is complete
3. ✅ No further consolidation needed

If tests fail:
1. Note which specific test fails
2. Check logs for error messages
3. Verify API endpoints are accessible
4. Check database permissions
5. Report findings for further debugging

---

**Last Updated**: November 3, 2025  
**Status**: Ready for testing

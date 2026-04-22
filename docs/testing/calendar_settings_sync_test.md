# Calendar Settings Sync - Testing Guide

**Test Date:** October 8, 2025  
**Feature:** Calendar Time Format & Business Hours Sync

## Pre-Test Setup

### 1. Clear Browser Cache
```bash
# Chrome/Edge
Cmd+Shift+Delete → Clear cached images and files

# Or hard refresh
Cmd+Shift+R
```

### 2. Check Current Settings
Navigate to **Settings** and note:
- **Localization → Time Format:** Current value
- **Business Hours → Work Start:** Current value
- **Business Hours → Work End:** Current value

## Test Scenarios

### Test 1: Time Format - 12h to 24h

**Steps:**
1. Open **Appointments** page
2. Click **"Week"** view button
3. Note current time labels (e.g., "9:00 AM")
4. Navigate to **Settings → Localization**
5. Change Time Format to **"HH:MM (24h)"**
6. Click **"Save All Settings"**
7. Navigate back to **Appointments**

**Expected Result:**
- ✅ Time labels show "09:00", "10:00", etc. (no AM/PM)
- ✅ No page reload required
- ✅ Month view events also show 24h format

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

### Test 2: Time Format - 24h to 12h

**Steps:**
1. Open **Appointments** page
2. Click **"Day"** view button
3. Note current time labels (e.g., "14:00")
4. Navigate to **Settings → Localization**
5. Change Time Format to **"hh:mm AM/PM (12h)"**
6. Click **"Save All Settings"**
7. Navigate back to **Appointments**

**Expected Result:**
- ✅ Time labels show "2:00 PM", "3:00 PM", etc.
- ✅ Morning times show "9:00 AM", "10:00 AM"
- ✅ No page reload required

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

### Test 3: Business Hours - Extend Range

**Steps:**
1. Open **Appointments** page
2. Click **"Week"** view
3. Note visible time range (e.g., 8:00 AM - 5:00 PM)
4. Navigate to **Settings → Business Hours**
5. Change Work Start to **"07:00"**
6. Change Work End to **"19:00"**
7. Click **"Save All Settings"**
8. Navigate back to **Appointments**
9. Click **"Week"** view again

**Expected Result:**
- ✅ Calendar shows 7:00 AM (or 07:00) to 7:00 PM (or 19:00)
- ✅ All hours visible in scrollable area
- ✅ Day view also reflects new range

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

### Test 4: Business Hours - Narrow Range

**Steps:**
1. Open **Appointments** page
2. Navigate to **Settings → Business Hours**
3. Change Work Start to **"09:00"**
4. Change Work End to **"17:00"**
5. Click **"Save All Settings"**
6. Navigate back to **Appointments**
7. Click **"Day"** view

**Expected Result:**
- ✅ Calendar shows only 9:00 AM - 5:00 PM range
- ✅ Earlier and later hours not visible
- ✅ No blank space or rendering issues

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

### Test 5: Combined Change

**Steps:**
1. Navigate to **Settings**
2. In **Localization** tab:
   - Set Time Format to **"12h"**
3. In **Business Hours** tab:
   - Set Work Start to **"08:00"**
   - Set Work End to **"18:00"**
4. Click **"Save All Settings"**
5. Navigate to **Appointments**
6. Switch between Day/Week/Month views

**Expected Result:**
- ✅ Day view: 8:00 AM - 6:00 PM with AM/PM labels
- ✅ Week view: 8:00 AM - 6:00 PM with AM/PM labels
- ✅ Month view: Events show times in 12h format
- ✅ All changes applied immediately

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

### Test 6: Month View Unaffected

**Steps:**
1. Open **Appointments** page in **Month** view
2. Note: Business hours should NOT restrict visible dates
3. Navigate to **Settings → Business Hours**
4. Change Work Start to **"10:00"**
5. Save and return to **Appointments**
6. Stay in **Month** view

**Expected Result:**
- ✅ Month view shows all days
- ✅ All dates visible (not limited to business hours)
- ✅ Event times display in correct format only

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

### Test 7: Scheduler Dashboard Integration

**Steps:**
1. Navigate to **Dashboard** (home page)
2. Scroll to **"Scheduler"** section
3. Note current calendar time format
4. Navigate to **Settings → Localization**
5. Toggle time format
6. Save settings
7. Return to **Dashboard**

**Expected Result:**
- ✅ Scheduler calendar updates automatically
- ✅ Time format matches localization setting
- ✅ No console errors

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

### Test 8: Settings Change Without Page Reload

**Goal:** Verify calendar updates without navigating away

**Steps:**
1. Open **Appointments** in one browser tab
2. Open **Settings** in another tab
3. In Settings tab: Change time format and save
4. Switch back to Appointments tab (don't reload)
5. Observe calendar

**Expected Result:**
- ✅ Calendar updates automatically on focus
- ✅ Time format reflects new setting
- ✅ No manual refresh needed

**Actual Result:**
- [ ] Pass / [ ] Fail
- Notes: ___________

---

## Browser Compatibility

Test on multiple browsers:

- [ ] **Chrome** (macOS)
- [ ] **Safari** (macOS)
- [ ] **Firefox** (macOS)
- [ ] **Edge** (macOS)

## Console Validation

### Check for Logs

Open browser console (Cmd+Option+I) and look for:

**On Settings Save:**
```
[calendar] Settings changed, checking for relevant updates: ["localization.time_format"]
[calendar] Detected relevant settings change, reinitializing...
[calendar] Settings loaded: {timeFormat: "12h", workStart: "08:00:00", workEnd: "17:00:00"}
```

**On Calendar Init:**
```
[calendar] Settings loaded: {timeFormat: "24h", workStart: "09:00:00", workEnd: "17:00:00"}
Calendar initialized successfully
```

### Check for Errors

Look for any red errors in console:
- [ ] No errors during settings save
- [ ] No errors during calendar init
- [ ] No errors during view switching

## Edge Case Testing

### Edge Case 1: Invalid Work Hours
**Setup:** Set work_end before work_start (if validation allows)
**Expected:** Calendar handles gracefully or prevents save

### Edge Case 2: Settings API Failure
**Setup:** Block `/api/v1/settings` in Network tab
**Expected:** Calendar uses default values, no crash

### Edge Case 3: Rapid Settings Changes
**Setup:** Change settings multiple times quickly
**Expected:** Calendar updates to final state, no race conditions

## Performance Testing

### Measure Reinitialization Time

1. Open browser DevTools → Performance tab
2. Start recording
3. Change settings and save
4. Switch to calendar view
5. Stop recording
6. Check timing:
   - Settings save: < 100ms
   - Calendar reinit: < 300ms
   - Total UX delay: < 500ms

**Acceptable Delay:** User should not notice refresh (< 500ms)

## Acceptance Criteria

| Criterion | Status | Notes |
|-----------|--------|-------|
| ✅ Day view matches business hours | [ ] Pass | |
| ✅ Week view matches business hours | [ ] Pass | |
| ✅ Month view unaffected | [ ] Pass | |
| ✅ 12h format displays AM/PM | [ ] Pass | |
| ✅ 24h format displays 00:00-23:59 | [ ] Pass | |
| ✅ Settings update immediately | [ ] Pass | |
| ✅ No page reload required | [ ] Pass | |
| ✅ No visual regressions | [ ] Pass | |
| ✅ No console errors | [ ] Pass | |
| ✅ Works across browsers | [ ] Pass | |

## Sign-Off

**Tested By:** _______________  
**Date:** _______________  
**Build Version:** 1.0.0  
**Result:** [ ] PASS / [ ] FAIL  

**Issues Found:**
1. _______________
2. _______________
3. _______________

**Notes:**
_______________________________________________
_______________________________________________
_______________________________________________

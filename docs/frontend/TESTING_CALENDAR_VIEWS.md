# Calendar View Controls - Testing Guide

## Quick Test Procedure

### Prerequisites
✅ Built assets: `npm run build` completed successfully  
✅ Server running: Navigate to `/appointments` dashboard  
✅ Browser: Chrome, Firefox, Safari, or Edge (latest version)

---

## Visual Testing Steps

### 1. Default State (Month View)
**Expected Result:**
- Calendar displays current month in grid format
- **"Month"** button has blue background (`bg-blue-600 text-white`)
- All other view buttons have gray background
- Current day highlighted with blue circle

**Test Actions:**
1. Open `/appointments` route
2. Verify calendar shows month grid
3. Check "Month" button is active (blue)
4. Confirm today's date has blue circular badge

---

### 2. Week View Toggle
**Expected Result:**
- Calendar switches to 7-column layout with time slots
- **"This Week"** button becomes active (blue)
- "Month" button returns to gray
- Time labels appear on left (12am, 1am, 2am...)
- Current time indicator visible if today is in view

**Test Actions:**
1. Click **"This Week"** button
2. Verify 7 columns appear (Sun-Sat)
3. Check hourly time slots render
4. Confirm active button state changed
5. Verify dark mode (if enabled) shows proper colors

**Browser Console Check:**
```javascript
// Should show 'timeGridWeek'
calendar.view.type
```

---

### 3. Day View Toggle
**Expected Result:**
- Calendar switches to single-column layout
- **"Day"** button becomes active (blue)
- Shows today's date by default
- Full 24-hour schedule visible
- Now indicator line appears at current time

**Test Actions:**
1. Click **"Day"** button
2. Verify single column appears
3. Check title updates to "Wed, Jan 8, 2025" format
4. Confirm today's date is shown
5. Look for blue "now" indicator line

**Browser Console Check:**
```javascript
// Should show 'timeGridDay'
calendar.view.type

// Should be today's date
calendar.getDate()
```

---

### 4. Today Button Behavior
**Expected Result:**
- In **Month view:** Navigates to current month, highlights today
- In **Week view:** Shows current week containing today
- In **Day view:** Shows today (redundant but works)
- Active view button remains unchanged

**Test Actions:**
1. Navigate to future/past month using prev/next
2. Click **"Today"** button
3. Verify calendar jumps back to today
4. Confirm active view button doesn't change

---

### 5. All Button (Month View Reset)
**Expected Result:**
- Calendar resets to month view
- **"Month"** button becomes active
- Shows current month

**Test Actions:**
1. Switch to Week or Day view
2. Click **"All"** button
3. Verify month view appears
4. Check "Month" button is now active

---

### 6. Navigation Consistency
**Expected Result:**
- Prev/next chevrons work in all views
- Month → Week → Day → Month cycle maintains proper state
- Title updates correctly for each view
- Active button always matches current view

**Test Actions:**
1. Click through all views in sequence
2. Use prev/next buttons in each view
3. Verify title format changes:
   - Month: "January 2025"
   - Week: "Jan 8 – 14, 2025"
   - Day: "Wed, Jan 8, 2025"
4. Confirm active button follows view changes

---

### 7. Dark Mode Compatibility
**Expected Result:**
- All views render correctly in dark mode
- Time slots have proper dark backgrounds
- Text remains readable
- Active buttons maintain blue color
- Hover states work properly

**Test Actions:**
1. Enable dark mode (toggle switch in nav)
2. Test all three views
3. Check time slot colors (`bg-gray-800`, `border-gray-700`)
4. Verify text contrast (`text-gray-200`)
5. Confirm now indicator is visible (`border-blue-400`)

**Quick Dark Mode Classes Check:**
```
Container: dark:bg-gray-800
Borders: dark:border-gray-700
Text: dark:text-gray-200
Time Labels: dark:text-gray-400
Now Line: dark:border-blue-400
```

---

### 8. Responsive Design
**Expected Result:**
- Mobile (< 640px): Buttons stack vertically
- Tablet (640px - 1024px): Buttons wrap horizontally
- Desktop (> 1024px): All buttons in single row
- Calendar remains full-width in all views

**Test Actions:**
1. Resize browser window to mobile size (375px)
2. Verify button layout stacks
3. Test view switching on mobile
4. Check calendar scrolls horizontally if needed
5. Resize to desktop and confirm layout

---

## Edge Cases & Error Scenarios

### Rapid View Switching
**Test:** Click Day → Week → Month → Day rapidly  
**Expected:** No errors, smooth transitions, correct active states

### Network Latency Simulation
**Test:** Throttle network to "Slow 3G" in DevTools  
**Expected:** Views still switch (no server round-trip needed)

### Event Loading (Future Test)
**Test:** Add events endpoint and switch views  
**Expected:** Events render in all views, no duplicates

---

## Performance Benchmarks

### Target Metrics
| Action | Target Time | Measurement Method |
|--------|-------------|-------------------|
| Month → Week | < 100ms | DevTools Performance tab |
| Week → Day | < 50ms | DevTools Performance tab |
| Day → Month | < 150ms | DevTools Performance tab |

### Memory Usage
| View | Expected Memory | Measurement Method |
|------|----------------|-------------------|
| Month | Baseline | Chrome Task Manager |
| Week | +15-25% | Chrome Task Manager |
| Day | +5-10% | Chrome Task Manager |

---

## Browser DevTools Checks

### JavaScript Console Tests
```javascript
// 1. Verify calendar instance exists
window.calendar || document.querySelector('#appointments-inline-calendar')

// 2. Check current view type
calendar.view.type // 'dayGridMonth', 'timeGridWeek', or 'timeGridDay'

// 3. Get current date
calendar.getDate().toISOString()

// 4. Test view switching programmatically
calendar.changeView('timeGridWeek')
calendar.changeView('timeGridDay')
calendar.changeView('dayGridMonth')

// 5. Verify plugins loaded
calendar.getOption('plugins') // Should include both dayGridPlugin and timeGridPlugin
```

### Network Tab
- **Expected:** Zero network requests on view switch (client-side only)
- **Exception:** If events feed is implemented, expect XHR to `/appointments/feed`

### CSS Inspection
- **Month View Day Number:** `.fc-daygrid-day-number` should have `border-radius: 9999px`
- **Week View Time Slot:** `.fc-timegrid-slot` should have `height: 3rem`
- **Active Button:** Should have `background-color: rgb(37, 99, 235)` (blue-600)

---

## Automated Test Script (Browser Console)

```javascript
// Copy-paste this into browser console for quick validation
(async function runCalendarTests() {
  console.group('Calendar View Tests');
  
  const calendar = window.calendar;
  if (!calendar) {
    console.error('❌ Calendar not found!');
    return;
  }
  
  // Test 1: Initial state
  console.log('✅ Calendar exists');
  console.log(`Current view: ${calendar.view.type}`);
  
  // Test 2: Switch to week view
  calendar.changeView('timeGridWeek');
  await new Promise(r => setTimeout(r, 100));
  console.log(calendar.view.type === 'timeGridWeek' ? '✅ Week view works' : '❌ Week view failed');
  
  // Test 3: Switch to day view
  calendar.changeView('timeGridDay');
  await new Promise(r => setTimeout(r, 100));
  console.log(calendar.view.type === 'timeGridDay' ? '✅ Day view works' : '❌ Day view failed');
  
  // Test 4: Back to month view
  calendar.changeView('dayGridMonth');
  await new Promise(r => setTimeout(r, 100));
  console.log(calendar.view.type === 'dayGridMonth' ? '✅ Month view works' : '❌ Month view failed');
  
  // Test 5: Today navigation
  const beforeDate = calendar.getDate().toISOString();
  calendar.gotoDate('2020-01-01'); // Go to past
  calendar.today(); // Return to today
  const afterDate = calendar.getDate().toISOString();
  console.log(afterDate.startsWith(new Date().toISOString().split('T')[0]) ? '✅ Today button works' : '⚠️ Today button issue');
  
  console.groupEnd();
  console.log('🎉 All tests complete!');
})();
```

---

## Sign-Off Checklist

Before marking this feature as complete, verify:

- [ ] All 3 views render correctly
- [ ] Active button state updates automatically
- [ ] Dark mode works in all views
- [ ] Responsive layout works on mobile/tablet/desktop
- [ ] Prev/next navigation works in all views
- [ ] Today button navigates correctly
- [ ] No JavaScript console errors
- [ ] No CSS layout issues
- [ ] Build completed successfully (158.84 KB CSS, 224.19 KB JS)
- [ ] Documentation updated and committed

---

**Testing Completed By:** _______________  
**Date:** _______________  
**Browser(s) Tested:** _______________  
**Issues Found:** _______________  

---

**Last Updated:** October 8, 2025  
**Version:** 1.3.0

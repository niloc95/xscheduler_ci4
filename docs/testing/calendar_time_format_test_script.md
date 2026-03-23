# Calendar Time Format Test Script

**Purpose:** Browser console test script to verify time format synchronization  
**Version:** 1.0.1  
**Date:** October 8, 2025

---

## 🧪 Quick Test Script

Copy and paste this into your browser console on the **Appointments** page:

```javascript
// ============================================
// Calendar Time Format Test Suite
// ============================================

console.clear();
console.log('🧪 Starting Calendar Time Format Tests...\n');

// Test 1: Check if calendar element exists
console.log('Test 1: Calendar Element Check');
const calendarEl = document.getElementById('appointments-inline-calendar');
console.log('  ✓ Calendar element:', calendarEl ? 'EXISTS' : '❌ NOT FOUND');
console.log('');

// Test 2: Check current settings from API
console.log('Test 2: Fetching Current Settings...');
fetch('/api/v1/settings')
    .then(r => r.json())
    .then(settings => {
        console.log('  ✓ Time Format:', settings['localization.time_format'] || '❌ NOT SET');
        console.log('  ✓ Work Start:', settings['business.work_start'] || '❌ NOT SET');
        console.log('  ✓ Work End:', settings['business.work_end'] || '❌ NOT SET');
        console.log('');
        
        // Test 3: Check if settings match expected format
        console.log('Test 3: Settings Validation');
        const timeFormat = settings['localization.time_format'];
        if (timeFormat === '12h' || timeFormat === '24h') {
            console.log('  ✓ Time format is valid:', timeFormat);
        } else {
            console.log('  ❌ Time format invalid:', timeFormat);
        }
        console.log('');
        
        return settings;
    })
    .catch(error => {
        console.error('  ❌ Failed to fetch settings:', error);
    });

// Test 4: Check if event listeners are registered
console.log('Test 4: Event Listener Check');
let settingsSavedFired = false;
let spaNavigatedFired = false;

document.addEventListener('settingsSaved', () => {
    settingsSavedFired = true;
    console.log('  ✓ settingsSaved event FIRED');
});

document.addEventListener('spa:navigated', () => {
    spaNavigatedFired = true;
    console.log('  ✓ spa:navigated event FIRED');
});

console.log('  ℹ️  Listeners registered. They will log when events fire.');
console.log('');

// Test 5: Check if reinitialize function exists
console.log('Test 5: Reinitialization Function Check');
if (typeof window.reinitializeCalendar === 'function') {
    console.log('  ✓ reinitializeCalendar function EXISTS');
    console.log('  ℹ️  You can call: await window.reinitializeCalendar(true)');
} else {
    console.log('  ❌ reinitializeCalendar function NOT FOUND');
}
console.log('');

// Test 6: Simulate settings change
console.log('Test 6: Simulate Settings Change');
console.log('  ℹ️  Dispatching settingsSaved event...');
document.dispatchEvent(new CustomEvent('settingsSaved', {
    detail: ['localization.time_format', 'business.work_start']
}));

setTimeout(() => {
    if (settingsSavedFired) {
        console.log('  ✓ Event listener responded correctly');
    } else {
        console.log('  ❌ Event listener did not respond');
    }
    console.log('');
    
    // Final summary
    console.log('═══════════════════════════════════════');
    console.log('📊 Test Summary');
    console.log('═══════════════════════════════════════');
    console.log('Calendar Element:', calendarEl ? '✓' : '❌');
    console.log('Settings API:', '✓ (see results above)');
    console.log('Event System:', settingsSavedFired ? '✓' : '❌');
    console.log('Reinit Function:', typeof window.reinitializeCalendar === 'function' ? '✓' : '❌');
    console.log('');
    console.log('🎯 Next Steps:');
    console.log('1. Go to Settings → Localization');
    console.log('2. Change time format (12h ↔ 24h)');
    console.log('3. Click "Save All Settings"');
    console.log('4. Watch console for update logs');
    console.log('5. Verify calendar time labels changed');
}, 1000);
```

---

## 🔧 Manual Test Commands

### Command 1: Force Calendar Refresh

```javascript
// Force the calendar to reinitialize with latest settings
await window.reinitializeCalendar(true);
console.log('✓ Calendar reinitialized');
```

### Command 2: Check Current Calendar Settings

```javascript
// View what settings the calendar is currently using
fetch('/api/v1/settings')
    .then(r => r.json())
    .then(s => console.table({
        'Time Format': s['localization.time_format'],
        'Work Start': s['business.work_start'],
        'Work End': s['business.work_end'],
        'Expected Display': s['localization.time_format'] === '12h' ? 'AM/PM format' : '24-hour format'
    }));
```

### Command 3: Monitor Settings Changes

```javascript
// Add a listener to see when settings are saved
document.addEventListener('settingsSaved', (event) => {
    console.log('📢 Settings saved!');
    console.log('Changed keys:', event.detail);
    console.log('Timestamp:', new Date().toLocaleTimeString());
});

console.log('✓ Monitoring settings changes... (change settings now)');
```

### Command 4: Monitor SPA Navigation

```javascript
// Add a listener to see when navigation occurs
document.addEventListener('spa:navigated', () => {
    console.log('🔄 SPA navigation detected');
    console.log('Current page:', window.location.pathname);
    console.log('Timestamp:', new Date().toLocaleTimeString());
});

console.log('✓ Monitoring navigation... (navigate to another page)');
```

### Command 5: Full Diagnostic

```javascript
// Complete diagnostic check
async function diagnosticCheck() {
    console.clear();
    console.log('🔍 FULL DIAGNOSTIC CHECK\n');
    
    // 1. Calendar element
    const calendarEl = document.getElementById('appointments-inline-calendar');
    console.log('1. Calendar Element:', calendarEl ? '✅ EXISTS' : '❌ MISSING');
    
    // 2. Settings API
    try {
        const response = await fetch('/api/v1/settings');
        const settings = await response.json();
        console.log('2. Settings API:', '✅ WORKING');
        console.log('   - Time Format:', settings['localization.time_format']);
        console.log('   - Work Start:', settings['business.work_start']);
        console.log('   - Work End:', settings['business.work_end']);
    } catch (error) {
        console.log('2. Settings API:', '❌ FAILED', error.message);
    }
    
    // 3. Event system
    let eventFired = false;
    const listener = () => { eventFired = true; };
    document.addEventListener('settingsSaved', listener);
    document.dispatchEvent(new CustomEvent('settingsSaved', { detail: ['test'] }));
    setTimeout(() => {
        console.log('3. Event System:', eventFired ? '✅ WORKING' : '❌ NOT WORKING');
        document.removeEventListener('settingsSaved', listener);
    }, 100);
    
    // 4. Reinit function
    console.log('4. Reinit Function:', typeof window.reinitializeCalendar === 'function' ? '✅ EXISTS' : '❌ MISSING');
    
    // 5. Current view
    console.log('5. Current URL:', window.location.pathname);
    
    console.log('\n✓ Diagnostic complete');
}

await diagnosticCheck();
```

---

## 📝 Expected Console Output

### When Settings Are Saved

```
[calendar] Settings changed, refreshing calendar: ["localization.time_format"]
[calendar] Detected relevant settings change, reinitializing...
[calendar] Destroying existing calendar instance
[calendar] Settings loaded: {timeFormat: "12h", workStart: "08:00:00", workEnd: "17:00:00"} (CHANGED)
[calendar] Applying time format configuration: {
    timeFormat: "12h",
    hour12: true,
    hourFormat: "numeric",
    meridiem: "short",
    workStart: "08:00:00",
    workEnd: "17:00:00"
}
Calendar initialized successfully
```

### When Navigating to Appointments

```
[calendar] SPA navigation detected, checking if calendar needs refresh
[calendar] On appointments page, reinitializing with latest settings...
[calendar] Settings loaded: {timeFormat: "12h", workStart: "08:00:00", workEnd: "17:00:00"} (CHANGED)
[calendar] Applying time format configuration: {...}
Calendar initialized successfully
```

### When Using Cached Settings

```
[calendar] Using cached settings
[calendar] Settings loaded: {timeFormat: "24h", ...} (no change)
```

---

## 🎯 Interactive Test Scenarios

### Scenario 1: Real-Time Update Test

1. Open **Appointments** page
2. Open browser console (F12)
3. Run monitoring command:
   ```javascript
   document.addEventListener('settingsSaved', e => console.log('✅ Event detected:', e.detail));
   ```
4. Navigate to **Settings → Localization**
5. Change time format
6. Click "Save All Settings"
7. **Check console:** Should see "✅ Event detected"
8. **Check calendar:** Time labels should update

### Scenario 2: Navigation Test

1. Open **Appointments** page
2. Note current time format (e.g., "9:00 AM")
3. Navigate to **Settings**
4. Change time format to 24h
5. Save settings
6. Navigate back to **Appointments**
7. **Check calendar:** Should show "09:00" (no AM/PM)

### Scenario 3: Force Refresh Test

1. Open **Appointments** page
2. Open console
3. Run:
   ```javascript
   await window.reinitializeCalendar(true);
   ```
4. **Check console:** Should see settings fetch and apply logs
5. **Check calendar:** Should reflect latest settings

---

## 🐛 Troubleshooting Test Results

### If "Calendar Element" shows ❌

**Problem:** Not on appointments page or element ID wrong

**Solution:**
```javascript
// Find calendar element by class
document.querySelector('.fc'); // Should find FullCalendar element
```

### If "Settings API" shows ❌

**Problem:** API endpoint not accessible or authentication issue

**Solution:**
```javascript
// Check API status
fetch('/api/v1/settings')
    .then(r => console.log('Status:', r.status, r.statusText))
    .catch(e => console.error('Error:', e));
```

### If "Event System" shows ❌

**Problem:** Event listeners not registered properly

**Solution:**
```javascript
// Check for other listeners
console.log('settingsSaved listeners:', 
    window.getEventListeners?.(document).settingsSaved?.length || 'Unknown (use Chrome DevTools)');
```

### If "Reinit Function" shows ❌

**Problem:** Function not exposed globally or script not loaded

**Solution:**
```javascript
// Check if module loaded
console.log('Main script loaded:', document.querySelector('script[src*="main.js"]') !== null);
```

---

## 📊 Performance Benchmarking

```javascript
// Measure calendar reinit performance
async function benchmarkReinit() {
    console.log('⏱️  Benchmarking calendar reinitialization...\n');
    
    const iterations = 5;
    const times = [];
    
    for (let i = 0; i < iterations; i++) {
        const start = performance.now();
        await window.reinitializeCalendar(true);
        const end = performance.now();
        const duration = end - start;
        times.push(duration);
        console.log(`Iteration ${i + 1}: ${duration.toFixed(2)}ms`);
    }
    
    const avg = times.reduce((a, b) => a + b, 0) / times.length;
    const min = Math.min(...times);
    const max = Math.max(...times);
    
    console.log('\n📊 Results:');
    console.log(`Average: ${avg.toFixed(2)}ms`);
    console.log(`Minimum: ${min.toFixed(2)}ms`);
    console.log(`Maximum: ${max.toFixed(2)}ms`);
    console.log(`\n${avg < 500 ? '✅' : '⚠️'} Target: < 500ms`);
}

await benchmarkReinit();
```

---

## ✅ Success Indicators

Your implementation is working correctly if:

1. **Console logs appear** when settings change
2. **Time format updates** within 500ms of save
3. **No errors** in console
4. **Visual changes** match settings (AM/PM vs 24h)
5. **Business hours** display correctly in chosen format

---

**Test Script Version:** 1.0.1  
**Compatible With:** xScheduler v1.0.0+  
**Browser Support:** Chrome, Safari, Firefox, Edge

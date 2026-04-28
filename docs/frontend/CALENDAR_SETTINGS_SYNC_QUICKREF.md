# Calendar Settings Sync - Quick Reference

**Last Updated:** October 8, 2025 | **Version:** 1.0.0

---

## 🎯 What It Does

Calendars automatically sync with:
- **Time Format** (12h/24h) from Settings → Localization
- **Business Hours** (start/end) from Settings → Business Hours

**Result:** Change settings → Calendar updates instantly (no reload)

---

## 📍 Where It Works

| Location | View | Syncs With |
|----------|------|------------|
| `/appointments` | Day/Week/Month | Time format + Business hours |
| `/dashboard` (Scheduler) | Day/Week/Month | Time format + Business hours + Breaks |

---

## 🔧 How It Works

### 1. Settings Are Saved
```javascript
// settings.php line 1467
document.dispatchEvent(new CustomEvent('settingsSaved', {
    detail: ['localization.time_format', 'business.work_start']
}));
```

### 2. Calendar Listens
```javascript
// app.js
document.addEventListener('settingsSaved', async (event) => {
    if (relevantChange) {
        await initializeCalendar(); // Refetch & reinit
    }
});
```

### 3. Settings Applied
```javascript
// Fetch from API
const settings = await fetch('/api/v1/settings').then(r => r.json());

// Apply to FullCalendar
new Calendar(el, {
    slotMinTime: settings['business.work_start'] + ':00',  // "08:00:00"
    slotMaxTime: settings['business.work_end'] + ':00',    // "17:00:00"
    slotLabelFormat: { hour12: settings['localization.time_format'] === '12h' }
});
```

---

## 📋 Settings API Response

```json
{
    "localization.time_format": "12h",
    "business.work_start": "08:00",
    "business.work_end": "17:00",
    "business.break_start": "12:00",
    "business.break_end": "13:00"
}
```

---

## 🎨 Format Examples

### 12h Format
```
09:00 AM
12:30 PM
05:00 PM
```

### 24h Format
```
09:00
12:30
17:00
```

---

## 🖥️ View Behavior

| View | Business Hours Applied | Time Format Applied |
|------|----------------------|-------------------|
| **Month** | ❌ No (shows all days) | ✅ Yes (event times) |
| **Week** | ✅ Yes (slot range) | ✅ Yes (all labels) |
| **Day** | ✅ Yes (slot range) | ✅ Yes (all labels) |

---

## 🐛 Debug Commands

### Check Current Settings
```javascript
fetch('/api/v1/settings')
    .then(r => r.json())
    .then(s => console.log(s));
```

### Check Calendar Config
```javascript
// If calendar exposed globally
calendar.getOption('slotMinTime');   // "08:00:00"
calendar.getOption('slotMaxTime');   // "17:00:00"
calendar.getOption('slotLabelFormat'); // { hour12: true, ... }
```

### Listen for Events
```javascript
document.addEventListener('settingsSaved', e => {
    console.log('Settings changed:', e.detail);
});
```

---

## ⚠️ Common Issues

### Calendar Not Updating
**Check:** Is `settingsSaved` event firing?
```javascript
// Add temporary listener
document.addEventListener('settingsSaved', e => console.log(e));
```

### Time Format Wrong
**Check:** API response
```javascript
fetch('/api/v1/settings')
    .then(r => r.json())
    .then(s => console.log(s['localization.time_format']));
// Should be "12h" or "24h"
```

### Business Hours Not Showing
**Check:** Are you in Month view? (Business hours only apply to Day/Week)

---

## 📂 File Locations

| File | Purpose |
|------|---------|
| `resources/js/app.js` | Appointments calendar |
| `resources/js/modules/scheduler-legacy/scheduler-dashboard.js` | Scheduler widget |
| `app/Views/settings.php` | Dispatches `settingsSaved` event |
| `resources/js/time-format-handler.js` | Business hours time display |

---

## 🧪 Quick Test

1. Open **Appointments**
2. Note current time format
3. Go to **Settings → Localization**
4. Toggle time format (12h ↔ 24h)
5. Save
6. Return to **Appointments**
7. **Expected:** Times updated immediately

---

## 📖 Full Documentation

- **Architecture:** `docs/frontend/calendar-settings-sync.md`
- **Testing:** `docs/testing/calendar-settings-sync-test.md`
- **Summary:** `docs/CALENDAR_SETTINGS_SYNC_IMPLEMENTATION.md`

---

**Quick Tip:** Settings changes propagate in < 500ms. If not, check console for errors.

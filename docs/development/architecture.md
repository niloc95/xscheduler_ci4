# Scheduler Architecture Documentation

## Overview
Single-page calendar application for managing appointments with full-featured filtering, multiple views, and real-time updates.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                         Routes                              │
│  /scheduler → Scheduler::index() [auth required]           │
│  /book → Scheduler::client() [public]                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      Controllers                            │
│                                                             │
│  Scheduler.php                                              │
│  ├── index()    → Admin scheduler dashboard                │
│  ├── client()   → Public booking interface                 │
│  ├── slots()    → GET /api/slots (available times)         │
│  └── book()     → POST /api/book (create appointment)      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                         Views                               │
│                                                             │
│  scheduler/index.php  → Main scheduler UI                   │
│  │                                                          │
│  ├── Summary cards (Today/Week/Month counts)               │
│  ├── Filter controls (Provider, Service, Date)             │
│  ├── Calendar container (#scheduler-calendar)              │
│  ├── Quick slots panel                                     │
│  └── Appointment modal                                     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      JavaScript                             │
│                                                             │
│  scheduler-dashboard.js (SINGLE SOURCE OF TRUTH)            │
│  │                                                          │
│  ├── FullCalendar initialization                           │
│  ├── Event data normalization                              │
│  ├── Localization & time format handling                   │
│  ├── Filter management                                     │
│  ├── Modal interactions                                    │
│  └── API communication                                     │
│                                                             │
│  services/scheduler-service.js                              │
│  └── API client wrapper (fetch helper)                     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                        API Layer                            │
│                                                             │
│  /api/v1/appointments                                       │
│  ├── GET    → List appointments (with filters)             │
│  ├── POST   → Create appointment                           │
│  ├── PUT    → Update appointment                           │
│  └── DELETE → Cancel appointment                           │
│                                                             │
│  /api/v1/appointments/summary                               │
│  └── GET → Aggregated counts (today/week/month)            │
│                                                             │
│  /api/v1/settings                                           │
│  └── GET → Localization & business settings                │
│                                                             │
│  /api/slots                                                 │
│  └── GET → Available time slots (provider + service)       │
│                                                             │
│  /api/book                                                  │
│  └── POST → Public booking endpoint                        │
└─────────────────────────────────────────────────────────────┘
```

---

## File Structure

```
app/
├── Controllers/
│   └── Scheduler.php                 # Single scheduler controller
│
├── Views/
│   └── scheduler/
│       └── index.php                 # Main scheduler view
│
├── Models/
│   ├── AppointmentModel.php
│   ├── ServiceModel.php
│   ├── UserModel.php
│   └── CustomerModel.php
│
└── Libraries/
    └── SlotGenerator.php             # Time slot availability logic

resources/
├── js/
│   ├── scheduler-dashboard.js        # ⭐ Main calendar script
│   └── services/
│       └── scheduler-service.js      # API client
│
└── css/
    └── fullcalendar-overrides.css    # Calendar styling

docs/
├── SCHEDULER_ARCHITECTURE.md         # This file
├── SCHEDULER_CONSOLIDATION_PLAN.md   # Refactoring history
├── calendar-ui-improvements.md       # UI enhancement details
├── day-week-view-improvements.md     # View-specific fixes
└── overlapping-appointments-fix.md   # Overlap prevention logic
```

---

## Data Flow

### 1. Page Load
```
User visits /scheduler
    ↓
Routes.php → Scheduler::index()
    ↓
Loads services & providers from DB
    ↓
Renders scheduler/index.php
    ↓
View includes scheduler-dashboard.js
    ↓
JS initializes FullCalendar
    ↓
Fetches settings from /api/v1/settings
    ↓
Applies localization (time format, timezone, first day)
    ↓
Fetches appointments from /api/v1/appointments
    ↓
Normalizes datetime strings (YYYY-MM-DD HH:MM:SS → ISO-8601)
    ↓
Maps appointments to FullCalendar events
    ↓
Renders calendar with events
```

### 2. Filter Application
```
User changes provider/service filter
    ↓
JS captures filter change event
    ↓
Builds query parameters
    ↓
Calls /api/v1/appointments?providerId=X&serviceId=Y
    ↓
Receives filtered appointments
    ↓
Clears calendar events
    ↓
Normalizes & maps new data
    ↓
Renders updated calendar
    ↓
Updates summary counts
```

### 3. Appointment Creation
```
User clicks time slot
    ↓
JS shows appointment modal
    ↓
User fills form & submits
    ↓
POST /api/v1/appointments
    ↓
Scheduler::book() validates data
    ↓
Creates/finds customer record
    ↓
Checks slot availability (race condition prevention)
    ↓
Inserts appointment into DB
    ↓
Returns appointment ID
    ↓
JS refreshes calendar
    ↓
Shows success notification
```

---

## Key Components

### **scheduler-dashboard.js**

**Responsibilities:**
- Initialize FullCalendar with correct plugins
- Fetch and apply localization settings
- Normalize appointment datetime strings
- Map database records to FullCalendar events
- Handle view switching (Day/Week/Month)
- Manage filters and refresh logic
- Show/hide appointment modal
- Render event pills with status colors

**Critical Functions:**
```javascript
normalizeEventDateTime(value)
  → Converts "YYYY-MM-DD HH:MM:SS" to ISO-8601

mapAppointmentToEvent(appointment)
  → Transforms DB record to FullCalendar event object

initializeCalendar(container, settings)
  → Sets up FullCalendar with plugins, config, and events

applyFilters()
  → Refetches appointments with current filter params

refreshCalendar()
  → Updates calendar after data changes
```

**No Duplication:**
- Event parsing happens ONCE in this file
- FullCalendar config defined ONCE
- Settings fetched ONCE on load

---

### **Scheduler.php**

**Methods:**

```php
index()
  → Loads main scheduler view
  → Passes services & providers for filters
  → Requires authentication

client()
  → Public booking interface
  → No authentication required
  → Simplified UI for customers

slots()
  → GET /api/slots?provider_id=X&service_id=Y&date=YYYY-MM-DD
  → Returns available time slots
  → Uses SlotGenerator library

book()
  → POST /api/book
  → Creates appointment & customer record
  → Final availability check (prevents race conditions)
  → Returns appointment ID
```

---

### **scheduler/index.php**

**Structure:**
```html
<div id="scheduler-dashboard">
  <!-- Header -->
  <h1>Scheduler Dashboard</h1>
  
  <!-- Summary Cards -->
  <div id="scheduler-summary">
    <button data-target-view="day">Today's Appointments</button>
    <button data-target-view="week">This Week</button>
    <button data-target-view="month">This Month</button>
  </div>
  
  <!-- Filters -->
  <div id="scheduler-filters">
    <select id="scheduler-filter-provider"></select>
    <select id="scheduler-filter-service"></select>
    <input id="scheduler-focus-date" type="date" />
  </div>
  
  <!-- Calendar -->
  <div id="scheduler-calendar"></div>
  
  <!-- Quick Slots -->
  <div id="scheduler-slots"></div>
  
  <!-- Modal -->
  <div id="scheduler-modal"></div>
</div>
```

**Key Features:**
- Material Icons for visual cues
- Gradient summary cards (clickable to switch views)
- Responsive filter layout
- Accessible modal (ARIA attributes)
- Template elements for dynamic content

---

## Settings & Localization

### **Fetched from `/api/v1/settings`:**
```json
{
  "localization.time_format": "24",
  "localization.timezone": "America/New_York",
  "localization.first_day_of_week": "0",
  "localization.language": "en",
  "business.work_start": "09:00",
  "business.work_end": "17:00"
}
```

### **Applied to FullCalendar:**
```javascript
{
  locale: settings['localization.language'],
  firstDay: parseInt(settings['localization.first_day_of_week']),
  slotMinTime: settings['business.work_start'],
  slotMaxTime: settings['business.work_end'],
  eventTimeFormat: {
    hour: '2-digit',
    minute: '2-digit',
    meridiem: settings['localization.time_format'] === '12'
  }
}
```

---

## Event Rendering

### **Status-Based Styling:**
```javascript
Confirmed  → Green border + background
Cancelled  → Red border + background
Pending    → Yellow border + background
Default    → Blue border + background
```

### **Event Structure:**
```javascript
{
  id: "123",
  title: "John Doe - Haircut",
  start: "2025-10-03T10:00:00",  // ISO-8601
  end: "2025-10-03T11:00:00",
  extendedProps: {
    status: "confirmed",
    service_name: "Haircut",
    provider_name: "Jane Smith",
    customer_name: "John Doe"
  }
}
```

---

## Overlap Prevention

### **FullCalendar Config:**
```javascript
{
  slotEventOverlap: false,  // No overlapping events in Day/Week view
  forceEventDuration: true, // Events must have start + end
  eventOverlap: false       // Prevent manual dragging into overlap
}
```

### **Backend Validation:**
```php
SlotGenerator::getAvailableSlots()
  → Checks existing appointments
  → Excludes blocked time periods
  → Returns only available slots

Scheduler::book()
  → Re-checks availability before insert
  → Returns 409 Conflict if slot taken
```

---

## Testing Checklist

### **Functional Tests:**
- [ ] Calendar loads without errors
- [ ] Day/Week/Month views switch correctly
- [ ] Appointments render in correct time slots
- [ ] Filters apply correctly (provider, service, date)
- [ ] Summary counts match appointment totals
- [ ] Modal opens/closes on event click
- [ ] Time format respects localization setting (12h/24h)
- [ ] Timezone conversion works correctly
- [ ] First day of week setting applied
- [ ] No appointment overlaps in Day/Week view

### **Browser Testing:**
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### **Responsive Testing:**
- [ ] Desktop (1920x1080)
- [ ] Tablet (768x1024)
- [ ] Mobile (375x667)

---

## Performance Considerations

### **Optimizations:**
1. **Lazy Loading**: Appointments fetched only for visible date range
2. **Debounced Filters**: Filter changes debounced to prevent excessive API calls
3. **Progressive Rendering**: FullCalendar renders incrementally
4. **Cached Settings**: Localization settings fetched once on load
5. **Indexed Queries**: Database queries use indexes on `start_time`, `provider_id`, `service_id`

### **Future Improvements:**
- Implement request cancellation for abandoned filters
- Add pagination for appointment list
- Cache appointment counts in Redis
- WebSocket updates for real-time appointment changes

---

## Security

### **Authentication:**
- Admin scheduler requires `auth` filter
- API endpoints protected by `api_auth` filter
- Public booking uses rate limiting

### **Authorization:**
- Providers can only see own appointments (unless admin)
- Customers can only book within business hours
- Slot availability re-checked on booking (prevents race conditions)

### **Data Validation:**
- Input sanitization in controller methods
- Time slot validation before booking
- SQL injection prevention via query builder

---

## Troubleshooting

### **Calendar not loading:**
1. Check console for JavaScript errors
2. Verify `/api/v1/settings` returns data
3. Confirm `scheduler-dashboard.js` loaded
4. Check `#scheduler-calendar` div exists

### **Appointments not showing:**
1. Verify `/api/v1/appointments` returns data
2. Check datetime format (must be YYYY-MM-DD HH:MM:SS)
3. Confirm timezone settings match database
4. Look for normalization errors in console

### **Time slots wrong:**
1. Check `business.work_start` and `business.work_end` settings
2. Verify timezone conversion
3. Confirm time format setting (12h vs 24h)

### **Filters not working:**
1. Check filter dropdowns populate
2. Verify API query parameters
3. Confirm event listeners attached
4. Look for JS errors in console

---

## Change Log

### **2025-10-03: Major Consolidation**
- ✅ Removed `Schedule.php` controller (unused)
- ✅ Removed `Scheduler::dashboard()` method (no view)
- ✅ Renamed `Scheduler::custom()` → `Scheduler::index()`
- ✅ Renamed view: `scheduler/custom.php` → `scheduler/index.php`
- ✅ Deleted legacy calendar scripts: `calendar-clean.js`, `calendar-test.js`, `custom-cal.js`
- ✅ Deleted stubbed `schedule-core.js`
- ✅ Updated routes: `/scheduler` → `Scheduler::index`
- ✅ Build verified: 242 modules, 1.71s

### **Result:**
- **Single controller**: `Scheduler.php`
- **Single view**: `scheduler/index.php`
- **Single JS file**: `scheduler-dashboard.js`
- **Zero duplication**: All calendar logic consolidated

---

## Developer Notes

### **When to modify scheduler-dashboard.js:**
- Adding new calendar views
- Changing event rendering
- Updating time slot logic
- Adding new filters
- Modifying modal behavior

### **When to modify Scheduler.php:**
- Adding new API endpoints
- Changing booking logic
- Updating slot generation
- Adding new validation rules

### **When to modify scheduler/index.php:**
- Adding UI elements
- Changing layout
- Adding new filter controls
- Updating modal structure

### **When to modify fullcalendar-overrides.css:**
- Styling event pills
- Adjusting calendar colors
- Adding dark mode support
- Changing spacing/padding

---

## Related Documentation

- [Scheduler Consolidation Plan](SCHEDULER_CONSOLIDATION_PLAN.md)
- [Calendar UI Improvements](calendar-ui-improvements.md)
- [Day/Week View Improvements](day-week-view-improvements.md)
- [Overlapping Appointments Fix](overlapping-appointments-fix.md)
- [Appointment Time Rendering Fix](appointment-time-rendering-fix.md)

---

**Last Updated**: October 3, 2025  
**Maintainer**: Development Team

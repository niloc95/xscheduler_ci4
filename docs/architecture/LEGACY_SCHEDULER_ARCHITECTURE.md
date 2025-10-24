# Legacy Scheduler Architecture (FullCalendar-based)

> ⚠️ **ARCHIVED**: This module was retired in favor of the new Appointments experience.  
> **Status**: Archived (routes now redirect to `/appointments`)  
> **Timeline**: Maintained in production until October 7, 2025; retained here for historical reference  

## Overview

Originally, the Scheduler module provided a FullCalendar-based interface for managing appointments, availability, and bookings across admin, provider, and staff roles. As of October 7, 2025 the UI has been removed from the application and `/scheduler` now permanently redirects to the modern Appointments module.

**Module Namespace**: `scheduler-legacy` – the entire legacy implementation (controller, view, JS module, and services) was relocated here in October 2025 to keep deprecated code isolated from the new Appointments experience.

**Route**: `/scheduler`  
**Navigation Label**: "Schedule"  
**Access Levels**: admin, provider, staff (authenticated)

## Architecture Flow

```
┌─────────────────────────────────────────────────┐
│ USER REQUEST: /scheduler                        │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│ CONTROLLER: app/Controllers/Scheduler.php       │
│                                                  │
│ Methods:                                         │
│ • index()    - Main scheduler dashboard         │
│ • client()   - Public booking interface         │
│ • slots()    - GET /api/slots (availability)    │
│ • book()     - POST /api/book (create booking)  │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│ VIEW: app/Views/scheduler-legacy/index.php      │ (Stub returns empty response)
│                                                  │
│ Includes:                                        │
│ • Unified sidebar (navigation)                  │
│ • Header with page title                        │
│ • Main content container (#scheduler-dashboard) │
│ • Filter controls (provider, service, date)     │
│ • Calendar container (#scheduler-calendar)      │
│ • Modal dialogs for appointment CRUD            │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│ FRONTEND: resources/js/modules/scheduler-legacy/│
│            scheduler-dashboard.js               │ (no longer bundled)
│                                                  │
│ Dependencies:                                    │
│ • @fullcalendar/core                            │
│ • @fullcalendar/daygrid (Month view)            │
│ • @fullcalendar/timegrid (Day/Week views)       │
│ • @fullcalendar/interaction (drag/click)        │
│ • services/scheduler-legacy/scheduler-service.js│
│   (API wrapper)                                 │
│                                                  │
│ Features:                                        │
│ • Calendar initialization with FullCalendar     │
│ • View switching (Day/Week/Month)               │
│ • Event rendering with color coding             │
│ • Filter application (provider, service, date)  │
│ • Summary cards (Today/Week/Month counts)       │
│ • Modal-based appointment CRUD                  │
│ • Quick slots display                           │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│ SERVICE LAYER: services/scheduler-legacy/       │
│                scheduler-service.js             │
│                                                  │
│ API Methods:                                     │
│ • listAppointments(params)                      │
│ • getAppointment(id)                            │
│ • createAppointment(data)                       │
│ • updateAppointment(id, data)                   │
│ • deleteAppointment(id)                         │
│ • listProviders()                               │
│ • listServices()                                │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│ BACKEND API: app/Controllers/Api/V1/            │
│                                                  │
│ Endpoints:                                       │
│ • GET    /api/v1/appointments                   │
│ • GET    /api/v1/appointments/:id               │
│ • POST   /api/v1/appointments                   │
│ • PUT    /api/v1/appointments/:id               │
│ • DELETE /api/v1/appointments/:id               │
│ • GET    /api/slots (scheduler-specific)        │
│ • POST   /api/book (scheduler-specific)         │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│ DATA LAYER: app/Models/                         │
│                                                  │
│ Models:                                          │
│ • AppointmentModel - Appointment CRUD           │
│ • ServiceModel - Service definitions            │
│ • UserModel - Providers and staff               │
│ • CustomerModel - Customer records              │
│                                                  │
│ Tables:                                          │
│ • xs_appointments                               │
│ • xs_services                                   │
│ • xs_users (providers)                          │
│ • xs_customers                                  │
└──────────────────────────────────────────────────┘
```

## File Inventory

### Controllers
- **`app/Controllers/Scheduler.php`** (126 lines)
  - Main scheduler controller
  - Handles dashboard, public booking, slots API, book API

### Views
- **`app/Views/scheduler-legacy/index.php`** (Stub since Oct 7, 2025)
  - Previously rendered the legacy scheduler dashboard UI
  - Replaced with a no-op stub when `/scheduler` was redirected to `/appointments`

### JavaScript
- **`resources/js/modules/scheduler-legacy/scheduler-dashboard.js`** (~1,280 lines, retained for historical reference)
  - FullCalendar initialization and configuration (legacy)
  - Event rendering and color coding
  - Filter logic and application
  - Modal management for CRUD operations
  - Summary card updates
  - Quick slots rendering

- **`resources/js/services/scheduler-legacy/scheduler-service.js`** (168 lines, retained for historical reference)
  - API wrapper for scheduler operations (legacy)
  - HTTP request abstraction
  - Error handling and response normalization

### Styles
- **`resources/css/fullcalendar-overrides.css`** (~200 lines)
  - Custom styles for FullCalendar components
  - Dark mode support
  - Responsive design overrides
  - Event pill styling

- **Build Output: `public/build/assets/scheduler-dashboard.css`** (retired Oct 7, 2025)
  - Formerly compiled styles including fullcalendar overrides

- **Build Output: `public/build/assets/scheduler-dashboard.js`** (retired Oct 7, 2025)
  - Formerly compiled JavaScript bundle for the legacy scheduler
  - `vite.config.js` no longer declares the `scheduler-dashboard` entry

## Routes

From `app/Config/Routes.php`:

```php
// Scheduler Routes (legacy paths now redirect to Appointments)
$routes->group('scheduler', ['filter' => 'setup'], static function($routes) {
  $routes->get('', 'Scheduler::index', ['filter' => 'auth']); // 308 redirect to /appointments
});

// Public booking interface (legacy redirect)
$routes->get('book', 'Scheduler::client', ['filter' => 'setup']);

// Scheduler API routes (lines 180-184)
$routes->group('api', function($routes) {
    $routes->get('slots', 'Scheduler::slots');
    $routes->post('book', 'Scheduler::book');
});
```

## Dependencies

### NPM Packages
```json
{
  "@fullcalendar/core": "^6.x",
  "@fullcalendar/daygrid": "^6.x",
  "@fullcalendar/timegrid": "^6.x",
  "@fullcalendar/interaction": "^6.x"
}
```

### PHP Dependencies
- CodeIgniter 4 Framework
- Standard CI4 Models (BaseModel)
- SlotGenerator library for availability calculation

## Key Features

### 1. Calendar Views
- **Day View**: Detailed time slots for single day
- **Week View**: 7-day overview with time slots
- **Month View**: Full month grid with appointment counts

### 2. Filtering
- **Provider Filter**: Show appointments for specific provider
- **Service Filter**: Show appointments for specific service
- **Date Focus**: Jump to specific date

### 3. Appointment Management
- **Create**: Modal-based form for new appointments
- **Read**: Click events to view details
- **Update**: Edit appointment details
- **Delete**: Remove appointments with confirmation

### 4. Summary Cards
- **Today**: Count of appointments today (links to Day view)
- **This Week**: Count of appointments this week (links to Week view)
- **This Month**: Count of appointments this month (links to Month view)

### 5. Quick Slots
- Display next available time slots
- Based on current filter selection
- Quick booking from slot list

### 6. Color Coding
- **By Status**: Different colors for confirmed, pending, cancelled, completed
- **By Service**: Color palette assigned to services
- **By Provider**: Color palette assigned to providers

## API Endpoints Used

### REST API (v1)
- `GET /api/v1/appointments` - List appointments (with filters)
- `GET /api/v1/appointments/:id` - Get single appointment
- `POST /api/v1/appointments` - Create appointment
- `PUT /api/v1/appointments/:id` - Update appointment
- `DELETE /api/v1/appointments/:id` - Delete appointment

### Scheduler-Specific API
- `GET /api/slots?provider_id=X&service_id=Y&date=YYYY-MM-DD` - Get available slots
- `POST /api/book` - Create public booking (customer-facing)

## Shared Dependencies

### Models (Used by Both Systems)
- ✅ `AppointmentModel` - Will be shared
- ✅ `ServiceModel` - Will be shared
- ✅ `UserModel` - Will be shared
- ✅ `CustomerModel` - Will be shared

### Libraries
- ✅ `SlotGenerator` - Availability calculation (will be shared)

### Utilities
- ✅ Settings system - Time format, business hours
- ✅ Permissions helper - Role-based access
- ✅ Date/time utilities - Timezone handling

## Known Issues / Limitations

### Resolved
- ✅ Day/Week view time slot rendering fixed
- ✅ Datetime normalization implemented
- ✅ Legacy file conflicts removed

### Current Limitations
1. **No Drag-and-Drop**: Cannot reschedule by dragging events
2. **No Recurring Appointments**: Each appointment is standalone
3. **Limited Conflict Detection**: Basic overlap checking only
4. **Mobile UX**: Could be improved for smaller screens
5. **No Real-time Updates**: Manual refresh required

## Performance Metrics

### Asset Sizes
- JavaScript: 291.90 KB (84.20 KB gzipped)
- CSS: 15.39 KB (2.64 KB gzipped)
- **Total**: ~307 KB (87 KB gzipped)

### Load Times (Approximate)
- Initial page load: < 1s
- Calendar render: < 500ms
- Filter application: < 300ms
- View switching: < 200ms

## Migration Considerations

### Features to Preserve in New System
1. ✅ Multiple view modes (Day/Week/Month/List)
2. ✅ Filter by provider/service/date
3. ✅ Color coding by status/service/provider
4. ✅ Quick summary cards
5. ✅ Modal-based CRUD operations
6. ✅ Available slots display
7. ✅ Public booking interface

### Features to Improve
1. 🔄 Add drag-and-drop rescheduling
2. 🔄 Implement recurring appointments
3. 🔄 Enhanced conflict detection UI
4. 🔄 Better mobile responsiveness
5. 🔄 Real-time updates (WebSockets)
6. 🔄 Bulk operations
7. 🔄 Advanced filtering (status, date range, custom fields)

### Breaking Changes to Plan For
- ⚠️ Different URL structure (`/scheduler` → `/appointments`)
- ⚠️ Different JavaScript API (replace FullCalendar with custom solution)
- ⚠️ Different event data structure
- ⚠️ Different modal implementation
- ⚠️ Need to update bookmarks/saved links

## Deprecation Timeline

### Phase 1: Preparation (Current)
- ✅ Document legacy architecture
- ✅ Isolate scheduler code
- 🔄 Create new appointment view skeleton
- 🔄 Implement feature flag

### Phase 2: Development
- 🔄 Build new appointment view UI
- 🔄 Implement calendar component
- 🔄 Port features from legacy scheduler
- 🔄 Add improvements and new features

### Phase 3: Testing
- 🔄 Feature parity verification
- 🔄 User acceptance testing
- 🔄 Performance testing
- 🔄 Accessibility audit

### Phase 4: Migration
- 🔄 Enable feature flag for testing
- 🔄 Gradual rollout (beta users first)
- 🔄 Monitor for issues
- 🔄 Update documentation

### Phase 5: Deprecation
- 🔄 Full cutover to new system
- 🔄 Remove legacy scheduler code
- 🔄 Clean up routes and references
- 🔄 Archive for future reference

## Support & Maintenance

### Until Replacement
- ✅ Bug fixes will be applied
- ✅ Security updates will be applied
- ⚠️ New features will NOT be added
- ⚠️ Major refactoring will NOT be done

### After Replacement
- ⏸️ Code will be archived
- ⏸️ No further maintenance
- ⏸️ Reference-only documentation

## References

### Related Documentation
- `docs/SCHEDULER_ARCHITECTURE.md` - Original architecture doc
- `docs/SCHEDULER_CONSOLIDATION_PLAN.md` - Consolidation history
- `docs/SCHEDULE_VIEW_REMOVAL_SUMMARY.md` - Legacy cleanup summary
- `docs/calendar-ui-quickref.md` - UI quick reference

### Related Files
- `app/Controllers/Scheduler.php` - Controller (routes now redirect to Appointments)
- `app/Views/scheduler-legacy/index.php` - View stub (outputs nothing since Oct 7, 2025)
- `resources/js/modules/scheduler-legacy/scheduler-dashboard.js` - Frontend logic (archived)
- `resources/js/services/scheduler-legacy/scheduler-service.js` - API service (archived)
- `vite.config.js` - Build configuration (legacy entry removed Oct 7, 2025)

---

**Last Updated**: October 7, 2025  
**Status**: Active (Deprecated)  
**Replacement**: New Appointment View (In Development)

# Legacy Scheduler Architecture (FullCalendar-based)

> ⚠️ **DEPRECATED**: This module is being phased out in favor of the new Appointment View.  
> **Status**: Active but will be replaced  
> **Timeline**: Maintained until new Appointment View reaches feature parity  

## Overview

The current Scheduler module provides a FullCalendar-based interface for managing appointments, availability, and bookings. It serves admin, provider, and staff roles.

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
│ VIEW: app/Views/scheduler/index.php             │
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
│ FRONTEND: resources/js/scheduler-dashboard.js   │
│                                                  │
│ Dependencies:                                    │
│ • @fullcalendar/core                            │
│ • @fullcalendar/daygrid (Month view)            │
│ • @fullcalendar/timegrid (Day/Week views)       │
│ • @fullcalendar/interaction (drag/click)        │
│ • services/scheduler-service.js (API wrapper)   │
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
│ SERVICE LAYER: services/scheduler-service.js    │
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
- **`app/Views/scheduler/index.php`** (235 lines)
  - Main scheduler dashboard view
  - Contains filter UI, calendar container, modals, templates

### JavaScript
- **`resources/js/scheduler-dashboard.js`** (1,275 lines)
  - FullCalendar initialization and configuration
  - Event rendering and color coding
  - Filter logic and application
  - Modal management for CRUD operations
  - Summary card updates
  - Quick slots rendering

- **`resources/js/services/scheduler-service.js`** (168 lines)
  - API wrapper for scheduler operations
  - HTTP request abstraction
  - Error handling and response normalization

### Styles
- **`resources/css/fullcalendar-overrides.css`** (~200 lines)
  - Custom styles for FullCalendar components
  - Dark mode support
  - Responsive design overrides
  - Event pill styling

- **Build Output: `public/build/assets/scheduler-dashboard.css`** (15.39 KB)
  - Compiled styles including overrides

- **Build Output: `public/build/assets/scheduler-dashboard.js`** (291.90 KB, 84.20 KB gzipped)
  - Compiled JavaScript with all dependencies

## Routes

From `app/Config/Routes.php`:

```php
// Scheduler Routes (lines 170-178)
$routes->group('scheduler', ['filter' => 'setup'], function($routes) {
    // Default scheduler page
    $routes->get('', 'Scheduler::index', ['filter' => 'auth']);
});

// Public booking interface
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
- `app/Controllers/Scheduler.php` - Controller
- `app/Views/scheduler/index.php` - View
- `resources/js/scheduler-dashboard.js` - Frontend logic
- `resources/js/services/scheduler-service.js` - API service
- `vite.config.js` - Build configuration

---

**Last Updated**: October 5, 2025  
**Status**: Active (Deprecated)  
**Replacement**: New Appointment View (In Development)

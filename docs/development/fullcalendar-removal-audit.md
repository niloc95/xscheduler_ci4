# FullCalendar Removal Audit
**Date:** October 27, 2025  
**Status:** Phase 1 - Dependency Audit  
**Goal:** Document all FullCalendar dependencies for safe removal and custom scheduler integration

---

## Executive Summary

FullCalendar v6 is currently integrated throughout the codebase for appointment scheduling visualization. This audit documents all dependencies to enable safe removal while preserving:
- Appointment data and database schema
- Business logic for scheduling (slots, availability, business hours)
- API endpoints for appointment management
- Backend services (CalendarConfigService, LocalizationSettings, etc.)

---

## 1. NPM Package Dependencies

### Packages to Remove
Located in `package.json`:
```json
"@fullcalendar/core": "^6.1.15",
"@fullcalendar/daygrid": "^6.1.15",
"@fullcalendar/interaction": "^6.1.15",
"@fullcalendar/luxon3": "^6.1.15",
"@fullcalendar/timegrid": "^6.1.15"
```

### Related Dependencies
- `luxon`: "^3.5.0" - **KEEP** (used by timezone services independently)

**Action Required:**
```bash
npm uninstall @fullcalendar/core @fullcalendar/daygrid @fullcalendar/interaction @fullcalendar/luxon3 @fullcalendar/timegrid
```

---

## 2. JavaScript/TypeScript Files

### Primary FullCalendar Module
**File:** `resources/js/modules/appointments/appointments-calendar.js` (655 lines)
- **Status:** DELETE - Entire file dedicated to FullCalendar
- **Dependencies:** 
  - Imports: `@fullcalendar/core`, `@fullcalendar/daygrid`, `@fullcalendar/timegrid`, `@fullcalendar/interaction`, `@fullcalendar/luxon3`
  - Uses: timezone-helper.js (PRESERVE - reusable utility)
- **Exports Used By:** `app.js`

### Main Application Entry
**File:** `resources/js/app.js` (400 lines)
- **Status:** REFACTOR - Remove FullCalendar imports and initialization
- **Lines to Remove/Modify:**
  - Line ~5: `import { initAppointmentsCalendar, setupViewButtons, refreshCalendar, showAppointmentModal } from './modules/appointments/appointments-calendar.js';`
  - Lines ~200-280: `initializeCalendar()` function
  - Lines ~350-370: Calendar-related event listeners
- **Preserve:**
  - Import of Charts.js
  - Appointment form initialization
  - Generic dashboard functionality

**Action:** Replace calendar initialization with placeholder component init

---

## 3. CSS/SCSS Files

### FullCalendar Custom Styles
**File:** `resources/css/fullcalendar-overrides.css` (500+ lines)
- **Status:** DELETE - Entire file for FullCalendar theming
- **Contains:**
  - `.fc-*` class overrides
  - Dark mode styles for calendar
  - Event styling and positioning
  - Grid and slot customization

### SCSS Main File
**File:** `resources/scss/app-consolidated.scss`
- **Status:** MODIFY - Remove FullCalendar imports
- **Lines to Remove:**
  - Line 42-44: FullCalendar comments and import
  - `@import '../css/fullcalendar-overrides.css';`
  - Lines 119-255: FullCalendar v6 Tailwind integration section
  - Lines 257-320: Dark Mode FullCalendar section

**Action:** Remove import and FC-specific style blocks

---

## 4. PHP Controllers

### API Controllers with FullCalendar References

#### `app/Controllers/Api/Appointments.php`
- **Status:** REFACTOR - Remove FC-specific comments, keep logic
- **Lines to Modify:**
  - Line 41: Comment "FullCalendar sends ISO 8601 dates..."
  - Line 70: Comment "Transform data for FullCalendar with provider colors"
- **Preserve:**
  - All appointment CRUD methods
  - Date range filtering
  - Provider/service filtering
  - Event data transformation (still needed for custom scheduler)

**Action:** Update comments to reference "custom scheduler" instead

#### `app/Controllers/Api/V1/Settings.php`
- **Status:** KEEP - Only comment references FullCalendar
- **Method:** `calendarConfig()` 
  - Line 45-48: Comment mentions FullCalendar but method returns generic config
  - **Action:** Update comment to "Returns calendar-specific configuration..."

---

## 5. PHP Services

### CalendarConfigService
**File:** `app/Services/CalendarConfigService.php`
- **Status:** REFACTOR - Keep logic, update comments
- **Current Purpose:** Generate FullCalendar-compatible configuration
- **Future Purpose:** Generate custom scheduler configuration
- **Methods to Preserve:**
  - `getTimeFormat()` - Time format preferences
  - `getFirstDayOfWeek()` - Localization
  - `getSlotDuration()` - Slot granularity
  - `getSlotMinTime/MaxTime()` - Business hours bounds
  - `getTimezone()` - Timezone handling
  - `getBusinessHoursForCalendar()` - Working hours
- **Methods to Update:**
  - `getJavaScriptConfig()` - Rename to `getSchedulerConfig()` or similar
  - Remove FullCalendar-specific keys (e.g., `headerToolbar`, `navLinks`)
  - Add custom scheduler-specific configuration

**Action:** Rename methods and update configuration keys for custom scheduler

### LocalizationSettingsService  
**File:** `app/Services/LocalizationSettingsService.php`
- **Status:** KEEP - No FullCalendar dependencies
- **Purpose:** Generic timezone and time format handling
- **Used by:** CalendarConfigService, TimezoneService, Controllers

### TimezoneService
**File:** `app/Services/TimezoneService.php`
- **Status:** KEEP - Generic timezone utilities
- **Purpose:** UTC conversion, timezone validation, offset calculation

---

## 6. Views/Templates

### Appointments Index View
**File:** `app/Views/appointments/index.php`
- **Status:** MODIFY - Replace calendar container with placeholder
- **Lines to Modify:**
  - Lines 89-122: Calendar toolbar and container
  - Current: `<div id="appointments-inline-calendar">`
  - Replace with: Custom scheduler placeholder component
- **Preserve:**
  - Stats cards
  - Filter buttons
  - Appointment list
  - Modal markup

**Action:** Create scheduler placeholder component

---

## 7. Routes

### No Dedicated Calendar Routes
**Status:** ✅ NO ACTION REQUIRED
- All calendar functionality is embedded in `/appointments` page
- No standalone `/calendar` route exists
- API endpoints under `/api/appointments` are generic (not FC-specific)

---

## 8. Configuration Files

### Vite Config
**File:** `vite.config.js`
- **Status:** ✅ NO FC-SPECIFIC CONFIGURATION
- No FullCalendar aliases or special handling

### Tailwind Config
**File:** `tailwind.config.js`
- **Status:** ✅ NO FC-SPECIFIC CONFIGURATION

---

## 9. Documentation (Reference Only)

### Files Mentioning FullCalendar (No Code Impact)
- `docs/README.md`
- `docs/development/calendar-implementation.md`
- `docs/development/timezone-fix.md`
- `docs/development/TIMEZONE-ALIGNMENT-FINAL-REPORT.md`
- `docs/fixes/CALENDAR_SYNC_FIX_OCT_26_2025.md`
- `docs/architecture/LEGACY_SCHEDULER_ARCHITECTURE.md`
- `docs/ui-ux/calendar-ui-overview.md`

**Action:** Update documentation to reflect custom scheduler after migration

---

## 10. Database Schema

### Tables to PRESERVE (Used by Custom Scheduler)
- ✅ `appointments` - Core appointment data
- ✅ `xs_users` - Provider/staff/customer records
- ✅ `xs_services` - Service definitions
- ✅ `business_hours` - Provider availability
- ✅ `xs_customers` - Customer contact info
- ✅ `xs_settings` - System configuration

**Status:** ✅ NO DATABASE CHANGES REQUIRED

---

## 11. API Endpoints to PRESERVE

### Appointment Management
- `GET /api/appointments` - Fetch appointments with filters
- `GET /api/appointments/{id}` - Single appointment details
- `POST /api/appointments` - Create appointment
- `PATCH /api/appointments/{id}` - Update appointment
- `DELETE /api/appointments/{id}` - Cancel appointment
- `GET /api/appointments/counts` - Stats summary
- `PATCH /api/appointments/{id}/status` - Status updates

### Settings & Configuration
- `GET /api/v1/settings` - Fetch settings
- `GET /api/v1/settings/calendar-config` - Calendar configuration

**Status:** ✅ ALL ENDPOINTS PRESERVED - Generic, not FC-specific

---

## 12. Backup Strategy

### Create Backup Before Removal
```bash
mkdir -p _legacy/fullcalendar_backup_2025_10_27
cp -r resources/js/modules/appointments/appointments-calendar.js _legacy/fullcalendar_backup_2025_10_27/
cp -r resources/css/fullcalendar-overrides.css _legacy/fullcalendar_backup_2025_10_27/
```

---

## 13. Removal Checklist

### Phase 1: NPM Packages
- [ ] Uninstall @fullcalendar/* packages
- [ ] Run `npm install` to update lock file
- [ ] Keep `luxon` package (used independently)

### Phase 2: JavaScript Files
- [ ] Delete `resources/js/modules/appointments/appointments-calendar.js`
- [ ] Remove FC imports from `resources/js/app.js`
- [ ] Remove `initializeCalendar()` function
- [ ] Remove FC event listeners
- [ ] Create stub `initSchedulerPlaceholder()` function

### Phase 3: CSS Files
- [ ] Delete `resources/css/fullcalendar-overrides.css`
- [ ] Remove FC import from `resources/scss/app-consolidated.scss`
- [ ] Remove FC style blocks (lines 119-320)

### Phase 4: PHP Files
- [ ] Update comments in `app/Controllers/Api/Appointments.php`
- [ ] Update comments in `app/Controllers/Api/V1/Settings.php`
- [ ] Refactor `CalendarConfigService` (optional - can defer)

### Phase 5: Views
- [ ] Create scheduler placeholder component
- [ ] Update `app/Views/appointments/index.php` calendar section
- [ ] Remove FC toolbar markup

### Phase 6: Build & Test
- [ ] Run `npm run build`
- [ ] Test appointments page loads without errors
- [ ] Verify no console errors related to FC
- [ ] Verify API endpoints still functional
- [ ] Test appointment list display

### Phase 7: Documentation
- [ ] Mark calendar docs as deprecated/legacy
- [ ] Create new scheduler integration docs
- [ ] Update README with scheduler status

---

## 14. Risk Assessment

### Low Risk Items
- ✅ Removing NPM packages (isolated dependency)
- ✅ Deleting standalone CSS file
- ✅ Removing dedicated JS module

### Medium Risk Items
- ⚠️ Modifying app.js (main entry point)
- ⚠️ Updating appointments view (user-facing)

### Mitigation Strategy
1. Create git branch: `feature/remove-fullcalendar`
2. Backup all modified files
3. Test after each phase
4. Keep rollback plan ready

---

## 15. Post-Removal Validation

### Functional Tests
- [ ] Page `/appointments` loads without errors
- [ ] Placeholder scheduler component renders
- [ ] Appointment list displays correctly
- [ ] Stats cards show accurate counts
- [ ] Filter buttons work (even if no effect yet)
- [ ] Create appointment link functions
- [ ] API calls return expected data

### Technical Tests
- [ ] No FullCalendar references in browser console
- [ ] No FC-related network requests
- [ ] No `.fc-*` classes in DOM
- [ ] Build completes without warnings
- [ ] All tests pass (if test suite exists)

---

## 16. Next Steps After Removal

1. **Create Custom Scheduler Component**
   - Design week/day/month view components
   - Implement appointment rendering logic
   - Add drag-and-drop functionality
   - Style with Tailwind CSS

2. **Update CalendarConfigService**
   - Rename to `SchedulerConfigService`
   - Add custom scheduler-specific settings
   - Update configuration format

3. **Integration Testing**
   - Test with real appointment data
   - Verify timezone handling
   - Test provider color coding
   - Validate business hours display

---

## Estimated Effort

- **Phase 1-3 (Package/File Removal):** 1-2 hours
- **Phase 4-5 (Code Refactoring):** 2-3 hours
- **Phase 6 (Testing & Validation):** 1-2 hours
- **Phase 7 (Documentation):** 1 hour

**Total:** ~5-8 hours for complete FullCalendar removal

---

## Dependencies Summary

### To Remove
- 5 NPM packages (@fullcalendar/*)
- 1 JavaScript module (appointments-calendar.js)
- 1 CSS file (fullcalendar-overrides.css)
- ~200 lines of SCSS
- Calendar container markup in view

### To Preserve
- All PHP services
- All API endpoints
- All database tables
- Timezone utilities
- Appointment business logic
- luxon package

### To Refactor
- app.js (remove FC initialization)
- appointments/index.php (replace calendar markup)
- CalendarConfigService (update comments/method names)

---

## Approval Required

**Checklist before proceeding:**
- [x] Audit complete and documented
- [ ] Backup strategy reviewed
- [ ] Risk mitigation plan approved
- [ ] Custom scheduler design ready
- [ ] Timeline approved

**Proceed to Phase 2:** Remove NPM Packages

# Locations Feature — Complete Implementation

## Overview

The Locations feature allows providers to operate from multiple physical locations.
Each location can be assigned to specific days of the week, and appointments
capture a snapshot of the location at booking time.

## Database Architecture

### Tables

| Table | Purpose |
|---|---|
| `xs_locations` | Location master data (name, address, phone, provider_id, is_primary, is_active) |
| `xs_location_days` | Many-to-many mapping: location → day_of_week (tinyint unsigned 0–6) |
| `xs_appointments` | Snapshot columns: `location_id`, `location_name`, `location_address`, `location_contact` |
| `xs_provider_schedules` | Has `location_id` column (vestigial — unused by design, see below) |

### Why `xs_provider_schedules.location_id` is Vestigial

A provider's day can have **multiple locations** (e.g., Monday at both Location A
and Location B). The single `location_id` column on `xs_provider_schedules` cannot
represent this. The correct many-to-many relationship is modeled via
`xs_location_days`. The column remains for backward compatibility but is not
read or written by the application.

### Location–Day Flow

1. Admin edits provider schedule → form posts `schedule[monday][locations][] = [5, 7]`
2. `UserManagement::update()` calls `syncLocationDaysFromSchedule()` which inverts
   the form data to per-location day lists and writes to `xs_location_days`
3. `AvailabilityService::getAvailableSlots()` calls `LocationModel::getLocationDays()`
   to check if a given day is valid for the selected location

## Admin Appointment Create/Edit Form (`appointments/form.php`, `time-slots-ui.js`)

A dynamic **Location** dropdown is shown between Service and Date/Time when the
selected provider has locations:

- `time-slots-ui.js` calls `GET /api/locations?provider_id=X&include_days=1` on
  provider change and populates the `<select id="location_id">`
- Location change clears date/time and reloads available slots with `location_id`
  in the calendar API call cache key
- On **create** (`store()`): `location_id` is passed to `AppointmentBookingService`
  which snapshots location data via `LocationModel::getLocationSnapshot()`
- On **edit** (`update()`): Same snapshot logic. Clears all four snapshot columns
  if no location is selected
- `form.php` Appointment Summary panel shows/hides the location row
- Edit mode pre-selects the existing `location_id` via `preselectLocationId`

## Public Booking — Reschedule Customer Fields (`PublicBookingService`, `public-booking.js`)

When a customer uses the **Manage Booking → Reschedule** flow, all customer
fields (first name, last name, email, phone, address) are now pre-populated:

- `PublicBookingService::formatPublicAppointment()` loads the full customer record
  and returns all five fields in the `customer` object
- `public-booking.js` `enterRescheduleStage()` maps all five fields into the
  reactive form state

## Admin Backend Integration

### Appointment Details Modal (`appointment-details-modal.js`)

Location section is rendered between "Service & Provider" and "Notes":
- Location Name (with `business` icon)
- Location Address (with `map` icon) — hidden if empty
- Location Contact (with `call` icon, clickable `tel:` link) — hidden if empty
- Entire section hidden when `appointment.locationName` is falsy

### Week View Summary (`scheduler-week-view.js`)

Each appointment card in `renderAppointmentSummaryList()` now shows a location
line (with `location_on` icon) beneath the time/service line when
`apt.locationName` is present.

### Day View (`scheduler-day-view.js`)

Already showed `locationName` with `location_on` icon — no changes needed.

### Advanced Filter Panel (`appointments/index.php`, `advanced-filters.js`)

A **Location** dropdown has been added to the advanced filter panel:
- Grid expanded from 4 to 5 columns
- `#filter-location` dropdown populated server-side from `xs_locations` (active only)
- Filter applies client-side via `scheduler.setFilters({ locationId })` matching
  `appointment.locationId`
- Clear button resets location filter
- Filter indicator badge accounts for location filter

### Scheduler Core (`scheduler-core.js`)

- `setFilters()` accepts optional `locationId` parameter
- `getFilteredAppointments()` applies location filter when `activeFilters.locationId`
  is set, comparing against `apt.locationId`

## API Integration

### `GET /api/appointments` (`Api/Appointments::index`)

- Accepts optional `locationId` query parameter
- Applies `WHERE xs_appointments.location_id = ?` filter
- Response already includes `locationId`, `locationName`, `locationAddress`,
  `locationContact` in each appointment object

### `GET /api/availability/calendar` (`Api/Availability::calendar`)

- Now accepts optional `location_id` query parameter
- Passes through to `AvailabilityService::getCalendarAvailability()` as 7th argument
- Enables location-aware availability checking in admin context (previously only
  available via public `BookingController::calendar`)

## Files Modified

| File | Change |
|---|---|
| `app/Controllers/Api/Availability.php` | Read & pass `location_id` param to `getCalendarAvailability()` |
| `app/Controllers/Api/Appointments.php` | Accept `locationId` filter, apply WHERE clause |
| `app/Controllers/Appointments.php` | `store()` passes `location_id` to service; `update()` snapshots location via `getLocationSnapshot()` |
| `app/Services/AppointmentBookingService.php` | `createAppointment()` snapshots location via `LocationModel::getLocationSnapshot()` |
| `app/Services/PublicBookingService.php` | `formatPublicAppointment()` returns full customer data for reschedule pre-population |
| `app/Views/appointments/form.php` | Location dropdown, summary row, JS wiring; migrated to `form-input`/`form-label` |
| `app/Views/appointments/index.php` | Add Location dropdown to advanced filter panel (5-col grid) |
| `resources/js/modules/appointments/time-slots-ui.js` | `loadLocations()`, location_id to API, location change events |
| `resources/js/modules/scheduler/appointment-details-modal.js` | Add Location section HTML + population logic |
| `resources/js/modules/scheduler/scheduler-week-view.js` | Add location line in appointment summary cards |
| `resources/js/modules/scheduler/scheduler-core.js` | Add `locationId` to `setFilters()` and `getFilteredAppointments()` |
| `resources/js/modules/filters/advanced-filters.js` | Wire `#filter-location` to apply/clear/indicator logic |
| `resources/js/public-booking.js` | Location auto-resolve, location card, reschedule customer fields |

## Data Flow Summary

```
Provider Edit Form
  └─ schedule[day][locations][] → syncLocationDaysFromSchedule() → xs_location_days

Admin Create/Edit Appointment
  └─ Provider change → GET /api/locations?provider_id=X → populate location dropdown
  └─ Location change → refresh calendar API with location_id
  └─ Store/Update → LocationModel::getLocationSnapshot() → xs_appointments snapshot columns

Public Booking
  └─ location_id → BookingController::calendar() → AvailabilityService (location-aware)
  └─ resolveLocationForDate() → auto-resolve from provider+day-of-week
  └─ Reschedule → formatPublicAppointment() → full customer + location data

Admin Calendar
  └─ GET /api/appointments?locationId=X → filtered results
  └─ GET /api/availability/calendar?location_id=X → availability (now location-aware)
  └─ Filter panel → scheduler.setFilters({ locationId }) → client-side filtering
  └─ Details modal → shows Location Name / Address / Contact
  └─ Week view summary → shows location name under time/service
  └─ Day view → already shows location name (pre-existing)
```

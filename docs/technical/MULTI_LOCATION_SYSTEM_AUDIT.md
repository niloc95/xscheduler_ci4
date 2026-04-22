# Multi-Location System – Full-Stack Audit

**Date**: 2025-02-19  
**Scope**: Complete audit of the Provider Multi-Location Scheduling system  
**Spec Reference**: "Provider Multi-Location Scheduling – Simplified Model"

---

## Executive Summary

The multi-location system is **fully scaffolded on the backend** (DB schema, model, API controller, booking-service snapshot capture, notification template placeholders) but the **public booking frontend has zero location awareness**. The `AvailabilityService` also does not accept a `location_id` parameter, so working-day filtering by location is not enforced during time-slot calculation.

### Layer Readiness

| Layer | Status | Notes |
|---|---|---|
| Database schema | ✅ Complete | `xs_locations`, `xs_location_days`, appointment snapshot columns |
| LocationModel | ✅ Complete | CRUD + days, snapshot, `getLocationsForDay()`, `getAvailableDates()` |
| Locations API | ✅ Complete | Full REST + `set-primary`, `for-date`, `available-dates` |
| AppointmentModel | ✅ Complete | `allowedFields` includes all 4 location snapshot columns |
| PublicBookingService | ⚠️ Partial | Sends locations in `listProviders()`, captures snapshot in `createBooking()`, but no location-based availability filtering |
| AvailabilityService | ❌ Location-unaware | `getProviderHoursForDate()` ignores location entirely |
| Appointments API | ✅ Fixed (this audit) | Now exposes `location_id`, `location_name`, `location_address`, `location_contact` in both list and show responses |
| Scheduler Day View | ✅ Fixed (this audit) | Now reads `appointment.locationName` (was reading non-existent `appointment.location`) |
| NotificationTemplateService | ✅ Complete | `{location_name}`, `{location_address}`, `{location_contact}` placeholders |
| Admin CRUD (provider-locations.php) | ✅ Complete | Refactored in v88 audit |
| Public Booking JS | ✅ Fixed (this audit) | Location auto-resolved from provider+date, displayed, sent in payload |
| Public Booking View | ✅ Fixed (this audit) | Location card renders between date picker and time slots |

---

## Findings

### F-01 — CRITICAL: Public Booking Frontend Has Zero Location Awareness (FIXED)

**Files**: `resources/js/public-booking.js`, `app/Services/PublicBookingService.php`  
**Spec Rule**: "The public booking flow must clearly determine and display the location where the appointment will take place."

**Fix applied**:
1. Added `resolveLocationForDate(providerId, dateStr)` — client-side resolution using `ctx.providers[].locations[].days[]` (no extra API call needed)
2. `resolvedLocation` added to booking/manage state drafts, updated on provider change and date change
3. Location info card (`renderLocationCard()`) renders between date picker and time slots showing name, address, contact
4. `buildPayload()` includes `location_id` when a location is resolved
5. `syncCalendarSelection()` auto-resolves location when calendar selects a date
6. Success screen shows location name + address when present
7. Backend `formatPublicAppointment()` now returns `location_id`, `location_name`, `location_address`, `location_contact`
8. Uses inline SVG pin icon (no Material Symbols font dependency in standalone booking page)

---

### F-02 — HIGH: AvailabilityService Ignores Location

**File**: `app/Services/AvailabilityService.php`, method `getProviderHoursForDate()` (L321-363)

The availability calculator checks `provider_schedules` and `business_hours` tables for the requested day of week. It does not cross-reference `xs_location_days` to determine whether the provider actually works at any location on that day.

**Impact per simplified model**: If Provider A works Mon at Location X and Tue at Location Y, the availability service will show Provider A as available on **both** days regardless. The location-day constraint is not enforced.

**Required change**: `getProviderHoursForDate()` (or a new wrapper) should accept an optional `location_id` and, when provided, verify the provider has that location assigned to the given day of week via `xs_location_days`.

---

### F-03 — HIGH: Appointments API Had Wrong Location Field Name (FIXED)

**File**: `app/Controllers/Api/Appointments.php` L272  
**Was**: `'location' => $appointment['location'] ?? ''`  
**Problem**: There is no `location` column in `xs_appointments`. The actual columns are `location_id`, `location_name`, `location_address`, `location_contact`.  

**Fix applied**: Replaced with four proper snapshot fields:
```php
'location_id'      => $appointment['location_id'] ? (int) $appointment['location_id'] : null,
'location_name'    => $appointment['location_name'] ?? '',
'location_address' => $appointment['location_address'] ?? '',
'location_contact' => $appointment['location_contact'] ?? '',
```

---

### F-04 — HIGH: Appointments List Response Omitted Location Data (FIXED)

**File**: `app/Controllers/Api/Appointments.php`, `index()` method (~L191)  
**Problem**: The list response mapping did not include any location fields. The scheduler day view tried to read `appointment.location` which was never present.  

**Fix applied**: Added to list response:
```js
locationId:      appointment['location_id'],
locationName:    appointment['location_name'],
locationAddress: appointment['location_address'],
locationContact: appointment['location_contact'],
```

---

### F-05 — MEDIUM: Scheduler Day View Read Non-Existent Field (FIXED)

**File**: `resources/js/modules/scheduler/scheduler-day-view.js` L122  
**Was**: `const location = appointment.location || '';`  
**Problem**: The field `location` was never in the API response. After F-04 fix, the correct field name is `locationName`.

**Fix applied**: `const location = appointment.locationName || '';`

---

### F-06 — MEDIUM: Unused API Endpoints (`forDate`, `availableDates`)

**Files**: `app/Controllers/Api/Locations.php` (`forDate()`, `availableDates()` methods), `app/Config/Routes.php` L285-286

These two endpoints are fully implemented and routed but never called from any frontend code:
- `GET /api/locations/for-date?provider_id=X&date=YYYY-MM-DD`
- `GET /api/locations/available-dates?provider_id=X&location_id=Y`

Both are correctly designed for the public booking flow (F-01) and should be wired in when the frontend location step is implemented.

**Action**: Keep — they will be needed for the booking flow integration.

---

### F-07 — LOW: LocationModel Hardcodes Table Prefix in Raw Queries

**File**: `app/Models/LocationModel.php` — 5 raw SQL queries  
**Tables referenced**: `xs_location_days`, `xs_locations`

These queries use literal table names instead of `$this->db->prefixTable()`. This is consistent with **all other models** in the project (which also embed `xs_` directly), and the `DBPrefix` config is empty by default (prefix is baked into table names, not dynamically applied).

**Risk**: None in practice — this is the project convention. Noted for completeness.

---

### F-08 — LOW: Appointment Details Modal Does Not Display Location

**File**: `resources/js/modules/scheduler/appointment-details-modal.js`

When viewing appointment details (click on an appointment in the scheduler), the modal does not display location information even if the appointment has a `locationName`. This is a minor UI gap since the day view already shows the location badge, but the full details modal should also include it.

**Action**: Low priority — can be added when the public booking frontend integration (F-01) is completed, so there will actually be appointments with location data.

---

## Alignment with Simplified Model Spec

### Core Rules

| Rule | Status |
|---|---|
| A provider may work at more than one location | ✅ Schema + model support this |
| Each location is assigned specific days of the week | ✅ `xs_location_days` table with unique constraint on `(location_id, day_of_week)` |
| Working hours are the same across all locations | ✅ No per-location hours — hours come from `provider_schedules` / `business_hours` |
| A day of the week maps to exactly one location | ⚠️ Enforced in DB (unique constraint) but NOT validated in availability flow |

### Booking Requirements

| Requirement | Status |
|---|---|
| Public booking must determine and display location | ✅ Fixed — auto-resolves from provider+date, displays location card |
| Auto-resolve location from selected date | ✅ Fixed — `resolveLocationForDate()` matches day-of-week to provider locations |
| Location snapshot captured at booking time | ✅ Complete — `location_id` now sent in payload, backend captures snapshot |

### Non-Goals (Correctly NOT Implemented)

| Non-Goal | Status |
|---|---|
| Per-location operating hours | ✅ Not implemented (by design) |
| Complex conflict resolution | ✅ Not implemented (by design) |
| Advanced multi-location overlap rules | ✅ Not implemented (by design) |

---

## Implementation Roadmap for F-01 (Public Booking Frontend)

When the team is ready to wire in the frontend location step, these are the concrete changes needed:

### 1. `resources/js/public-booking.js`

- In the provider list response handler, store the `locations[]` array that `listProviders()` already sends
- After the user selects a provider and date, call `GET /api/locations/for-date?provider_id=X&date=YYYY-MM-DD` to get the location for that day
- If a location is returned, display it in the booking form (name + address)
- Include `location_id` in the `createBooking()` POST payload

### 2. `app/Views/public/booking.php`

- Add a location display section (between provider/date selection and time selection)
- Show location name, address, and optionally a map link

### 3. `app/Services/AvailabilityService.php`

- Optionally: cross-check `xs_location_days` when calculating available dates to grey out days the provider has no location for (prevents confusing UX)

### 4. Validation

- `PublicBookingService::createBooking()` could optionally validate that the `location_id` matches the selected provider and date (defence-in-depth)

---

## Summary of Fixes Applied in This Audit

| Finding | File(s) | Change |
|---|---|---|
| F-01 | `resources/js/public-booking.js`, `app/Services/PublicBookingService.php` | Full location integration: resolve, display, send in payload, show on success |
| F-03 | `app/Controllers/Api/Appointments.php` | Replaced phantom `'location'` with 4 proper snapshot fields |
| F-04 | `app/Controllers/Api/Appointments.php` | Added location fields to list response |
| F-05 | `resources/js/modules/scheduler/scheduler-day-view.js` | Changed `appointment.location` → `appointment.locationName` |
| Doc header | `app/Controllers/Api/Locations.php` | Removed phantom fields (city, postal_code, phone, start_time, end_time), fixed `@see` reference, added missing endpoint docs |
| Doc header | `app/Models/LocationModel.php` | Fixed stale `@see` reference |

---

*End of audit*

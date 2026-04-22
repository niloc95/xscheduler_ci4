# Refactor Proposal Audit – Provider Form & Location Handling

**Date**: 2025-07-14  
**Scope**: Complete audit of provider form views, location models/controllers, services, DB schema, and frontend components  
**Goal**: Catalogue all issues before controlled implementation of Location A/B model with schedule integration

---

## Executive Summary

This audit covers every file involved in provider management and multi-location scheduling. Findings are grouped into **five categories**: frontend consistency, backend logic, DB schema, naming/conventions, and duplicated code. Each finding has a severity, affected file(s), and recommended fix.

**Totals**: 22 findings — 5 HIGH, 9 MEDIUM, 8 LOW

---

## Category 1: Frontend Consistency

### F-01 — MEDIUM: Inline Tailwind classes instead of `form-input` / `form-label` utilities

**Files**: `app/Views/user-management/edit.php` (lines 49, 65, 82, 96), `app/Views/user-management/create.php` (lines 49, 65, 82, 96)

Both views use verbose inline classes on every `<input>` and `<select>`:

```html
class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg
       focus:ring-2 focus:ring-blue-500 focus:border-transparent
       bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
       transition-colors duration-300"
```

Meanwhile, `provider-schedule.php` and `provider-locations.php` use the project's `form-input` CSS utility (defined in `resources/css/scheduler.css` L701-729) which provides identical styling in one class.

**Same issue for labels**: 8 labels in `edit.php` use `class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300"` instead of `form-label` (defined at L682-697).

**Impact**: Inconsistency across views, harder maintenance, bloated HTML.  
**Fix**: Replace all inline input classes with `form-input` + conditional error class. Replace all inline label classes with `form-label`.

---

### F-02 — LOW: Mixed indentation (tabs vs spaces)

**Files**: `app/Views/user-management/edit.php`

The file mixes tabs (`\t`) in the `section`/`endSection` directives with spaces inside the HTML body. CI4 views elsewhere in the project consistently use spaces.

**Fix**: Normalize to spaces (project convention).

---

### F-03 — LOW: `create.php` has no `providerLocationsWrapper` div

**File**: `app/Views/user-management/create.php`

By design, locations can only be added _after_ the user is saved (correct "save first" pattern). However, the toggle-role JS in `edit.php` references `providerLocationsWrapper` — if the create form ever evolves, this divergence should be noted.

**Impact**: None currently. Not a bug, but a documentation gap.  
**Fix**: Add a comment in `create.php` explaining the intentional omission.

---

### F-04 — LOW: Staff card HTML rendered twice (PHP + JS)

**File**: `app/Views/user-management/components/provider-staff.php` (L67-95 PHP, L157-178 JS)

Same card markup maintained in two places — PHP server-render and JS `renderStaff()`. These can drift apart silently.

**Fix**: Convert to a single `_buildStaffCardHTML()` JS function used for both initial render and dynamic adds (same pattern as `provider-locations.php`'s `_buildCardHTML()`), or keep server-render for initial HTML and only use JS for appending.

---

### F-05 — LOW: Empty-state markup duplicated in `provider-staff.php`

**File**: `app/Views/user-management/components/provider-staff.php` (PHP L97, JS L143)

Same pattern as F-04.

---

### F-06 — LOW: `var` usage in JS

**File**: `app/Views/user-management/components/provider-staff.php` (L194)

Uses `var selectedOption` while the rest of the file uses `const`/`let`.

**Fix**: Change to `const`.

---

## Category 2: Backend Logic Gaps

### B-01 — HIGH: AvailabilityService is completely location-blind

**File**: `app/Services/AvailabilityService.php` (740 lines)

No method accepts a `location_id` parameter. Schedule lookup (`getProviderHoursForDate()`, L263) queries `xs_provider_schedules` by `(provider_id, day_of_week)` only. An appointment at Location A blocks availability at Location B for the same timeslot.

**Key methods affected**:
- `getAvailableSlots()` — no location parameter
- `isSlotAvailable()` — no location parameter
- `getCalendarAvailability()` — no location parameter
- `getProviderHoursForDate()` — ignores location days
- `getConflictingAppointments()` — filters only on `provider_id`
- cache key (L652) — no location dimension

**Impact**: Multi-location scheduling produces incorrect availability — a provider working at two locations on different days will have slots merged.

**Fix**: Add optional `?int $locationId = null` parameter to all public methods. When provided:
1. `getProviderHoursForDate()` checks `xs_location_days` to verify the location operates on that day
2. `getConflictingAppointments()` filters by `location_id`
3. Cache key includes location dimension

---

### B-02 — HIGH: `reschedule()` drops location snapshot

**File**: `app/Services/PublicBookingService.php` (L256-290)

When a booking is rescheduled, the update payload omits `location_id`, `location_name`, `location_address`, `location_contact`. If the new date maps to a different location, the appointment retains stale location data.

**Fix**: Re-resolve location when rescheduling and update snapshot fields in the update payload.

---

### B-03 — HIGH: `buildViewContext()` initial availability ignores location

**File**: `app/Services/PublicBookingService.php` (L120)

The initial calendar/availability computed at page load doesn't consider locations. Combined with B-01, the first-load calendar may show incorrect available dates.

**Fix**: Chain with B-01 fix — once AvailabilityService accepts `locationId`, pass it from `buildViewContext()`.

---

### B-04 — MEDIUM: No max-location limit enforced

**Files**: `app/Controllers/Api/Locations.php` `create()`, `app/Models/LocationModel.php`

The API allows unlimited locations per provider. The refactor proposal specifies max 2 (Location A + Location B).

**Fix**: Add validation in `Locations::create()` — before inserting, count existing locations for the provider and reject if >= 2. Add constant `LocationModel::MAX_LOCATIONS_PER_PROVIDER = 2`.

---

### B-05 — MEDIUM: Doc comment claims location support that doesn't exist

**File**: `app/Services/AvailabilityService.php` (L22)

Line 22: _"Location-based availability (if multi-location)"_ — purely aspirational.

**Fix**: Will be accurate after B-01 is implemented. Update doc to reflect actual state.

---

### B-06 — MEDIUM: `assertSlotAvailable()` is location-blind

**File**: `app/Services/PublicBookingService.php` (L316)

Delegates to `AvailabilityService::isSlotAvailable()` which has no location parameter. A booking could be confirmed for a slot that doesn't exist at the chosen location.

**Fix**: Chain with B-01 fix — pass `locationId` through.

---

## Category 3: DB Schema & Normalization

### D-01 — HIGH: Day-of-week representation mismatch

**Tables**: `xs_location_days` vs `xs_provider_schedules`

| Table | Column | Type | Values |
|---|---|---|---|
| `xs_location_days` | `day_of_week` | `tinyint unsigned` | 0 (Sun) – 6 (Sat) |
| `xs_provider_schedules` | `day_of_week` | `enum` | `'monday'`, `'tuesday'`, …, `'sunday'` |

This forces conversion logic whenever the two tables are joined or compared. The location days use JS-convention integers (Sunday=0), while schedules use PHP-friendly English names.

**Impact**: Any future "schedule per location" feature must bridge this gap constantly. Bug-prone.

**Fix (refactor proposal)**:
- **Option A**: Migrate `xs_provider_schedules.day_of_week` to `tinyint unsigned` (0-6) matching `xs_location_days`. Requires updating all PHP code that references day names.
- **Option B**: Add a mapping constant to `LocationModel` and `AvailabilityService` — `['sunday' => 0, 'monday' => 1, ...]` — and use consistently.
- **Recommended**: Option B (non-breaking), with Option A deferred to a future schema migration.

---

### D-02 — HIGH: Schedule is global to provider, not per-location

**Table**: `xs_provider_schedules` has `provider_id` + `day_of_week` but no `location_id`

The refactor proposal requires schedule to be configurable per-location (e.g., "Location A: Mon-Wed 9-5, Location B: Thu-Fri 10-6").

**Fix**: Add `location_id` nullable FK column to `xs_provider_schedules`. When `location_id` is NULL, the schedule applies globally (backward compatible). When set, it applies only to that location.

---

### D-03 — MEDIUM: `xs_location_days` has no unique constraint

**Table**: `xs_location_days`

Nothing prevents duplicate rows for the same `(location_id, day_of_week)` pair.

**Fix**: Add `UNIQUE KEY (location_id, day_of_week)`.

---

## Category 4: Naming & Convention Inconsistencies

### N-01 — MEDIUM: Model property naming divergence across services

| Property | AvailabilityService | PublicBookingService |
|---|---|---|
| AppointmentModel | `$this->appointmentModel` | `$this->appointments` |
| ServiceModel | `$this->serviceModel` | `$this->services` |
| SettingModel | `$this->settingModel` | `$this->settings` |

Two conventions in services that collaborate directly. `AvailabilityService` uses `{name}Model` suffix, `PublicBookingService` uses plural nouns.

**Fix**: Standardize on plural nouns (shorter, cleaner) in new code. Existing code can be left as-is to avoid churn — flag for future cleanup.

---

### N-02 — MEDIUM: DateTime type inconsistency in AvailabilityService

**File**: `app/Services/AvailabilityService.php`

- `generateCandidateSlots()` returns mutable `DateTime` objects
- `getCalendarAvailability()` works with `DateTimeImmutable`
- `dateTimesOverlap()` signature uses `DateTime` — won't type-match `DateTimeImmutable` without coercion
- L682: `getCalendarAvailability()` has `instanceof` workaround for both types

**Fix**: Standardize on `DateTimeInterface` for method signatures, `DateTimeImmutable` for new code.

---

### N-03 — LOW: `formatSlotRange()` hardcodes 12-hour format

**File**: `app/Services/PublicBookingService.php` (L665)

Uses hardcoded `'g:i A'` instead of the localization service's `formatTimeForDisplay()`. Also hardcodes the English word `'at'`.

**Fix**: Use `$this->localization->formatTimeForDisplay()` and make the connector string translatable.

---

### N-04 — LOW: `html()` tagged-template helper is misleading

**File**: `app/Views/user-management/components/provider-staff.php` (L152)

The `html` function is just string concatenation — the name implies escaping/sanitization that doesn't happen.

**Fix**: Rename to `buildHTML()` or just use template literals directly.

---

## Category 5: Duplicated Logic

### DUP-01 — MEDIUM: Overlap-detection SQL duplicated

**File**: `app/Services/AvailabilityService.php` (L556-590 and L596-616)

`getConflictingAppointments()` and `getBlockedTimesForPeriod()` contain near-identical three-clause overlap SQL.

**Fix**: Extract into a shared `buildOverlapConditions($builder, $start, $end)` helper.

---

### DUP-02 — MEDIUM: Two overlap-check methods with different boundary semantics

**File**: `app/Services/AvailabilityService.php`

- `timesOverlap()` — compares `H:i:s` strings with **strict** `<`/`>` boundaries
- `dateTimesOverlap()` — compares DateTime objects with **inclusive** `<=`/`>=` boundaries

The different boundary semantics mean break overlap uses strict comparison while appointment overlap uses inclusive — potential subtle bug source.

**Fix**: Audit both usages, pick one semantics, document the boundary rule.

---

### DUP-03 — LOW: Humanized date formatting in two languages

**File**: `app/Views/user-management/components/provider-staff.php`

PHP uses `Time::parse()->humanize()`, JS reimplements in `formatAssignedAt()` (L134-149). These may produce different outputs for edge cases.

**Fix**: Send pre-formatted string from PHP instead of raw timestamp, or accept the minor deviation.

---

### DUP-04 — LOW: Slot formatting duplicated across services

`PublicBookingService::getAvailableSlots()` (L174-183) and `AvailabilityService::getCalendarAvailability()` (L682-699) both map raw slots into `{start, end, label, timezone}` dictionaries with nearly identical logic.

**Fix**: Extract into a shared `formatSlot()` utility.

---

## Implementation Priority Matrix

| Priority | ID | Description | Risk if Deferred |
|---|---|---|---|
| 🔴 P0 | D-02 | Add `location_id` to `xs_provider_schedules` | Blocks per-location scheduling |
| 🔴 P0 | B-01 | Make AvailabilityService location-aware | Slots are wrong for multi-location providers |
| 🔴 P0 | D-01 | Day-of-week mapping constant | Needed for D-02 and B-01 |
| 🟠 P1 | B-02 | Fix `reschedule()` location snapshot | Stale data on rescheduled bookings |
| 🟠 P1 | B-04 | Enforce max 2 locations | Data integrity for A/B model |
| 🟠 P1 | F-01 | Replace inline CSS with `form-input`/`form-label` | Maintenance burden |
| 🟠 P1 | D-03 | Unique constraint on `xs_location_days` | Prevents duplicate day entries |
| 🟡 P2 | B-03 | Pass location to `buildViewContext()` | Wrong first-load calendar |
| 🟡 P2 | B-06 | Pass location to `assertSlotAvailable()` | False availability confirmation |
| 🟡 P2 | DUP-01 | Extract overlap SQL helper | Code duplication |
| 🟡 P2 | DUP-02 | Audit overlap boundary semantics | Potential subtle bugs |
| 🟡 P2 | N-02 | Standardize DateTime types | Developer confusion |
| ⚪ P3 | F-02 – F-06, N-01, N-03, N-04, DUP-03, DUP-04 | Low-severity cleanups | Minor debt |

---

## Next Steps

1. ~~**Fix P0 items** — DB migration + AvailabilityService + day mapping~~ ✅ DONE
2. ~~**Fix P1 items** — reschedule snapshot, max locations, form-input migration, unique constraint~~ ✅ DONE
3. ~~**Fix P2 items** — context pass-through, overlap helpers, DateTime standardization~~ ✅ B-03 & B-06 DONE (pass-through)
4. **P3 items** — address opportunistically during related changes
5. ~~**Build & deploy** — `npm run build`~~ ✅ DONE

---

## Fixes Applied (v91)

| Finding | Status | Change |
|---|---|---|
| F-01 | ✅ Fixed | Replaced all inline Tailwind classes with `form-input`/`form-label` in `edit.php` and `create.php` |
| D-01 | ✅ Fixed | Added `DAY_INT_TO_NAME` / `DAY_NAME_TO_INT` constants to `LocationModel` |
| D-02 | ✅ Fixed | Migration `2026-01-20-120200`: added nullable `location_id` FK to `xs_provider_schedules` |
| D-03 | ✅ Fixed | Same migration: added `UNIQUE(location_id, day_of_week)` on `xs_location_days` |
| B-01 | ✅ Fixed | `AvailabilityService`: all public methods accept `?int $locationId`, check location days, cache key includes location |
| B-02 | ✅ Fixed | `reschedule()` now re-resolves and updates location snapshot fields |
| B-03 | ✅ Fixed | `buildViewContext()` initial calendar calls pass through (null by default, location-ready) |
| B-04 | ✅ Fixed | Max 2 locations enforced: `LocationModel::MAX_LOCATIONS_PER_PROVIDER`, API guard in `create()`, UI limit + button toggle |
| B-05 | ✅ Fixed | Doc comment in AvailabilityService updated to reflect actual location support |
| B-06 | ✅ Fixed | `assertSlotAvailable()` now passes `locationId` to `isSlotAvailable()` |

**Files modified (12):**
- `app/Views/user-management/edit.php` — form-input/form-label migration
- `app/Views/user-management/create.php` — form-input/form-label migration
- `app/Views/user-management/components/provider-locations.php` — max 2 enforcement
- `app/Models/LocationModel.php` — constants + max limit
- `app/Controllers/Api/Locations.php` — max locations guard
- `app/Services/AvailabilityService.php` — location-aware availability
- `app/Services/PublicBookingService.php` — location pass-through + reschedule snapshot
- `app/Controllers/PublicSite/BookingController.php` — location_id query param
- `app/Database/Migrations/2026-01-20-120200_AddLocationIdToProviderSchedules.php` — new migration

---

## Pass 2 — Post-Refactor Audit (2026-02-19)

Comprehensive audit of all files touched during the Location/Schedule refactor after the scheduling controls were removed from the Location block and the Update User redirect was changed to stay on the edit page.

### Findings Fixed

| ID | Severity | File | Issue | Fix |
|----|----------|------|-------|-----|
| P2-01 | P1 | provider-locations.php | Empty `else {}` block in `addLocation()` success path — dead code from removed alert | Restored error alert in else branch |
| P2-02 | P2 | provider-locations.php | `addLocation()` POST body included `days: []` — field is irrelevant since day assignment moved to Schedule section | Removed `days` from POST payload |
| P2-03 | P3 | provider-locations.php | Double blank line between `</textarea>` and card `</div>` close | Removed extra blank line |
| P2-04 | P1 | PublicBookingService.php | `$newLocationId` declared twice in `reschedule()` (L282 casts to int, L307 re-declares as raw string) — second shadows first | Removed duplicate declaration; reused already-cast `$newLocationId` |
| P2-05 | P2 | PublicBookingService.php | `if ($locationSnapshot)` always truthy — `getLocationSnapshot()` returns array with null values, never null | Changed guard to `$locationSnapshot['location_id'] !== null` |
| P2-06 | P1 | edit.php + provider-schedule.php | Duplicate role-toggle: both files toggled `providerScheduleSection`, `.provider-color-field` on role change. The edit.php IIFE also toggled `providerLocationsWrapper` which provider-schedule.php did not | Merged `providerLocationsWrapper` toggle into `toggleScheduleSection()` in provider-schedule.php; removed redundant IIFE from edit.php |
| P2-07 | P3 | flash-messages.php | `window.scrollTo()` fired on every page with any flash type — too aggressive | Guarded with `if (document.querySelector('[data-flash-message]'))` |

### Verified Clean (no issues found)

| Area | Notes |
|------|-------|
| **LocationModel.php** | Clean: no duplicate logic, consistent naming (snake_case), proper use of `$this->db`, MAX_LOCATIONS_PER_PROVIDER constant, DAY_INT_TO_NAME / DAY_NAME_TO_INT constants correct |
| **Locations API Controller** | Clean: consistent error responses (`status: 'ok'`/`'error'`), max-locations guard, proper soft-delete, no hardcoded IDs |
| **AvailabilityService.php** | Clean: `?int $locationId` on all public methods, location-day check early-returns empty array, cache key includes location dimension, no fallback to `null` location |
| **public-booking.js** | Clean: `selectedLocationId` in draft state, `handleLocationChange()` re-fetches correctly, `resolveLocationForDate()` returns null on no match (no primary fallback), `location_id` passed to both `fetchCalendar()` and `fetchSlots()`, UI_CLASSES constants prevent duplication |
| **provider-schedule.php** | Clean: tick-box checkboxes use correct `name`/`value`/`data-*` attributes, `toggleDayInputs()` handles both time inputs and location checkboxes, `_syncScheduleTickBoxes()` correctly manages dynamic add/remove |
| **UserManagement.php** | Clean: `syncLocationDaysFromSchedule()` correctly inverts per-day→per-location, redirect now goes to edit page (not list), no duplicate logic |

### Naming Convention Audit

| Convention | Scope | Status |
|------------|-------|--------|
| snake_case | PHP variables, DB columns, form field names | ✅ Consistent |
| camelCase | JS variables, function names | ✅ Consistent |
| PascalCase | PHP classes, JS object names (`LocationManager`) | ✅ Consistent |
| UPPER_SNAKE | PHP constants (`MAX_LOCATIONS_PER_PROVIDER`, `DAY_NAME_TO_INT`) | ✅ Consistent |
| kebab-case | CSS classes (`schedule-location-checkboxes`, `js-day-active`) | ✅ Consistent |

### No Inline CSS

All files verified: zero inline `style=""` attributes. All styling uses Tailwind utility classes or project CSS utilities (`form-input`, `form-label`, `btn`, `card`, etc.).

### No Orphaned Code

- No unused JS functions (removed `toggleDay()`, `_updateDayChip()`)
- No unused PHP variables (removed `$dayNames`, `$dayAbbr`, `$locationDays`)
- No dead imports

---

*This document supersedes the earlier `MULTI_LOCATION_SYSTEM_AUDIT.md` for implementation planning. That document remains valid for historical reference of v89 fixes.*

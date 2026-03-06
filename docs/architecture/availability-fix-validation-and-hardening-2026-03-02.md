# Availability Fix Validation & Hardening (2026-03-02)

## Scope
This document validates and hardens the availability panel flow:

Provider filter + Service filter + Date
→ Availability API
→ AvailabilityService
→ Slot rendering panel

This subsystem is now validated under a MySQL-only CI profile.

## 1) UTC Standardization Status

### Current State (Confirmed)
- API slot panel requests now explicitly use `timezone=UTC` in scheduler slot panels:
  - `resources/js/modules/scheduler/scheduler-week-view.js`
  - `resources/js/modules/scheduler/scheduler-day-view.js`
  - `resources/js/modules/scheduler/scheduler-month-view.js`
- Appointments are stored in UTC (`xs_appointments.start_at`, `xs_appointments.end_at`) and `stored_timezone` is `UTC` for existing rows sampled.
- Availability conflict queries convert boundaries to UTC for DB reads.
- CI MySQL integration profile enforces session/global MySQL timezone `+00:00` before tests.

### Working Hours Handling
- `xs_provider_schedules` and `xs_business_hours` store wall-clock time (start/end time only, no date/timezone).
- `AvailabilityService` currently builds candidate slots in the requested timezone (now UTC from panel calls), then compares against busy periods transformed from UTC.
- Result: not strict UTC→UTC-only math internally yet, because working-hour windows are modeled as local wall-clock schedule definitions.

### JS Local Time Math
- Availability panels do not compute slots client-side.
- JS only sends query parameters and renders server response.
- No client-side conflict logic reintroduced.

### Gap
- Full UTC-only internal arithmetic is not complete because working-hours tables are local wall-clock constructs.

### Migration Plan to Full UTC Discipline
1. **Schema extension (non-breaking)**
   - Add timezone context per provider/business schedule source.
   - Add normalized UTC boundary materialization strategy for target date (computed at runtime or persisted cache).
2. **Service refactor**
   - Normalize all candidate slot windows to UTC before overlap checks.
   - Keep display formatting as a final conversion step only.
3. **Backfill + verification**
   - Backfill timezone metadata for providers/business.
   - Run parity checks comparing old/new slot outputs on representative dates (DST and non-DST).
4. **Cutover**
   - Feature-flag strict UTC path.
   - Compare API outputs in shadow mode before full enable.

### Risk Assessment
- **High:** DST boundaries if local schedule intent is misinterpreted during UTC conversion.
- **Medium:** Existing provider schedule semantics (`day_of_week`, local wall-clock) may drift if timezone metadata is missing.
- **Medium:** Historical blocked periods spanning midnight may shift if migrated incorrectly.

### Impacted Tables
- `xs_appointments`
- `xs_blocked_times`
- `xs_provider_schedules`
- `xs_business_hours`
- `xs_settings` (timezone keys)

## 2) Guard Conditions on Slot Fetch

Implemented explicit guard checks before API calls in panel refresh methods:
- Provider required
- Service required
- Date required

Files:
- `resources/js/modules/scheduler/scheduler-week-view.js`
- `resources/js/modules/scheduler/scheduler-day-view.js`
- `resources/js/modules/scheduler/scheduler-month-view.js`

Behavior:
- If any required field is missing, API call is not fired and user prompt is rendered.

## 3) Location Fallback Behavior

### Implemented fallback logic
When provider has active locations and `location_id` is omitted:
1. Use primary active location (`is_primary = 1`) if present.
2. Else use first active location.
3. If no active location exists, return validation failure.

Implemented in:
- `app/Controllers/Api/Availability.php` (`resolveProviderLocationContext`)
- `app/Services/AvailabilityService.php` (`resolveLocationContext`)

### Regression Test Added
- `tests/integration/AvailabilitySlotsLocationFallbackTest.php`

Test intent:
- Ensure `/api/availability/slots?provider_id=X&service_id=Y&date=Z` does not regress to location-required 422 for providers with active locations.

Assertions include:
- HTTP 200 response
- Non-empty `slots` array
- `location_id` fallback resolved when omitted in request
- UTC payload integrity (`startTime`/`endTime` with `+00:00` or `Z`)
- UTC overlap check (blocked period excludes expected slot)

## 3.1) MySQL CI Integration Profile

Added MySQL CI test profile:
- `phpunit.mysql.xml.dist`

Profile characteristics:
- Forces `database.tests.DBDriver=MySQLi`
- Uses MySQL `tests` connection settings
- Runs full PHPUnit suite under MySQL test group

CI pipeline changes (`.github/workflows/ci-cd.yml`):
- Waits for MySQL health
- Sets MySQL timezone to UTC (`SET GLOBAL time_zone = '+00:00'`)
- Runs fresh migrations (`php spark migrate:refresh -n App --all`)
- Seeds baseline availability data (`php spark db:seed AvailabilityIntegrationSeeder`)
- Runs full suite with MySQL profile
- Runs explicit availability regression test filter

Added baseline seeder:
- `app/Database/Seeds/AvailabilityIntegrationSeeder.php`

## 4) Dev-Only Debug Visibility

Added debug payload rendering in slot panels (dev/debug mode only):
- provider_id
- service_id
- resolved location_id
- date
- timezone

Gating:
- `scheduler.isDebugEnabled()` or `window.appConfig.debug`
- Not rendered in production unless debug is enabled.

Files:
- `resources/js/modules/scheduler/scheduler-week-view.js`
- `resources/js/modules/scheduler/scheduler-day-view.js`
- `resources/js/modules/scheduler/scheduler-month-view.js`

## 5) Regression Checklist

- [x] Slots render after provider/service change without Apply dependency (filter auto-apply implemented).
- [x] Direct API test returns slots without explicit location_id (location fallback active).
- [x] Feature flag confirmed not blocking availability (`calendar.rebuild_enabled = true`).
- [x] Calendar and right panel use same active filter context.
- [x] Slot links still route to appointment creation with selected date/time/provider/service.
- [x] Availability regression test configured for MySQL-only CI execution (no SQLite skip behavior).

## Validation Commands Executed

- Build:
  - `npm run build`
- API route health:
  - `php spark routes | grep -E "availability/(slots|calendar|check)|api/calendar"`
- Availability endpoint checks:
  - `curl /api/availability/slots?...`
  - `curl /api/availability/calendar?...`
- Settings check:
  - `SELECT setting_key, setting_value FROM xs_settings WHERE setting_key IN ('calendar.rebuild_enabled','localization.timezone');`

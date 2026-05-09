# Code Audit Report — Session v96

**Date:** 2026-02-23  
**Scope:** 96 recently changed files (HEAD~10) + 3 unstaged files  
**Categories:** Duplication, Redundancy, Inconsistency, Inline CSS, Orphaned Code, Bugs, Security

---

## Summary

| Severity | Found | Fixed | Deferred |
|----------|-------|-------|----------|
| **Critical / High** | 10 | 8 | 2 |
| **Medium** | 24 | 6 | 18 |
| **Low** | 28 | 4 | 24 |
| **Total** | 62 | 18 | 44 |

---

## Fixes Applied

### Critical / High Fixes

| # | File | Issue | Fix |
|---|------|-------|-----|
| 1 | `app/Controllers/Appointments.php` L731 | **BUG:** `$startTimeUtc` undefined variable — reminders never reset after rescheduling | Changed to `$startTimeStored` (the correct variable) |
| 2 | `resources/js/modules/scheduler/scheduler-core.js` L815 | **BUG:** Dead `export { CustomScheduler }` — ReferenceError on import | Removed orphaned export (class already exported via `export class SchedulerCore`) |
| 3 | `resources/js/spa.js` L389 | **SECURITY/XSS:** Flash message injected via `innerHTML` without escaping | Added HTML entity escaping before injection |
| 4 | `app/Controllers/Dashboard.php` L121 | **SECURITY:** User session data (`json_encode(session('user'))`) exposed in 403 error page | Removed user role and data from HTML response; details remain in server logs only |
| 5 | `app/Views/settings/tabs/notifications.php` ~L378 | **BUG:** SMS section nested inside WhatsApp card `<div>` — SMS rendered inside WhatsApp border | Closed WhatsApp card, added dedicated SMS card wrapper |
| 6 | `.env.example` L28 | **Duplicate:** `app.forceGlobalSecureRequests` defined twice (L28 active, L97 commented) | Consolidated to single commented-out entry at L28 |
| 7 | `app/Views/user-management/create.php` L23 | **Duplication:** Flash messages rendered twice (view + layout) | Removed redundant `include('components/ui/flash-messages')` |
| 8 | `app/Views/user-management/edit.php` L23 | **Duplication:** Flash messages rendered twice (view + layout) | Removed redundant `include('components/ui/flash-messages')` |

### Medium Fixes

| # | File | Issue | Fix |
|---|------|-------|-----|
| 9 | `app/Services/DashboardService.php` L112 | **Inconsistency:** Timezone from `env('app.timezone')` instead of `LocalizationSettingsService` | Changed to `$this->localizationService->getTimezone()` |
| 10 | `app/Views/settings/tabs/integrations.php` L44 | **Inconsistency:** LDAP checkbox uses `class="checkbox"` instead of standard `form-checkbox h-4 w-4 text-blue-600` | Standardized checkbox class |
| 11 | `app/Views/settings/tabs/notifications.php` L491-497 | **Performance:** `NotificationPhase1` service instantiated per preview call (9+ times) | Service instantiated once before closure |
| 12 | `app/Views/services/index.php` L444 | **Inline CSS:** JS-injected category color dots use `style="background-color:..."` | Changed to `data-color` attribute matching server-rendered pattern |
| 13 | `resources/js/app.js` L39-43 | **Redundancy:** 5 unused imports (`MonthView`, `WeekView`, `DayView`, `DragDropManager`, `SettingsManager`) | Removed — `SchedulerCore` imports them internally |
| 14 | `app/Filters/AuthFilter.php` L63-67 | **Redundancy:** `$permissionModel` created but never used | Removed unused property, constructor, and import |

### Low Fixes

| # | File | Issue | Fix |
|---|------|-------|-----|
| 15 | `app/Views/settings/tabs/notifications.php` L27 | **Naming:** `$notifLang` abbreviation inconsistent with other variables | Renamed to `$defaultLanguage` |
| 16 | `app/Views/settings/tabs/notifications.php` L14 | **Naming:** `form_source=notification_rules_phase1` breaks `{tab}_settings` convention | Changed to `notifications_settings` |
| 17 | `resources/js/app.js` L85-86 | **Orphan:** Stale deprecated comment about calendar initialization | Removed outdated comment |
| 18 | `resources/js/app.js` L118-119 | **Inconsistency:** `var` used instead of `const`/`let` | Changed to `const` |

### Other Cleanups

- Deleted orphaned `app/Views/settings.php.bak` (199KB backup artifact)
- Added explanatory comment about custom flash rendering in `settings/index.php`

---

## Deferred Issues (Require Larger Refactors)

### Architecture / Duplication (High Priority for Future Sprint)

| # | File(s) | Issue | Recommendation |
|---|---------|-------|----------------|
| D1 | `app/Helpers/app_helper.php` / `settings_helper.php` | 5 functions (170 lines) fully duplicated across both files | Delete `settings_helper.php`, keep `app_helper.php` as single source. Requires updating all `helper('settings')` calls. |
| D2 | `app/Services/AvailabilityService.php` / `DashboardService.php` | Provider working hours lookup duplicated (~40 lines each) | `DashboardService` should delegate to `AvailabilityService::getProviderHoursForDate()` |
| D3 | `app/Views/settings/tabs/booking.php` L24-115 | 6x copy-pasted checkbox toggle blocks for standard booking fields | Refactor into `foreach` loop (same pattern as custom fields section) |
| D4 | `app/Views/settings/tabs/notifications.php` | 3x identical encryption key mismatch warning banners (email, WhatsApp, SMS) | Extract into shared partial `components/decrypt-error-banner.php` |
| D5 | `app/Views/user-management/create.php` / `edit.php` | ~160 lines of form fields duplicated | Extract into shared partial `user-management/components/_user-form-fields.php` |
| D6 | `app/Controllers/Appointments.php` L218-236 / L505-523 | Provider/service formatting duplicated in `create()` and `edit()` | Extract to private `formatDropdownData()` method |
| D7 | `app/Controllers/Api/Appointments.php` | Notification enqueue pattern copy-pasted in 4 methods | Extract to private `queueNotification()` helper |
| D8 | `resources/js/` (4 files) | `escapeHtml` function defined 4 separate times | Consolidate into `utils/escape-html.js` and import everywhere |
| D9 | `resources/js/modules/scheduler/` | `isDateBlocked`, `getBlockedPeriodInfo`, `renderMiniCalendar` duplicated between DayView and WeekView | Extract into shared scheduler utility module |

### Inconsistency (Medium Priority)

| # | File(s) | Issue | Recommendation |
|---|---------|-------|----------------|
| I1 | `app/Controllers/Api/Locations.php` | Returns `{"status":"ok","data":...}` instead of `{"data":...,"meta":...}` | Refactor all 8 endpoints to use `BaseApiController` helper methods |
| I2 | `app/Controllers/Api/Availability.php` | Returns `{"ok":true,"data":...}` non-standard envelope | Align with standard `{"data":...,"meta":...}` format |
| I3 | `app/Controllers/Api/Appointments.php` L168-197 vs L264-290 | `index()` uses camelCase fields, `show()` uses snake_case | Standardize to snake_case for all API responses |
| I4 | Multiple services | Mixed `protected`/`private` property visibility with no subclassing | Standardize to `private` for non-subclassed services |
| I5 | Multiple models/services | Missing `xs_` table prefix in some raw queries | Add prefix or rely on CI4 `DBPrefix` consistently |
| I6 | `app/Models/AppointmentModel.php` / `ServiceModel.php` | MySQL-only SQL functions (`DATE_FORMAT`, `HOUR`, `DATE_SUB`, `NOW()`) break SQLite | Add SQLite-compatible alternatives |
| I7 | `app/Views/settings/tabs/business.php` L91 | `booking.statuses` key used in business tab (cross-concern) | Move to booking tab or create `business.statuses` key |
| I8 | `app/Services/AppointmentBookingService.php` L355 | Keys named `startUtc`/`endUtc` but contain local times | Rename to `startLocal`/`endLocal` |

### Orphaned / Dead Code (Low Priority)

| # | File | Issue |
|---|------|-------|
| O1 | `app/Models/AppointmentModel.php` | 6 `@deprecated` methods still present |
| O2 | `app/Controllers/Notifications.php` L422-447 | `markAsRead()` and `markAllAsRead()` are no-ops |
| O3 | `app/Models/ServiceModel.php` L112 | `getPopularServices()` returns newest, not popular |
| O4 | `app/Services/DashboardService.php` L278-300 | ~25 lines of commented-out alert code |
| O5 | `app/Services/DashboardService.php` L490-505 | `getBookingStatus()` returns hardcoded placeholder data |
| O6 | `resources/js/modules/filters/status-filters.js` L547 | `initAllFilters()` exported but never imported |
| O7 | `app/Services/DashboardService.php` L840 | `'booked'`/`'rescheduled'` status cases don't match valid statuses |

### Security / Error Handling (Medium Priority)

| # | File | Issue |
|---|------|-------|
| S1 | `app/Services/BusinessHoursService.php` L170-195 | No try/catch on DB queries |
| S2 | `app/Models/LocationModel.php` L220 | `setLocationDays()` always returns `true` regardless of DB success |
| S3 | Multiple JS files | ~28 `console.*` statements in production code — recommend shared logger utility |
| S4 | `app/Controllers/Api/Appointments.php` L150 | Excessive `log_message('info')` calls in list endpoint (1000+ log lines per request) |

---

## Files Modified in This Audit Session

```
.env.example
app/Controllers/Appointments.php
app/Controllers/Dashboard.php
app/Filters/AuthFilter.php
app/Services/DashboardService.php
app/Views/services/index.php
app/Views/settings/index.php
app/Views/settings/tabs/integrations.php
app/Views/settings/tabs/notifications.php
app/Views/user-management/create.php
app/Views/user-management/edit.php
resources/js/app.js
resources/js/modules/scheduler/scheduler-core.js
resources/js/spa.js
```

## Files Deleted

```
app/Views/settings.php.bak (199KB orphaned backup)
```

## Verification

- **Vite build:** Passes (`npm run build` — 1.81s, no errors)
- **PHP syntax:** All edited PHP files pass `php -l` lint

---

## 2026-02-24 Addendum — Scheduler Inline CSS Cleanup

Additional frontend audit/remediation completed after the initial report:

- Removed inline `style="..."` attributes from scheduler render templates in:
	- `resources/js/modules/scheduler/scheduler-week-view.js`
	- `resources/js/modules/scheduler/scheduler-day-view.js`
	- `resources/js/modules/scheduler/scheduler-month-view.js`
	- `resources/js/modules/scheduler/slot-engine.js`
	- `resources/js/modules/scheduler/scheduler-ui.js`
	- `resources/js/modules/scheduler/appointment-colors.js`
- Replaced style attributes with `data-*` color hooks (`data-bg-color`, `data-text-color`, `data-border-color`, `data-border-left-color`, `data-provider-color`, `data-pill-color`).
- Extended `resources/js/utils/dynamic-colors.js` to apply all supported color hooks and observe dynamically inserted SPA/scheduler nodes via `MutationObserver`.
- Replaced day-view section hiding from direct style mutation to utility class toggling (`hidden`).
- Validation: `npm run build` passes after this cleanup.

## 2026-02-24 Addendum — Backend Consistency + Location-Aware Hardening

Additional backend audit/remediation completed:

- Reduced duplicate service write logic in `app/Controllers/Services.php` by centralizing:
	- service payload normalization (`buildServicePayload`)
	- provider-id normalization (`normalizeProviderIds`)
- Normalized service/provider pivot table usage in controller actions to use DB-prefixed table names consistently.
- Fixed DB-prefix inconsistencies in models that can break production installs with `xs_` prefix:
	- `app/Models/ServiceModel.php` (`findWithRelations`, stats bookings query, provider pivot writes)
	- `app/Models/CategoryModel.php` (`withServiceCounts` join)
- Hardened location-aware booking rules in `app/Services/PublicBookingService.php`:
	- rejects invalid location/provider combinations
	- requires explicit `location_id` when provider has multiple active locations (no silent default fallback)
	- still allows auto-selection when exactly one active location exists
- Hardened location checks in `app/Services/AvailabilityService.php`:
	- validates location belongs to provider and is active before day/slot checks

Validation:

- `php -l` passes for all modified backend files
- Frontend build remains passing (`npm run build`)

## 2026-02-24 Addendum — Settings Save Error Handling

Observed production/runtime failure:

- API response: `settings_update_failed`
- Log message: `Failed to parse JSON string. Error: Syntax error`

Root cause:

- `app/Controllers/Api/V1/Settings.php::update()` called `$this->request->getJSON(true)` inside the main try/catch.
- On malformed/empty JSON bodies, `getJSON(true)` throws, causing a 500 response before fallback to form data could run.

Fix applied:

- Wrapped JSON parse in an inner try/catch and added graceful fallback to `getPost()`.
- Returns validation error (`Invalid JSON payload`) instead of 500 when JSON is malformed and no form payload exists.
- Filters transport keys (`csrf_test_name`, `form_source`) from persisted settings payload.
- Keeps successful JSON and form-data updates unchanged.

## 2026-02-25 Addendum — Backend Validation Centralization + Strict Location Context

Additional backend hardening completed for appointment/availability flows:

- Enforced strict provider-location context inside `app/Services/AvailabilityService.php`:
	- Added centralized `resolveLocationContext()` guard.
	- Requires explicit `location_id` when provider has multiple active locations.
	- Auto-resolves the single active location when exactly one exists.
	- Rejects invalid/inactive location-provider mappings consistently.
- Updated `getAvailableSlots()` and `isSlotAvailable()` to use the same location-context resolution path, removing divergent location validation behavior.
- Hardened `app/Services/AppointmentBookingService.php`:
	- Added centralized `resolveBookingLocationContext()` helper.
	- `createAppointment()` now validates/normalizes location before availability checks and persists resolved `location_id`.
	- `updateAppointment()` now validates provider/location context even when time fields are unchanged, preventing silent multi-location fallback during updates.
	- Availability checks in create/update now pass resolved `location_id` explicitly.
- Hardened `app/Controllers/Api/Availability.php`:
	- Added centralized `resolveProviderLocationContext()`.
	- Applied consistent location validation/enforcement to `slots`, `check`, `summary`, `calendar`, and `nextAvailable` endpoints.
	- Endpoints return `422` with a clear message when `location_id` is required or invalid.
- Reduced duplication and improved consistency in `app/Controllers/Api/Appointments.php::checkAvailability()`:
	- Added `validateAvailabilityPayload()` for centralized request validation.
	- Added `resolveProviderLocationContext()` for consistent location enforcement.
	- Availability checks now pass resolved `location_id`.
	- Standardized service table access via prefixed table resolution (`$db->prefixTable('services')`).

Validation:

- `php -l` passes for:
	- `app/Services/AvailabilityService.php`
	- `app/Services/AppointmentBookingService.php`
	- `app/Controllers/Api/Availability.php`
	- `app/Controllers/Api/Appointments.php`

## 2026-02-25 Addendum — Controller Validation Rule Centralization

Additional backend deduplication completed for controller-level validation rules:

- `app/Controllers/Appointments.php`
	- Replaced duplicated store/update rule arrays with centralized helpers:
		- `getStoreValidationRules(bool $hasExistingCustomer)`
		- `getUpdateValidationRules()`
	- Replaced repeated date-in-past checks with shared helper:
		- `validateNotInPast(...)`
- `app/Controllers/Api/Appointments.php`
	- Replaced inline rule arrays in `create()` and `counts()` with centralized helpers:
		- `getCreateValidationRules()`
		- `getCountsValidationRules()`
- `app/Controllers/UserManagement.php`
	- Replaced duplicated validation arrays in `store()` and `update()` with:
		- `getStoreValidationRules()`
		- `getUpdateValidationRules(int $userId, bool $includePasswordRules, bool $canChangeRole)`

Impact:

- Reduces rule drift risk between create/update endpoints.
- Keeps behavior and response envelopes unchanged while improving maintainability.

Validation:

- `php -l` passes for:
	- `app/Controllers/Appointments.php`
	- `app/Controllers/Api/Appointments.php`
	- `app/Controllers/UserManagement.php`
- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Controller Duplication Cleanup (D6/D7)

Additional duplication cleanup completed from deferred architecture items:

- `app/Controllers/Appointments.php`
	- Centralized duplicated provider/service dropdown preparation used by both `create()` and `edit()`.
	- Added shared helper:
		- `formatDropdownData()`
	- `create()` and `edit()` now consume the same providers/services formatting path.

- `app/Controllers/Api/Appointments.php`
	- Centralized repeated notification enqueue blocks across API flows.
	- Added shared helper:
		- `queueAppointmentNotifications(int $appointmentId, array $channels, string $eventType, string $context = 'appointment')`
	- Replaced copy-pasted queue logic in:
		- `updateStatus()`
		- `create()`
		- `update()`
		- `delete()`
		- `notify()` (WhatsApp path)

Impact:

- Reduces notification enqueue drift risk between endpoints.
- Keeps response payloads and notification behavior unchanged while improving maintainability.

Validation:

- `php -l` passes for:
	- `app/Controllers/Appointments.php`
	- `app/Controllers/Api/Appointments.php`
- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Appointment View UX Fixes (Day Hidden + Fresh Time Rendering)

Additional appointment scheduler UX fixes completed:

- `app/Views/appointments/index.php`
	- Removed Day view toggle button from the appointments header controls.
	- Scheduler toolbar exposes `Today`, `Week`, and `Month` controls with previous/next navigation.

- `resources/js/modules/scheduler/scheduler-ui.js`
	- Restricted view-toggle bindings to Week/Month controls (Day hidden in appointments).
	- Updated active-state handling to target Week/Month buttons.

- `resources/js/modules/scheduler/scheduler-core.js`
	- Enforced allowed view switching to Week/Month in `changeView()`.
	- Hardened `loadAppointments()` against stale GET caching by adding cache-busting query param and `fetch(..., { cache: 'no-store' })`.
	- Ensures updated appointment times appear immediately after reschedule/status flows without requiring manual browser refresh.

Validation:

- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Week Grid Audit + Border/Layering Fix

Targeted audit and remediation completed for the week schedule grid after visual regression report.

Audit findings (`resources/js/modules/scheduler/scheduler-week-view.js`):

- Inline CSS usage detected in rendered markup (`style="..."`) for:
	- dynamic event `top/height`
	- dynamic grid column templates
	- negative-margin overlay stacking
- Overlapping border/layer model caused missing top/left borders and inconsistent line rendering.
- Orphan expansion handlers remained (`.week-day-more`, `.week-day-hidden`) after timeline rewrite.

Fix applied:

- Replaced overlay/negative-margin timeline composition with a single-layer, class-driven grid.
- Removed all inline style attributes from week-grid rendering.
- Restored consistent border model:
	- one root `border-l border-t`
	- cell-level `border-r border-b`
	- no stacked duplicate grid overlays
- Refactored event rendering to row-scoped cards (hour buckets) using existing click handlers and color hooks.
- Removed orphan expand handlers and dead selector logic from `attachWeeklySectionListeners()`.

Impact:

- Week grid lines render cleanly without overlap artifacts.
- Top and left borders are consistently visible.
- Implementation now complies with no-inline-css requirement in this section.

Validation:

- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Phase 1 Refactor Kickoff (Location Strictness + Calendar Surface Cleanup)

Initial implementation phase completed against the new refactor ruleset:

- Strict location enforcement (no silent fallback/auto-select) applied in:
	- `app/Services/AvailabilityService.php`
		- `resolveLocationContext()` now requires explicit `location_id` whenever provider has active locations.
	- `app/Services/AppointmentBookingService.php`
		- `resolveBookingLocationContext()` now requires explicit `location_id` whenever provider has active locations.
	- `app/Controllers/Api/Availability.php`
		- `resolveProviderLocationContext()` now rejects omitted `location_id` whenever provider has active locations.
	- `app/Controllers/Api/Appointments.php`
		- `resolveProviderLocationContext()` now rejects omitted `location_id` whenever provider has active locations.
	- `app/Services/PublicBookingService.php`
		- `resolveBookingLocation()` no longer auto-selects provider location when omitted.

- Appointment month-view surface cleanup:
	- `app/Views/appointments/index.php`
		- Removed `bg-white dark:bg-gray-800` from the calendar wrapper and daily appointments wrapper to match the intended month-view surface style.

Validation:

- `php -l` passes for modified backend files:
	- `app/Services/AvailabilityService.php`
	- `app/Services/AppointmentBookingService.php`
	- `app/Services/PublicBookingService.php`
	- `app/Controllers/Api/Availability.php`
	- `app/Controllers/Api/Appointments.php`
- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Phase 2 Refactor (Location-Aware Query Determinism)

Follow-up backend hardening completed in availability conflict paths:

- `app/Services/AvailabilityService.php`
	- Applied `location_id` filtering to busy-period appointment query when location context is present.
	- Applied `location_id` filtering to conflicting-appointments query when location context is present.
	- Removed redundant location-active checks in `getAvailableSlots()` and `isSlotAvailable()` (context is already validated by `resolveLocationContext()`).
	- Removed unused helper `isProviderLocationActive()` after consolidation.

Impact:

- Reduces hidden cross-location side effects during slot generation and conflict detection.
- Improves deterministic behavior for location-scoped availability checks.
- Removes dead code and redundant conditions per refactor rules.

Validation:

- `php -l app/Services/AvailabilityService.php` passes.
- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Phase 2 Refactor (Scheduler Naming + Deterministic Normalization)

Additional frontend consistency hardening completed for scheduler state handling:

- `resources/js/modules/scheduler/scheduler-core.js`
	- Added centralized appointment normalization helpers:
		- `parseOptionalInteger(value)`
		- `parseAppointmentDateTime(value)`
		- `normalizeAppointment(rawAppointment)`
	- `loadAppointments()` now normalizes API payload items into a canonical internal shape (`providerId`, `serviceId`, `locationId`, `startDateTime`, `endDateTime`) before storing state.
	- `getFilteredAppointments()` now relies on normalized canonical fields, removing repeated per-filter snake/camel conversion.
	- `setFilters(...)` now uses `parseOptionalInteger(...)` for provider/service/location parsing to avoid integer parsing drift and NaN edge cases.

- `resources/js/modules/scheduler/stats/stats-engine.js`
	- Updated provider aggregation to prioritize canonical `providerId` before legacy `provider_id` fallback.
	- Updated datetime parsing to use pre-normalized `startDateTime` when present/valid, then fallback parsing paths.
	- Kept backward-compatible fallbacks for mixed API payloads while preferring canonical scheduler state.

Impact:

- Reduces naming inconsistency side effects across scheduler filtering/stats paths.
- Improves determinism by normalizing appointment state once at ingest time.
- Preserves compatibility with mixed API payload styles during transition.

Validation:

- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Phase 2 Refactor (Appointments API Naming Convergence)

Additional API/frontend naming convergence completed in appointment listing paths:

- `app/Controllers/Api/Appointments.php`
	- `index()` now accepts both snake_case and camelCase query filter aliases:
		- `provider_id` / `providerId`
		- `service_id` / `serviceId`
		- `location_id` / `locationId`
	- `index()` response now emits canonical snake_case entity fields (`provider_id`, `service_id`, `customer_id`, `service_name`, `provider_name`, `provider_color`, `service_duration`, `service_price`, `location_id`, `location_name`, `location_address`, `location_contact`) while retaining camelCase aliases for compatibility during transition.
	- `meta.filters` now includes snake_case filter keys with camelCase aliases.

- `resources/js/modules/scheduler/scheduler-core.js`
	- `normalizeAppointment(...)` now maps snake_case API fields into canonical scheduler display/runtime fields (`serviceName`, `providerName`, `locationName`, `providerColor`, `title`) to keep Week/Month rendering deterministic regardless of payload style.

Impact:

- Reduces naming drift between backend payloads and scheduler state.
- Preserves backward compatibility while shifting canonical API semantics toward snake_case.

Validation:

- `php -l app/Controllers/Api/Appointments.php` passes.
- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Phase 2 Refactor (Modal/Stats Consumer Naming Cleanup)

Follow-up frontend consumer cleanup completed after API/scheduler naming convergence:

- `resources/js/modules/scheduler/scheduler-core.js`
	- Extended `normalizeAppointment(...)` with canonical customer fields used by UI actions:
		- `customerName`, `customerEmail`, `customerPhone`
	- Preserves compatibility aliases while ensuring a deterministic camelCase runtime contract for scheduler modules.

- `resources/js/modules/scheduler/appointment-details-modal.js`
	- Removed mixed snake/camel fallback reads in high-use modal actions.
	- Modal now consumes normalized scheduler fields consistently:
		- `start` / `end`
		- `customerName`
		- `serviceName`
		- `providerName`
		- `customerEmail`
		- `customerPhone`

- `resources/js/modules/scheduler/stats/stats-engine.js`
	- Provider aggregation now uses canonical `providerId` from normalized scheduler state.

Impact:

- Reduces mixed-naming branching in modal/stats paths.
- Moves scheduler consumers toward a single internal naming contract while keeping ingress compatibility at normalization boundaries.

Validation:

- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Phase 2 Refactor (Day/Week/Month Consumer Fallback Reduction)

Additional scheduler consumer cleanup completed to reduce mixed naming branches:

- `resources/js/modules/scheduler/scheduler-day-view.js`
	- Updated appointment display name selection to rely on normalized `customerName` with `title` fallback.

- `resources/js/modules/scheduler/scheduler-week-view.js`
	- Updated appointment summary and expanded day-cell rendering to rely on normalized `customerName` with `title` fallback.

- `resources/js/modules/scheduler/scheduler-month-view.js`
	- Updated month appointment block/list title derivation to prefer normalized `customerName` with `title` fallback.

Impact:

- Removes remaining high-frequency `apt.name || apt.customerName` branch patterns in core scheduler views.
- Keeps user-facing rendering unchanged while converging to a single normalized internal contract.

Validation:

- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — Phase 2 Refactor (API Envelope Strictness + Constants + Debug Cleanup)

Additional cleanup pass completed for strict naming, duplication reduction, and production-readiness:

- `app/Controllers/Api/Appointments.php`
	- Added centralized constant for default provider color:
		- `DEFAULT_PROVIDER_COLOR`
	- `index()` response now emits canonical snake_case fields only (removed mixed camelCase alias keys from payload and `meta.filters`).
	- Replaced duplicate hardcoded provider color literals with `DEFAULT_PROVIDER_COLOR`.
	- Removed noisy per-request/per-appointment informational/debug logs in `index()`.
	- `meta.total` now uses filtered total count from query count result instead of page item count.

- `resources/js/modules/scheduler/constants.js`
	- Added centralized scheduler constant:
		- `DEFAULT_PROVIDER_COLOR`

- `resources/js/modules/scheduler/scheduler-core.js`
	- Switched default provider color fallback to centralized `DEFAULT_PROVIDER_COLOR` constant.

- `resources/js/modules/scheduler/appointment-details-modal.js`
	- Switched default provider color fallback to centralized `DEFAULT_PROVIDER_COLOR` constant.

- `app/Views/appointments/index.php`
	- Removed console debug output from prototype bootstrap script to avoid production debug remnants.

Impact:

- Removes mixed-case API response envelope drift in appointment listing.
- Centralizes repeated default color magic value into a single scheduler constant.
- Reduces debug/log noise for production deployments.

Validation:

- `php -l app/Controllers/Api/Appointments.php` passes.
- Frontend build passes (`npm run build`).

## 2026-02-25 Addendum — D13 Date Navigation Label Deduplication

Additional scheduler deduplication completed for header date-label rendering:

- `resources/js/modules/scheduler/date-nav-label.js`
	- Added shared date label formatter utilities:
		- `formatDateNavLabel(...)`
		- `syncDateNavLabel(...)`
	- Supports day/week/month label formatting using scheduler timezone context.

- `resources/js/modules/scheduler/scheduler-core.js`
	- Replaced local duplicated date-label switch logic in `getDateRangeText()` with shared `formatDateNavLabel(...)`.
	- Replaced direct DOM text assignment in `updateDateDisplay()` with shared `syncDateNavLabel(...)`.

- `resources/js/modules/scheduler/scheduler-ui.js`
	- Replaced fallback duplicate date-label formatting switch in toolbar updater with shared `syncDateNavLabel(...)`.

Impact:

- Removes duplicated date-label rendering logic between core and UI fallback paths.
- Preserves existing day/week/month output format while reducing drift risk.

Validation:

- Frontend build passes (`npm run build`).

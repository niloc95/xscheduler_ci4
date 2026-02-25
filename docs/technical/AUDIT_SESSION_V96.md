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

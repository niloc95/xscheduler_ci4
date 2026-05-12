---
title: WebScheduler CI4 - Consolidated Engineering Contract
version: 3.0
status: Active hardening
last_updated: 2026-05-12
source_documents:
  - Agent_Context.md
  - Agent_Context_Restructured.md
  - restructure_diff.md
purpose: Single source of truth for agents, developers, and architects
---

# WebScheduler CI4 - Agent Context v2

## Rule #1 — Full Codebase Audit Directive

Perform a comprehensive audit of the entire codebase with the following objectives:

1. Code Quality and Redundancy
Identify and eliminate:
Duplicate or repeated code
Dead code (unused variables, functions, imports, files)
Orphaned components, views, and routes
Detect inline CSS and migrate to structured styling (Tailwind/SCSS)

2. Architecture Mapping
Map relationships between:
Controllers ↔ Views ↔ Helpers ↔ Services ↔ Assets
Identify:
Unused or disconnected components
Architectural violations
Tight coupling and improper dependencies

3. Refactoring Opportunities
Propose and implement:
Service layer extraction (e.g., MailerService, BookingService)
Reusable UI components
Helper consolidation
JavaScript modularization

4. Performance Optimization
Detect and resolve:
Unused or duplicate JS/CSS assets
Inefficient Vite bundling / multiple entry issues
Large or unnecessary dependencies

5. Security and Validation
Enforce:
Input validation across all forms
Proper output escaping
CSRF protection consistency
Secure file and external input handling

6. Deployment Integrity
Verify:
Only required files are deployed
No dev/debug artifacts in production
Environment configuration is secure and consistent

7. Actionable Output Requirement
Provide clear, actionable fixes
Include file-level recommendations
Avoid vague suggestions - always propose a concrete solution

8. Scheduling and Availability Audit (Domain-Specific)
When auditing scheduling code, verify:
- Every `xs_business_hours` query includes a `provider_id` filter — no unscoped reads
- Global business bounds are read from `xs_settings` (keys `business.work_start`, `business.work_end`), not from per-provider tables
- `SettingModel::getByKeys()` is used (not the non-existent `getValue()`)
- `AvailabilityService::constrainToBusinessHours()` is called for every slot-generation path
- `TimezoneService::businessTimezone()` is the source for the business timezone — never hardcoded `'UTC'`

## ⚠️ Rule #2 — No Assumptions

Do not assume code is correct.
Always verify usage before keeping anything.
If uncertain → trace usage or flag it.

**Query Scope Rule:** Every query against a per-entity table (`xs_business_hours`, `xs_provider_schedules`, `xs_locations`, `xs_blocked_times`) MUST include an entity-scoping `WHERE` clause. An unfiltered query on these tables returns a random row from whichever entity was inserted first — never a "global" value. Verify every `WHERE` clause before keeping code.

**API Contract Rule:** `SettingModel` exposes `getByKeys(array)`, `getByPrefix(string)`, `getAllAsMap()`, and `upsert()`. There is no `getValue()` method. Always use `getByKeys(['key'])` to read a single setting.

## 🧠 Rule #3 — Before You Change Code (Mandatory Checklist)

Before making any code changes, the agent must validate the following:

[] Does this logic belong in a service instead of a controller/view?
[] Am I using UTC semantics for persisted datetimes?
[] Am I reading timezone/localization from settings-backed services?
[] Am I using canonical appointment fields and IDs?
[] Am I preserving API response contracts?
[] Am I reusing queue and booking pipelines?
[] Am I respecting route filters and role boundaries?
[] Am I keeping SPA initialization conventions?
[] If the controller action redirects back to the current page, does the JSON response include a `redirect` key so spa.js can use forceReload?
[] Am I using xsRegisterViewInit instead of a bare DOMContentLoaded handler?
[] If writing a migration, does it extend MigrationBase?
[] Did I avoid dead-code surfaces and stale symbols?
[] Have I verified consistency with SPA.JS and APP.JS patterns?
[] Am I querying `xs_business_hours` with a `provider_id` filter? (All rows are per-provider; no global-only rows exist. An unfiltered query returns the first-inserted provider's hours — not a system-wide default.)
[] Am I reading global time bounds (`business.work_start`, `business.work_end`) from `xs_settings` via `SettingModel::getByKeys()`, not from `xs_business_hours`?

## 🎯 Rule #4 — Dashboard Layout and Scroll UX Contract

Dashboard panels must use semantic, responsive scroll containers and avoid hardcoded height utilities in view templates.

Mandatory requirements:

1. No inline hard caps in templates
- Do not use one-off utility caps such as `max-h-[400px]`, `h-[...px]`, or fixed pixel scroll wrappers directly in view markup for primary dashboard panels.
- Define semantic classes in SCSS (example: `dashboard-schedule-scroll`, `provider-card-slots`) and keep sizing behavior centralized.

2. Viewport-aware sizing
- Use `clamp()`/viewport-aware sizing for desktop dashboards so panel bodies scale across common screen sizes (1366x768 through 2560x1440) without clipping critical controls.
- On tablet/mobile (`<=1023px`), default to natural height flow unless there is a clear performance reason for nested scroll.

3. Nested scroll rules
- Nested scroll is allowed only for long data lists, not for filter/control groups.
- Filters and primary actions must remain fully visible without requiring inner scrolling.

4. UX consistency
- Keep provider and schedule panel behaviors consistent: if one pane is internally scrollable on desktop, the other should use an equivalent semantic strategy.
- Preserve accessibility and keyboard navigation; avoid scroll traps (`overscroll-behavior: contain` where needed).

## 🧪 Rule #5 — Provider Assignment Integrity Audit (Operational)

Before shipping any dashboard/provider-card changes that touch service, location, or availability filtering, run the canonical assignment audit and review results:

Command:

`php spark audit:provider-assignments`

Minimum checks:

1. Provider -> Services mapping
- Confirm each provider card source only contains services assigned via `providers_services` mapping.

2. Provider -> Locations mapping
- Confirm location options are provider-scoped (no cross-provider location leakage).

3. Service -> Providers mapping
- Confirm sensitive/owner-specific services are assigned only to expected providers.

Release gate:
- If audit reports unexpected cross-provider assignment or missing critical assignments, treat as blocking until resolved or explicitly approved.

## 🔥 Why This Is Strong

This document is the canonical engineering contract for this repository.

Use this file first when making architecture, API, scheduling, booking, notification, frontend lifecycle, or database-sensitive changes.

## 1) Quick Navigation

| If you need to... | Read this section first |
| --- | --- |
| Understand system boundaries | 2) Foundation and Design Order |
| Check non-negotiable architecture rules | 3) Global Contracts |
| Implement or review RBAC logic | 4) Authentication and Authorization Contract |
| Build frontend behavior safely with SPA lifecycle | 6) Frontend Contract |
| Change appointment flows or scheduler behavior | 8) Appointments and Scheduling Contract |
| Change public booking behavior | 10) Public Booking Contract |
| Modify notification behavior | 11) Notifications Contract |
| Understand reminder offset independent triggering | 11.8a) Reminder Offsets Behavior |
| Update persistence logic or queries | 12) Database Schema and Relationships |
| Add migrations safely | 13) Migration and Schema-Drift Rules |
| Avoid duplication, orphans, naming collisions | 14) Mandatory Quality Gates |

## 2) Foundation and Design Order

### 2.1 What You Are Building

WebScheduler CI4 is a professional appointment scheduling system built on CodeIgniter 4.

Architecture style: service-oriented CI4 monolith with server-rendered views, REST-style APIs, Vite-managed frontend assets, and custom scheduler/public-booking modules.

### 2.2 Confirmed Stack

- Backend: PHP 8.1+, CodeIgniter 4
- Frontend: Vite 6, Tailwind CSS 3.4, SCSS, plain JavaScript modules, Chart.js 4, Luxon 3
- Data: MySQL/MariaDB runtime only
- Auth: session-based
- Authorization: route filters plus service-level checks

### 2.3 No Framework Drift

- Do not introduce React/Vue/Alpine without explicit approval.
- Do not introduce TypeScript without explicit scoped approval.

### 2.4 Final Design Order (Mandatory)

Always design and implement in this order:

1. Data and storage contract
2. Service logic
3. API contract
4. View/UI rendering

If a UI change starts before service/data contract clarity, stop and fix boundaries first.

## 3) Global Contracts

### 3.1 Services Own Business Logic

Scheduling, booking, availability, timezone conversion, notification dispatch, and query scoping belong in services.

### 3.2 Controllers Stay Thin

Controllers parse requests, call services, and shape responses. Controllers are not alternate business layers.

### 3.3 Models Stay Data-Focused

Models encapsulate data access and query composition. Keep model behavior compatible with existing schema-safe patterns.

### 3.4 API Envelope Contract

API controllers should use BaseApiController response helpers.

Success envelope:

```json
{ "data": {}, "meta": {} }
```

Error envelope:

```json
{ "error": { "message": "", "code": "", "details": {} } }
```

### 3.5 Views Render, They Do Not Decide

Views must not perform business logic, authorization, or timezone calculations.

## 4) Authentication and Authorization Contract

### 4.1 Operational Roles

- admin
- provider
- staff
- customer

### 4.2 Multi-Role Storage Model

| Layer | Storage | Role |
| --- | --- | --- |
| Compatibility primary | xs_users.role | Legacy single-role fallback |
| Authoritative membership | xs_user_roles | Real role membership set |

### 4.3 Session Contract at Login

Set by auth flow:

- role: primary compatibility role
- roles: full authoritative role array from UserModel::getRolesForUser()
- active_role: highest privilege role for UI context

Read role membership from roles first. Use role only as fallback.

### 4.4 Canonical RBAC Pattern

Use this shape everywhere:

```php
$userRoles = $user['roles'] ?? [$user['role'] ?? ''];
$hasAccess = !empty(array_intersect($requiredRoles, $userRoles));
```

Never make authorization decisions from xs_users.role alone.

### 4.5 Session Write Contract (v140+ Mandatory)

Any mid-session update to session user payload must preserve existing role context.

Required pattern:

```php
$currentUser = session()->get('user') ?? [];
session()->set('user', array_merge($currentUser, [
    // only fields being updated
]));
```

Only the initial login path may write a full user array from scratch.

### 4.6 Route Filter Contract

Primary filters:

- setup
- auth
- role:admin
- role:admin,provider
- role:admin,provider,staff

## 5) API Contract

### 5.1 Versioning and Surface

- Versioned endpoints under /api/v1/
- Operational endpoints under /api/

### 5.2 Base Controller Responsibilities

Base API controller enforces:

- requireAuth()
- hasRole()
- requireRole()
- success/error envelope helpers

### 5.3 Public Endpoint Guardrails

Public endpoints must keep:

- hash/token protections
- rate limiting
- provider/service scoping
- no numeric customer IDs in public URLs

### 5.4 SPA Form JSON Contract

For SPA-intercepted form posts:

```json
{
  "success": true,
  "message": "Saved",
  "redirect": "https://app/path",
  "errors": {}
}
```

If success destination equals current page, include redirect so SPA navigation can force reload.

## 6) Frontend Contract

### 6.1 Canonical Entry Points

Vite entry points (`vite.config.js` `rollupOptions.input`):

| Key | File |
| --- | --- |
| `main` | `resources/js/app.js` |
| `style` | `resources/scss/app-consolidated.scss` |
| `spa` | `resources/js/spa.js` |
| `dark-mode` | `resources/js/dark-mode.js` |
| `theme-bootstrap` | `resources/js/theme-bootstrap.js` |
| `app-layout-init` | `resources/js/layout/app-layout-init.js` |
| `public-booking` | `resources/js/public-booking.js` |
| `public-booking-bootstrap` | `resources/js/public-booking-bootstrap.js` |
| `unified-sidebar` | `resources/js/unified-sidebar.js` |
| `charts` | `resources/js/charts.js` |
| `setup` | `resources/js/setup.js` |

`resources/js/material-web.js` was removed — the `@material/web` bundle was unused (zero `<md-*>` elements in production views).

Non-entry utility modules (import only, not Vite entry points):
- `resources/js/currency.js` — currency formatting, loads settings from `/api/v1/settings/localization`
- `resources/js/utils/avatar.js` — avatar initials helper (see §6.8)

### 6.2 Lifecycle Contract

Core lifecycle for authenticated surfaces:

1. app.js loads
2. spa.js intercepts links/forms
3. initializeComponents() runs on load and after spa:navigated
4. View code uses xsRegisterViewInit(fn)
5. Initializers must be idempotent via dataset guards

Do not add bare DOMContentLoaded-only logic for app surfaces that can SPA-navigate.

### 6.3 SPA Navigation Contract

spa.js behavior:

- swaps #spa-content
- emits spa:navigated after successful swap
- supports data-no-spa and no-spa opt-outs
- supports forceReload semantics for same URL post-mutation refresh

### 6.4 Dark Mode Contract

**Tailwind configuration:** `darkMode: 'class'` in `tailwind.config.js`. All Tailwind `dark:` utilities require `.dark` on `<html>`.

**FOUC prevention:** `resources/js/theme-bootstrap.js` runs as an inline blocking script (no `type="module"`) on every page load. It sets BOTH `document.documentElement.dataset.theme = theme` AND `document.documentElement.classList.toggle('dark', theme === 'dark')` before the browser paints.

**Runtime management:** `DarkModeManager` in `resources/js/dark-mode.js` handles the full lifecycle:
- On every `applyTheme()` call: sets BOTH `data-theme` attribute AND `.dark` class
- Persists to `localStorage` key `xs-theme`
- Dispatches `xs:theme-changed` custom event for downstream components
- Re-wires `[data-theme-toggle]` buttons after each `spa:navigated` event

**SCSS:** Dark overrides use `.dark { ... }` selector in `resources/scss/abstracts/_custom-properties.scss`, not `[data-theme="dark"]`.

**Summary:** `data-theme` attribute is always kept in sync (for external consumers and CSS that reads it), but `.dark` class is the canonical trigger for Tailwind utilities and SCSS dark overrides.

### 6.5 Styling Contract

- Tailwind + consolidated SCSS only
- No inline style attributes in app-facing templates

### 6.6 Shared Fetch Contract

- `resources/js/core/api.js` `apiRequest()` returns `{ response, payload }`.
- For `application/json` responses, `payload` is already parsed JSON. Consumers must not assume string methods such as `match()` are available unless they first confirm `typeof payload === 'string'`.
- Text or HTML responses may still return string payloads.
- Shared frontend helpers such as `extractJSON()` must accept already-parsed objects so search surfaces remain compatible with the shared fetch layer.

### 6.7 Profile Surface Contract

- `/profile` is a live account surface backed by `App\Services\ProfilePageService`; do not reintroduce placeholder summary cards or fake recent activity.
- `app/Views/profile/index.php` is SPA-safe and is initialized through `resources/js/modules/profile/profile-page.js` from `resources/js/app.js`.
- Profile mutations must preserve session role context via `array_merge` and write audit-log events for `user_updated`, `password_changed`, `profile_photo_updated`, and `notification_preferences_updated`.
- Provider and staff notification preferences edited from `/profile` must persist to `xs_users.notify_on_appointments`.

### 6.8 Avatar System Contract (Owner Section)

All avatar rendering — profile images and initials fallback — has a single source of truth on both PHP and JS sides.

#### 6.8.1 PHP Helpers (`app/Helpers/app_helper.php`)

Four canonical helpers, loaded via `helper('app')`:

| Helper | Purpose |
| --- | --- |
| `avatar_initials(string $name, string $default = 'U'): string` | Derives 1-2 letter initials from a display name. Strips titles (Dr., Prof., Mr., Mrs., Ms., Rev.) and suffixes (MD, PhD, DDS, DO, RN, NP, PA, DVM, Jr., Sr., II, III, IV). Multi-word → first + last initial. Single-word → first 2 chars. Empty → `$default`. |
| `avatar_display_name(array $user, string $fallback = 'User'): string` | Prefers `$user['name']`; falls back to `first_name` + `last_name` concatenation. |
| `avatar_profile_image_url(array $user): ?string` | Resolves profile image URL. Paths starting with `assets/` map to `FCPATH`; paths starting with `uploads/` or `writable/` map to `WRITEPATH`. Returns `null` if no usable path. |
| `avatar_data(array $user, string $defaultInitial = 'U'): array` | Returns `['imageUrl' => ?string, 'initials' => string, 'displayName' => string]`. Use this in views for image-first rendering with initials fallback. |

`ProfilePageService::buildProfileImageUrl()` and `buildProfileInitials()` delegate to these helpers.

#### 6.8.2 JS Utility (`resources/js/utils/avatar.js`)

Single ESM module — import directly in other modules:

```js
import { getAvatarInitials, getDisplayName } from '../../utils/avatar.js';
```

| Export | Signature | Behaviour |
| --- | --- | --- |
| `getDisplayName(entity, fallback)` | `(object, string) → string` | Prefers `entity.name`; falls back to `first_name` + `last_name`. |
| `getAvatarInitials(name, options)` | `(string, { defaultInitial? }) → string` | Same normalization rules as PHP: strip titles/suffixes, multi-word → first+last initial, single-word → first 2 chars. `options.defaultInitial` defaults to `'U'`. |

For inline `<script>` blocks in PHP views (which cannot use ESM imports), use the globals exposed by `resources/js/app.js`:

```js
window.xsGetAvatarInitials(name, defaultInitial)
window.xsGetDisplayName(entity, fallback)
```

#### 6.8.3 Canonical Default Initials by Context

| Context | Default |
| --- | --- |
| User / staff / header | `'U'` |
| Customer | `'C'` |
| Staff assignment widget | `'S'` |
| Provider assignment widget | `'P'` |
| Scheduler provider chip | `'?'` |

#### 6.8.4 Covered Surfaces

- `app/Views/layouts/app.php` — header user avatar (image-first via `avatar_data()`)
- `app/Views/user-management/index.php` — PHP rows + JS `userRow()` via `window.xsGetAvatarInitials`
- `app/Views/customer-management/index.php` — `avatar_data()` with `defaultInitial: 'C'`
- `app/Views/customer-management/history.php` — large customer header avatar
- `app/Views/appointments/form.php` — customer avatar placeholder (`'C'`)
- `app/Views/user-management/components/provider-staff.php` — server PHP + JS `renderStaff()` widget
- `app/Views/user-management/components/staff-providers.php` — server PHP + JS `renderProviders()` widget
- `resources/js/modules/scheduler/appointment-colors.js` — `getProviderInitials()` delegates to `getAvatarInitials`
- `resources/js/modules/customer-management/customer-search.js`
- `resources/js/modules/appointments/appointments-form.js`

#### 6.8.5 Do Not Duplicate

Do not reimplement initials logic in any view, controller, or JS module. Always call the shared helper. A one-letter initial in a completed surface is a regression against this contract.

### 6.9 View Layout & Design System Contract (Owner Section)

#### 6.9.1 Canonical Layout

All authenticated views **must** extend `layouts/app`. The legacy `layouts/dashboard` layout is deprecated; do not use it for new views or when migrating existing views.

| Layout | Status | Sections |
| --- | --- | --- |
| `layouts/app` | **Active standard** | `sidebar`, `header_title`, `header_subtitle`, `header_primary_action`, `header_controls`, `content`, `scripts`, `modals` |
| `layouts/dashboard` | **Deprecated** | `dashboard_stats`, `dashboard_actions`, `dashboard_filters`, `dashboard_content_top`, `dashboard_content` |

To suppress the header's built-in "New Appointment" CTA for a page that provides its own CTA in `content`, set:
```php
<?= $this->section('header_primary_action') ?>hidden<?= $this->endSection() ?>
```

#### 6.9.2 Design System Component Classes

| Token | Element | Notes |
| --- | --- | --- |
| `xs-card` | Card container | Replaces `card card-spacious` |
| `xs-card-header` | Card header bar | Use with `xs-card-header-content` (left) + `xs-card-actions` (right) |
| `xs-card-header-content` | Left slot of card header | Contains title + subtitle |
| `xs-card-title` | Title inside `xs-card-header-content` | |
| `xs-card-subtitle` | Subtitle inside `xs-card-header-content` | |
| `xs-card-actions` | Right slot of card header | Icon buttons, secondary controls |
| `xs-card-body` | Card content area | Add `p-0` when the card contains a full-bleed table |
| `xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon` | Icon-only action button | Replaces text+icon `btn btn-secondary btn-sm` rows |
| `xs-btn xs-btn-sm xs-btn-primary` | Primary small button | |
| `xs-actions-container` | Wrapper for per-row icon action buttons | Always `justify-end` for right-aligned table cells |

#### 6.9.3 Per-Row Action Button Pattern

All table action columns use icon-only buttons inside `xs-actions-container`. This is the canonical pattern — do not add visible text labels to row actions:

```php
<div class="xs-actions-container justify-end">
    <a href="<?= site_url('entity/edit/' . $row['id']) ?>"
       class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" title="Edit">
        <span class="material-symbols-outlined">edit</span>
    </a>
    <form action="<?= site_url('entity/delete/' . $row['id']) ?>" method="post"
          class="inline-flex" data-no-spa="true"
          data-confirm-message="Delete this item?">
        <?= csrf_field() ?>
        <button type="submit"
                class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-red-600 hover:text-red-700 dark:text-red-400"
                title="Delete">
            <span class="material-symbols-outlined">delete</span>
        </button>
    </form>
</div>
```

#### 6.9.4 Flash Messages

Flash messages are rendered automatically by `layouts/app` via `$this->include('components/ui/flash-messages')`. Views must NOT include manual flash message HTML blocks (`session()->getFlashdata('message')` divs). Deleting those blocks from migrated views is required.

#### 6.9.5 Appointments Toolbar — Mobile Two-Rail Layout

`app/Views/appointments/index.php` uses a two-rail horizontal layout inside `header_controls`:

- **Outer wrapper**: `appointments-toolbar flex flex-row items-start gap-3 md:items-center md:gap-3`
- **Rail A** (`appointments-toolbar__primary`): `flex flex-col gap-1.5 min-w-0 md:flex-row md:items-center md:gap-2`
  - Sub-row 1 (mobile): Today button + view-mode switcher
  - Sub-row 2 (mobile): Date navigation cluster (prev / label / next)
  - Collapses to single flex row on `md+`
- **Rail B** (`appointments-toolbar__secondary`): `flex items-start gap-2 min-w-0 md:items-center`
  - `#scheduler-stats-bar`: `flex flex-col gap-1 md:flex-row md:flex-nowrap md:items-center md:gap-1` — chips stack vertically on mobile, row on desktop
- Mobile filter button is **removed** from Rail B; filter toggle is desktop-only (`hidden md:inline-flex`)

Do not revert to a single `overflow-x-auto` row — that pattern caused date cluster overflow on narrow screens.

## 7) Backend Service Boundaries

### 7.1 Canonical Services

**Core scheduling:**
- `AvailabilityService` — slot generation and business-hours constraint
- `AppointmentBookingService` — booking pipeline (validate → persist → notify)
- `ConflictService` — appointment conflict detection
- `ScheduleValidationService` — provider schedule validation
- `BusinessHoursService` — global business hours from `xs_settings`

**Appointment namespace** (`App\Services\Appointment\`):
- `AppointmentQueryService` — appointment reads and list queries
- `AppointmentStatus` — status enum and notification event mapping
- `AppointmentMutationService` — appointment writes (status changes etc.)
- `AppointmentAvailabilityService` — availability checks per appointment
- `AppointmentFormSubmissionService` / `AppointmentFormMutationService` — form create/edit pipeline
- `AppointmentFormContextService` / `AppointmentFormGuardService` / `AppointmentFormResponseService` — form guards and response shaping
- `AppointmentCustomFieldService` — custom field validation and storage
- `AppointmentManualNotificationService` — manual notification resend
- `AppointmentFormatterService` — appointment data formatting for views
- `AppointmentDateTimeNormalizer` — datetime normalization helpers

**Calendar views** (`App\Services\Calendar\`):
- `DayViewService` / `WeekViewService` / `MonthViewService` — server-side view model builders
- `CalendarConfigService` — calendar display configuration
- `TimeGridService` — time-grid computation shared by day/week views
- `ProviderWorkingHoursTrait` — working hours shared logic (see §8.7.4 for known debt)

**Users / customers:**
- `UserManagementMutationService` — user create/edit/delete mutations
- `UserManagementContextService` — user management page context
- `UserDeletionService` — safe user removal with cascade checks
- `CustomerAppointmentService` — provider/staff customer scoping
- `CustomerService` — customer CRUD
- `CustomerDeletionService` — safe customer removal
- `CustomerCustomFieldService` — custom field validation (see Custom Field Required Semantics)
- `ProfilePageService` — /profile page context and mutations
- `AuthorizationService` — RBAC helpers

**Public booking:**
- `PublicBookingService` — public booking pipeline (see §10)
- `BookingLinkService` — canonical URL builder for booking links and `/r/{reference}` short links
- `BookingSettingsService` — booking form configuration, custom field validation rules
- `BookingMetricsService` — booking statistics

**Dashboard:**
- `DashboardService` — dashboard data aggregation
- `DashboardPageService` — dashboard page context builder
- `DashboardApiService` — dashboard API endpoint data
- `AppointmentDashboardContextService` — appointment dashboard context

**Notifications:**
- `AppointmentEventService` — appointment event publishing
- `AppointmentNotificationService` — appointment notification coordination
- `NotificationQueueService` — queue write and enqueue logic
- `NotificationQueueDispatcher` — queue processing and dispatch
- `NotificationPolicyService` — delivery policy rules
- `NotificationEmailService` — email delivery via `MailerService`
- `NotificationSmsService` — SMS delivery (Clickatell/Twilio)
- `NotificationWhatsAppService` — WhatsApp delivery (Meta Cloud API)
- `NotificationTemplateService` — template loading and placeholder rendering
- `NotificationDeliveryLogService` — delivery log queries
- `NotificationOptOutService` — opt-out management
- `NotificationReminderHeartbeatService` — reminder offset scheduling heartbeat
- `NotificationAutomationStatusService` — automation on/off status
- `NotificationBusinessOptionsService` — business-level notification options
- `NotificationCenterService` — notification center UI data
- `NotificationCatalog` — constants (event types, defaults)

**Settings** (`App\Services\Settings\`):
- `SettingsPageService` — settings page context
- `SettingsApiService` — settings API endpoints
- `GeneralSettingsService` — general/localization settings mutations
- `NotificationSettingsService` — notification settings mutations
- `LocalizationSettingsService` — timezone/locale reads

**Infrastructure:**
- `MailerService` — sole email transport layer (see §7.3)
- `TimezoneService` — UTC/local conversion (see §12.6)
- `GlobalSearchService` — cross-entity search
- `PhoneNumberService` — phone number formatting/normalization
- `BookingMetricsService` — booking statistics

### 7.2 Key System Files

- `app/Config/Routes.php`
- `app/Config/Filters.php`
- `app/Controllers/Api/BaseApiController.php`
- `app/Database/MigrationBase.php`
- `app/Models/AppointmentModel.php`
- `app/Models/UserModel.php`
- `app/Models/SettingModel.php`
- `app/Models/AppointmentCustomFieldModel.php`
- `app/Services/MailerService.php`
- `app/Services/AvailabilityService.php`
- `app/Services/BusinessHoursService.php`
- `app/Services/BookingLinkService.php`
- `app/Services/Calendar/DayViewService.php`
- `app/Services/Calendar/WeekViewService.php`
- `app/Services/Calendar/ProviderWorkingHoursTrait.php`
- `resources/js/spa.js`
- `resources/js/app.js`
- `resources/js/theme-bootstrap.js`
- `resources/js/dark-mode.js`
- `resources/js/layout/app-layout-init.js`
- `resources/js/public-booking.js`
- `resources/js/public-booking-bootstrap.js`
- `resources/js/modules/scheduler/scheduler-core.js`
- `resources/js/modules/scheduler/time-grid-utils.js`
- `vite.config.js`

### 7.4 Business Context Resolver Contract

**Owner section.** All business-ID resolution rules live here. §11 contains reference-only reminders.

#### 7.4.1 `current_business_id()` Helper

`app/Helpers/permissions_helper.php` exposes `current_business_id(?int $default = null): int`.
It is the single canonical way to resolve the active business context for the current request.
Load it with `helper('permissions')` (already loaded by `BaseController`).

#### 7.4.2 Resolution Priority (Strict)

1. **Request params** — `GET`/`POST` `business_id` or `businessId`
2. **Session keys** — `session()->get('business_id')` or `session()->get('active_business_id')`
3. **Session user sub-keys** — `session()->get('user')['business_id' | 'active_business_id' | 'businessId' | 'activeBusinessId']`
4. **Fallback** — `$default ?? NotificationCatalog::BUSINESS_ID_DEFAULT` (value `1`)

The result is always `max(1, resolved_value)`.

#### 7.4.3 Service-Level Usage Pattern

Services that scope data to a business must use a protected delegate:

```php
protected function resolveBusinessId(): int {
  helper('permissions');
  return current_business_id();
}
```

This makes the resolver overridable in unit tests without request/session scaffolding.

#### 7.4.4 Current Service Coverage

| Service | Scope switch |
| --- | --- |
| `NotificationCenterService` | `getNotifications()`, `getUnreadCount()` |
| `NotificationSettingsService` | `getIndexData()`, `save()` |

Do not use `NotificationCatalog::BUSINESS_ID_DEFAULT` directly in service methods that serve UI requests. Route all UI-facing scoping through `resolveBusinessId()` / `current_business_id()`.

#### 7.4.5 Auth Channel Exception

`Auth::sendResetEmail()` still passes `NotificationCatalog::BUSINESS_ID_DEFAULT` directly to `MailerService`. This is intentional — password reset has no session context. Do not route it through `current_business_id()`.


- app/Config/Routes.php
- app/Config/Filters.php
- app/Controllers/Api/BaseApiController.php
- app/Database/MigrationBase.php
- app/Models/AppointmentModel.php
- app/Models/UserModel.php
- app/Models/SettingModel.php
- app/Services/MailerService.php
- app/Services/AvailabilityService.php
- app/Services/BusinessHoursService.php
- app/Services/Calendar/DayViewService.php
- app/Services/Calendar/WeekViewService.php
- app/Services/Calendar/ProviderWorkingHoursTrait.php
- resources/js/spa.js
- resources/js/app.js
- resources/js/public-booking.js
- resources/js/modules/scheduler/scheduler-core.js
- resources/js/modules/scheduler/time-grid-utils.js
- vite.config.js

### 7.3 Unified Email Transport Contract

**Owner section.** All email transport rules live here. Sections 11.2 and 11.8
contain reference-only reminders that point back to this section.

#### 7.3.1 Single Transport Layer

`MailerService` is the sole permitted email transport layer. No other class may
instantiate or configure a CI4 `Email` driver for production sends. Every
outgoing email — password reset, booking notifications, and future system
emails — flows through `MailerService::send()`.

#### 7.3.2 Transport Resolution Priority (Strict)

1. **Active DB integration (all environments):** An `xs_business_integrations`
   row where `channel = 'email'` and `is_active = 1` exists for the business.
   Config is decrypted from `encrypted_config`. This takes unconditional priority.
2. **`.env` dev fallback (development only):** When `ENVIRONMENT === 'development'`
   and no active integration row is found, `MailerService` uses `Config\Email`
   (populated from `.env`). If `.env` does not specify `protocol = smtp`, the
   service falls back to a hardcoded Mailpit address (`127.0.0.1:1025`) provided
   a `fromEmail` is configured.
3. **null — cannot send:** If neither source yields a valid config,
   `resolveTransportConfig()` returns `null` and `send()` returns
   `['ok' => false, ...]`.

#### 7.3.3 From-Address Ownership

`MailerService` resolves the from address using this priority:
1. `$fromEmailOverride` (caller-supplied, when non-empty)
2. `$config['from_email']` (from the resolved transport config)
3. Failure — an empty from address causes `send()` to return `['ok' => false]`.

Callers must never set a from address on the email driver directly.

#### 7.3.4 send() Response Contract

```php
['ok' => bool, 'error' => ?string, 'transport' => string, 'messageId' => ?string]
```

- `transport` values: `'smtp'` (DB integration), `'dev-fallback'` (`.env`),
  `'unknown'` (error before transport resolved)
- `messageId` is always `null` (reserved for future SMTP header extraction)

#### 7.3.5 canSend() — Queue Gate Capability Check

`MailerService::canSend(int $businessId)` returns `true` only when the resolved
config has a non-empty `host` AND a non-empty `from_email`.

`NotificationEmailService::canUseDevelopmentFallbackSmtp()` is a thin delegate
to `MailerService::canSend()`. This method is the queue gate; queue files
(`NotificationQueueDispatcher`, `NotificationQueueService`) must not be
changed to reference MailerService directly without updating this note.

#### 7.3.6 testConnection() Isolation

`NotificationEmailService::sendTestEmail()` bypasses `MailerService` intentionally.
It constructs a CI4 Email driver from a caller-supplied config object to test
SMTP credentials in real time. Do not route `sendTestEmail()` through
`MailerService` — the bypass is load-bearing for the settings integration wizard.

#### 7.3.7 Auth Email Channel

Password reset emails use
`NotificationCatalog::BUSINESS_ID_DEFAULT` (value `1`) as `$businessId`.
`Auth::sendResetEmail()` renders the view, then calls `MailerService::send()`.
Auth has no knowledge of SMTP configuration.

## 8) Appointments and Scheduling Contract

### 8.1 Single Sources of Truth

- AvailabilityService
- AppointmentBookingService
- Appointment\AppointmentQueryService
- Appointment\AppointmentStatus

### 8.2 Booking Pipeline (Mandatory)

1. Validate service/provider context
2. Resolve timezone boundaries
3. Validate business hours
4. Validate availability and conflicts
5. Resolve/create customer
6. Persist appointment
7. Enqueue notifications

### 8.3 Canonical Appointment Detail Access

All appointment detail views are accessed via the scheduler modal only.

**Entry point:** `/appointments?open={hash}`

**Deep-link compatibility:** Legacy `/appointments/view/{hash}` endpoint persists as a compatibility redirect to `/appointments?open={hash}` to prevent breaking old links, bookmarks, and notification emails.

**Modal implementation:** AppointmentDetailsModal in resources/js/modules/scheduler/appointment-details-modal.js

**Routes affected:** 
- Dashboard "Today's Schedule" card links to `/appointments?open={ref}`
- Customer management history "View Details" links to `/appointments?open={ref}`
- Notification emails contain `/appointments?open={ref}` links

### 8.4 Status to Notification Event Mapping

Canonical source: AppointmentStatus::notificationEvent()

| Status | Event |
| --- | --- |
| pending | appointment_pending |
| confirmed | appointment_confirmed |
| completed | appointment_confirmed |
| cancelled | appointment_cancelled |
| no_show | appointment_no_show |
| rescheduled | appointment_rescheduled |

### 8.5 Scheduler Refresh Semantics (Critical)

Scheduler is not real-time by default.

- No WebSocket/SSE sync channel for appointment data
- No periodic polling for appointment model refresh
- Day view timer updates now-line only

### 8.5 Mode-Aware Data Loading Contract

SchedulerCore loading methods:

- loadData(): canonical mutation-safe refresh entry
  - server mode: calendarModel from /api/calendar/{view}
  - non-server mode: flat appointments from /api/appointments
- loadAppointments(): flat appointment refresh only (not calendarModel)

Rule: mutations that can move slots must refresh through loadData() in server mode.

### 8.6 Canonical Mutation Pipeline

Use appointment-mutation-coordinator for scheduler-side mutations.

Expected behavior:

- inject CSRF
- execute mutation request
- in scheduler context, reload via loadData() and re-render
- dispatch appointment:changed event
- coordinator owns toast and loading state

### 8.7 Business Hours Architecture (Owner Section)

#### 8.7.1 Two Distinct Concepts — Do Not Conflate

| Concept | Source | Scope | Purpose |
| --- | --- | --- | --- |
| Global business hours | `xs_settings` keys `business.work_start`, `business.work_end` | System-wide outer bounds | The widest window any appointment can be booked in |
| Provider schedule | `xs_business_hours` rows (always per `provider_id` + `weekday`) | Per-provider | The specific hours a given provider is available |

**Critical:** `xs_business_hours` has NO global-only rows. Every row always has a `provider_id`. Querying this table without a `provider_id` filter returns an arbitrary provider's row — not a global business hour. This exact mistake caused all providers' slots to start at 10:00 (root cause fixed in commit `34a74a0`).

#### 8.7.2 Canonical Service Responsibilities

| Service / Method | Responsibility |
| --- | --- |
| `BusinessHoursService::getBusinessHoursForDay($weekday)` | Returns global hours from `xs_settings`. `$weekday` is kept for interface compatibility; global hours apply uniformly to all weekdays. |
| `BusinessHoursService::getWeeklyHours()` | Returns Mon–Fri (weekdays 1–5) keyed by weekday, sourced from `xs_settings`. |
| `BusinessHoursService::validateAppointmentTime($start, $end)` | Validates an appointment falls within global hours. Returns `['valid'=>bool,'reason'=>?string,'hours'=>?array]`. |
| `AvailabilityService::constrainToBusinessHours($date, $providerHours)` | Narrows a provider's hours to the global bounds. Uses `SettingModel::getByKeys(['business.work_start','business.work_end'])`. Returns `null` if the window collapses to zero. |
| `BusinessHourModel` | Queries `xs_business_hours` filtered by `provider_id` + `weekday`. Correct place to read individual provider schedules. Never use this without a `provider_id` filter. |

#### 8.7.3 Global Business Hours Setting Keys

| Key | Meaning | Example |
| --- | --- | --- |
| `business.work_start` | Global open time | `'06:00'` |
| `business.work_end` | Global close time | `'17:00'` |

Read via: `SettingModel::getByKeys(['business.work_start', 'business.work_end'])`.

`SettingModel` has no `getValue()` method. Use `getByKeys()` for all reads (including single keys).

#### 8.7.4 ProviderWorkingHoursTrait — Active Debt

`app/Services/Calendar/ProviderWorkingHoursTrait::getBusinessHours()` falls back to calling `$settings->getValue('booking.day_start', '08:00')`. `SettingModel` has no `getValue()` method and this will throw `BadMethodCallException` at runtime when `$this->timeGrid` is not available. The fix is to replace the fallback with `SettingModel::getByKeys(['booking.day_start', 'booking.day_end'])`. This path is hit for the "placeholder provider" (provider_id = 0) case.

### 8.8 Availability and Slot Generation Pipeline

The server-side slot-generation pipeline operates in this sequence:

1. **Resolve provider schedule** — Query `xs_business_hours` with `provider_id` + `weekday` filter to get the provider's start/end and breaks for the requested day.
2. **Constrain to global bounds** — `AvailabilityService::constrainToBusinessHours()` reads `business.work_start` / `business.work_end` from `xs_settings` and narrows the provider window. If the provider window falls entirely outside global hours the day returns no slots.
3. **Remove blocked times** — Query `xs_blocked_times` where `provider_id` matches and the blocked interval overlaps the working window.
4. **Remove booked appointments** — Query `xs_appointments` for confirmed/pending appointments within the window for that provider.
5. **Generate time slots** — Divide remaining free time into increments from `booking.slot_duration` setting.
6. **Return slots** — Each slot is a UTC-anchored datetime.

#### 8.8.1 Key Variables Required Before Calling Availability

| Variable | Source | Notes |
| --- | --- | --- |
| `$providerId` | `xs_users.id` | Required; 0 is only a UI placeholder |
| `$date` | Request input | ISO date `YYYY-MM-DD` |
| `$serviceId` | `xs_services.id` | Determines slot duration |
| `$timezone` | `localization.timezone` from `xs_settings` | `?string`, `null` resolves via `TimezoneService::businessTimezone()` |
| `business.work_start` / `business.work_end` | `xs_settings` | Global outer bounds |
| Provider's `xs_business_hours` rows | `provider_id` + `weekday` | Per-provider schedule |
| `xs_blocked_times` rows | Provider + date range | Provider non-availability intervals |
| `booking.slot_duration` | `xs_settings` | Slot increment in minutes |

### 8.9 Calendar Architecture and Data Flow

#### 8.9.1 Server-Side View Model API

The calendar uses pre-computed server-side render models via `CalendarController`.

| Endpoint | Service | View |
| --- | --- | --- |
| `GET /api/calendar/day?date=YYYY-MM-DD` | `DayViewService` | Day view |
| `GET /api/calendar/week?date=YYYY-MM-DD` | `WeekViewService` | Week view |
| `GET /api/calendar/month?year=&month=` | `MonthViewService` | Month view |

Common query params: `provider_id`, `service_id`, `location_id`, `status`.

Response envelope: `{ "data": { ...viewModel... }, "meta": { "view", "date", "generated_at" } }`

`viewModel.businessHours` (`{ startTime, endTime }`) is sourced from `ProviderWorkingHoursTrait::getBusinessHours()` using `booking.day_start` / `booking.day_end` settings — these control the calendar grid display window, not booking availability.

#### 8.9.2 Client-Side Scheduler Modules

| File | Role |
| --- | --- |
| `scheduler-core.js` | Orchestrator. Owns `this.calendarModel`, `this.appointments`, `loadData()`, `loadCalendarModel()`, `loadAppointments()`. |
| `scheduler-day-view.js` | Day column rendering. Derives `this.businessHours` via `_resolveBusinessHours(config, calendarModel)`. |
| `scheduler-week-view.js` | Week grid rendering. |
| `scheduler-month-view.js` | Month grid rendering. |
| `time-grid-utils.js` | `getBusinessHours(config)` — extracts `startHour`/`endHour` from config or `calendarModel.businessHours`. Returns `{ startHour, endHour, startTime, endTime, hoursPerDay }`. |
| `settings-manager.js` | Bootstraps `window.appTimezone` from `/api/v1/settings/localization`. |
| `appointment-details-modal.js` | Renders the appointment detail modal (entry: `/appointments?open={hash}`). |

#### 8.9.3 Data Load Paths

**Server mode (default):**
- `loadData()` → `loadCalendarModel()` → `GET /api/calendar/{view}` → `calendarModel` set → appointments synced from `calendarModel.appointments` → `render(calendarModel)` → `DayView._resolveBusinessHours()` uses `calendarModel.businessHours`.

**Non-server mode (fallback):**
- `loadData()` → `loadAppointments()` → `GET /api/appointments` → flat array → `render(null)` → `DayView._resolveBusinessHours()` falls back to `config.businessHours` or `config.slotMinTime`/`config.slotMaxTime`, then hardcoded `'08:00'`/`'17:00'`.

#### 8.9.4 Calendar Grid Hours vs Booking Availability

These are two separate concerns:

| Concern | Source | Where used |
| --- | --- | --- |
| Calendar grid display window | `booking.day_start` / `booking.day_end` in `xs_settings` | `ProviderWorkingHoursTrait`, passed as `viewModel.businessHours` to JS |
| Booking availability bounds | `business.work_start` / `business.work_end` in `xs_settings` | `BusinessHoursService`, `AvailabilityService::constrainToBusinessHours()` |
| Provider working hours | `xs_business_hours` (per `provider_id` + `weekday`) | `AvailabilityService` slot generation, `BusinessHourModel` |

#### 8.9.5 Role Scoping (Automatic)

- Providers: `CalendarController` scopes to their own appointments only regardless of query params.
- Admins/Staff: see all appointments; can filter by `provider_id` query param.
- Do not rely on `provider_id` query param as the sole authorization mechanism — role-based scoping is enforced server-side.

#### 8.9.6 Datetime Parsing on the Client

All scheduler views parse API datetimes as UTC via Luxon:

```js
DateTime.fromISO(val, { zone: 'utc' }).setZone(window.appTimezone)
```

`window.appTimezone` is set by `SettingsManager` from `/api/v1/settings/localization`. Never parse appointment datetimes as local time.

## 9) Customers, Providers, and User Management

### 9.1 Customer vs User Domain Split

- xs_users: internal login users (admin/provider/staff/customer accounts)
- xs_customers: booking customers
- xs_appointments.customer_id links to xs_customers.id

### 9.2 Staff Scope Contract

Canonical source: ProviderStaffModel::getProvidersForStaff($userId, 'active')

When extracting provider IDs, use:

```php
array_column($rows, 'id')
```

Preferred appointment context key: provider_id

### 9.3 Provider Scope Contract

Use CustomerAppointmentService for provider/staff customer scoping:

- resolveCustomerIdsForProvider(int $providerUserId)
- resolveCustomerIdsForStaff(int $staffUserId)
- getProviderIdsForStaff(int $staffUserId)

### 9.4 User Management Multi-Role Contract

- Form model uses roles[] checkboxes
- Persist role membership to xs_user_roles
- Keep xs_users.role compatibility primary role
- User list and APIs must render/return authoritative roles[]

## 10) Public Booking Contract

### 10.1 Public Routes

- GET /booking
- GET /booking/{serviceSlug}
- GET /booking/{serviceSlug}/{providerSlug}
- GET /booking/legal
- GET /my-appointments/{hash}
- GET /r/{reference} — managed appointment reference short-link; resolves to `/my-appointments/{hash}` or `/booking`; built by `BookingLinkService::manageReferenceUrl()`

### 10.2 Public APIs

- GET /api/v1/public/services
- GET /api/v1/public/providers
- GET /api/v1/public/availability
- GET /api/v1/providers/slug/{segment}/services — fetch services by provider slug (used by public booking flow)
- POST booking submit route

### 10.3 Public Security Rules

- Never expose numeric customer IDs in URLs
- Use hash/token references only
- Enforce CSRF on form submit
- Keep slot queries scoped by provider and service

### 10.4 Public Booking Policy and Legal Rules

- `business.reschedule` and `business.cancel` are enforced server-side in `PublicBookingService`; the SPA may only reflect eligibility via `can_reschedule` / `can_cancel` flags and policy summaries.
- `business.future_limit` is a **public-channel-only** booking guard. Public booking calendar queries and public booking submission must respect it; admin/staff/API appointment creation does not inherit this limit by default.
- Public booking page context must expose `reschedulePolicy`, `cancelPolicy`, `futureLimitDays`, and a `legalPolicies` object for UI rendering.
- `legalPolicies` currently carries `cancellationPolicy`, `reschedulingPolicy`, `termsUrl`, `privacyUrl`, and `legalPageUrl`.
- The public booking UI must reuse the existing in-form policy surface (`renderSchedulingTips()` in `resources/js/public-booking.js`) instead of adding a detached sidebar/right panel unless explicitly requested.
- Short legal summaries belong in the booking flow; long-form legal text belongs on `/booking/legal`.
- The `Full legal policies` link from the booking flow opens `/booking/legal` in a new tab; the legal page itself must provide an in-page navigation affordance back to `/booking`.
- Avoid duplicate policy copy blocks in the form. If rules/legal are shown in `renderSchedulingTips()`, do not render a second standalone policy card with overlapping content.
- Any legal URL shown from booking context (`termsUrl`, `privacyUrl`) must open with `target="_blank" rel="noopener"`.

## 11) Notifications Contract

### 11.1 Canonical Services

- AppointmentEventService
- NotificationQueueService
- NotificationQueueDispatcher
- NotificationPolicyService
- NotificationEmailService
- NotificationSmsService
- NotificationWhatsAppService
- AppointmentNotificationService

### 11.2 Delivery Contract

Queue first, then dispatch, with idempotency and delivery logging preserved.

SMTP transport for notifications is owned by `MailerService`. See §7.3 for
the full transport resolution priority, from-address rules, `send()` response
contract, and dev fallback behavior. Do not document transport details here.

Business-ID scoping for all notification service methods that serve UI requests is owned by
`current_business_id()` in `permissions_helper`. See §7.4 for full resolution priority and
service usage pattern. Do not use `NotificationCatalog::BUSINESS_ID_DEFAULT` directly in
UI-facing service methods.

### 11.3 Dispatch Architecture Notes

- Booking-time notifications are queued synchronously inline during the appointment mutation request, but **dispatch is deferred** to the queue worker / cron (`php spark notifications:dispatch-queue`). Do not perform SMTP or channel delivery inline in the HTTP request path.
- New booking flows must derive event type from AppointmentStatus::notificationEvent() instead of hardcoding confirmation events.
- `PATCH /api/appointments/{id}/status` fires notifications server-side for each transition.
- Frontend events such as `appointments-updated` do not drive notification delivery; notifications are triggered by backend mutation flows.
- Manual resend flows use `POST /api/appointments/{id}/notify`. Email and SMS resends send immediately, while WhatsApp resends are queued.

### 11.3a HTTP Request Path Rule

- `AppointmentBookingService::queueNotifications()` must enqueue only. It must not instantiate `NotificationQueueDispatcher` for immediate delivery.
- Inline dispatch during booking/reschedule/cancel requests is a correctness and performance hazard: it blocks the HTTP response on SMTP/channel transport availability and turns transient transport failures into user-facing latency.
- If Mailpit/SMTP or any external transport is down, the correct behavior is: mutation succeeds, notification rows remain queued/failed for retry, and the cron/dispatcher handles recovery later.

### 11.4 Event and Template Rules

- appointment_pending is the canonical event for pending bookings.
- appointment_confirmed is the canonical event for confirmed bookings and completed appointments.
- appointment_reminder is cron-only and is not triggered by a status transition.
- booking.default_appointment_status is a notification control point because it determines whether new bookings emit pending or confirmed events.
- Customer and internal templates are split by recipient_class, and internal fallback templates exist for migration-drift safety.

### 11.5 Queue Record Contract

- Customer-facing rows are written through AppointmentEventService and NotificationQueueService::enqueueAppointmentEvent().
- Internal provider/staff rows are written through AppointmentBookingService::enqueueInternalNotifications() and NotificationQueueService::enqueueInternalEvent().
- Queue rows must preserve attempts/max_attempts retry semantics, run_after scheduling, and row-level locking through locked_at and lock_token.
- idempotency_key is the deduplication key; correlation_id is the tracing key shared with delivery logs.

Key queue fields:

- recipient_type
- recipient_user_id
- attempts
- max_attempts
- run_after
- locked_at
- lock_token
- last_error
- sent_at
- idempotency_key
- correlation_id
- reminder_offset_minutes (reminder rows only — offset that triggered this row; used for stale-reminder cancellation)
- schedule_fingerprint (SHA1 of `start_at|end_at|updated_at`; dispatcher cancels reminder rows whose fingerprint no longer matches the live appointment)

### 11.6 Architecture Flow

1. Appointment mutation determines event via AppointmentStatus::notificationEvent()
2. Queue rows written to xs_notification_queue
3. Dispatcher sends by channel
4. Attempt outcomes written to xs_notification_delivery_logs

### 11.7 Internal Recipient Contract

Internal notifications:

- recipient_type = internal
- recipient_user_id stores xs_users.id
- final contact resolution happens at dispatch time
- eligibility follows notify_on_appointments preference
- internal recipient selection is determined by UserModel::getNotifiableUsersForProvider()
- both admin user-management and self-service profile surfaces write the notify_on_appointments preference

### 11.8 Timezone and Delivery Prerequisites

- Notification rendering must resolve display timezone from xs_appointments.stored_timezone first.
- If stored_timezone is unavailable, fall back to TimezoneService::businessTimezone().
- Do not rely on implicit session timezone when rendering queued notifications.
- Production delivery requires enabled business notification rules and active business integrations with non-empty encrypted configuration.
- If required rule or integration configuration is missing, queue rows can be cancelled or failed during dispatch.

Dev-only SMTP fallback for email: see §7.3.2 for transport priority and §7.3.5
for the queue gate that activates the fallback. The fallback covers both
queue-enqueue checks and queue-dispatch checks transparently.

### 11.8a Reminder Offsets Behavior

#### 11.8a.1 Independent Offset Processing

**Each reminder offset triggers independently. No offset blocks another.**

Configuration (Settings → Notifications → Customer Reminder Offsets):
- Primary offset: e.g., 4320 minutes (3 days before)
- Secondary offset: e.g., 60 minutes (1 hour before)
- Offsets are stored as an array in `xs_business_notification_rules.reminder_offsets_json`

`NotificationQueueService::enqueueDueReminders()` processes each offset separately:

```
For each appointment where start_at in (now, +30 days]:
  For each channel (email, sms, whatsapp):
    For each offset in that channel's offset list:
      dueAt = start_at - offset_minutes
      If now >= dueAt → enqueue a row with marker 'offset:N'
      Else → skip (not yet due)
```

There is no cross-offset dependency. An offset that is already past-due does not affect a future offset that has not yet arrived.

#### 11.8a.2 Appointment Booked 1 Day in Advance — Concrete Example

Config: `[4320 min (3 days), 60 min (1 hour)]`
Booking time: today 14:00 UTC. Appointment: tomorrow 14:00 UTC.

| Offset | dueAt | now >= dueAt | Result |
|--------|-------|-------------|--------|
| 4320 min (3 days) | 3 days ago | ✅ TRUE | Enqueued immediately (catch-up) |
| 60 min (1 hour) | tomorrow 13:00 | ❌ FALSE | Skipped — enqueued when tomorrow 13:00 arrives |

Both reminders will send. The first being past-due at booking time does not block the second.

#### 11.8a.3 Queue Row Identity

Each offset creates its own queue row with:
- `reminder_offset_minutes` — the specific offset value that triggered this row
- `idempotency_key` — includes marker `'offset:N'` so the same offset for the same appointment is never double-enqueued
- `schedule_fingerprint` — SHA1(`start_at|end_at|updated_at`); dispatcher cancels a reminder row if the fingerprint no longer matches the live appointment (i.e., appointment was rescheduled after enqueue)

#### 11.8a.4 reminder_sent Flag — Compatibility Only

After a **customer** reminder dispatches successfully, `reminder_sent = 1` is set on the appointment row via `NotificationQueueDispatcher::markReminderSentIfSupported()`.

**This flag is NOT checked as an enqueue-time filter.** `enqueueDueReminders()` does not skip appointments with `reminder_sent = 1`. The flag is for display purposes only.

> **Known hazard (fixed 2026-04-23, commit e5bf2e7):** Previously, the flag write used `model->update(['reminder_sent' => 1])`, which also updated `updated_at`. That changed the schedule fingerprint mid-dispatch, causing sibling reminder rows for the same appointment to be cancelled as stale. The fix writes `reminder_sent` via query builder `set()` without touching `updated_at`.

#### 11.8a.5 Debugging Reminder Offsets

```bash
# Force an appointment into a reminder-due window, then enqueue + dispatch
php spark notifications:test-reminder <appointmentId> [businessId] [minutesUntilStart]

# Example: set appointment to start 45 min from now
php spark notifications:test-reminder 96 1 45
```

Output shows per-offset queue rows with their `reminder_offset_minutes`, `status` (`sent` / `queued` / `cancelled`), and `schedule_fingerprint`. A healthy run shows one row per offset per recipient, all with `status=sent` and `Cancelled: 0`.

### 11.9 Template Contract

#### 11.9.1 Recipient Classes

- `customer` — outbound to the person who booked. Resolved via settings-based custom templates, then `DEFAULT_TEMPLATES` in code.
- `internal` — outbound to providers/staff. Resolved via `xs_message_templates` rows seeded by migration, then `DEFAULT_INTERNAL_TEMPLATES` in code.

#### 11.9.2 Template Loading Priority (customer class)

1. `xs_settings` row with key `notification_template.{event_type}.{channel}` (JSON value `{"subject":"...","body":"..."}`)
2. `NotificationTemplateService::DEFAULT_TEMPLATES` (code-level fallback)

Settings-based templates are upserted by migrations and can be overridden at runtime without code deploys.

#### 11.9.3 Template Loading Priority (internal class)

1. `xs_message_templates` row where `recipient_class = 'internal'`, `is_active = 1`
2. `NotificationTemplateService::DEFAULT_INTERNAL_TEMPLATES` (code-level fallback)

#### 11.9.4 Supported Placeholder Set (34 total)

**Customer info:** `{customer_name}`, `{customer_first_name}`, `{customer_email}`, `{customer_phone}`

**Appointment info:** `{service_name}`, `{service_duration}`, `{provider_name}`, `{appointment_date}`, `{appointment_time}`, `{appointment_datetime}`

**Business info:** `{business_name}`, `{business_email}`, `{business_phone}`, `{business_address}`

**Legal content:** `{cancellation_policy}`, `{rescheduling_policy}`, `{terms_link}`, `{privacy_link}`

**Links:** `{reschedule_link}`, `{booking_url}`, `{booking_id}`, `{internal_view_link}`, `{internal_edit_link}`, `{internal_contact_link}`, `{booked_via}`, `{booked_timestamp}`

**Location:** `{location_name}`, `{location_address}`, `{location_contact}`

**Navigation / calendar:** `{booking_reference}`, `{calendar_link}`, `{google_maps_link}`, `{waze_link}`

#### 11.9.5 Business Contact Resolution

`{business_email}` → `general.company_email` setting via `legalContent`
`{business_phone}` → `general.telephone_number`, then `general.mobile_number`, with `general.company_phone` as legacy fallback via `legalContent`
`{business_name}` → `general.business_name` setting via `legalContent`
`{business_address}` → `general.business_address` setting via `legalContent`

#### 11.9.6 Location and Map Link Resolution

`{location_address}` — primary value is `xs_appointments.location_address`. If empty, falls back to `general.business_address` setting.
`{google_maps_link}` and `{waze_link}` — generated from the resolved `{location_name} + {location_address}` string. Empty when no address is resolvable.
`{calendar_link}` — Google Calendar add-event URL built from `start_datetime`, service duration, resolved location, and `{booking_reference}`.

#### 11.9.7 Booking Reference Format

`WS-{year}-{id_zero_padded_4}` — e.g., `WS-2026-0042`. Sourced from `booking_id` or `appointment_id` in render data.

#### 11.9.8 Customer Email Contact Placement (V4)

All 5 customer email bodies place the enquiries line inside the appointment details block:

```
☎ Enquiries: {business_email} | Tel: {business_phone}
```

The closing footer now ends with:

```
{business_name}
{terms_link} | {privacy_link}
```

Do not alter this customer email layout without creating a new migration to upsert updated settings rows.

#### 11.9.9 Required Placeholders

`{reschedule_link}` is required in `email`, `sms`, and `whatsapp` bodies for `appointment_pending` and `appointment_confirmed`. `render()` auto-appends a fallback block if the placeholder is missing from a stored template.

## 12) Database Schema and Relationships

### 12.1 Runtime DB Conventions

- Prefix: xs_
- Runtime DB: MySQL/MariaDB only
- SQLite is not supported runtime DB
- Runtime schema is authoritative
- Preferred charset/collation for installs: utf8mb4 / utf8mb4_unicode_ci

### 12.2 Total Runtime Tables

Total application tables: 22

### 12.3 Table Catalog by Domain

#### Identity Tables

##### xs_users
Columns:
- id
- name
- title (nullable, added 2026-04-23)
- email
- phone
- password_hash
- role
- created_at
- updated_at
- color
- reset_token
- reset_expires
- status
- profile_image
- bio (text, nullable, added 2026-04-23)
- education (text, nullable, added 2026-04-23)
- qualifications (text, nullable, added 2026-04-23)
- slug (unique, nullable, added 2026-04-23)
- last_login
- notify_on_appointments

Notes:
- status is canonical active-state field
- notify_on_appointments controls provider/staff appointment notices
- slug is unique per provider; used in public booking URL `/booking/{serviceSlug}/{providerSlug}`
- title, bio, education, qualifications are public profile fields for provider profile pages

##### xs_user_roles
Columns:
- id
- user_id
- role
- created_at

Notes:
- Authoritative role membership table
- Backfilled from xs_users.role in migration 2026-04-08-000001_CreateUserRolesTable.php

##### xs_customers
Columns:
- id
- hash
- first_name
- last_name
- email
- phone
- address
- notes
- created_at
- updated_at
- custom_fields

Notes:
- hash is 64-char unique slug for public routes
- index idx_customers_hash is present
- custom_fields stores JSON map in text field (legacy; per-appointment custom fields now also stored in xs_appointment_custom_fields)
- email has unique index idx_customers_email_unique (added 2026-04-30)

#### Scheduling Tables

##### xs_services
Columns:
- id
- name
- slug (unique, nullable, added 2026-04-23)
- description
- duration_min
- buffer_before
- buffer_after
- price
- created_at
- updated_at
- category_id
- active

Notes:
- slug is used in public booking URL `/booking/{serviceSlug}` and `/booking/{serviceSlug}/{providerSlug}`

##### xs_categories
Columns:
- id
- name
- description
- color
- created_at
- updated_at
- active

##### xs_appointments
Columns:
- id
- provider_id
- service_id
- start_at
- end_at
- stored_timezone
- status
- notes
- hash
- public_token
- public_token_expires_at
- created_at
- updated_at
- reminder_sent
- customer_id
- location_id
- location_name
- location_address
- location_contact

Notes:
- customer linkage is customer_id -> xs_customers.id
- do not use deprecated appointment user_id linkage in new logic

##### xs_blocked_times
Columns:
- id
- provider_id
- start_at
- end_at
- reason
- created_at
- updated_at

#### Availability and Location Tables

##### xs_business_hours
Columns:
- id
- provider_id
- weekday
- start_time
- end_time
- breaks_json
- created_at
- updated_at

Notes:
- runtime uses weekday, not day_of_week

##### xs_provider_schedules
Columns:
- id
- provider_id
- day_of_week
- start_time
- end_time
- break_start
- break_end
- is_active
- created_at
- updated_at

Notes:
- runtime does not include location_id

##### xs_locations
Columns:
- id
- provider_id
- name
- address
- city (nullable, added 2026-04-23)
- area (nullable, added 2026-04-23)
- contact_number
- is_primary
- is_active
- created_at
- updated_at

##### xs_location_days
Columns:
- id
- location_id
- day_of_week

Notes:
- runtime currently exposes only these 3 columns
- treat as schema-incomplete for location-hours redesign work

##### xs_providers_services
Columns:
- provider_id
- service_id
- created_at

##### xs_provider_staff_assignments
Columns:
- id
- provider_id
- staff_id
- assigned_at
- assigned_by
- status

#### Settings Tables

##### xs_settings
Columns:
- id
- setting_key
- setting_value
- setting_type
- created_at
- updated_at

Notes:
- typed key-value store
- key namespaces: general.*, localization.*, booking.*, calendar.*, notifications.*, branding.*, security.*
- runtime may omit updated_by in some environments

#### Notification Tables

##### xs_business_notification_rules
Columns:
- id
- business_id
- event_type
- channel
- is_enabled
- reminder_offset_minutes
- created_at
- updated_at

##### xs_business_integrations
Columns:
- id
- business_id
- channel
- provider_name
- encrypted_config
- is_active
- health_status
- last_tested_at
- created_at
- updated_at

##### xs_message_templates
Columns:
- id
- business_id
- event_type
- channel
- provider
- provider_template_id
- locale
- recipient_class
- subject
- body
- is_active
- created_at
- updated_at

Notes:
- recipient_class separates customer vs internal templates

##### xs_notification_queue
Columns:
- id
- business_id
- channel
- event_type
- appointment_id
- recipient_type
- recipient_user_id
- status
- attempts
- max_attempts
- run_after
- locked_at
- lock_token
- last_error
- sent_at
- idempotency_key
- correlation_id
- created_at
- updated_at
- reminder_offset_minutes
- schedule_fingerprint

Notes:
- uses attempts/max_attempts (not legacy attempt_count)
- includes locked_at, lock_token, correlation_id
- internal rows resolve recipient from xs_users at dispatch
- reminder rows include reminder_offset_minutes and schedule_fingerprint (added 2026-04-21 migration)
- dispatcher cancels a reminder row if schedule_fingerprint no longer matches the live appointment

##### xs_notification_delivery_logs
Columns:
- id
- business_id
- queue_id
- correlation_id
- channel
- event_type
- appointment_id
- recipient
- provider
- status
- attempt
- error_message
- created_at
- updated_at

##### xs_notification_opt_outs
Columns:
- id
- business_id
- channel
- recipient
- reason
- created_at
- updated_at

#### Appointment Custom Fields Table

##### xs_appointment_custom_fields
Columns:
- id
- appointment_id
- field_key
- value (text, nullable)
- created_at
- updated_at

Notes:
- Added 2026-04-30 (`CreateAppointmentCustomFieldsTable` migration)
- Unique index on (appointment_id, field_key) — idx_appt_custom_field_unique
- FK: appointment_id -> xs_appointments.id ON DELETE CASCADE
- Stores per-appointment custom field values; replaces reliance on xs_customers.custom_fields for appointment-scoped data
- Backfilled from legacy customer custom fields on migration
- Model: `App\Models\AppointmentCustomFieldModel`

#### Audit Table

##### xs_audit_logs
Columns:
- id
- user_id
- action
- target_type
- target_id
- old_value
- new_value
- ip_address
- user_agent
- created_at

Notes:
- verify table name as xs_audit_logs in mixed environments

### 12.4 Canonical Relationships

- xs_appointments.customer_id -> xs_customers.id
- xs_appointments.provider_id -> xs_users.id
- xs_appointments.service_id -> xs_services.id
- xs_appointments.location_id -> xs_locations.id
- xs_appointment_custom_fields.appointment_id -> xs_appointments.id (CASCADE)
- xs_business_hours.provider_id -> xs_users.id
- xs_provider_schedules.provider_id -> xs_users.id
- xs_locations.provider_id -> xs_users.id
- xs_location_days.location_id -> xs_locations.id
- xs_providers_services.provider_id -> xs_users.id
- xs_providers_services.service_id -> xs_services.id
- xs_provider_staff_assignments.provider_id -> xs_users.id
- xs_provider_staff_assignments.staff_id -> xs_users.id
- xs_user_roles.user_id -> xs_users.id
- xs_notification_queue.appointment_id -> xs_appointments.id
- xs_notification_delivery_logs.queue_id -> xs_notification_queue.id
- xs_audit_logs.user_id -> xs_users.id

### 12.5 Compatibility Rules

- Do not write new logic against deprecated appointment user_id linkage.
- Keep scheduling logic on start_at/end_at.
- Use schema-safe fallbacks where runtime columns vary.
- xs_business_hours uses weekday (not day_of_week) in this runtime.
- xs_customers.hash and xs_customers.custom_fields were restored and backfilled (2026-04-12).
- xs_customers.email has a unique index (added 2026-04-30); enforce uniqueness on customer create/update.
- xs_provider_schedules does not include location_id in this runtime.
- xs_location_days is currently a minimal 3-column table.
- xs_notification_queue uses modern locking/idempotency columns; do not assume legacy queue columns.
- xs_business_hours has NO global-only rows. Every row has a `provider_id`. Do not query this table without a `provider_id` filter expecting a global business hour result.
- Global business hours (system-wide outer bounds) live in `xs_settings` keys `business.work_start` and `business.work_end`, NOT in `xs_business_hours`.
- xs_appointment_custom_fields is the normalized store for per-appointment custom field values (added 2026-04-30). xs_customers.custom_fields remains as customer-level storage.

### 12.6 Timezone Integrity Rules (Single Source of Truth)

**Rule:** All datetime values stored in `xs_*` tables are UTC. Convert to local only at display time.

**Single source of truth:** `localization.timezone` in `xs_settings` (read via `LocalizationSettingsService::getTimezone()`).

**Canonical PHP service:** `TimezoneService` — use these methods and no others for datetime conversion:
- `TimezoneService::toDisplay($utcString, $tz)` — UTC → display timezone string (`Y-m-d H:i:s`)
- `TimezoneService::toStorage($localString, $tz)` — local → UTC for DB writes
- `TimezoneService::businessTimezone()` — reads `localization.timezone`, cached per-request

**Never do:**
- `new \DateTime($localString)` without a `\DateTimeZone` argument when the string is in a non-UTC timezone
- Pass `start_at` (UTC) directly to template rendering; always convert via `toDisplay()` first
- Use `date()` / `new \DateTime()` without explicit timezone when building notification content

**Notification pipeline contract:**
- `NotificationQueueDispatcher` converts `start_at` (UTC) → `start_datetime` (display TZ local string) before calling template service
- `NotificationQueueDispatcher` passes `display_timezone` key in all `$templateData` arrays
- `NotificationTemplateService::buildPlaceholders()` creates `new \DateTime($data['start_datetime'], new \DateTimeZone($data['display_timezone'] ?? 'UTC'))` — always explicit timezone
- Google Calendar links require UTC: convert `start_datetime` from display TZ → UTC before formatting with `\Z`

**JS contract:**
- `window.appTimezone` is set by `SettingsManager` from `/api/v1/settings/localization`
- All scheduler views (SchedulerCore, DayView) parse API datetimes as UTC via Luxon: `DateTime.fromISO(val, {zone:'utc'}).setZone(appTimezone)`
- Public booking JS uses `context.timezone` (from `PublicBookingService::buildViewContext()`) — do not omit this key
- `X-Client-Timezone` / `client_timezone` are browser hints only; `localization.timezone` always takes priority

**AvailabilityService contract:**
- `isSlotAvailable()` `$timezone` parameter is `?string` with `null` resolving via `TimezoneService::businessTimezone()`
- Always pass the correct business/booking timezone explicitly; do not rely on the default

## 13) Migration and Schema-Drift Rules

### 13.1 Migration Base Requirement

All app migrations must extend App\Database\MigrationBase.

### 13.2 Migration Run Command

```bash
php spark migrate -n App
```

### 13.3 Schema-Drift Safety Rules

- Validate runtime columns before making assumptions in mixed environments.
- Keep model/service fallback patterns when optional columns may be absent.
- Avoid hard assumptions on legacy or removed columns.

## 14) Mandatory Quality Gates (Anti-Duplication and Orphan Prevention)

This section is mandatory for PRs that touch behavior, contracts, schema, or architecture docs.

### 14.1 Contract Ownership Matrix

Each high-risk contract has one owner section in this file.

| Contract | Owner Section | Reference-Only Sections |
| --- | --- | --- |
| Avatar initials and image rendering | 6.8) Avatar System Contract | 7) (ProfilePageService), 14) |
| RBAC role resolution | 4) Authentication and Authorization Contract | 5), 9), 14) |
| Session write merge rule | 4.5) Session Write Contract | 9), 14) |
| API envelope and errors | 3.4) API Envelope Contract | 5), 6) |
| SPA lifecycle/init | 6) Frontend Contract | 5), 8) |
| Scheduler mutation reload semantics | 8.5) and 8.6) | 6), 14) |
| Status -> notification event mapping | 8.3) | 11) |
| Queue-first notification flow | 11) Notifications Contract | 8), 12) |
| Public hash/token URL safety | 10) Public Booking Contract | 12), 14) |
| Database schema and compatibility | 12) Database Schema and Relationships | 8), 9), 10), 11), 13) |
| Migration base requirement | 13.1) Migration Base Requirement | 3), 12), 14) |
| Unified email transport | 7.3) Unified Email Transport Contract | 11.2), 11.8) |
| Business hours architecture | 8.7) Business Hours Architecture | 8.8), 12), 14) |
| Availability and slot generation | 8.8) Availability and Slot Generation Pipeline | 8.7), 10), 12) |
| Calendar data flow and grid bounds | 8.9) Calendar Architecture and Data Flow | 6), 8.5), 14) |

Rule: Do not duplicate full contract text in non-owner sections. Use reference links and concise reminders only.

### 14.2 Orphan Section Gate

Fail if any section is placeholder-only or TODO-only without ownership reference.

Pass condition:

- each section contains either:
  - substantive contract content, or
  - explicit pointer to the owner section and rationale

### 14.3 Variable and Function Collision Gate

Avoid double variables/functions and ambiguous naming.

Required checks:

1. Role variables
- Use roles for authoritative arrays.
- Use role only as compatibility fallback.

2. Provider scope keys
- provider_id: single provider context
- provider_ids: array filter context only

3. Scheduler loaders
- Use loadData() for canonical mutation-safe reload behavior.
- Do not replace with loadAppointments() in server mode mutation paths.

4. Notification IDs
- id: queue row primary key
- idempotency_key: dedup semantics
- correlation_id: trace correlation across related attempts/logs

### 14.4 Mandatory Pre-Merge Checks

Run these checks before merge:

```bash
# 1) Detect accidental single-role auth checks
rg "\$user\['role'\]\s*===|\$currentUser\['role'\]\s*==" app/ resources/

# 2) Detect direct provider-role SQL assumptions
rg "role\s*=\s*'provider'|WHERE\s+role\s*=\s*'provider'" app/

# 3) Detect direct session user overwrites without merge
rg "session\(\)->set\('user',\s*\[" app/

# 4) Detect inline style regressions in views
rg "style=\"|<style>" app/Views resources/js

# 5) Detect deprecated appointment linkage usage
rg "appointments\.user_id|\buser_id\b" app/ resources/

# 6) Detect unfiltered xs_business_hours queries (must always have provider_id filter)
rg "table\('business_hours'\)|from.*xs_business_hours" app/

# 7) Detect SettingModel::getValue() calls (method does not exist; use getByKeys instead)
rg "->getValue\(" app/Services app/Controllers app/Models
```

Any result must be reviewed and justified or fixed.

### 14.5 Duplication Gate for Docs

When updating this file:

- Update only the contract owner section for full text changes.
- In other sections, keep only short references.
- Preserve one-source wording for critical contracts.

## 15) Testing, Operations, and Deployment

### 15.1 Testing Rules

- Use PHPUnit.
- Integration tests must run against test DB config only.
- Never run tests against default/live schema.

### 15.2 Core Runtime Commands

```bash
php spark serve
php spark migrate -n App
php spark notifications:dispatch-queue          # enqueue due reminders + dispatch queue
php spark notifications:test-reminder <id> [businessId] [minutesUntilStart]
php spark notifications:backfill-templates      # backfill missing notification templates
php spark notifications:purge-delivery-logs     # purge old delivery log rows
php spark notifications:export-delivery-logs    # export delivery logs
php spark notifications:repair-business-id      # repair business_id on queue rows
php spark audit:provider-assignments            # audit provider-service-location mappings
php spark audit:reminder-pipeline               # audit reminder scheduling pipeline
php spark audit:purge-logs                      # purge old audit log rows
npm run dev
npm run build
```

## 16) Known Debt and Guardrails

### 16.1 Active Debt Themes

- Scheduler loading-path discipline must remain strict.
- Frontend large-module decomposition is active and tracked in Appendix 17.3 (items 14-19).
- Schema-compatibility in mixed local DB states must remain guarded.
- `ProviderWorkingHoursTrait::getBusinessHours()` calls `$settings->getValue()` which does not exist on `SettingModel`. Fix: replace with `getByKeys(['booking.day_start','booking.day_end'])` (see §8.7.4).
- Integration test DB has a migration sequence gap near `2025-10-22-174832`; `BusinessHoursServiceIntegrationTest` and some journey tests cannot run against the test DB until the gap is repaired.
- Calendar selector unit tests are now runner-aligned via `npm run test:unit:calendar` (Vitest).
- `@material/web` package is still in `node_modules` / `package-lock.json` but is unused. Can be removed with `npm uninstall @material/web` when ready to clean up.

### 16.2 Forbidden Patterns (Condensed)

- Business logic in controllers
- Scheduling rules implemented only in views/JS
- Migrations extending framework Migration instead of MigrationBase
- Numeric customer IDs in public URLs
- UTC/local time mixing in persisted datetimes
- New authorization logic based on role alone
- Mid-session session user overwrite without array_merge
- Inline style attributes in app-facing templates

## 17) Appendices

### 17.1 Traceability Note

This v2 contract consolidates the mapped content from Agent_Context_Restructured.md and restructure_diff.md into a single owner-based architecture document.

### 17.2 Update Protocol for This File

When behavior changes:

1. Update the owner section first.
2. Update impacted references in non-owner sections.
3. Re-run mandatory quality gates in Section 14.
4. If schema changed, update Section 12 and Section 13 together.
5. If RBAC/session contracts changed, update Section 4 and rerun role/session grep checks.

### 17.3 Execution Log: Items 14-19

Status legend: `done`, `in_progress`, `queued`.

| Item | Scope | Status | Notes |
| --- | --- | --- | --- |
| 14 | `public-booking.js` decomposition | done | State/constants extracted to `resources/js/modules/public-booking/state.js`. |
| 15 | `scheduler-core.js` decomposition | done | Calendar model URL building extracted to `resources/js/modules/scheduler/calendar-model-url.js`. |
| 16 | Selector test runner alignment | done | Added `test:unit:calendar` script and Vitest dependency in `package.json`. |
| 17 | `scheduler-day-view.js` decomposition | done | View constants/status metadata extracted to `scheduler-day-view-config.js`. |
| 18 | `scheduler-week-view.js` decomposition | done | Day grid rendering extracted to `week-view-day-grid.js`. |
| 19 | `app.js` decomposition | done | Shared UI helpers extracted to `resources/js/modules/app/shared-ui.js`. |

### 17.4 Recent Behavior Hardening Log

Status legend: `done`, `in_progress`, `queued`.

| Item | Scope | Status | Notes |
| --- | --- | --- | --- |
| 20 | `/profile` live view hardening | done | Added `ProfilePageService`, replaced placeholder profile cards/activity with authoritative data, moved tab/avatar behavior to `resources/js/modules/profile/profile-page.js`, and covered the flow with `tests/integration/ProfileJourneyTest.php`. |
| 21 | Appointment customer-search payload contract | done | Appointment-form search now accepts parsed JSON payloads from `resources/js/core/api.js`, and shared `extractJSON()` accepts object payloads so sibling search surfaces do not fail on `.match()` calls. |
| 22 | Business context resolver for notification services | done | Added `current_business_id()` to `permissions_helper.php`. `NotificationCenterService` and `NotificationSettingsService` now resolve scope via `resolveBusinessId()` instead of `NotificationCatalog::BUSINESS_ID_DEFAULT`. Full resolver contract documented in §7.4. |
| 23 | Reminder queue metadata and stale-reminder cancellation | done | Added `reminder_offset_minutes` and `schedule_fingerprint` columns to `xs_notification_queue` (migration `2026-04-21-150000`). `NotificationQueueService` writes fingerprint on enqueue; `NotificationQueueDispatcher` cancels reminder rows whose fingerprint no longer matches the live appointment, and resolves internal recipients via `resolveInternalRecipientUser()`. |
| 24 | Unified avatar / initials system | done | Single PHP source of truth (`avatar_initials()`, `avatar_display_name()`, `avatar_profile_image_url()`, `avatar_data()`) in `app/Helpers/app_helper.php`. Single JS source of truth in `resources/js/utils/avatar.js` (`getAvatarInitials`, `getDisplayName`). Globals `window.xsGetAvatarInitials` / `window.xsGetDisplayName` exposed from `app.js` for inline PHP-view scripts. All avatar surfaces updated (header, user list, customer list, customer history, appointments form, provider-staff widget, staff-providers widget, scheduler). Title/suffix stripping and two-letter fallback enforced consistently. Parity tests: `tests/frontend/avatar-utils.test.js` (6 tests) and `tests/unit/Helpers/AvatarHelperTest.php` (3 tests, 12 assertions). Full contract in §6.8. |
| 25 | Appointment inline notification dispatch regression fix | done | Restored immediate queue dispatch in `AppointmentBookingService::queueNotifications()` via `NotificationQueueDispatcher::dispatch(NotificationCatalog::BUSINESS_ID_DEFAULT)` so create/update flows no longer depend on cron timing for immediate notifications. Added queue idempotency pre-check hardening in `NotificationQueueService::enqueueRaw()` to avoid duplicate enqueue attempts under retries. |
| 26 | Setup hosting compatibility mode + resilience hardening | done | Added shared-hosting-safe DB probe path (`app/Helpers/SetupCompatibilityHelper.php`) and compatibility UI module (`resources/js/setup-compat.js`) with smart suggestion banner. Setup controller now accepts JSON `testConnection` requests, forwards `compatibility_mode`, logs raw DB probe errors server-side, and returns sanitized payloads. Added camelCase route alias `setup/testConnection`, persisted `compatibility_mode` through setup processing and `.env` generation, and fixed setup-page CSRF header value (`csrf_hash()` instead of `csrf_token()`) plus robust client-side non-JSON/exception response handling so host-level 403/HTML responses no longer surface as `undefined` or misleading network errors. |
| 27 | Reminder enqueue idempotency — allow re-enqueue after cancellation/failure | done | `NotificationQueueService::enqueueRaw()` previously blocked re-enqueue for any existing row regardless of status. Fixed: rows with status `cancelled` or `failed` are now deleted before a fresh row is inserted, so reminders that previously cancelled (e.g. fingerprint mismatch) or failed are retried on the next cron run. In-flight (`queued`, `processing`) and delivered (`sent`) rows still deduplicate normally. |
| 28 | Reminder scan window — 48 h lookback for missed cron runs | done | `NotificationQueueService::enqueueDueReminders()` filtered `start_at > now` which silently excluded all appointments with a reminder window that opened while cron was stopped. Fixed: scan now starts at `now - 48 hours` (`start_at >=`), allowing catch-up delivery of overdue reminders. Idempotency keys prevent double-sending. Added per-channel `log_message('info',...)` diagnostics for each skip reason (no offsets / rule disabled / integration inactive) and a `log_message('warning',...)` when the enabled-channel list is empty. |
| 29 | Provider public profile schema | done | Added `title`, `bio`, `education`, `qualifications`, `slug` columns to `xs_users` (migration `2026-04-23-120000`). Slugs backfilled from provider names and unique-indexed (`idx_xs_users_slug_unique`). Added `slug` to `xs_services` (`2026-04-23-120100`, `idx_xs_services_slug_unique`). Added `city`, `area` to `xs_locations` (`2026-04-23-120200`). New API route `GET /api/v1/providers/slug/{segment}/services` added. Schema documented in §12.3. |
| 30 | Appointment custom fields table | done | Added `xs_appointment_custom_fields` table (migration `2026-04-30-140000`) with `appointment_id` FK (CASCADE), `field_key`, `value`. Unique index on (appointment_id, field_key). Backfilled legacy customer custom fields from appointment table. Model: `AppointmentCustomFieldModel`. Table count updated to 22. Schema documented in §12.3. |
| 31 | Unique email constraint on xs_customers | done | Added unique index `idx_customers_email_unique` on `xs_customers.email` (migration `2026-04-30-120000`). Enforce uniqueness on customer create/update. Documented in §12.5. |
| 32 | Booking reference short-link `/r/{reference}` | done | Added route `GET /r/{reference}` → `PublicSite\BookingController::reference` (with `setup` + `public_rate_limit` filters). `BookingLinkService::manageReferenceUrl()` generates these branded short links from appointment hash or public_token. Used in notification emails and customer-facing links. Documented in §10.1. |
| 33 | Dark mode to `darkMode: 'class'` + SCSS `.dark` | done | `tailwind.config.js` changed from `['selector', '[data-theme="dark"]']` to `'class'`. SCSS `_custom-properties.scss` uses `.dark { }` block (not `[data-theme="dark"]`). `theme-bootstrap.js` (new inline FOUC-prevention script) and `dark-mode.js` both set `.dark` class on `<html>`. Full contract in §6.4. |
| 34 | Vite entry point cleanup — remove material-web | done | `resources/js/material-web.js` deleted and removed from `vite.config.js` inputs. The `@material/web` bundle (485 kB) was dead — zero `<md-*>` elements in production views. New entries added: `theme-bootstrap`, `app-layout-init`, `public-booking-bootstrap`. Full entry point list in §6.1. |
| 35 | Appointments toolbar — mobile two-rail layout | done | Replaced single `overflow-x-auto` scrolling toolbar row with a two-column side-by-side layout: Rail A (`flex-col` on mobile, two sub-rows: Today+view-switcher and date cluster) + Rail B (stats chips stack `flex-col` on mobile). Outer wrapper uses `items-start` for left-alignment. Mobile filter button removed from Rail B; filter is desktop-only. Full contract in §6.9.5. |
| 36 | Services section — full layout/design-system migration | done | Migrated all five services-section views from `layouts/dashboard` to `layouts/app` with `xs-card` design system: (1) `services/index.php` — inline stats summary bar, context-aware CTA, `xs-card` with tab switcher (Services/Categories), collapsible filter panel (tune icon), 5-col services table and 4-col categories table, all row actions as `xs-actions-container` icon-only buttons, AJAX quick-add form and `scripts` JS block removed entirely; (2) `services/categories.php` — `layouts/app`, back link in content, `xs-card` table, icon-only actions, raw flash markup removed; (3) `services/create.php` — footer buttons replaced with `view('components/button', ...)`, Tips sidebar changed from `card card-spacious` to `xs-card`; (4) `services/edit.php` — same footer + sidebar fix; (5) `categories/form.php` — migrated to `layouts/app`, form wrapped in `xs-card`. Full design system contract in §6.9. |

## 18) Audit Progress Board

### 18.1 Verified in Current Pass

- Mandatory quality gates (14.4) were rerun after tranche updates.
- Targeted decomposition items 14-19 are now implemented and linked in 17.3.
- `/profile` now renders authoritative account data and audit activity through `ProfilePageService`, with SPA-safe tab/image behavior and integration coverage recorded in 17.4 item 20.
- Appointment create customer search now respects the shared parsed-payload contract from `apiRequest()`, with the hardening recorded in 17.4 item 21.
- Frontend test + build validation is required after each decomposition tranche.
- `current_business_id()` helper added to `permissions_helper.php`; `NotificationCenterService` and `NotificationSettingsService` switched off hardcoded constant — business context resolver documented in §7.4 (17.4 item 22).
- Reminder queue metadata (`reminder_offset_minutes`, `schedule_fingerprint`) added to `xs_notification_queue`; stale-reminder cancellation logic in `NotificationQueueDispatcher` — schema updated in §12.3, queue fields in §11.5 (17.4 item 23).
- Two new resolver-focused unit tests passing: `testResolveBusinessIdUsesSessionBusinessContext` (NotificationCenterServiceTest) and `testNotificationSettingsServiceResolvesBusinessIdFromSessionContext` (SettingsBoundaryServicesTest).
- Unified avatar/initials system implemented across all PHP views and JS modules; single PHP helper and single JS module replace all ad-hoc initials logic. Title/suffix stripping and two-letter fallback enforced consistently. Parity tests added for both PHP and JS layers. Full contract in §6.8 (17.4 item 24).
- Appointments toolbar mobile two-rail layout implemented: side-by-side Rail A + Rail B with Rail A using two sub-rows on mobile (Today+switcher / date cluster). Left-aligned via `items-start`. Rail B stats chips stack vertically on mobile. Mobile filter button removed. Contract in §6.9.5 (17.4 item 35).
- Services section fully migrated from `layouts/dashboard` to `layouts/app` design system: all five views updated (`services/index.php`, `services/categories.php`, `services/create.php`, `services/edit.php`, `categories/form.php`). AJAX quick-add form and inline JS block removed. All row actions use `xs-actions-container` icon-only pattern. View layout and design system contract documented in §6.9 (17.4 item 36).

### 18.2 Remaining Debt Outside 14-19

- `ProviderWorkingHoursTrait::getBusinessHours()` still requires `SettingModel::getByKeys()` migration.
- Test DB migration-sequence gap near `2025-10-22-174832` still blocks part of integration suite.
- `testSettingsPageServiceBuildsDefaultAdminContextWhenSessionUserMissing` (SettingsBoundaryServicesTest) fails because the mock expectation `expects($this->once())` on `getByKeys` is violated when `LocalizationSettingsService` makes a second internal call. Fix: change expectation to `expects($this->atLeastOnce())` or mock `LocalizationSettingsService` separately. Pre-existing; not introduced in this session.
- `@material/web` package still in `package-lock.json` and `node_modules`; not yet uninstalled from `package.json`. Zero production impact (no imports). Can be removed with `npm uninstall @material/web`.

## 19) Next Hardening Queue

### 19.1 Immediate Follow-up Work

1. Split `scheduler-today-view.js` into render/data helpers while preserving existing event contracts.
2. Continue reducing inline script usage in non-target legacy views (module-bootstrapped parity).
3. Add CI coverage for `npm run test:unit:calendar` in frontend validation workflow.
4. Fix pre-existing mock expectation count failure in `SettingsBoundaryServicesTest::testSettingsPageServiceBuildsDefaultAdminContextWhenSessionUserMissing` (see §18.2).
5. Add an admin-visible business selector / `?business_id=` query-param handoff to the Notifications and Settings pages — `current_business_id()` already reads `?business_id=` from GET params, so no PHP changes are needed; only a UI affordance is required.
6. Run `npm uninstall @material/web` to remove unused 485 kB package from `package.json` and `package-lock.json`.
7. Fix `ProviderWorkingHoursTrait::getBusinessHours()` — replace `$settings->getValue()` with `SettingModel::getByKeys(['booking.day_start','booking.day_end'])` (see §8.7.4 and §16.1).

### 19.2 Verification Rule

Each queue item in 19.1 must end with:

- `npm run test:frontend:lifecycle`
- `npm run test:unit:calendar`
- `npm run build`

## Custom Field Required Semantics (Apr 2026)

### Rule: Required fields are satisfied by stored values

**Canonical contract**: A custom field marked as `required` in booking settings is only required if the linked customer does NOT already have a non-empty value stored in `xs_customers.custom_fields`.

**When required enforcement applies**:
- **Initial booking/create**: Enforced strictly; all required custom fields must be provided.
- **Later edits/reschedule**: Relaxed; customer may update a single field without re-entering others if they already have stored values.

**Implementation seams**:
- `CustomerCustomFieldService::hasStoredValue($storedValues, $fieldKey)` → returns bool; true if field exists in decoded JSON and value is non-empty
- `BookingSettingsService::getValidationRulesForUpdate($customerId, $existingCustomFieldValues)` → accepts optional stored values; relaxes required rules when stored values exist
- Three surfaces apply this rule:
  1. **Customer edit form**: `CustomerManagement::edit()` computes `$customFieldRequiredState` array; `app/Views/customer-management/edit.php` renders field-by-field required attribute conditionally
  2. **Admin appointment edit form**: `app/Views/appointments/form.php` computes `$isRequiredForEdit = $fieldMeta['required'] && $existingValue === ''` for each field
  3. **Public booking manage**: `PublicBookingService::reschedule()` passes stored customer values to `extractAppointmentCustomFieldValues()`; `resources/js/public-booking.js` only shows required marker when `!existingMasked`

**Example workflow**:
1. Initial booking: Customer fills required Medical Aid → value stored in `xs_customers.custom_fields`
2. Later customer edit: Medical Aid field NOT marked required (stored value exists) → customer may change address and save without re-entering Medical Aid
3. Public reschedule: Medical Aid NOT marked required in form → blank submission accepted, existing value preserved via selective merge

**Edge case semantics**:
- Sensitive fields on reschedule: Form input starts empty but shows "Current: ****5201" hint. Blank submission is no-op (doesn't overwrite stored value).
- Non-sensitive fields on edit: Show "Leave blank to remove this value" hint. Blank submission is no-op (selective merge only takes non-empty values).
- Explicit clear: `clear__fieldKey=1` in payload still removes value regardless of blank status (explicit intent wins).

**Test coverage**:
- `Tests\Unit\Services\BookingSettingsServiceTest::testGetValidationRulesForUpdateRelaxesRequiredCustomFieldWhenStoredValueExists` → confirms rules relax when stored values present
- `Tests\Unit\PublicBookingServiceTest::testExtractAppointmentCustomFieldValuesRequiresInitialCaptureWhenMissing` → confirms first capture strict
- `Tests\Unit\PublicBookingServiceTest::testExtractAppointmentCustomFieldValuesAllowsBlankWhenStoredValueAlreadyExists` → confirms reschedule relax

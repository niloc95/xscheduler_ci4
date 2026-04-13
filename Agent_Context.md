# WebScheduler CI4 - Agent Context & Engineering Standards

> Read this document before making structural, architectural, API, scheduling, or UI changes.
> This file is the live engineering contract for this repository.

---

## What You Are Building

WebScheduler CI4 is a professional appointment scheduling system built on CodeIgniter 4.

Architecture: service-oriented CI4 monolith with server-rendered views, REST-style APIs, Vite-managed frontend assets, and a custom scheduler/public-booking experience.

Current state: active hardening. Core workflows are production-capable, with focused debt cleanup still needed in scheduler/frontend internals and selected user-management/calendar seams.

### How To Read This File
- Read `Architecture Rules`, `Database Rules`, `Scheduling & Booking Rules`, and `Notifications Rules` before changing behavior.
- Use `Key Files To Know` and `Agent Notes` for fast orientation.
- If code and this document disagree, verify the live runtime first and then update this file.

### Current Refactor Snapshot
- Business logic has moved from controllers into service boundaries.
- Scheduler and appointment UI behavior is modularized under `resources/js/modules`.
- Multi-role support is live via `xs_user_roles` with compatibility fallback to `xs_users.role`.
- Notification delivery uses queue + dispatcher + delivery logs.
- Schema-safe compatibility is still required across mixed local databases.

---

## Confirmed Tech Stack

### Backend
- PHP 8.1+
- CodeIgniter 4
- MySQL / MariaDB only
- Session-based authentication
- Route filters + service-level authorization

### Frontend
- Vite 6
- Tailwind CSS 3.4
- SCSS
- Material Design 3 packages/styles
  - Icon font: `Material+Symbols+Outlined` **and** `Material+Symbols+Rounded` — both loaded in `app/Views/layouts/app.php`. Views may use either `material-symbols-outlined` or `material-symbols-rounded` class on `<span>` elements. The Rounded variant was added 2026-04-12 to fix missing icons on the profile view.
- Chart.js 4
- Luxon 3
- Plain JavaScript modules

### Build / Release
- npm scripts for asset build/release flow
- Composer for PHP dependencies
- Packaging via `scripts/package.js`
- Release automation via `scripts/release.js`

#### Deployment Package (`scripts/package.js`)
- Builds a production-ready ZIP package excluding all development/runtime files
- Run via `npm run package` (builds assets then packages) or `npm run package:local` (packages only)
- **Excluded from deployment** (automatically stripped):
  - Setup flags: `setup_completed.flag`, `setup_complete.flag`
  - Runtime cache: all files in `writable/cache/` (e.g., availability calendar cache)
  - Session files: all files in `writable/session/`
  - Log files: `.log` files in `writable/logs/`
  - Debug toolbar: `.json` files in `writable/debugbar/`
  - Database backups: `.db` files in `writable/database/`
  - Exports/backups: all files in `writable/exports/` and `writable/backups/`
  - Test views: `app/Views/test/` directory
- **Preserved directories** (empty shells with `.gitkeep`):
  - `writable/logs/`, `writable/cache/`, `writable/session/`, `writable/debugbar/`, `writable/backups/`, `writable/exports/`
  - These ensure proper directory structure on fresh installation
- **Output**: ZIP file versioned as `webschedulr-deploy-v{N}.zip`, generic copy as `webschedulr-deploy.zip`
- Configuration auto-detection: `App.php` baseURL is set empty for auto-detection via `$_SERVER['HTTP_HOST']` in production

### No Framework Drift
- Do not introduce React/Vue/Alpine without explicit approval.
- Do not introduce TypeScript without explicit scoped approval.

---

## Project Structure

### Backend
```text
app/
  Commands/
  Config/
  Controllers/
    Api/
    PublicSite/
  Database/
  Filters/
  Helpers/
  Models/
  Services/
    Appointment/
    Calendar/
    Concerns/
    Settings/
  Views/
    components/
    layouts/
    settings/
    public_booking/
```

### Frontend
```text
resources/
  js/
    modules/
    utils/
    app.js
    public-booking.js
    spa.js
    dark-mode.js
    charts.js
    setup.js
    unified-sidebar.js
    material-web.js
  scss/
    app-consolidated.scss
```

### Documentation
```text
docs/
  architecture/
  audits/
  configuration/
  deployment/
  design/
  development/
  scheduler/
  security/
  technical/
  testing/
```

### Known Dead Code
- Runtime calendar API controller is `app/Controllers/Api/CalendarController.php`.

---

## Architecture Rules

### Rule 1 - Services Own Business Logic
- Scheduling, booking, availability, timezone conversion, notification dispatch, and query scoping belong in services.

### Rule 2 - Controllers Stay Thin
- Controllers parse requests, call services, and shape responses.
- Controllers must not become alternate business layers.

### Rule 3 - Models Stay Data-Focused
- Models should encapsulate data access and query composition.
- Keep model usage compatible with project conventions.

### Rule 4 - Migrations Extend MigrationBase
- App migrations must extend `App\Database\MigrationBase`.

### Rule 5 - API Envelope Contract
- API controllers should use `App\Controllers\Api\BaseApiController` where applicable.
- Success:
```json
{ "data": {}, "meta": {} }
```
- Error:
```json
{ "error": { "message": "", "code": "", "details": {} } }
```

### Rule 6 - Views Render, They Do Not Decide
- Views should not perform business logic, authorization, or timezone calculations.

---

## Database Rules

### Core Conventions
- Table prefix: `xs_`.
- Runtime DB: MySQL / MariaDB only.
- SQLite is not a supported app runtime DB.
- Runtime schema is authoritative for this file.
- Default DB charset/collation for new installs:
  - Charset: `utf8mb4`
  - Collation: `utf8mb4_unicode_ci` (portable default for MySQL and MariaDB)
  - If strictly MySQL 8.0+, `utf8mb4_0900_ai_ci` is preferred.

### Customer vs User Split
- `xs_users` holds internal login users.
- `xs_customers` holds booking customers.
- Appointment customer linkage is `xs_appointments.customer_id`.

### Complete Current Runtime Schema

**Total application tables: 21** (all prefixed `xs_`)

#### Core Identity Tables

##### `xs_users`
- Columns:
  - `id`, `name`, `email`, `phone`, `password_hash`, `role`
  - `created_at`, `updated_at`, `color`
  - `reset_token`, `reset_expires`, `status`, `profile_image`, `last_login`, `notify_on_appointments`
- Notes:
  - `status` is the canonical active-state field in this runtime.
  - `notify_on_appointments` is the internal appointment-notification preference used for provider/staff recipients; defaults to enabled.

##### `xs_user_roles`
- Columns:
  - `id`, `user_id`, `role`, `created_at`
- Notes:
  - Authoritative multi-role membership table.
  - Backfilled from `xs_users.role` in `2026-04-08-000001_CreateUserRolesTable.php`.

##### `xs_customers`
- Columns:
  - `id`, `hash`, `first_name`, `last_name`, `email`, `phone`, `address`, `notes`, `created_at`, `updated_at`, `custom_fields`
- Notes:
  - `hash` — 64-char unique slug used by public portal routes (`/my-appointments/{hash}`). Column added and backfilled 2026-04-12; unique index `idx_customers_hash` is present.
  - `custom_fields` — TEXT nullable; JSON map of custom booking field values populated via the booking form.
  - All existing customers have their `hash` backfilled as of 2026-04-12. New customers get a hash via `CustomerModel` `beforeInsert` callback.

#### Core Scheduling Tables

##### `xs_services`
- Columns:
  - `id`, `name`, `description`, `duration_min`, `buffer_before`, `buffer_after`, `price`, `created_at`, `updated_at`, `category_id`, `active`

##### `xs_categories`
- Columns:
  - `id`, `name`, `description`, `color`, `created_at`, `updated_at`, `active`

##### `xs_appointments`
- Columns:
  - `id`, `provider_id`, `service_id`, `start_at`, `end_at`, `stored_timezone`, `status`, `notes`
  - `hash`, `public_token`, `public_token_expires_at`
  - `created_at`, `updated_at`, `reminder_sent`
  - `customer_id`, `location_id`, `location_name`, `location_address`, `location_contact`

##### `xs_blocked_times`
- Columns:
  - `id`, `provider_id`, `start_at`, `end_at`, `reason`, `created_at`, `updated_at`

#### Availability / Location Tables

##### `xs_business_hours`
- Columns:
  - `id`, `provider_id`, `weekday`, `start_time`, `end_time`, `breaks_json`, `created_at`, `updated_at`
- Notes:
  - This runtime uses `weekday`, not `day_of_week`.

##### `xs_provider_schedules`
- Columns:
  - `id`, `provider_id`, `day_of_week`, `start_time`, `end_time`, `break_start`, `break_end`, `is_active`, `created_at`, `updated_at`
- Notes:
  - This runtime does not include `location_id`; older docs and schema snapshots may still mention it.

##### `xs_locations`
- Columns:
  - `id`, `provider_id`, `name`, `address`, `contact_number`, `is_primary`, `is_active`, `created_at`, `updated_at`

##### `xs_location_days`
- Columns:
  - `id`, `location_id`, `day_of_week`
- Notes:
  - Runtime currently exposes only these three columns.
  - Treat this table as schema-incomplete for day-level hours/availability work until additional fields are restored or intentionally redesigned.

##### `xs_providers_services`
- Columns:
  - `provider_id`, `service_id`, `created_at`

##### `xs_provider_staff_assignments`
- Columns:
  - `id`, `provider_id`, `staff_id`, `assigned_at`, `assigned_by`, `status`

#### Settings Tables

##### `xs_settings`
- Columns:
  - `id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`
- Notes:
  - This runtime does not currently expose `updated_by` even though migrations/model compatibility logic still support it.

#### Notification Tables

##### `xs_business_notification_rules`
- Columns:
  - `id`, `business_id`, `event_type`, `channel`, `is_enabled`, `reminder_offset_minutes`, `created_at`, `updated_at`

##### `xs_business_integrations`
- Columns:
  - `id`, `business_id`, `channel`, `provider_name`, `encrypted_config`, `is_active`, `health_status`, `last_tested_at`, `created_at`, `updated_at`

##### `xs_message_templates`
- Columns:
  - `id`, `business_id`, `event_type`, `channel`, `provider`, `provider_template_id`, `locale`, `recipient_class`, `subject`, `body`, `is_active`, `created_at`, `updated_at`
- Notes:
  - `recipient_class` distinguishes customer-facing templates from internal provider/staff templates.

##### `xs_notification_queue`
- Columns:
  - `id`, `business_id`, `channel`, `event_type`, `appointment_id`, `recipient_type`, `recipient_user_id`, `status`, `attempts`, `max_attempts`, `run_after`, `locked_at`, `lock_token`, `last_error`, `sent_at`, `idempotency_key`, `correlation_id`, `created_at`, `updated_at`
- Notes:
  - This runtime uses `attempts` and `max_attempts` instead of legacy `attempt_count`.
  - This runtime includes `locked_at`, `lock_token`, and `correlation_id`.
  - `recipient_type` is `customer` or `internal`; internal rows resolve the final email recipient from `xs_users` at dispatch time.
  - `recipient_user_id` stores the internal recipient's `xs_users.id` for provider/staff notifications.
  - This runtime does not currently expose legacy columns such as `customer_id`, `recipient`, `payload_json`, `attempt_count`, or `provider_message_id`.

##### `xs_notification_delivery_logs`
- Columns:
  - `id`, `business_id`, `queue_id`, `correlation_id`, `channel`, `event_type`, `appointment_id`, `recipient`, `provider`, `status`, `attempt`, `error_message`, `created_at`, `updated_at`

##### `xs_notification_opt_outs`
- Columns:
  - `id`, `business_id`, `channel`, `recipient`, `reason`, `created_at`, `updated_at`

#### Audit Table

##### `xs_audit_logs`
- Columns:
  - `id`, `user_id`, `action`, `target_type`, `target_id`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`
- Notes:
  - In this runtime the table name is `xs_audit_logs`.
  - Older documentation may mention legacy `audit_logs` without the `xs_` prefix; verify actual runtime table name before querying in mixed environments.

### Canonical Relationships
- `xs_appointments.customer_id -> xs_customers.id`
- `xs_appointments.provider_id -> xs_users.id`
- `xs_appointments.service_id -> xs_services.id`
- `xs_appointments.location_id -> xs_locations.id`
- `xs_business_hours.provider_id -> xs_users.id`
- `xs_provider_schedules.provider_id -> xs_users.id`
- `xs_locations.provider_id -> xs_users.id`
- `xs_location_days.location_id -> xs_locations.id`
- `xs_providers_services.provider_id -> xs_users.id`
- `xs_providers_services.service_id -> xs_services.id`
- `xs_provider_staff_assignments.provider_id -> xs_users.id`
- `xs_provider_staff_assignments.staff_id -> xs_users.id`
- `xs_user_roles.user_id -> xs_users.id`
- `xs_notification_queue.appointment_id -> xs_appointments.id`
- `xs_notification_delivery_logs.queue_id -> xs_notification_queue.id`
- `xs_audit_logs.user_id -> xs_users.id`

### Compatibility Rules
- Do not write new logic against appointment `user_id`.
- Keep scheduling logic on `start_at` / `end_at`.
- Use schema-safe service/model fallback behavior when runtime columns vary.
- `xs_business_hours` uses `weekday` in this runtime, not `day_of_week`; verify before querying in mixed-schema environments.
- `xs_customers.hash` and `xs_customers.custom_fields` were absent in this runtime and were restored 2026-04-12. `/my-appointments/{hash}` flows are now operational for all existing and new customers.
- `xs_provider_schedules` does not include `location_id` in this runtime; older docs that reference it are not authoritative here.
- `xs_location_days` is currently a three-column stub in this runtime; verify schema intent before implementing location-hours logic against it.
- `xs_settings` runtime omits `updated_by`; use model/service compatibility checks instead of assuming its presence.
- `xs_notification_queue` uses `attempts`, `max_attempts`, `locked_at`, `lock_token`, and `correlation_id`; do not assume older queue columns still exist.

---

## Timezone & Datetime Rules

### Canonical Strategy
- Persist UTC datetimes.
- Convert at service/render boundaries using localization settings.

### Mandatory Services
- `App\Services\TimezoneService`
- `App\Services\LocalizationSettingsService`

### Required Behavior
- Compute business-local boundaries first, then convert to UTC for DB querying.

---

## Scheduling & Booking Rules

### Single Sources of Truth
- `AvailabilityService`
- `AppointmentBookingService`
- `Appointment\AppointmentQueryService`
- `Appointment\AppointmentStatus`

### Booking Pipeline
```text
1. Validate service/provider context
2. Resolve timezone boundaries
3. Validate business hours
4. Validate availability and conflicts
5. Resolve/create customer
6. Persist appointment
7. Enqueue notifications
```

### Notification Enqueue Rule
- The enqueue step must derive event type from `AppointmentStatus::notificationEvent()`.
- New booking flows must not hardcode `appointment_confirmed` for all successful creates.
- **`booking.default_appointment_status` is a notification control point.** Changing the default to `confirmed` causes new bookings to fire `appointment_confirmed` instead of `appointment_pending` — a different template is sent. This is intentional and expected.

### Status → Notification Event Mapping

Defined in `app/Services/Appointment/AppointmentStatus.php::notificationEvent()`:

| Status | Notification Event |
|--------|--------------------|
| `pending` | `appointment_pending` |
| `confirmed` | `appointment_confirmed` |
| `completed` | `appointment_confirmed` *(reuses confirmed template)* |
| `cancelled` | `appointment_cancelled` |
| `no_show` | `appointment_no_show` |
| `rescheduled` | `appointment_rescheduled` |

### Notification Template Inventory

**15 customer-facing default templates** (5 event types × 3 channels) remain defined inline in `app/Services/NotificationTemplateService.php` for fallback rendering. Per-business overrides and internal/provider-facing templates are stored in `xs_message_templates`.

| Event | When it fires |
|-------|---------------|
| `appointment_pending` | New booking created with status = pending |
| `appointment_confirmed` | Status → confirmed or completed; OR new booking if default status = `confirmed` |
| `appointment_reminder` | Scheduled cron job — **not** triggered by status change |
| `appointment_cancelled` | Status → cancelled |
| `appointment_rescheduled` | Date or time fields updated on an existing appointment |

Channels per event: `email`, `sms`, `whatsapp`.

**Internal template layer (2026-04-13):**
- 5 seeded internal email templates now exist in `xs_message_templates` with `recipient_class = 'internal'`.
- Internal templates are provider/staff facing and are loaded directly from `xs_message_templates` by `NotificationTemplateService::getTemplate(..., 'internal')`.
- Internal notifications currently use the `email` channel only.

### Public Booking Flow

#### Overview
The public booking system allows unauthenticated customers to book appointments without creating an account. Route prefix `/book/` and customer portal `/my-appointments/`.

#### Entry Points
- `GET /book` — service catalog / landing page (no auth)
- `GET /book/{serviceSlug}` — service-specific booking page
- `GET /book/{serviceSlug}/{providerHash}` — provider-specific booking page
- `GET /my-appointments/{hash}` — customer appointment portal (hash-gated, no login required)

#### Controller
- `app/Controllers/PublicSite/PublicBookingController.php`

#### JS Module
- `resources/js/public-booking.js` (dedicated Vite entry point)

#### API Calls Made by Public Booking Flow
| Endpoint | Purpose |
|----------|---------|
| `GET /api/v1/public/services` | Load available services |
| `GET /api/v1/public/providers` | Load available providers |
| `GET /api/v1/public/availability` | Fetch available time slots |
| `POST /book/submit` (or equivalent web route) | Submit booking form |

All public API endpoints are unauthenticated and must enforce rate limiting. Provider and appointment hashes are passed; numeric IDs are never exposed in public URLs.

#### Security Rules for Public Routes
- Numeric customer IDs must never appear in URLs — use `xs_appointments.hash` or `xs_appointments.public_token`.
- `xs_customers.hash` is present in this runtime; public customer portal routes must continue to use hash-based lookups rather than numeric IDs.
- CSRF protection applies to all booking form submissions.
- Public slot queries must scope by provider and service — never return cross-provider slots.

---

## Settings Rules

### Single Source of Truth
- Use `SettingModel` and settings services.
- Active key namespaces: `general.*`, `localization.*`, `booking.*`, `calendar.*`, `notifications.*`, `branding.*`, `security.*`.

### Notable Booking Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `booking.default_appointment_status` | string | `pending` | Status applied to new appointments unless an explicit status is passed in the mutation payload. Allowed values: `pending`, `confirmed`. Resolved in `AppointmentBookingService::createAppointment()` via `model('SettingModel')->getByKeys(['booking.default_appointment_status'])` with `AppointmentStatus::PENDING` fallback. Configurable at Admin → Settings → Booking. |

### Phone Normalization Default
- `PhoneNumberService` reads `localization.default_phone_country_code` with fallback to `localization.phone_country_code`.
- If neither is configured, the default fallback is `+27`.

---

## Frontend Rules

### Rule 1 - Existing Entry Points Only
- `resources/js/app.js`
- `resources/scss/app-consolidated.scss`
- `resources/js/material-web.js`
- `resources/js/setup.js`
- `resources/js/dark-mode.js`
- `resources/js/spa.js`
- `resources/js/unified-sidebar.js`
- `resources/js/charts.js`
- `resources/js/public-booking.js`

### Rule 2 - SPA Safety
- Use idempotent initializers with dataset guards.
- Non-AJAX form posts must carry `data-no-spa="true"`.

### Rule 3 - Styling
- Tailwind + consolidated SCSS only.
- No inline style attributes in app-facing templates.

---

## Frontend JS Architecture

### Entry Points Summary

| File | Purpose |
|------|---------|
| `resources/js/app.js` | Main authenticated-app bundle. Orchestrates all component initialization. |
| `resources/js/spa.js` | SPA navigation layer. Intercepts links/forms; swaps `#spa-content`. |
| `resources/js/public-booking.js` | Standalone bundle for unauthenticated public booking flow. No spa.js. |
| `resources/js/dark-mode.js` | Dark-mode toggle; sets `data-theme` attribute on `<html>`. |
| `resources/js/charts.js` | Chart.js wrapper for dashboard and analytics charts. |
| `resources/js/unified-sidebar.js` | Sidebar initialization and mobile-toggle logic. |
| `resources/js/material-web.js` | Material Design 3 component imports (side-effect only). |
| `resources/js/setup.js` | Setup wizard JS (setup flow only, not included in main bundle). |

### app.js — Main Authenticated Bundle

`app.js` is the Vite entry point for all authenticated-app pages.

#### Initialization Flow

1. `initializeComponents()` — master init function called on first load and after every SPA navigation. Calls in order: charts, scheduler, status filters, summary-card filters, view-toggle handlers, global search, appointment form, settings page enhancements, customer management search, provider schedule UI, service management forms, phone country selectors, appointment URL prefill.
2. `bindAppLifecycleEvents()` (from `modules/app-lifecycle.js`) — wires `DOMContentLoaded` and the `spa:navigated` custom event so `initializeComponents()` re-runs after every SPA page swap. This is the single lifecycle hook; do not add duplicate `DOMContentLoaded` handlers elsewhere.
3. Scheduler is lazy-initialized inside `initScheduler()` with retry logic (up to 10 attempts at 200 ms intervals) to handle SPA navigation timing. Instance is stored at `window.scheduler`.

#### Global Window Exports (available to PHP view scripts)

| Symbol | Source | Notes |
|--------|--------|-------|
| `window.xsEscapeHtml(str)` | Inline in `app.js` | HTML-encode any user-supplied string before inserting into JS-rendered DOM |
| `window.initTimeSlotsUI` | `modules/appointments/time-slots-ui.js` | Time-slot picker; called by create/edit appointment views |
| `window.initRevenueTrendChart` | `modules/analytics/analytics-charts.js` | |
| `window.initTimeSlotChart` | `modules/analytics/analytics-charts.js` | |
| `window.initServiceDistributionChart` | `modules/analytics/analytics-charts.js` | |
| `window.initProviderPicker(root)` | Inline in `app.js` | Multi-provider checkbox picker for service create/edit views |
| `window.togglePassword(fieldId)` | Inline in `app.js` | Password-field visibility toggle |
| `window.scheduler` | `modules/scheduler/scheduler-core.js` | Running `SchedulerCore` instance; set after `init()` resolves |
| `window.refreshAppointmentStats` | `modules/filters/status-filters.js` | Refresh dashboard stat counters |

#### Per-View Initializers

Views that need JS behavior must NOT add bare `DOMContentLoaded` handlers — these won't fire on SPA navigation. Instead, register with:

```js
function initMyView() {
  const el = document.getElementById('myEl');
  if (!el || el.dataset.initialized === 'true') return;
  el.dataset.initialized = 'true';
  // attach event listeners...
}
xsRegisterViewInit(initMyView);
```

`xsRegisterViewInit` (exposed by `spa.js`) runs the function immediately if the DOM is ready, and again after every `spa:navigated` event. Always include a `data-initialized` guard to prevent duplicate binding.

---

### spa.js — SPA Navigation Layer

`spa.js` is a lightweight SPA layer that preserves the page chrome (header, sidebar, footer) and swaps only the `#spa-content` div. It is loaded once and never re-imported.

#### How It Works

```
User clicks <a> or submits <form>
  └─▶ clickHandler / submitHandler (document-level listeners)
        └─▶ shouldIntercept() — opt-out check (data-no-spa, .no-spa, .fc, data-navlink)
              └─▶ navigate(url) or submitForm(form)
                    ├─ navigate: GET with X-Requested-With: XMLHttpRequest
                    │     └─▶ Server returns full HTML → spa.js extracts #spa-content innerHTML
                    │     └─▶ syncHeaderControls() — keeps calendar toolbar in sync
                    │     └─▶ Re-runs inline <script> tags in new content
                    │     └─▶ history.pushState()
                    │     └─▶ initTabsInSpaContent() — tabs driven by URL hash
                    │     └─▶ Dispatches spa:navigated CustomEvent
                    │     └─▶ runViewInitializers() — calls all xsViewInitializers[]
                    └─ submitForm: POST with X-Requested-With: XMLHttpRequest
                          └─▶ Reads JSON response (see contract below)
                          └─▶ Calls navigate() with forceReload on success
```

#### `navigate(url, push = true, options = {})`

| Parameter | Default | Effect |
|-----------|---------|--------|
| `url` | required | Destination URL |
| `push` | `true` | Push a new browser history entry |
| `options.forceReload` | `false` | **Bypass the same-URL early-return guard.** Required whenever a server action modifies data and redirects back to the current page (e.g. delete on the index page). Without it, the content silently does not refresh. |

**Same-URL guard:** Without `forceReload: true`, a `navigate()` call whose destination pathname and query string match the current page returns immediately without fetching. This prevents spurious reloads. Always pass `{ forceReload: true }` in post-form-submit success paths.

#### Form Submission & JSON Response Contract

`submitForm()` POSTs via `fetch` with `X-Requested-With: XMLHttpRequest`. The controller must return JSON:

**Success:**
```json
{ "success": true, "message": "Customer deleted.", "redirect": "https://app/customer-management" }
```

**Error:**
```json
{ "success": false, "message": "Cannot delete — appointments exist.", "errors": { "fieldName": "Error text." } }
```

| Key | Required | Effect |
|-----|----------|--------|
| `success` | Yes | `true` → navigate with forceReload; `false` → show error flash |
| `message` | No | Shown as toast after navigation (fires after content swap so it survives) |
| `redirect` | No (but recommended) | URL to navigate to on success. If absent, reloads current page. **Always include when the success destination matches the current URL.** |
| `errors` | No | Field-keyed validation messages; injected inline next to form fields |

**Success navigation behaviour:**
- `data.redirect` set → `navigate(data.redirect, true, { forceReload: true })`
- No redirect → `navigate(currentPath, false, { forceReload: true })`

Flash toast fires **after** navigation so it is not wiped by the content swap.

#### Opt-Out Patterns

| Pattern | When to use |
|---------|-------------|
| `data-no-spa="true"` on `<form>` or `<a>` | Force full page reload (logout, file downloads, external links, CSRF-sensitive non-JSON flows) |
| `class="no-spa"` on `<form>` or `<a>` | Same as above (class-based alternative) |
| `data-navlink` on `<a>` | Prevents interception of FullCalendar's internal navigation anchors |

#### Events

| Event | Fired on | When |
|-------|----------|------|
| `spa:navigated` | `document` | After every successful content swap; detail: `{ url }` |
| `xs:flash` | `document` | Show a toast; detail: `{ type: 'success'|'error', message }` |
| `scheduler:ready` | `window` | After `SchedulerCore` finishes `init()`; detail: `{ scheduler }` |
| `settingsSaved` | `document` | After settings form saves; triggers scheduler settings refresh |
| `appointmentsUpdated` | `document` | Emitted by status-filters / scheduler to trigger stat + list refresh |

---

### Scheduler Refresh Semantics (Critical)

The scheduler is **not real-time** today.

- No WebSocket/SSE channel is used for appointment data synchronization.
- No periodic appointment polling is enabled.
- The only interval timer in scheduler views is the Day view now-line updater (`setInterval(..., 60000)`), which updates only the visual current-time indicator.

#### Mode-Aware Data Source Contract

`SchedulerCore` has two loading paths:

- `loadData()` (canonical):
  - `mode='server'` → loads `calendarModel` from `/api/calendar/{view}`
  - fallback/non-server → loads flat appointments from `/api/appointments`
- `loadAppointments()` (flat appointments only):
  - refreshes `this.appointments`
  - does **not** refresh `calendarModel`

In server mode, views can consume `calendarModel` for slot placement. Therefore, any mutation that can move an appointment between slots must reload through `loadData()` to avoid stale placement.

#### Reschedule Update Propagation

**Drag-drop reschedule path (authenticated scheduler):**

1. `scheduler-drag-drop.js` calls `appointmentMutationCoordinator.execute()` with `uiContext: 'scheduler'`.
2. Coordinator sends `PATCH /api/appointments/{id}`, then calls `scheduler.loadData()` + `scheduler.render()` automatically.
3. Coordinator dispatches canonical `appointment:changed` event on `window`.

Do NOT call `emitAppointmentsUpdated()` from scheduler mutation handlers — it is now restricted to filter-change actions (status-filter, summary-card clicks) inside `status-filters.js`.

#### Troubleshooting: Appointment Does Not Move Until Manual Refresh
2. Confirm reschedule success in Network tab (`PATCH /api/appointments/{id}` returns 2xx).
3. Confirm a post-PATCH data reload request fires (`/api/calendar/{view}` for server mode).
4. If only `/api/appointments` reloads after mutation in server mode, expect stale slot placement risk.
5. Hard refresh will mask this by rebuilding both model and flat data.

#### Canonical Mutation Pipeline

All appointment mutations (reschedule, status-change, notes-save, cancel, create) funnel through a single coordinator:

```
appointmentMutationCoordinator.execute(options)
  → CSRF injection (authenticated: <meta csrf-token>; public: [data-booking-root].dataset.csrfValue)
  → loadingTargets disabled (aria-busy + disabled)
  → fetch(endpoint, {method, body})
  → CSRF rotation from X-CSRF-TOKEN response header
  → _refreshContext: 'scheduler' → loadData() + render(); 'passive' → no-op
  → window.dispatchEvent(appointment:changed)
  → window.XSNotify.toast() or xs:flash fallback
  → onSuccess / onError callbacks
```

**Key contracts:**
- Module: `resources/js/modules/appointments/appointment-mutation-coordinator.js`
- Exported singleton: `appointmentMutationCoordinator`
- `uiContext: 'scheduler'` triggers mode-aware reload; `'passive'` skips reload
- `loadingTargets` accepts any CSS selectors — coordinator sets `disabled` + `aria-busy`
- Coordinator shows its own toast — callers must NOT call `window.XSNotify` or dispatch `xs:flash` separately
- `handleNotify()` (manual re-send) is explicitly excluded — it is a send action, not a mutation

**Migrated callers:**
- `scheduler-drag-drop.js` → `rescheduleAppointment()` uses coordinator
- `appointment-details-modal.js` → `handleStatusChange()`, `handleNotesChange()`, `handleCancel()` use coordinator

**Not migrated (FormData / complex state machine):**
- `appointments-form.js` — HTML form POST with `FormData`; CSRF managed by its own helpers
- `public-booking.js` — complex multi-target state machine; own CSRF rotation

**Canonical post-mutation event:**
```js
window.dispatchEvent(new CustomEvent('appointment:changed', {
  detail: { action, endpoint, data }
}));
```
The legacy `appointments-updated` CustomEvent (dispatched by `emitAppointmentsUpdated`) remains in `status-filters.js` for filter-change actions only and has no scheduler listeners — do not add new callers. `window.emitAppointmentsUpdated` global was removed.

#### Schema-Drift Retry Pattern (`AppointmentFormContextService`)

`app/Services/Appointment/AppointmentFormContextService` uses a **retry loop** when building the appointment edit view if the runtime `xs_customers` table is missing optional columns. The loop runs up to 3 passes:
1. First attempt includes `c.custom_fields` and `c.hash` in the SELECT.
2. On `Unknown column 'c.custom_fields'`, drops that field and retries.
3. On `Unknown column 'c.hash'`, drops that field and retries.

This guard prevents hard 500 errors on drifted schemas. The `appointment_custom_fields` and `appointment_hash` keys in the view-data array default to `null` when unavailable. **As of 2026-04-12 both columns exist in the live runtime, so the guard no longer triggers.**

#### Edited Files (Refresh/Reschedule Continuity)

- `resources/js/modules/scheduler/scheduler-drag-drop.js`
  - Reschedule now uses `appointmentMutationCoordinator`; removed inline fetch, `showLoading`/`hideLoading`.
- `resources/js/modules/scheduler/appointment-details-modal.js`
  - Status/notes/cancel mutations use coordinator; `emitAppointmentsUpdated` import removed.
- `resources/js/modules/appointments/appointment-mutation-coordinator.js`
  - Singleton coordinator module (NEW).
- `resources/js/modules/scheduler/scheduler-core.js`
  - Source of truth for mode-aware data loading (`loadData`, `loadCalendarModel`, `loadAppointments`).
- `resources/js/modules/scheduler/scheduler-day-view.js`
  - Uses `calendarModel` for provider-slot placement and includes the now-line 60s timer.
- `resources/js/app.js`
  - Scheduler boot config sets `mode: 'server'`; `window.emitAppointmentsUpdated` global removed.

---

### public-booking.js — Public Booking Bundle

`public-booking.js` is a completely separate Vite entry point. It does not share `spa.js` or `app.js`.

- No authentication context.
- Uses plain `fetch` calls to `/api/v1/public/...` endpoints.
- No SPA navigation layer — transitions are full page loads.
- Entry point for all unauthenticated routes under `/book/` and `/my-appointments/`.

#### Public API Calls

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/public/services` | GET | Load available services |
| `/api/v1/public/providers` | GET | Load available providers |
| `/api/v1/public/availability` | GET | Fetch available time slots for a provider/service/date |
| `/book/submit` (web route) | POST | Submit booking form |

All public API calls are unauthenticated. Responses must never expose numeric customer or provider IDs — use hash/token references only.

---

## API Rules

### Versioning
- Versioned routes under `/api/v1/`.
- Operational routes under `/api/`.

### API Layer Architecture

#### Base Class
- All API controllers extend `App\Controllers\Api\BaseApiController`.
- Provides: `successResponse()`, `errorResponse()`, `currentUser()`, `hasRole()`, `requireRole()`, `requireAuth()`.
- Enforces the API envelope contract (Rule 5).

#### Authentication Flow
- API endpoints rely on the shared session (same session cookie as web routes — no separate JWT/token).
- `requireAuth()` → returns 401 JSON if not authenticated.
- `requireRole($roles)` → calls `hasRole()` and returns 403 JSON if insufficient role.
- `hasRole()` checks the full `$user['roles']` array in session via `array_intersect`; never relies on `xs_users.role` alone.

#### Versioned API Resources (`/api/v1/`)
| Resource | Controller |
|----------|-----------|
| Appointments | `Api/V1/Appointments.php` |
| Providers | `Api/V1/Providers.php` |
| Customers | `Api/V1/Customers.php` |
| Calendar | `Api/V1/CalendarController.php` (operational; see known dead code note) |
| Public availability / services / providers | `Api/V1/Public*` controllers |

#### Operational Routes (`/api/`)
- `POST /api/auth/switch-role` — switches active_role in session; requires auth.
- `POST /api/notifications/dispatch` — manual cron-equivalent dispatch trigger.

### Authorization
- Enforce with route filters plus backend checks.

### Public Endpoints
- Keep setup/rate-limit/hash-token protections for public flows.

---

## Roles & Access Rules

Current operational roles:
- `admin`
- `provider`
- `staff`
- `customer`

### Multi-Role Storage & Session Structure

Multi-role support is live. The storage model has two layers:

| Layer | Column/Table | Purpose |
|-------|--------------|---------|
| Compatibility primary | `xs_users.role` | Single legacy role; kept for fallback reads |
| Authoritative membership | `xs_user_roles` (one row per role per user) | Full role set; written on every create/update |

**Session keys set at login (`app/Filters/Auth.php`):**
- `$session->role` — primary single role from `xs_users.role` (legacy compat)
- `$session->roles` — full role array from `UserModel::getRolesForUser()` (authoritative)
- `$session->active_role` — highest-privilege role for UI display (admin > provider > staff)

Always read from `$user['roles']` first; fall back to `[$user['role']]` only for legacy compatibility. **Never check `xs_users.role` directly for authorization decisions.**

### How RBAC Is Enforced End-to-End

#### Route Filters (`app/Filters/RoleFilter.php`)
- Reads `$sessionRoles = $user['roles'] ?? [$user['role'] ?? '']` from session.
- Gate: `empty(array_intersect($requiredRoles, $sessionRoles))` → redirect unauthorized.
- A user with roles `['admin', 'provider']` passes both `role:admin` and `role:admin,provider` filters.
- Applied to routes inline: `'filter' => 'role:admin,provider'`.

#### Service-Level Authorization (`app/Services/AuthorizationService.php`)
- All `can*()` and scope methods accept `string|array $userRole`.
- `private resolveRole(string|array $userRole): string` — returns the highest privilege role found by scanning `[admin, provider, staff]` in hierarchy order.
- `getProviderId()` checks `$user['roles'] ?? [$user['role']]` array, not `$user['role']` alone.
- Callers that pass a plain string (e.g. `getUserRole()` result) are unaffected; `resolveRole(string)` returns the string unchanged.

#### API Base Controller (`app/Controllers/Api/BaseApiController.php`)
- `hasRole(string|array $roles): bool` — reads full session `$user['roles'] ?? [$user['role']]`, applies `array_intersect`.
- `requireRole($roles)` — calls `hasRole()` and returns 403 JSON on failure.
- No separate JWT layer; API endpoints rely on the same session as web routes.

#### User Management Context (`app/Services/UserManagementContextService.php`)
- `buildEditViewData()` resolves `$userRoles = $user['roles'] ?? [$user['role']]`.
- All provider/staff/admin conditionals use `in_array($role, $userRoles, true)` — not `$user['role'] === 'provider'`.
- Provider-specific context (assigned staff, locations, canManageAssignments) is passed to the view whenever `$userIsProvider === true`.
- Index/API user rows must be enriched with authoritative `roles[]` membership for display surfaces; the user-management list must not render only `xs_users.role` when multiple roles are assigned.

#### Provider Schedule JS (`resources/js/modules/user-management/provider-schedule.js`)
- `getActiveRoles(): string[]` — reads all `input[name="roles[]"]:checked` checkboxes; falls back to `<select id="role">` for legacy DOM.
- `toggleScheduleSection(activeRoles: string[])` — enables/disables the schedule section and "copy to all days" button based on `activeRoles.includes('provider')`.
-  Event binding: loops all role checkboxes on `change`; single-select fallback is preserved for any remaining legacy form paths.

#### API Providers Resource (`app/Controllers/Api/V1/Providers.php`)
- Provider-identity checks at `services()`, `appointments()`, and `uploadProfileImage()` use `$userModel->getRolesForUser($id)` via `in_array('provider', ..., true)`.
- Provider index listing must resolve provider membership from `UserModel::getRolesForUser($userId)` / `xs_user_roles`, not `xs_users.role` alone. Public/provider/staff scoped listings must include users whose compatibility primary role differs from their authoritative provider membership.

#### Provider Schedule Authorization (`app/Controllers/ProviderSchedule.php`)
- `isAuthorized()` must evaluate the full effective role set from session `roles[]` plus authoritative DB fallback via `getRolesForUser()`.
- Do not gate provider schedule access on `$currentUser['role']` alone; multi-role users may carry provider/admin membership even when the compatibility primary role is different in-session.

### Route Filters Reference
- `setup`
- `auth`
- `role:admin`
- `role:admin,provider`
- `role:admin,provider,staff`

### Staff Scope Contract
- Canonical source: `ProviderStaffModel::getProvidersForStaff($userId, 'active')`.
- Extract provider IDs with `array_column($rows, 'id')`.
- Preferred appointment context key: `provider_id`.

### Provider Scope Contract
- For customer scoping, delegate to `CustomerAppointmentService`:
  - `resolveCustomerIdsForProvider(int $providerUserId): array`
  - `resolveCustomerIdsForStaff(int $staffUserId): array`
  - `getProviderIdsForStaff(int $staffUserId): array`

---

## Notifications Rules

### Canonical Services
- `AppointmentEventService`
- `NotificationQueueService`
- `NotificationQueueDispatcher`
- `NotificationPolicyService`
- `NotificationEmailService`
- `NotificationSmsService`
- `NotificationWhatsAppService`
- `AppointmentNotificationService` (manual resend path)

### Delivery Contract
- Queue first, then dispatch.
- Preserve idempotency and delivery-log behavior.

### Notification Dispatch Architecture Note
- **Notifications are queued synchronously inline** during the appointment HTTP mutation request (not deferred to the first cron tick). `AppointmentBookingService::queueNotifications()` dispatches the customer-facing appointment event into the queue, enqueues internal provider/staff email rows, then immediately invokes the queue dispatcher.
- **`handleNotify()` is a manual re-send path** (`POST /api/appointments/{id}/notify`, body: `{channel, event_type?}`). Service: `AppointmentManualNotificationService::send()`. Email/SMS re-sends are sent immediately (no queue). WhatsApp re-sends are queued and picked up by cron. Event type defaults to `AppointmentStatus::notificationEvent($appointment['status'])` if not explicitly passed.
- **`PATCH /api/appointments/{id}/status`** (used by the coordinator's status-change and cancel flows) **fires notifications server-side** for every transition. `AppointmentMutationService` resolves the event via `AppointmentStatus::notificationEvent()` and passes it to `updateAppointment()`. Channels: email + whatsapp.
- `appointments-updated` event (from `emitAppointmentsUpdated`) does **not** affect notification dispatch. Notifications are driven by the PHP model layer, not frontend events.
- Internal appointment notifications are queue-only: `NotificationQueueService::enqueueInternalEvent()` writes `recipient_type = 'internal'` + `recipient_user_id`, and `NotificationQueueDispatcher` resolves the real email/name from `xs_users` just before send.
- Internal recipient eligibility is determined by `UserModel::getNotifiableUsersForProvider()` and `xs_users.notify_on_appointments`; both admin user-management and self-service profile surfaces write that preference.
- Notification time rendering must use an explicit timezone: `NotificationQueueDispatcher` now resolves display timezone from `xs_appointments.stored_timezone` first and falls back to `TimezoneService::businessTimezone()`; do not rely on implicit session timezone when rendering queued notifications.

### Appointment Event Contract
- `appointment_pending` is the canonical event for pending bookings.
- `appointment_confirmed` is the canonical event for confirmed bookings and completed appointments.
- New booking flows must resolve event type from appointment status via `AppointmentStatus::notificationEvent()` instead of hardcoding confirmation.
- See **Status → Notification Event Mapping** and **Notification Template Inventory** above for the full reference.

---

### Notification System: Complete Flow

The notification system handles all multi-channel notifications (email, SMS, WhatsApp) using a **queue-first** pattern. Booking flows enqueue rows first and may immediately invoke the dispatcher; cron/manual dispatch remains the general delivery path. This preserves idempotency and delivery logging.

**Developer shortcut:**
- Customer-facing notification rows enter through `AppointmentEventService` and `NotificationQueueService::enqueueAppointmentEvent()`.
- Internal provider/staff rows enter through `AppointmentBookingService::enqueueInternalNotifications()` and `NotificationQueueService::enqueueInternalEvent()`.
- All queued rows are sent by `NotificationQueueDispatcher`.

#### 1. Appointment Event → Queue Enqueue

**When:** An appointment is created, updated, or confirmed.

**Flow:**
```
Appointment created/updated
  └─▶ AppointmentBookingService::queueNotifications()
    ├─▶ AppointmentEventService::dispatch(event, appointmentId, channels, businessId)
    │     └─▶ NotificationQueueService::enqueueAppointmentEvent(...)
    │           ├─ Creates customer-facing xs_notification_queue row(s)
    │           ├─ Uses idempotency_key + correlation_id tracking
    │           └─ Stores run_after = NOW() (or future for reminders)
    ├─▶ AppointmentBookingService::enqueueInternalNotifications()
    │     └─▶ UserModel::getNotifiableUsersForProvider(providerId)
    │           └─▶ NotificationQueueService::enqueueInternalEvent(...)
    │                 ├─ Creates internal xs_notification_queue row(s)
    │                 ├─ Sets recipient_type='internal'
    │                 └─ Stores recipient_user_id for dispatcher-time resolution
    └─▶ NotificationQueueDispatcher::dispatch()
```

**Key Services:**
- `AppointmentEventService` — canonical customer-event enqueue entry point
- `NotificationQueueService` — queue row insertion for both customer and internal recipients
- `AppointmentBookingService` — booking-time orchestration for customer + internal notification enqueue
- `NotificationPolicyService` — reads business rules from `xs_business_notification_rules`

**Key Models:**
- `NotificationQueueModel` — reads/writes queue status
- `AppointmentStatus` — provides `notificationEvent()` canonical event mapping

#### 2. Queue Dispatch (Cron/Manual Trigger)

**When:** Cron job runs `php spark notifications:dispatch-queue` OR manual admin trigger via `/api/notifications/dispatch`.

**Flow:**
```
Cron / Admin trigger
  └─▶ NotificationQueueDispatcher::dispatch()
  ├─ Query xs_notification_queue WHERE status='queued' AND run_after <= NOW()
        ├─ Apply row-level locking: lock_token + locked_at timestamp
        ├─ For each unlocked row:
        │   ├─ Resolve channel (email/sms/whatsapp)
        │   ├─ Resolve business integration config: xs_business_integrations (encrypted)
        │   ├─ Route to appropriate sender service:
        │   │   ├─ Email    → NotificationEmailService::sendEmail()
        │   │   ├─ SMS      → NotificationSmsService::sendSms()
        │   │   ├─ WhatsApp → NotificationWhatsAppService::sendWhatsApp()
        │   ├─ Increment xs_notification_queue.attempts ++
        │   ├─ On success:
        │   │   ├─ Update queue row: status='sent', sent_at=NOW()
        │   │   └─ Log delivery: xs_notification_delivery_logs (status='success')
        │   ├─ On failure:
        │   │   ├─ If attempts < max_attempts:
        │   │   │   └─ Update queue row: last_error=..., run_after=NOW() + 5min backoff
        │   │   └─ If attempts >= max_attempts:
        │   │       ├─ Update queue row: status='failed'
        │   │       └─ Log delivery: xs_notification_delivery_logs (status='failed', error_message=...)
        │   ├─ Always log attempt: xs_notification_delivery_logs row created
```

**Key Services:**
- `NotificationQueueDispatcher` — coordinator for all dispatch logic
- `NotificationEmailService` — SMTP sending via configured business integration
- `NotificationSmsService` — SMS sending via Clickatell/Twilio
- `NotificationWhatsAppService` — WhatsApp sending via Meta/Clickatell

**Key Models:**
- `NotificationQueueModel` — row locking, status updates
- `NotificationDeliveryLogModel` — immutable delivery attempt records
- `BusinessIntegrationModel` — encrypted config storage for SMTP, SMS, WhatsApp providers

#### 3. Configuration & Policies

**Settings stored in:**
- `xs_business_notification_rules` — which events/channels are enabled for which business
- `xs_business_integrations` — SMTP/SMS/WhatsApp credentials (encrypted in `encrypted_config` column)
- `xs_message_templates` — per-business, per-event, per-channel message body/subject
- `xs_notification_opt_outs` — customer opt-outs by channel (email, sms, whatsapp)

**Example customer queue row structure:**
```json
{
  "id": 42,
  "business_id": 1,
  "channel": "email",
  "event_type": "appointment_confirmed",
  "appointment_id": 123,
  "recipient_type": "customer",
  "recipient_user_id": null,
  "status": "queued",             /* queued | sent | failed | cancelled */
  "attempts": 0,
  "max_attempts": 3,
  "run_after": "2026-04-11 14:00:00",
  "locked_at": null,              /* Set during dispatch to prevent concurrent sends */
  "lock_token": null,
  "last_error": null,
  "sent_at": null,
  "idempotency_key": "apt_123_email_confirmed_1712847600",
  "correlation_id": "cor_67890",
  "created_at": "2026-04-11 13:58:00",
  "updated_at": "2026-04-11 13:58:00"
}
```

**Example internal queue row structure:**
```json
{
  "id": 43,
  "business_id": 1,
  "channel": "email",
  "event_type": "appointment_confirmed",
  "appointment_id": 123,
  "recipient_type": "internal",
  "recipient_user_id": 77,
  "status": "queued"
}
```

#### 4. Delivery Log (Immutable Record)

**Every attempt** (success or failure) creates a **new** `xs_notification_delivery_logs` row:
```json
{
  "id": 1001,
  "business_id": 1,
  "queue_id": 42,
  "correlation_id": "cor_67890",
  "channel": "email",
  "event_type": "appointment_confirmed",
  "appointment_id": 123,
  "recipient": "customer@example.com",     /* Resolved from appointment + customer lookup */
  "provider": "smtp",                       /* e.g., smtp, clickatell, twilio, meta */
  "status": "success",                      /* success | failed | bounced */
  "attempt": 1,                             /* Which attempt this was out of max_attempts */
  "error_message": null,
  "created_at": "2026-04-11 14:00:05",
  "updated_at": "2026-04-11 14:00:05"
}
```

---

### Frontend Architecture: In-Depth

The frontend uses a **modular, SPA-first architecture** with strict initialization guardrails to support both authenticated (app.js + spa.js) and public-facing (public-booking.js) experiences.

#### Architecture Layers

```
┌─────────────────────────────────────────────────────────┐
│ Browser HTML (server-rendered PHP template)             │
├─────────────────────────────────────────────────────────┤
│ Vite Entry Point (app.js OR public-booking.js)          │
├─────────────────────────────────────────────────────────┤
│ SPA Layer (spa.js) ← intercepts navigation               │
│   └─ Preserves header/sidebar/footer                    │
│   └─ Swaps #spa-content only                            │
├─────────────────────────────────────────────────────────┤
│ Feature Modules (calendar, appointments, etc.)          │
│ Located in resources/js/modules/                        │
├─────────────────────────────────────────────────────────┤
│ Utilities (url-helpers, phone-selector, etc.)           │
│ Located in resources/js/utils/                          │
└─────────────────────────────────────────────────────────┘
```

#### Initialization Lifecycle

**Page Load → app.js → spa.js → Feature Modules → View Initializers**

**Step 1: Initial Page Load**
- Server renders `app/Views/layouts/main.php`
- Includes `<script>` tag pointing to Vite-built `app.js` bundle
- Browser requests and executes `app.js`

**Step 2: app.js Imports & Side Effects**
```javascript
import { SchedulerCore } from './modules/scheduler/scheduler-core.js';
import { initGlobalSearch } from './modules/search/global-search.js';
import { setupAdvancedFilterPanel } from './modules/filters/advanced-filters.js';
// ... many more module imports ...

window.xsEscapeHtml = function(str) { /* ... */ };
window.scheduler = null;  // Will be set after SchedulerCore init
```

**Step 3: spa.js Registers Event Listeners**
- Intercepts all `<a>` clicks and `<form>` submissions
- Attached via document-level event delegation (fires on every event)

**Step 4: initializeComponents() Runs**
- Called automatically on `DOMContentLoaded`
- Calls all feature module init functions in sequence:
  ```javascript
  initializeComponents() {
    initRevenueTrendChart();
    initTimeSlotChart();
    initServiceDistributionChart();
    initScheduler();           // Contains retry logic
    initStatusFilterControls();
    initSummaryCardFilters();
    // ... more inits ...
  }
  ```

**Step 5: View Initializers Register & Run**
- Any view-specific JS code must run via `xsRegisterViewInit(fn)`
- Function runs immediately if DOM is ready
- Also runs after every `spa:navigated` event

**Step 6: SPA Navigation (on Click/Submit)**
```
User clicks <a href="/appointments">
  ↓
spa.js interceptHandler() runs
  ↓
shouldIntercept() checks data-no-spa, .no-spa, etc.
  ↓
navigate("/appointments") called
  ↓
fetch GET with X-Requested-With: XMLHttpRequest header
  ↓
Server returns full HTML
  ↓
spa.js extracts #spa-content innerHTML
  ↓
Replace DOM #spa-content with new content
  ↓
Execute any inline <script> tags in new content
  ↓
history.pushState() updates browser back button
  ↓
Dispatch spa:navigated CustomEvent
  ↓
runViewInitializers() runs all registered init functions
```

---

### API Request Flow

#### From Frontend (JavaScript)

**Authenticated routes (require login):**
```javascript
// In a module or event handler
fetch('/api/v1/appointments', {
  method: 'GET',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',  // SPA indicator
    'Accept': 'application/json'
  }
  // Cookies sent automatically (session-based auth)
})
.then(r => r.json())
.then(json => {
  console.log(json.data);    // Actual data
  console.log(json.meta);    // Pagination, timestamps
});
```

**Public routes (no auth):**
```javascript
// In public-booking.js
fetch('/api/v1/public/providers', {
  method: 'GET',
  headers: { 'Accept': 'application/json' }
})
.then(r => r.json())
.then(json => {
  // Display providers
});
```

#### Backend Processing

**Step 1: Route Matching**
```php
// app/Config/Routes.php
$routes->get('appointments', 'Appointments::index', ['filter' => 'auth']);
$routes->group('api', function($routes) {
    $routes->group('v1', function($routes) {
        $routes->get('appointments', 'Api/V1/Appointments::index', ['filter' => 'auth']);
    });
});
```

**Step 2: Filter Chain**
- `auth` filter: Check session, verify user logged in
- `role:admin,provider` filter: Check `$user['roles']` array

**Step 3: Controller / API Endpoint**
```php
namespace App\Controllers\Api\V1;

class Appointments extends BaseApiController {
    public function index() {
        $this->requireAuth();  // Verify authenticated
        $userId = $this->currentUser()['id'];
        
        $data = $this->appointmentService->getUserAppointments($userId);
        
        return $this->ok($data, [
            'pagination' => ['total' => count($data)],
            'timestamp' => now()
        ]);
    }
}
```

**Step 4: Response Sent**
```json
{
  "data": [
    { "id": 1, "service_id": 5, "provider_id": 2, "status": "confirmed" },
    { "id": 2, "service_id": 3, "provider_id": 2, "status": "pending" }
  ],
  "meta": {
    "pagination": { "total": 2 },
    "timestamp": "2026-04-11T14:05:30Z"
  }
}
```

**Step 5: Frontend Processes Response**
```javascript
.then(json => {
  if (!json.data) return console.error('No data');
  
  json.data.forEach(apt => {
    // Render appointment row
  });
});
```

---

### Public Booking Flow: Step-by-Step

The public booking flow allows customers to book appointments **without logging in**. It uses hash-based URLs and token-based security instead of user accounts.

#### High-Level Process

```
Customer receives booking link: /book/provider/{provider_hash}
  ↓
Public booking form loads (public-booking.js bundle)
  ↓
Customer selects service → available providers/times load
  ↓
Customer enters contact details (name, email, phone)
  ↓
Customer selects time slot
  ↓
Customer submits form
  ↓
Backend creates appointment (status: pending/confirmed per policy)
  ↓
Backend generates public_token + expiry for future access
  ↓
Customer receives confirmation email with reschedule link
  ↓
Customer can access /my-appointments/{appointment_hash} to reschedule/cancel
```

#### Database Context

**Three hash-based identifiers are used:**

| Column | Table | Purpose | Created on |
|--------|-------|---------|-----------|
| `hash` | `xs_appointments` | Canonical appointment reference; used in URLs like `/my-appointments/{hash}` | Appointment insert (model callback) |
| `public_token` | `xs_appointments` | Temporary token for rescheduling/canceling (expires); used as fallback when `hash` is unavailable | Booking pipeline |
| `public_token_expires_at` | `xs_appointments` | Expiry timestamp for public_token | Booking pipeline |

**Customer context:**
- `xs_customers.hash` exists in the current runtime.
- Public customer-facing routes remain appointment-hash based (`/my-appointments/{hash}`), not a separate `/my-customers/{hash}` portal contract.

#### Detailed Flow: Steps 1–4 (Form Load & Selection)

**Step 1: Customer clicks booking link**
```
GET /book/provider/{provider_hash}
```

**Backend (PublicBookingController):**
```php
public function bookProvider($providerHash) {
    // Lookup provider by hash (not numeric ID)
    $provider = ProviderModel::where('hash', $providerHash)->first();
    
    // Render public booking form
    return view('public_booking/form', [
        'provider' => $provider,
        'services' => $provider->getServices(),
    ]);
}
```

**Frontend (public-booking.js):**
```javascript
// On page load
fetch('/api/v1/public/providers/' + providerHash, {
  headers: { 'Accept': 'application/json' }
})
.then(r => r.json())
.then(json => {
  // Populate provider name, description
  // Populate available services dropdown
});
```

**Step 2: Customer selects a service**
```
User picks a service from <select name="service_id">
```

**Frontend:**
```javascript
document.querySelector('select[name="service_id"]')
  .addEventListener('change', async (e) => {
    const serviceId = e.target.value;
    
    // Fetch available providers for this service
    const resp = await fetch(
      `/api/v1/public/providers/${providerHash}/services/${serviceId}`
    );
    const json = await resp.json();
    
    // Update provider picker (or reconfirm provider)
  });
```

**Step 3: Customer selects a date**
```
User picks date from calendar
```

**Frontend triggers time-slot fetch:**
```javascript
const response = await fetch(
  `/api/v1/public/availability?provider_hash=${providerHash}&service_id=${serviceId}&date=${date}`
);
const slots = await response.json();

// Render time slots
slots.data.forEach(slot => {
  // Show time <button>
});
```

**Step 4: Customer selects a time slot**
```
User clicks time slot button
```

**Frontend stores selection:**
```javascript
window.bookingContext = {
  providerHash,
  serviceId,
  date,
  startTime,
  endTime
};
```

#### Detailed Flow: Step 5 (Form Submission)

**Step 5: Customer fills contact details & submits**
```html
<form id="bookingForm">
  <input name="first_name" />
  <input name="last_name" />
  <input name="email" />
  <input name="phone" />
  <button type="submit">Confirm Booking</button>
</form>
```

**Frontend (public-booking.js):**
```javascript
document.getElementById('bookingForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const payload = {
    provider_hash: window.bookingContext.providerHash,
    service_id: window.bookingContext.serviceId,
    date: window.bookingContext.date,
    time_slot: window.bookingContext.startTime,
    first_name: formData.get('first_name'),
    last_name: formData.get('last_name'),
    email: formData.get('email'),
    phone: formData.get('phone')
  };
  
  const response = await fetch('/api/v1/public/bookings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  
  const result = await response.json();
  
  if (result.data) {
    // Success: redirect to confirmation page
    window.location.href = `/my-appointments/${result.data.hash}`;
  } else {
    // Failure: show error
    alert(result.error.message);
  }
});
```

#### Backend: Booking Processing

**Backend (PublicSiteController or Api/V1/PublicBookings):**
```php
public function store_booking() {
    // Validate inputs
    $rules = [
        'provider_hash' => 'required|string',
        'service_id' => 'required|integer',
        'date' => 'required|date_format:Y-m-d',
        'time_slot' => 'required|date_format:H:i',
        'email' => 'required|email',
        'phone' => 'required|string'
    ];
    
    if (!$this->validate($rules)) {
        return $this->badRequest('Validation failed', $this->validator->getErrors());
    }
    
    // Lookup provider
    $provider = ProviderModel::where('hash', $request->getPost('provider_hash'))->first();
    if (!$provider) return $this->notFound('Provider not found');
    
    // Create or find customer (matching by email)
    $customer = CustomerModel::where('email', $request->getPost('email'))->first();
    if (!$customer) {
        $customer = new CustomerModel([
            'first_name' => $request->getPost('first_name'),
            'last_name' => $request->getPost('last_name'),
            'email' => $request->getPost('email'),
            'phone' => $request->getPost('phone')
        ]);
        $customer->save();
    }
    
    // Create appointment via booking service
    $appointment = $this->appointmentBookingService->createBooking([
        'provider_id' => $provider->id,
        'customer_id' => $customer->id,
        'service_id' => $request->getPost('service_id'),
        'start_at' => /* construct datetime from date + time_slot */,
        'end_at'   => /* add service duration */,
        'status'   => 'pending'  // or 'confirmed' per policy
    ]);
    
    // Generate public token for rescheduling
    $appointment->public_token = bin2hex(random_bytes(32));
    $appointment->public_token_expires_at = now()->addDays(30);
    $appointment->save();
    
    // Enqueue notification (confirmation email)
    AppointmentNotificationService::enqueueNotificationsForAppointment($appointment);
    
    return $this->created([
        'id' => $appointment->id,
        'hash' => $appointment->hash,
        'status' => $appointment->status
    ]);
}
```

#### After Booking: Customer Access

**Customer receives email with link:**
```
Confirm your appointment: https://app.example.com/my-appointments/abc123def456
```

**Customer visits `/my-appointments/{appointment_hash}`:**
```php
public function viewMyAppointment($appointmentHash) {
    $appointment = AppointmentModel::where('hash', $appointmentHash)->first();
    
    if (!$appointment) return 404('Appointment not found');
    
    return view('public_booking/my_appointment', [
        'appointment' => $appointment,
        'rescheduleLink' => url('/my-appointments/' . $appointment->hash . '/reschedule'),
        'cancelLink' => url('/my-appointments/' . $appointment->hash . '/cancel')
    ]);
}
```

**Customer can:**
- ✅ View appointment details (time, provider, service, location)
- ✅ Reschedule appointment (select new time slot)
- ✅ Cancel appointment
- ❌ Cannot access other customers' appointments (hash-based protection)
- ❌ Cannot see customer names or emails of other bookings

---

## Testing Rules

### Primary Tooling
- PHPUnit.
- Integration tests must use test DB config only.

### Requirements
- Do not run tests against default/live schema.
- Seed deterministic settings for journey tests.
- Coverage-driver warning is not an assertion failure.

---

## Documentation Rules

### Canonical Home
- Repository docs in `docs/`.
- Root `README.md` is top-level documentation entrypoint.

### This File
- `Agent_Context.md` is the root engineering contract file.

---

## Known Tech Debt

| ID | Description | Affected files | Correct fix | Status |
|----|-------------|----------------|-------------|--------|
| TD-01 | Dark mode detection currently uses mixed patterns. | `resources/js/dark-mode.js`, `resources/js/utils/dark-mode-detector.js`, scheduler/chart modules | Standardize on `document.documentElement.dataset.theme === 'dark'`. | open |
| TD-02 | `emitAppointmentsUpdated` consistency is not fully centralized. | status filter and scheduler view modules | Use one shared utility wrapper. | closed — coordinator dispatches `appointment:changed`; `emitAppointmentsUpdated` now restricted to filter-change actions only |
| TD-03 | Toasts are still emitted through drag-drop module helper path. | `resources/js/modules/scheduler/scheduler-drag-drop.js` | Move to canonical toast utility entrypoint. | closed — coordinator owns toast; drag-drop no longer calls showToast for mutations |
| TD-04 | Scheduler loading paths require strict dispatcher discipline. | `resources/js/modules/scheduler/scheduler-core.js` | Route feature calls through `loadData()`. | in-progress |
| TD-05 | Day-view positional values require strict day-view derivation. | `resources/js/modules/scheduler/scheduler-day-view.js` | Compute from day-view Luxon datetime range. | open |
| TD-06 | Duplicate/overlapping currency formatting adapters remain. | `resources/js/currency.js`, scheduler settings/right panel modules | Consolidate around canonical formatter utility. | open |
| TD-07 | `window.unifiedSidebar` default null export can be misused. | `resources/js/unified-sidebar.js` | Provide clear initialized-instance contract. | open |
| TD-08 | logger.js adoption remains partial. | `resources/js/modules/scheduler/logger.js`, modules with raw console usage | Adopt logger utility across modules. | open |
| TD-09 | Delete-preview flow requires ongoing parity checks. | `app/Controllers/UserManagement.php`, `app/Views/user-management/index.php`, route `user-management/delete-preview/{id}` | Keep endpoint, states, and modal behavior aligned. | in-progress |
| TD-10 | Staff/provider calendar scope must remain guarded by regression tests. | `app/Controllers/Api/CalendarController.php`, `app/Services/Appointment/AppointmentQueryService.php` | Enforce/verify staff provider assignment scope in tests. | in-progress |
| TD-11 | Phone normalization is not guaranteed at every input boundary. | customer/user mutation controllers/services | Enforce `PhoneNumberService::normalize()` before persistence. | in-progress |
| TD-12 | Inline style attributes still exist in some templates. | `app/Views/components/dark-mode-toggle.php`, `app/Views/appointments/index.php`, email/error templates | Replace with utility classes or data-* dynamic-color resolver pattern. | open |
| TD-13 | Provider index listing queried `xs_users.role = 'provider'` directly. | `app/Controllers/Api/V1/Providers.php` | Resolved by filtering scoped rows through authoritative provider membership from `getRolesForUser()`. | closed |
| TD-14 | `ProviderSchedule.php::isAuthorized()` used single `$currentUser['role']` checks. | `app/Controllers/ProviderSchedule.php` | Resolved by evaluating merged session `roles[]` plus DB fallback roles from `getRolesForUser()`. | closed |

---

## Debt Prevention Guardrails

### Schema-Drift Guardrails
- Validate runtime schema before changing query assumptions.
- Use schema-safe model/service paths where columns vary by environment.

### DB Isolation Guardrails
- Development runtime uses `database.default.*`.
- Tests use `database.tests.*` only under testing environment.

### AI-Debt Guardrails
- Validate source-of-truth before fixing symptoms.
- Fix adjacent paths that share the same root assumption.
- Explicitly call out unverified gaps.

### Regression Checklist for Schema-Sensitive Changes
```text
[] Did I validate runtime schema for affected tables?
[] Did I avoid non-canonical xs_users assumptions?
[] Did I include compatibility fallbacks where required?
[] Did I verify runtime DB target?
[] Did I lint touched PHP files?
[] For role logic, did I use UserModel::getRolesForUser()?
[] For user forms, did I preserve roles[] checkbox behavior?
[] If a role checkbox is disabled, did I preserve its submitted value another way?
[] Did I sync role changes with xs_user_roles and keep xs_users.role compatibility?
[] Did I avoid dead-code surfaces?
[] Did I document residual risks if full sweep was deferred?
```

---

## Forbidden List

| # | Forbidden | Correct Alternative |
|---|-----------|---------------------|
| 1 | Business logic in controllers | Move logic to `app/Services/` |
| 2 | New scheduling rules in views/JS only | Put rules in scheduling services |
| 3 | App migrations extending framework `Migration` | Extend `App\Database\MigrationBase` |
| 4 | Public URL exposure of numeric customer IDs | Use hash/token reference flows |
| 5 | Bypassing `AppointmentBookingService` in standard flows | Use booking pipeline |
| 6 | Mixing local-time persistence with UTC persistence | Store canonical datetimes in UTC |
| 7 | Hardcoded timezone behavior in scheduling logic | Use timezone/localization services |
| 8 | Introducing new frontend framework casually | Stay with current Vite + JS module stack |
| 9 | Creating new top-level SCSS entrypoint without need | Use `app-consolidated.scss` |
| 10 | Client-side availability invention | Consume service/API availability output |
| 11 | Duplicating settings values in module-local constants | Read from settings/services |
| 12 | Reintroducing appointment `user_id` as customer link | Use `customer_id` |
| 13 | Ad hoc appointment status vocabularies | Use `AppointmentStatus` |
| 14 | UI-only hiding without backend auth | Enforce with route filters + backend checks |
| 15 | Assuming `xs_users.is_active` always exists | Use schema-safe active-state handling |
| 16 | Running tests against default/live DB | Isolate tests with testing DB config |
| 17 | Declaring fixes complete without runtime/schema verification | Validate schema/runtime and adjacent paths |
| 18 | `array_column($rows, 'provider_id')` on staff provider rows | Use `array_column($rows, 'id')` |
| 19 | Defaulting to non-canonical appointment context keys | Use `provider_id` as primary key (plural only as temporary compatibility fallback) |
| 20 | Unassigned staff seeing broad appointment results | Force no-match provider scope for unassigned staff |
| 21 | Direct booking/mutation notification sends via manual sender | Enqueue via event + queue dispatcher |
| 22 | Raw controller SQL for customer ID provider/staff scoping | Delegate to `CustomerAppointmentService` |
| 23 | Displaying raw appointment tokens in customer-facing output | Use opaque reference flows |
| 24 | Reading roles only from `xs_users.role` | Use `UserModel::getRolesForUser($userId)` |
| 25 | Single-role-only role assignment UI in multi-role flows | Use checkbox `roles[]` model |
| 26 | Not syncing role changes to `xs_user_roles` | Sync membership and keep compat primary role |
| 27 | Using old single-role validators for multi-role flows | Validate role arrays against allowed role set |
| 28 | `style=""` attributes or `<style>` tags in views/templates/JS-rendered HTML | Use utility classes; runtime dynamic values via data-* + dynamic-color resolver pattern |
| 29 | Calling `isDarkMode()` or checking `.dark` class in new logic | Check `document.documentElement.dataset.theme === 'dark'` |
| 30 | Implementing user-management logic outside canonical user-management surfaces | Use `app/Controllers/UserManagement.php` and its service layer |
| 31 | Controller JSON response that omits `redirect` when the success destination is the current page | Always include `redirect` in the AJAX response so spa.js can call navigate with forceReload |
| 32 | Disabling a required role checkbox without preserving its submitted value | Add a hidden `roles[]` input or preserve the role server-side so disabled controls do not silently demote the user |
| 33 | Mid-session `session()->set('user', [...])` with a truncated array (missing `roles`/`active_role`) | Use `array_merge(session()->get('user') ?? [], $updates)` to preserve multi-role RBAC context; only `Auth::attemptLogin()` writes the full array from scratch |
| 34 | Rendering the user-management Role column from `xs_users.role` only | Enrich rows with authoritative `roles[]` from `UserModel::getRolesForUser()` / `xs_user_roles` and render all assigned roles |
| 35 | Listing providers by `xs_users.role = 'provider'` alone | Filter scoped user rows through authoritative provider membership from `getRolesForUser()` / `xs_user_roles` |
| 36 | Authorizing provider schedule routes from `$currentUser['role']` alone | Evaluate merged session `roles[]` plus DB fallback roles before allowing admin/provider access |

---

## Before You Change Code

```text
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
```

---

## Suggested Active Phase View

| Area | Status |
|------|--------|
| Setup and deployment flow | Stable |
| Auth, sessions, and role filters | Stable (multi-role RBAC hardened; session write contract enforced v140+) |
| Settings and localization foundation | Stable |
| Services/providers/customers/public booking | Stable |
| Appointment API/service consolidation | Active hardening |
| Scheduler UI debt cleanup | Active |
| Notification platform maturity | Active hardening |
| Documentation governance | Active |

---

## Key Files To Know

### Core Orientation
- `app/Config/Routes.php`
- `app/Config/Filters.php`
- `app/Controllers/Api/BaseApiController.php`
- `app/Database/MigrationBase.php`
- `app/Models/AppointmentModel.php`
- `app/Models/UserModel.php`
- `app/Models/SettingModel.php`
- `app/Services/AvailabilityService.php`
- `app/Services/AppointmentBookingService.php`
- `app/Services/AuthorizationService.php`
- `app/Services/TimezoneService.php`
- `app/Services/UserManagementMutationService.php`
- `app/Services/UserManagementContextService.php`
- `app/Services/Appointment/AppointmentQueryService.php`
- `app/Services/CustomerAppointmentService.php`
- `app/Services/NotificationQueueDispatcher.php`
- `vite.config.js`

### Frontend JS Core
- `resources/js/spa.js` — SPA navigation layer; `navigate()` with `forceReload` option; `submitForm()` JSON contract; opt-out patterns
- `resources/js/app.js` — main authenticated-app bundle; all component init; global window exports
- `resources/js/public-booking.js` — standalone public booking bundle; no spa.js dependency
- `resources/js/modules/app-lifecycle.js` — wires `DOMContentLoaded` + `spa:navigated` to `initializeComponents()`
- `resources/js/utils/url-helpers.js` — `getBaseUrl()` used by both `spa.js` and `app.js` for base-relative URL building

### Key Docs
- `docs/readme.md`
- `docs/architecture/scheduler_ui_architecture.md`
- `docs/architecture/unified_calendar_engine.md`
- `docs/testing/test_runner_guide.md`
- `docs/deployment/releasing.md`

---

## Final Rule

Design and change the system in this order:

```text
1. Data and storage contract
2. Service logic
3. API contract
4. View/UI rendering
```

If the presentation layer change starts before service/data contract clarity, stop and fix boundaries first.

---

## Phase 3: Multi-Role User Management Status

- ✅ `xs_user_roles` migration and backfill
- ✅ User create/edit forms using `roles[]` checkboxes
- ✅ Role sync on create/update mutations
- ✅ `UserModel::getRolesForUser()` authoritative role retrieval with fallback
- ✅ Role switching endpoint and sidebar integration
- ✅ `RoleFilter.php` — route RBAC now checks full `$user['roles']` array via `array_intersect`
- ✅ `AuthorizationService.php` — all `can*()` methods accept `string|array`; `resolveRole()` picks highest privilege
- ✅ `BaseApiController::hasRole()` — checks full session roles array; no longer single-column only
- ✅ `UserManagementContextService::buildEditViewData()` — provider/staff/admin conditionals use full roles array
- ✅ `Api/V1/Providers.php` — provider-identity checks at `services()`, `appointments()`, `uploadProfileImage()` use `getRolesForUser()`
- ✅ `provider-schedule.js` — schedule section initializes from multi-role checkbox DOM; `getActiveRoles()` helper added
- ✅ `Profile.php` — all 3 session writes (`index`, `updateProfile`, `uploadPicture`) now use `array_merge` to preserve `roles` and `active_role`
- ✅ `UserManagement.php` — self-edit session refresh reloads roles from `getRolesForUser()` and writes full `roles`+`active_role` context
- ✅ `UserManagementContextService.php` — index/API user rows are enriched with authoritative `roles[]` membership for multi-role display
- ✅ `app/Views/user-management/index.php` — Role column now renders all assigned roles as badges in both initial PHP render and AJAX reload path
- ✅ `app/Views/user-management/edit.php` — self-admin edit preserves `admin` via hidden `roles[]` input when the visible checkbox is disabled
- ✅ `UserManagementMutationService.php` — self-edit defensively restores `admin` if a disabled checkbox omits it from the payload
- ✅ `ProviderSchedule.php` — standalone provider-schedule auth now evaluates full effective role membership, not the single compatibility role only
- ✅ `Api/V1/Providers.php` — provider listing now resolves provider membership from authoritative role data, not `xs_users.role` alone

### Session Write Contract (enforced from v140+)
Any code path that calls `session()->set('user', [...])` mid-session (outside the initial login) **must** use `array_merge` pattern:
```php
$currentUser = session()->get('user') ?? [];
session()->set('user', array_merge($currentUser, [
    // only the fields being updated
]));
```
Exception: the login path in `Auth::attemptLogin()` writes the full array including `roles` and `active_role` from scratch — that is the only correct place to write the full array without merging.

---

## Agent Notes

- Settings dropdown chevrons must be standardized through shared SCSS selectors (`select.form-input`, `.form-select`) in `resources/scss/components/_forms.scss`, not per-view overrides.
- Keep chevron visuals aligned with header user-menu dropdown scale; adjust shared SVG/background-size once, then rebuild assets.
- Native form-control chrome (date/time/select icons) must follow `data-theme` (`light`/`dark`) via consolidated SCSS in `resources/scss/app-consolidated.scss`.
- Forbidden Rule 28 is strict: do not use inline `style=""` attributes or `<style>` blocks for icon color/size fixes.
- All multi-role RBAC checks (route filters, API base controller, authorization service, user-management context) now use `array_intersect` against the full `$user['roles']` session array. Never reintroduce single `$user['role']` comparisons for authorization decisions.
- When writing new PHP that needs to check if a user has a role: use `in_array($role, $user['roles'] ?? [$user['role'] ?? ''], true)` or delegate to `AuthorizationService` / `BaseApiController::hasRole()`.
- When writing new JS role checks in user-management modules: use `getActiveRoles()` from `provider-schedule.js` as a reference — read from `input[name="roles[]"]:checked` checkboxes, not a single `<select id="role">`.
- **Session write contract (v140+):** Any mid-session call to `session()->set('user', [...])` must use `array_merge(session()->get('user') ?? [], $updates)` to preserve `roles` and `active_role`. Only `Auth::attemptLogin()` writes the full session user array from scratch. Violating this silently destroys multi-role RBAC context — this was the confirmed root cause of the v139 production RBAC regression.
- `Profile.php` is a known audit target: its `index()`, `updateProfile()`, and `uploadPicture()` methods all touch the user session; verified safe as of v140.
- `Profile::updateNotifications()` must preserve SPA success behavior by returning a `redirect` in AJAX responses and keeping the notifications tab active on full reload (`active_tab` flash or `#notifications` hash).
- `UserManagement.php` self-edit path is an audit target: when the logged-in admin edits their own record, the session must be refreshed with a full role reload from `getRolesForUser()` — verified safe as of v140.
- `app/Views/user-management/edit.php` self-admin role lock uses a disabled checkbox for UX only; disabled inputs do not submit, so the hidden `roles[]=admin` field is part of the contract and must not be removed without replacing its submission behavior.
- `app/Views/user-management/index.php` must keep PHP render and AJAX reload parity for role badges; any change to role display must update both paths together.
- `app/Controllers/ProviderSchedule.php` is no longer allowed to make auth decisions from `$currentUser['role']` alone; session `roles[]` plus DB fallback are the required source of truth.
- `app/Controllers/Api/V1/Providers.php` index listing is intentionally schema-safe: it filters scoped user rows via `getRolesForUser()` rather than assuming `xs_users.role = 'provider'` is authoritative.
- Internal provider/staff appointment notifications must go through `NotificationQueueService::enqueueInternalEvent()` and `NotificationQueueDispatcher`; do not introduce direct email sends from controllers or views.
- `NotificationTemplateService` now has a recipient-class split: customer flows use settings/default templates, internal provider/staff flows use `xs_message_templates.recipient_class = 'internal'` rows.
- The provider/staff appointment-notification preference is `xs_users.notify_on_appointments`; admin edits flow through `UserManagementMutationService`, self-service edits flow through `POST /profile/update-notifications`.
- Appointment creation must persist `xs_appointments.stored_timezone` and notification rendering must pass explicit timezone to `TimezoneService::toDisplay(...)` to avoid UTC/session drift in outbound messages.

---

Last updated: 2026-04-13
Status: Active hardening
Phase 3: ✅ Multi-role RBAC fully hardened (RoleFilter, AuthorizationService, BaseApiController, UserManagementContextService, Providers API including authoritative provider listing, provider-schedule.js, ProviderSchedule.php standalone auth, Profile.php, UserManagement.php self-edit, user list multi-role display, self-admin role preservation); session write contract enforced from v140
Phase 4 (2026-04-12): ✅ Canonical Mutation Pipeline (appointment-mutation-coordinator.js singleton; drag-drop + modal status/notes/cancel migrated; appointment:changed event; TD-02 TD-03 closed) + Default Appointment Status Setting (booking.default_appointment_status; migration seeded; settings UI; notification control point documented) + xs_customers schema restored (hash + custom_fields columns added, hashes backfilled) + Material Symbols Rounded added to app layout
Phase 5 (2026-04-13): ✅ Internal provider/staff notification hardening (`xs_users.notify_on_appointments`; internal queue recipients via `recipient_type`/`recipient_user_id`; internal email templates via `recipient_class`; admin + self-service notification preference surfaces; dispatcher resolves internal recipients at send time)

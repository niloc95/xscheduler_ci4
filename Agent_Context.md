# WebScheduler CI4 - Agent Context & Engineering Standards

> Read this document before making structural, architectural, API, scheduling, or UI changes.
> This file is the live engineering contract for this repository.

---

## What You Are Building

WebScheduler CI4 is a professional appointment scheduling system built on CodeIgniter 4.

Architecture: service-oriented CI4 monolith with server-rendered views, REST-style APIs, Vite-managed frontend assets, and a custom scheduler/public-booking experience.

Current state: active hardening. Core workflows are production-capable, with focused debt cleanup still needed in scheduler/frontend internals and selected user-management/calendar seams.

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
- Chart.js 4
- Luxon 3
- Plain JavaScript modules

### Build / Release
- npm scripts for asset build/release flow
- Composer for PHP dependencies
- Packaging via `scripts/package.js`
- Release automation via `scripts/release.js`

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
  - `reset_token`, `reset_expires`, `status`, `profile_image`, `last_login`
- Notes:
  - `status` is the canonical active-state field in this runtime.

##### `xs_user_roles`
- Columns:
  - `id`, `user_id`, `role`, `created_at`
- Notes:
  - Authoritative multi-role membership table.
  - Backfilled from `xs_users.role` in `2026-04-08-000001_CreateUserRolesTable.php`.

##### `xs_customers`
- Columns:
  - `id`, `first_name`, `last_name`, `email`, `phone`, `address`, `notes`, `created_at`, `updated_at`
- Notes:
  - The codebase expects hash-capable customer records for public portal routes such as `/my-appointments/{hash}`.
  - This runtime does not currently expose a `hash` column on `xs_customers`, so public customer portal work should treat that as schema drift and restore the column before relying on hash-based lookup.
  - Internal-only resolution may use `CustomerModel::findByIdentifier()` numeric fallback, but public customer access should not.

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
  - `id`, `business_id`, `event_type`, `channel`, `provider`, `provider_template_id`, `locale`, `subject`, `body`, `is_active`, `created_at`, `updated_at`

##### `xs_notification_queue`
- Columns:
  - `id`, `business_id`, `channel`, `event_type`, `appointment_id`, `status`, `attempts`, `max_attempts`, `run_after`, `locked_at`, `lock_token`, `last_error`, `sent_at`, `idempotency_key`, `correlation_id`, `created_at`, `updated_at`
- Notes:
  - This runtime uses `attempts` and `max_attempts` instead of legacy `attempt_count`.
  - This runtime includes `locked_at`, `lock_token`, and `correlation_id`.
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
- `xs_customers.hash` is absent in this runtime even though public customer portal code expects it; restore schema before relying on `/my-appointments/{hash}` flows.
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

---

## Settings Rules

### Single Source of Truth
- Use `SettingModel` and settings services.
- Active key namespaces: `general.*`, `localization.*`, `booking.*`, `calendar.*`, `notifications.*`, `branding.*`, `security.*`.

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

## API Rules

### Versioning
- Versioned routes under `/api/v1/`.
- Operational routes under `/api/`.

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

### Multi-Role Support (Phase 3)
- Multi-role memberships are stored in `xs_user_roles`.
- `xs_users.role` remains compatibility primary role.
- User forms use checkbox payload `roles[]`.
- Role-switch endpoint: `POST /api/auth/switch-role`.

### Route Filters
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

### Appointment Event Contract
- `appointment_pending` is the canonical event for pending bookings.
- `appointment_confirmed` is the canonical event for confirmed bookings.
- New booking flows must resolve event type from appointment status via `AppointmentStatus::notificationEvent()` instead of hardcoding confirmation.

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
| TD-02 | `emitAppointmentsUpdated` consistency is not fully centralized. | status filter and scheduler view modules | Use one shared utility wrapper. | in-progress |
| TD-03 | Toasts are still emitted through drag-drop module helper path. | `resources/js/modules/scheduler/scheduler-drag-drop.js` | Move to canonical toast utility entrypoint. | open |
| TD-04 | Scheduler loading paths require strict dispatcher discipline. | `resources/js/modules/scheduler/scheduler-core.js` | Route feature calls through `loadData()`. | in-progress |
| TD-05 | Day-view positional values require strict day-view derivation. | `resources/js/modules/scheduler/scheduler-day-view.js` | Compute from day-view Luxon datetime range. | open |
| TD-06 | Duplicate/overlapping currency formatting adapters remain. | `resources/js/currency.js`, scheduler settings/right panel modules | Consolidate around canonical formatter utility. | open |
| TD-07 | `window.unifiedSidebar` default null export can be misused. | `resources/js/unified-sidebar.js` | Provide clear initialized-instance contract. | open |
| TD-08 | logger.js adoption remains partial. | `resources/js/modules/scheduler/logger.js`, modules with raw console usage | Adopt logger utility across modules. | open |
| TD-09 | Delete-preview flow requires ongoing parity checks. | `app/Controllers/UserManagement.php`, `app/Views/user-management/index.php`, route `user-management/delete-preview/{id}` | Keep endpoint, states, and modal behavior aligned. | in-progress |
| TD-10 | Staff/provider calendar scope must remain guarded by regression tests. | `app/Controllers/Api/CalendarController.php`, `app/Services/Appointment/AppointmentQueryService.php` | Enforce/verify staff provider assignment scope in tests. | in-progress |
| TD-11 | Phone normalization is not guaranteed at every input boundary. | customer/user mutation controllers/services | Enforce `PhoneNumberService::normalize()` before persistence. | in-progress |
| TD-12 | Inline style attributes still exist in some templates. | `app/Views/components/dark-mode-toggle.php`, `app/Views/appointments/index.php`, email/error templates | Replace with utility classes or data-* dynamic-color resolver pattern. | open |

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
[] If writing a migration, does it extend MigrationBase?
[] Did I avoid dead-code surfaces and stale symbols?
```

---

## Suggested Active Phase View

| Area | Status |
|------|--------|
| Setup and deployment flow | Stable |
| Auth, sessions, and role filters | Stable |
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
- 🔄 Expand multi-role regression coverage in broader journeys

---

## Agent Notes

- Settings dropdown chevrons must be standardized through shared SCSS selectors (`select.form-input`, `.form-select`) in `resources/scss/components/_forms.scss`, not per-view overrides.
- Keep chevron visuals aligned with header user-menu dropdown scale; adjust shared SVG/background-size once, then rebuild assets.
- Native form-control chrome (date/time/select icons) must follow `data-theme` (`light`/`dark`) via consolidated SCSS in `resources/scss/app-consolidated.scss`.
- Forbidden Rule 28 is strict: do not use inline `style=""` attributes or `<style>` blocks for icon color/size fixes.

---

Last updated: 2026-04-09
Status: Active hardening
Phase 3: ✅ Multi-role core implementation complete; regression/debt cleanup in progress

# WebScheduler CI4 - Agent Context & Engineering Standards

> Read this document before making structural, architectural, API, scheduling, or UI changes.
> These rules are repo-specific and should be treated as the working contract for this codebase.

---

## What You Are Building

WebScheduler CI4 is a professional appointment scheduling system built on CodeIgniter 4.

Architecture: service-oriented CodeIgniter monolith with server-rendered views, REST-style APIs, Vite-managed frontend assets, and a custom scheduler/public-booking experience.

Current stage: active development and refactoring. The foundation, setup flow, settings system, public booking flow, and core scheduling services exist, while controller seams, SPA initialization boundaries, and focused regression coverage are being consolidated across the remaining high-churn surfaces.

### Current Refactor Snapshot
- Settings, notifications, appointments, dashboard, user management, customer management, search, and services have all moved toward explicit constructor seams or extracted service boundaries.
- Appointment form and provider-schedule SPA behavior now live in frontend modules instead of view-local inline scripts.
- Focused regression coverage exists for appointment form SPA re-initialization, provider schedule SPA re-initialization, customer-management CRUD/search/history journeys, and service CRUD/provider-assignment journeys.
- Compatibility hardening is active for mixed-schema environments where internal-user active state may use `status` and/or `is_active`, and where hash columns may not be present in every migrated local database.
- Remaining cleanup is centered on the last legacy controllers and any extracted seams that still lack consistent adoption.

---

## Confirmed Tech Stack

### Backend
- PHP 8.1+
- CodeIgniter 4
- MySQL / MariaDB only
- Session-based authentication
- Role filters via CodeIgniter filters and app-level role checks

### Frontend
- Vite 6
- Tailwind CSS 3.4
- SCSS
- Material Design 3 styling and Material packages
- Chart.js 4
- Luxon 3
- Plain JavaScript modules

### Build / Release
- npm for frontend build and packaging scripts
- Composer for PHP dependencies
- Custom deployment packaging via `scripts/package.js`
- Release automation via `scripts/release.js`

### No Framework Drift
- Do not introduce React, Vue, Alpine, or another frontend framework into this repo unless explicitly requested.
- Do not introduce TypeScript unless a specific scoped change requires it and the team has approved that direction.

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
  Libraries/
  Models/
  Services/
    Appointment/
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

### Deployment Output
```text
public/build/
webschedulr-deploy/
builds/
```

---

## Architecture Rules

### Rule 1 - Controllers Stay Thin
- Controllers coordinate request validation, authorization, and response shaping.
- Business logic belongs in `app/Services/`.
- Repeated data-fetching or mutation flows should be extracted to services instead of copied across controllers.

### Rule 2 - Services Own Business Logic
- Scheduling, booking, availability, timezone conversion, notification dispatch, dashboard metrics, search, and settings workflows belong in services.
- If a controller or view needs non-trivial scheduling logic, the logic is probably in the wrong place.

### Rule 3 - Models Are Data Access Layers
- Models should encapsulate query patterns and persistence concerns.
- Do not turn models into controller replacements.
- All models extend project conventions and must stay compatible with the existing `BaseModel` usage.

### Rule 4 - Migrations Must Extend MigrationBase
- All project migrations must extend `App\Database\MigrationBase`.
- Do not extend the framework `Migration` class directly for app migrations.

### Rule 5 - API Controllers Use the Base API Contract
- API controllers should extend `App\Controllers\Api\BaseApiController` when they follow the standardized API envelope.
- Success responses use:
  - `{ "data": ..., "meta": ... }`
- Error responses use:
  - `{ "error": { "message": ..., "code": ..., "details": ... } }`

### Rule 6 - Views Are Render Layers
- Views should not perform scheduling calculations, status normalization, or timezone business logic.
- Precompute data in services or controllers before passing it into views.

---

## Database Rules

### Core Conventions
- Table prefix is `xs_`.
- MySQL / MariaDB only.
- SQLite is not a supported application database target.

### Customer vs User Split
- `xs_users` stores login-capable staff, providers, and admins.
- `xs_customers` stores booking customers.
- Appointments must use `customer_id` for customers.
- `user_id` on appointments is deprecated and must not be reused.

### Hash-Based Public Identifiers
- Public-facing appointment and customer flows use hashes or public tokens.
- Do not expose sequential numeric IDs in customer-facing URLs if a hash/token flow already exists.

### Settings Storage
- Settings are stored in `xs_settings` as key-value pairs.
- Keys are namespaced, for example:
  - `general.*`
  - `localization.*`
  - `booking.*`
  - `notifications.*`
  - `branding.*`
  - `security.*`

### Complete Current Schema

The current database is centered on appointments, provider availability, public booking, settings, and queued notifications. Use the schema below as the canonical working model when changing data access, migrations, services, or API payloads.

#### Core Identity Tables

##### `xs_users`
- Login-capable internal actors: admin, provider, staff.
- Key columns:
  - `id`
  - `name`
  - `email`
  - `phone`
  - `password_hash`
  - `role`
  - `status`
  - `is_active` (legacy/compatibility schemas)
  - `color`
  - `created_at`
  - `updated_at`
- Notes:
  - This is not the customer table.
  - Providers and staff are modeled here and linked outward through schedules, services, blocked times, and appointments.
  - Active-state checks in services and tests must branch by available column (`is_active` first when present, otherwise normalized `status`).

##### `xs_customers`
- Booking customers and customer-profile data.
- Key columns:
  - `id`
  - `first_name`
  - `last_name`
  - `email`
  - `phone`
  - `address`
  - `notes`
  - `custom_fields`
  - `hash`
  - `created_at`
  - `updated_at`
- Notes:
  - Public-facing customer flows should use `hash`, not the numeric `id`.
  - `CustomerModel` generates `hash` on insert when the column exists.

#### Core Scheduling Tables

##### `xs_services`
- Bookable services.
- Key columns:
  - `id`
  - `name`
  - `description`
  - `duration_min`
  - `price`
  - `buffer_before`
  - `buffer_after`
  - `created_at`
  - `updated_at`
- Notes:
  - `duration_min` is the service duration baseline.
  - `buffer_before` and `buffer_after` must be included in availability logic.

##### `xs_appointments`
- Canonical appointment record.
- Key columns:
  - `id`
  - `customer_id`
  - `provider_id`
  - `service_id`
  - `location_id`
  - `appointment_date`
  - `appointment_time`
  - `start_at`
  - `end_at`
  - `stored_timezone`
  - `status`
  - `notes`
  - `hash`
  - `public_token`
  - `public_token_expires_at`
  - `location_name`
  - `location_address`
  - `location_contact`
  - `created_at`
  - `updated_at`
- Notes:
  - `start_at` and `end_at` are the canonical persisted datetime fields.
  - Stored datetimes are UTC.
  - `customer_id` is canonical. `user_id` is deprecated and must not be used for new logic.
  - `appointment_date` and `appointment_time` still exist for compatibility, but new scheduling logic should anchor on `start_at` and `end_at`.
  - `location_name`, `location_address`, and `location_contact` are snapshot fields copied onto the appointment so historical records remain stable.
  - Public customer flows may use `hash` and time-limited `public_token` fields.

##### `xs_blocked_times`
- Provider or global time exclusions.
- Key columns:
  - `id`
  - `provider_id`
  - `start_at`
  - `end_at`
  - `reason`
  - `created_at`
  - `updated_at`
- Notes:
  - `provider_id = NULL` means a global block.
  - This table was migrated from `start_time` and `end_time` to UTC-backed `start_at` and `end_at`.

#### Availability, Hours, and Location Tables

##### `xs_business_hours`
- Business-hour windows, typically keyed by provider and weekday.
- Key columns:
  - `id`
  - `provider_id`
  - `day_of_week`
  - `is_open`
  - `start_time`
  - `end_time`
  - `breaks_json`
  - `created_at`
  - `updated_at`
- Notes:
  - These values express local business-time concepts and must be interpreted in the configured business timezone.

##### `xs_provider_schedules`
- Provider-specific recurring schedule definition.
- Key columns:
  - `id`
  - `provider_id`
  - `location_id`
  - `day_of_week`
  - `start_time`
  - `end_time`
  - `is_available`
  - `created_at`
  - `updated_at`
- Notes:
  - Unique schedule rows exist per provider/day.
  - `location_id` is nullable and uses `ON DELETE SET NULL` semantics.

##### `xs_locations`
- Business or provider appointment locations.
- Key columns:
  - `id`
  - `provider_id`
  - `name`
  - `address`
  - `city`
  - `state`
  - `postal_code`
  - `country`
  - `phone`
  - `email`
  - `description`
  - `is_active`
  - `is_primary`
  - `created_at`
  - `updated_at`
- Notes:
  - Locations belong to providers and can be referenced both by schedules and appointments.

##### `xs_location_days`
- Day-level hours and availability overrides for a location.
- Key columns:
  - `id`
  - `location_id`
  - `day_of_week`
  - `is_open`
  - `open_time`
  - `close_time`
  - `created_at`
  - `updated_at`
- Notes:
  - Unique per `location_id` and `day_of_week`.

##### `xs_providers_services`
- Provider-to-service pivot.
- Key columns:
  - `provider_id`
  - `service_id`
- Notes:
  - Use this as the authority for which providers can deliver which services.

##### `xs_provider_staff_assignments`
- Provider-to-staff assignment pivot.
- Key columns:
  - `id`
  - `provider_id`
  - `staff_id`
  - `created_at`
  - `updated_at`
- Notes:
  - Used to scope staff access and operational ownership.
  - Protected by a unique provider/staff pairing.

#### Settings and File-Backed Configuration Tables

##### `xs_settings`
- Typed key-value application settings.
- Key columns:
  - `id`
  - `setting_key`
  - `setting_value`
  - `setting_type`
  - `updated_by`
  - `created_at`
  - `updated_at`
- Notes:
  - This is the source of truth for configurable application behavior.
  - `updated_by` links settings changes back to a user when available.

##### `xs_settings_files`
- Binary or file-backed settings payloads.
- Key columns:
  - `id`
  - `setting_key`
  - `filename`
  - `mime`
  - `data`
  - `updated_by`
  - `created_at`
  - `updated_at`
- Notes:
  - Used for settings values that need stored file/blob content rather than plain scalar values.
  - `setting_key` is unique.

#### Notification Policy and Delivery Tables

##### `xs_business_notification_rules`
- Business-level notification policy by event and channel.
- Key columns:
  - `id`
  - `business_id`
  - `event_type`
  - `channel`
  - `is_enabled`
  - `reminder_offset_minutes`
  - `created_at`
  - `updated_at`
- Notes:
  - Unique on `business_id`, `event_type`, and `channel`.

##### `xs_business_integrations`
- Provider/integration configuration for delivery channels.
- Key columns:
  - `id`
  - `business_id`
  - `channel`
  - `provider_name`
  - `encrypted_config`
  - `is_active`
  - `health_status`
  - `last_tested_at`
  - `created_at`
  - `updated_at`
- Notes:
  - Unique on `business_id` and `channel`.
  - Secrets belong in `encrypted_config`, not in plain settings rows.

##### `xs_message_templates`
- Channel-aware message template storage.
- Key columns:
  - `id`
  - `business_id`
  - `event_type`
  - `channel`
  - `provider`
  - `provider_template_id`
  - `locale`
  - `subject`
  - `body`
  - `is_active`
  - `created_at`
  - `updated_at`

##### `xs_notification_queue`
- Queue of notification jobs awaiting dispatch or retry.
- Key columns:
  - `id`
  - `business_id`
  - `appointment_id`
  - `customer_id`
  - `channel`
  - `event_type`
  - `recipient`
  - `payload_json`
  - `status`
  - `run_after`
  - `attempt_count`
  - `last_error`
  - `idempotency_key`
  - `provider_message_id`
  - `created_at`
  - `updated_at`
  - `sent_at`
- Notes:
  - Queue dispatch is the canonical notification execution path.
  - `idempotency_key` is unique and prevents duplicate deliveries.

##### `xs_notification_delivery_logs`
- Immutable-ish delivery result records for attempted sends.
- Key columns:
  - `id`
  - `queue_id`
  - `appointment_id`
  - `customer_id`
  - `channel`
  - `event_type`
  - `recipient`
  - `provider_name`
  - `provider_message_id`
  - `status`
  - `error_code`
  - `error_message`
  - `payload_json`
  - `response_json`
  - `created_at`

##### `xs_notification_opt_outs`
- Channel-specific opt-out registry.
- Key columns:
  - `id`
  - `business_id`
  - `channel`
  - `recipient`
  - `reason`
  - `created_at`
- Notes:
  - Unique on `business_id`, `channel`, and `recipient`.

#### Audit and Traceability Tables

##### `xs_audit_logs`
- Internal audit trail for administrative and user-management actions.
- Key columns:
  - `id`
  - `user_id`
  - `action`
  - `target_type`
  - `target_id`
  - `old_value`
  - `new_value`
  - `ip_address`
  - `user_agent`
  - `created_at`
- Notes:
  - `old_value` and `new_value` are JSON-like text snapshots.
  - Do not store audit-only business history in application tables when this audit surface is the intended trace.

#### Canonical Relationships
- `xs_appointments.customer_id -> xs_customers.id`
- `xs_appointments.provider_id -> xs_users.id`
- `xs_appointments.service_id -> xs_services.id`
- `xs_appointments.location_id -> xs_locations.id`
- `xs_blocked_times.provider_id -> xs_users.id`
- `xs_business_hours.provider_id -> xs_users.id`
- `xs_provider_schedules.provider_id -> xs_users.id`
- `xs_provider_schedules.location_id -> xs_locations.id`
- `xs_locations.provider_id -> xs_users.id`
- `xs_location_days.location_id -> xs_locations.id`
- `xs_providers_services.provider_id -> xs_users.id`
- `xs_providers_services.service_id -> xs_services.id`
- `xs_provider_staff_assignments.provider_id -> xs_users.id`
- `xs_provider_staff_assignments.staff_id -> xs_users.id`
- `xs_settings.updated_by -> xs_users.id` when populated
- `xs_settings_files.updated_by -> xs_users.id` when populated
- `xs_notification_queue.appointment_id -> xs_appointments.id`
- `xs_notification_queue.customer_id -> xs_customers.id`
- `xs_notification_delivery_logs.queue_id -> xs_notification_queue.id`
- `xs_notification_delivery_logs.appointment_id -> xs_appointments.id`
- `xs_notification_delivery_logs.customer_id -> xs_customers.id`
- `xs_audit_logs.user_id -> xs_users.id`

#### Index and Query Expectations
- Appointment availability and calendar queries must be optimized around provider, status, and UTC datetime ranges.
- Public appointment access depends on indexed `hash` and `public_token` lookups.
- Location-day, provider-day, provider-service, provider-staff, and notification-rule uniqueness constraints are part of the domain contract and should not be bypassed in application code.
- Queue dispatch depends on indexed `status`, `run_after`, and idempotency fields.

#### Legacy and Compatibility Rules
- Do not write new logic against appointment `user_id`.
- Do not reintroduce `start_time` and `end_time` on appointments or blocked times; use `start_at` and `end_at`.
- Treat `appointment_date` and `appointment_time` as compatibility fields only when older views or exports still need them.
- When adding new public booking flows, prefer `hash` or `public_token` over raw numeric identifiers.

---

## Timezone & Datetime Rules

> This is one of the most important rules in the repo.

### Canonical Strategy
- Datetimes in the database are stored in UTC.
- Business-local display and calendar semantics are resolved through settings and `TimezoneService`.
- `start_at` and `end_at` are the canonical appointment datetime fields.

### Mandatory Services
- Use `App\Services\TimezoneService` for UTC/local conversions.
- Use `LocalizationSettingsService` or settings-backed services to resolve active timezone and time format.

### Required Behavior
- All comparisons against persisted appointment times should assume UTC storage.
- All business-local concepts like "today", "this week", and "this month" should be evaluated in the configured business timezone before converting query boundaries to UTC.
- Location hours and provider schedules must be interpreted in the configured localization timezone, not in server-default time.

### Forbidden
- Do not hardcode timezone strings in business logic when a settings-backed timezone should be used.
- Do not mix `appointment_date` / `appointment_time` style legacy fields into new scheduling logic when `start_at` / `end_at` are available.
- Do not use raw `strtotime()` as the authoritative scheduling engine for timezone-sensitive logic when `TimezoneService`, `DateTime`, or `DateTimeImmutable` should be used.

---

## Scheduling & Booking Rules

### Single Sources of Truth
- `AvailabilityService` is the source of truth for slot availability.
- `AppointmentBookingService` is the source of truth for appointment creation flows.
- `AppointmentModel` is the core appointment persistence model.

### Booking Pipeline
All booking paths should flow through the same business rules:

1. Validate service and provider context
2. Resolve timezone
3. Compute appointment times
4. Validate business hours
5. Validate slot availability
6. Resolve or create customer
7. Persist appointment
8. Queue notifications

If a booking flow bypasses that pipeline, it is a defect unless there is a documented reason.

### Availability Rules
- Availability calculations must account for:
  - provider hours
  - business hours
  - blocked times
  - existing appointments
  - service duration
  - buffer time
  - optional location constraints
- Client code may display slot data, but it must not invent availability rules locally.

### Appointment Status Rules
- Use the canonical status handling in `App\Services\Appointment\AppointmentStatus`.
- Do not hardcode ad hoc status vocabularies in new controllers, views, or scripts.

---

## Settings Rules

### Single Source of Truth
- Application configuration must flow through `SettingModel` and settings-focused services.
- Read settings with:
  - `SettingModel::getByPrefix()`
  - `SettingModel::getByKeys()`
  - service wrappers such as `LocalizationSettingsService`

### No Direct Config Drift
- Do not duplicate business-hour, localization, or booking-field configuration in multiple places.
- If a value is user-configurable in Settings, do not hardcode a separate module-specific copy.

### High-Impact Settings Consumers
- Scheduler configuration
- Booking field configuration
- Currency and time display
- Notification defaults
- Business hours and booking windows

---

## Frontend Rules

### Rule 1 - Use Existing Entry Points
Current Vite entry points are:
- `resources/js/app.js`
- `resources/scss/app-consolidated.scss`
- `resources/js/material-web.js`
- `resources/js/setup.js`
- `resources/js/dark-mode.js`
- `resources/js/spa.js`
- `resources/js/unified-sidebar.js`
- `resources/js/charts.js`
- `resources/js/public-booking.js`

Do not create a new top-level asset entry point unless the change truly needs one.

### Rule 2 - Preserve SPA Behavior
- In-app navigation is handled by `resources/js/spa.js`.
- When adding interactive pages, make sure page initialization works after SPA content swaps.
- Avoid assuming a full page reload for every screen.

### Rule 3 - Respect Existing Styling Direction
- Tailwind and SCSS are already integrated.
- Use the consolidated SCSS pipeline.
- Do not create a second independent styling system.

### Rule 4 - Avoid Inline Styling Drift
- Prefer existing utility classes and established styling patterns.
- Inline styles should be the exception, not the default.

### Rule 5 - Scheduler UI Is Custom
- The scheduler is custom-built.
- Do not replace it with FullCalendar or another calendar framework unless explicitly requested.
- Luxon is already part of the scheduler/frontend stack and should remain the date-time utility where that code already depends on it.

---

## API Rules

### Versioning
- Versioned API routes live under `/api/v1/`.
- There is also an unversioned `/api/` surface for some scheduler and operational endpoints.
- Do not move or rename API routes casually; route stability matters for the frontend and public flows.

### Response Contract
- Follow the `BaseApiController` envelope for standard API endpoints.
- Keep status codes coherent with response semantics.

### Authorization
- Use route filters and controller/service authorization together.
- Hiding a UI action is not sufficient protection for data access.

### Public Endpoints
- Public booking and customer-portal routes must continue to respect setup gating, rate limiting, and hash/token-based access patterns.

---

## Roles & Access Rules

Current operational roles include:
- `admin`
- `provider`
- `staff`
- `customer`

### Route Filters
- `setup`
- `auth`
- `role:admin`
- `role:admin,provider`
- `role:admin,provider,staff`

### Expectations
- Admin has full system access.
- Provider is scoped to provider-owned or provider-relevant data.
- Staff access is narrower and must be checked at query and service level.
- Customer-facing public access uses hashes/tokens rather than full staff authentication.

### Staff Scope Contract
- Staff are limited to data belonging to their actively-assigned providers via `xs_provider_staff_assignments` (status = `active`).
- The **canonical source** for a staff member's allowed provider IDs is `ProviderStaffModel::getProvidersForStaff($userId, 'active')`. This returns rows where the key for the provider's numeric ID is **`id`** (not `provider_id`).
- Extract provider IDs with `array_column($result, 'id')`, not `array_column($result, 'provider_id')`.
- Pass them to appointment query context as `$context['provider_id'] = $providerIds ?: [0]`. The `AppointmentModel::applyContextScope()` reads **`provider_id`** (singular key) and accepts both scalar and array values.
- **Never** use `'provider_ids'` (plural), `'staff_id'`, or `'filter_by_staff'` as appointment context keys; those keys are not consumed by `applyContextScope`.
- When staff have no active assignments, force `$context['provider_id'] = [0]` to guarantee no appointments are returned rather than silently returning all appointments.
- The reference implementation for staff appointment scoping is `AppointmentQueryService::applyProviderScope()` — use it as a template for any new scope-enforcement code.
- Customer Management for staff must be scoped to customers who have at least one appointment with their assigned providers. Derive the allowed customer IDs via `xs_appointments WHERE provider_id IN (assigned_ids)`, then apply a `whereIn('id', $customerIds)` to the customer query.
- The provider dropdown on the appointment create/edit form must show **only assigned providers** for staff, not the full provider list.
- `AuthorizationService::canManageAppointment()` returns `true` for staff because their data-layer scope already restricts what they can access; do not add a second redundant ownership check for staff.

---

## Notifications Rules

### Delivery Model
- Notifications are queued and dispatched through the notification queue system.
- Do not implement one-off notification paths when existing queue and dispatcher flows should be used.

### Channels
- Email
- SMS
- WhatsApp

### Canonical Services
- `AppointmentNotificationService`
- `NotificationQueueDispatcher`
- `NotificationEmailService`
- `NotificationSmsService`
- `NotificationWhatsAppService`

### Policy
- Notification behavior should be driven by settings and rule services where available.
- Templates and integration settings belong to the notification/settings system, not arbitrary module code.

---

## Testing Rules

### Primary Tooling
- PHPUnit is the test runner.
- Integration tests use a dedicated MySQL/MariaDB test database.

### Commands
- `./vendor/bin/phpunit`
- `npm run test:integration:mysql`
- `php vendor/bin/phpunit tests/unit/Controllers/ServicesControllerTest.php tests/integration/ServicesJourneyTest.php`
- `php vendor/bin/phpunit tests/unit/Controllers/SearchControllerTest.php tests/integration/CustomerManagementJourneyTest.php`

### Requirements
- Do not point tests at a live application schema.
- Keep test DB configuration isolated via `phpunit.xml`, `phpunit.xml.dist`, `phpunit.mysql.xml.dist`, or `.env` overrides.
- Journey tests that depend on AJAX-backed controller flows often need an explicit CSRF cookie, a setup flag under `writable/`, and deterministic settings fixtures.
- Booking-field settings can leak into customer-management validation; seed those settings explicitly in controller journeys instead of assuming ambient DB state.
- A PHPUnit warning about a missing code coverage driver does not mean the assertions failed; it only means coverage data was not collected.

### Migration Rule
- Test-related migrations for the application should still respect project migration conventions.

---

## Documentation Rules

### Canonical Home
- Repository-authored documentation lives in `/docs`.
- `README.md` is the main top-level documentation entrypoint.

### Exception
- This file exists in the root because it is an explicit engineering context document requested for agent and contributor guidance.

### Naming Standard
- Documentation under `/docs` uses lowercase with underscores.

### Before Adding New Docs
- Prefer updating an existing `/docs` file if the information logically belongs there.
- Avoid scattering new one-off markdown files across the repo.

---

## Debt Prevention Guardrails

### Schema-Drift Guardrails (Tech Debt)
- Treat this as canonical for internal users: `xs_users` uses `status` (`active|inactive|suspended`) and does not guarantee an `is_active` column.
- Do not write new `xs_users` query filters that assume `is_active` exists.
- Do not implement provider-loading filters inline in controllers using raw `where('is_active', ...)` clauses; route through schema-safe model/service methods (for example `UserModel::getProviders()`).
- Treat this as canonical for appointment public access: `xs_appointments` is expected to include `hash`, `public_token`, and `public_token_expires_at` for public-safe lookup flows.
- Before changing booking or public booking flows, verify runtime appointment schema with:
  - `php spark db:table xs_appointments`
- If runtime schema drifts from expected appointment columns, prefer restoring schema first; only then add compatibility guards in model/service code.
- For user-active filtering, use one of these patterns:
  - Preferred: model/service methods that encapsulate active-user semantics.
  - Fallback for mixed-schema compatibility: `fieldExists('is_active', 'xs_users')` check, else filter by `status = active`.
- Any change that introduces a new user filter must include a schema-safe path or an explicit migration that adds required columns.
- If a query references a column that is not guaranteed by this contract, the PR must be treated as incomplete.
- Seeder logic that queries `xs_users` must follow the same compatibility contract (`is_active` when available, fallback to `status = active`), not assume one schema shape.

### Database Isolation Guardrails (Tech Debt)
- Development web runtime must resolve to the configured `database.default.*` connection.
- Test execution must use `database.tests.*` only when `CI_ENVIRONMENT=testing`.
- Never run test suites against live/default schema.
- Do not assume migration status is recorded under the same migration group used by web runtime; verify migration history table entries before relying on `php spark migrate` output.
- Before debugging data mismatches, verify active runtime DB with:
  - `php spark db:table xs_users`
  - `php spark db:table xs_appointments`
- Debugging checklist for possible DB crossing:
  - Confirm `php spark env` output.
  - Confirm `.env` `database.default.*` and `database.tests.*` values.
  - Confirm no OS-level overrides for `CI_ENVIRONMENT` and `database.*`.

### AI-Debt Guardrails (Agent and Contributor Quality)
- Do not cargo-cult schema assumptions across files. Validate table/column contracts first.
- Do not patch only one crash site when the same assumption appears in nearby flows; perform a targeted local sweep and fix adjacent paths.
- Do not introduce parallel business rules in controllers if a service/model boundary already exists.
- Prefer additive, reversible refactors over broad rewrites in dirty branches.
- In JOIN-heavy CI4 query builders that select aliased fields (for example `c.email`, `s.name`, `u.name`), prevent identifier rewriting by using raw select projections where needed (for example `select($sql, false)`) and validate generated SQL behavior.
- Any schema-compat helper changes must pass immediate syntax validation (`php -l`) before endpoint checks; parse errors in shared services can cascade into broad API outages.
- Every bug fix should include:
  - source-of-truth validation (schema/config/runtime),
  - minimal code correction,
  - syntax/error verification,
  - and a note of residual risk if full sweep is deferred.
- If an agent cannot verify a path (for example testing bootstrap failure), it must state the gap explicitly and avoid pretending coverage.
- When implementing staff data scoping, always resolve provider IDs from `ProviderStaffModel::getProvidersForStaff()` and store them under the `'provider_id'` context key (singular, accepts array). Do not invent `'staff_id'` or `'filter_by_staff'` context keys — those are not consumed by `applyContextScope`.
- A staff scope fix must cover ALL affected surfaces: list page, dashboard, create/edit form dropdowns, and any management list (e.g., customers). Fixing only one surface is incomplete.

### Regression Checklist for Schema-Sensitive Changes
Use this quick gate before merge:

```text
[] Did I validate the real runtime schema for affected tables?
[] Did I avoid hardcoding non-canonical columns on xs_users?
[] If compatibility logic is needed, did I include safe fallback behavior?
[] Did I verify development runtime DB target with php spark db:table?
[] Did I verify no accidental reliance on testing DB settings in web runtime?
[] Did I lint/validate the touched PHP files and review adjacent call sites?
```

---

## Forbidden List

| # | Forbidden | Correct Alternative |
|---|---|---|
| 1 | Business logic in controllers | Move logic to `app/Services/` |
| 2 | New scheduling rules in views or JS only | Put rules in `AvailabilityService` / booking services |
| 3 | New app migrations extending framework `Migration` | Extend `App\Database\MigrationBase` |
| 4 | Hardcoding customer-facing numeric IDs into public URLs | Use hash/token-based flows |
| 5 | Bypassing `AppointmentBookingService` for standard booking flows | Route booking through the booking pipeline |
| 6 | Mixing local-time storage with UTC storage | Store canonical datetimes in UTC |
| 7 | Hardcoded timezone behavior in new scheduling code | Use `TimezoneService` and localization settings |
| 8 | Introducing a new frontend framework casually | Stay with the existing Vite + JS module approach |
| 9 | Creating new top-level SCSS entrypoints without need | Use `app-consolidated.scss` |
| 10 | Client-side availability invention | Read from API/service-calculated availability |
| 11 | Direct duplication of settings values in module code | Read from `SettingModel` or settings services |
| 12 | Reintroducing `user_id` as the appointment customer link | Use `customer_id` |
| 13 | Ad hoc appointment statuses in controllers/views | Use `AppointmentStatus` |
| 14 | Skipping route filters because the UI hides an action | Enforce access in routes and backend logic |
| 15 | Assuming `xs_users.is_active` exists in new logic | Use canonical `status` or schema-safe fallback checks |
| 16 | Running tests against `database.default.*` by accident | Isolate tests with `database.tests.*` and testing env only |
| 17 | Claiming bug-fix completeness without runtime/schema verification | Verify DB target, schema, and adjacent flows before closure |
| 18 | `array_column($staffProviders, 'provider_id')` on `getProvidersForStaff()` result | Use `array_column($result, 'id')` — the returned column is aliased `id` |
| 19 | Passing staff provider IDs as `'provider_ids'` (plural) to appointment context | Use `'provider_id'` (singular) — `applyContextScope` reads that key only |
| 20 | Staff sees all appointments when unassigned | Set `$context['provider_id'] = [0]` to force empty result instead of no filter |

---

## Before You Change Code

Run this checklist:

```text
[] Does this logic belong in a service instead of a controller or view?
[] Am I using UTC storage semantics for persisted datetimes?
[] Am I reading timezone/localization from settings-backed services?
[] Am I using the canonical appointment fields (`start_at`, `end_at`, `customer_id`, `provider_id`)?
[] Am I preserving the API response contract if this is an API change?
[] Am I reusing the notification queue and booking pipeline instead of inventing a parallel path?
[] Am I respecting route filters and role boundaries?
[] Am I working within the existing Vite entrypoint and SPA initialization model?
[] If I am writing a migration, does it extend `MigrationBase`?
[] Should this documentation update live under `/docs` instead of another random markdown file?
```

---

## Suggested Active Phase View

| Area | Status |
|---|---|
| Setup and deployment flow | Stable |
| Auth, sessions, and role filters | Stable |
| Settings and localization foundation | Stable |
| Services, providers, customers, and public booking | Active |
| Appointment API and service consolidation | Active |
| Scheduler UI refactor and hardening | Active |
| Notification platform maturity | In progress |
| Documentation governance | Stable |

---

## Key Files To Know

### Core orientation
- `app/Config/Routes.php`
- `app/Config/Filters.php`
- `app/Controllers/Api/BaseApiController.php`
- `app/Database/MigrationBase.php`
- `app/Models/AppointmentModel.php`
- `app/Models/SettingModel.php`
- `app/Services/AvailabilityService.php`
- `app/Services/AppointmentBookingService.php`
- `app/Services/TimezoneService.php`
- `vite.config.js`

### Key docs
- `docs/readme.md`
- `docs/architecture/scheduler_ui_architecture.md`
- `docs/architecture/unified_calendar_engine.md`
- `docs/architecture/calendar_engine_api_reference.md`
- `docs/architecture/calendar_engine_implementation_summary.md`
- `docs/scheduler/calendar_engine_quick_start.md`
- `docs/testing/test_runner_guide.md`
- `docs/deployment/releasing.md`

---

## Final Rule

Design and change the system in this order:

1. Data and storage contract
2. Service logic
3. API contract
4. View / UI rendering

Do not reverse that order.

If a change starts from presentation while the service or data contract is still unclear, stop and fix the underlying boundary first.

---

Last updated: 2026-03-29
Status: Active

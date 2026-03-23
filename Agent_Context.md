# WebSchedulr CI4 - Agent Context & Engineering Standards

> Read this document before making structural, architectural, API, scheduling, or UI changes.
> These rules are repo-specific and should be treated as the working contract for this codebase.

---

## What You Are Building

WebSchedulr CI4 is a professional appointment scheduling system built on CodeIgniter 4.

Architecture: service-oriented CodeIgniter monolith with server-rendered views, REST-style APIs, Vite-managed frontend assets, and a custom scheduler/public-booking experience.

Current stage: active development and refactoring. The foundation, setup flow, settings system, public booking flow, and core scheduling services exist, but parts of the scheduler, API surface, and service boundaries are still being consolidated.

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

### Requirements
- Do not point tests at a live application schema.
- Keep test DB configuration isolated via `phpunit.xml`, `phpunit.xml.dist`, `phpunit.mysql.xml.dist`, or `.env` overrides.

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
- `docs/architecture/master_context.md`
- `docs/architecture/scheduler_ui_architecture.md`
- `docs/architecture/settings_architecture.md`
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

Last updated: 2026-03-23
Status: Active
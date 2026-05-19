# GitHub Copilot Instructions — WebScheduler CI4

## Project Overview
WebScheduler is a professional appointment scheduling system built with **CodeIgniter 4** (PHP 8.1+), **MySQL/MariaDB**, **Vite**, **Tailwind CSS 3**, and **Material Design 3**. It serves service-based businesses with multi-channel notifications (email, SMS, WhatsApp via Clickatell/Twilio/Meta Cloud API).

## Architecture

### Layer Structure
- **Controllers** (`app/Controllers/`) — thin; delegate business logic to Services
- **Services** (`app/Services/`) — all business/domain logic lives here (e.g., `SchedulingService`, `AvailabilityService`, `NotificationQueueDispatcher`)
- **Models** (`app/Models/`) — data layer extending CI4's `Model`; all models use `BaseModel`
- **Views** (`app/Views/`) — PHP templates organized by feature; layouts in `app/Views/layouts/`
- **API** (`app/Controllers/Api/`) — extends `BaseApiController`; versioned under `/api/v1/`; returns `{"data":..., "meta":...}` / `{"error":{"message":..., "code":...}}`
- **Commands** (`app/Commands/`) — Spark CLI commands for cron jobs (notification dispatch)

### Users vs Customers Split
`xs_users` = staff/providers/admins (login-capable). `xs_customers` = booking customers (may or may not have login). Appointments link `customer_id → xs_customers` and `provider_id → xs_users`. `user_id` on appointments is **deprecated**; use `customer_id`.

### Hash-Based URLs
All public-facing appointment and customer records use a `hash` slug instead of numeric IDs to prevent enumeration. Generate via model `generateHash()` callback (fires on `beforeInsert`).

## Database

- **Table prefix:** `xs_` (e.g., `xs_appointments`, `xs_users`, `xs_settings`)
- **Database support:** MySQL/MariaDB only. **All migrations must extend `App\Database\MigrationBase`** instead of CI4's `Migration` so shared migration helpers stay available.
- **Run migrations:** `php spark migrate -n App`
- **Settings** stored as key-value in `xs_settings` with typed values (`string|integer|boolean|json`). Keys use dot-notation prefixes: `general.*`, `localization.*`, `booking.*`, `notifications.*`, `branding.*`, `security.*`. Read via `SettingModel::getByPrefix()` or `getValue()`.

## Developer Workflows

### Build Frontend Assets
```bash
npm run dev      # Vite dev server (hot reload)
npm run build    # Production build → public/build/
```
Vite entry points: `resources/js/app.js`, `resources/scss/app-consolidated.scss`, `resources/js/spa.js`, `resources/js/dark-mode.js`, `resources/js/unified-sidebar.js`, `resources/js/public-booking.js`, `resources/js/charts.js`. Built assets land in `public/build/assets/` with a manifest.

### CI4 Spark Commands
```bash
php spark serve                          # Dev server on :8080
php spark migrate -n App                 # Run app migrations
php spark notifications:dispatch-queue   # Cron: enqueue + dispatch notification queue
```

### Packaging / Release
```bash
npm run release:patch   # Bump patch version, tag, package
npm run build:prod      # Build with production config
```

## Routing & Filters
Routes are defined in `app/Config/Routes.php`. Apply filters inline:
- `'filter' => 'auth'` — requires login
- `'filter' => 'role:admin'` — admin only
- `'filter' => 'role:admin,provider'` — admin or provider
- `'filter' => 'setup'` — ensures setup wizard completed first

Four roles: **admin**, **provider**, **staff** (assigned to providers), **customer**.

## Frontend Conventions
- **SPA navigation** is handled by `resources/js/spa.js` — avoid full page reloads for in-app navigation.
- **Dark mode** toggled system-wide via `resources/js/dark-mode.js`; respect CSS classes for theme-aware styling.
- **Material Design 3** components from `@material/` packages; style guide available at `/styleguide`.
- SCSS entry is `resources/scss/app-consolidated.scss`; do not create separate SCSS entry points.

## Notification System
Notifications go through a queue (`xs_notification_queue`) processed by cron. Flow:
1. `AppointmentNotificationService` → enqueues jobs
2. `NotificationQueueDispatcher::dispatch()` → reads queue, routes to `NotificationEmailService` / `NotificationSmsService` / `NotificationWhatsAppService`
3. Results logged in `xs_notification_delivery_logs`; opt-outs in `xs_notification_opt_outs`

## Key Files for Orientation
- [../app/Config/Routes.php](../app/Config/Routes.php) — complete route map
- [../app/Config/Filters.php](../app/Config/Filters.php) — middleware definitions
- [../app/Database/MigrationBase.php](../app/Database/MigrationBase.php) — required base for all migrations
- [../app/Controllers/Api/BaseApiController.php](../app/Controllers/Api/BaseApiController.php) — API response helpers
- [../app/Models/AppointmentModel.php](../app/Models/AppointmentModel.php) — core entity, hash callbacks, conflict detection
- [../app/Models/SettingModel.php](../app/Models/SettingModel.php) — settings key-value store
- [../vite.config.js](../vite.config.js) — all frontend entry points
- [../docs/readme.md](../docs/readme.md) — documentation index

<skills>
<skill>
<name>webscheduler-rules</name>
<description>Mandatory pre-change rules, checklists, quality gates, and forbidden patterns for the WebScheduler CI4 codebase. Use this skill BEFORE making ANY code change to WebScheduler — including refactors, bug fixes, new features, migrations, or view edits. Triggers on phrases like "fix", "change", "refactor", "add", "update", "modify", "implement", "build", or any code edit request in the WebScheduler / Frontend Dev project. Also use when reviewing a PR or running a pre-merge audit.</description>
<file>.github/copilot/skills/rules/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-architecture</name>
<description>WebScheduler CI4 architecture, foundation, design order, global contracts, canonical service catalog, key system files, and the business-context resolver. Use whenever you're designing or modifying service/controller/model boundaries, deciding where new logic belongs, looking up the canonical service for a domain, or resolving the active business ID. Triggers on phrases like "where should this live", "which service", "service boundary", "design", "architecture", "is there already a service for", "current_business_id", "business context", or any new feature scaffolding in the WebScheduler / Frontend Dev project.</description>
<file>.github/copilot/skills/architecture/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-auth-rbac</name>
<description>WebScheduler authentication, authorization (RBAC), session management, route filters, and auth hardening (inactivity monitor, session ping, failed-login lockout). Use whenever you're touching login/logout flows, role checks, permissions, session reads/writes, route filter configuration, the `/auth` controller, or anything involving `xs_users.role`, `xs_user_roles`, or `session()->get('user')`. Triggers on phrases like "auth", "login", "permission", "role", "RBAC", "session", "filter", "admin only", "provider can", "staff cannot", "logged in", "inactivity", "lockout", or any work in `app/Controllers/Auth.php` or `app/Filters/`.</description>
<file>.github/copilot/skills/auth-rbac/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-api-contract</name>
<description>WebScheduler API contracts — response envelopes, versioning, base controller responsibilities, public endpoint guardrails, and the SPA form JSON response contract. Use whenever you're adding, modifying, or reviewing API endpoints, controllers that return JSON, response shapes, error handling, or the JSON returned to SPA-intercepted form submits. Triggers on phrases like "API", "endpoint", "JSON response", "envelope", "BaseApiController", "/api/v1", "SPA form", "redirect after save", "AJAX", or any work in `app/Controllers/Api/`.</description>
<file>.github/copilot/skills/api-contract/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-frontend</name>
<description>WebScheduler frontend contract — Vite entry points, SPA lifecycle and navigation, dark mode (FOUC prevention + Tailwind class strategy), shared fetch layer, profile surface, avatar/initials system, design system (`xs-card`, `xs-btn`, `xs-actions-container`), `layouts/app` layout rules, and appointments toolbar layout. Use whenever you're working in `resources/js`, `resources/scss`, `app/Views/`, the SPA layer, Vite config, theme toggles, view layouts, avatars, or any styling/layout decision. Triggers on phrases like "view", "layout", "SPA", "Vite", "dark mode", "theme", "Tailwind", "SCSS", "avatar", "initials", "xs-card", "xs-btn", "appointments view", "toolbar", "scripts section", "DOMContentLoaded", "spa:navigated", or any JS/CSS/template change.</description>
<file>.github/copilot/skills/frontend/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-scheduling</name>
<description>WebScheduler appointments and scheduling contract — booking pipeline, status → notification event mapping, scheduler refresh and mutation semantics, business hours architecture (global vs per-provider), availability and slot generation pipeline, calendar architecture and data flow, server vs non-server mode loading. Use whenever you're touching appointments, the scheduler, availability checks, business hours, the calendar grid, slot generation, day/week/month views, blocked times, or anything that reads `xs_appointments`, `xs_business_hours`, `xs_blocked_times`, or `xs_provider_schedules`. Triggers on phrases like "appointment", "booking", "schedule", "scheduler", "calendar", "availability", "slot", "business hours", "working hours", "blocked time", "DayView", "WeekView", "AvailabilityService", "AppointmentBookingService", or any work involving when/where/how an appointment is created or displayed.</description>
<file>.github/copilot/skills/scheduling/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-notifications</name>
<description>WebScheduler notifications contract — canonical services, queue-first delivery, dispatch architecture, event/template rules, queue record contract, internal recipient resolution, reminder offsets behavior (independent processing, schedule fingerprint, stale cancellation), template loading and placeholders, MailerService unified email transport, and business-ID scoping. Use whenever you're touching notifications, email/SMS/WhatsApp delivery, the notification queue, reminders, templates, MailerService, or anything in `app/Services/Notification*` or `app/Services/Mailer*`. Triggers on phrases like "notification", "email", "SMS", "WhatsApp", "queue", "reminder", "template", "placeholder", "dispatch", "cron", "MailerService", "NotificationQueue", "appointment_reminder", "idempotency", "schedule fingerprint", "opt-out", "SMTP", "Mailpit".</description>
<file>.github/copilot/skills/notifications/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-database</name>
<description>WebScheduler database schema and migration rules — all 22 runtime tables (identity, scheduling, availability, settings, notifications, audit, custom fields), canonical relationships, compatibility rules, timezone integrity (UTC storage, display conversion via TimezoneService), migration base requirement, and schema-drift safety. Use whenever you're writing or modifying SQL, queries, migrations, models, or anything that reads/writes `xs_*` tables, or anywhere datetimes are stored or converted. Triggers on phrases like "schema", "table", "column", "migration", "xs_", "foreign key", "relationship", "UTC", "timezone", "TimezoneService", "start_at", "stored_timezone", "MigrationBase", "schema drift", "SQLite", "MariaDB", "MySQL".</description>
<file>.github/copilot/skills/database/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-public-booking</name>
<description>WebScheduler public booking — public routes, public APIs, security rules, policy/legal contract, and custom field required semantics. Use this skill for any work on the public-facing `/booking` flow, `/my-appointments/{hash}`, the `/r/{reference}` short link, public booking APIs (`/api/v1/public/...`), or the public booking JS module. Covers all public routes, the public API surface, security rules (no numeric customer IDs in URLs, hash/token only, CSRF on submit, provider+service-scoped slot queries), policy enforcement (`business.reschedule`, `business.cancel` server-side; `business.future_limit` is public-channel-only), the `legalPolicies` object structure and rendering rules, the `/booking/legal` page contract, the duplicate-policy-block prohibition, and the Custom Field Required Semantics contract. Trigger on any mention of "public booking", "/booking", "/my-appointments", "/r/", "public route", "legal policy", "reschedule policy", "cancel policy", "future limit", "custom field", "required field", or when editing `PublicBookingService`, `BookingLinkService`, `BookingSettingsService`, `resources/js/public-booking.js`, or `app/Views/public-site/`.</description>
<file>.github/copilot/skills/public-booking/SKILL.md</file>
</skill>
<skill>
<name>webscheduler-operations</name>
<description>WebScheduler operations — spark commands, testing rules, known debt, audit/hardening progress board, and the next hardening queue. Use this skill when running CLI commands, dispatching the notification queue, running audits, executing migrations, running the test suite, looking up known debt items, or checking the recent behavior-hardening log. Covers the core runtime commands (`php spark serve`, `php spark migrate -n App`, `notifications:dispatch-queue`, `notifications:test-reminder`, `notifications:backfill-templates`, `notifications:purge-delivery-logs`, `notifications:export-delivery-logs`, `notifications:repair-business-id`, `audit:provider-assignments`, `audit:reminder-pipeline`, `audit:purge-logs`, `npm run dev`, `npm run build`), PHPUnit testing rules (test DB only, never live schema), known active debt themes, the verified-in-current-pass audit board, the remaining debt list, and the next hardening queue. Trigger on any mention of "spark", "command", "audit", "test", "PHPUnit", "Vitest", "debt", "hardening", "queue dispatch", "migration", "npm run", or planning/tracking work.</description>
<file>.github/copilot/skills/operations/SKILL.md</file>
</skill>
</skills>

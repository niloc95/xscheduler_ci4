# GitHub Copilot Instructions — WebSchedulr CI4

## Project Overview
WebSchedulr is a professional appointment scheduling system built with **CodeIgniter 4** (PHP 8.1+), **MySQL/SQLite**, **Vite**, **Tailwind CSS 3**, and **Material Design 3**. It serves service-based businesses with multi-channel notifications (email, SMS, WhatsApp via Clickatell/Twilio/Meta Cloud API).

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
- **Dual-database support:** MySQL (production) and SQLite (zero-config dev). **All migrations must extend `App\Database\MigrationBase`** instead of CI4's `Migration` to get automatic SQLite compatibility (strips `UNSIGNED`, `AFTER`, converts `ENUM→VARCHAR`, `JSON→TEXT`).
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
php spark notifications:send-reminders   # Cron: appointment reminders
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
- [app/Config/Routes.php](app/Config/Routes.php) — complete route map
- [app/Config/Filters.php](app/Config/Filters.php) — middleware definitions
- [app/Database/MigrationBase.php](app/Database/MigrationBase.php) — required base for all migrations
- [app/Controllers/Api/BaseApiController.php](app/Controllers/Api/BaseApiController.php) — API response helpers
- [app/Models/AppointmentModel.php](app/Models/AppointmentModel.php) — core entity, hash callbacks, conflict detection
- [app/Models/SettingModel.php](app/Models/SettingModel.php) — settings key-value store
- [vite.config.js](vite.config.js) — all frontend entry points
- [docs/README.md](docs/README.md) — documentation index

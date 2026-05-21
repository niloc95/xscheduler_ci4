---
name: webscheduler-database
description: WebScheduler database schema and migration rules — all 22 runtime tables (identity, scheduling, availability, settings, notifications, audit, custom fields), canonical relationships, compatibility rules, timezone integrity (UTC storage, display conversion via TimezoneService), migration base requirement, and schema-drift safety. Use whenever you're writing or modifying SQL, queries, migrations, models, or anything that reads/writes `xs_*` tables, or anywhere datetimes are stored or converted. Triggers on phrases like "schema", "table", "column", "migration", "xs_", "foreign key", "relationship", "UTC", "timezone", "TimezoneService", "start_at", "stored_timezone", "MigrationBase", "schema drift", "SQLite", "MariaDB", "MySQL".
---

# WebScheduler — Database Schema & Migration Rules

## 1. Runtime DB Conventions

- **Prefix:** `xs_`
- **Runtime DB:** MySQL/MariaDB only
- **SQLite is not supported** as runtime DB
- Runtime schema is authoritative
- Preferred charset/collation for installs: `utf8mb4` / `utf8mb4_unicode_ci`
- **Total application tables: 22**

## 2. Table Catalog by Domain

### 2.1 Identity Tables

#### `xs_users`

Columns: `id`, `name`, `title` (nullable, added 2026-04-23), `email`, `phone`, `password_hash`, `role`, `created_at`, `updated_at`, `color`, `reset_token`, `reset_expires`, `status`, `profile_image`, `bio` (text, nullable, added 2026-04-23), `education` (text, nullable, added 2026-04-23), `qualifications` (text, nullable, added 2026-04-23), `slug` (unique, nullable, added 2026-04-23), `last_login`, `notify_on_appointments`

Notes:
- `status` is canonical active-state field
- `notify_on_appointments` controls provider/staff appointment notices
- `slug` is unique per provider; used in public booking URL `/booking/{serviceSlug}/{providerSlug}`
- `title`, `bio`, `education`, `qualifications` are public profile fields for provider profile pages

#### `xs_user_roles`

Columns: `id`, `user_id`, `role`, `created_at`

Notes:
- **Authoritative role membership table**
- Backfilled from `xs_users.role` in migration `2026-04-08-000001_CreateUserRolesTable.php`

#### `xs_customers`

Columns: `id`, `hash`, `first_name`, `last_name`, `email`, `phone`, `address`, `notes`, `created_at`, `updated_at`, `custom_fields`

Notes:
- `hash` is 64-char unique slug for public routes
- Index `idx_customers_hash` is present
- `custom_fields` stores JSON map in text field (legacy; per-appointment custom fields now also stored in `xs_appointment_custom_fields`)
- `email` has unique index `idx_customers_email_unique` (added 2026-04-30)

### 2.2 Scheduling Tables

#### `xs_services`

Columns: `id`, `name`, `slug` (unique, nullable, added 2026-04-23), `description`, `duration_min`, `buffer_before`, `buffer_after`, `price`, `created_at`, `updated_at`, `category_id`, `active`

Notes:
- `slug` used in public booking URL `/booking/{serviceSlug}` and `/booking/{serviceSlug}/{providerSlug}`

#### `xs_categories`

Columns: `id`, `name`, `description`, `color`, `created_at`, `updated_at`, `active`

#### `xs_appointments`

Columns: `id`, `provider_id`, `service_id`, `start_at`, `end_at`, `stored_timezone`, `status`, `notes`, `hash`, `public_token`, `public_token_expires_at`, `created_at`, `updated_at`, `reminder_sent`, `customer_id`, `location_id`, `location_name`, `location_address`, `location_contact`

Notes:
- Customer linkage is `customer_id → xs_customers.id`
- **Do not use deprecated appointment `user_id` linkage in new logic** — caught by grep check

#### `xs_blocked_times`

Columns: `id`, `provider_id`, `start_at`, `end_at`, `reason`, `created_at`, `updated_at`

### 2.3 Availability and Location Tables

#### `xs_business_hours`

Columns: `id`, `provider_id`, `weekday`, `start_time`, `end_time`, `breaks_json`, `created_at`, `updated_at`

Notes:
- Runtime uses **`weekday`**, not `day_of_week`
- **NO global-only rows.** Every row has a `provider_id`. Querying without a `provider_id` filter returns an arbitrary provider's row — never a "global" hour. See `scheduling` skill §8.

#### `xs_provider_schedules`

Columns: `id`, `provider_id`, `day_of_week`, `start_time`, `end_time`, `break_start`, `break_end`, `is_active`, `created_at`, `updated_at`

Notes:
- Runtime does **not** include `location_id`

#### `xs_locations`

Columns: `id`, `provider_id`, `name`, `address`, `city` (nullable, added 2026-04-23), `area` (nullable, added 2026-04-23), `contact_number`, `is_primary`, `is_active`, `created_at`, `updated_at`

#### `xs_location_days`

Columns: `id`, `location_id`, `day_of_week`

Notes:
- Runtime currently exposes **only these 3 columns**
- Treat as schema-incomplete for location-hours redesign work

#### `xs_providers_services`

Columns: `provider_id`, `service_id`, `created_at`

#### `xs_provider_staff_assignments`

Columns: `id`, `provider_id`, `staff_id`, `assigned_at`, `assigned_by`, `status`

### 2.4 Settings Table

#### `xs_settings`

Columns: `id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`

Notes:
- Typed key-value store
- Key namespaces: `general.*`, `localization.*`, `booking.*`, `calendar.*`, `notifications.*`, `branding.*`, `security.*`
- Runtime may omit `updated_by` in some environments
- **Read via `SettingModel::getByKeys(['key'])`** — `getValue()` does not exist

### 2.5 Notification Tables

#### `xs_business_notification_rules`

Columns: `id`, `business_id`, `event_type`, `channel`, `is_enabled`, `reminder_offset_minutes`, `created_at`, `updated_at`

#### `xs_business_integrations`

Columns: `id`, `business_id`, `channel`, `provider_name`, `encrypted_config`, `metadata`, `is_active`, `health_status`, `last_tested_at`, `created_at`, `updated_at`

Notes:
- `channel` ENUM: `email`, `sms`, `whatsapp`, `webhook`, `google_calendar`, `stripe`, `zoom`, `slack`, `jitsi`, `payfast` (expanded 2026-05-20)
- `metadata` — nullable TEXT, added 2026-05-20 (webhook last-delivery tracking)
- Unique index: `uniq_integration_business_channel_provider` on `(business_id, channel, provider_name)` — replaced original `uniq_integration_business_channel` to allow multiple webhook endpoints per business
- `encrypted_config` — AES-encrypted JSON. For Google Calendar includes `client_id`, `client_secret`, `access_token`, `refresh_token`, `token_expiry`. Credentials are admin-configured via UI, never read from .env.

#### `xs_message_templates`

Columns: `id`, `business_id`, `event_type`, `channel`, `provider`, `provider_template_id`, `locale`, `recipient_class`, `subject`, `body`, `is_active`, `created_at`, `updated_at`

Notes:
- `recipient_class` separates customer vs internal templates

#### `xs_notification_queue`

Columns: `id`, `business_id`, `channel`, `event_type`, `appointment_id`, `recipient_type`, `recipient_user_id`, `status`, `attempts`, `max_attempts`, `run_after`, `locked_at`, `lock_token`, `last_error`, `sent_at`, `idempotency_key`, `correlation_id`, `created_at`, `updated_at`, `reminder_offset_minutes`, `schedule_fingerprint`

Notes:
- Uses `attempts`/`max_attempts` (not legacy `attempt_count`)
- Includes `locked_at`, `lock_token`, `correlation_id`
- Internal rows resolve recipient from `xs_users` at dispatch
- Reminder rows include `reminder_offset_minutes` and `schedule_fingerprint` (added 2026-04-21 migration)
- Dispatcher cancels a reminder row if `schedule_fingerprint` no longer matches the live appointment

#### `xs_notification_delivery_logs`

Columns: `id`, `business_id`, `queue_id`, `correlation_id`, `channel`, `event_type`, `appointment_id`, `recipient`, `provider`, `status`, `attempt`, `error_message`, `created_at`, `updated_at`

#### `xs_notification_opt_outs`

Columns: `id`, `business_id`, `channel`, `recipient`, `reason`, `created_at`, `updated_at`

### 2.6 Custom Fields Table

#### `xs_appointment_custom_fields`

Columns: `id`, `appointment_id`, `field_key`, `value` (text, nullable), `created_at`, `updated_at`

Notes:
- Added 2026-04-30 (`CreateAppointmentCustomFieldsTable` migration)
- Unique index on `(appointment_id, field_key)` — `idx_appt_custom_field_unique`
- FK: `appointment_id → xs_appointments.id` ON DELETE CASCADE
- Stores per-appointment custom field values; replaces reliance on `xs_customers.custom_fields` for appointment-scoped data
- Backfilled from legacy customer custom fields on migration
- Model: `App\Models\AppointmentCustomFieldModel`

### 2.7 Audit Table

#### `xs_audit_logs`

Columns: `id`, `user_id`, `action`, `target_type`, `target_id`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`

Notes:
- Verify table name as `xs_audit_logs` in mixed environments

## 3. Canonical Relationships

- `xs_appointments.customer_id → xs_customers.id`
- `xs_appointments.provider_id → xs_users.id`
- `xs_appointments.service_id → xs_services.id`
- `xs_appointments.location_id → xs_locations.id`
- `xs_appointment_custom_fields.appointment_id → xs_appointments.id` (CASCADE)
- `xs_business_hours.provider_id → xs_users.id`
- `xs_provider_schedules.provider_id → xs_users.id`
- `xs_locations.provider_id → xs_users.id`
- `xs_location_days.location_id → xs_locations.id`
- `xs_providers_services.provider_id → xs_users.id`
- `xs_providers_services.service_id → xs_services.id`
- `xs_provider_staff_assignments.provider_id → xs_users.id`
- `xs_provider_staff_assignments.staff_id → xs_users.id`
- `xs_user_roles.user_id → xs_users.id`
- `xs_notification_queue.appointment_id → xs_appointments.id`
- `xs_notification_delivery_logs.queue_id → xs_notification_queue.id`
- `xs_audit_logs.user_id → xs_users.id`

## 4. Compatibility Rules

- Do not write new logic against deprecated appointment `user_id` linkage.
- Keep scheduling logic on `start_at`/`end_at`.
- Use schema-safe fallbacks where runtime columns vary.
- `xs_business_hours` uses `weekday` (not `day_of_week`) in this runtime.
- `xs_customers.hash` and `xs_customers.custom_fields` were restored and backfilled (2026-04-12).
- `xs_customers.email` has a unique index (added 2026-04-30); **enforce uniqueness on customer create/update**.
- `xs_provider_schedules` does not include `location_id` in this runtime.
- `xs_location_days` is currently a minimal 3-column table.
- `xs_notification_queue` uses modern locking/idempotency columns; do not assume legacy queue columns.
- `xs_business_hours` has NO global-only rows. Every row has a `provider_id`. Do not query this table without a `provider_id` filter expecting a global business hour result.
- Global business hours (system-wide outer bounds) live in `xs_settings` keys `business.work_start` and `business.work_end`, **NOT** in `xs_business_hours`.
- `xs_appointment_custom_fields` is the normalized store for per-appointment custom field values (added 2026-04-30). `xs_customers.custom_fields` remains as customer-level storage.

## 5. Timezone Integrity Rules (Single Source of Truth)

**Rule:** All datetime values stored in `xs_*` tables are **UTC**. Convert to local only at display time.

**Single source of truth:** `localization.timezone` in `xs_settings` (read via `LocalizationSettingsService::getTimezone()`).

### 5.1 Canonical PHP Service

`TimezoneService` — use these methods and **no others** for datetime conversion:

- `TimezoneService::toDisplay($utcString, $tz)` — UTC → display timezone string (`Y-m-d H:i:s`)
- `TimezoneService::toStorage($localString, $tz)` — local → UTC for DB writes
- `TimezoneService::businessTimezone()` — reads `localization.timezone`, cached per-request

### 5.2 Never Do

- `new \DateTime($localString)` without a `\DateTimeZone` argument when the string is in a non-UTC timezone
- Pass `start_at` (UTC) directly to template rendering; always convert via `toDisplay()` first
- Use `date()` / `new \DateTime()` without explicit timezone when building notification content

### 5.3 Notification Pipeline Contract

- `NotificationQueueDispatcher` converts `start_at` (UTC) → `start_datetime` (display TZ local string) before calling template service
- `NotificationQueueDispatcher` passes `display_timezone` key in all `$templateData` arrays
- `NotificationTemplateService::buildPlaceholders()` creates `new \DateTime($data['start_datetime'], new \DateTimeZone($data['display_timezone'] ?? 'UTC'))` — always explicit timezone
- Google Calendar links require UTC: convert `start_datetime` from display TZ → UTC before formatting with `\Z`

### 5.4 JS Contract

- `window.appTimezone` is set by `SettingsManager` from `/api/v1/settings/localization`
- All scheduler views (`SchedulerCore`, `DayView`) parse API datetimes as UTC via Luxon: `DateTime.fromISO(val, {zone:'utc'}).setZone(appTimezone)`
- Public booking JS uses `context.timezone` (from `PublicBookingService::buildViewContext()`) — **do not omit this key**
- `X-Client-Timezone` / `client_timezone` are browser hints only; `localization.timezone` always takes priority

### 5.5 AvailabilityService Contract

- `isSlotAvailable()` `$timezone` parameter is `?string` with `null` resolving via `TimezoneService::businessTimezone()`
- Always pass the correct business/booking timezone explicitly; do not rely on the default

## 6. Migration and Schema-Drift Rules

### 6.1 Migration Base Requirement

**All app migrations must extend `App\Database\MigrationBase`.** Migrations extending the framework `Migration` class are a forbidden pattern.

### 6.2 Migration Run Command

```bash
php spark migrate -n App
```

### 6.3 Schema-Drift Safety

- Validate runtime columns before making assumptions in mixed environments.
- Keep model/service fallback patterns when optional columns may be absent.
- Avoid hard assumptions on legacy or removed columns.

## 7. Pre-Merge Database Grep Checks

```bash
# Detect unfiltered xs_business_hours queries
rg "table\('business_hours'\)|from.*xs_business_hours" app/

# Detect SettingModel::getValue() calls (method does not exist)
rg "->getValue\(" app/Services app/Controllers app/Models

# Detect deprecated appointment linkage usage
rg "appointments\.user_id|\buser_id\b" app/ resources/
```

Any result must be reviewed and justified or fixed.

## 8. Cross-Skill References

- Business hours architecture and slot generation pipeline → `scheduling` skill (owner)
- Queue field semantics → `notifications` skill (owner)
- `current_business_id()` resolver → `architecture` skill (owner)

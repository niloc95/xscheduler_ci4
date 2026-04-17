---
title: WebScheduler CI4 - Consolidated Engineering Contract
version: 2.0
status: Active hardening
last_updated: 2026-04-15
source_documents:
  - Agent_Context.md
  - Agent_Context_Restructured.md
  - restructure_diff.md
purpose: Single source of truth for agents, developers, and architects
---

# WebScheduler CI4 - Agent Context v2

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

- resources/js/app.js
- resources/js/spa.js
- resources/js/public-booking.js
- resources/js/dark-mode.js
- resources/js/charts.js
- resources/js/unified-sidebar.js
- resources/js/material-web.js
- resources/js/setup.js
- resources/scss/app-consolidated.scss

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

Use document.documentElement.dataset.theme as canonical theme source.

### 6.5 Styling Contract

- Tailwind + consolidated SCSS only
- No inline style attributes in app-facing templates

## 7) Backend Service Boundaries

### 7.1 Canonical Services

- AvailabilityService
- AppointmentBookingService
- AppointmentQueryService
- AppointmentStatus
- AuthorizationService
- TimezoneService
- UserManagementMutationService
- UserManagementContextService
- CustomerAppointmentService
- MailerService
- NotificationQueueDispatcher

### 7.2 Key System Files

- app/Config/Routes.php
- app/Config/Filters.php
- app/Controllers/Api/BaseApiController.php
- app/Database/MigrationBase.php
- app/Models/AppointmentModel.php
- app/Models/UserModel.php
- app/Models/SettingModel.php
- app/Services/MailerService.php
- resources/js/spa.js
- resources/js/app.js
- resources/js/public-booking.js
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

### 8.3 Status to Notification Event Mapping

Canonical source: AppointmentStatus::notificationEvent()

| Status | Event |
| --- | --- |
| pending | appointment_pending |
| confirmed | appointment_confirmed |
| completed | appointment_confirmed |
| cancelled | appointment_cancelled |
| no_show | appointment_no_show |
| rescheduled | appointment_rescheduled |

### 8.4 Scheduler Refresh Semantics (Critical)

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

- GET /book
- GET /book/{serviceSlug}
- GET /book/{serviceSlug}/{providerHash}
- GET /my-appointments/{hash}

### 10.2 Public APIs

- GET /api/v1/public/services
- GET /api/v1/public/providers
- GET /api/v1/public/availability
- POST booking submit route

### 10.3 Public Security Rules

- Never expose numeric customer IDs in URLs
- Use hash/token references only
- Enforce CSRF on form submit
- Keep slot queries scoped by provider and service

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

### 11.3 Dispatch Architecture Notes

- Booking-time notifications are queued synchronously inline during the appointment mutation request; they are not deferred until the first cron tick.
- New booking flows must derive event type from AppointmentStatus::notificationEvent() instead of hardcoding confirmation events.
- `PATCH /api/appointments/{id}/status` fires notifications server-side for each transition.
- Frontend events such as `appointments-updated` do not drive notification delivery; notifications are triggered by backend mutation flows.
- Manual resend flows use `POST /api/appointments/{id}/notify`. Email and SMS resends send immediately, while WhatsApp resends are queued.

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

### 11.9 Template Contract

- Customer and internal templates split by recipient_class
- Internal fallback templates exist for migration drift safety

## 12) Database Schema and Relationships

### 12.1 Runtime DB Conventions

- Prefix: xs_
- Runtime DB: MySQL/MariaDB only
- SQLite is not supported runtime DB
- Runtime schema is authoritative
- Preferred charset/collation for installs: utf8mb4 / utf8mb4_unicode_ci

### 12.2 Total Runtime Tables

Total application tables: 21

### 12.3 Table Catalog by Domain

#### Identity Tables

##### xs_users
Columns:
- id
- name
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
- last_login
- notify_on_appointments

Notes:
- status is canonical active-state field
- notify_on_appointments controls provider/staff appointment notices

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
- custom_fields stores JSON map in text field

#### Scheduling Tables

##### xs_services
Columns:
- id
- name
- description
- duration_min
- buffer_before
- buffer_after
- price
- created_at
- updated_at
- category_id
- active

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

Notes:
- uses attempts/max_attempts (not legacy attempt_count)
- includes locked_at, lock_token, correlation_id
- internal rows resolve recipient from xs_users at dispatch

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
- xs_provider_schedules does not include location_id in this runtime.
- xs_location_days is currently a minimal 3-column table.
- xs_notification_queue uses modern locking/idempotency columns; do not assume legacy queue columns.

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
php spark notifications:dispatch-queue
npm run dev
npm run build
```

## 16) Known Debt and Guardrails

### 16.1 Active Debt Themes

- Scheduler loading-path discipline must remain strict.
- Remaining frontend utility consolidation is ongoing.
- Schema-compatibility in mixed local DB states must remain guarded.

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

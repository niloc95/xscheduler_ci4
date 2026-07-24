---
name: webscheduler-architecture
description: WebScheduler CI4 architecture, foundation, design order, global contracts, canonical service catalog, key system files, and the business-context resolver. Use whenever you're designing or modifying service/controller/model boundaries, deciding where new logic belongs, looking up the canonical service for a domain, or resolving the active business ID. Triggers on phrases like "where should this live", "which service", "service boundary", "design", "architecture", "is there already a service for", "current_business_id", "business context", or any new feature scaffolding in the WebScheduler / Frontend Dev project.
---

# WebScheduler — Architecture & Service Catalog

## 1. What You Are Building

WebScheduler CI4 is a professional appointment scheduling system built on CodeIgniter 4.

**Architecture style:** service-oriented CI4 monolith with server-rendered views, REST-style APIs, Vite-managed frontend assets, and custom scheduler/public-booking modules.

## 2. Confirmed Stack

- **Backend:** PHP 8.1+, CodeIgniter 4
- **Frontend:** Vite 6, Tailwind CSS 3.4, SCSS, plain JavaScript modules, Chart.js 4, Luxon 3
- **Data:** MySQL/MariaDB runtime only
- **Auth:** session-based
- **Authorization:** route filters plus service-level checks

## 3. No Framework Drift

- Do not introduce React/Vue/Alpine without explicit approval.
- Do not introduce TypeScript without explicit scoped approval.

## 4. Final Design Order (Mandatory)

Always design and implement in this order:

1. Data and storage contract
2. Service logic
3. API contract
4. View/UI rendering

If a UI change starts before service/data contract clarity, stop and fix boundaries first.

## 5. Global Contracts

### 5.1 Services Own Business Logic

Scheduling, booking, availability, timezone conversion, notification dispatch, and query scoping belong in services.

### 5.2 Controllers Stay Thin

Controllers parse requests, call services, and shape responses. Controllers are **not** alternate business layers.

### 5.3 Models Stay Data-Focused

Models encapsulate data access and query composition. Keep model behavior compatible with existing schema-safe patterns.

### 5.4 Views Render, They Do Not Decide

Views must not perform business logic, authorization, or timezone calculations.

### 5.5 API Envelope

See the `api-contract` skill for full envelope rules.

Success: `{ "data": {}, "meta": {} }`
Error: `{ "error": { "message": "", "code": "", "details": {} } }`

## 6. Canonical Service Catalog

### Core Scheduling
- `AvailabilityService` — slot generation and business-hours constraint
- `AppointmentBookingService` — booking pipeline (validate → persist → notify)
- `ConflictService` — appointment conflict detection
- `ScheduleValidationService` — provider schedule validation
- `BusinessHoursService` — global business hours from `xs_settings`

### Appointment Namespace (`App\Services\Appointment\`)
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

### Calendar Views (`App\Services\Calendar\`)
- `DayViewService` / `WeekViewService` / `MonthViewService` — server-side view model builders
- `CalendarConfigService` — calendar display configuration
- `TimeGridService` — time-grid computation shared by day/week views
- `ProviderWorkingHoursTrait` — working hours shared logic (has known debt — see `scheduling` skill)

### Users / Customers
- `UserManagementMutationService` — user create/edit/delete mutations
- `UserManagementContextService` — user management page context
- `UserDeletionService` — safe user removal with cascade checks
- `CustomerAppointmentService` — provider/staff customer scoping
- `CustomerService` — customer CRUD; `insertCustomer()` — admin create (audited via `AuditLogModel`); `updateCustomerById()` — admin update (audited); `upsertCustomer()` — find-or-create for the public booking flow only
- `CustomerDeletionService` — safe customer removal
- `CustomerCustomFieldService` — custom field validation
- `ProfilePageService` — `/profile` page view-data builder only (cards, activity feed, stats). Does **not** own mutations; profile saves and audit logging via `logProfileActivity()` live in `Profile.php` controller
- `AuthorizationService` — RBAC helpers

### Services / Categories
- `ServiceMutationService` — service and category create/update/delete; provider assignment with `transStart/transComplete` transaction wrapping; audit logging via `AuditLogModel`. Used by `Services`, `ServiceCategories`, and `Api/V1/Services` controllers

### Public Booking
- `PublicBookingService` — public booking pipeline
- `BookingLinkService` — canonical URL builder for booking links and `/r/{reference}` short links
- `BookingSettingsService` — booking form configuration, custom field validation rules
- `BookingMetricsService` — booking statistics

### Dashboard
- `DashboardService` — dashboard data aggregation
- `DashboardPageService` — dashboard page context builder
- `DashboardApiService` — dashboard API endpoint data
- `AppointmentDashboardContextService` — appointment dashboard context

### Notifications
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

### Settings (`App\Services\Settings\`)
- `SettingsPageService` — settings page context
- `SettingsApiService` — settings API endpoints
- `GeneralSettingsService` — general/localization settings mutations
- `NotificationSettingsService` — notification settings mutations
- `IntegrationSettingsService` — integrations hub mutations (routes by intent + channel to individual integration services)
- `LocalizationSettingsService` — timezone/locale reads

### Integrations (`App\Services\`)
Six services follow the `HandlesNotificationIntegrations` trait pattern — each owns one channel in `xs_business_integrations`:
- `WebhookIntegrationService` — HMAC-signed webhook endpoints; fires `appointment.*` events (channel: `webhook`)
- `GoogleCalendarIntegrationService` — OAuth 2.0 two-way calendar sync; credentials stored encrypted in DB, not .env (channel: `google_calendar`)
- `StripeIntegrationService` — deposit / no-show fee collection; secret key never returned in public data (channel: `stripe`)
- `ZoomIntegrationService` — Server-to-Server OAuth; cached access tokens; `createMeeting()` returns join URL (channel: `zoom`)
- `JitsiIntegrationService` — public or self-hosted Jitsi Meet; optional JaaS credentials; `generateMeetingLink()` uses appointment hash for room name (channel: `jitsi`)
- `PayFastIntegrationService` — South African gateway; sandbox/live toggle; `buildPaymentUrl()` generates signed redirect (channel: `payfast`)

**New controllers:**
- `App\Controllers\OAuthCallback` — `GET /oauth/google/authorize` and `GET /oauth/google/callback`
- `App\Controllers\Api\V1\Integrations` — `GET/POST /api/v1/integrations/{index|save|test|disconnect}`

### Infrastructure
- `MailerService` — sole email transport layer (see `notifications` skill)
- `TimezoneService` — UTC/local conversion (see `database` skill)
- `GlobalSearchService` — cross-entity search
- `PhoneNumberService` — phone number formatting/normalization
- `BookingMetricsService` — booking statistics
- `FormResponseTrait` (`App\Traits\FormResponseTrait`) — standardised JSON envelope for form-surface controllers; `formSuccess(redirect, message)` → HTTP 200; `formError(message, errors, status)` → HTTP 422 by default. Applied to: `Services`, `ServiceCategories`, `CustomerManagement`
- `MutationResult` (`App\Services\MutationResult`) — readonly DTO for mutation service return values; named constructors `::ok(message, redirect, entityId)` and `::fail(message, errors, statusCode)`; `toArray()` for array interop. All **new** mutation services must return this type

## 7. Key System Files

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

## 8. Business Context Resolver Contract (Owner Section)

All business-ID resolution rules live here. The notifications skill references back to this section.

### 8.1 `current_business_id()` Helper

`app/Helpers/permissions_helper.php` exposes `current_business_id(?int $default = null): int`. It is the **single canonical way** to resolve the active business context for the current request. Load it with `helper('permissions')` (already loaded by `BaseController`).

### 8.2 Resolution Priority (Strict)

1. **Request params** — `GET`/`POST` `business_id` or `businessId`
2. **API token context** — `api_identity()?->businessId()`, i.e. `xs_api_keys.business_id` for a Bearer-token request
3. **Session keys** — `session()->get('business_id')` or `session()->get('active_business_id')`
4. **Session user sub-keys** — `session()->get('user')['business_id' | 'active_business_id' | 'businessId' | 'activeBusinessId']`
5. **Fallback** — `$default ?? NotificationCatalog::BUSINESS_ID_DEFAULT` (value `1`)

The result is always `max(1, resolved_value)`.

`api_identity()` (also in `permissions_helper.php`) returns the request-scoped `App\Services\ApiIdentity`, or `null` outside a request context. There is no businesses table yet, so `xs_api_keys.business_id` is forward-compatibility only and always resolves to `1` today.

### 8.2a Acting-Identity Resolution

The same helper file owns identity resolution, which follows the same "token first, session second" rule:

| Helper | Returns |
| --- | --- |
| `current_identity_user()` | Token-bound user, else `session()->get('user')` |
| `current_user_id()` | Token-bound user id, else `session()->get('user_id')` |
| `current_user_role()` / `has_role()` | Derived from `current_identity_user()` |
| `resolve_active_role($roles, $fallback)` | Highest-privilege role (`admin > provider > staff`) — shared by `Auth::login()` and `ApiIdentity` so the two identity shapes cannot drift |

**Never read `session()->get('user_id')` / `session()->get('user')` directly in code reachable from an API route.** See `api-contract` §Authentication for the full rationale.

### 8.3 Service-Level Usage Pattern

Services that scope data to a business must use a protected delegate so the resolver is overridable in unit tests:

```php
protected function resolveBusinessId(): int {
  helper('permissions');
  return current_business_id();
}
```

### 8.4 Current Service Coverage

| Service | Scope switch |
| --- | --- |
| `NotificationCenterService` | `getNotifications()`, `getUnreadCount()` |
| `NotificationSettingsService` | `getIndexData()`, `save()` |

Do not use `NotificationCatalog::BUSINESS_ID_DEFAULT` directly in service methods that serve UI requests. Route all UI-facing scoping through `resolveBusinessId()` / `current_business_id()`.

### 8.5 Auth Channel Exception

`Auth::sendResetEmail()` still passes `NotificationCatalog::BUSINESS_ID_DEFAULT` directly to `MailerService`. This is intentional — password reset has no session context. Do not route it through `current_business_id()`.

## 8A. Currency & Localization Settings Contract (Owner Section)

**Scope:** this section owns **currency** and the **localization settings resolution model**. Timezone *conversion and storage integrity* — UTC rules, `TimezoneService` method contract, `stored_timezone` semantics, notification/JS pipeline — is owned by the `database` skill §5. Do not restate those rules here; link to them.

Currency and timezone are **business-wide singletons** stored in `xs_settings`. There is no per-provider, per-customer, or per-gateway override. A relocation or rebrand is a single settings change plus (for timezone) a data migration.

| Concern | Canonical key | Canonical reader | Full contract |
| --- | --- | --- | --- |
| Currency | `localization.currency` | `LocalizationSettingsService::getCurrency()` / `getCurrencySymbol()` / `formatCurrency()` | this section |
| Timezone | `localization.timezone` | `LocalizationSettingsService::getTimezone()`, `TimezoneService::businessTimezone()` | `database` skill §5 |

### 8A.1 View-Layer Entry Points

`format_currency()`, `get_app_currency()`, and `get_app_currency_symbol()` in `app/Helpers/currency_helper.php` are the **only** approved ways to render money in a view. Never hand-roll `number_format()` with a literal symbol.

For JS, pass the symbol down as a `data-currency-symbol` attribute and format via `resources/js/currency.js`. Do not re-derive the symbol client-side.

### 8A.2 Settings Key Casing

All `xs_settings` keys are **lowercase.dotted**. `SettingModel::getByKeys()` matches keys exactly and returns `null` for a miss, so a mis-cased key silently falls through to the caller's default forever — it does not error. `setting('Localization.currency')` pinned the entire app to ZAR for the lifetime of that bug. Grep check #8 in the `rules` skill guards this.

### 8A.3 `stored_timezone`

Reference only — full contract in `database` skill §5.3. In short: the column records the zone the appointment *belongs to*, which is not the zone its input arrived in, and `createAppointment()` takes the two as separate parameters.

### 8A.4 Payment Gateway Currency

Gateways do **not** own a currency. `PaymentService` charges in `localization.currency` for both Stripe and PayFast. The legacy `currency` key inside the encrypted Stripe config is retained for round-trip compatibility only and is never read. PayFast settles in ZAR exclusively and is gated off in `servicePaymentMeta()` and `initiatePayFast()` when the business bills in anything else.

### 8A.5 Known Limitations

- `format_currency()` always prefixes the symbol and uses `.`/`,` separators. Correct for USD/GBP/ZAR; wrong grouping for EUR/CHF and wrong precision for zero-decimal JPY. `ext-intl` is not a dependency.
- Business hours (`xs_business_hours.start_time`) are naive wall-clock local. Changing `localization.timezone` re-interprets them in the new zone while existing UTC appointments shift. **Relocating a business is a data-migration event, not a settings toggle.**
- `TimezoneService::businessTimezone()` caches in a process-`static`. Long-running spark workers need a restart after a timezone change.

## 9. Contract Ownership Matrix

Each high-risk contract has one owner. Make full-text changes only in the owner; elsewhere keep short references.

| Contract | Owner | Reference-only |
| --- | --- | --- |
| Avatar initials and image rendering | `frontend` skill (§6.8) | architecture, rules |
| RBAC role resolution | `auth-rbac` skill | api-contract, scheduling/users, rules |
| Session write merge rule | `auth-rbac` skill | scheduling/users, rules |
| API envelope and errors | `api-contract` skill | frontend |
| SPA lifecycle/init | `frontend` skill | api-contract, scheduling |
| Scheduler mutation reload semantics | `scheduling` skill | frontend, rules |
| Status → notification event mapping | `scheduling` skill | notifications |
| Queue-first notification flow | `notifications` skill | scheduling, database |
| Public hash/token URL safety | `public-booking` skill | database, rules |
| Database schema and compatibility | `database` skill | all domain skills |
| Migration base requirement | `database` skill | architecture, rules |
| Unified email transport | `notifications` skill (§7.3) | architecture |
| Business hours architecture | `scheduling` skill | database, rules |
| Availability and slot generation | `scheduling` skill | public-booking, database |
| Calendar data flow and grid bounds | `scheduling` skill | frontend |
| Business context resolver | this skill (§8) | notifications |
| Currency + localization settings model | this skill (§8A) | database, scheduling, notifications, public-booking, rules |
| Integrations hub (all 6 channels) | `IntegrationSettingsService` + individual service classes | api-contract, frontend, database |
| Google Calendar OAuth credentials | `GoogleCalendarIntegrationService` (DB-stored, not .env) | integrations |

**Rule:** Do not duplicate full contract text in non-owner sections. Use reference links and concise reminders only.

## 10. Update Protocol for the Master Contract

When behavior changes:

1. Update the owner section first.
2. Update impacted references in non-owner sections.
3. Re-run mandatory quality gates (see `rules` skill).
4. If schema changed, update database skill and migration rules together.
5. If RBAC/session contracts changed, update auth-rbac skill and rerun role/session grep checks.

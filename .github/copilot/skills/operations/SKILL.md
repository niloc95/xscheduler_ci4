---
name: webscheduler-operations
description: WebScheduler operations — spark commands, testing rules, known debt, audit/hardening progress board, and the next hardening queue. Use this skill when running CLI commands, dispatching the notification queue, running audits, executing migrations, running the test suite, looking up known debt items, or checking the recent behavior-hardening log. Covers the core runtime commands (`php spark serve`, `php spark migrate -n App`, `notifications:dispatch-queue`, `notifications:test-reminder`, `notifications:backfill-templates`, `notifications:purge-delivery-logs`, `notifications:export-delivery-logs`, `notifications:repair-business-id`, `audit:provider-assignments`, `audit:reminder-pipeline`, `audit:purge-logs`, `npm run dev`, `npm run build`), PHPUnit testing rules (test DB only, never live schema), known active debt themes (`ProviderWorkingHoursTrait`, migration sequence gap near `2025-10-22-174832`, `@material/web` package), the verified-in-current-pass audit board, the remaining debt list, the next-hardening queue (split `scheduler-today-view.js`, reduce inline scripts, add CI coverage, fix mock expectations, business selector UI, npm uninstall `@material/web`, fix `ProviderWorkingHoursTrait::getBusinessHours()`), and the verification rule (each queue item ends with `npm run test:frontend:lifecycle`, `npm run test:unit:calendar`, `npm run build`). Also includes execution log items 14-19 (frontend decomposition tranches) and the recent behavior-hardening log items 20-36 with their status and notes. Trigger on any mention of "spark", "command", "audit", "test", "PHPUnit", "Vitest", "debt", "hardening", "queue dispatch", "migration", "npm run", or planning/tracking work.
---

# WebScheduler — Operations

## Core Runtime Commands

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
npm run build                  # Vite asset compilation only (no ZIP, no version bump)
npm run package:local          # Build deployment ZIP from current source (no Vite rebuild)
npm run package                # npm run build + npm run package:local
npm run release:patch          # Bump patch → build → ZIP → commit/tag → push → CI publishes GitHub Release
npm run release:minor          # Same, minor version bump
npm run release:major          # Same, major version bump
```

See `docs/deployment/UPDATER.md` for full command semantics and the in-app updater architecture.

## Testing Rules

- Use **PHPUnit**.
- Integration tests MUST run against test DB config only.
- **Never run tests against default/live schema.**
- Calendar selector unit tests are runner-aligned via `npm run test:unit:calendar` (Vitest).

---

## Known Debt and Guardrails

### Active Debt Themes

- Scheduler loading-path discipline must remain strict.
- Frontend large-module decomposition is active and tracked in Execution Log items 14–19 (below).
- Schema-compatibility in mixed local DB states must remain guarded.
- ~~`ProviderWorkingHoursTrait::getBusinessHours()` calls `$settings->getValue()`~~ — **RESOLVED:** already uses `getByKeys()` correctly.
- Composer packages added: `google/apiclient:^2.0` (Google Calendar), `stripe/stripe-php:^12.0` (Stripe). Keep these updated alongside any API version changes.
- Integration test DB has a migration sequence gap near `2025-10-22-174832`. `BusinessHoursServiceIntegrationTest` and some journey tests cannot run against the test DB until the gap is repaired.
- `@material/web` and related `@material/*` packages (9 total) still in `package.json` — unused. Remove with `npm uninstall @material/button @material/card @material/drawer @material/icon-button @material/list @material/textfield @material/top-app-bar @material/web @material-tailwind/html`.

### SaaS Multi-Tenancy Gap Register

The following core tables lack `business_id` — system is intentionally single-tenant; these must be addressed before any multi-tenant deployment:

| Table | Gap |
|---|---|
| `xs_appointments` | No `business_id` — all appointments implicitly belong to business 1 |
| `xs_customers` | No `business_id` — customer records are not tenant-scoped |
| `xs_services` | No `business_id` — services shared across all tenants |
| `xs_locations` | No `business_id` — locations not tenant-scoped |
| `xs_users` | No `business_id` — providers/staff not explicitly tenant-scoped |

**Estimated effort:** Medium — adding `business_id` to all 5 tables + scoping every service query method + migration. Do not attempt incrementally; this requires a coordinated multi-table migration and query audit.

---

## Audit Progress Board

### Verified in Current Pass

- Mandatory quality gates (§14.4) were rerun after tranche updates.
- Targeted decomposition items 14–19 are now implemented and linked in §17.3.
- `/profile` renders authoritative account data and audit activity through `ProfilePageService`, with SPA-safe tab/image behavior and integration coverage recorded in §17.4 item 20.
- Appointment create customer search now respects the shared parsed-payload contract from `apiRequest()`, with hardening recorded in §17.4 item 21.
- Frontend test + build validation is required after each decomposition tranche.
- `current_business_id()` helper added to `permissions_helper.php`; `NotificationCenterService` and `NotificationSettingsService` switched off hardcoded constant — business context resolver documented in `architecture` skill (§17.4 item 22).
- Reminder queue metadata (`reminder_offset_minutes`, `schedule_fingerprint`) added to `xs_notification_queue`; stale-reminder cancellation logic in `NotificationQueueDispatcher` — schema updated in `database` skill, queue fields in `notifications` skill (§17.4 item 23).
- Two new resolver-focused unit tests passing: `testResolveBusinessIdUsesSessionBusinessContext` (`NotificationCenterServiceTest`) and `testNotificationSettingsServiceResolvesBusinessIdFromSessionContext` (`SettingsBoundaryServicesTest`).
- Unified avatar/initials system implemented across all PHP views and JS modules — see `frontend` skill (§17.4 item 24).
- Appointments toolbar mobile two-rail layout implemented — see `frontend` skill (§17.4 item 35).
- Services section fully migrated from `layouts/dashboard` to `layouts/app` design system — see `frontend` skill (§17.4 item 36).

### Remaining Debt Outside Items 14–19

- ~~`ProviderWorkingHoursTrait` getValue bug~~ — **RESOLVED:** already uses `getByKeys()` correctly.
- Test DB migration-sequence gap near `2025-10-22-174832` still blocks part of integration suite.
- `testSettingsPageServiceBuildsDefaultAdminContextWhenSessionUserMissing` (`SettingsBoundaryServicesTest`) fails because the mock expectation `expects($this->once())` on `getByKeys` is violated when `LocalizationSettingsService` makes a second internal call. **Fix:** change to `expects($this->atLeastOnce())` or mock `LocalizationSettingsService` separately. Pre-existing.
- `@material/web` and 8 other `@material/*` packages still in `package.json`; confirmed unused. Run `npm uninstall @material/button @material/card @material/drawer @material/icon-button @material/list @material/textfield @material/top-app-bar @material/web @material-tailwind/html`
- `public-booking.js` is 2154 lines — needs splitting into modules under `resources/js/modules/public-booking/` (booking-flow, manage-flow, render, api). Phase 6 of engineering review.
- CSRF/fetch logic duplicated in 7 modules outside `core/api.js` — centralization needed (Phase 6).

---

## Next Hardening Queue

### Immediate Follow-up Work

1. Split `scheduler-today-view.js` into render/data helpers while preserving existing event contracts.
2. Continue reducing inline script usage in non-target legacy views (module-bootstrapped parity).
3. Add CI coverage for `npm run test:unit:calendar` in frontend validation workflow.
4. Fix pre-existing mock expectation count failure in `SettingsBoundaryServicesTest::testSettingsPageServiceBuildsDefaultAdminContextWhenSessionUserMissing`.
5. Add an admin-visible business selector / `?business_id=` query-param handoff to the Notifications and Settings pages — `current_business_id()` already reads `?business_id=` from GET params, so no PHP changes are needed; only a UI affordance is required.
6. Run `npm uninstall @material/web` to remove unused 485 kB package from `package.json` and `package-lock.json`.
7. Fix `ProviderWorkingHoursTrait::getBusinessHours()` — replace `$settings->getValue()` with `SettingModel::getByKeys(['booking.day_start','booking.day_end'])`.
8. Adopt `MutationResult` in existing mutation services (`AppointmentFormMutationService`, `UserManagementMutationService`) as a follow-on refactor — any new mutation service must return `MutationResult` from creation.

### Verification Rule

Each queue item MUST end with:

```bash
npm run test:frontend:lifecycle
npm run test:unit:calendar
npm run build
```

---

## Execution Log — Items 14–19 (Frontend Decomposition)

Status legend: `done`, `in_progress`, `queued`.

| Item | Scope | Status | Notes |
|---|---|---|---|
| 14 | `public-booking.js` decomposition | done | State/constants extracted to `resources/js/modules/public-booking/state.js`. |
| 15 | `scheduler-core.js` decomposition | done | Calendar model URL building extracted to `resources/js/modules/scheduler/calendar-model-url.js`. |
| 16 | Selector test runner alignment | done | Added `test:unit:calendar` script and Vitest dependency in `package.json`. |
| 17 | `scheduler-day-view.js` decomposition | done | View constants/status metadata extracted to `scheduler-day-view-config.js`. |
| 18 | `scheduler-week-view.js` decomposition | done | Day grid rendering extracted to `week-view-day-grid.js`. |
| 19 | `app.js` decomposition | done | Shared UI helpers extracted to `resources/js/modules/app/shared-ui.js`. |

---

## Recent Behavior Hardening Log — Items 20–36

| Item | Scope | Status | Notes |
|---|---|---|---|
| 20 | `/profile` live view hardening | done | Added `ProfilePageService`, replaced placeholder profile cards/activity with authoritative data, moved tab/avatar behavior to `resources/js/modules/profile/profile-page.js`, and covered the flow with `tests/integration/ProfileJourneyTest.php`. |
| 21 | Appointment customer-search payload contract | done | Appointment-form search accepts parsed JSON payloads from `resources/js/core/api.js`; shared `extractJSON()` accepts object payloads so sibling search surfaces do not fail on `.match()` calls. |
| 22 | Business context resolver for notification services | done | Added `current_business_id()` to `permissions_helper.php`. `NotificationCenterService` and `NotificationSettingsService` resolve scope via `resolveBusinessId()` instead of `NotificationCatalog::BUSINESS_ID_DEFAULT`. Full contract in `architecture` skill. |
| 23 | Reminder queue metadata + stale-reminder cancellation | done | Added `reminder_offset_minutes` and `schedule_fingerprint` columns to `xs_notification_queue` (migration `2026-04-21-150000`). Fingerprint cancellation logic in dispatcher. |
| 24 | Unified avatar / initials system | done | Single PHP source of truth in `app/Helpers/app_helper.php`. Single JS source of truth in `resources/js/utils/avatar.js`. Globals `window.xsGetAvatarInitials` / `window.xsGetDisplayName` from `app.js` for inline view scripts. All avatar surfaces updated. Parity tests added for both PHP and JS. |
| 25 | Appointment inline notification dispatch regression fix | done | Restored immediate queue dispatch in `AppointmentBookingService::queueNotifications()` via `NotificationQueueDispatcher::dispatch(NotificationCatalog::BUSINESS_ID_DEFAULT)`. Added queue idempotency pre-check hardening in `NotificationQueueService::enqueueRaw()`. |
| 26 | Setup hosting compatibility mode + resilience hardening | done | Added shared-hosting-safe DB probe path (`app/Helpers/SetupCompatibilityHelper.php`) and compatibility UI module (`resources/js/setup-compat.js`) with smart suggestion banner. Setup controller accepts JSON `testConnection`, forwards `compatibility_mode`, returns sanitized payloads. Fixed setup-page CSRF header value. |
| 27 | Reminder enqueue idempotency — re-enqueue after cancellation/failure | done | `NotificationQueueService::enqueueRaw()` previously blocked re-enqueue regardless of status. Rows with status `cancelled` or `failed` are now deleted before fresh insert; reminders that previously cancelled or failed retry on next cron run. In-flight (`queued`, `processing`) and delivered (`sent`) rows still dedupe normally. |
| 28 | Reminder scan window — 48 h lookback for missed cron runs | done | `enqueueDueReminders()` filtered `start_at > now` which silently excluded all appointments with a reminder window that opened while cron was stopped. Scan now starts at `now - 48 hours` (`start_at >=`), allowing catch-up delivery. Idempotency keys prevent double-sending. Added per-channel `log_message('info',...)` diagnostics for skip reasons and `log_message('warning',...)` when enabled-channel list is empty. |
| 29 | Provider public profile schema | done | Added `title`, `bio`, `education`, `qualifications`, `slug` columns to `xs_users` (migration `2026-04-23-120000`). Slugs backfilled and unique-indexed. Added `slug` to `xs_services`. Added `city`, `area` to `xs_locations`. New API route `GET /api/v1/providers/slug/{segment}/services`. |
| 30 | Appointment custom fields table | done | Added `xs_appointment_custom_fields` table (migration `2026-04-30-140000`) with `appointment_id` FK (CASCADE), `field_key`, `value`. Unique index on `(appointment_id, field_key)`. Backfilled legacy customer custom fields. Model: `AppointmentCustomFieldModel`. Table count updated to 22. |
| 31 | Unique email constraint on `xs_customers` | done | Added unique index `idx_customers_email_unique` (migration `2026-04-30-120000`). Enforce uniqueness on customer create/update. |
| 32 | Booking reference short-link `/r/{reference}` | done | Added route `GET /r/{reference}` → `PublicSite\BookingController::reference` (with `setup` + `public_rate_limit` filters). `BookingLinkService::manageReferenceUrl()` generates these branded short links. Used in notification emails. |
| 33 | Dark mode to `darkMode: 'class'` + SCSS `.dark` | done | `tailwind.config.js` changed from `['selector', '[data-theme="dark"]']` to `'class'`. SCSS `_custom-properties.scss` uses `.dark { }` block. `theme-bootstrap.js` and `dark-mode.js` both set `.dark` class on `<html>`. Full contract in `frontend` skill. |
| 34 | Vite entry point cleanup — remove material-web | done | `resources/js/material-web.js` deleted and removed from `vite.config.js` inputs. The `@material/web` bundle (485 kB) was dead. New entries added: `theme-bootstrap`, `app-layout-init`, `public-booking-bootstrap`. |
| 35 | Appointments toolbar — mobile two-rail layout | done | Replaced single `overflow-x-auto` scrolling row with two-column side-by-side layout: Rail A (`flex-col` on mobile, two sub-rows) + Rail B (stats chips stack on mobile). Outer wrapper uses `items-start` for left-alignment. Mobile filter button removed. Full contract in `frontend` skill. |
| 36 | Services section — full layout/design-system migration | done | Migrated all five services-section views from `layouts/dashboard` to `layouts/app` with `xs-card` design system. Inline stats summary bar, context-aware CTA, tab switcher (Services/Categories), collapsible filter panel, 5-col services table and 4-col categories table, all row actions as `xs-actions-container` icon-only buttons. AJAX quick-add form and `scripts` JS block removed entirely. |
| 37 | `.github` directory audit and cleanup | done | Deleted `SETUP_COMPLETE.md` and `RELEASES_SETUP_COMPLETE.md` (historical artifacts). Fixed `ci-cd.yml` asset checks — all four were broken due to hashed filenames, `materialWeb.js` was removed; replaced with glob-based checks (`main-*.js`, `style-*.css`, `spa-*.js`, `setup-*.js`). Fixed `docs.yml` — `mastercontext.md` check replaced with 10-skill presence validation. Expanded `PULL_REQUEST_TEMPLATE.md` with skills checklist, 6-item pre-merge checklist, and breaking-changes section. |
| 38 | Rules validation pass — auth + notification fixes | done | `UserModel::canManageUser()` and `canViewUser()` — 5 single `['role'] === 'x'` comparisons replaced with `getRolesForUser()` + `in_array()`. `NotificationTemplateService::buildBusinessHoursText()` — removed dead unscoped `xs_business_hours` query (was assuming non-existent global rows with `provider_id = 0/null`); replaced with direct delegation to `buildBusinessHoursFromDefaultSettings()` which reads from `xs_settings`. |
| 39 | Settings > Integrations Hub — Phase 1 + Jitsi + PayFast | done | Full integrations hub: 2 migrations (ENUM expansion, unique index from `(business_id,channel)` to `(business_id,channel,provider_name)`, `metadata` column). 6 service classes (`WebhookIntegrationService`, `GoogleCalendarIntegrationService`, `StripeIntegrationService`, `ZoomIntegrationService`, `JitsiIntegrationService`, `PayFastIntegrationService`) — all use `HandlesNotificationIntegrations` trait pattern. `IntegrationSettingsService` hub routes by intent+channel. `OAuthCallback` controller for Google OAuth flow. `Api\V1\Integrations` controller (index/save/test/disconnect). `integrations.php` view rewritten — 6 cards in `xs-card` grid, 6 modals, all inside `#panel-integrations` section. `integration-hub.js` module with per-channel `wire*()` functions. Composer: `google/apiclient:^2.0`, `stripe/stripe-php:^12.0`. |
| 41 | Analytics dropdown → card | done | Removed `<form id="integrations-settings-form">` from `integrations.php` entirely. Analytics is now a card in the hub grid (orange `analytics` icon, status badge showing provider name). Configure button opens `#analytics-modal`. Removed `'integrations'` from `SETTINGS_TABS` in `settings-form-ui.js` (no more stale `console.warn`). Added `wireAnalytics()` to `integration-hub.js` — posts `integrations.analytics` to `POST /api/v1/settings`. |
| 42 | Analytics tracking ID + script injection | done | Added `analytics_head_html()` to `app/Helpers/app_helper.php` — reads `integrations.analytics`, `integrations.analytics_id`, `integrations.analytics_site_id` from `xs_settings` and returns ready-to-inject GA4 or Matomo `<script>` block. Injected into `layouts/public.php` (covers all public booking pages including `/booking/p/{slug}`, `/booking/s/{slug}`) and `layouts/app.php`. App layout also adds a `spa:navigated` listener that fires `gtag('event','page_view',...)` / `_paq.push(['trackPageView'])` on every SPA navigation. Analytics modal updated: provider select now shows/hides GA4 fields (Measurement ID `G-XXXXXXXXXX`) or Matomo fields (URL + Site ID). New settings keys: `integrations.analytics_id`, `integrations.analytics_site_id`. Added to `SettingsPageService::settingsKeys()` and `GeneralSettingsService` mapping. |
| 43 | Engineering review Phase 1-3 — security, backend, DB | done | Phase 1: all 7 security greps pass; ProviderWorkingHoursTrait already correct. Phase 2: removed inline `NotificationQueueDispatcher::dispatch()` from `AppointmentBookingService::queueNotifications()` — method now enqueue-only per `notifications` skill §3.1 (note: item 25 had deliberately restored this; it is now reverted to comply with the queue-first contract; deployments must run `php spark notifications:dispatch-queue` via cron for email delivery). Phase 3: added composite covering index `idx_appts_provider_start_end (provider_id, start_at, end_at)` on `xs_appointments` (migration `2026-05-22-100000`); fixed 4 models extending CI4's `Model` instead of `BaseModel` (`UserPermissionModel`, `BusinessHourModel`, `ProviderScheduleModel`, `BlockedTimeModel`); documented SaaS gap register (5 tables lack `business_id`). |
| 40 | Google Calendar OAuth credentials — DB-stored, no .env required | done | `GoogleCalendarIntegrationService::buildClient()` now reads `client_id`/`client_secret` from `encrypted_config` in `xs_business_integrations` (env var fallback preserved for existing deployments). `isConfigured(int $businessId)` checks DB first. New `saveAppCredentials()` merges credentials into existing config without overwriting OAuth tokens. `handleCallback()` merges tokens into existing config to preserve credentials. UI: 3-state card (no credentials → credentials saved → connected), configure modal with read-only redirect URI + copy button. `OAuthCallback::googleAuthorize()` error message updated to point to UI, not .env. |
| 44 | UserManagement `store()` SPA redirect fix | done | `redirect()->back()` in `UserManagement::store()` misbehaves after SPA navigation — CI4 only updates `_ci_previous_url` for non-AJAX GET requests; SPA XHR fetches never set it, causing broken redirect to dashboard. Replaced both `redirect()->back()` calls in `store()` with `redirect()->to(base_url('user-management/create'))` to guarantee correct destination regardless of navigation context. |
| 45 | CSP nonces on `app.php` inline scripts | done | Two bare `<script>` blocks in `app/Views/layouts/app.php` (FOUC dark-mode blocker and analytics `spa:navigated` listener) were missing `{csp-script-nonce}`. CI4 replaces this placeholder with the actual `nonce="…"` attribute when `app.CSPEnabled = true`. Both scripts updated. Rule: every inline `<script>` in a CI4 layout must carry `{csp-script-nonce}`. |
| 46 | Provider schedule time format — localization-aware text inputs | done | `app/Views/user-management/components/provider-schedule.php` used `<input type="time">` — browser-controlled, ignores `localization.time_format`. Replaced all 4 fields per day with `<input type="text">` using `LocalizationSettingsService::formatTimeForDisplay()` for display values, `data-time-format`, `pattern`, and `placeholder` attributes. `resources/js/modules/user-management/provider-schedule.js` `parseToMinutes()` updated to handle both 24h (`HH:MM`) and 12h (`H:MM AM/PM`) formats. Server-side validation unchanged — `ScheduleValidationService::normaliseTimeString()` already handled both formats. |
| 47 | Customer avatar cross-table ID leak fix | done | `avatar_profile_image_url()` in `app/Helpers/app_helper.php` had a fallback block that called `UserModel::find($entity['id'])` when the entity array lacked a `profile_image` key. For customer records (`xs_customers` has no `profile_image` column), `$entity['id']` is a customer PK treated as a `xs_users` PK — Customer 1 showed Provider 1's image, Customer 2 showed Provider 2's image. Fixed by removing the 17-line fallback block entirely; any entity without `profile_image` in its array now returns `null` → views render the initials badge as intended. Provider entities (which do carry `profile_image` in their array) are unaffected. |
| 48 | Production log hardening — heartbeat unlink + validation severity | done | (a) `NotificationReminderHeartbeatService::runIfDue()` `finally` block called `$cache->delete(LOCK_KEY)` unconditionally; if the 30-second lock TTL had already expired, CI4's file-cache `delete()` called `unlink()` on a non-existent file → logged as ERROR in `Events::pre_system`. Fixed by wrapping the delete in `try { } catch (\Throwable $ignored) {}` inside `finally`. (b) `UserManagement::update()` logged normal validation failures (e.g. `password_confirm` mismatch) at `log_message('error',...)` — triggered monitoring alerts for routine user input errors. Downgraded to `log_message('warning',...)`. |
| 49 | Replace all native browser dialogs with custom UI | done | `window.confirm()` / `alert()` calls are silently blocked when the browser's per-site popup blocker is enabled — causing destructive actions (delete service, cancel appointment, etc.) to be unconditionally refused. Replaced all 29 calls across 14 files: (a) New `resources/js/utils/confirm.js` — `XSConfirm.show({ title, message, confirmText, cancelText, danger })` returns `Promise<boolean>`; renders a Tailwind modal with dark mode, ARIA, keyboard support (Enter/Escape), and danger styling for destructive actions. Exposed as `window.XSConfirm` via `app.js`. (b) `initConfirmActions()` in `shared-ui.js` updated to async — prevents default, awaits `XSConfirm.show()`, then calls `form.requestSubmit()` on confirm; handles all `data-confirm-message` forms (services, categories, customers). (c) All 15 `confirm()` calls in JS modules and PHP inline scripts replaced with `await XSConfirm.show(...)`. (d) All 14 `alert()` fallbacks replaced with `XSNotify.toast({ type: 'error'/'warning', ... })`; `createNotifier()` fallback paths in settings files updated to `console.error()`. PHP inline scripts in `provider-locations.php`, `provider-staff.php`, `staff-providers.php` updated. |
| 50 | MVP in-app updater | done | Browser-based update system requiring no CLI or exec(). `app/Services/Updater/` — 5 services: `UpdaterValidatorService` (rejects legacy ZIPs without `version.json`, downgrades, min_version violations), `UpdaterBackupService` (pure-PHP DB dump via SHOW TABLES + SELECT * + INSERT; shadow ZIP of `app/` + `public/`; prunes to 3 backup sets), `UpdaterFileService` (entry-by-entry extraction, path traversal rejection, preserve list: `.env`, `writable/`, `public/assets/settings/`, `public/assets/providers/`), `UpdaterMigrationService` (non-shared DB connection mirroring `Setup.php:444`), `UpdaterService` (orchestrator with `$enteredMaintenance` + `$filesMutated` fail-closed state gate). `app/Controllers/Admin/Updater.php` — thin coordinator extending `BaseApiController`; `upload()` is multipart redirect, `execute()`/`rollback()`/`toggleMaintenance()` return JSON. `app/Filters/MaintenanceFilter.php` — global `before` filter; admin session bypasses via `roles` array; no auto-expiry; fail closed. `app/Views/settings/tabs/system-update.php` + `errors/maintenance.php`. `resources/js/modules/settings/system-update.js` — explicit `xsSPA.navigate()` post JSON actions; `data-no-spa="true"` upload form. `version.json` injected into deploy ZIP by `scripts/package.js`. `Setup.php` seeds `system.installed_version` from deployed `version.json`. Rollback scope: `app/` + `public/` + DB only; vendor/system requires re-upload of previous ZIP from GitHub Releases. |
| 51 | Version consistency — dynamic footer | done | `package.json` bumped from 1.0.4 to 2.0.0. `version.json` created at project root (git-tracked) with version 2.0.0. `scripts/package.js` now overwrites project-root `version.json` after each package build so the file stays current across releases. Footer in `app/Views/layouts/app.php:314` replaced hardcoded `2.0.0` string with dynamic PHP read from `ROOTPATH/version.json` (PHP `static` variable caches per request; fallback `2.0.0`). All version sources now consistent: `package.json` → `version.json` (root) → `system.installed_version` (DB, seeded at setup) → footer display. |
| 52 | Updater ZIP packaging — version.json path fix | done | `archive.directory(dir, false)` on Linux (GitHub Actions Ubuntu) stored entries with a `./` prefix (e.g. `./version.json`) that PHP `ZipArchive::getFromName('version.json')` could not find. Switched to `archive.glob('**', { cwd: packageDir, dot: true })` which produces canonical paths without any prefix on both macOS and Linux. Validator updated with two-step fallback (`getFromName('version.json')` → `getFromName('./version.json')`) plus `FL_NODIR` detection-only for source-code archives. Source archive detection added: if `version.json` exists only nested (e.g. `xscheduler_ci4-2.0.15/version.json`), the validator returns a specific error directing the user to download the deploy ZIP, not the "Source code" archive. |
| 53 | Release script version ordering fix | done | `scripts/release.js` ran `node scripts/package.js` (which generates `version.json`) **before** `writePackageJson(pkg)` (which sets the new version), so the local ZIP and project-root `version.json` were always stamped with the previous version. Moved the `writePackageJson` call before the package step so local ZIP and `version.json` carry the correct new version. Also added `version.json` to the `git add` list in the release commit so the GitHub source-code archive always contains the current version. |
| 54 | GitHub Actions release workflow — permissions and validation | done | Release workflow failed with 403 because `GITHUB_TOKEN` lacked `contents: write`. Added `permissions: contents: write` to the `create-release` job. Added Python-based ZIP validation step (using `zipfile.ZipFile.namelist()` + `z.read()`) that prints the exact stored entry name and version, replacing the fragile `unzip -l | awk | grep` approach that silently passed even when entries had `./` prefixes. |
| 55 | `core/api.js` subdirectory install fix | done | `apiRequest()` passed endpoints directly to `fetch()`. Root-relative paths (`/admin/updater/execute`) resolve to the origin root (`https://example.com/`) in the browser, ignoring `window.__BASE_URL__`. On subdirectory installs (e.g. Hostinger `/mvp/public/`) all AJAX routes returned 404. Fixed: import `getBaseUrl()` from `utils/url-helpers.js`; prepend it for any endpoint starting with `/`; full URLs (`http/https`) and `withBaseUrl()`-pre-resolved paths pass through unchanged. Fixes three call sites in one place: `system-update.js` (`/admin/updater/*`), `unified-sidebar.js` (`/api/auth/switch-role`), `integration-hub.js` (`/api/v1/settings`). Modules already using `withBaseUrl()` (scheduler-core, scheduler-drag-drop, inactivity-monitor) are unaffected. |
| 56 | `.htaccess` rewrite rule — `index.php/$1` → `index.php` | done | `scripts/package.js` generated `RewriteRule ^(.*)$ index.php/$1 [L]` (PATH_INFO routing). On LiteSpeed (Hostinger) this is unreliable, particularly for AJAX POST routes in subdirectory installs. Replaced with `RewriteRule ^ index.php [L]` (CI4-standard, REQUEST_URI routing). Added commented `RewriteBase` example for subdirectory operators. Subdirectory installs must uncomment and set `RewriteBase /path/to/public/` in `public/.htaccess` after extraction. |
| 57 | Save mechanism standardisation — `ServiceMutationService` + `FormResponseTrait` | done | `ServiceMutationService` extracted: service/category create/update/delete, provider assignment with `transStart/transComplete`, `AuditLogModel` audit. `FormResponseTrait` created (`formSuccess()`/`formError()`) and applied to `Services`, `ServiceCategories`, `CustomerManagement`. `CustomerManagement::store()` wired to `CustomerService::insertCustomer()`, `update()` to `updateCustomerById()` — audit now fires on all admin customer create/update paths. |
| 58 | CustomerManagement HTTP status fixes | done | `update()` 404 guard was returning HTTP 200 via bare `response()->setJSON()`. Replaced with `$this->formError('Customer not found.', [], 404)`. Validation failure paths in `store()` and `update()` were hand-rolling `setStatusCode(422)->setJSON(...)` despite the class having `FormResponseTrait`; replaced both with `$this->formError('Validation failed', $this->validator->getErrors())`. |
| 59 | `MutationResult` value object | done | `app/Services/MutationResult.php` created as `readonly class` with `::ok(message, redirect, entityId)` and `::fail(message, errors, statusCode)` named constructors and `toArray()`. Not yet adopted by existing services — adoption is a follow-on refactor. All new mutation services must use this type from the start. |
| 60 | Public booking dark mode completion | done | The shared `xs-theme` toggle (admin ↔ public booking) was switching `.dark` on `<html>` everywhere, but the booking SPA's `UI_CLASSES` tokens (`state.js`) and rendered components (`render.js`) had zero `dark:` variants, and `legal.php` / `my-appointments.php` were missing the FOUC-prevention inline script entirely (`.dark` never applied there). Added `dark:` variants to all `UI_CLASSES` tokens and rendered components; added the standard `#0f172a`/slate-900 FOUC blocking script + double-rAF cleanup script to both standalone views; fixed `<body>` to `dark:bg-slate-900`; added `html.dark` CSS overrides to the inline-styled `payment-return.php`/`payment-cancel.php`. Full contract in `public-booking` skill §9. |
| 61 | Inactivity-warning modal — wall-clock rewrite | done | `inactivity-monitor.js` used tick-based `setTimeout`/`setInterval` countdowns, which browsers throttle/suspend in background tabs and across machine sleep — users saw a stale "5:00" countdown that had already expired, or hit "Stay Logged In" long after the server session (2h from last HTTP request) had died. Rewrote around `Date.now()` wall-clock deadlines: 30s `checkIdleState()` interval plus immediate re-checks on `visibilitychange`/`focus`/`pageshow`; elapsed ≥120min redirects immediately, ≥115min shows the modal with the true remaining time computed from the deadline; added a 15-minute keepalive `GET /auth/ping` while active to prevent client/server drift; cross-tab sync via `xs-last-activity-at`/`xs-last-keepalive-at` localStorage keys. Full contract in `auth-rbac` skill "Inactivity Warning Modal". |

---

## Update Protocol for the Engineering Contract

When behavior changes:

1. Update the **owner section** (or owner skill) first.
2. Update impacted references in non-owner sections (or other skills).
3. Re-run mandatory quality gates (see `rules` skill §14).
4. If schema changed, update `database` skill AND migration rules together.
5. If RBAC/session contracts changed, update `auth-rbac` skill AND rerun role/session ripgrep checks.

---

## Cross-References

- Mandatory pre-merge ripgrep checks → `rules` skill §14.4
- Spark command `audit:provider-assignments` minimum checks → `rules` skill (Rule #5)
- Notification cron details (`notifications:dispatch-queue`) → `notifications` skill
- Reminder debugging (`notifications:test-reminder`) → `notifications` skill
- Migration base requirement → `database` skill

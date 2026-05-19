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
npm run build
```

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
- `ProviderWorkingHoursTrait::getBusinessHours()` calls `$settings->getValue()` which does NOT exist on `SettingModel`. **Fix:** replace with `getByKeys(['booking.day_start','booking.day_end'])` (see `scheduling` skill §8.7.4).
- Integration test DB has a migration sequence gap near `2025-10-22-174832`. `BusinessHoursServiceIntegrationTest` and some journey tests cannot run against the test DB until the gap is repaired.
- `@material/web` package is still in `node_modules` / `package-lock.json` but is unused. Can be removed with `npm uninstall @material/web` when ready to clean up.

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

- `ProviderWorkingHoursTrait::getBusinessHours()` still requires `SettingModel::getByKeys()` migration.
- Test DB migration-sequence gap near `2025-10-22-174832` still blocks part of integration suite.
- `testSettingsPageServiceBuildsDefaultAdminContextWhenSessionUserMissing` (`SettingsBoundaryServicesTest`) fails because the mock expectation `expects($this->once())` on `getByKeys` is violated when `LocalizationSettingsService` makes a second internal call. **Fix:** change to `expects($this->atLeastOnce())` or mock `LocalizationSettingsService` separately. Pre-existing.
- `@material/web` still in `package-lock.json` and `node_modules`; not yet uninstalled from `package.json`. Zero production impact (no imports). Remove with `npm uninstall @material/web`.

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

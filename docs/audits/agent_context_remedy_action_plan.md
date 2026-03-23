# Agent Context Remedy Action Plan

Date: 2026-03-21
Repository: xscheduler_ci4
Branch Audited: calendar-refactor

## Purpose

This document captures the read-only technical debt audit of the repository and converts it into a prioritized, sequential remediation plan that another AI agent or developer can execute without ambiguity.

## Execution Status

As of 2026-03-21:

- Completed: 31 of 31 debt items.
- Partial / follow-through still required: none.
- Outstanding engineering work: none from the remedy plan.
- Human decision still required: none for the debt items tracked in this plan.

Completed items:

- DEBT-024: removed the unused vfsstream dev dependency.
- DEBT-026: removed the scaffold example tests and example fixture/support files.
- DEBT-027: reclassified HealthTest into EnvironmentSmokeTest so it is explicit environment smoke coverage.
- DEBT-009: collapsed settings_helper into a compatibility wrapper over app_helper.
- DEBT-010: centralized shared role helper behavior in app_helper and reduced duplicate helper files to compatibility entry points.
- DEBT-001: removed the dead Home controller and aligned routing fallback metadata with AppFlow.
- DEBT-002: deleted the unrouted Styleguide controller and view surface.
- DEBT-006: removed the legacy reminder alias commands and their unused shared abstract base command.
- DEBT-007: removed the unreferenced diagnostic commands from the main command surface.
- DEBT-003: removed the orphaned public clear-session utility from both the app and deploy public roots.
- DEBT-004: documented the duplicate blocked-times migration as intentional historical no-op debt rather than rewriting applied migration history.
- DEBT-005: documented the receptionist-era migration path as historical schema context superseded by staff.
- DEBT-018: standardized active API and JSON controller response contracts on the shared BaseApiController helpers across the appointments, availability, locations, customer appointments, database backup, and V1 settings/provider surfaces.
- DEBT-019: centralized active appointment status definitions, labels, filters, and status-driven notification mapping on one appointment-domain authority and fixed the no-show constant mismatch.
- DEBT-012: extracted shared appointment date-time normalization into a reusable appointment-domain normalizer used by both the web and API appointment controllers.
- DEBT-013: extracted shared notification integration lookup, config crypto, health update, and E.164 validation behavior into one reusable notification-service support trait.
- DEBT-008: removed the deprecated V1 API base-controller shim and pointed the V1 controllers directly at the shared API base controller.
- DEBT-014: extracted the settings block-period client workflow into a dedicated frontend module and reduced the view to data handoff plus markup.
- DEBT-015: extracted the settings database-backup client workflow into a dedicated frontend module and reduced the view to data handoff plus markup.
- DEBT-025: removed unused frontend dependencies after source verification and build validation.
- DEBT-031: cleaned the remaining root-entry and Styleguide documentation drift in active project docs.
- DEBT-021: extracted appointment create, update, status-change, notes, cancellation, manual notify, and availability-check orchestration into dedicated appointment services, and moved appointment detail retrieval plus today/week/month counts into the appointment query/formatter layer so the API controller now acts primarily as a transport boundary.
- DEBT-023: completed the final Dashboard controller-boundary cleanup by removing dead actions, unused dependencies, redundant setup gating, inline access-denied HTML, and the last inline stats fallback payload from the controller.
- DEBT-022: completed the remaining Settings decomposition by extracting notification-tab loading/save/test orchestration into NotificationSettingsService, the main settings save plus logo-upload flow into GeneralSettingsService, index page-data assembly into SettingsPageService, and the last large inline settings-page bootstrap/save logic into a dedicated frontend module.

Partial items still requiring follow-through:

- None.

Outstanding engineering work:

- None.

Human decision still required:

- None.

Further progress on 2026-03-21:

- DEBT-028: added focused unit coverage for PublicBookingService booking-flow guardrails and lookup/reschedule orchestration, AppointmentManualNotificationService channel dispatch and fallback behavior, direct AppointmentDateTimeNormalizer conversion/error contracts, direct AppointmentFormatterService response shaping, direct AppointmentQueryService provider/staff scoping and grouped-date behavior, DashboardService cache/alert/context formatting contracts, SettingsPageService, BookingSettingsService, AppointmentBookingService, the extracted UserManagement context/mutation guardrails, the appointment mutation/availability boundary services, and high-risk NotificationQueueDispatcher branches after small collaborator-injection refactors that made the public-booking, booking, dashboard, dispatcher, and appointment-query orchestration services unit-testable without live infrastructure.
- DEBT-028: added direct unit coverage for the appointment mutation-layer reschedule mapping so `AppointmentMutationService::updateFromApiPayload()` now has an explicit regression assertion for propagating booking-service reschedule conflicts into the expected 422-style service result shape.
- DEBT-028: added direct unit coverage for `AppointmentMutationService::cancelAppointment()` so the delete/cancel API path now has an explicit boundary regression asserting the expected booking-service call, notification event, and success response contract.
- DEBT-028: added direct unit coverage for `AppointmentBookingService::updateAppointment()` location-context validation so provider/location updates that keep the existing slot now explicitly reject unavailable locations before any persistence or downstream side effects occur.
- DEBT-028: expanded `NotificationQueueDispatcher` regression coverage with an explicit opted-out-recipient branch so queued notifications now have a direct test locking down cancellation, delivery-log status, and recipient handling before any send attempt.
- DEBT-028: expanded `NotificationQueueDispatcher` regression coverage again with an explicit retry/backoff branch so transient delivery failures below the max-attempt threshold now have a direct test locking down requeue status, attempt increments, preserved error context, and deferred `run_after` scheduling.
- DEBT-028: expanded the settings boundary coverage with a direct `GeneralSettingsService::save()` regression that locks down checkbox defaulting, blocked-period normalization, and JSON persistence for the extracted settings save path without requiring live file uploads.
- DEBT-028: expanded the settings boundary coverage again with a direct `NotificationSettingsService::save()` regression that exposed and fixed double-encoded template persistence while locking down normalized notification-template storage plus default-language updates for the extracted notification-settings save path.
- DEBT-028: expanded the settings boundary coverage again with a direct strict-error regression for `NotificationSettingsService::save()` so intent-specific notification integration saves now explicitly surface email integration validation failures while still persisting the default-language setting through the extracted service seam.
- DEBT-028: expanded the settings boundary coverage again with a direct `NotificationSettingsService::test_whatsapp` regression that exposed and fixed a missing-provider fallback bug, so the extracted notification-settings service now has an explicit test locking down the link-generator success branch, including the HTML-markup flag and escaped WhatsApp deep link returned to the settings UI.
- DEBT-028: expanded the settings boundary coverage again with a direct `NotificationSettingsService::save_whatsapp` meta-cloud regression so the extracted notification-settings service now has explicit coverage for provider persistence plus per-event WhatsApp template synchronization across the full notification catalog.
- DEBT-028: expanded the settings boundary coverage again with a direct `NotificationSettingsService::test_sms` regression so the extracted notification-settings service now has explicit coverage for propagating provider-level SMS test failures back to the settings UI after the intent-specific integration-save step.
- DEBT-028: expanded the settings boundary coverage again with a direct `NotificationSettingsService::test_email` regression so the extracted notification-settings service now has explicit coverage for propagating provider-level SMTP test failures back to the settings UI after the intent-specific integration-save step.
- DEBT-028: expanded the settings boundary coverage again with a direct strict `NotificationSettingsService::save_whatsapp` regression so the extracted notification-settings service now has explicit coverage for surfacing provider-level WhatsApp integration validation failures back to the settings UI during intent-specific saves.
- DEBT-028: expanded the settings boundary coverage again with a direct strict `NotificationSettingsService::save_sms` regression so the extracted notification-settings service now has explicit coverage for surfacing provider-level SMS integration validation failures back to the settings UI during intent-specific saves.
- DEBT-028: added direct coverage for `NotificationOptOutService` so the notification-domain service layer now has an explicit regression locking down opt-out insertion, duplicate-recipient update behavior, persisted reason changes, and blank-recipient no-op checks.
- DEBT-028: added direct coverage for `NotificationTemplateService` so the notification-domain service layer now has explicit regressions locking down stored-template rendering with legal-content placeholders plus validation failures for unknown placeholders and unbalanced braces.
- DEBT-028: added direct coverage for `NotificationDeliveryLogService` so the notification-domain service layer now has explicit regressions locking down provider lookup, persisted delivery-log writes, attempt normalization, and missing-provider fallback behavior.
- DEBT-028: added direct coverage for `CustomerAppointmentService` that exposed and fixed query-state leakage plus favorite-ID normalization bugs, so the customer-domain service layer now has an explicit regression locking down autofill payload composition, favorite provider/service lookup, and appointment-stat aggregation from persisted history.
- DEBT-028: expanded `CustomerAppointmentService` coverage again with a direct `getCustomerByHash()` regression so the customer-domain service layer now also locks down public-hash lookup, null handling for missing hashes, and appointment-stats attachment on the returned customer payload.
- DEBT-028: expanded `CustomerAppointmentService` coverage again with a direct `searchAllAppointments()` regression so the customer/admin appointment service layer now also locks down cross-customer search, provider/status/date filtering, pagination totals, and enriched result payload fields for admin-facing appointment discovery.
- DEBT-028: expanded `CustomerAppointmentService` coverage again with a direct `getHistory()` regression so the customer appointment service layer now also locks down local-timezone date-range filtering, provider/service constrained history queries, pagination metadata, and enriched display fields on returned history rows.
- DEBT-028: expanded `CustomerAppointmentService` coverage again with a direct `getUpcoming()` regression so the customer appointment service layer now also locks down future-only pending/confirmed selection, ascending ordering, result limiting, and enrichment of upcoming appointment rows.
- DEBT-028: expanded `CustomerAppointmentService` coverage again with a direct `getPast()` regression so the customer appointment service layer now also locks down the `type=past` behavior, inclusion of status-driven past rows, pagination metadata, and enrichment of returned past appointments.
- DEBT-028: added direct coverage for `GlobalSearchService` so the remaining cross-entity search service layer now has explicit regressions locking down blank-query short-circuit behavior, combined customer-plus-appointment result counts, and ISO-formatted appointment timestamps in returned search hits.
- DEBT-028: added direct coverage for `UserDeletionService` so the user-management service layer now has explicit regressions locking down the missing-target 404 path plus the `LAST_ADMIN` safety block that must prevent deletion of the final active administrator.
- DEBT-028: added direct coverage for `AvailabilityService` so the scheduling service layer now has explicit regressions locking down blocked-time rejection plus location-operating-day enforcement when checking individual slot availability.
- DEBT-028: added direct coverage for `LocalizationSettingsService`, `CalendarConfigService`, and `ScheduleValidationService` so the remaining settings/scheduling support layer now has explicit regressions locking down timezone precedence plus session sync, twelve-hour parsing/display behavior, blocked-period config decoding, scheduler JavaScript config composition, schedule normalization, and business-hours/break-window validation messaging.
- DEBT-028: added direct coverage for `NotificationQueueService` and the notification policy service so the remaining notification orchestration layer now has explicit regressions locking down queue idempotency, due-reminder enqueue behavior, rule-matrix hydration, integration-status projection, and channel-specific preview generation. With these additions, the meaningful direct service-layer gaps identified in the DEBT-028 audit are closed, so DEBT-028 is complete.
- DEBT-030: resolved the final notification architecture naming debt by replacing the transitional `NotificationPhase1` runtime service with `NotificationPolicyService`, updating the settings UI/runtime references to the stable business-domain service name, and aligning the active tests/docs with the new notification-policy end state. The only remaining `NotificationPhase1` identifier is the intentionally preserved historical migration class name `CreateNotificationPhase1Tables`, which remains unchanged for migration-history compatibility rather than runtime architecture reasons. DEBT-030 is complete.
- DEBT-023: completed the final Dashboard controller-boundary cleanup by removing dead search and unrouted analytics actions, dropping redundant setup gating and unused dependencies, replacing the inline 403 HTML body with a dedicated error view, and moving the last inline stats fallback payload into DashboardPageService while keeping the live metrics and chart endpoints stable.
- DEBT-022: completed the remaining Settings decomposition by extracting the last large inline settings-page bootstrap/save script into a dedicated frontend module, leaving the view as markup plus endpoint data handoff and moving the residual tab-specific orchestration out of the PHP template.
- DEBT-020: further reduced UserManagement by moving manageable-user resolution plus validation-rule construction into UserManagementContextService, moving activate/deactivate policy and mutation flows into UserManagementMutationService, moving delete-preview and delete-by-id lookup/orchestration into UserDeletionService, and collapsing repeated AJAX-vs-redirect plus lightweight API response shaping behind shared controller helpers. This also closed a lingering permission gap where activation previously bypassed canManageUser checks, leaving only ordinary controller-boundary behavior in UserManagement.php.
- Post-plan operational follow-up: removed SQLite from the supported runtime, setup, packaging, documentation, and test paths so the app is now explicitly MySQL/MariaDB-only.
- Post-plan operational follow-up: stabilized the MySQL-backed integration suite, hardened migration refresh paths that the real database exposed, and added a repeatable `npm run test:integration:mysql` runner for dedicated test-schema execution.
- DEBT-029: added controller-level FeatureTestTrait regression coverage for the admin database-backup flow, including unauthenticated rejection plus authenticated toggle/status/list/delete behavior against the real settings table and writable backup directory.
- DEBT-029: expanded the database-backup controller journey coverage with explicit invalid-filename and missing-file delete-path assertions, and fixed the controller to return the stable `NOT_FOUND` contract for valid-but-missing backups before path resolution checks.
- DEBT-029: expanded the database-backup controller journey coverage again with an explicit master-switch rejection regression that verifies authenticated backup creation is blocked with the expected `FORBIDDEN` contract when `database.allow_backup` is disabled.
- DEBT-029: expanded the database-backup controller journey coverage again with explicit invalid-filename and missing-file download assertions so the download endpoint’s validation and `NOT_FOUND` contracts are now locked down alongside the delete-path coverage.
- DEBT-029: expanded the database-backup controller journey coverage again with an explicit unauthenticated download regression that verifies the routed auth contract returns the expected `401 unauthenticated` error before the controller-level admin check runs.
- DEBT-029: added a focused authenticated-settings API regression for `POST /api/v1/settings` so the routed `api_auth` contract is now locked down to the expected `401 unauthorized` JSON response when no session or API credentials are present.
- DEBT-029: expanded the authenticated V1 settings API coverage with an explicit empty-payload regression so authenticated `POST /api/v1/settings` requests now have a locked-down `422 VALIDATION_ERROR` contract when neither JSON nor form data is supplied.
- DEBT-029: expanded the authenticated V1 settings API coverage again with an explicit `POST /api/v1/settings/logo` missing-file regression so the settings upload surface now has a locked-down `422 VALIDATION_ERROR` contract for absent logo uploads under an authenticated session.
- DEBT-029: expanded the authenticated V1 settings API coverage again with an explicit `POST /api/v1/settings/icon` missing-file regression so the sibling icon-upload surface now also has a locked-down `422 VALIDATION_ERROR` contract for absent icon uploads under an authenticated session.
- DEBT-029: expanded the authenticated V1 settings API coverage again with an explicit malformed-JSON regression so authenticated `POST /api/v1/settings` requests now also have a locked-down `422 VALIDATION_ERROR` contract when the request body is invalid JSON and no form fallback is available.
- DEBT-029: expanded the authenticated V1 settings API coverage again with an explicit update-success regression so authenticated `POST /api/v1/settings` requests now also lock down persistence of real setting keys while ignoring transport-only keys like `csrf_test_name` and `form_source`.
- DEBT-029: expanded the authenticated V1 settings API coverage again with an explicit JSON success-path regression so authenticated `POST /api/v1/settings` requests now also lock down typed persistence for JSON and boolean values through the real settings table.
- DEBT-029: expanded the authenticated V1 settings API coverage again with an explicit prefix-filter regression so authenticated `GET /api/v1/settings?prefix=general.` requests now lock down filtered settings retrieval without leaking keys from other prefixes.
- DEBT-029: added a public V1 settings regression for `GET /api/v1/settings/localization` so the public settings surface now has a locked-down compatibility contract for `firstDayOfWeek`/`first_day_of_week`, `timeFormat`, `is12Hour`, timezone, and nested localization context without requiring authentication.
- DEBT-029: expanded the public V1 settings regression coverage again with an explicit `GET /api/v1/settings/booking` contract so the public settings surface now also locks down visible/required field lists plus enabled custom-field configuration without requiring authentication.
- DEBT-029: expanded the public V1 settings regression coverage again with an explicit `GET /api/v1/settings/calendar-config` contract so the public settings surface now also locks down scheduler view, time-format, slot-range, locale, weekend toggle, timezone, and blocked-period configuration without requiring authentication.
- DEBT-029: expanded the public V1 settings regression coverage again with an explicit `GET /api/v1/settings/business-hours` contract so the public settings surface now also locks down seven-day payload scaffolding, per-day time fields, break-array shape, and closed-Sunday compatibility without requiring authentication.
- DEBT-029: added controller-level public customer-appointments API coverage for the hash-based history and autofill endpoints so the external customer surface now also locks down status-filter normalization, stable `NOT_FOUND` behavior for missing hashes, and the rule that public hash responses must not expose the internal numeric customer ID.
- DEBT-029: expanded the customer-appointments API coverage again with an explicit `GET /api/appointments/search` contract so the external search surface now also locks down `q` alias handling, status normalization from transport values like `no_show`, and stable enriched search results for cross-customer appointment discovery.
- DEBT-029: expanded the customer-appointments API coverage again with an explicit `GET /api/appointments/filters` contract so the external filter-options surface now also locks down active-only provider/service lists plus the canonical appointment-status option set returned to admin-facing search UIs.
- DEBT-029: added controller-level global-search coverage for `GET /search` plus the legacy `GET /dashboard/search` route so the external search surface now also locks down JSON unauthenticated rejection, authenticated combined-result payload shape, and parity between the primary and legacy dashboard entrypoints.
- DEBT-029: expanded the user-management controller journey coverage again with an explicit last-active-admin regression so the delete-preview and delete endpoints now also lock down the `LAST_ADMIN` block contract alongside the existing self-delete safety path.
- DEBT-029: expanded the availability API regression coverage again with an explicit `POST /api/availability/check` contract so the external availability surface now also locks down blocked-slot rejection, empty-conflict shape, and default-location fallback when the request omits `location_id`.
- DEBT-029: added controller-level appointments API journey coverage that exercises create, show, notes mutation, and status mutation through the real HTTP endpoints and verifies the persisted appointment state after each step.
- DEBT-029: expanded the appointments API controller journey coverage with an explicit failure-path regression that locks down duplicate-booking conflict responses and invalid-status mutation responses through the real HTTP endpoints.
- DEBT-029: expanded the appointments API controller journey coverage again with an explicit reschedule-conflict regression that verifies `PATCH /api/appointments/:id` returns the expected unprocessable response and preserves the original stored slot when the target time is already occupied.
- DEBT-029: expanded the appointments API controller journey coverage again with an explicit delete/cancel regression that verifies `DELETE /api/appointments/:id` returns the expected success payload, persists the `cancelled` status, and still returns the stable `NOT_FOUND` contract for a missing appointment.
- DEBT-029: added controller-level user-management journey coverage that exercises create, update, deactivate, activate, delete-preview, and delete for a provider through the real CSRF-protected controller endpoints and verifies the persisted user plus provider-schedule state after each step.
- DEBT-029: expanded the user-management controller journey coverage with an explicit self-delete safety regression that verifies delete-preview surfaces the `SELF_DELETE` block code and the delete endpoint returns the expected 422-style failure without removing the current admin account.
- DEBT-029: added controller-level public-booking journey coverage that exercises create, contact-verified lookup, reschedule, token rotation, persisted appointment-state changes, and the reschedule-policy rejection path through the real CSRF-protected public booking endpoints.
- DEBT-029: added controller-level public customer-portal coverage for `/my-appointments/{hash}` plus the `upcoming`, `history`, and `autofill` hash routes so the public self-service surface now also locks down JSON portal payload shaping, missing-hash handling, upcoming/history separation, and the rule that autofill responses expose the public customer hash but not the internal numeric customer ID.
- DEBT-029: added controller-level provider-schedule coverage for `GET|POST|DELETE /providers/{id}/schedule` so the schedule-management surface now also locks down provider-self updates, admin visibility/deletion, twelve-hour schedule normalization, persisted break windows, and forbidden cross-provider writes.
- DEBT-029: added controller-level provider/staff assignment coverage for `ProviderStaff` plus `StaffProviders` so the assignment-management surface now also locks down provider-driven assignment, duplicate-assignment conflict handling, staff self-view of assigned providers, admin-driven inverse assignments, and removal through the reverse-direction controller.
- DEBT-029: added public/authenticated V1 provider/service API coverage for `/api/providers`, `/api/v1/providers/{id}/services`, `/api/v1/providers/{id}/appointments`, and `/api/v1/services?providerId=` so the booking/calendar catalog surface now also locks down provider color exposure, active-only public provider services, provider appointment pagination/filter metadata, and authenticated provider-filtered service listings.
- DEBT-029: with the customer portal, provider schedule, staff assignment, and booking-facing provider/service API contracts now covered alongside the previously completed booking, appointments, settings, search, availability, database-backup, and user-management journeys, DEBT-029 is complete.

Next recommended batch:

- All tracked debt items are complete; future work should be ordinary feature or maintenance work rather than debt-plan follow-through.

## Audit Scope

The audit covered:

- Application controllers, services, models, helpers, commands, filters, and views
- Routes, migrations, config files, and public utilities
- Frontend JavaScript modules, SCSS, and Vite entrypoints
- Composer and npm manifests
- Unit, integration, database, session, and support tests

## Repository Facts

- Service layer PHP files: 52
- Total PHP test files: 50
- Unit test files under `tests/unit`: 30
- Integration test files under `tests/integration`: 18
- Skipped-test markers found: 0

The historical test problem was not skipped tests; it was placeholder coverage and large gaps over critical business flows that this remediation plan targeted.

---

## Phase 2A — Debt Status Matrix

| ID | Completed | Partial | Outstanding | Blocker |
|---|---|---|---|---|
| DEBT-001 | Yes | No | No | None |
| DEBT-002 | Yes | No | No | None |
| DEBT-003 | Yes | No | No | None |
| DEBT-004 | Yes | No | No | None |
| DEBT-005 | Yes | No | No | None |
| DEBT-006 | Yes | No | No | None |
| DEBT-007 | Yes | No | No | None |
| DEBT-008 | Yes | No | No | None |
| DEBT-009 | Yes | No | No | None |
| DEBT-010 | Yes | No | No | None |
| DEBT-011 | Yes | No | No | None |
| DEBT-012 | Yes | No | No | None |
| DEBT-013 | Yes | No | No | None |
| DEBT-014 | Yes | No | No | None |
| DEBT-015 | Yes | No | No | None |
| DEBT-016 | Yes | No | No | None |
| DEBT-017 | Yes | No | No | None |
| DEBT-018 | Yes | No | No | None |
| DEBT-019 | Yes | No | No | None |
| DEBT-020 | Yes | No | No | None |
| DEBT-021 | Yes | No | No | None |
| DEBT-022 | Yes | No | No | None |
| DEBT-023 | Yes | No | No | None |
| DEBT-024 | Yes | No | No | None |
| DEBT-025 | Yes | No | No | None |
| DEBT-026 | Yes | No | No | None |
| DEBT-027 | Yes | No | No | None |
| DEBT-028 | Yes | No | No | None |
| DEBT-029 | Yes | No | No | None |
| DEBT-030 | Yes | No | No | None |
| DEBT-031 | Yes | No | No | None |

---

## Phase 1 — Discovery Summary

### Dead Code

- Home controller has been removed and the routing fallback now points to AppFlow.
- Styleguide controller and views have been deleted.
- Legacy reminder alias commands have been removed from the main command surface.
- Diagnostic commands have been removed from the main command surface.
- One migration is explicitly a duplicate no-op migration.

### Duplicate and Repeated Code

- Settings helper duplication has been reduced to a compatibility wrapper over app_helper.
- Role display helper duplication has been consolidated into one canonical helper location.
- Currency formatting logic is now centralized behind the shared frontend currency utility.
- Appointment time normalization is now centralized behind the shared appointment-domain normalizer.
- Notification channel services now share integration lookup, encryption/decryption, health updates, and E.164 validation through a common support trait.

### Orphaned Code

- Styleguide feature has been deleted.
- Public clear-session utility has been removed from the public web roots.
- Receptionist-era migration/schema path remains historically embedded after conversion to staff.

### Inline Code That Should Be Abstracted

- Settings tab markup still lives on one page, but the block-period, database-backup, and general/tab form workflows now live in dedicated frontend modules instead of the PHP template.
- Customer-management live-search rendering and request orchestration now live in a dedicated frontend module instead of remaining inline in the PHP template.
- Search query composition now lives in a dedicated service/query layer.

### Structural and Architectural Debt

- Active API response contracts were standardized across the main JSON and V1 controller surfaces covered by the remedy plan.
- Appointment statuses are now centralized on one appointment-domain authority.
- UserManagement, Settings, and Dashboard now behave primarily as HTTP/controller boundaries over their supporting services and frontend modules, though UserManagement remains comparatively larger than most controllers.
- The transitional NotificationPhase1 abstraction has been retired in favor of NotificationPolicyService.
- Active project documentation is materially cleaner, though the remedy plan itself must continue to track the latest execution state.

### Dependency Debt

- mikey179/vfsstream was unused and has been removed.
- @coreui/coreui, @headlessui/tailwindcss, and material-icons were unused and have been removed.

### Test Debt

- Example scaffold tests and fixtures have been removed from the suite.
- MySQL/MariaDB-backed integration execution is now repeatable, and the remediation work added focused regression coverage across the highest-risk service and controller flows.
- Booking, notifications, user management, appointments, customer portal, provider schedule, staff assignment, and settings/API surfaces now have materially stronger regression coverage than at audit time.

---

## Phase 2 — Structured Debt Register

| ID | Category | Location | Description | Impact | Effort |
|---|---|---|---|---|---|
| DEBT-001 | Completed | app/Config/Routes.php; app/Config/Routing.php | Home controller was removed and the routing fallback now points to AppFlow, eliminating the obsolete entry path and configuration drift. | Resolved | Low |
| DEBT-002 | Completed | app/Controllers/Styleguide.php; app/Views/styleguide/index.php; app/Views/styleguide/components.php; app/Config/Routes.php | Deleted the unrouted Styleguide controller and view surface instead of reintroducing development-only routes. | Resolved | Low |
| DEBT-003 | Completed | public/clear-session.php; webschedulr-deploy/public/clear-session.php | Removed the orphaned public session-reset utility from both public roots because it bypassed the framework, was unreferenced, and exposed support-only behavior directly on the web surface. | Resolved | Low |
| DEBT-004 | Completed | app/Database/Migrations/2025-07-13-120400_CreateBlockedTimesTable.php; app/Database/Migrations/2025-08-24-120100_CreateBlockedTimesTable.php | Retained the duplicate blocked-times migration as an explicit historical no-op and documented why it must not be rewritten or allowed to drop the original table. | Resolved | Low |
| DEBT-005 | Completed | app/Database/Migrations/2025-10-22-174832_CreateReceptionistProvidersTable.php; app/Database/Migrations/2025-10-22-183826_ConvertReceptionistsToStaff.php | Documented the receptionist-era migration path as historical schema context superseded by the staff role, preserving compatibility without reintroducing the old domain model. | Resolved | Medium |
| DEBT-006 | Completed | app/Commands/DispatchNotificationQueue.php | Removed the legacy reminder alias commands and the now-unused shared abstract queue alias base command, leaving notifications:dispatch-queue as the single supported command. | Resolved | Low |
| DEBT-007 | Completed | app/Commands/TestCustomerSearch.php; app/Commands/TestEncryption.php | Removed the unreferenced diagnostic commands from the main command surface. | Resolved | Low |
| DEBT-008 | Completed | app/Controllers/Api/V1/BaseApiController.php; app/Controllers/Api/V1/Providers.php; app/Controllers/Api/V1/Services.php; app/Controllers/Api/V1/Settings.php | Removed the deprecated V1 base-controller shim and pointed the V1 controllers directly at the shared API base controller. | Resolved | Low |
| DEBT-009 | Completed | app/Helpers/app_helper.php; app/Helpers/settings_helper.php | settings_helper now acts as a compatibility wrapper over app_helper instead of maintaining a second implementation. | Resolved | Low |
| DEBT-010 | Completed | app/Helpers/app_helper.php; app/Helpers/user_helper.php; app/Helpers/permissions_helper.php | Shared role helper behavior is centralized in app_helper, with user and permissions helpers retained as compatibility entry points. | Resolved | Low |
| DEBT-011 | Completed | resources/js/currency.js; resources/js/public-booking.js; resources/js/modules/analytics/analytics-charts.js; resources/js/modules/appointments/time-slots-ui.js; resources/js/modules/scheduler/settings-manager.js; resources/js/modules/scheduler/right-panel.js; resources/js/modules/scheduler/appointment-details-modal.js | Consolidated frontend currency formatting onto the shared currency utility and removed duplicated fallback formatter implementations across booking, analytics, scheduler, and appointment-form surfaces. | Resolved | Low |
| DEBT-012 | Completed | app/Controllers/Appointments.php; app/Controllers/Api/Appointments.php; app/Services/Appointment/AppointmentDateTimeNormalizer.php | Extracted one shared appointment date-time normalizer and routed both appointment controllers through it so controller-boundary UTC/app-local conversion no longer diverges. | Resolved | Medium |
| DEBT-013 | Completed | app/Services/NotificationEmailService.php; app/Services/NotificationSmsService.php; app/Services/NotificationWhatsAppService.php; app/Services/Concerns/HandlesNotificationIntegrations.php | Notification channel services now share integration-row lookup, config crypto, health updates, and E.164 validation through one reusable support trait instead of duplicating that behavior per channel. | Resolved | Medium |
| DEBT-014 | Completed | app/Views/settings/index.php; resources/js/modules/settings/settings-page.js; resources/js/app.js | Extracted the settings block-period client workflow into the dedicated settings frontend module so the PHP view now supplies markup and endpoint data only. | Resolved | Medium |
| DEBT-015 | Completed | app/Views/settings/index.php; resources/js/modules/settings/settings-page.js; resources/js/app.js | Extracted the settings database-backup client workflow into the dedicated settings frontend module so the PHP view no longer carries that UI orchestration inline. | Resolved | Medium |
| DEBT-016 | Completed | app/Views/customer-management/index.php; resources/js/modules/customer-management/customer-search.js; resources/js/app.js; resources/js/modules/search/global-search.js | Moved customer-management live-search rendering, debounce, error handling, and JSON extraction into a dedicated frontend module wired through the main bundle. | Resolved | Low |
| DEBT-017 | Completed | app/Controllers/Search.php; app/Services/GlobalSearchService.php | Extracted global search orchestration and appointment-query composition into a dedicated service so the controller remains an authenticated HTTP boundary. | Resolved | Medium |
| DEBT-018 | Completed | app/Controllers/Api/BaseApiController.php; app/Controllers/Api/Appointments.php; app/Controllers/Api/Availability.php; app/Controllers/Api/CustomerAppointments.php; app/Controllers/Api/Locations.php; app/Controllers/Api/DatabaseBackup.php; app/Controllers/Api/V1/Settings.php; app/Controllers/Api/V1/Providers.php | Standardized active API and JSON-producing endpoints on the shared response helper contract, removing ad hoc setJSON chains and legacy fail/respond usage from those controller surfaces. | Resolved | Medium |
| DEBT-019 | Completed | app/Config/Constants.php; app/Models/AppointmentModel.php; app/Controllers/Appointments.php; app/Controllers/Api/Appointments.php; app/Services/DashboardService.php; app/Controllers/Api/CustomerAppointments.php; app/Services/Appointment/AppointmentStatus.php | Centralized active appointment status definitions, labels, option lists, and status-to-notification mapping on one appointment-domain helper and fixed the no-show constant mismatch. | Resolved | Medium |
| DEBT-020 | Completed | app/Controllers/UserManagement.php; app/Services/UserDeletionService.php; app/Services/UserManagementContextService.php; app/Services/UserManagementMutationService.php | UserManagement now delegates manageable-user resolution, validation-rule construction, delete-preview/delete execution, list/create/edit context assembly, store/update mutation orchestration, and activate/deactivate policy flows into dedicated services, leaving the controller as an ordinary HTTP boundary. | Resolved | High |
| DEBT-021 | Completed | app/Controllers/Api/Appointments.php; app/Services/Appointment/AppointmentMutationService.php; app/Services/Appointment/AppointmentManualNotificationService.php; app/Services/Appointment/AppointmentAvailabilityService.php; app/Services/Appointment/AppointmentQueryService.php; app/Services/Appointment/AppointmentFormatterService.php | The appointments API controller now delegates mutation, manual notify, availability checking, detail retrieval, and period-count shaping into dedicated appointment services, leaving the controller primarily as a transport/response boundary. | Resolved | High |
| DEBT-022 | Completed | app/Controllers/Settings.php; app/Services/Settings/NotificationSettingsService.php; app/Services/Settings/GeneralSettingsService.php; app/Services/Settings/SettingsPageService.php; app/Views/settings/index.php; resources/js/modules/settings/settings-page.js; resources/js/modules/settings/settings-form-ui.js | Completed the remaining Settings decomposition by moving the last large inline settings-page bootstrap/save script into a dedicated frontend module, leaving the PHP view as markup plus endpoint data handoff while the controller and services retain the server-side responsibilities. | Resolved | High |
| DEBT-023 | Completed | app/Controllers/Dashboard.php; app/Services/DashboardPageService.php; app/Services/DashboardApiService.php; app/Views/errors/html/error_403.php | Completed the final Dashboard controller-boundary cleanup by removing dead actions and unused dependencies, deleting redundant setup gating, replacing inline access-denied HTML with a dedicated 403 view, and moving the last inline stats fallback payload out of the controller. | Resolved | Medium |
| DEBT-024 | Completed | composer.json | Removed the unused vfsstream dev dependency. | Resolved | Low |
| DEBT-025 | Completed | package.json; tailwind.config.js; app/Views/components/setup-layout.php | Removed @coreui/coreui, @headlessui/tailwindcss, and material-icons after verifying the source tree relies on Google-hosted Material Symbols fonts and not the npm package. | Resolved | Low |
| DEBT-026 | Completed | tests/database/ExampleDatabaseTest.php; tests/session/ExampleSessionTest.php; tests/_support/Models/ExampleModel.php; tests/_support/Database/Seeds/ExampleSeeder.php | Removed framework scaffold example tests and support fixtures from the suite. | Resolved | Low |
| DEBT-027 | Completed | tests/unit/EnvironmentSmokeTest.php | Reclassified the old health check as explicit environment smoke coverage. | Resolved | Low |
| DEBT-028 | Completed | app/Services/PublicBookingService.php; app/Services/Appointment/AppointmentManualNotificationService.php; app/Services/Appointment/AppointmentDateTimeNormalizer.php; app/Services/Appointment/AppointmentFormatterService.php; app/Services/Appointment/AppointmentQueryService.php; app/Services/NotificationQueueDispatcher.php; app/Services/AppointmentBookingService.php; app/Services/DashboardService.php; app/Services/BookingSettingsService.php; app/Controllers/UserManagement.php; app/Services/UserManagementContextService.php; app/Services/UserManagementMutationService.php; app/Services/Settings/SettingsPageService.php; app/Services/Settings/GeneralSettingsService.php; app/Services/Settings/NotificationSettingsService.php; app/Services/Appointment/AppointmentMutationService.php; app/Services/Appointment/AppointmentAvailabilityService.php; tests/unit/PublicBookingServiceTest.php; tests/unit/Services/AppointmentManualNotificationServiceTest.php; tests/unit/Services/AppointmentDateTimeNormalizerTest.php; tests/unit/Services/AppointmentFormatterServiceTest.php; tests/unit/Services/AppointmentQueryServiceTest.php; tests/unit/Services/BusinessHoursServiceTest.php; tests/unit/Services/AppointmentConflictServiceTest.php; tests/unit/Services/DashboardBoundaryServicesTest.php; tests/unit/Services/DashboardServiceTest.php; tests/unit/Services/SettingsBoundaryServicesTest.php; tests/unit/Services/BookingSettingsServiceTest.php; tests/unit/Services/AppointmentBookingServiceTest.php; tests/unit/Services/UserManagementBoundaryServicesTest.php; tests/unit/Services/AppointmentBoundaryServicesTest.php; tests/unit/Services/NotificationQueueDispatcherTest.php | Added focused direct regression coverage across the highest-risk extracted service boundaries, including public booking orchestration, appointment mutation/query/formatting flows, dashboard/settings boundaries, notification queue dispatch branches, booking and availability behavior, user-management boundary services, customer appointment service paths, and notification policy/queue support. The meaningful direct service-layer gaps identified in this audit were closed, and DEBT-028 is complete. | Resolved | High |
| DEBT-029 | Completed | app/Controllers/Appointments.php; app/Controllers/Api/Appointments.php; app/Controllers/UserManagement.php; app/Controllers/Api/DatabaseBackup.php; app/Services/PublicBookingService.php; app/Services/NotificationQueueDispatcher.php; tests/unit/PublicBookingServiceTest.php; tests/integration/DatabaseBackupApiTest.php; tests/integration/AppointmentsApiJourneyTest.php; tests/integration/UserManagementJourneyTest.php; tests/integration/PublicBookingJourneyTest.php | Added controller-level regression coverage for the highest-risk routed user journeys, including database backup, settings APIs, appointments, availability, search, user management, public booking, customer portal, provider schedule, provider/staff assignment, and booking-facing provider/service APIs. The main controller and public-contract gaps identified in this audit were closed, and DEBT-029 is complete. | Resolved | Medium |
| DEBT-030 | Completed | app/Services/NotificationPolicyService.php; app/Services/NotificationCatalog.php; app/Services/Settings/NotificationSettingsService.php; app/Views/settings/tabs/notifications.php | Replaced the transitional NotificationPhase1 runtime facade with NotificationPolicyService so notification rules, integration status, and admin previews now live behind a stable business-domain service name instead of phase-scoped architecture. | Resolved | Medium |
| DEBT-031 | Completed | app/Controllers/Search.php; docs/architecture/master_context.md; app/Controllers/AppFlow.php | Updated the active project docs and root-entry comments to match AppFlow as the root controller and to remove live Styleguide references. | Resolved | Low |

---

## Phase 3 — Sequential Remediation Plan

Order rule used:

1. High impact + low effort
2. High impact + high effort
3. Low impact items last

### Quick Wins

1. Completed: DEBT-024, DEBT-026, and DEBT-027.
   Exact fix: remove the unused vfsstream dependency, delete the example scaffold tests and their example support fixtures, and either delete or explicitly reclassify HealthTest as environment smoke coverage.
   Why first: this immediately makes the quality signal from the test suite more honest with low implementation risk.

2. Completed: DEBT-009 and DEBT-010.
   Exact fix: consolidate settings and role helper behavior into one canonical helper location, update all callers, and remove or reduce duplicate helper files to compatibility wrappers only if required.
   Dependency: do this before any user-management UI cleanup so role labeling and helper behavior are stabilized.

3. Completed: DEBT-001 and DEBT-002.
    Exact fix: removed the dead Home controller and deleted the unreachable Styleguide feature.
    Decision applied: delete the unreachable Styleguide feature.

4. Completed: DEBT-006 and DEBT-007.
    Exact fix: removed legacy alias commands and diagnostic commands that were no longer operationally required.
    Decision applied: delete the unused legacy aliases and diagnostic commands.

5. Completed: DEBT-025.
    Exact fix: removed @coreui/coreui, @headlessui/tailwindcss, and material-icons after verifying the source tree and build output did not depend on them.
    Verification: `npm run build` passed after dependency removal.

6. Completed: DEBT-031.
    Exact fix: updated comments and docs to match the live root controller, active search frontend path, and current styleguide/Home state.
    Result: active docs now reflect the post-cleanup root-controller and Styleguide state.

### High-Impact Structural Work

7. Completed: DEBT-018 before splitting any large controller.
    Exact fix: standardized active API and JSON response contracts behind shared response helpers and removed hand-rolled setJSON status chains from the affected controller surfaces.
    Result: downstream controller decomposition can now work from one response contract.

8. Completed: DEBT-019.
    Exact fix: centralized active appointment status definitions, labels, filters, API option payloads, and status-driven notification mapping on a shared appointment-domain authority.

9. Completed: DEBT-012.
    Exact fix: extracted one canonical appointment date-time normalization service and routed both Appointments controllers through it.

10. Completed: DEBT-013.
    Exact fix: extracted shared notification integration persistence, encrypt/decrypt, health update, and E.164 validation behavior into a common notification-service support trait.

11. Completed: DEBT-030.
    Exact fix: retired the transitional NotificationPhase1 runtime facade, replaced it with NotificationPolicyService, and updated the settings/runtime consumers to the stable notification-policy service name.

12. Completed: DEBT-020.
    Exact fix: split UserManagement policy, validation-rule construction, delete-preview/delete orchestration, manageable-user resolution, and activate/deactivate mutation flow into dedicated services so the controller remains an HTTP boundary.
    Result: UserManagement.php now primarily coordinates request validation, service delegation, session updates, and transport responses without owning the core user-management business branches.
    Dependency satisfied: DEBT-018, DEBT-019, and DEBT-012 were completed first.

13. Fix DEBT-021.
    Exact fix: split Api/Appointments into thinner transport endpoints backed by dedicated services for validation, normalization, formatting, status transitions, and notification side effects.
    Completed: create, update, updateStatus, updateNotes, and delete now delegate to AppointmentMutationService, notify now delegates to AppointmentManualNotificationService, availability checking now delegates to AppointmentAvailabilityService, and show, counts, and summary now delegate to the appointment query/formatter layer for detail shaping and period counts.
    Dependency: complete DEBT-018, DEBT-019, and DEBT-012 first.

14. Completed: DEBT-022.
    Exact fix: moved the remaining large inline settings-page bootstrap/save logic into a dedicated frontend module so the view now acts as markup and endpoint data handoff while server-side settings orchestration remains in the extracted services.
    Result: the Settings page no longer hosts a monolithic inline client application.
    Dependency satisfied: DEBT-014 and DEBT-015 landed first.

15. Completed: DEBT-023.
    Exact fix: finished the last Dashboard controller-boundary cleanup by removing dead actions and unused dependencies, dropping redundant setup gating, replacing inline access-denied HTML with a dedicated 403 view, and moving the final inline stats fallback payload into DashboardPageService.
    Result: Dashboard.php now behaves mainly as a thin transport boundary over DashboardPageService and DashboardApiService.
    Dependency satisfied: DEBT-019 had already landed.

### Mid-Tier Cleanup

16. Completed: DEBT-014 and DEBT-015.
    Exact fix: extracted the block-period and database-backup client workflows from the settings view into the dedicated settings frontend module.
    Result: the settings view now acts as markup and data handoff instead of hosting those two inline page applications.

17. Completed: DEBT-017.
    Exact fix: moved Search query composition into GlobalSearchService and reduced Search to an authenticated response wrapper.
    Result: appointment/customer search orchestration now lives outside the controller.

18. Completed: DEBT-011.
    Exact fix: replaced local frontend currency formatter copies with one shared import path and one fallback policy rooted in the shared currency utility.
    Result: booking, analytics, scheduler, and appointment-form surfaces now use the same formatting implementation.

19. Completed: DEBT-005.
    Exact fix: documented and quarantined the receptionist-era migration path in the migration files themselves so the historical schema remains understandable without preserving receptionist as a live domain concept.
    Result: old terminology remains only as migration history.

20. Completed: DEBT-004.
    Exact fix: retained the duplicate blocked-times migration as a documented no-op and annotated why it must remain in the chain for applied-environment compatibility.
    Result: migration history is clearer without rewriting previously applied artifacts.

### Low-Impact Final Cleanup

21. Completed: DEBT-003.
    Exact fix: deleted clear-session.php from both public roots rather than preserving a framework-bypassing support utility on the live web surface.

22. Completed: DEBT-008.
    Exact fix: removed the deprecated V1 base controller shim and pointed the V1 controllers directly to the shared API base controller.
    Result: the extra compatibility inheritance layer is gone.

23. Completed: DEBT-016.
    Exact fix: moved customer-management inline search/render logic into a dedicated JS module and reused the shared JSON extraction helper.
    Result: the view no longer embeds a page-specific search application.

### Testing Expansion After Refactors Stabilize

24. Fix DEBT-028 and DEBT-029 now that the remaining controller/view cleanups above have settled.
    Exact execution order:
    - Add UserManagement service/controller-boundary tests around the extracted context, mutation, and deletion services
    - Add Settings workflow tests around SettingsPageService, GeneralSettingsService, NotificationSettingsService, and the database-backup/settings UI paths
    - Add dashboard authorization/boundary regression tests on the remaining controller transport behavior
    - Add notification queue and channel-service tests
    - Add public-booking and appointment journey regression coverage across create/lookup/update/cancellation flows
    Current state: focused dashboard boundary unit coverage exists for DashboardPageService, DashboardApiService, and AuthorizationService in tests/unit/Services/DashboardBoundaryServicesTest.php; PublicBookingService has dedicated unit coverage; the repository now has a repeatable MySQL/MariaDB-backed integration runner via `npm run test:integration:mysql`; user-management and notification-specific automated coverage are still absent.
    Dependency satisfied: DEBT-022 and DEBT-023 are now complete, so tests can lock the stabilized contracts rather than churn immediately.

---

## Human Verification Checklist

The following items require explicit human confirmation before action:

- None.

---

## Recommended First Execution Batch

If another agent starts acting on this plan, the safest next execution batch is:

1. No further debt-plan execution is required; all tracked debt items are complete.
2. Future work should start from ordinary feature, maintenance, or new audit priorities rather than this remediation plan.
3. If this document is retained as a living artifact, keep the repository facts and execution status synchronized with future repo changes.

This plan is now a closed remediation record rather than an active execution queue.

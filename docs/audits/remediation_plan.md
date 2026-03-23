Phase 1 — Discovery

Repository areas scanned: application controllers, services, models, helpers, migrations, commands, views, frontend modules, manifests, routes, public utilities, and tests.

Evidence gathered from:
Routes.php, composer.json, package.json, vite.config.js, tailwind.config.js, controller/service/helper/test inventories, and direct symbol searches across the workspace.

Key inventory facts:

The service layer contains 36 PHP files.
The test tree contains 19 PHP files total.
Only 4 unit service tests exist under the service-test folder.
Only 6 integration tests exist.
No skipped-test markers were found in the scanned test tree. The dominant test debt is weak, placeholder, or missing coverage rather than skipped tests.
Phase 2 — Audit Report

DEBT-001
Category: Completed
Location: Routes.php:46, Routing.php:51
Description: The dead Home controller was removed and the routing fallback now points to AppFlow, eliminating the obsolete entry-path ambiguity.
Impact: Resolved.
Effort: Low.

DEBT-002
Category: Completed
Location: Styleguide.php:51, index.php:219, components.php, Routes.php
Description: The unrouted Styleguide controller and views were deleted instead of being preserved as a dormant development-only feature.
Impact: Resolved.
Effort: Low.

DEBT-003
Category: Completed
Location: public/clear-session.php, webschedulr-deploy/public/clear-session.php
Description: The standalone public session-reset utility was removed from both public roots because it bypassed the framework entirely, cleared cookies and browser storage outside normal app controls, and was not referenced anywhere else in the repository.
Impact: Resolved.
Effort: Low.

DEBT-004
Category: Completed
Location: 2025-07-13-120400_CreateBlockedTimesTable.php:1, 2025-08-24-120100_CreateBlockedTimesTable.php:1
Description: The duplicate blocked-times migration was preserved as an explicitly documented historical no-op so applied environments keep a safe migration chain without risking table recreation or accidental rollback of the original table.
Impact: Resolved.
Effort: Low.

DEBT-005
Category: Completed
Location: 2025-10-22-174832_CreateReceptionistProvidersTable.php:1, 2025-10-22-183826_ConvertReceptionistsToStaff.php:1
Description: The receptionist-era migration path was documented as historical schema context superseded by staff, clarifying that the terminology remains only for migration compatibility and should not be reused in active product logic.
Impact: Resolved.
Effort: Medium.

DEBT-006
Category: Completed
Location: DispatchNotificationQueue.php:15
Description: The legacy reminder alias commands and their abstract base were removed, leaving notifications:dispatch-queue as the single supported queue command.
Impact: Resolved.
Effort: Low.

DEBT-007
Category: Completed
Location: TestCustomerSearch.php:12, TestEncryption.php:8
Description: The ad hoc diagnostic commands were removed from the main command namespace.
Impact: Resolved.
Effort: Low.

DEBT-008
Category: Completed
Location: BaseApiController.php:55, Providers.php:62, Services.php:60, Settings.php:65
Description: The deprecated V1 BaseApiController shim was removed, and the remaining V1 controllers now inherit directly from the shared API base controller.
Impact: Resolved.
Effort: Low.

DEBT-009
Category: Completed
Location: app_helper.php:82, settings_helper.php:18
Description: The settings helper was reduced to a compatibility wrapper over app_helper instead of maintaining a duplicate implementation of shared settings behavior.
Impact: Resolved.
Effort: Low.

DEBT-010
Category: Completed
Location: app_helper.php:229, user_helper.php:57, permissions_helper.php:54
Description: Shared role helper behavior was centralized in app_helper, with the user and permissions helpers retained only as compatibility entry points.
Impact: Resolved.
Effort: Low.

DEBT-011
Category: Completed
Location: currency.js, public-booking.js, analytics-charts.js, time-slots-ui.js, settings-manager.js, right-panel.js, appointment-details-modal.js
Description: Frontend currency formatting now routes through the shared currency utility, and duplicated formatter/fallback implementations were removed from booking, analytics, scheduler, and appointment-detail surfaces.
Impact: Resolved.
Effort: Low.

DEBT-012
Category: Completed
Location: Appointments.php, Api/Appointments.php, app/Services/Appointment/AppointmentDateTimeNormalizer.php
Description: The shared AppointmentDateTimeNormalizer now owns controller-boundary timezone normalization and UTC/app-local conversion for both the web and API appointment flows.
Impact: Resolved.
Effort: Medium.

DEBT-013
Category: Completed
Location: NotificationEmailService.php, NotificationSmsService.php, NotificationWhatsAppService.php, app/Services/Concerns/HandlesNotificationIntegrations.php
Description: The email, SMS, and WhatsApp channel services now share integration-row lookup, config encryption/decryption, health updates, and E.164 validation through a common notification integration support trait.
Impact: Resolved.
Effort: Medium.

DEBT-014
Category: Completed
Location: index.php:333, resources/js/modules/settings/settings-page.js
Description: The block-period management workflow was moved out of the settings view and into the dedicated settings frontend module, leaving the template responsible for markup and endpoint handoff only.
Impact: Resolved.
Effort: Medium.

DEBT-015
Category: Completed
Location: index.php:1177, resources/js/modules/settings/settings-page.js
Description: The database-backup UI client was moved out of the settings view and into the dedicated settings frontend module, reducing the template to markup plus endpoint configuration.
Impact: Resolved.
Effort: Medium.

DEBT-016
Category: Completed
Location: index.php, resources/js/modules/customer-management/customer-search.js, resources/js/app.js, resources/js/modules/search/global-search.js
Description: Customer-management live search was extracted from the inline view script into a dedicated frontend module, reusing the shared JSON extraction helper and bundle initialization path.
Impact: Resolved.
Effort: Low.

DEBT-017
Category: Completed
Location: Search.php, app/Services/GlobalSearchService.php
Description: Search orchestration and appointment query composition were moved into GlobalSearchService, leaving the controller as a thin authenticated HTTP boundary.
Impact: Resolved.
Effort: Medium.

DEBT-018
Category: Completed
Location: BaseApiController.php:74, Appointments.php:89, Availability.php:108, CustomerAppointments.php:95, Locations.php:77, DatabaseBackup.php:84, V1/Settings.php:70, V1/Providers.php:398
Description: Active API and JSON-producing controllers were standardized on the shared BaseApiController response helpers so success and error payloads now flow through one contract instead of mixed ok/data, success/message, fail*, and raw setJSON patterns.
Impact: Resolved.
Effort: Medium.

DEBT-019
Category: Completed
Location: Constants.php, AppointmentModel.php, Appointments.php, Api/Appointments.php, DashboardService.php, CustomerAppointments.php, app/Services/Appointment/AppointmentStatus.php
Description: A shared AppointmentStatus helper now owns canonical status values, labels, option payloads, normalization aliases, and status-driven notification mapping, eliminating the active no_show versus no-show drift.
Impact: Resolved.
Effort: Medium.

DEBT-020
Category: Structural & Architectural Debt
Location: UserManagement.php:1, app/Services/UserDeletionService.php, app/Services/UserManagementContextService.php, app/Services/UserManagementMutationService.php
Description: UserManagement still owns the HTTP boundary and validation rules, but delete-preview/delete execution, list/create/edit context assembly, and store/update mutation orchestration have been extracted into dedicated services. The controller is no longer the main home of those subdomains, though final transport/policy cleanup remains.
Impact: Medium. Risk is materially reduced, but the remaining controller boundary should still be simplified further when convenient.
Effort: High.

DEBT-021
Category: Completed
Location: Appointments.php:69, Appointments.php:937, app/Services/Appointment/AppointmentMutationService.php, app/Services/Appointment/AppointmentManualNotificationService.php, app/Services/Appointment/AppointmentAvailabilityService.php, app/Services/Appointment/AppointmentQueryService.php, app/Services/Appointment/AppointmentFormatterService.php
Description: Api/Appointments now delegates mutation, manual-notify, and availability-check orchestration into dedicated appointment services, while appointment detail retrieval and period-count shaping live in the appointment query/formatter layer rather than inlined inside the controller.
Impact: Resolved. The controller now behaves primarily as a transport and response boundary instead of a god-class orchestration surface.
Effort: High.

DEBT-022
Category: Structural & Architectural Debt
Location: Settings.php:164, Settings.php:244, Settings.php:519, app/Services/Settings/NotificationSettingsService.php, app/Services/Settings/GeneralSettingsService.php, app/Services/Settings/SettingsPageService.php, index.php:1
Description: The settings stack is still monolithic overall, but controller-side orchestration is now materially reduced: page-data assembly lives in SettingsPageService, notification loading/save/test orchestration lives in NotificationSettingsService, and the main settings save plus logo-upload flow lives in GeneralSettingsService.
Impact: Medium. Settings changes are still expensive overall because the view and remaining tab-specific logic are large, but the controller is now mostly an HTTP boundary with dedicated service seams.
Effort: High.

DEBT-023
Category: Structural & Architectural Debt
Location: Dashboard.php:1, DashboardPageService.php:1
Description: Dashboard is now materially decomposed: landing-page session resolution, dashboard access enforcement, fallback payload construction, landing view-data assembly, and metrics-endpoint payload shaping live in DashboardPageService, while charts, analytics, and status endpoint payload assembly live in DashboardApiService. Dashboard.php is now largely a transport/controller boundary with only lightweight response handling and the Search redirect left inline.
Impact: Medium, reduced. The dashboard boundary is substantially easier to test and evolve because payload assembly and policy orchestration no longer live in the controller.
Effort: Medium.

DEBT-024
Category: Dependency Debt
Location: composer.json:13
Description: The repository declares mikey179/vfsstream as a dev dependency, but there are no references to it anywhere in the tests or support code.
Impact: Low. Unused dependencies add install weight and noise, but the runtime risk is small.
Effort: Low.

DEBT-025
Category: Completed
Location: package.json:33, tailwind.config.js:186, setup-layout.php:24
Description: Removed @coreui/coreui, @headlessui/tailwindcss, and material-icons after verifying they were unused in the source tree and that icon rendering uses Google-hosted Material Symbols fonts.
Impact: Resolved.
Effort: Low.

DEBT-026
Category: Test Debt
Location: ExampleDatabaseTest.php:1, ExampleSessionTest.php:1, ExampleModel.php:7, ExampleSeeder.php:7
Description: The test suite still includes CodeIgniter example tests and example fixtures. They exercise framework scaffolding rather than application behavior and inflate the appearance of test coverage.
Impact: High. Placeholder tests create false confidence and distract from missing application coverage.
Effort: Low.

DEBT-027
Category: Test Debt
Location: HealthTest.php:8
Description: HealthTest validates environment wiring and base URL format rather than business logic. It is not harmful, but it is low-value coverage compared with the untested service and controller surfaces.
Impact: Low. It adds little confidence for the product’s actual behavior.
Effort: Low.

DEBT-028
Category: Test Debt
Location: NotificationQueueDispatcher.php:1, AppointmentBookingService.php:1, DashboardService.php:1, AuthorizationService.php:1, BusinessHoursServiceTest.php:1, AppointmentConflictServiceTest.php:1
Description: The service layer breadth is badly under-tested. There are 36 service files but only 4 unit service tests and 6 integration tests in total. Most business-critical services have no direct tests.
Impact: High. This is a systemic regression risk multiplier across the whole application.
Effort: High.

DEBT-029
Category: Test Debt
Location: Appointments.php:1, Appointments.php:69, UserManagement.php:1, PublicBookingService.php:1, NotificationQueueDispatcher.php:1, PublicBookingServiceTest.php:1
Description: Core user journeys remain untested or thinly tested: appointment CRUD, user-management flows, queue dispatch, notification sending, and most public-booking edge cases. PublicBookingService has some tests, but not enough for validation failures, timezone behavior, conflict handling, and fallback paths.
Impact: High. These are the highest-value and highest-risk paths in the product.
Effort: High.

DEBT-030
Category: Structural & Architectural Debt
Location: NotificationPolicyService.php:1, NotificationCatalog.php:1, NotificationSettingsService.php:1, settings/tabs/notifications.php:1
Description: Completed by replacing the transitional NotificationPhase1 runtime facade with NotificationPolicyService so notification rules, integration-status projection, and admin preview generation now live behind a stable business-domain service name.
Impact: Resolved.
Effort: Medium.

DEBT-031
Category: Completed
Location: Search.php:39, master_context.md:468, AppFlow.php:47
Description: The active docs and root-entry comments were updated to match AppFlow as the root controller and to remove live Styleguide references.
Impact: Resolved.
Effort: Low.

Phase 3 — Single Consolidated Remediation Plan

Remove the fake safety net first. Address DEBT-024, DEBT-026, and DEBT-027 by deleting the unused vfsstream dependency, removing the ExampleDatabaseTest and ExampleSessionTest scaffolding plus their example fixture/support files, and either deleting or clearly reclassifying HealthTest as environment smoke coverage. This is a high-impact, low-effort cleanup because it immediately makes the test surface more honest.

Collapse helper duplication next. Address DEBT-009 and DEBT-010 by choosing one canonical helper home for settings and role-display utilities, migrating all callers to it, then deleting or stubbing the duplicate files with minimal compatibility wrappers only if required. Do this before any user-management UI cleanup so role-label behavior is stabilized first.

Remove obviously dead runtime surfaces after confirmation. Address DEBT-001, DEBT-002, DEBT-006, and DEBT-007 by deleting the dead Home controller, retiring the unreachable Styleguide controller/views, and removing the legacy alias and diagnostic commands. This batch has been completed.

Decide the fate of the public emergency utility. Address DEBT-003 by either removing clear-session.php or moving it behind a controlled support-only or environment-gated path. This batch has been completed by removing the public utility from both public roots.

Clean the migration history while it is still understandable. Address DEBT-004 and DEBT-005 by documenting the duplicate blocked-times migration and the receptionist-to-staff supersession, then either archiving the duplicate intent in docs or leaving explicit comments/tests that lock the migration history. This batch has been completed as migration-file annotations without rewriting applied history.

Prune unused frontend dependencies. Address DEBT-025 by removing @coreui/coreui, @headlessui/tailwindcss, and material-icons after source verification and build validation. This batch has been completed.

Standardize API response contracts before splitting large controllers. Address DEBT-018 first, because controller decomposition will be cleaner once success and error response helpers are unified. Exact fix: consolidate on one response contract for API controllers, add shared response helpers for non-API JSON endpoints, and replace local setJSON chains and custom fail helpers. This batch has been completed across the active API controller surfaces.

Completed: DEBT-019. Appointment status definitions, labels, API filter options, and status-driven notification mapping now flow through the shared AppointmentStatus domain helper.

Completed: DEBT-012. Appointment date-time normalization now runs through the shared AppointmentDateTimeNormalizer for both the web and API appointment controllers.

Completed: DEBT-013. Notification channel services now share integration-row lookup, config crypto, health updates, and E.164 validation through the common HandlesNotificationIntegrations trait.

Completed: DEBT-008. The deprecated V1 API base-controller shim is gone, and the V1 controllers now inherit directly from the shared API base controller.

Completed: DEBT-030. Runtime-wide notification defaults, channels, and event definitions live in NotificationCatalog, and the transitional NotificationPhase1 facade has been replaced with NotificationPolicyService as the stable notification-policy runtime surface.

Completed: DEBT-014 and DEBT-015. The settings block-period and database-backup workflows now live in the dedicated settings frontend module, and the PHP view is reduced to markup and endpoint handoff for those features.

Completed: DEBT-016. Customer-management live search now runs from a dedicated frontend module, and the view no longer embeds debounce, fetch, error handling, and rendering logic inline.

Completed: DEBT-017. Search orchestration now runs through GlobalSearchService, and Search no longer owns the join-heavy appointment query logic directly.

Split the large controllers by responsibility. Address DEBT-020, DEBT-021, DEBT-022, and DEBT-023 in this order: UserManagement, Api/Appointments, Settings, then Dashboard. Exact fix: split CRUD, validation, formatting, side-effect orchestration, and support utilities into services or dedicated controllers/modules. Progress: UserManagement deletion preview/delete execution now live in UserDeletionService, list/create/edit context loading now lives in UserManagementContextService, and store/update mutation orchestration now lives in UserManagementMutationService. Api/Appointments is now materially decomposed: create, update, updateStatus, updateNotes, and delete delegate to AppointmentMutationService; notify delegates to AppointmentManualNotificationService; availability checking delegates to AppointmentAvailabilityService; and show, counts, and summary delegate to the appointment query/formatter layer for detail shaping and period counts. Settings is now materially reduced on the controller side: page-data assembly delegates to SettingsPageService, notification loading/save/test orchestration delegates to NotificationSettingsService, and the main settings save plus logo-upload flow delegates to GeneralSettingsService. Dashboard is now materially reduced across both landing and API paths: session resolution, access enforcement, landing payload assembly, fallback payload construction, and metrics-endpoint response shaping delegate to DashboardPageService, while charts, analytics, and status payload assembly delegate to DashboardApiService. Dependency: do not start this until DEBT-018, DEBT-019, and DEBT-012 are complete, or you will preserve inconsistent contracts inside smaller files.

Completed: DEBT-011. Frontend currency formatting now uses the shared currency utility across booking, analytics, scheduler, and appointment-detail surfaces instead of carrying local fallback implementations.

Repair documentation drift last among the low-risk items. Address DEBT-031 after the actual code cleanup so the documentation reflects the chosen end state rather than an intermediate one. This batch has been completed for the active project docs covering Search, Home, and Styleguide references.

Build real coverage around the critical paths. Address DEBT-028 and DEBT-029 after the high-churn refactors above settle. Exact order:

Add notification queue and channel-service tests.

Add appointment API and web appointment flow tests.

Add user-management tests.

Add public-booking edge-case tests.

Add dashboard authorization/aggregation tests.
Progress: dashboard boundary unit coverage now exists for DashboardPageService, DashboardApiService, and AuthorizationService in tests/unit/Services/DashboardBoundaryServicesTest.php. The older dashboard integration class was previously blocked by legacy test infrastructure debt; the repository is now being standardized on MySQL/MariaDB-backed testing instead.
Dependency: wait until response contracts, status constants, and date-time normalization are stable, or you will churn tests immediately.

Keep one residual verification gate for every item that still depends on product or operational intent. That now includes only the remaining naming and business-context decision around DEBT-030. The runtime constants leak itself has already been removed.

Residual note: I did not find skipped tests in the current test tree. The main testing problem is not abandoned tests; it is placeholder scaffolding and missing coverage over critical business flows.
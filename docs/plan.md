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
Category: Dead Code
Location: Home.php:44, Routes.php:46, Routing.php:51
Description: The Home controller is still present as an entry-point controller, but the explicit root route already points to AppFlow instead of Home. The routing config still names Home as the default controller, which creates ambiguity about the real application entry path. This is dead code plus configuration drift.
Impact: Medium. It enlarges the maintenance surface and makes route behavior harder to reason about.
Effort: Low.

DEBT-002
Category: Orphaned Code
Location: Styleguide.php:51, index.php:219, components.php, app/Views/styleguide/scheduler.php, Routes.php
Description: The Styleguide controller and its views describe reachable pages, but no styleguide route is defined in the active route map. The assets still exist and even link to each other, but they are unreachable in the current application.
Impact: Low. It does not affect runtime behavior directly, but it is a clear orphaned feature branch in production code.
Effort: Low.

DEBT-003
Category: Orphaned Code
Location: clear-session.php:1
Description: This standalone public utility bypasses the framework entirely, clears all cookies and browser storage, and is not referenced anywhere else in the repository. It may still be useful for manual support recovery, but from the codebase perspective it is an orphaned public script and a special-case operational path. NEEDS HUMAN VERIFICATION.
Impact: Medium. Public ad hoc utilities are easy to forget, bypass normal security/layout/filter flows, and complicate deployment hardening.
Effort: Low.

DEBT-004
Category: Dead Code
Location: 2025-07-13-120400_CreateBlockedTimesTable.php:1, 2025-08-24-120100_CreateBlockedTimesTable.php:1
Description: The later blocked-times migration is explicitly a duplicate no-op migration. It exists only to avoid re-creating a table already created by the earlier migration. That preserves history, but it is still technical debt because the migration timeline now encodes a known duplicate artifact.
Impact: Medium. It increases migration confusion and makes schema history harder to trust.
Effort: Low.

DEBT-005
Category: Orphaned Code
Location: 2025-10-22-174832_CreateReceptionistProvidersTable.php:1, 2025-10-22-183826_ConvertReceptionistsToStaff.php:1
Description: The repository still carries schema history for a receptionist role and a receptionist_providers table, then later converts receptionists to staff. That leaves a superseded concept embedded in the migration chain and in surrounding compatibility logic.
Impact: Medium. It preserves obsolete domain language and increases the chance of future feature work reusing the wrong concept.
Effort: Medium.

DEBT-006
Category: Dead Code
Location: SendAppointmentReminders.php:5, SendAppointmentSmsReminders.php:5, SendAppointmentWhatsAppReminders.php:5, DispatchNotificationQueue.php:15
Description: Three reminder commands are explicitly labeled as legacy aliases for the canonical notifications:dispatch-queue command. They may still exist for backward compatibility with old cron jobs, but they are redundant command surface. NEEDS HUMAN VERIFICATION before deletion.
Impact: Low. Redundant aliases increase CLI surface area and operational ambiguity.
Effort: Low.

DEBT-007
Category: Dead Code
Location: TestCustomerSearch.php:12, TestEncryption.php:8
Description: These are ad hoc diagnostic commands shipped in the main command namespace. TestEncryption is especially problematic because it performs persistence-side effects against notification configuration and contains a duplicated success/decrypt block in the command body. They are maintenance-only tools exposed as production commands. NEEDS HUMAN VERIFICATION.
Impact: Medium. Diagnostic commands with side effects are easy to misuse and should not live indefinitely in the main operational surface.
Effort: Low.

DEBT-008
Category: Structural & Architectural Debt
Location: BaseApiController.php:55, Providers.php:62, Services.php:60, Settings.php:65
Description: The V1 BaseApiController is explicitly deprecated, but the V1 controllers still inherit from it. This is a compatibility shim kept alive only for namespace clarity and backward compatibility.
Impact: Low. It is not broken, but it formalizes an extra inheritance layer that the codebase already considers obsolete.
Effort: Low.

DEBT-009
Category: Duplicate & Repeated Code
Location: app_helper.php:82, settings_helper.php:62, settings_helper.php:18
Description: The settings helper duplicates core helper functions already defined in app_helper, including setting, provider_image_url, settings_by_prefix, setting_url, and fallback-path logic. The duplicate helper is not referenced anywhere except its own header comment. This is both duplicate code and likely orphaned compatibility baggage.
Impact: High. Shared helper behavior now has two authoritative copies and one appears unused.
Effort: Low.

DEBT-010
Category: Duplicate & Repeated Code
Location: app_helper.php:229, user_helper.php:67, permissions_helper.php:190, app_helper.php:241, permissions_helper.php:206
Description: Role display and role-permission description helpers are duplicated across three helper files, with inconsistent human-readable labels such as Admin versus Administrator and Provider versus Service Provider. This is duplicated logic plus naming inconsistency for the same domain concept.
Impact: Medium. UI wording and permission descriptions can drift silently across views.
Effort: Low.

DEBT-011
Category: Duplicate & Repeated Code
Location: currency.js:54, public-booking.js:60, analytics-charts.js:41, time-slots-ui.js:16
Description: Currency formatting exists as a central utility, but three other frontend modules still implement their own fallback currency formatter logic. The repeated implementations differ slightly in symbol source and decimal handling.
Impact: Medium. Formatting behavior can diverge across booking, analytics, and appointment forms.
Effort: Low.

DEBT-012
Category: Duplicate & Repeated Code
Location: Appointments.php:393, Appointments.php:937, Appointments.php:965
Description: Timezone normalization and UTC/app-local conversion logic is implemented separately in the web controller and the API controller. The methods are solving the same problem with slightly different inputs and return shapes instead of using one shared normalization service.
Impact: High. Date-time bugs are expensive and hard to detect, especially in booking and update flows.
Effort: Medium.

DEBT-013
Category: Duplicate & Repeated Code
Location: NotificationEmailService.php:359, NotificationEmailService.php:375, NotificationEmailService.php:391, NotificationSmsService.php:398, NotificationSmsService.php:414, NotificationSmsService.php:429, NotificationWhatsAppService.php:751, NotificationWhatsAppService.php:767, NotificationWhatsAppService.php:782
Description: Notification channel services each reimplement integration-row lookup, encryption, decryption, and health-update routines. SMS and WhatsApp also duplicate E.164 validation. This is a classic copy-and-specialize pattern that should be in a shared base service or trait.
Impact: High. Security-sensitive behavior and integration health logic can drift per channel.
Effort: Medium.

DEBT-014
Category: Inline Code That Should Be Abstracted
Location: index.php:333
Description: The settings page embeds a full block-period management workflow directly in the view: modal state, validation, CSRF handling, fetch calls, persistence, and rendering. That is client application logic living inside a template rather than a frontend module.
Impact: Medium. It makes the settings page difficult to test, refactor, and reason about.
Effort: Medium.

DEBT-015
Category: Inline Code That Should Be Abstracted
Location: index.php:1177
Description: The same settings view also embeds a database-backup UI client with API base handling, fetch orchestration, fallback listing behavior, and DOM updates. This is a second substantial client-side subsystem buried in a PHP view.
Impact: Medium. It compounds the maintenance cost of an already oversized settings page.
Effort: Medium.

DEBT-016
Category: Inline Code That Should Be Abstracted
Location: index.php:308
Description: Customer search rendering, debounce behavior, error-state handling, and inline date formatting live directly in the customer-management template. This is view-embedded application logic that should be a frontend module.
Impact: Low. It is localized debt, but it reinforces the pattern of heavy inline scripts in views.
Effort: Low.

DEBT-017
Category: Inline Code That Should Be Abstracted
Location: Search.php:77
Description: The Search controller builds multi-join appointment search queries directly in the controller instead of delegating to a service or model query object. That mixes HTTP handling with search/business/data-access logic.
Impact: Medium. Search behavior becomes harder to reuse, test, or optimize independently.
Effort: Medium.

DEBT-018
Category: Structural & Architectural Debt
Location: BaseApiController.php:74, Appointments.php:136, Availability.php:179, CustomerAppointments.php:121, Locations.php:104, ServiceCategories.php:106, ProviderSchedule.php:254
Description: The repository defines a canonical API base controller, but many API and quasi-API endpoints bypass it and hand-roll status-code plus JSON responses. Response contracts vary between success/message, ok/data, and direct raw payloads. ProviderSchedule even redefines local fail helpers instead of using a shared response contract.
Impact: High. Inconsistent API contracts slow frontend work and make clients more brittle.
Effort: Medium.

DEBT-019
Category: Inline Code That Should Be Abstracted
Location: Constants.php:97, AppointmentModel.php:234, Appointments.php:768, Appointments.php:475, DashboardService.php:168, CustomerAppointments.php:315
Description: Appointment status values are scattered as string literals throughout models, services, controllers, views, and migrations even though the codebase already has both constants and a SIMPLE_STATUSES list. The same domain enum is being restated in many places.
Impact: High. Status drift creates bugs in validation, analytics, queueing, and UI filters.
Effort: Medium.

DEBT-020
Category: Structural & Architectural Debt
Location: UserManagement.php:1
Description: UserManagement is a 1391-line controller that handles user CRUD, permission checks, schedule preparation, staff assignment, delete impact analysis, and audit concerns. That is a god controller spanning multiple subdomains.
Impact: High. Any change in user management now risks collateral regression across unrelated responsibilities.
Effort: High.

DEBT-021
Category: Structural & Architectural Debt
Location: Appointments.php:69, Appointments.php:937
Description: Api/Appointments is a 1234-line controller that mixes transport concerns, validation, status transitions, notification triggering, date normalization, and formatting. This is a god controller in the system’s most critical API surface.
Impact: High. High churn and high blast radius in appointment APIs increase regression risk materially.
Effort: High.

DEBT-022
Category: Structural & Architectural Debt
Location: Settings.php:164, Settings.php:244, Settings.php:519, index.php:1
Description: The settings stack is monolithic on both server and client. The controller handles general settings, notification rules, integrations, templates, and file uploads. The view is 1497 lines and still embeds significant JavaScript workflows. This is mixed abstraction on both sides of the request boundary.
Impact: High. Settings changes are expensive, difficult to isolate, and likely to cause regressions across unrelated tabs.
Effort: High.

DEBT-023
Category: Structural & Architectural Debt
Location: Dashboard.php:1, Dashboard.php:170, Dashboard.php:280
Description: Dashboard still owns substantial session validation, authorization flow control, fallback data construction, and data shaping despite the presence of DashboardService and AuthorizationService. The controller is smaller than the other god classes, but it still mixes orchestration, policy, and presentation shaping.
Impact: Medium. It is harder than necessary to test and evolve the dashboard boundary.
Effort: Medium.

DEBT-024
Category: Dependency Debt
Location: composer.json:13
Description: The repository declares mikey179/vfsstream as a dev dependency, but there are no references to it anywhere in the tests or support code.
Impact: Low. Unused dependencies add install weight and noise, but the runtime risk is small.
Effort: Low.

DEBT-025
Category: Dependency Debt
Location: package.json:33, package.json:35, package.json:47, tailwind.config.js:186, setup-layout.php:24
Description: The repository declares @coreui/coreui and @headlessui/tailwindcss, but there are no usage references outside the manifest. The material-icons package also has no import references, while actual icon rendering appears to rely on Google Material Symbols fonts and SCSS classes. The first two look unused; the last one likely is, but NEEDS HUMAN VERIFICATION.
Impact: Medium. Unused frontend dependencies increase install/build surface and obscure what the UI stack actually depends on.
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
Location: NotificationPhase1.php:59, Settings.php:164, AppointmentNotificationService.php:75, NotificationQueueDispatcher.php:79, Events.php:73
Description: NotificationPhase1 has become a cross-cutting compatibility layer for event lists, channel lists, business defaults, preview behavior, and queue orchestration. It is clearly active, but it centralizes a transitional concept and hard-codes a single-business default across the notification stack. NEEDS HUMAN VERIFICATION on intended long-term architecture.
Impact: High. The notification system’s future evolution is constrained by an explicitly phase-scoped abstraction that leaked into core runtime paths.
Effort: High.

DEBT-031
Category: Structural & Architectural Debt
Location: Search.php:39, mastercontext.md:468, mastercontext.md:40, AppFlow.php:47
Description: Documentation and code comments are out of sync with the running system. Search points to a non-existent frontend path, mastercontext still describes Home as the root controller, and styleguide references remain documented despite being unreachable.
Impact: Low. Documentation drift slows maintenance and increases onboarding error rates.
Effort: Low.

Phase 3 — Single Consolidated Remediation Plan

Remove the fake safety net first. Address DEBT-024, DEBT-026, and DEBT-027 by deleting the unused vfsstream dependency, removing the ExampleDatabaseTest and ExampleSessionTest scaffolding plus their example fixture/support files, and either deleting or clearly reclassifying HealthTest as environment smoke coverage. This is a high-impact, low-effort cleanup because it immediately makes the test surface more honest.

Collapse helper duplication next. Address DEBT-009 and DEBT-010 by choosing one canonical helper home for settings and role-display utilities, migrating all callers to it, then deleting or stubbing the duplicate files with minimal compatibility wrappers only if required. Do this before any user-management UI cleanup so role-label behavior is stabilized first.

Remove obviously dead runtime surfaces after confirmation. Address DEBT-001, DEBT-002, DEBT-006, and DEBT-007 by deleting the dead Home controller, retiring the unreachable Styleguide controller/views, and removing legacy alias or diagnostic commands that are no longer used. Dependency: confirm whether any deployment cron or support process still calls the legacy commands before deleting them. Human decision required for the diagnostic commands and any dev-only styleguide need.

Decide the fate of the public emergency utility. Address DEBT-003 by either removing clear-session.php or moving it behind a controlled support-only or environment-gated path. Dependency: make this decision before broader security hardening or public-surface audits so it does not remain an undocumented exception.

Clean the migration history while it is still understandable. Address DEBT-004 and DEBT-005 by documenting the duplicate blocked-times migration and the receptionist-to-staff supersession, then either archiving the duplicate intent in docs or leaving explicit comments/tests that lock the migration history. Dependency: do not rewrite historical migrations blindly if environments have already applied them. Human decision required on whether to preserve migration artifacts for deployed-instance compatibility.

Prune unused frontend dependencies. Address DEBT-025 by removing @coreui/coreui and @headlessui/tailwindcss after one final build verification. Treat material-icons separately and only remove it if a human confirms the app relies exclusively on Google Material Symbols font loading and not on the npm package anywhere else.

Standardize API response contracts before splitting large controllers. Address DEBT-018 first, because controller decomposition will be cleaner once success and error response helpers are unified. Exact fix: consolidate on one response contract for API controllers, add shared response helpers for non-API JSON endpoints, and replace local setJSON chains and custom fail helpers. Dependency: fix this before DEBT-021 and before refactoring ProviderSchedule or ServiceCategories.

Centralize appointment status definitions. Address DEBT-019 by choosing one authoritative source for status values and labels, then update validation, analytics, queueing, API filters, and views to consume that source. Dependency: do this before DEBT-021, DEBT-023, and any notification-status work, because those layers all branch on status values today.

Extract shared date-time normalization. Address DEBT-012 by creating one canonical appointment date-time normalization service or utility and routing both Appointments controllers through it. Dependency: do this before splitting the appointments controllers, or the duplication will be moved instead of removed.

Consolidate notification integration internals. Address DEBT-013 by extracting shared integration-row lookup, encryption, decryption, health updates, and phone validation into a common notification integration base or trait. Dependency: do this before DEBT-030, because the NotificationPhase1 refactor will be easier once the channel services are already normalized.

Make a human architecture decision on NotificationPhase1. Address DEBT-030 only after DEBT-013. Exact decision required: either confirm that the product will remain effectively single-business and rename/simplify NotificationPhase1 into a stable notification policy component, or confirm a real multi-business future and replace phase-scoped constants/defaults with proper business-context injection.

Extract client logic from views in the settings stack. Address DEBT-014 and DEBT-015 by moving block-period and database-backup JavaScript into dedicated frontend modules, keeping the PHP view responsible only for rendering data and element hooks. Dependency: do this before or together with DEBT-022 so the settings monolith shrinks in a controlled way.

Extract the remaining small inline view logic. Address DEBT-016 by moving customer-management search and formatting behavior into a dedicated JS module. This is lower risk than the settings extraction and can be done independently once the app’s module pattern is chosen.

Move search query logic out of the controller. Address DEBT-017 by extracting appointment/customer search orchestration into a dedicated search service or query object, leaving Search as a thin HTTP boundary. Dependency: do this after helper and response-contract cleanup, but before any broader search or analytics feature work.

Split the large controllers by responsibility. Address DEBT-020, DEBT-021, DEBT-022, and DEBT-023 in this order: UserManagement, Api/Appointments, Settings, then Dashboard. Exact fix: split CRUD, validation, formatting, side-effect orchestration, and support utilities into services or dedicated controllers/modules. Dependency: do not start this until DEBT-018, DEBT-019, and DEBT-012 are complete, or you will preserve inconsistent contracts inside smaller files.

Replace duplicate frontend currency formatting with the shared utility. Address DEBT-011 after the controller/API contract cleanup, because some formatting call sites may also change when response payloads are standardized. Exact fix: import the shared currency formatter or a shared wrapper from one location and delete local copies.

Repair documentation drift last among the low-risk items. Address DEBT-031 after the actual code cleanup so the documentation reflects the chosen end state rather than an intermediate one. Exact fix: update route docs, controller comments, and frontend path references for Search, Home, and Styleguide.

Build real coverage around the critical paths. Address DEBT-028 and DEBT-029 after the high-churn refactors above settle. Exact order:

Add notification queue and channel-service tests.

Add appointment API and web appointment flow tests.

Add user-management tests.

Add public-booking edge-case tests.

Add dashboard authorization/aggregation tests.
Dependency: wait until response contracts, status constants, and date-time normalization are stable, or you will churn tests immediately.

Keep one residual verification gate for every item marked NEEDS HUMAN VERIFICATION. That includes DEBT-003, DEBT-006, DEBT-007, DEBT-025 for material-icons, and DEBT-030. These should not be auto-deleted or auto-refactored until a human confirms operational usage and intended product direction.

Residual note: I did not find skipped tests in the current test tree. The main testing problem is not abandoned tests; it is placeholder scaffolding and missing coverage over critical business flows.
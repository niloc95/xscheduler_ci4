# Agent Context Remedy Action Plan

Date: 2026-03-19
Repository: xscheduler_ci4
Branch Audited: calendar-refactor

## Purpose

This document captures the read-only technical debt audit of the repository and converts it into a prioritized, sequential remediation plan that another AI agent or developer can execute without ambiguity.

## Audit Scope

The audit covered:

- Application controllers, services, models, helpers, commands, filters, and views
- Routes, migrations, config files, and public utilities
- Frontend JavaScript modules, SCSS, and Vite entrypoints
- Composer and npm manifests
- Unit, integration, database, session, and support tests

## Repository Facts

- Service layer PHP files: 36
- Total PHP test files: 19
- Unit service test files: 4
- Integration test files: 6
- Skipped-test markers found: 0

The main test problem is not skipped tests. It is placeholder coverage and large gaps over critical business flows.

---

## Phase 1 — Discovery Summary

### Dead Code

- Home controller remains in the codebase but the root route points to AppFlow instead.
- Styleguide controller and views exist without active route bindings.
- Three reminder commands are explicit legacy aliases for the canonical notification queue command.
- Diagnostic commands remain in the main command surface.
- One migration is explicitly a duplicate no-op migration.

### Duplicate and Repeated Code

- Settings helper duplicates core app helper behavior.
- Role display helpers are implemented in three separate helper files.
- Currency formatting logic is duplicated across multiple frontend modules.
- Appointment time normalization logic is duplicated between web and API controllers.
- Notification channel services duplicate encryption, decryption, integration lookup, and health update routines.

### Orphaned Code

- Styleguide feature appears unreachable.
- Public clear-session utility is present but unreferenced.
- Receptionist-era migration/schema path remains historically embedded after conversion to staff.

### Inline Code That Should Be Abstracted

- Settings view contains large client-side workflows inline.
- Customer management view contains embedded client-side search/formatting logic.
- Search controller contains query composition logic that should be in a service/query layer.

### Structural and Architectural Debt

- API response contracts are inconsistent across controllers.
- Appointment statuses are defined in many places despite existing constants.
- UserManagement, Api/Appointments, Settings, and Dashboard are oversized mixed-responsibility controllers.
- NotificationPhase1 has become a runtime-wide transitional abstraction.
- Documentation and code comments are out of sync with the live system.

### Dependency Debt

- mikey179/vfsstream appears unused.
- @coreui/coreui and @headlessui/tailwindcss appear unused.
- material-icons likely appears unused, but requires human verification before removal.

### Test Debt

- Example scaffold tests and fixtures remain in the suite.
- Critical business services and controllers are largely untested.
- Booking, notifications, user management, and appointment APIs have insufficient coverage.

---

## Phase 2 — Structured Debt Register

| ID | Category | Location | Description | Impact | Effort |
|---|---|---|---|---|---|
| DEBT-001 | Dead Code | app/Controllers/Home.php; app/Config/Routes.php; app/Config/Routing.php | Home controller remains while the explicit root route now points to AppFlow, leaving an obsolete entry path and configuration drift. | Medium: increases ambiguity around the real application entry point. | Low |
| DEBT-002 | Orphaned Code | app/Controllers/Styleguide.php; app/Views/styleguide/index.php; app/Views/styleguide/components.php; app/Views/styleguide/scheduler.php; app/Config/Routes.php | Styleguide controller and views describe reachable pages, but no active routes expose them. | Low: no direct runtime failure, but clear orphaned feature surface. | Low |
| DEBT-003 | Orphaned Code | public/clear-session.php | Standalone public utility bypasses the framework, is not referenced anywhere else, and may only be a support artifact. NEEDS HUMAN VERIFICATION. | Medium: bypasses normal app controls and complicates public-surface hardening. | Low |
| DEBT-004 | Dead Code | app/Database/Migrations/2025-07-13-120400_CreateBlockedTimesTable.php; app/Database/Migrations/2025-08-24-120100_CreateBlockedTimesTable.php | Later blocked-times migration is an explicit duplicate no-op migration. | Medium: migration history becomes less trustworthy and harder to reason about. | Low |
| DEBT-005 | Orphaned Code | app/Database/Migrations/2025-10-22-174832_CreateReceptionistProvidersTable.php; app/Database/Migrations/2025-10-22-183826_ConvertReceptionistsToStaff.php | Historical receptionist schema path remains embedded after the domain moved to staff. | Medium: obsolete domain language and schema history remain in active maintenance context. | Medium |
| DEBT-006 | Dead Code | app/Commands/SendAppointmentReminders.php; app/Commands/SendAppointmentSmsReminders.php; app/Commands/SendAppointmentWhatsAppReminders.php; app/Commands/DispatchNotificationQueue.php | Three commands are explicit legacy aliases of the canonical notification queue command. NEEDS HUMAN VERIFICATION. | Low: redundant CLI surface and operational ambiguity. | Low |
| DEBT-007 | Dead Code | app/Commands/TestCustomerSearch.php; app/Commands/TestEncryption.php | Diagnostic commands remain in the main command namespace; TestEncryption performs persistence-side effects and contains duplicated logic. NEEDS HUMAN VERIFICATION. | Medium: support/debug commands can be misused and should not linger in production command surfaces. | Low |
| DEBT-008 | Structural & Architectural Debt | app/Controllers/Api/V1/BaseApiController.php; app/Controllers/Api/V1/Providers.php; app/Controllers/Api/V1/Services.php; app/Controllers/Api/V1/Settings.php | Deprecated V1 base controller still anchors V1 controllers through an extra inheritance shim. | Low: unnecessary compatibility layer that the codebase already considers obsolete. | Low |
| DEBT-009 | Duplicate & Repeated Code | app/Helpers/app_helper.php; app/Helpers/settings_helper.php | settings_helper duplicates core helper behavior already present in app_helper and appears unused outside its own commentary. | High: two authoritative copies of shared helper logic invite silent drift. | Low |
| DEBT-010 | Duplicate & Repeated Code | app/Helpers/app_helper.php; app/Helpers/user_helper.php; app/Helpers/permissions_helper.php | Role display and permission-description helpers are duplicated with inconsistent labels and semantics. | Medium: inconsistent UI wording and duplicated domain mapping logic. | Low |
| DEBT-011 | Duplicate & Repeated Code | resources/js/currency.js; resources/js/public-booking.js; resources/js/modules/analytics/analytics-charts.js; resources/js/modules/appointments/time-slots-ui.js | Currency formatting exists centrally but is reimplemented in several frontend modules. | Medium: formatting divergence risk across booking, analytics, and admin UI. | Low |
| DEBT-012 | Duplicate & Repeated Code | app/Controllers/Appointments.php; app/Controllers/Api/Appointments.php | Appointment time normalization and UTC/app-local conversion logic is duplicated across web and API controllers. | High: date-time bugs in booking/update flows are expensive and hard to detect. | Medium |
| DEBT-013 | Duplicate & Repeated Code | app/Services/NotificationEmailService.php; app/Services/NotificationSmsService.php; app/Services/NotificationWhatsAppService.php | Notification services duplicate integration lookup, encrypt/decrypt routines, health updates, and phone validation behavior. | High: security-sensitive behavior can drift between channels. | Medium |
| DEBT-014 | Inline Code That Should Be Abstracted | app/Views/settings/index.php | Settings view embeds full client-side block-period workflow logic directly in the template. | Medium: difficult to test, refactor, and maintain. | Medium |
| DEBT-015 | Inline Code That Should Be Abstracted | app/Views/settings/index.php | Settings view also embeds a database-backup client workflow directly in the template. | Medium: compounds complexity in an already oversized settings page. | Medium |
| DEBT-016 | Inline Code That Should Be Abstracted | app/Views/customer-management/index.php | Customer-management view contains embedded client-side search rendering, debounce logic, and date formatting. | Low: localized debt, but repeats the inline-script anti-pattern. | Low |
| DEBT-017 | Inline Code That Should Be Abstracted | app/Controllers/Search.php | Search controller directly composes join-heavy search queries instead of delegating to a service/query layer. | Medium: HTTP and query/business logic are mixed. | Medium |
| DEBT-018 | Structural & Architectural Debt | app/Controllers/Api/BaseApiController.php; app/Controllers/Api/Appointments.php; app/Controllers/Api/Availability.php; app/Controllers/Api/CustomerAppointments.php; app/Controllers/Api/Locations.php; app/Controllers/ServiceCategories.php; app/Controllers/ProviderSchedule.php | API and JSON-producing endpoints do not consistently use shared response helpers; contracts vary widely. | High: inconsistent response contracts make clients brittle and maintenance slower. | Medium |
| DEBT-019 | Inline Code That Should Be Abstracted | app/Config/Constants.php; app/Models/AppointmentModel.php; app/Controllers/Appointments.php; app/Controllers/Api/Appointments.php; app/Services/DashboardService.php; app/Controllers/Api/CustomerAppointments.php | Appointment status values and labels are scattered across controllers, services, models, and views despite existing constants. | High: status drift risks bugs in validation, analytics, queueing, and UI filters. | Medium |
| DEBT-020 | Structural & Architectural Debt | app/Controllers/UserManagement.php | 1391-line god controller spans user CRUD, schedules, assignments, permission logic, and delete impact behavior. | High: high blast radius for change and high regression risk. | High |
| DEBT-021 | Structural & Architectural Debt | app/Controllers/Api/Appointments.php | 1234-line god API controller mixes transport concerns, validation, notification triggering, formatting, and date normalization. | High: central critical API surface is too large and fragile. | High |
| DEBT-022 | Structural & Architectural Debt | app/Controllers/Settings.php; app/Views/settings/index.php | Settings stack is monolithic on both server and client sides. | High: expensive to change and likely to regress unrelated tabs. | High |
| DEBT-023 | Structural & Architectural Debt | app/Controllers/Dashboard.php | Dashboard controller still owns substantial session, authorization, fallback, and shaping logic despite existing services. | Medium: harder to test and evolve than necessary. | Medium |
| DEBT-024 | Dependency Debt | composer.json | mikey179/vfsstream appears unused in the repository. | Low: extra install weight and dependency noise. | Low |
| DEBT-025 | Dependency Debt | package.json; tailwind.config.js; app/Views/components/setup-layout.php | @coreui/coreui and @headlessui/tailwindcss appear unused. material-icons likely appears unused but NEEDS HUMAN VERIFICATION. | Medium: unused frontend dependencies enlarge build/install surface and obscure true stack requirements. | Low |
| DEBT-026 | Test Debt | tests/database/ExampleDatabaseTest.php; tests/session/ExampleSessionTest.php; tests/_support/Models/ExampleModel.php; tests/_support/Database/Seeds/ExampleSeeder.php | Framework scaffold example tests and fixtures remain in the suite and inflate perceived coverage. | High: false confidence and noisy coverage surface. | Low |
| DEBT-027 | Test Debt | tests/unit/HealthTest.php | HealthTest validates framework/environment configuration, not core business behavior. | Low: little confidence value for product correctness. | Low |
| DEBT-028 | Test Debt | app/Services/NotificationQueueDispatcher.php; app/Services/AppointmentBookingService.php; app/Services/DashboardService.php; app/Services/AuthorizationService.php; tests/unit/Services/BusinessHoursServiceTest.php; tests/unit/Services/AppointmentConflictServiceTest.php | Service layer is badly under-tested relative to its size and responsibility. | High: systemic regression risk multiplier across the application. | High |
| DEBT-029 | Test Debt | app/Controllers/Appointments.php; app/Controllers/Api/Appointments.php; app/Controllers/UserManagement.php; app/Services/PublicBookingService.php; app/Services/NotificationQueueDispatcher.php; tests/unit/PublicBookingServiceTest.php | Core user journeys remain untested or only thinly tested. | High: the most valuable product flows lack adequate regression protection. | High |
| DEBT-030 | Structural & Architectural Debt | app/Services/NotificationPhase1.php; app/Controllers/Settings.php; app/Services/AppointmentNotificationService.php; app/Services/NotificationQueueDispatcher.php; app/Config/Events.php | NotificationPhase1 has become a cross-cutting transitional abstraction carrying runtime-wide notification defaults and policy. NEEDS HUMAN VERIFICATION. | High: future notification evolution is constrained by a phase-scoped abstraction leaking into core runtime. | High |
| DEBT-031 | Structural & Architectural Debt | app/Controllers/Search.php; docs/architecture/mastercontext.md; app/Controllers/AppFlow.php | Documentation and comments are out of sync with the running system, including root-controller and frontend-path references. | Low: slows maintenance and increases onboarding mistakes. | Low |

---

## Phase 3 — Sequential Remediation Plan

Order rule used:

1. High impact + low effort
2. High impact + high effort
3. Low impact items last

### Quick Wins

1. Fix DEBT-024, DEBT-026, and DEBT-027 first.
   Exact fix: remove the unused vfsstream dependency, delete the example scaffold tests and their example support fixtures, and either delete or explicitly reclassify HealthTest as environment smoke coverage.
   Why first: this immediately makes the quality signal from the test suite more honest with low implementation risk.

2. Fix DEBT-009 and DEBT-010 next.
   Exact fix: consolidate settings and role helper behavior into one canonical helper location, update all callers, and remove or reduce duplicate helper files to compatibility wrappers only if required.
   Dependency: do this before any user-management UI cleanup so role labeling and helper behavior are stabilized.

3. Fix DEBT-001 and DEBT-002.
   Exact fix: remove the dead Home controller and either delete the unreachable Styleguide feature or reintroduce explicit routes if the feature is intentionally kept for development use.
   Human decision required: confirm whether the styleguide should exist as a live development route or as archived documentation.

4. Fix DEBT-006 and DEBT-007.
   Exact fix: remove legacy alias commands and diagnostic commands that are no longer operationally required.
   Dependency: confirm cron usage and support workflows before deletion.
   Human decision required: verify no deployment or support process still depends on the legacy aliases or diagnostic commands.

5. Fix DEBT-025.
   Exact fix: remove @coreui/coreui and @headlessui/tailwindcss after verifying the production build still passes; evaluate material-icons separately and remove it only if a human confirms it is not part of the runtime icon strategy.
   Dependency: run build verification before and after dependency removal.

6. Fix DEBT-031.
   Exact fix: update comments and docs to match the live root controller, active search frontend path, and current styleguide/Home state.
   Dependency: do this after DEBT-001 and DEBT-002 so documentation reflects the chosen final state.

### High-Impact Structural Work

7. Fix DEBT-018 before splitting any large controller.
   Exact fix: standardize all API and JSON response contracts behind shared response helpers and remove hand-rolled setJSON status chains where shared helpers should apply.
   Dependency: complete this before DEBT-021 and before refactoring ProviderSchedule or ServiceCategories, or inconsistent contracts will be preserved in smaller pieces.

8. Fix DEBT-019.
   Exact fix: choose one authoritative appointment status definition source and route validation, analytics, queueing, API filters, and UI labels through it.
   Dependency: do this before DEBT-021, DEBT-023, and notification-related refactors since those layers branch on status today.

9. Fix DEBT-012.
   Exact fix: extract one canonical appointment date-time normalization service or utility and route both Appointments controllers through it.
   Dependency: do this before controller decomposition so duplication is removed rather than relocated.

10. Fix DEBT-013.
    Exact fix: extract shared notification integration persistence, encrypt/decrypt, health update, and phone validation behavior into a common notification base service or trait.
    Dependency: complete this before DEBT-030 so NotificationPhase1 decisions happen on a normalized service foundation.

11. Fix DEBT-030.
    Exact fix: decide whether NotificationPhase1 is a temporary compatibility layer to retire or a stable notification policy component to rename and formalize.
    Dependency: do this after DEBT-013.
    Human decision required: confirm long-term notification architecture and business-context strategy.

12. Fix DEBT-020.
    Exact fix: split UserManagement into focused controller/service responsibilities such as user CRUD, provider schedule handling, assignment management, and delete-impact logic.
    Dependency: complete DEBT-018, DEBT-019, and DEBT-012 first.

13. Fix DEBT-021.
    Exact fix: split Api/Appointments into thinner transport endpoints backed by dedicated services for validation, normalization, formatting, status transitions, and notification side effects.
    Dependency: complete DEBT-018, DEBT-019, and DEBT-012 first.

14. Fix DEBT-022.
    Exact fix: break Settings controller behavior into dedicated services/modules by tab or capability, and remove embedded page-application logic from the monolithic settings view.
    Dependency: complete DEBT-014 and DEBT-015 as part of this effort.

15. Fix DEBT-023.
    Exact fix: push remaining authorization/session/fallback shaping logic from Dashboard into services or a thinner orchestration layer.
    Dependency: do this after DEBT-019 if dashboard metrics and statuses will be touched in the process.

### Mid-Tier Cleanup

16. Fix DEBT-014 and DEBT-015.
    Exact fix: extract the block-period and database-backup client workflows from the settings view into dedicated frontend modules.
    Dependency: these can be implemented as a precursor or as a sub-step of DEBT-022.

17. Fix DEBT-017.
    Exact fix: move Search query composition into a service or query object and leave the Search controller as a thin HTTP boundary.
    Dependency: easiest after response handling patterns are standardized.

18. Fix DEBT-011.
    Exact fix: replace local frontend currency formatter copies with one shared import path and one fallback policy.
    Dependency: do this after API contract cleanup if payload shape or localization metadata delivery changes.

19. Fix DEBT-005.
    Exact fix: document and quarantine the receptionist-era migration path and any residual compatibility assumptions.
    Dependency: do not rewrite historical migrations casually if deployed environments already rely on them.
    Human decision required: confirm whether any compatibility references must be retained permanently.

20. Fix DEBT-004.
    Exact fix: retain clear documentation around the duplicate no-op blocked-times migration or archive its intent in migration notes if the team keeps full migration history intact.
    Dependency: do not alter already-run migration history without a rollout strategy.

### Low-Impact Final Cleanup

21. Fix DEBT-003.
    Exact fix: either delete clear-session.php or move it behind a controlled, documented support-only path.
    Human decision required: confirm whether support still needs this tool.

22. Fix DEBT-008.
    Exact fix: simplify or remove the deprecated V1 base controller shim if versioned API support can point directly to the shared base controller.
    Dependency: only do this once backward compatibility requirements are confirmed.

23. Fix DEBT-016.
    Exact fix: move customer-management inline search/render logic into a dedicated JS module.
    Dependency: independent, but lowest urgency compared with the settings extraction.

### Testing Expansion After Refactors Stabilize

24. Fix DEBT-028 and DEBT-029 last, once the high-churn refactors above settle.
    Exact execution order:
    - Add notification queue and channel-service tests
    - Add appointment API and web appointment flow tests
    - Add user-management tests
    - Add public-booking edge-case tests
    - Add dashboard authorization and aggregation tests
    Dependency: wait until DEBT-018, DEBT-019, and DEBT-012 are complete so tests lock the stabilized contracts rather than churn immediately.

---

## Human Verification Checklist

The following items require explicit human confirmation before action:

- DEBT-003: whether public clear-session.php is still an intentional support tool
- DEBT-006: whether legacy reminder aliases are still used by cron or deployment scripts
- DEBT-007: whether diagnostic commands should be retained in production command surfaces
- DEBT-025: whether material-icons is truly unused in the deployed asset strategy
- DEBT-030: whether NotificationPhase1 is transitional or intended as long-term architecture

---

## Recommended First Execution Batch

If another agent starts acting on this plan, the safest first implementation batch is:

1. DEBT-024
2. DEBT-026
3. DEBT-027
4. DEBT-009
5. DEBT-010

That batch gives the highest cleanup value with the lowest risk of breaking product behavior.

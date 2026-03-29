# Refactor Target Decision Record

**Last Updated:** March 25, 2026  
**Status:** Active

---

## Purpose

This document records the target architecture for the next refactor cycle after the March 2026 code audit.

It exists to keep cleanup work aligned with the repository contract in `Agent_Context.md`, especially for areas where drift is still visible:

- settings and business-hours access
- notifications read and formatting flows
- controller/service dependency construction
- SPA lifecycle code embedded in views
- test coverage around critical boundaries

This is a decision record, not a backlog dump. It defines the desired steady-state architecture that future changes should move toward.

---

## Context

The current codebase is materially better than the earlier controller-heavy baseline, but the audit confirmed several recurring sources of regressions:

- settings behavior is split between controllers, services, helpers, and direct SQL
- some controllers still aggregate domain data and format view models inline
- controllers and services still construct dependencies ad hoc with `new Model()` and `new Service()`
- large view-local scripts still own SPA lifecycle and form behavior
- a few extracted boundaries exist without real adoption
- tests are stronger than before, but the highest-risk seams still lack focused coverage

These problems directly contributed to recent production bugs involving subfolder routing, SPA initialization order, appointment flows, and user-management form behavior.

---

## Current Status

As of March 25, 2026, this decision record is still active, but several of the highest-risk targets have already moved materially toward the desired state.

Implemented or materially advanced:

- settings API boundary consolidation is in place through `app/Services/Settings/SettingsApiService.php`, with `app/Controllers/Api/V1/Settings.php` now delegating settings reads, localization payloads, booking payloads, business-hours shaping, and file-upload response handling through service seams
- notifications page aggregation and formatting moved into `app/Services/NotificationCenterService.php`, with `app/Controllers/Notifications.php` reduced to request handling and view rendering
- appointments web form flows were split into dedicated seams:
	- `app/Services/Appointment/AppointmentFormContextService.php`
	- `app/Services/Appointment/AppointmentFormSubmissionService.php`
	- `app/Services/Appointment/AppointmentFormMutationService.php`
	- `app/Services/Appointment/AppointmentFormGuardService.php`
	- `app/Services/Appointment/AppointmentFormResponseService.php`
- dashboard dependency construction improved through explicit constructor seams in `app/Controllers/Dashboard.php`, `app/Services/DashboardPageService.php`, and `app/Services/DashboardService.php`
- the previously orphaned `app/Services/AppointmentDashboardContextService.php` seam is now adopted by dashboard services instead of remaining dead refactor residue
- focused regression coverage expanded around the new seams, especially for settings, notifications, dashboard context, and appointment create/update web journeys

Still incomplete:

- controller and service dependency construction is improved but not yet consistently applied across all high-churn surfaces, especially in user management and remaining legacy controllers
- appointment and user-management views still contain significant inline SPA behavior even though the highest-risk appointment time-slot initialization path has been stabilized
- the next meaningful frontend cleanup is still extracting remaining behavior from `app/Views/appointments/form.php` and `app/Views/user-management/components/provider-schedule.php` into reusable modules
- test coverage is stronger at the boundary level, but SPA lifecycle contracts and some role-specific web flows still need direct regression coverage

Practical status by implementation order:

1. Settings boundary consolidation: materially complete for the API/controller path
2. Notifications read-model extraction: materially complete for the page controller path
3. Dependency seam cleanup in high-churn controllers and services: in progress
4. View-script extraction into frontend modules: in progress
5. Orphaned-boundary cleanup: in progress, with dashboard appointment scope now adopted
6. Focused test expansion around the new seams: in progress and improving

### Completion Checklist

Implementation-order tracking:

- [x] Settings boundary consolidation completed for the current API/controller path
- [x] Notifications read-model extraction completed for the page controller path
- [ ] Dependency seam cleanup completed across all high-churn controllers and services
- [ ] View-script extraction completed for the targeted appointment and user-management screens
- [ ] Orphaned-boundary cleanup completed across the remaining extracted seams
- [ ] Focused test expansion completed for all named high-risk flows

Current branch completions:

- [x] `app/Controllers/Api/V1/Settings.php` delegates settings reads and shaping to `app/Services/Settings/SettingsApiService.php`
- [x] business-hours payload shaping moved out of the settings API controller
- [x] upload response shaping for settings file endpoints moved behind service boundaries
- [x] `app/Controllers/Notifications.php` delegates notification page aggregation and formatting to `app/Services/NotificationCenterService.php`
- [x] `app/Controllers/Appointments.php` create/edit form payload assembly moved behind `AppointmentFormContextService`
- [x] `app/Controllers/Appointments.php` request-to-domain payload mapping moved behind `AppointmentFormSubmissionService`
- [x] `app/Controllers/Appointments.php` create/update mutation orchestration moved behind `AppointmentFormMutationService`
- [x] `app/Controllers/Appointments.php` auth/hash/past-date guards moved behind `AppointmentFormGuardService`
- [x] `app/Controllers/Appointments.php` mutation result to HTTP response translation moved behind `AppointmentFormResponseService`
- [x] dashboard appointment-scope seam is now adopted instead of remaining orphaned
- [x] `app/Controllers/UserManagement.php` and the user-management boundary services now accept explicit optional dependencies instead of constructing the full object graph ad hoc in the controller path
- [x] `app/Controllers/CustomerManagement.php` now accepts explicit optional dependencies instead of constructing its customer and booking services unconditionally in the controller constructor
- [x] `app/Controllers/Settings.php` now accepts explicit optional dependencies instead of lazily constructing all settings services as fixed controller-owned collaborators
- [x] `app/Controllers/Search.php` now accepts an explicit optional `GlobalSearchService` dependency instead of constructing it unconditionally in the controller constructor
- [x] `app/Controllers/Services.php` now accepts explicit optional model dependencies instead of constructing user, service, and category models unconditionally in the controller constructor
- [x] provider-schedule page behavior is no longer owned inline in `app/Views/user-management/components/provider-schedule.php`; it now initializes from the frontend module bundle
- [x] appointment form page behavior is no longer owned inline in `app/Views/appointments/form.php`; the view now hands configuration to the bundled initializer through DOM data attributes
- [x] focused unit and integration regression coverage exists for settings, notifications, dashboard context, appointment form flows, customer-management CRUD/search/history journeys, and service CRUD/provider-assignment journeys

Remaining work before this record can be considered fully implemented:

- [ ] remove the remaining ad hoc dependency construction from user-management and other legacy high-churn surfaces
- [x] finish extracting appointment form SPA behavior out of `app/Views/appointments/form.php`
- [x] finish extracting provider-schedule behavior out of `app/Views/user-management/components/provider-schedule.php`
- [x] add direct SPA lifecycle regression coverage for appointment and user-management screen initialization
- [x] add focused regression coverage for remaining role-specific user-management flows

---

## Decision

### 1. Controllers remain thin HTTP boundaries

Controllers should only:

- read request input
- invoke a domain or page service
- translate the result into HTML or JSON responses
- apply authorization and transport-level concerns

Controllers should not:

- build domain read models inline
- format notification or settings payloads inline
- query tables directly when a service boundary already exists or should exist
- own business fallback rules

This rule applies first to:

- `app/Controllers/Api/V1/Settings.php`
- `app/Controllers/Notifications.php`
- `app/Controllers/Appointments.php`
- `app/Controllers/UserManagement.php`
- `app/Controllers/Dashboard.php`

### 2. Settings become a single domain boundary

Settings reads and writes must converge behind dedicated services rather than being split across helpers, controllers, and direct database queries.

Target state:

- one settings-facing service layer assembles settings payloads for API and page use
- business-hours shaping for frontend consumers lives in a service, not in a controller
- helpers remain compatibility wrappers only, not primary business logic entry points
- controllers do not query `xs_settings` or `xs_business_hours` directly for application behavior

### 3. Notifications use dedicated read-model and dispatch boundaries

The notifications page must consume a dedicated service that returns normalized notification view models.

Target state:

- queue and delivery-log aggregation happens in a service
- message composition and time formatting happen in a service
- the notifications controller only handles request filtering and response rendering
- channel dispatch continues to live in explicit notification services and dispatcher classes

### 4. Dependency construction moves to explicit seams

New or refactored code should prefer constructor injection with backward-compatible defaults where necessary for CodeIgniter ergonomics.

Target state:

- controllers and services receive dependencies explicitly
- constructor signatures stay stable or add optional parameters at the end
- ad hoc `new Model()` and `new Service()` calls are reduced in high-churn code first
- tests can replace dependencies without subclass hacks or fragile constructor ordering

Priority surfaces:

- appointments
- user management
- settings
- notifications
- dashboard
- notification dispatcher and settings notification services

### 5. Views render markup; frontend modules own behavior

Large inline scripts embedded in PHP views are not the target architecture.

Target state:

- views emit markup and small configuration payloads only
- SPA initialization lives in `resources/js` modules
- page-specific modules register through stable initialization hooks
- timing-sensitive behavior is tested and reusable outside a single template

This rule applies first to:

- `app/Views/appointments/form.php`
- `app/Views/user-management/components/provider-schedule.php`
- similar view-local scripts that manage state, retries, fetches, or form orchestration

### 6. Extracted boundaries must be either adopted or removed

If a service exists as an architectural seam, it must have real call sites and tests. Otherwise it should be removed.

Current example:

- `app/Services/AppointmentDashboardContextService.php` should either become the real dashboard appointment-scope boundary or be deleted as refactor residue

### 7. Tests follow risk, not file count

The next test expansion should target business-critical seams rather than broad low-value scaffolding.

Priority test targets:

- settings API/service contract
- notifications read-model and queue/display formatting
- appointment create/update conflict and timezone boundaries
- user-management create/update role-specific flows
- SPA initialization contracts for appointment and user-management screens

Low-value example and framework sample tests should not be treated as meaningful business coverage.

---

## Non-Goals

This decision record does not require:

- introducing a full IoC container or framework-wide dependency injection rewrite in one pass
- rewriting all legacy controllers before targeted fixes can ship
- replacing CodeIgniter idioms that are not causing architectural friction
- large frontend redesign work unrelated to boundary cleanup

---

## Implementation Order

Refactor work should follow this order unless a production bug forces reprioritization:

1. Settings boundary consolidation
2. Notifications read-model extraction
3. Dependency seam cleanup in high-churn controllers and services
4. View-script extraction into frontend modules
5. Orphaned-boundary cleanup
6. Focused test expansion around the new seams

---

## Consequences

Expected benefits:

- fewer production-only regressions caused by hidden fallback logic
- lower risk when adding constructor dependencies
- less duplication between page and API settings behavior
- more stable SPA initialization on internal screens
- clearer test seams around the most failure-prone workflows

Expected tradeoffs:

- short-term churn in service constructors and controller wiring
- temporary duplication while old and new boundaries coexist during migration
- a need to keep backward-compatible call patterns during incremental rollout

---

## Success Criteria

This decision is considered implemented when:

- [x] settings API responses are assembled through service boundaries only
- [x] notifications page aggregation and formatting no longer live in the controller
- [ ] major controllers no longer construct most dependencies ad hoc
- [ ] appointment and user-management view scripts are reduced to markup plus config handoff
- [x] orphaned architectural seams are either adopted or deleted for the currently targeted dashboard appointment-scope boundary
- [ ] targeted regression tests exist for all of the highest-risk flows named above

---

## Related Documents

- `Agent_Context.md`
- `docs/audits/remediation_plan.md`
- `docs/architecture/provider_service_catalog_contract.md`
- `docs/architecture/scheduler_ui_architecture.md`

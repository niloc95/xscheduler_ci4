# WebScheduler CI4 — Documentation Index

**Last Updated:** 2026-05-09 (second pass — 112 → 97 active files; removed orphaned + historical)

Single-source navigation for all active documentation. Historical records, fix logs, phase summaries, and one-off audit reports have been moved to [`_archive/`](_archive/).

---

## Documentation Health

| Metric | Count |
|---|---|
| Active docs | 97 |
| Archived (historical) | ~127 |
| Architecture docs current against code | ✅ 7 |

---

## Navigation by Domain

### Architecture and System Contracts

All 7 docs in this folder are current and have been audited against the codebase.

- [App Entry and Root Routing](architecture/APP_ENTRY_AND_ROUTING.md) — `AppFlow` controller, root redirect logic
- [Role-Based System](architecture/ROLE_BASED_SYSTEM.md) — RBAC, dual-role model, session contract, filter chain
- [User vs Customer Split](architecture/USER_CUSTOMER_SPLIT.md) — `xs_users` vs `xs_customers`, hash contract, custom fields
- [Provider Service Catalog Contract](architecture/provider_service_catalog_contract.md) — `xs_providers_services` pivot, booking-facing queries
- [Calendar Engine API Reference](architecture/CALENDAR_ENGINE_API_REFERENCE.md) — all Calendar service method signatures and response shapes
- [Calendar UI Architecture](architecture/CALENDAR_UI_ARCHITECTURE.md) — shell layout, color system, view layout descriptions
- [Scheduler UI Architecture](architecture/scheduler_ui_architecture.md) — all 31 JS modules in `resources/js/modules/scheduler/`

Also see `Agent_Context_v2.md` (project root) — the canonical engineering contract for architecture, API, scheduling, notifications, and database rules.

---

### Setup and Configuration

- [Requirements](REQUIREMENTS.md) — tech stack, user roles, system requirements
- [Environment Configuration](configuration/ENV-CONFIGURATION-GUIDE.md)
- [Setup-Driven Env Config](configuration/SETUP-DRIVEN-ENV-CONFIG.md)
- [Settings Architecture](configuration/settings-architecture.md) — tab-based settings system
- [Settings Contact Fields](configuration/SETTINGS_CONTACT_FIELDS.md)

---

### Scheduling and Calendar

- [Scheduling System](features/SCHEDULING_SYSTEM.md) — AvailabilityService, booking pipeline
- [Calendar Engine API Reference](architecture/CALENDAR_ENGINE_API_REFERENCE.md)
- [Calendar Engine Quick Start](scheduler/calendar_engine_quick_start.md)
- [Conflict Service](scheduler/CONFLICT_SERVICE.md) — overlap detection
- [Day View Architecture](scheduler/DAY_VIEW_ARCHITECTURE.md)
- [Appointment Edit Architecture](scheduler/APPOINTMENT_EDIT_ARCHITECTURE.md)
- [Appointment Color System](scheduler/APPOINTMENT_COLOR_SYSTEM.md)
- [Availability Rendering Logic](scheduler/availability-rendering-logic.md)
- [Day/Week View Quick Reference](scheduler/day-week-view-quickref.md)
- [Day/Week View Troubleshooting](scheduler/DAY-WEEK-VIEW-TROUBLESHOOTING.md)
- [Overlapping Appointments Quick Reference](scheduler/overlapping-appointments-quickref.md)
- [Overlapping Appointments Troubleshooting](scheduler/overlapping-appointments-troubleshooting.md)

---

### Frontend and Design System

- [Core JS Modules](frontend/core-js-modules.md) — `core/api.js`, `core/csrf.js`, `core/datetime.js`, `core/lifecycle.js`
- [Avatar System](features/AVATAR_SYSTEM.md) — PHP helpers + JS utilities
- [Material 3 Design System](design/material-3-design-system.md)
- [Design System](design/DESIGN_SYSTEM.md)
- [Material Icons Usage](design/MATERIAL_ICONS_USAGE.md)
- [Global Layout System](development/GLOBAL_LAYOUT_SYSTEM.md)
- [Dark Mode Implementation](dark-mode/DARK_MODE_IMPLEMENTATION.md)
- [Calendar Settings Sync](frontend/CALENDAR_SETTINGS_SYNC_IMPLEMENTATION.md)
- [Calendar Integration](frontend/calendar-integration.md)
- [Calendar View Controls](frontend/calendar-view-controls.md)
- [Calendar Time Format Fix](frontend/CALENDAR_TIME_FORMAT_FIX_SUMMARY.md)
- [Calendar Time Format Troubleshooting](frontend/CALENDAR_TIME_FORMAT_TROUBLESHOOTING.md)

---

### Backend, API, and Data

- [OpenAPI Spec](technical/openapi.yml) — REST API endpoint definitions
- [Spark Commands Reference](technical/spark-commands.md) — all 9 `php spark` commands
- [CSS Consolidation Guide](technical/CSS_CONSOLIDATION_GUIDE.md)
- [Production Debug](technical/PRODUCTION-DEBUG.md)
- [DB Backup Plan](database/DB_BACKUP_PLAN.md)
- [DB Prefix Best Practices](database/DB_PREFIX_BEST_PRACTICES.md)

---

### Notifications

- [Notifications Implementation Checklist](features/NOTIFICATIONS_IMPLEMENTATION_CHECKLIST.md)
- [Dashboard Service Layer](features/DASHBOARD_SERVICE_LAYER.md)

---

### Features and Domain Modules

- [Customer Appointment History](features/CUSTOMER_APPOINTMENT_HISTORY.md)
- [Global Header System](features/GLOBAL_HEADER_SYSTEM.md)
- [Locations Feature](features/LOCATIONS_FEATURE.md)

---

### Security

- [Security Policy](security/security_policy.md)
- [Security Implementation Guide](security/SECURITY_IMPLEMENTATION_GUIDE.md)
- [Hash-Based URL Implementation](security/HASH_BASED_URL_IMPLEMENTATION.md)
- [Encryption Status Assessment](security/ENCRYPTION-STATUS-ASSESSMENT.md)

---

### Development Reference

- [Architecture Overview](development/architecture.md)
- [Naming Conventions](development/naming-conventions.md)
- [File Naming Convention](development/file-naming-convention.md)
- [File Header Template](development/FILE_HEADER_TEMPLATE.md)
- [Migration Example](development/MIGRATION_EXAMPLE.md)
- [Provider System Guide](development/provider_system_guide.md)
- [Provider Color System](development/provider-color-system.md)
- [Provider Service Binding](development/provider-service-binding.md)
- [Provider UX](development/provider-ux.md)
- [Staff Assignment](development/staff-assignment.md)
- [Unified Staff Assignment](development/unified-staff-assignment.md)
- [Dynamic Customer Fields](development/dynamic-customer-fields.md)
- [Dashboard Landing View](development/DASHBOARD_LANDING_VIEW_IMPLEMENTATION.md)
- [Setup Workflow](development/setup-workflow.md)
- [GitHub Actions Workflows](development/github_actions_workflows.md)
- [GitHub Repository Setup](development/github_repository_setup_guide.md)
- [Sample Data](development/SAMPLE_DATA.md)
- [Quick Reference](development/QUICK_REFERENCE.md)
- [Timezone Troubleshooting](development/TROUBLESHOOTING-timezone-issues.md)

---

### Testing

- [Test Runner Guide](testing/test_runner_guide.md)
- [Testing Appointment Updates](testing/TESTING_APPOINTMENT_UPDATES.md)
- [Calendar Debug Instructions](testing/CALENDAR_DEBUG_INSTRUCTIONS.md)
- [Calendar Settings Sync Checklist](testing/CALENDAR_SETTINGS_SYNC_CHECKLIST.md)
- [Calendar Settings Sync Test](testing/calendar-settings-sync-test.md)
- [Calendar Time Format Test Script](testing/calendar-time-format-test-script.md)
- [Overlapping Appointments Visual Guide](testing/overlapping-appointments-visual-guide.md)

---

### Deployment and Release

- [Releasing](deployment/RELEASING.md)
- [Packaging and Release Guide](deployment/PACKAGING_AND_RELEASE_GUIDE.md)
- [Quick Release Guide](deployment/QUICK_RELEASE_GUIDE.md)
- [Quick Deploy](deployment/quick_deploy.md)
- [Deploy Bundle Readme](deployment/deploy_bundle_readme.md)
- [Production Fix Guide](deployment/PRODUCTION_FIX_GUIDE.md)
- [Production URL Auto-Detection](deployment/PRODUCTION-URL-AUTO-DETECTION.md)
- [MySQL Test Connection Fix](deployment/MYSQL-TEST-CONNECTION-FIX.md)
- [Public Booking](deployment/PUBLIC_BOOKING.md)
- [AWS Lightsail Reference](aws-lightsail/aws-lightsail-reference.md)

---

### Contributing

- [Contributing Guide](contributing.md)
- [Changelog](changelog.md)
- [Development Readme](development/README.md)

---

## Archive

All historical records, fix logs, phase summaries, investigation reports, and audit records are in [`_archive/`](_archive/).

Subfolders:
- `_archive/audits/` — investigation and audit reports
- `_archive/fixes/` — resolved bug fix records
- `_archive/development/` — phase summaries, timezone fix reports, calendar rebuild records
- `_archive/scheduler/` — scheduler phase records
- `_archive/technical/` — technical audit records
- `_archive/misc/` — scattered historical records
- `_archive/old-architecture/` — pre-cleanup architecture docs
- (older pre-existing subfolders)

# WebScheduler CI4 Documentation Index

Last Updated: 2026-04-22

Purpose: Single source-of-truth navigation for repository documentation. This index is based on a full audit of `docs/` and a cross-check against current code and architecture contracts in [architecture/mastercontext.md](architecture/mastercontext.md), [../Agent_Context_v2.md](../Agent_Context_v2.md), and [old_Archived.md](old_Archived.md).

## Documentation Health Snapshot

- Total documentation files audited: ~220+
- ACTIVE: ~85
- OUTDATED: ~50
- DEPRECATED: ~40
- DUPLICATE: ~45
- Root-level clutter candidates: largely addressed; non-entry docs are being normalized into domain folders.

## Core Navigation By Domain

### Setup And Configuration
Status: ✅ Valid

- [Requirements](REQUIREMENTS.md)
- [Environment Configuration](configuration/ENV-CONFIGURATION-GUIDE.md)
- [Configuration Folder](configuration/)

Notes:
- Setup flow is well documented.
- Historical setup completion notes belong in archive, not root.

### Architecture And System Contracts
Status: ✅ Valid

- [Master Context](architecture/mastercontext.md)
- [Architecture Folder](architecture/)
- [User vs Customer Split](architecture/USER_CUSTOMER_SPLIT.md)
- [Unified Calendar Engine](architecture/UNIFIED_CALENDAR_ENGINE.md)
- [Provider Service Catalog Contract](architecture/provider_service_catalog_contract.md)

Notes:
- Core architecture contracts align with the current CI4 service-oriented structure.
- Legacy scheduler architecture docs should be archived.

### Scheduling And Calendar
Status: ⚠️ Needs Update

- [Scheduling System](features/SCHEDULING_SYSTEM.md)
- [Calendar Engine Quick Start](scheduler/calendar_engine_quick_start.md)
- [Calendar System Audit](audits/CALENDAR_SYSTEM_AUDIT.md)
- [Day/Week Quick Reference](scheduler/day-week-view-quickref.md)
- [Scheduler Folder](scheduler/)

Notes:
- Coverage is strong, but there are too many overlapping audit, quick reference, troubleshooting, and investigation files.
- Consolidate calendar-related troubleshooting and audits into fewer canonical docs.

### Frontend And Design System
Status: ⚠️ Needs Update

- [Material 3 Design System](design/material-3-design-system.md)
- [Global Layout System](development/GLOBAL_LAYOUT_SYSTEM.md)
- [Material Dashboard Guide](architecture/MATERIAL_DASHBOARD_GUIDE.md)
- [Design Folder](design/)
- [Frontend Folder](frontend/)

Notes:
- Documented direction is Tailwind + Material Design + SPA.
- Drift exists: competing UI patterns and partial `ui_helper` adoption.
- Vite documentation is incomplete relative to actual entry points in [../vite.config.js](../vite.config.js).

### Backend, API, And Data
Status: ⚠️ Needs Update

- [Technical Folder](technical/)
- [Database Folder](database/)
- [OpenAPI Spec](technical/openapi.yml)
- [Settings Architecture](configuration/settings-architecture.md)
- [Role Based System](architecture/ROLE_BASED_SYSTEM.md)

Notes:
- API and services are partially documented, but service catalog coverage is incomplete.
- Duplicate OpenAPI and system-contract files should be consolidated into one canonical location.

### Notifications
Status: ✅ Valid

- [Notifications Implementation Checklist](features/NOTIFICATIONS_IMPLEMENTATION_CHECKLIST.md)
- [Features Folder](features/)

Notes:
- Core queue and dispatch architecture is represented.
- A dedicated troubleshooting runbook for delivery incidents is still missing.

### Security And Compliance
Status: ✅ Valid

- [Security Policy](security/security_policy.md)
- [Security Implementation Guide](security/SECURITY_IMPLEMENTATION_GUIDE.md)
- [Security Folder](security/)
- [Compliance Folder](compliance/)
- [Access Control Matrix](user-management/ACCESS_CONTROL_MATRIX.md)

Notes:
- Security documentation is strong, but some topics still exist in both root and subfolders.

### Testing And QA
Status: ⚠️ Needs Update

- [Testing Folder](testing/)
- [Testing Appointment Updates](testing/TESTING_APPOINTMENT_UPDATES.md)

Notes:
- Good targeted test docs exist.
- A single integration/system testing guide that maps to current CI flow is still missing.

### Deployment And Release
Status: ✅ Valid

- [Releasing](deployment/RELEASING.md)
- [Packaging And Release Guide](deployment/PACKAGING_AND_RELEASE_GUIDE.md)
- [Deployment Folder](deployment/)
- [DB Backup Plan](database/DB_BACKUP_PLAN.md)

Notes:
- Deployment docs are robust but duplicated across root and `deployment/`.

## Major Drift And Duplication Hotspots

1. Calendar docs fragmentation
- Multiple files cover similar ground: quick references, troubleshooting, time-format fixes, sync fixes, and audits.
- Keep [scheduler/calendar_engine_quick_start.md](scheduler/calendar_engine_quick_start.md) as the practical entry point.

2. Duplicate root and subfolder docs
- Several root docs are exact or near duplicates of canonical subfolder docs.
- Keep a single canonical copy per topic.

3. Historical phase and audit docs mixed with active docs
- Completion reports and old audits still sit in active locations.
- Move them into archive with `OLD_` prefixes.

4. Design system fragmentation
- Material and Tailwind rules are documented, but implementation patterns are inconsistent.
- Align design docs to actual helper/component usage.

## Missing Documentation Gaps

- End-to-end integration/system testing playbook
- Notification troubleshooting runbook
- Service catalog index with ownership boundaries
- API versioning strategy note
- Database schema relationship quick reference

## Target Structure

Long-term target:
- `docs/setup`
- `docs/architecture`
- `docs/frontend`
- `docs/backend`
- `docs/deployment`
- `docs/integrations`
- `docs/archive`

Short-term practical mapping in the current repo:
- Use existing folders as canonical homes: `configuration/`, `architecture/`, `frontend/`, `technical/`, `deployment/`, `security/`, `testing/`, `features/`, `database/`, `compliance/`, `_archive/`.
- Continue using [`_archive/`](_archive/) as the canonical archive root for minimal churn.

## Prioritized Cleanup Plan

1. Consolidate duplicates first.
- Remove duplicate copies and keep one canonical document per topic.

2. Archive deprecated and outdated docs.
- Move deprecated docs into `_archive/` and prefix them with `OLD_`.

3. Normalize calendar documentation.
- Keep one architecture doc, one quick start, and one troubleshooting guide.

4. Add missing runbooks.
- Create testing and notification troubleshooting guides.

5. Re-run documentation audit monthly.
- Track drift against code changes and architecture contracts.

## Archive References

- [Legacy Archive Bucket](_archive/)
- [Legacy Archive Notes](old_Archived.md)

## Related Source-Of-Truth Files

- [Project Master Context](architecture/mastercontext.md)
- [Agent Context v2](../Agent_Context_v2.md)
- [Legacy Archived Notes](old_Archived.md)

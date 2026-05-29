# Changelog

All notable changes to xScheduler will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- No unreleased changes documented yet.

## [2.0.9] - 2026-05-29

### Fixed
- fix: include version.json in release commit so repo stays in sync

## [2.0.8] - 2026-05-29

### Fixed
- fix: bump version before building deployment package in release.js

## [2.0.7] - 2026-05-29

### Changed
- chore: sync version metadata

### Fixed
- fix: use archive.glob to eliminate ./version.json prefix on Linux CI

## [2.0.6] - 2026-05-29

### Changed
- chore: sync version metadata

### Fixed
- fix: version.json not found in Linux CI release ZIPs

## [2.0.5] - 2026-05-29

### Fixed
- fix: replace CI3 escapeStr() with CI4 escapeString() in UpdaterBackupService

## [2.0.4] - 2026-05-28

### Fixed
- fix: updater settings — wrong GitHub URL, release ZIP validation gate, actionable upload errors

## [2.0.3] - 2026-05-28

### Changed
- chore: sync version metadata

### Fixed
- fix: extract updater routes from empty-prefix group to eliminate CI4 routing ambiguity
- fix: updater action-failed — wrong error key + silent maintenance write failure

## [2.0.2] - 2026-05-28

### Changed
- chore: refresh version metadata

## [2.0.1] - 2026-05-28

### Added
- feat: version consistency — dynamic footer, version.json, bump to 2.0.0
- feat: MVP in-app updater — browser-based update system with no CLI dependency
- feat: replace all native browser dialogs with custom XSConfirm modal
- feat(pub-redesign): sidebar summary panel, mobile tips accordion, restore inline dispatch
- feat: integrations hub, delivery mode, online sessions, webhook support, Jitsi/PayFast/Zoom/Stripe/Google Calendar
- feat: add remove-tenant.sh and update deployment guide
- feat: add 10 Copilot skills and wire into copilot-instructions.md
- feat: update deployment docs and sync current workspace changes
- feat: auth hardening, analytics provider scoping, UI system overhaul
- feat(services): migrate services section to layouts/app design system
- feat: implement analytics dashboard with providers tab and filtering
- feat(security): Phase 2 CSP — nonce all inline scripts, remove unsafe-inline from script-src
- feat(security): Phase 1 CSP — centralize policy in CI4 engine, always-on
- feat(setup): add hosting compatibility mode hardening
- feat: finalize SEO public booking and unified metrics contracts
- feat: resolve active business context in notification services; add reminder queue metadata
- feat: RBAC multi-role enforcement, SPA lifecycle fixes, notification improvements
- feat(notifications): support dual reminder offsets per channel
- feat(notifications): add spark helper to test appointment reminders end-to-end
- feat(notifications): add business phone to customer email enquiry footer
- feat(notifications): location fallback, map links, business email in customer templates
- feat(notifications): rich customer appointment email templates
- feat(auth): add full multi-role user management flow
- feat: legal page and secure reference notifications
- feat: continue calendar refactor and schema compatibility stabilization
- feat(scheduler): change default view from week to day
- feat(scheduler): day-view adaptive rendering with client-side overlap resolution and 15-min grid
- feat: Calendar refactor hardening with multi-provider overlap validation and DST support
- feat(calendar): event-driven notifications and staff scoping
- feat(conflict): add appointment conflict service and harden schema
- feat(ui): refine scheduler controls, tokens, and docs sync
- feat: Add favicon upload and fix critical production issues

### Changed
- docs(agent): update Agent_Context_v2.md to v3.0 — codebase audit sync
- restore: Agent_Context_v2.md recovered from HEAD~1
- chore: sync documentation and workspace updates
- Update dashboard layout and styling
- docs: Update documentation index
- Implement dashboard provider cards and reminder automation health
- refactor(spa): harden SPA lifecycle, flash notifications, and form handling
- refactor(public-booking): consolidate loading state and extract helpers
- Improve public booking policies and scheduling flow
- refactor: Remove dead code and consolidate scheduler module (Phase A+B)
- refactor: Clean up scheduler week/month view duplication and dead code
- refactor: Clean up app.js duplication and dead code
- refactor: Clean up spa.js duplications and improve maintainability
- refactor: Clean up public-booking.js dead code and duplications
- docs: Add custom field required semantics documentation
- Relax required custom-field validation after first capture
- Harden customer upsert and public booking verification
- Add booking UX verification script and update test cache
- Refactor public booking to mobile-first selection hierarchy
- Harden public booking with input validation & rate limits
- refactor: reorganize docs archive and update notifications/services/tests
- docs: Add §11.8a reminder offsets behavior to Agent_Context_v2.md (v2.5)
- docs: restore all remaining deleted files from docs/ directory
- docs: restore 261 deleted .md documents from git history
- Implement live profile view and search hardening
- refactor: implement dashboard role-based scoping and extract frontend utilities
- docs(context): expand §11.9 Template Contract with full NotificationTemplateService spec
- refactor(email): unify SMTP transport via MailerService
- Harden notifications and localization flows; refresh agent context
- docs(agent): add production notification delivery prerequisites
- Implement booking status defaults, mutation coordinator, and public reschedule guard
- chore: remove dead PHP view user-management/customers.php (no route or controller method)
- chore: remove dead JS day-view-components (never imported anywhere)
- chore: remove dead JS timeRangeGenerator (never imported anywhere)
- docs: add user verification checklist for spacing fix
- docs: add comprehensive task completion record
- docs: add spacing fix verification guide
- chore: finalize legacy cleanup and context alignment
- Add country code support for General settings phones
- Reduce public booking hydration payload
- Handle legacy customer columns in appointment edit context
- docs: update Agent_Context.md with missing schema, settings, and rule additions
- docs: align agent context and README schema compatibility notes
- Restore user management mutation constructor compatibility
- Sync provider business hours from schedules
- refactor app boundaries and fix user management submit flow
- docs: add agent context and remove Cypress
- docs: add agent remedy action plan
- Refine week view expansion and remove slots panel
- Localize service and revenue currency displays
- Implement safe user deletion and refine scheduler flows
- refactor: normalize settings properties to camelCase
- refactor: consolidate status color definitions and emitAppointmentsUpdated import
- refactor: remove dead export initAllFilters (never imported)
- refactor(js): unify scheduler layout/time utils and remove legacy handlers
- refactor(calendar): apply audit fixes across overlap, timezone, config, and conflicts
- chore(calendar): normalize events and move architecture docs
- refactor(booking): centralize pipeline and normalize weekdays
- refactor(calendar): remove client-side slot engine; use API availability
- refactor(calendar): add TimeGridService, loop WeekView via DayView, add rebuild flag
- refactor(timezone): standardize blocked times to UTC and align conflict/availability queries
- docs: remove 3 stale root-level .md files (setup note + superseded calendar specs)
- docs: remove 2 completed dev docs; fix development/README dead links
- docs: update README count to 28 active documents
- docs: remove 4 stale arch docs; fix CALENDAR_UI_ARCHITECTURE component tree
- docs: update README count to 32 active documents
- docs: remove 5 more completed fix notes and stale API spec
- docs: remove 27 outdated and superseded documents
- refactor(js): audit appointments + calendar-utils layer
- refactor(js): audit utils layer — remove dead imports in app.js
- refactor(scheduler): audit JS module layer — delete orphans, consolidate shared helpers
- refactor(calendar): audit pass — eliminate duplication, fix N+1, remove orphans
- docs(calendar): update audit checklist for phase 6 completion
- refactor(calendar): complete steps 4-8 of calendar rebuild
- Step 3: Fix UTC timezone correctness across entire codebase
- refactor: rename start_time/end_time to start_at/end_at, migrate data to UTC
- calendar rebuild Phase 2-5 complete
- refactor: location strictness, scheduler naming convergence, calendar UI redesign
- v96: Modularize settings page into tab-based architecture
- docs: mark audit item #1 as complete
- refactor: consolidate settings.php from 8 script blocks to 2
- ui: hide SQLite option on setup page, default to MySQL
- chore: add alert diagnostic to create user script
- Add diagnostic logging to create user role toggle
- Sweep pass: guard remaining SPA listener accumulations
- Harden user management role-toggle parity for SPA re-execution
- Low-priority SPA audit fixes (L1-L3)
- Medium-priority SPA audit fixes (M1-M5)
- docs: add comprehensive SPA refresh audit report
- [ImgBot] Optimize images

### Fixed
- fix: mark infra-only CI jobs continue-on-error; fix dump-autoload flag
- fix: add composer dump-autoload after install for PHPUnit and setup-test jobs
- fix: CI infrastructure — MySQL GLOBAL privilege, server readiness, PR comment permission
- fix: match comment exclusion against content after filename:N: prefix
- fix: use POSIX [[:space:]] in business_hours grep exclusion
- fix: npm audit vulnerabilities and CI quality gate grep pattern
- fix: 5 production hardening fixes — avatar leak, SPA redirect, CSP nonces, time format, log severity
- fix: add favicon to public booking, my-appointments, and legal pages
- fix: add local Material Symbols CSS definitions; fix public booking icon empty states and inline style violations
- Fix provider scoping for dashboard and public booking
- fix(csp): restore same-origin assets and remove inline UI handlers
- fix(day-view): replace inline style= with data-style to fix CSP blocking
- fix(scheduler): resolve day-view stacking and enforce cache-busted entry assets
- fix(notifications): allow re-enqueue on cancelled/failed rows; widen reminder scan window to 48h lookback
- fix(migration): normalize users.provider_id to unsigned before FK
- fix(setup): load compatibility helper reliably on Linux
- fix(notifications): restore inline dispatch after enqueue on booking
- fix(notifications): repair business_id injection vector in settings save
- Fix: Prevent 'body stream already read' error in appointment creation
- fix: provider appointments starting at 10:00 due to unfiltered business_hours query
- fix: timezone integrity hardening across notification, availability, and JS layers
- fix(day-view): use flat appointments as authoritative source in _buildAppointmentsByProvider
- fix: appointments outside provider working hours no longer dropped from day view
- fix: calendar timeline reads business.work_start/end (Settings UI keys), not booking.day_start/end
- fix: calendar day view timeline anchored to business hours; now-line clamped to visible range
- fix(notifications): move enquiry line and restore business phone fallback
- Fix notification recipients and timezone rendering
- fix(rbac): harden multi-role auth paths and close TD-13 TD-14
- fix(appointments): preserve quick-book prefill on create
- fix: refine form icon spacing and update user edit info panel
- fix: apply consistent gap-3 roles spacing to create view
- fix: use gap-3 on flex container for consistent roles spacing on all breakpoints
- fix: improve spacing between checkbox and text in Roles card
- Fix customer edit parity and harden booking notifications
- Fix reschedule policy and timezone conflict regressions
- fix: preserve secure manage flow and append legacy reschedule links
- fix: enforce notification template parity and backfill legacy templates
- fix: comply with Agent_Context.md architecture and notifications rules
- Fix: send confirmation email synchronously on public booking
- Fix UserManagementMutationService constructor wiring
- Fix RBAC scoping, constructor fatal, and empty-hash 404s
- Fix staff RBAC scope and harden migrations/logging
- Fix appointment query alias stability and align docs
- Fix appointments SPA header controls in subfolder deployments
- Fix booking availability and harden appointment flows
- fix: sync scheduler filters and service form state
- Fix services and category form flows
- fix: button API compliance (label/tag/attrs) + week-view overflow/accordion UX
- Fix appointments form buttons and day-view status pill consistency
- fix: syntax errors in EventLayoutService cluster calculation
- fix: critical backend bug - EventLayoutService now accepts canonical 'start'/'end' keys from formatter
- fix: 6 critical bugs - dark mode selector, listener guards, server mode, hex matching, toast routing
- fix: 7 critical bugs - setStatusFilter, escapeHtml, listener guards, dark mode utils
- refactor: fix 23 cross-file code quality issues (consolidate escapeHtml/getBaseUrl, fix bugs, remove duplication)
- fix: remove hardcoded timezone fallback, let settingsManager handle DB settings
- fix: Unify dark mode implementation and complete design system cleanup
- Fix header clock timezone
- fix(audit): WeekViewService missed provider_id key + dead dayView; form create/update button text; field-error-dynamic lifecycle; dead classList.add on option
- fix(calendar): replace is_working with is_active in AvailabilityService provider schedule queries
- fix(calendar): add permissions+app helpers to autoload; fix provider_id key in view services
- fix: resolve profile view exception and complete frontend/backend audit
- fix: complete audit future work items 2-6
- fix: resolve SPA refresh-to-work issues across settings and auth
- fix: add dark mode classes to MySQL port field on setup page
- fix: prevent cursor-reset on email/tel inputs in public booking
- fix: remove legacy user_id from appointments + fix CSRF token refresh
- fix: replace MySQL GROUP_CONCAT SEPARATOR with SQLite-compatible syntax
- fix: move role toggle logic into provider-schedule component script
- Fix provider schedule visibility on create user form
- Fix provider role section toggle on user create SPA view
- Fix provider role dropdown not triggering schedule form
- Fix subdirectory redirect stripping in AJAX JSON responses
- fix(high): SPA-compatible view init, SPA navigation, listener guards
- fix(critical): resolve SPA blank-page bug, add AJAX responses, define XSNotify
- fix: improve detectProductionURL() for subdirectory detection
- fix: use base_url() in all redirects for subdirectory deployment
- fix: Provider Work Schedule not showing on role selection (SPA navigation)
- fix: Add try-catch to notification services for missing table resilience

## [1.0.4] - 2026-02-12

### Added
- SQLite compatibility improvements and setup hardening.
- Shared scheduler stats bar infrastructure with date-range aware API support.
- Database selection and zero-config SQLite deployment guides.

### Changed
- Booking flow boundaries and settings cleanup to reinforce a single source of truth.
- Week view UX with a redesigned right panel and mini date-picker dropdown.
- Week view defaults to the scheduler-first experience while temporary view toggle controls remain hidden.
- Documentation was reorganized to align with the repository structure.

### Fixed
- Provider schedule loading, scheduler stats SQL, and slot date-picker issues.
- SQLite setup failures caused by locked connections and missing `color` column drift.

## [1.0.3] - 2026-02-04

### Changed
- Release synchronization only. No additional user-facing changes were documented in this patch release.

## [1.0.2] - 2026-02-04

### Added
- Unified npm packaging workflow and expanded release documentation.
- UX, analytics, validation, and dashboard provider-availability improvements.

### Changed
- Release packaging audit and npm release workflow documentation.

### Fixed
- GitHub Actions release workflow now uses the local packaging command consistently.

## [1.0.1] - 2026-02-03

### Added
- Comprehensive bug reporting system with GitHub Issues templates
- GitHub Discussions integration for community support
- Automated label system for issue triage
- Enhanced CONTRIBUTING.md with detailed guidelines

### Changed
- Standardized all view naming to kebab-case convention
- Updated README.md with Support & Bug Reporting section

### Fixed
- Appointment modal currency display
- Notes field persistence in appointment form
- Edit button visibility in appointment modal
- Time format standardization across calendar views

---

## [1.0.0] - 2026-02-01

### Added
- ✅ Complete user authentication & role-based access control
- ✅ Four user roles: Admin, Provider, Staff, Customer
- ✅ Interactive appointment booking system
- ✅ Calendar views: Day, Week, Month with drag-and-drop
- ✅ Multi-provider support with color-coded calendars
- ✅ Real-time availability checking with 60-day lookahead
- ✅ Pre-populated time slot system
- ✅ Multi-channel notifications (Email, SMS, WhatsApp)
- ✅ Email notifications via SMTP
- ✅ SMS integration (Clickatell, Twilio)
- ✅ WhatsApp Business Cloud API integration
- ✅ Notification queue system with background processing
- ✅ Customer management with custom fields
- ✅ Service catalog with pricing & durations
- ✅ Provider schedule management
- ✅ Business hours configuration
- ✅ Dashboard analytics with real-time metrics
- ✅ Dark mode support with system-wide theme
- ✅ Material Design 3.0 component library
- ✅ Responsive mobile-first design
- ✅ Hash-based URLs for security
- ✅ CSRF & XSS protection
- ✅ Setup wizard for easy installation
- ✅ MySQL/MariaDB database support
- ✅ Timezone-aware scheduling
- ✅ Vite build system for assets
- ✅ Tailwind CSS 3.4.17
- ✅ CodeIgniter 4.6.1 framework

### Security
- Implemented CSRF protection on all forms
- Added XSS prevention with input sanitization
- Secure session handling with IP binding
- Hash-based non-enumerable URLs
- SQL injection prevention via query builder

---

## Version Schema

xScheduler follows [Semantic Versioning](https://semver.org/):

- **MAJOR** version (X.0.0) - Incompatible API changes or major features
- **MINOR** version (0.X.0) - New functionality in a backwards compatible manner
- **PATCH** version (0.0.X) - Backwards compatible bug fixes

### Version Types

- **Stable Release**: `v1.0.0`
- **Release Candidate**: `v1.0.0-rc.1`
- **Beta Release**: `v1.0.0-beta.1`
- **Alpha Release**: `v1.0.0-alpha.1`

---

## Change Categories

- **Added** - New features
- **Changed** - Changes in existing functionality
- **Deprecated** - Soon-to-be removed features
- **Removed** - Removed features
- **Fixed** - Bug fixes
- **Security** - Security improvements

---

## Release Notes

For detailed release notes, see [GitHub Releases](https://github.com/niloc95/xscheduler_ci4/releases).

## Upgrade Guides

For upgrade instructions between major versions, add or consult a dedicated upgrade guide under `/docs` for the relevant release.

---

[Unreleased]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.9...HEAD
[1.0.4]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.4
[1.0.3]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.3
[1.0.2]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.2
[1.0.1]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.1
[1.0.0]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.0
[2.0.1]: https://github.com/niloc95/xscheduler_ci4/compare/v1.0.4...v2.0.1
[2.0.2]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.1...v2.0.2
[2.0.3]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.2...v2.0.3
[2.0.4]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.3...v2.0.4
[2.0.5]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.4...v2.0.5
[2.0.6]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.5...v2.0.6
[2.0.7]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.6...v2.0.7
[2.0.8]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.7...v2.0.8
[2.0.9]: https://github.com/niloc95/xscheduler_ci4/compare/v2.0.8...v2.0.9

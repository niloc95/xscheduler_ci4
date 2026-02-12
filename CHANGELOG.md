# Changelog

All notable changes to xScheduler will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.4] - 2026-02-12

## [1.0.3] - 2026-02-04

## [1.0.2] - 2026-02-04

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

For upgrade instructions between major versions, see [docs/UPGRADING.md](docs/UPGRADING.md).

---

[Unreleased]: https://github.com/niloc95/xscheduler_ci4/compare/v1.0.0...HEAD
[1.0.4]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.4
[1.0.3]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.3
[1.0.2]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.2
[1.0.1]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.1
[1.0.0]: https://github.com/niloc95/xscheduler_ci4/releases/tag/v1.0.0

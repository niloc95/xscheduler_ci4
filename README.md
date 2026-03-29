# WebScheduler - Professional Appointment Scheduling System

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6.1-red.svg)](https://codeigniter.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://www.php.net/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.4.17-38bdf8.svg)](https://tailwindcss.com/)
[![Material Design](https://img.shields.io/badge/Material-Design%203.0-6750A4.svg)](https://m3.material.io/)

A modern, enterprise-ready appointment scheduling application built with CodeIgniter 4 and Material Design 3. Designed for service-based businesses including salons, clinics, consultancies, and any organization that needs professional appointment management.

## 📑 Table of Contents

- [Key Features](#-key-features)
- [Technology Stack](#-technology-stack)
- [System Requirements](#-system-requirements)
- [Quick Start](#-quick-start)
- [Installation Guide](#-installation-guide)
- [Configuration](#-configuration)
- [User Roles & Permissions](#-user-roles--permissions)
- [API Reference](#-api-reference)
- [Project Structure](#-project-structure)
- [Development](#-development)
- [Testing](#-testing)
- [Deployment](#-deployment)
- [Security](#-security)
- [Documentation](#-documentation)
- [Troubleshooting](#-troubleshooting)
- [Support & Bug Reporting](#-support--bug-reporting)
- [Roadmap](#-roadmap)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Key Features

### 📅 Intelligent Scheduling System
- **Pre-populated Availability**: 60-day lookahead with instant slot display
- **Real-time Conflict Detection**: Automatic validation against existing bookings
- **Timezone-Aware**: Full timezone support with automatic conversions
- **Smart Buffer Time**: Configurable gaps between appointments (0/15/30 min)
- **Business Hours Validation**: Respects provider schedules and breaks
- **Multi-Provider Support**: Provider-specific calendars with color coding

### 🗓️ Interactive Calendar
- **Day/Week/Month Views**: Custom-built scheduler with drag-and-drop
- **Provider Filtering**: View by individual or all providers
- **Status Color Coding**: Visual distinction for appointment states
- **Quick Actions**: Click appointments to view, edit, or update status
- **Responsive Design**: Works seamlessly on mobile devices

### 🔔 Multi-Channel Notifications
- **Email Notifications**: SMTP-based with customizable templates
- **SMS Integration**: Clickatell and Twilio support
- **WhatsApp Business**: Meta Cloud API integration with template enforcement
- **Event Types**: Confirmations, reminders, cancellations, reschedules
- **Queue Processing**: Background job processing for reliable delivery
- **Smart Reminders**: Automated appointment reminders with configurable timing

### 👥 Role-Based Access Control
- **Three Internal Roles + Public Customer Flows**: Admin, Provider, Staff (internal users) with customer booking/portal access via hash/token flows
- **Granular Permissions**: Fine-grained access per role
- **Provider Hierarchy**: Staff assigned to specific providers
- **Secure Authentication**: CSRF protection, session security

### 🎨 Modern UI/UX
- **Material Design 3.0**: Consistent, accessible component library
- **Dark Mode**: System-wide theme support with smooth transitions
- **Responsive Layout**: Mobile-first adaptive design
- **SPA Navigation**: Smooth page transitions without full reloads
- **WCAG Compliant**: Accessible interface elements

### ⚙️ Comprehensive Configuration
- **Setup Wizard**: Guided initial configuration
- **Business Settings**: Hours, time slots, booking rules
- **Service Catalog**: Durations, pricing, provider assignments
- **Localization**: Multi-language and regional format support
- **Custom Fields**: Configurable customer data fields

### 📊 Analytics & Reporting
- **Real-time Dashboard**: Today's metrics and alerts
- **Provider Analytics**: Performance tracking per provider
- **Appointment Statistics**: Status distribution and trends
- **Activity Logs**: Audit trail for compliance

### 🔒 Security Features
- **Hash-Based URLs**: Non-enumerable appointment/customer links
- **CSRF Protection**: All forms protected
- **XSS Prevention**: Input sanitization throughout
- **SQL Injection Prevention**: Query builder with parameterized queries
- **Session Security**: Secure cookie handling, IP binding

---

## 🏗️ Technology Stack

| Layer | Technology | Version |
|-------|------------|---------|
| **Backend** | CodeIgniter 4 | 4.6.1 |
| **Language** | PHP | 8.1+ |
| **Database** | MySQL / MariaDB | 5.7+ / 10.3+ |
| **CSS Framework** | Tailwind CSS | 3.4.17 |
| **Design System** | Material Design | 3.0 |
| **Charts** | Chart.js | 4.5.0 |
| **Build Tool** | Vite | 6.3.5 |
| **Icons** | Material Symbols | Latest |

---

## 💻 System Requirements

### Server Requirements
- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite OR Nginx
- Composer 2.x
- Node.js 18+ and npm

### Required PHP Extensions
- `intl` - Internationalization
- `mbstring` - Multibyte string handling
- `json` - JSON encoding/decoding
- `mysqlnd` - MySQL native driver
- `curl` - For notifications
- `openssl` - Encryption support

### Recommended
- SSL certificate (required for production)
- 256MB+ PHP memory limit
- Redis/Memcached (optional, for caching)

---

## 🚀 Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/niloc95/xscheduler_ci4.git
cd xscheduler_ci4

# 2. Install dependencies
composer install
npm install

# 3. Configure environment
cp .env.example .env
php spark key:generate

# 4. Edit .env with your database credentials
# database.default.hostname = localhost
# database.default.database = webschedulr
# database.default.username = your_user
# database.default.password = your_password

# 5. Run migrations
php spark migrate -n App

# 6. Build frontend assets
npm run build

# 7. Start development server
php spark serve

# 8. Access setup wizard at http://localhost:8080/setup
```

---

## 📦 Installation Guide

### Step 1: Server Preparation

**For Apache:**
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/xscheduler_ci4/public
    
    <Directory /path/to/xscheduler_ci4/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**For Nginx:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/xscheduler_ci4/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Step 2: Database Setup

```sql
-- Create database
CREATE DATABASE webschedulr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (optional)
CREATE USER 'webschedulr'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON webschedulr.* TO 'webschedulr'@'localhost';
FLUSH PRIVILEGES;
```

### Step 3: Application Setup

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build
npm ci
npm run build

# Set permissions
chmod -R 755 writable/
chown -R www-data:www-data writable/

# Run migrations
php spark migrate -n App
```

### Step 4: Complete Setup Wizard

Navigate to `https://yourdomain.com/setup` and complete:
1. Database verification
2. Admin account creation
3. Business information
4. Business hours configuration
5. Initial service setup

---

## ⚙️ Configuration

### Environment Configuration (.env)

```ini
#--------------------------------------------------------------------
# ENVIRONMENT
#--------------------------------------------------------------------
CI_ENVIRONMENT = production

#--------------------------------------------------------------------
# APP
#--------------------------------------------------------------------
app.baseURL = 'https://yourdomain.com'
app.timezone = 'Africa/Johannesburg'
app.forceGlobalSecureRequests = true

#--------------------------------------------------------------------
# DATABASE
#--------------------------------------------------------------------
database.default.hostname = localhost
database.default.database = webschedulr
database.default.username = db_user
database.default.password = db_password
database.default.DBDriver = MySQLi
database.default.DBPrefix = xs_
database.default.port = 3306

#--------------------------------------------------------------------
# SESSION
#--------------------------------------------------------------------
session.driver = 'CodeIgniter\Session\Handlers\FileHandler'
session.savePath = 'writable/session'
session.matchIP = true
session.timeToUpdate = 300
session.regenerateDestroy = true

#--------------------------------------------------------------------
# EMAIL (SMTP)
#--------------------------------------------------------------------
email.fromEmail = 'noreply@yourdomain.com'
email.fromName = 'WebScheduler'
email.protocol = smtp
email.SMTPHost = smtp.example.com
email.SMTPUser = smtp_user
email.SMTPPass = smtp_password
email.SMTPPort = 587
email.SMTPCrypto = tls

#--------------------------------------------------------------------
# SECURITY
#--------------------------------------------------------------------
security.CSRFProtection = true
security.CSRFTokenName = 'csrf_token'
security.CSRFCookieName = 'csrf_cookie'
security.CSRFExpire = 7200
security.CSRFRegenerate = true
security.CSRFSameSite = 'Strict'
```

### Application Settings (Admin Panel)

Access via `/settings` after login:

| Setting | Description |
|---------|-------------|
| **Business Hours** | Operating hours per day of week |
| **Time Slot Duration** | Default appointment slot length |
| **Buffer Time** | Gap between appointments |
| **Advance Booking** | How far ahead customers can book |
| **Cancellation Policy** | Minimum notice for cancellations |
| **Notification Templates** | Email/SMS/WhatsApp message content |

---

## 👥 User Roles & Permissions

### Role Hierarchy

```
Administrator (admin)
├── Full system access
├── User management (all roles)
├── System settings
├── Global analytics
└── All provider data

Provider
├── Own calendar management
├── Own appointment handling
├── Staff management (assigned)
├── Own analytics
└── Cannot modify system settings

Staff
├── Assigned provider's calendar
├── Own appointments only
├── Limited to assigned scope
└── No user management

Customer (public/hash-token access)
├── Book appointments through public booking
├── View own history via customer hash/token routes
├── Manage own appointment actions on public-safe links
└── Not an internal xs_users role
```

### Permission Matrix

| Feature | Admin | Provider | Staff | Public Customer |
|---------|:-----:|:--------:|:-----:|:---------------:|
| System Settings | ✅ | ❌ | ❌ | ❌ |
| User Management | ✅ | Own Staff | ❌ | ❌ |
| All Appointments | ✅ | Own | Assigned | ❌ |
| Create Appointments | ✅ | ✅ | ✅ | ✅ (public booking) |
| Cancel/Reschedule Appointment | ✅ | Own | Assigned | ✅ (own token/hash links) |
| Services Management | ✅ | Own | ❌ | ❌ |
| Analytics | Global | Own | ❌ | ❌ |
| Notification Rules | ✅ | ❌ | ❌ | ❌ |

### Schema Alignment Notes

- Internal users are stored in `xs_users` and use role values `admin`, `provider`, `staff`.
- Customers are stored in `xs_customers`; they are not internal `xs_users` records.
- Public-safe appointment/customer access should use `hash` and token fields, not sequential IDs.
- Canonical appointment datetime fields are `start_at` and `end_at` (UTC storage).

---

## 🔌 API Reference

### Base URL
```
Production: https://yourdomain.com/api/v1
Development: http://localhost:8080/api/v1
```

### Authentication
All API endpoints require authentication via session or Bearer token.

### Core Endpoints

#### Availability

```http
GET /api/availability/calendar
```
Get 60-day pre-computed availability calendar.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `provider_id` | int | Yes | Provider ID |
| `service_id` | int | Yes | Service ID |
| `start_date` | date | No | Start date (default: today) |
| `days` | int | No | Window size (default: 60) |

**Response:**
```json
{
  "ok": true,
  "data": {
    "availableDates": ["2026-01-30", "2026-01-31"],
    "slotsByDate": {
      "2026-01-30": [
        {"start": "2026-01-30T09:00:00+02:00", "startFormatted": "09:00", "available": true}
      ]
    },
    "timezone": "Africa/Johannesburg"
  }
}
```

#### Appointments

```http
GET /api/appointments
POST /api/appointments
GET /api/appointments/{id}
PUT /api/appointments/{id}
PATCH /api/appointments/{id}/status
DELETE /api/appointments/{id}
```

#### Providers

```http
GET /api/providers
GET /api/providers/{id}
GET /api/providers/{id}/services
```

#### Services

```http
GET /api/services
GET /api/services/{id}
```

For project documentation, start with [docs/readme.md](docs/readme.md).

---

## 📁 Project Structure

```
xscheduler_ci4/
├── app/
│   ├── Commands/               # CLI commands (notifications, reminders)
│   ├── Config/                 # Application configuration
│   ├── Controllers/            # Request handlers
│   │   ├── Api/               # RESTful API controllers
│   │   ├── Appointments.php   # Appointment management
│   │   ├── Dashboard.php      # Analytics dashboard
│   │   └── Settings.php       # System configuration
│   ├── Database/
│   │   └── Migrations/        # Database migrations (50+)
│   ├── Filters/               # Request filters (auth, roles)
│   ├── Helpers/               # Utility functions
│   ├── Libraries/             # Custom libraries
│   ├── Models/                # Database models
│   │   ├── AppointmentModel.php
│   │   ├── CustomerModel.php
│   │   ├── ServiceModel.php
│   │   └── UserModel.php
│   ├── Services/              # Business logic
│   │   ├── AvailabilityService.php
│   │   ├── BusinessHoursService.php
│   │   ├── NotificationQueueService.php
│   │   └── SchedulingService.php
│   └── Views/                 # Templates
│       ├── appointments/      # Appointment views
│       ├── components/        # Reusable UI components
│       ├── dashboard/         # Dashboard views
│       └── layouts/           # Page layouts
├── docs/                      # Canonical project documentation
│   ├── architecture/          # Architecture and system design
│   ├── audits/                # Historical audit and remediation records
│   ├── deployment/            # Packaging and release guides
│   ├── scheduler/             # Scheduler-specific documentation
│   └── readme.md              # Documentation index
├── public/                    # Web root
│   ├── index.php             # Application entry
│   └── build/                # Compiled assets
├── resources/
│   ├── css/                  # Source stylesheets
│   ├── js/                   # JavaScript modules
│   │   ├── modules/
│   │   │   ├── scheduler/    # Calendar components
│   │   │   ├── appointments/ # Booking logic
│   │   │   └── filters/      # UI filters
│   │   └── app.js           # Main entry
│   └── scss/                 # SCSS source
├── tests/                    # Test suites
│   ├── unit/                 # Unit tests
│   └── integration/          # Integration tests
├── writable/                 # Runtime files (logs, cache)
├── composer.json             # PHP dependencies
├── package.json              # Node dependencies
├── tailwind.config.js        # Tailwind configuration
└── vite.config.js           # Build configuration
```

---

## 🛠️ Development

### Development Server

```bash
# Terminal 1: PHP server
php spark serve

# Terminal 2: Vite dev server (hot reload)
npm run dev

# Terminal 3: Watch logs (optional)
tail -f writable/logs/log-$(date +%Y-%m-%d).log
```

### Available Commands

```bash
# Development
npm run dev              # Start Vite dev server
npm run build            # Production build
npm run preview          # Preview production build

# Database
php spark migrate -n App           # Run migrations
php spark migrate:rollback -n App  # Rollback last migration
php spark db:seed                  # Run seeders

# Notifications
php spark notifications:dispatch-queue  # Process notification queue

# Cache
php spark cache:clear    # Clear all caches

# Testing
./vendor/bin/phpunit tests/unit/           # Unit tests
./vendor/bin/phpunit tests/integration/    # Integration tests
```

### Focused Validation Commands

```bash
# Constructor seam and controller regression slices
php vendor/bin/phpunit tests/unit/Controllers/SearchControllerTest.php
php vendor/bin/phpunit tests/unit/Controllers/CustomerManagementControllerTest.php
php vendor/bin/phpunit tests/unit/Controllers/ServicesControllerTest.php

# Controller journeys
php vendor/bin/phpunit tests/integration/CustomerManagementJourneyTest.php
php vendor/bin/phpunit tests/integration/ServicesJourneyTest.php

# Mixed focused runs used during refactor work
php vendor/bin/phpunit tests/unit/Controllers/SearchControllerTest.php tests/integration/CustomerManagementJourneyTest.php
php vendor/bin/phpunit tests/unit/Controllers/ServicesControllerTest.php tests/integration/ServicesJourneyTest.php
```

If PHPUnit reports that no code coverage driver is available, assertions may still have passed successfully. That warning only means coverage data was not collected. Install Xdebug with coverage enabled when you need clean coverage runs.

### Code Standards

- Follow [CodeIgniter 4 Style Guide](https://codeigniter.com/user_guide/general/styleguide.html)
- Use PSR-12 coding standards
- Document all public methods with PHPDoc
- Write tests for new features

---

## 🧪 Testing

### Test Strategy

- Unit tests cover isolated service, model, and controller seam behavior.
- Integration journey tests cover authenticated controller flows such as customer CRUD/history and service CRUD/provider assignment.
- Frontend runtime coverage uses Node and jsdom for SPA re-initialization and page module behavior.

### Controller Journey Expectations

Controller-level journeys in this repository often need more than a plain request/response assertion:

- hydrate `database.tests.*` from `.env` when local PHPUnit config does not provide the test connection explicitly
- ensure `writable/setup_complete.flag` exists
- prime the CSRF cookie for AJAX-backed form submissions
- seed deterministic settings when validation depends on runtime configuration, especially booking field visibility and required flags

### Schema Compatibility Notes

- Mixed local schemas may expose internal-user active state as `is_active`, `status`, or both; queries and fixtures should resolve active users by checking available columns instead of assuming one field.
- Hash columns are expected for public-facing appointment/customer flows, but compatibility checks should guard writes in partially migrated environments.

### Testing Entry Points

- [docs/testing/test_runner_guide.md](docs/testing/test_runner_guide.md) for test environment and runner details
- [docs/architecture/refactor_target_decision_record.md](docs/architecture/refactor_target_decision_record.md) for the active refactor ledger
- [docs/architecture/provider_service_catalog_contract.md](docs/architecture/provider_service_catalog_contract.md) for provider/service pivot behavior and service-management expectations

---

## 🚀 Deployment

### Production Checklist

- [ ] Set `CI_ENVIRONMENT = production`
- [ ] Configure `app.baseURL` correctly
- [ ] Enable `app.forceGlobalSecureRequests = true`
- [ ] Set strong database password
- [ ] Configure email settings
- [ ] Run `npm run build`
- [ ] Set `writable/` permissions to 755
- [ ] Configure SSL certificate
- [ ] Set up cron job for notifications

### Cron Job Setup

```bash
# Process notification queue (every minute)
* * * * * cd /path/to/project && php spark notifications:dispatch-queue >> /dev/null 2>&1
```

### Hosting Compatibility

✅ **Shared Hosting**: cPanel, Plesk (requires PHP 8.1+)  
✅ **VPS/Cloud**: DigitalOcean, AWS, Linode, Vultr  
✅ **Managed Platforms**: Cloudways, Laravel Forge  
✅ **Containers**: Docker (Dockerfile included)

---

## 🔒 Security

### Security Features

| Feature | Status | Description |
|---------|--------|-------------|
| CSRF Protection | ✅ | All forms protected with tokens |
| XSS Prevention | ✅ | Input/output sanitization |
| SQL Injection | ✅ | Parameterized queries throughout |
| Password Hashing | ✅ | bcrypt with cost factor 12 |
| Hash-Based URLs | ✅ | Non-enumerable resource URLs |
| Session Security | ✅ | IP binding, regeneration, secure cookies |
| Role-Based Access | ✅ | Server-side authorization |

### Reporting Security Issues

**Do not** open public issues for security vulnerabilities.

Report security concerns privately to: **info@webschedulr.co.za**

See [docs/security_policy.md](docs/security_policy.md) for our full security policy.

---

## 📚 Documentation

### Primary entrypoints

| Document | Description |
|----------|-------------|
| [Documentation index](docs/readme.md) | Canonical entrypoint for all repository-authored docs |
| [Requirements](docs/requirements.md) | Runtime, platform, and environment requirements |
| [Agent context](Agent_Context.md) | Active engineering context and architecture guardrails |
| [Refactor decision record](docs/architecture/refactor_target_decision_record.md) | Current status of controller seam cleanup, frontend extraction, and focused regression work |
| [Provider/service catalog contract](docs/architecture/provider_service_catalog_contract.md) | Provider-to-service pivot rules for booking and internal service-management flows |
| [Test runner guide](docs/testing/test_runner_guide.md) | PHPUnit setup, focused test commands, and repository-specific controller journey guidance |
| [Scheduler UI architecture](docs/architecture/scheduler_ui_architecture.md) | Scheduler design and boundaries |
| [Release guide](docs/deployment/releasing.md) | Release process and packaging workflow |
| [Security policy](docs/security_policy.md) | Responsible disclosure and reporting |

---

## 🐛 Troubleshooting

### Common Issues

#### Calendar Not Loading
```bash
# Check browser console for errors
# Rebuild assets
npm run build
# Clear cache
php spark cache:clear
```

#### Notifications Not Sending
```bash
# Verify SMTP settings in .env
# Check queue
php spark notifications:dispatch-queue
# Review logs
tail -f writable/logs/log-*.log
```

#### Database Connection Errors
```bash
# Verify credentials in .env
# Test connection

# If you hit unknown-column errors after branch switches,
# run app migrations and rerun focused integration tests
php spark migrate -n App
php vendor/bin/phpunit tests/integration/UserManagementJourneyTest.php tests/integration/DayViewServiceIntegrationTest.php tests/integration/WeekViewServiceIntegrationTest.php
php spark db:table users
# Run pending migrations
php spark migrate -n App
```

#### Schema-Drift and Unknown Column Errors
```bash
# Verify runtime schema on default DB group
php spark db:table xs_users --dbgroup default
php spark db:table xs_appointments --dbgroup default
php spark db:table xs_services --dbgroup default

# If migration history drifted across groups, confirm status explicitly
php spark migrate:status -n App
```

When writing JOIN-heavy query builders with aliased selects (`c.*`, `s.*`, `u.*`), use raw select projections where needed (`select($sql, false)`) to avoid CI4 identifier rewriting on prefixed tables.

After changing shared query services, always lint touched PHP files before endpoint checks:

```bash
php -l app/Services/Appointment/AppointmentQueryService.php
```

#### Permission Errors
```bash
# Set correct permissions
chmod -R 755 writable/
chown -R www-data:www-data writable/
```

#### Assets Not Loading
```bash
# Clear caches
php spark cache:clear
# Rebuild assets
npm run build
# Verify baseURL in .env
```

### Getting Help

- **Issues**: [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues)
- **Discussions**: [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions)
- **Email**: info@webschedulr.co.za

---

## � Support & Bug Reporting

We use GitHub's built-in tools for all support, bug reports, and feature requests. Please follow these guidelines:

### 🐞 Reporting Bugs

**Found a bug?** → [**Create a Bug Report**](https://github.com/niloc95/xscheduler_ci4/issues/new/choose)

Before reporting:
1. **Search existing issues** to avoid duplicates
2. **Check the documentation** in [`/docs`](docs/)
3. **Review [docs/requirements.md](docs/requirements.md)** to ensure your environment is supported

**What to include:**
- Environment details (Localhost, VPS, Shared Hosting)
- PHP version and CodeIgniter 4 version
- Clear steps to reproduce the issue
- Expected vs actual behavior
- Error logs from `writable/logs/` or browser console
- Screenshots if applicable

### ✨ Requesting Features

**Have an idea?** → [**Create a Feature Request**](https://github.com/niloc95/xscheduler_ci4/issues/new/choose)

**What to include:**
- Clear problem description
- Proposed solution with details
- Use case / user story (who benefits and how)
- Alternative approaches (optional)

### 💬 Asking Questions

**Need help?** → [**Start a Discussion**](https://github.com/niloc95/xscheduler_ci4/discussions)

Use GitHub Discussions for:
- ❓ General questions ("How do I...?")
- 🛠️ Installation and setup help
- 💡 Ideas that aren't fully formed yet
- 🗣️ Community feedback and suggestions
- 🎓 How-to questions

**Please do NOT use Issues for support questions** - use Discussions instead.

### 🔒 Security Vulnerabilities

**Found a security issue?** → See [**docs/security_policy.md**](docs/security_policy.md) for responsible disclosure.

**Do NOT open public issues for security vulnerabilities.**

### 📚 Documentation

Before asking for help, check these resources:

| Document | Description |
|----------|-------------|
| [docs/requirements.md](docs/requirements.md) | System requirements and compatibility |
| [docs/readme.md](docs/readme.md) | Complete documentation index |
| [docs/contributing.md](docs/contributing.md) | Contribution workflow and standards |
| [docs/security_policy.md](docs/security_policy.md) | Security policy |

---

## �📈 Roadmap

### ✅ Completed (v1.0)
- [x] User authentication & role-based access
- [x] Appointment booking & management
- [x] Interactive calendar (day/week/month)
- [x] Multi-provider support
- [x] Multi-channel notifications (Email/SMS/WhatsApp)
- [x] Real-time availability checking
- [x] Customer management
- [x] Dashboard analytics
- [x] Dark mode support
- [x] Hash-based URL security
- [x] Setup wizard
- [x] Pre-populated availability system

### 🚧 In Development (v1.1)
- [ ] Payment integration (Stripe, PayPal)
- [ ] Advanced reporting & CSV exports
- [ ] Calendar sync (Google Calendar, Outlook)
- [ ] Recurring appointments
- [ ] Waiting list management

### 📋 Planned (v2.0)
- [ ] Multi-location support
- [ ] Customer self-service portal
- [ ] Video consultation integration
- [ ] Mobile app (iOS/Android)
- [ ] Package & membership support
- [ ] Marketing automation
- [ ] Inventory management

---

## 🤝 Contributing

We welcome contributions! Please follow our workflow:

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/amazing-feature`
3. **Commit** your changes: `git commit -m 'Add amazing feature'`
4. **Push** to the branch: `git push origin feature/amazing-feature`
5. **Open** a Pull Request to `main`

### Contribution Guidelines

- Follow CodeIgniter 4 coding standards
- Write clear, descriptive commit messages
- Add tests for new features
- Update documentation as needed
- Keep PRs focused on single features

See [docs/contributing.md](docs/contributing.md) for detailed guidelines.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

Built with these excellent open-source projects:

- [CodeIgniter 4](https://codeigniter.com/) - PHP framework
- [Tailwind CSS](https://tailwindcss.com/) - CSS framework
- [Material Design](https://m3.material.io/) - Design system
- [Chart.js](https://www.chartjs.org/) - Charts library
- [Vite](https://vitejs.dev/) - Build tool
- [Luxon](https://moment.github.io/luxon/) - Date/time library

---

## 📞 Contact & Support

| Channel | Link |
|---------|------|
| **Website** | [webscheduler.co.za](https://webscheduler.co.za) |
| **Email** | info@webscheduler.co.za |
| **GitHub** | [@niloc95](https://github.com/niloc95) |
| **Repository** | [github.com/niloc95/xscheduler_ci4](https://github.com/niloc95/xscheduler_ci4) |

---

<div align="center">

**Made with ❤️ for service-based businesses worldwide**

*Modern, accessible appointment scheduling for everyone*

</div>








# WebScheduler CI4

[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6.1-red.svg)](https://codeigniter.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://www.php.net/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.4-38bdf8.svg)](https://tailwindcss.com/)
[![Material Design](https://img.shields.io/badge/Material-Design%203-6750A4.svg)](https://m3.material.io/)
[![Version](https://img.shields.io/badge/version-1.0.4-blue.svg)](docs/changelog.md)

Professional appointment scheduling for service-based businesses. Built with CodeIgniter 4, PHP 8.1+, MySQL/MariaDB, Vite 6, Tailwind CSS 3, and Material Design 3.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [System Requirements](#system-requirements)
- [Quick Start](#quick-start)
- [Installation](#installation)
- [Configuration](#configuration)
- [Roles and Permissions](#roles-and-permissions)
- [API Reference](#api-reference)
- [Public Booking](#public-booking)
- [Scheduling and Availability](#scheduling-and-availability)
- [Calendar Architecture](#calendar-architecture)
- [Notifications](#notifications)
- [Project Structure](#project-structure)
- [Development](#development)
- [Testing](#testing)
- [Deployment](#deployment)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)
- [Support and Bug Reporting](#support-and-bug-reporting)
- [Contributing](#contributing)
- [License](#license)

## Overview

WebScheduler CI4 is a multi-role appointment platform with internal scheduling tools, public booking routes, and queue-based multi-channel notifications.

Core contracts:
- Runtime database is MySQL/MariaDB only.
- All persisted datetimes are UTC; display conversion happens at render time.
- Public-facing appointment and customer links use hash/token identifiers.
- Controllers stay thin; business logic lives in services.

## Features

- Interactive day/week/month scheduler with server-built view models.
- Provider-scoped business hours, blocked times, and conflict detection.
- Public booking flow: service -> provider -> slot -> confirmation.
- Customer self-service links via secure hash routes.
- Notifications via email, SMS, and WhatsApp using queue dispatch.
- Reminder offsets are independent per offset window.
- Role-based access with admin/provider/staff/customer boundaries.
- SPA navigation and re-initialization lifecycle for authenticated UI.

## Technology Stack

| Layer | Technology | Version |
| --- | --- | --- |
| Backend | CodeIgniter | 4.6.1 |
| Language | PHP | 8.1+ |
| Database | MySQL / MariaDB | runtime only |
| Build Tool | Vite | 6.3.5 |
| CSS | Tailwind CSS | 3.4 |
| UI System | Material Design | 3 |
| Charts | Chart.js | 4 |
| Datetime | Luxon | 3 |

## System Requirements

- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.3+
- Composer 2.x
- Node.js 18+ and npm
- Apache with mod_rewrite or Nginx

Required PHP extensions:
- intl
- mbstring
- json
- mysqlnd
- curl
- openssl

## Quick Start

```bash
git clone https://github.com/niloc95/xscheduler_ci4.git
cd xscheduler_ci4

composer install
npm install

cp .env.example .env
php spark key:generate
# configure DB in .env

php spark migrate -n App
npm run build
php spark serve
```

Then complete setup at http://localhost:8080/setup.

## Installation

### 1) Web server

Apache vhost:

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

Nginx site:

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
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

### 2) Database

```sql
CREATE DATABASE webschedulr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'webschedulr'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON webschedulr.* TO 'webschedulr'@'localhost';
FLUSH PRIVILEGES;
```

### 3) App setup

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php spark migrate -n App
```

### 4) Setup wizard

Visit /setup and complete:
- database verification
- admin user
- business profile
- business hours
- initial services

## Configuration

Environment example:

```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://yourdomain.com'
app.forceGlobalSecureRequests = true

# runtime timezone source is localization.timezone in xs_settings

# DB
database.default.hostname = localhost
database.default.database = webschedulr
database.default.username = db_user
database.default.password = db_password
database.default.DBDriver = MySQLi
database.default.DBPrefix = xs_
database.default.port = 3306

# Session
session.driver = 'CodeIgniter\Session\Handlers\FileHandler'
session.savePath = 'writable/session'
session.matchIP = true
session.regenerateDestroy = true

# Security
security.CSRFProtection = true
security.CSRFTokenName = 'csrf_token'
security.CSRFSameSite = 'Strict'
```

Settings are stored in xs_settings as typed key/value rows. Key namespaces include:
- general.*
- localization.*
- booking.*
- calendar.*
- notifications.*
- branding.*
- security.*

Important model contract:
- Use SettingModel::getByKeys(), getByPrefix(), getAllAsMap(), upsert().
- Do not call a non-existent getValue() method.

## Roles and Permissions

Roles:
- admin
- provider
- staff
- customer

Storage model:
- Internal users: xs_users
- Role membership: xs_user_roles (authoritative)
- Booking customers: xs_customers
- Appointment customer link: xs_appointments.customer_id -> xs_customers.id

Permission summary:
- admin: full system access
- provider: own calendar/services/staff scope
- staff: assigned provider scope
- customer: public booking and own hash/token routes

Filter patterns used in routes:
- setup
- auth
- role:admin
- role:admin,provider
- role:admin,provider,staff

## API Reference

API surfaces:
- Operational endpoints under /api/
- Versioned endpoints under /api/v1/

Response envelope:

```json
{ "data": {}, "meta": {} }
```

Error envelope:

```json
{ "error": { "message": "", "code": "", "details": {} } }
```

Key endpoints:

Appointments:
```http
GET    /api/appointments
POST   /api/appointments
GET    /api/appointments/{id}
PATCH  /api/appointments/{id}
PATCH  /api/appointments/{id}/status
PATCH  /api/appointments/{id}/notes
POST   /api/appointments/{id}/reschedule
DELETE /api/appointments/{id}
```

Calendar view models:
```http
GET /api/calendar/day?date=YYYY-MM-DD
GET /api/calendar/week?date=YYYY-MM-DD
GET /api/calendar/month?year=YYYY&month=MM
```

Availability:
```http
GET /api/availability/calendar?provider_id=&service_id=&start_date=&days=
```

Settings and catalogs:
```http
GET /api/v1/settings/localization
GET /api/v1/providers
GET /api/v1/providers/{id}/services
GET /api/v1/services
```

Scheduler deep-link:
- /appointments?open={hash}

## Public Booking

Public routes:
- /book
- /book/{serviceSlug}
- /book/{serviceSlug}/{providerHash}
- /my-appointments/{hash}

Public safety rules:
- use hash/public token, not numeric IDs
- customer-facing links are non-enumerable

## Scheduling and Availability

Scheduling model highlights:
- canonical appointment fields: start_at, end_at (UTC)
- conflict checks at service layer
- provider-scoped business hours in xs_business_hours
- global work bounds in xs_settings keys business.work_start and business.work_end
- blocked intervals in xs_blocked_times

Availability pipeline:
- slot generation considers provider schedule, service duration, buffers, blocked times, existing appointments, and timezone conversion.

## Calendar Architecture

Calendar data flow:
- frontend requests /api/calendar/{day|week|month}
- backend DayViewService/WeekViewService/MonthViewService builds server-side view model
- frontend scheduler modules render from returned model

Frontend contract:
- app.js bootstraps authenticated surface
- spa.js handles in-app navigation
- use xsRegisterViewInit(fn) for page module initialization
- avoid DOMContentLoaded-only initializers in SPA-managed surfaces

## Notifications

Channels:
- email
- sms
- whatsapp

Pipeline:
1. Appointment events enqueue rows in xs_notification_queue.
2. Cron command dispatches queue.
3. Dispatcher routes by channel and writes xs_notification_delivery_logs.

Cron command:

```bash
php spark notifications:dispatch-queue
```

Reminder offset behavior:
- offsets are processed independently
- each offset writes distinct queue rows with reminder_offset_minutes
- schedule_fingerprint prevents stale reminders after reschedules
- reminder_sent is compatibility state; not enqueue-time dedupe logic

Email transport contract:
- MailerService is the only email sending path.
- Active database integration in xs_business_integrations has priority.
- In development only, fallback to env/local Mailpit when no active integration is configured.

Template and placeholder notes:
- message templates live in xs_message_templates
- recipient_class separates customer vs internal templates
- customer reminder template requires consistent placeholder coverage

## Project Structure

```text
xscheduler_ci4/
  app/
    Commands/
    Config/
    Controllers/
      Api/
    Database/
      MigrationBase.php
      Migrations/
    Filters/
    Helpers/
    Models/
    Services/
    Views/
  docs/
    readme.md
    architecture/
    configuration/
    deployment/
    scheduler/
    testing/
    security/
  public/
    build/
  resources/
    js/
      app.js
      spa.js
      public-booking.js
      modules/
    scss/
      app-consolidated.scss
  tests/
    unit/
    integration/
    frontend/
  writable/
  Agent_Context_v2.md
```

## Development

Core runtime commands:

```bash
php spark serve
php spark migrate -n App
php spark notifications:dispatch-queue
npm run dev
npm run build
```

Additional commands from package scripts:

```bash
npm run mailpit:start
npm run mailpit:status
npm run mailpit:stop
npm run test:frontend:lifecycle
npm run test:unit:calendar
npm run test:integration:mysql
npm run release:patch
npm run build:prod
```

Frontend guardrails:
- do not introduce React/Vue/Alpine/TypeScript without explicit approval
- keep single SCSS entry at resources/scss/app-consolidated.scss
- apiRequest() already parses JSON payloads; do not re-read response body stream

## Testing

Testing strategy:
- unit tests for isolated services/models/controllers
- integration journey tests for authenticated flows
- frontend tests for SPA lifecycle and scheduler selectors

Examples:

```bash
./vendor/bin/phpunit tests/unit/
./vendor/bin/phpunit tests/integration/
php vendor/bin/phpunit tests/integration/CustomerManagementJourneyTest.php
npm run test:unit:calendar
npm run test:integration:mysql
```

Important test notes:
- test DB only; never run destructive test flows on runtime DB
- ensure writable/setup_complete.flag exists for journey tests that require setup completion
- mixed local schemas may need compatibility checks for status/is_active fields

## Deployment

Production checklist:
- set CI_ENVIRONMENT=production
- set correct app.baseURL
- force HTTPS with app.forceGlobalSecureRequests
- build frontend assets
- run migrations
- configure writable permissions
- configure SMTP/SMS/WhatsApp integrations
- schedule queue cron every minute

Cron example:

```cron
* * * * * cd /path/to/project && php spark notifications:dispatch-queue >> /dev/null 2>&1
```

Release commands:

```bash
npm run release:patch
npm run build:prod
```

## Security

Implemented controls:
- CSRF protection
- server-side RBAC checks
- parameterized queries
- password hashing
- secure session handling
- hash-based public identifiers

Security reporting:
- do not report vulnerabilities via public issues
- follow docs/security/security_policy.md

## Troubleshooting

Common checks:

Calendar not loading:

```bash
npm run build
php spark cache:clear
```

Queue not sending:

```bash
php spark notifications:dispatch-queue
```

Migration/schema mismatch:

```bash
php spark migrate -n App
php spark migrate:status -n App
php spark db:table xs_users --dbgroup default
php spark db:table xs_appointments --dbgroup default
```

Local email testing:

```bash
npm run mailpit:start
# inbox: http://127.0.0.1:8025
```

Filesystem permissions:

```bash
chmod -R 755 writable/
chown -R www-data:www-data writable/
```

## Documentation

Start here:
- docs/readme.md
- docs/INDEX.md
- docs/REQUIREMENTS.md
- Agent_Context_v2.md

Key references:
- docs/architecture/
- docs/configuration/ENV-CONFIGURATION-GUIDE.md
- docs/deployment/RELEASING.md
- docs/testing/test_runner_guide.md
- docs/security/security_policy.md

## Support and Bug Reporting

Use GitHub:
- Issues for bugs and feature requests
- Discussions for support questions

Links:
- https://github.com/niloc95/xscheduler_ci4/issues/new/choose
- https://github.com/niloc95/xscheduler_ci4/discussions

Include in bug reports:
- environment and versions
- exact reproduction steps
- expected vs actual result
- relevant logs from writable/logs

## Contributing

Workflow:
1. fork
2. create feature branch
3. implement and test
4. push
5. open PR

Conventions:
- business logic in services
- migrations extend App\Database\MigrationBase
- preserve API response envelope contract
- preserve UTC storage contract
- preserve hash-based public URL contract

See docs/contributing.md for full guidelines.

## License

Copyright (c) 2025 Nilesh Cara. All rights reserved.

This repository contains a proprietary commercial license. See LICENSE-PROPRIETARY.

# XScheduler CI4 - Master Codebase Index

**Last Updated:** January 28, 2026  
**Status:** Comprehensive Audit Complete  
**Next Review:** Q2 2026

---

## Quick Navigation

### 📋 Audit Documents

1. **[CODEBASE_AUDIT.md](./CODEBASE_AUDIT.md)** - Main audit report
   - Executive summary
   - Architecture overview
   - Directory structure
   - Redundancy analysis
   - Cleanup plan

2. **[CODEBASE_AUDIT_CONFIG.md](./CODEBASE_AUDIT_CONFIG.md)** - Critical configuration
   - Routes.php documentation
   - Database.php documentation
   - App.php settings
   - Services.php container
   - Filters/middleware

3. **[CODEBASE_AUDIT_CONTROLLERS.md](./CODEBASE_AUDIT_CONTROLLERS.md)** - *(In Progress)*
   - 18 active controllers
   - Entry points and responsibilities
   - Dependencies and relationships

4. **[CODEBASE_AUDIT_MODELS.md](./CODEBASE_AUDIT_MODELS.md)** - *(In Progress)*
   - 20 data models
   - Relationships and queries
   - Database table mappings

5. **[CODEBASE_AUDIT_ROUTES.md](./CODEBASE_AUDIT_ROUTES.md)** - *(In Progress)*
   - Complete route listing
   - Execution flow diagrams
   - Filter applications

---

## File Inventory by Type

### A. Configuration Files (28 files)

**Location:** `app/Config/`

**CRITICAL - Do not modify without understanding:**
- `Routes.php` - URL routing (299 lines)
- `Database.php` - DB connection
- `App.php` - Application settings
- `Services.php` - Dependency injection
- `Filters.php` - Middleware chain

**Important - Keep documented:**
- `Api.php` - API settings
- `Cors.php` - CORS configuration
- `Email.php` - Email settings
- `Security.php` - Security options
- `Session.php` - Session handling
- `Cache.php` - Caching strategy
- `Encryption.php` - Encryption config
- `Toolbar.php` - Debug toolbar
- `Validation.php` - Validation rules
- `Constants.php` - Global constants

**Rarely Used - Review for removal:**
- `Calendar.php` - ⚠️ Legacy?
- `UserAgents.php` - Legacy
- `Honeypot.php` - Not confirmed in use
- `Kint.php` - Debug tool
- `Mimes.php` - Auto-loaded
- `DocTypes.php` - Auto-loaded
- Others: Generators, Logger, Routing, Publisher, etc.

---

### B. Controllers (18 active + 3 API)

**Location:** `app/Controllers/`

#### Main Controllers

| Controller | Lines | Purpose | Status |
|-----------|-------|---------|--------|
| `Dashboard.php` | 504 | Dashboard, metrics | ✅ Refactored (Phase 6-7) |
| `Search.php` | 109 | **Global search** (extracted Phase 1) | ✅ New |
| `Appointments.php` | ~400 | Appointment CRUD | ✅ Active |
| `CustomerManagement.php` | ~500 | Customer CRUD, search | ✅ Active |
| `Services.php` | ~400 | Service management | ✅ Active |
| `UserManagement.php` | ~400 | User CRUD | ✅ Active |
| `Auth.php` | ~300 | Login, logout, password reset | ✅ Active |
| `Settings.php` | ~300 | Application settings | ✅ Active |
| `Help.php` | ~300 | Help system | ✅ Active |
| `Notifications.php` | ~200 | Notification management | ✅ Active |
| `ProviderSchedule.php` | ~300 | Provider scheduling | ⚠️ Active (overlaps with Scheduler) |
| `Scheduler.php` | ~300 | ⚠️ Experimental scheduler | ⚠️ Experimental |
| `StaffProviders.php` | ~200 | Staff assignments | ✅ Active |
| `ProviderStaff.php` | ~200 | Similar to StaffProviders | ⚠️ Possible duplicate |
| `Analytics.php` | ~200 | Analytics dashboards | ✅ Active |
| `Profile.php` | ~150 | User profile management | ✅ Active |
| `Setup.php` | ~300 | Initial setup wizard | ✅ Active |
| `AppFlow.php` | ~100 | App routing logic | ✅ Active |
| `Home.php` | ~50 | ⚠️ Possibly unused | ⚠️ Verify |
| `Assets.php` | ~100 | Asset serving | ✅ Active |
| `Styleguide.php` | ~100 | Component documentation | ✅ Active |

**Size Analysis:**
- **Large (>500 lines):** Dashboard, Appointments, CustomerManagement
  - *Recommendation:* Consider splitting into smaller classes
- **Medium (300-500 lines):** Most others
  - *Recommendation:* Monitor for growth
- **Small (<150 lines):** Home, AppFlow, Assets
  - *Recommendation:* Review for consolidation

#### API Controllers

| Controller | Purpose | Status |
|-----------|---------|--------|
| `Api/Appointments.php` | Appointment API | ✅ Active |
| `Api/Availability.php` | Availability checking | ✅ Active |
| `Api/Locations.php` | Location API | ✅ Active |
| `Api/Users.php` | User API | ✅ Active |
| `Api/CustomerAppointments.php` | Customer appointment API | ✅ Active |
| `Api/DatabaseBackup.php` | Backup utility | ✅ Active |
| `Api/V1/Settings.php` | ⚠️ DEPRECATED | 🔴 Remove |
| `Api/V1/Services.php` | ⚠️ DEPRECATED | 🔴 Remove |
| `Api/V1/Providers.php` | ⚠️ DEPRECATED | 🔴 Remove |

#### Special Controllers

- `PublicSite/BookingController.php` - Public booking interface
- `PublicSite/CustomerPortalController.php` - Customer portal
- `BaseController.php` - Base class for all controllers

---

### C. Models (20 files)

**Location:** `app/Models/`

#### Data Models

| Model | Purpose | Table | Status |
|-------|---------|-------|--------|
| `UserModel` | Users (providers, staff, admins) | xs_users | ✅ |
| `CustomerModel` | Customers | xs_customers | ✅ |
| `AppointmentModel` | Appointments | xs_appointments | ✅ |
| `ServiceModel` | Services offered | xs_services | ✅ |
| `LocationModel` | Locations/branches | xs_locations | ✅ |
| `CategoryModel` | Service categories | xs_categories | ✅ |
| `ProviderScheduleModel` | Provider availability | xs_provider_schedules | ✅ |
| `BusinessHourModel` | Business hours | xs_business_hours | ✅ |
| `BlockedTimeModel` | Blocked time slots | xs_blocked_times | ✅ |
| `SettingModel` | Application settings | xs_settings | ✅ |
| `AuditLogModel` | Audit trail | xs_audit_logs | ✅ |
| `SettingFileModel` | File-based settings | (No table) | ✅ |

#### Notification Models

| Model | Purpose | Table | Status |
|-------|---------|-------|--------|
| `NotificationQueueModel` | Pending notifications | xs_notification_queue | ✅ |
| `NotificationDeliveryLogModel` | Delivery history | xs_notification_delivery_logs | ✅ |
| `NotificationOptOutModel` | Opt-out tracking | xs_notification_opt_outs | ✅ |

#### Support Models

| Model | Purpose | Table | Status |
|-------|---------|-------|--------|
| `ProviderStaffModel` | Staff/provider assignments | xs_provider_staff_assignments | ✅ |
| `UserPermissionModel` | User permissions | xs_user_permissions | ✅ |
| `MessageTemplateModel` | Message templates | xs_message_templates | ✅ |
| `BusinessIntegrationModel` | External integrations | xs_business_integrations | ✅ |
| `BusinessNotificationRuleModel` | Notification rules | xs_business_notification_rules | ✅ |
| `BaseModel` | Base class for all models | - | ✅ |

---

### D. Views (150+ files)

**Location:** `app/Views/`

#### Layout Files

| File | Purpose | Status |
|------|---------|--------|
| `layouts/app.php` | Main authenticated layout | ✅ Recently refactored |
| `layouts/public.php` | Public site layout | ✅ |
| `layouts/setup.php` | Setup wizard layout | ✅ |

#### Dashboard & Landing

| File | Purpose | Status |
|------|---------|--------|
| `dashboard/landing.php` | Main dashboard view | ✅ Refactored |
| `index.html` | SPA root | ✅ |

#### Feature Views

| Directory | Files | Status |
|-----------|-------|--------|
| `appointments/` | 2 files | ✅ |
| `customer_management/` | 1 file | ✅ |
| `services/` | Multiple | ✅ |
| `user_management/` | Multiple | ✅ |
| `settings/` | Multiple | ✅ |
| `auth/` | 3 files (login, reset, etc.) | ✅ |
| `help/` | Multiple | ✅ |
| `notifications/` | Multiple | ✅ |

#### Components

| Directory | Purpose | Status |
|-----------|---------|--------|
| `components/` | Reusable components | ✅ Well-organized |
| `components/card.php` | Card wrapper | ✅ |
| `components/page-header.php` | Page headers (standardized) | ✅ |
| `components/unified-sidebar.php` | Main sidebar | ✅ |
| `components/ui/` | 10+ UI components | ✅ |

---

### E. Database Files

#### Migrations (40+ files)

**Location:** `app/Database/Migrations/`

**Status:** ✅ Stable and chronologically ordered

**Core Tables:**
- Users (roles, permissions)
- Customers
- Appointments
- Services & Categories
- Locations
- Business Hours & Blocked Times

**Feature Tables:**
- Notifications (queue, delivery logs, opt-outs)
- Provider Schedules
- Staff Assignments
- Audit Logs

#### Seeds (Multiple files)

**Location:** `app/Database/Seeds/`

- `MainSeeder` - Primary seed
- `DefaultServicesSeeder`
- `BusinessHoursSeeder`
- `DummyAppointmentsSeeder`
- `SchedulingSampleDataSeeder`

---

### F. Frontend Assets

#### JavaScript Modules (in `resources/js/`)

**Main Entry Point:**

| File | Lines | Purpose | Status |
|------|-------|---------|--------|
| `app.js` | 172 | **Main app entry (83% reduction)** | ✅ Refactored |
| `spa.js` | ~200 | SPA routing | ✅ Active |
| `charts.js` | ~500 | Dashboard charts | ✅ Active |
| `unified-sidebar.js` | ~100 | Sidebar functionality | ✅ Active |
| `dark-mode.js` | ~50 | Dark mode toggle | ✅ Active |
| `time-format-handler.js` | ~100 | Time formatting | ✅ Active |
| `public-booking.js` | ~400 | Public booking interface | ✅ Active |
| `setup.js` | ~300 | Setup wizard | ✅ Active |
| `materialWeb.js` | ~500 | Material Design | ✅ Active |
| 5+ utility files | ~100 each | Various utilities | ✅ Active |

**Extracted Modules (New - Phase 1-5 Refactoring):**

| Module | Lines | Purpose | Status |
|--------|-------|---------|--------|
| `modules/search/global-search.js` | 325 | Global search (desktop + mobile) | ✅ Phase 1 |
| `modules/filters/status-filters.js` | 281 | Dashboard status filtering | ✅ Phase 2 |
| `modules/filters/advanced-filters.js` | 188 | Advanced filter panel UI | ✅ Phase 3 |
| `modules/scheduler/scheduler-ui.js` | 157 | Scheduler toolbar & navigation | ✅ Phase 4 |
| `modules/appointments/appointment-navigation.js` | 128 | Appointment form prefilling | ✅ Phase 5 |

**Module Ecosystem (Pre-existing):**

| Module | Lines | Purpose | Status |
|--------|-------|---------|--------|
| `modules/scheduler/scheduler-core.js` | ~800 | Core scheduler logic | ✅ Active |
| `modules/scheduler/scheduler-month-view.js` | ~300 | Month view rendering | ✅ Active |
| `modules/scheduler/scheduler-week-view.js` | ~600 | Week view rendering | ✅ Active |
| `modules/scheduler/scheduler-day-view.js` | ~300 | Day view rendering | ✅ Active |
| `modules/scheduler/scheduler-drag-drop.js` | ~500 | Drag & drop functionality | ✅ Active |
| `modules/scheduler/appointment-colors.js` | ~300 | Color theming & status | ✅ Active |
| `modules/scheduler/appointment-details-modal.js` | ~700 | Appointment modal UI | ✅ Active |
| `modules/appointments/appointments-form.js` | ~800 | Appointment booking form | ✅ Active |
| `modules/appointments/time-slots-ui.js` | ~400 | Time slot selection UI | ✅ Active |
| `modules/calendar/calendar-utils.js` | ~200 | Calendar helpers | ✅ Active |

**Refactoring Impact:**
- **app.js:** 1,020 → 172 lines (83% reduction) ✅
- **New modules:** 5 extracted modules (1,079 lines total)
- **Maintainability:** High - Single Responsibility Principle
- **Testability:** Easy - Isolated, modular functions

#### SCSS/CSS (in `resources/scss/`)

**Key Files:**
- `main.scss` - Entry point
- `layout/_app-layout.scss` - **CRITICAL: Design tokens**
  - Contains `--xs-header-height`, `--xs-frame-inset-*`, etc.
- `layout/_unified-content-system.scss` - Content layout system
- 20+ component stylesheets

**Design Tokens:** All in `_app-layout.scss`
```scss
--xs-header-height: 6rem;
--xs-frame-inset-desktop: 64px;
--xs-content-inset-*: Various padding values
--xs-radius-lg: Border radius
```

---

### G. Commands/CLI (8 files)

**Location:** `app/Commands/`

| Command | Trigger | Purpose | Status |
|---------|---------|---------|--------|
| `SendAppointmentReminders` | CRON | Email reminders | ✅ |
| `SendAppointmentSmsReminders` | CRON | SMS reminders | ✅ |
| `SendAppointmentWhatsAppReminders` | CRON | WhatsApp reminders | ✅ |
| `DispatchNotificationQueue` | CRON | Process queue | ✅ |
| `ExportNotificationDeliveryLogs` | Manual | Export logs | ✅ |
| `PurgeNotificationDeliveryLogs` | CRON | Cleanup | ✅ |
| `TestCustomerSearch` | Manual | Test search | ✅ |
| `TestEncryption` | Manual | Test encryption | ✅ |

---

### H. Filters/Middleware (8 files)

**Location:** `app/Filters/`

| Filter | Purpose | Applied To |
|--------|---------|------------|
| `AuthFilter` | Authentication check | Protected routes |
| `RoleFilter` | Role-based access | Role-protected routes |
| `SetupFilter` | Setup completion | Public routes |
| `SetupAuthFilter` | Pre-setup auth | Setup wizard |
| `CorsFilter` | CORS headers | API routes |
| `SecurityHeaders` | Security headers | All responses |
| `TimezoneDetection` | User timezone | Dashboard |
| `PublicBookingRateLimiter` | Rate limiting | Public booking |

---

### I. Build & Configuration Files

**Root Level:**

| File | Purpose | Status |
|------|---------|--------|
| `vite.config.js` | Vite build configuration | ✅ Active |
| `tailwind.config.js` | Tailwind CSS configuration | ✅ Active |
| `postcss.config.js` | PostCSS configuration | ✅ Active |
| `package.json` | Node.js dependencies | ✅ Active |
| `composer.json` | PHP dependencies | ✅ Active |
| `phpunit.xml.dist` | PHPUnit configuration | ⚠️ Tests minimal |
| `cypress.config.js` | E2E testing configuration | ✅ Active |

---

## Recent Changes (This Session)

### ✅ Global Search Implementation

**Commit:** `fd5f6e7`

**What Changed:**
1. Added `/dashboard/search` endpoint for unified global search
2. Implemented robust JSON extraction to handle debug toolbar
3. Global search now searches both customers and appointments
4. Matches Customer Management search pattern with:
   - 300ms debounce
   - Result rendering (customers + appointments)
   - Click-through navigation

**Files Modified:**
- `app/Views/layouts/app.php` - Search result containers
- `resources/js/app.js` - initGlobalSearch() function (~200 lines)
- `resources/scss/layout/_app-layout.scss` - Layout tokens

---

## Known Issues & Cleanup Tasks

### 🔴 High Priority

- [ ] **Deprecate `/api/v1/*` endpoints**
  - Create migration guide
  - Set 90-day removal date
  - Remove routes and controllers

- [ ] **Duplicate scheduler implementations**
  - `Scheduler.php` vs `ProviderSchedule.php`
  - Decision: Consolidate or mark experimental?

### 🟡 Medium Priority

- [ ] **Standardize view file naming**
  - Rename kebab-case files to snake_case
  - Update all view() calls

- [ ] **Audit helper functions**
  - Identify unused helpers
  - Remove or document

- [ ] **Remove debug code**
  - Commented-out code blocks
  - Temporary test code

### 🟢 Low Priority

- [ ] **Modularize `app.js`**
  - Break into modules by feature
  - Improve maintainability

- [ ] **Add unit tests**
  - Target 70% coverage
  - Focus on models and business logic

---

## Statistics

| Metric | Value | Status |
|--------|-------|--------|
| **Total Files Audited** | 4,292 | ✅ |
| **PHP Controllers** | 21 | Active |
| **PHP Models** | 20 | Active |
| **View Files** | 150+ | Organized |
| **Database Migrations** | 40+ | Stable |
| **Config Files** | 28 | Reviewed |
| **Filters/Middleware** | 8 | Active |
| **CLI Commands** | 8 | Working |
| **JavaScript Modules** | 15+ | Active |
| **SCSS Stylesheets** | 25+ | Organized |
| **Frontend Routes** | 20+ | Documented |
| **API Endpoints** | 15+ | Mixed (V1 + Current) |
| **Build Size** | 233 KB (main.js) | Monitored |

---

## Quick Search Guide

**Need to find something?**

| Question | Look In |
|----------|----------|
| "How do I add a new route?" | `CODEBASE_AUDIT_CONFIG.md` → Routes.php |
| "Where is the global search code?" | `resources/js/app.js` → initGlobalSearch() |
| "What does this controller do?" | `CODEBASE_AUDIT_CONTROLLERS.md` |
| "What API endpoints exist?" | `app/Config/Routes.php` → `/api/` group |
| "How does authentication work?" | `app/Filters/AuthFilter.php` |
| "What database tables exist?" | `app/Database/Migrations/` |
| "Where are the components?" | `app/Views/components/` |
| "How do I run background tasks?" | `app/Commands/` |
| "What are the design tokens?" | `resources/scss/layout/_app-layout.scss` |

---

## Next Steps

1. **Review this index** with team
2. **Create remaining audit documents:**
   - CODEBASE_AUDIT_CONTROLLERS.md
   - CODEBASE_AUDIT_MODELS.md
   - CODEBASE_AUDIT_ROUTES.md
3. **Create cleanup plan** and prioritize
4. **Schedule deprecation** of API V1
5. **Plan refactoring** (modularize app.js, split large controllers)
6. **Add unit tests** (target 70% coverage)

---

**Master Index Version:** 1.0  
**Last Updated:** January 28, 2026  
**Maintained By:** Development Team  
**Next Review:** Q2 2026


# WebSchedulr Codebase Quick Reference

**Quick Navigation for New Developers & Maintainers**

---

## 📍 WHERE TO FIND THINGS

### Authentication & Authorization
- **Files:** `app/Filters/AuthFilter.php`, `app/Services/AuthService.php`
- **Flow:** POST `/auth/login` → validate → set session
- **Session Keys:** `user_id`, `user`, `user_role`, `provider_id`
- **Roles:** admin, provider, staff, customer

### Appointments Management
- **Controller:** `app/Controllers/Appointments.php`
- **Model:** `app/Models/AppointmentModel.php`
- **Services:** `AppointmentService.php`, `AppointmentBookingService.php`
- **Views:** `app/Views/appointments/`
- **Database Table:** `xs_appointments`

### Customer Management
- **Controller:** `app/Controllers/CustomerManagement.php`
- **Model:** `app/Models/CustomerModel.php`
- **Views:** `app/Views/customer_management/`
- **Database Table:** `xs_customers` (separate from xs_users)
- **Recent:** Refactored UI with stat card (Jan 2026)

### User/Staff/Provider Management
- **Controller:** `app/Controllers/UserManagement.php`
- **Model:** `app/Models/UserModel.php`
- **Database Table:** `xs_users` (role-based)
- **Roles Stored:** admin, provider, staff, customer

### Dashboard
- **Controller:** `app/Controllers/Dashboard.php`
- **Service:** `app/Services/DashboardService.php`
- **View:** `app/Views/dashboard/index.php`
- **Layout:** `app/Views/layouts/dashboard.php`
- **Status:** Phase 8-9 refactoring complete ✅

### Services & Categories
- **Controller:** `app/Controllers/Services.php`
- **Models:** `ServiceModel.php`, `CategoryModel.php`
- **Views:** `app/Views/services/`
- **Database Tables:** `xs_services`, `xs_categories`

### Notifications
- **Controller:** `app/Controllers/Notifications.php`
- **Service:** `app/Services/NotificationService.php`
- **Queue:** `app/Models/NotificationQueueModel.php`
- **Commands:** `DispatchNotificationQueue.php`, `SendAppointmentReminders.php`
- **Channels:** Email, SMS, WhatsApp

### Settings & Configuration
- **Controller:** `app/Controllers/Settings.php`
- **API:** `app/Controllers/Api/V1/Settings.php`
- **Model:** `app/Models/SettingModel.php`
- **Service:** `app/Services/BookingSettingsService.php`
- **Database Table:** `xs_settings`

### Public Booking (Customer-Facing)
- **Controller:** `app/Controllers/PublicSite/BookingController.php`
- **View:** `app/Views/public/booking/`
- **Route:** Separate from admin (unauthenticated access)

---

## 🔄 COMMON WORKFLOWS

### Create an Appointment
```
1. GET /appointments/create → Form view
2. POST /appointments → Validation → AppointmentBookingService::create()
3. Model insert → Event trigger → Notification queue
4. Redirect to /appointments
```

### Search/Filter (Any List View)
```
1. GET /customer-management?q=search_term
2. Controller receives $q parameter
3. Model::search(['q' => $q]) → finds matching records
4. Return filtered results
```

### Update Setting
```
1. POST /api/v1/settings → Settings API
2. Handles both JSON and form data
3. SettingModel::update()
4. Return JSON response
```

---

## 📁 FILE ORGANIZATION

### Controllers
- **Main CRUD:** Dashboard, Appointments, CustomerManagement, UserManagement, Services
- **Config Pages:** Settings, ProviderSchedule, ProviderStaff
- **User Pages:** Profile, Help, Notifications, Styleguide
- **API Endpoints:** Api/Dashboard, Api/Appointments, Api/V1/Settings
- **Public:** PublicSite/BookingController, PublicSite/CustomerPortalController

### Views
- **Layouts:** `layouts/app.php` (main), `layouts/dashboard.php`, `layouts/public.php`
- **Components:** Reusable in `components/card.php`, `components/page-header.php`, `components/unified-sidebar.php`
- **Sections:** Extends layout with named sections (sidebar, header_title, content, scripts)

### Database
- **Migrations:** Sequentially numbered, track schema changes
- **Seeders:** MainSeeder (entry point), then specialized seeders

### Frontend Assets
- **CSS:** `resources/scss/` (structured by abstrats/base/components/layout/pages)
- **JavaScript:** `resources/js/modules/` (calendar, appointments, scheduler, search, filters)
- **Build:** Vite `public/build/assets/` (minified, versioned)

### Tests
- **Unit:** `tests/unit/` (Models, Services, Helpers)
- **Integration:** `tests/integration/` (Controllers, Workflows)
- **Manual:** `tests/manual/` (non-automated tests)

---

## 🛠️ COMMON TASKS

### Add a New Controller
```php
// 1. Create: app/Controllers/MyController.php
class MyController extends BaseController {
    public function index() {
        // Render or return response
    }
}

// 2. Add route: app/Config/Routes.php
$routes->get('my-page', 'MyController::index');

// 3. Create view: app/Views/my_page/index.php
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
  <!-- Your content -->
<?= $this->endSection() ?>
```

### Add a New Model
```php
// 1. Create: app/Models/MyModel.php
class MyModel extends BaseModel {
    protected $table = 'xs_my_table';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
}

// 2. Create migration: app/Database/Migrations/2026-01-29-120000_CreateMyTable.php
public function up() {
    $this->forge->createTable('xs_my_table', [
        'id' => ['type' => 'INT', 'auto_increment' => true, 'unsigned' => true],
        // ... columns
    ]);
}

// 3. Run migration
php spark migrate -n App
```

### Add a New Service
```php
// 1. Create: app/Services/MyService.php
class MyService {
    public function doSomething() {
        // Business logic
    }
}

// 2. Use in Controller
$service = new MyService();
$result = $service->doSomething();
```

### Add a New Component
```php
// 1. Create: app/Views/components/my_component.php
<!-- Reusable HTML -->

// 2. Use in views
<?= view('components/my_component', ['param' => $value]) ?>
```

---

## 🔑 KEY CONSTANTS & ENUMS

### Database Prefixes
- All tables start with `xs_` (e.g., `xs_users`, `xs_appointments`)

### User Roles
- `admin` - Full system access
- `provider` - Service provider (can manage own appointments)
- `staff` - Provider staff member
- `customer` - Booking customer

### Appointment Status
- `pending` - Awaiting confirmation
- `confirmed` - Confirmed
- `completed` - Finished
- `cancelled` - Cancelled

### Notification Channels
- `email`
- `sms`
- `whatsapp`

---

## 🚨 IMPORTANT FILES (DON'T DELETE WITHOUT REVIEW)

| File | Why Important |
|------|---|
| `app/Config/Routes.php` | Central routing - if deleted, app breaks |
| `app/Config/Database.php` | Database connection - if wrong, all fails |
| `app/Models/BaseModel.php` | Custom model hooks - foundation for all models |
| `app/Filters/AuthFilter.php` | Authentication - protects admin routes |
| `app/Common.php` | Global functions - used throughout |
| `resources/scss/` | All styling - affects entire UI |
| `app/Views/layouts/app.php` | Master layout - extends to all pages |

---

## 📊 PERFORMANCE NOTES

### Caching Strategy
- Dashboard metrics cached for 1 hour
- Query results cached where appropriate
- Cache flushed on data updates

### Database Indexes
- Added on frequently searched columns
- See migrations for complete list

### Frontend Optimization
- Vite builds assets to `public/build/`
- CSS minified to ~170KB
- JavaScript split into modules (~250 total)
- Lazy loading for non-critical assets

---

## 🧪 RUNNING TESTS

```bash
# All tests
vendor/bin/phpunit

# Specific test file
vendor/bin/phpunit tests/unit/Models/UserModelTest.php

# Watch mode (requires plugin)
vendor/bin/phpunit --watch
```

---

## 🗄️ DATABASE COMMANDS

```bash
# Run migrations
php spark migrate -n App

# Rollback one migration
php spark migrate:rollback -n App

# Reset database
php spark migrate:refresh -n App

# Seed data
php spark db:seed MainSeeder
```

---

## 🔍 DEBUGGING

### Enable Debug Mode
- Edit `.env` to set `CI_ENVIRONMENT = development`
- Debug toolbar appears at bottom of page

### Check Logs
- Location: `writable/logs/`
- View recent errors: `tail -f writable/logs/log-*.log`

### Test Query
```php
log_message('info', 'Debug info: ' . json_encode($data));
```

---

## 📚 FULL DOCUMENTATION

For complete details, refer to:
- **Comprehensive Audit:** `docs/COMPREHENSIVE_CODEBASE_AUDIT.md`
- **Executive Summary:** `docs/AUDIT_EXECUTIVE_SUMMARY.md`
- **Phase Tracking:** `docs/development/HIGH_PRIORITY_ISSUES_RESOLUTION.md`
- **Feature Docs:** `docs/features/`, `docs/architecture/`

---

## ✅ VERIFICATION CHECKLIST (Before Commit)

- [ ] File has header comment with purpose
- [ ] Follows naming conventions (PascalCase classes, camelCase methods)
- [ ] No commented-out code blocks (2+ lines)
- [ ] No debug statements (dd, var_dump, die)
- [ ] Tests written for new logic (80%+ coverage target)
- [ ] No magic strings (use constants/enums)
- [ ] Dependent files in same commit

---

**Last Updated:** January 29, 2026  
**Linked to:** COMPREHENSIVE_CODEBASE_AUDIT.md


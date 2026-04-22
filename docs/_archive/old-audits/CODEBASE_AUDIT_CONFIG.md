# XScheduler CI4 - Detailed Configuration File Documentation

**Reference:** [Main Audit](./CODEBASE_AUDIT.md)

---

## Table of Contents

1. [Routes.php - URL Routing](#routesphp---url-routing)
2. [Database.php - Database Configuration](#databasephp---database-configuration)
3. [App.php - Application Settings](#appphp---application-settings)
4. [Services.php - Service Container](#servicesphp---service-container)
5. [Filters.php - Middleware Chain](#filtersphp---middleware-chain)
6. [Other Critical Configs](#other-critical-configs)

---

## Routes.php - URL Routing

**Path:** `app/Config/Routes.php`

**File Type:** Configuration → Routing Engine

**Size:** 299 lines

**Purpose:** 
Central location for all URL routing in the application. Defines which URLs map to which controllers and what filters (middleware) apply to each route.

**Execution Context:** 
- Loaded at framework bootstrap
- Evaluated before each request
- Parsed into route collection

**Key Responsibilities:**

1. **Define all public-facing URLs**
   - User-accessible endpoints
   - API endpoints
   - Static routes

2. **Apply filters to routes**
   - Authentication checks (`'filter' => 'auth'`)
   - Role-based access (`'filter' => 'role:admin,provider'`)
   - Setup completion checks (`'filter' => 'setup'`)

3. **Support URL parameters**
   - Named parameters: `(:num)`, `(:any)`, `(:segment)`
   - Route groups for organizing related routes

4. **Maintain backwards compatibility**
   - Support old route patterns if still in use

**Critical Content Analysis:**

### Route Groups

| Group | Prefix | Filters | Purpose |
|-------|--------|---------|---------|
| Root | `/` | None | Application entry point |
| Setup | `/setup` | None | Initial configuration wizard |
| Auth | `/auth` | `setup` | Authentication (login/logout) |
| Dashboard | `/dashboard` | `setup`, `auth` | Main app dashboard |
| Customer Management | `/customer-management` | `setup`, `role:admin,provider,staff` | CRUD for customers |
| User Management | `/user-management` | `setup`, `role:admin,provider` | Admin user management |
| Services | `/services` | None (per-route) | Service catalog management |
| Analytics | `/analytics` | `role:admin,provider` | Analytics dashboards |
| Notifications | `/notifications` | `auth` | Notification center |
| Appointments | `/appointments` | `setup`, `auth` | Appointment scheduling |
| Provider Schedule | `/provider-schedule` | Various | Provider availability |
| Staff/Providers | `/staff-providers` | Various | Staff assignments |
| Settings | `/settings` | `auth` | Application settings |
| Help | `/help` | None | Help system |
| Public Booking | `/public/` | None | Public booking interface |
| API | `/api/` | Various | API endpoints |
| API V1 | `/api/v1/` | Various | **DEPRECATED** |

### Key Routes

**CRITICAL - These routes must never change:**

```php
// Entry point
$routes->get('/', 'AppFlow::index');

// Setup flow
$routes->get('setup', 'Setup::index');
$routes->post('setup/process', 'Setup::process');

// Authentication
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/login', 'Auth::attemptLogin');
$routes->get('auth/logout', 'Auth::logout');

// Dashboard (protected)
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);

// Global Search endpoint (NEW)
$routes->get('dashboard/search', 'Dashboard::search', ['filter' => 'auth']);
```

**RECENTLY CHANGED:**

- ✅ Added `/dashboard/search` for unified global search (addresses header search functionality)
- ✅ Customer Management now has separate `/customer-management/search` endpoint
- ⚠️ `/api/v1/` routes still present but deprecated

### Status & Recommendations

| Status | Finding |
|--------|---------|
| ✅ Well-organized | Route groups are logical |
| ✅ Filters applied | Authentication and role checks in place |
| ⚠️ API V1 legacy | Deprecated endpoints still routed |
| ✅ Recent improvements | Global search route added correctly |
| 🟡 Monitor | Watch for route conflicts as app grows |

**Recommendation:**
- Document the deprecation timeline for `/api/v1/*` routes
- Create migration guide for API consumers
- Remove V1 routes after deprecation period (suggest 90 days)

---

## Database.php - Database Configuration

**Path:** `app/Config/Database.php`

**File Type:** Configuration → Database Connection

**Purpose:**
Defines database connection parameters, connection pooling, and query logging.

**Execution Context:**
- Loaded when database is first accessed
- Creates persistent connection
- Applied to all database queries

**Key Responsibilities:**

1. **Define database connections**
   - Host, port, username, password
   - Database name
   - Connection options

2. **Configure query logging**
   - DBDebug mode
   - Query caching

3. **Connection pooling** (if applicable)
   - Persistent connections
   - Connection limits

**Current Setup:**

```php
// Default connection (typically set via .env)
'default' => [
    'DSN'      => '',
    'hostname' => $_ENV['database.default.hostname'] ?? 'localhost',
    'username' => $_ENV['database.default.username'] ?? 'root',
    'password' => $_ENV['database.default.password'] ?? '',
    'database' => $_ENV['database.default.database'] ?? 'xscheduler',
    'DBDriver' => 'MySQLi',
    'DBPrefix' => 'xs_',
    'pConnect' => false,
    'DBDebug'  => (ENVIRONMENT !== 'production'),
    'charset'  => 'utf8mb4',
    'DBCollat' => 'utf8mb4_unicode_ci',
    'swapPre'  => '',
    'encrypt'  => false,
    'compress' => false,
    'strictOn' => false,
    'port'     => $_ENV['database.default.port'] ?? 3306,
    'timeout'  => 0,
],
```

**Key Features:**

- **DBPrefix:** `xs_` - All tables prefixed with `xs_`
- **DBDebug:** Enabled in development, disabled in production
- **Charset:** UTF-8 multi-byte for internationalization
- **Environment Variables:** Connection params from `.env` file

**⚠️ Important Notes:**

1. **Table Prefix:** All queries should use `xs_` prefix
   ```php
   // Correct
   $this->db->table('xs_customers');
   
   // Wrong
   $this->db->table('customers');
   ```

2. **Character Set:** UTF-8 MB4 supports emoji and special characters
   - Important for customer names and notes

3. **Strict Mode:** Currently `false`
   - May need to enable for production MySQL 8.0+

**Status & Recommendations:**

| Status | Finding |
|--------|---------|
| ✅ Well-configured | Proper use of environment variables |
| ✅ Character set | UTF-8 MB4 for international support |
| ⚠️ Strict mode | Should enable in production |
| ✅ Prefix strategy | `xs_` prefix is clear |

---

## App.php - Application Settings

**Path:** `app/Config/App.php`

**File Type:** Configuration → Application Settings

**Purpose:**
Core application settings including URL configuration, timezone, security options, and session management.

**Execution Context:**
- Loaded at framework bootstrap
- Applied globally to entire application

**Key Responsibilities:**

1. **URL Configuration**
   - Base URL for the application
   - URL rewriting behavior
   - Index file handling

2. **Security Settings**
   - CSRF protection
   - Cookie settings
   - Session configuration

3. **Localization**
   - Default timezone
   - Default language
   - Date/time formats

4. **Performance**
   - Cache settings
   - Compression options

**Critical Settings:**

```php
// Base URL - MUST be configured correctly
public string $baseURL = 'http://localhost:8080/';

// Index file in URL (.html vs empty)
public string $indexPage = '';

// Timezone
public string $appTimezone = 'UTC';

// CSRF protection
public bool $CSRFProtection = true;

// Session
public string $sessionDriver = 'CodeIgniterSession';
public int $sessionExpiration = 7200;

// Cookie settings
public string $cookieName = 'XSID';
public bool $cookieSecure = false; // Set to true in production
public bool $cookieHttpOnly = true;
```

**Current Configuration Issues:**

| Issue | Severity | Fix |
|-------|----------|-----|
| Hardcoded baseURL | 🟡 Medium | Use environment variable |
| cookieSecure = false | 🟡 Medium | Set to true in production |
| appTimezone = UTC | ✅ OK | Consider per-user timezone in future |

**Recommendations:**

1. **Environment-based URL:**
   ```php
   public string $baseURL = $_ENV['app.baseURL'] ?? 'http://localhost:8080/';
   ```

2. **Dynamic Cookie Security:**
   ```php
   public bool $cookieSecure = ENVIRONMENT !== 'development';
   ```

3. **Add timezone detection filter** (already exists: `TimezoneDetection` filter)

---

## Services.php - Service Container

**Path:** `app/Config/Services.php`

**File Type:** Configuration → Dependency Injection Container

**Purpose:**
Registers services (classes) in the DI container. Services are automatically injected into controllers and can be retrieved throughout the app.

**Execution Context:**
- Loaded once at bootstrap
- Services instantiated on demand
- Enables dependency injection

**Pattern Used:**

```php
public static function userModel(bool $getShared = true): UserModel
{
    if ($getShared) {
        return static::getSharedInstance('userModel');
    }
    return new UserModel();
}
```

**Key Services Registered:**

| Service | Purpose | File |
|---------|---------|------|
| `userModel` | User data access | `app/Models/UserModel` |
| `customerModel` | Customer data access | `app/Models/CustomerModel` |
| `appointmentModel` | Appointment data | `app/Models/AppointmentModel` |
| `notificationQueue` | Notification queue | `app/Models/NotificationQueueModel` |
| (others) | Various models | `app/Models/*` |

**Shared vs Non-shared:**

```php
// Shared instance (singleton) - same object returned
return static::getSharedInstance('userModel');

// New instance each time
return new UserModel();
```

**Current Usage in Controllers:**

```php
// Automatic injection
public function __construct(UserModel $userModel)
{
    $this->userModel = $userModel;
}

// Or manual retrieval
$userModel = service('userModel');
```

**Status & Recommendations:**

| Status | Finding |
|--------|---------|
| ✅ Well-organized | Services logically named |
| ✅ Consistent pattern | All services follow same structure |
| ✅ Dependency injection | Controllers can request services |
| 🟡 Could add more | Helper services for business logic |

**Potential Improvements:**

1. **Create service classes** for business logic
   ```php
   // app/Services/AppointmentReminderService.php
   public static function appointmentReminder(): AppointmentReminderService
   {
       return static::getSharedInstance('appointmentReminder');
   }
   ```

2. **External services** (notifications, payments, etc.)
   ```php
   public static function twilioService(): TwilioService
   {
       return static::getSharedInstance('twilio');
   }
   ```

---

## Filters.php - Middleware Chain

**Path:** `app/Config/Filters.php`

**File Type:** Configuration → Middleware/Filter Chain

**Purpose:**
Defines the order and rules for applying filters (middleware) to requests before they reach controllers.

**Execution Context:**
- Evaluated on every request
- Applied before routing to controller
- Can modify request/response

**Filter Chain:**

```php
public $filters = [
    'setup' => ['before' => ['App\Filters\SetupFilter']],
    'auth' => ['before' => ['App\Filters\AuthFilter']],
    'role' => ['before' => ['App\Filters\RoleFilter']],
    'cors' => ['before' => ['App\Filters\CorsFilter']],
    'security' => ['after' => ['App\Filters\SecurityHeaders']],
    'timezone' => ['before' => ['App\Filters\TimezoneDetection']],
    'ratelimit' => ['before' => ['App\Filters\PublicBookingRateLimiter']],
];
```

**Active Filters:**

| Filter | Purpose | Type | Runs |
|--------|---------|------|------|
| `SetupFilter` | Check setup complete | Before | Setup routes |
| `AuthFilter` | Check user logged in | Before | Protected routes |
| `RoleFilter` | Check user role (admin/provider/staff) | Before | Role-protected routes |
| `CorsFilter` | Add CORS headers | Before | API routes |
| `SecurityHeaders` | Add security headers | After | All responses |
| `TimezoneDetection` | Detect user timezone | Before | Dashboard |
| `PublicBookingRateLimiter` | Rate limit public booking | Before | Public booking |

**Filter Alias Mapping:**

```php
// In routes:
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);

// Means: Apply 'auth' filter => SetupFilter + AuthFilter
```

**Filter Logic Flow:**

```
Request
  ↓
[Before Filters Applied]
  ├─ SetupFilter - Verify setup complete
  ├─ AuthFilter - Verify user logged in
  ├─ RoleFilter - Verify user role
  ├─ CorsFilter - Add CORS headers
  └─ TimezoneDetection - Detect timezone
  ↓
Route to Controller
  ↓
[After Filters Applied]
  ├─ SecurityHeaders - Add security headers
  ↓
Response sent
```

**Custom Filters:**

All custom filters located in `app/Filters/`:

1. **AuthFilter.php**
   - Checks `session()->get('user_id')`
   - Redirects to login if not authenticated
   - Sets `$_SESSION['user']`

2. **RoleFilter.php**
   - Checks `session()->get('user_role')`
   - Verifies role against allowed roles
   - Returns 403 Forbidden if unauthorized

3. **SetupFilter.php**
   - Checks setup completion flag
   - Redirects to setup if incomplete

4. **TimezoneDetection.php**
   - Detects browser timezone
   - Stores in session for server-side use

5. **PublicBookingRateLimiter.php**
   - Prevents abuse of public booking
   - Uses IP address as key

**Status & Recommendations:**

| Status | Finding |
|--------|---------|
| ✅ Well-organized | Clear filter purposes |
| ✅ Proper ordering | Before/after filters in correct order |
| ✅ Security-focused | Auth, role, rate limiting in place |
| ✅ Recent addition | TimezoneDetection properly configured |

---

## Other Critical Configs

### Cache.php

**Purpose:** Cache configuration (Redis, File, Memcached)

**Current Setting:**
```php
public string $handler = 'file'; // Could be 'redis' for production
```

**Recommendation:** 
- Use Redis in production for better performance
- Document cache key prefixes
- Set appropriate TTLs for each cache type

---

### Security.php

**Purpose:** Security settings (tokenization, CSRF, XSS)

**Key Settings:**
- CSRF protection (enabled)
- Tokenizer (generates random tokens)
- Hash algorithm (bcrypt recommended)

**Status:** ✅ Properly configured

---

### Session.php

**Purpose:** Session storage and configuration

**Current Setting:**
```php
public string $driver = 'CodeIgniterSession';
public int $expiration = 7200; // 2 hours
```

**Consideration:** 
- Session expiration may be too long for security-sensitive app
- Consider shorter timeout (30 minutes) with session refresh

---

### Email.php

**Purpose:** Email configuration (SMTP, PHPMailer)

**Current Setting:**
```php
public string $protocol = 'smtp'; // or 'sendmail'
```

**Important for:**
- Password reset emails
- Appointment reminders
- Notifications

**Recommendation:**
- Use SendGrid, Mailgun, or AWS SES in production
- Test email delivery in staging
- Set up bounce handling

---

### Validation.php

**Purpose:** Validation rule definitions and error messages

**Usage:** 
```php
// In model or controller
$this->validate([
    'email' => 'required|valid_email|is_unique[xs_users.email]',
    'password' => 'required|min_length[8]',
]);
```

**Status:** ✅ Properly configured with custom rules

---

### Constants.php

**Purpose:** Global application constants

**Contains:**
```php
define('PROJECT_NAME', 'XScheduler');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'UTC');
// ... others
```

**Recommendation:**
- Keep constants minimal
- Use config files for complex settings
- Document all constants

---

## Configuration Audit Summary

| File | Status | Issues | Priority |
|------|--------|--------|----------|
| Routes.php | ✅ Good | Deprecate API V1 | 🟡 Medium |
| Database.php | ✅ Good | Strict mode in production | 🟡 Medium |
| App.php | 🟡 Fair | Environment variables, cookie security | 🟡 Medium |
| Services.php | ✅ Good | Could add more services | 🟢 Low |
| Filters.php | ✅ Good | Well-organized | ✅ No action |
| Cache.php | ✅ Good | Consider Redis in production | 🟢 Low |
| Security.php | ✅ Good | Well-configured | ✅ No action |
| Session.php | ✅ Good | Consider shorter timeout | 🟢 Low |
| Email.php | ✅ Good | Use managed service in production | 🟡 Medium |

---

## Next Steps

1. **Review and test** each configuration in development
2. **Create environment-specific configs** (dev, staging, production)
3. **Document all configuration changes** when making updates
4. **Create API migration guide** for V1 → current API
5. **Add configuration validation tests**

---

**Reference:** [Main Audit](./CODEBASE_AUDIT.md)

**Next Document:** [CONTROLLER DOCUMENTATION](./CODEBASE_AUDIT_CONTROLLERS.md)

# Redirect Subdirectory Fix

## Problem

When deployed in a subdirectory (e.g., `https://webscheduler.co.za/v50a/`), all `redirect()->to('/path')` calls resolved to `https://webscheduler.co.za/path`, stripping the subdirectory prefix. This affected:

- Setup completion redirects
- Login/logout redirects
- Post-create/update redirects (users, services, categories)
- Authentication filter redirects
- Role-based access control redirects

## Root Cause

CodeIgniter 4's `redirect()->to('/path')` sets the HTTP `Location` header to exactly `/path`. The browser resolves this as an absolute path from the domain root, bypassing any subdirectory the app is deployed in.

## Fix Applied

All `redirect()->to('/path')` calls changed to `redirect()->to(base_url('path'))`.

`base_url()` reads `app.baseURL` from `.env` and correctly prefixes the configured subdirectory path.

### Files Changed

#### Filters (4 files, 9 calls)
- `app/Filters/SetupFilter.php`
- `app/Filters/SetupAuthFilter.php`
- `app/Filters/RoleFilter.php`
- `app/Filters/AuthFilter.php`

#### Controllers (14 files, ~137 calls)
- `app/Controllers/Auth.php`
- `app/Controllers/Home.php`
- `app/Controllers/AppFlow.php`
- `app/Controllers/Dashboard.php`
- `app/Controllers/Setup.php`
- `app/Controllers/Services.php`
- `app/Controllers/UserManagement.php`
- `app/Controllers/Appointments.php`
- `app/Controllers/Profile.php`
- `app/Controllers/Scheduler.php`
- `app/Controllers/Notifications.php`
- `app/Controllers/CustomerManagement.php`
- `app/Controllers/Analytics.php`
- `app/Controllers/Help.php`

#### Helpers (1 file)
- `app/Helpers/setup_helper.php`

#### Views — JS fetch() calls (3 files, 9 calls)
- `app/Views/services/create.php` — `/services/categories`, `/services/store`
- `app/Views/services/edit.php` — `/services/categories`, `/services/update/`
- `app/Views/user-management/components/provider-locations.php` — `/api/locations` (5 fetch calls)

### Pattern

```php
// BEFORE (broken in subdirectory):
return redirect()->to('/auth/login');

// AFTER (works in any deployment path):
return redirect()->to(base_url('auth/login'));
```

```javascript
// BEFORE (broken in subdirectory):
fetch('/services/store', { ... })

// AFTER (works in any deployment path):
fetch('<?= base_url("services/store") ?>', { ... })
```

## Deployment Note

Ensure `app.baseURL` in `.env` includes the full path:
```
app.baseURL = 'https://webscheduler.co.za/v50a/'
```

## Date
2025-02-17

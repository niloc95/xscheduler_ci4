# URL Routing Fix — `/public/public/` Duplication

## Problem

Production deployments at `webscheduler.co.za/v43-mysql/` generated double `/public/public/` URL paths:

```
❌ https://webscheduler.co.za/v43-mysql/public/public/booking
✅ https://webscheduler.co.za/v43-mysql/booking
```

## Root Cause

Two mechanisms combined to create the duplication:

### 1. Root `.htaccess` rewrites all requests into `public/`

```apache
# webschedulr-deploy/.htaccess
RewriteRule (.*) public/$1 [L]
```

Every incoming request (e.g. `/booking`) is mapped to `public/booking` internally — this is correct CI4 subfolder deployment behaviour.

### 2. Routes were prefixed with `public/`

```php
# app/Config/Routes.php (BEFORE)
$routes->group('public/booking', ...);
$routes->group('public/my-appointments', ...);
```

When a user browsed to `/public/booking`, the `.htaccess` rewrite sent it to `public/public/booking`, which did not match any route.

### 3. JS hardcoded absolute `/public/booking` fetch paths

```javascript
// BEFORE — hardcoded
const response = await fetch(`/public/booking/calendar?${query}`);
```

These absolute paths bypassed `base_url()` and failed on subfolder deployments.

## Fix Applied

### Routes renamed (no `public/` prefix)

```php
# app/Config/Routes.php (AFTER)
$routes->group('booking', ['filter' => 'setup'], function($routes) { ... });
$routes->group('my-appointments', ['filter' => 'setup'], function($routes) { ... });
```

### JS uses dynamic base URL from context

A `bookingBaseUrl` property is passed from `PublicBookingService::buildViewContext()` via the SPA context payload:

```php
'bookingBaseUrl' => rtrim(base_url('booking'), '/'),
```

The JS reads it at startup:

```javascript
const bookingBase = context.bookingBaseUrl || '/booking';
```

All fetch calls now use `bookingBase` instead of hardcoded paths:

```javascript
await fetch(`${bookingBase}/calendar?${query}`);
await fetch(`${bookingBase}/slots?${query}`);
await fetch(bookingBase, { method: 'POST', ... });
```

### PHP `base_url()` references updated

| File | Before | After |
|---|---|---|
| `DashboardService.php` | `base_url('/public/booking')` | `base_url('/booking')` |
| `my-appointments.php` (×3) | `base_url('public/booking')` | `base_url('booking')` |

## Files Changed

| File | Change |
|---|---|
| `app/Config/Routes.php` | Route groups renamed |
| `app/Services/PublicBookingService.php` | Added `bookingBaseUrl` to context |
| `resources/js/public-booking.js` | Dynamic `bookingBase` for all fetch calls |
| `app/Services/DashboardService.php` | Updated `booking_url` |
| `app/Views/public/my-appointments.php` | Updated 3 `base_url()` calls |
| `app/Controllers/PublicSite/CustomerPortalController.php` | Updated doc comments |
| `webschedulr-deploy/` (mirror) | All matching changes applied |

## URL Map (After Fix)

| User Visits | `.htaccess` Rewrites To | CI4 Route Match |
|---|---|---|
| `/booking` | `public/booking` | `booking` group → `BookingController::index` |
| `/booking/calendar` | `public/booking/calendar` | `booking` group → `BookingController::calendar` |
| `/booking/slots` | `public/booking/slots` | `booking` group → `BookingController::slots` |
| `/my-appointments/{hash}` | `public/my-appointments/{hash}` | `my-appointments` group → `CustomerPortalController::index` |

## Deployment Notes

- No `.htaccess` changes required — the rewrite rules are correct.
- The `.env` file's `app.baseURL` should match the production root (or be empty for auto-detection).
- After deploying, clear the CI4 cache: `php spark cache:clear`.
- Ensure the compiled `public/build/assets/public-booking-*.js` is deployed alongside the PHP changes.

---
name: webscheduler-auth-rbac
description: WebScheduler authentication, authorization (RBAC), session management, route filters, and auth hardening (inactivity monitor, session ping, failed-login lockout). Use whenever you're touching login/logout flows, role checks, permissions, session reads/writes, route filter configuration, the `/auth` controller, or anything involving `xs_users.role`, `xs_user_roles`, or `session()->get('user')`. Triggers on phrases like "auth", "login", "permission", "role", "RBAC", "session", "filter", "admin only", "provider can", "staff cannot", "logged in", "inactivity", "lockout", or any work in `app/Controllers/Auth.php` or `app/Filters/`.
---

# WebScheduler — Authentication & Authorization Contract

## Operational Roles

- `admin`
- `provider`
- `staff`
- `customer`

## Multi-Role Storage Model

| Layer | Storage | Role |
| --- | --- | --- |
| Compatibility primary | `xs_users.role` | Legacy single-role fallback |
| Authoritative membership | `xs_user_roles` | Real role membership set |

`xs_user_roles` is the **authoritative** source. `xs_users.role` is the compatibility primary role only.

Backfilled from `xs_users.role` in migration `2026-04-08-000001_CreateUserRolesTable.php`.

## Session Contract at Login

Set by auth flow:

- `role` — primary compatibility role
- `roles` — full authoritative role array from `UserModel::getRolesForUser()`
- `active_role` — highest privilege role for UI context

**Read role membership from `roles` first. Use `role` only as fallback.**

## Canonical RBAC Pattern

Use this shape everywhere:

```php
$userRoles = $user['roles'] ?? [$user['role'] ?? ''];
$hasAccess = !empty(array_intersect($requiredRoles, $userRoles));
```

**Never make authorization decisions from `xs_users.role` alone.**

**Fixed (2026-05-20):** `UserModel::canManageUser()` and `canViewUser()` previously used `$user['role'] === 'x'` comparisons — now use `UserModel::getRolesForUser(int $userId)` with `in_array(..., true)`. Any new authorization logic in `UserModel` must follow the same pattern.

## Session Write Contract (v140+ Mandatory)

Any mid-session update to session user payload **must** preserve existing role context.

**Required pattern:**

```php
$currentUser = session()->get('user') ?? [];
session()->set('user', array_merge($currentUser, [
    // only fields being updated
]));
```

Only the **initial login path** may write a full user array from scratch.

A direct `session()->set('user', [...])` overwrite mid-session is a regression and will be caught by the pre-merge grep check (see `rules` skill).

## Route Filter Contract

Primary filters:

- `setup`
- `auth`
- `role:admin`
- `role:admin,provider`
- `role:admin,provider,staff`

## Auth Hardening Contract (added 2026-05-12)

### Inactivity Warning Modal

File: `resources/js/modules/auth/inactivity-monitor.js`

- Tracks user activity events (mouse / keyboard / scroll / touch).
- After **115 minutes** of inactivity (`SESSION_MS 7200s − WARNING_MS 300s`), shows a modal with a 5:00 countdown.
- **"Stay Logged In"** → `GET /auth/ping` → hides modal, resets the 115-min clock.
- **"Log Out"** → redirects to `/auth/logout`.
- Countdown reaches 0:00 → redirects to `/auth/login`.
- Exported as `initInactivityMonitor()`, called from `initializeComponents()` in `app.js`.
- SPA-safe: removes listeners on `spa:leaving` to prevent double-binding.

### Session Ping Endpoint

- Route: `GET /auth/ping` → `Auth::ping()` (filter: `auth`)
- Returns `{"ok": true, "expires_in": 7200}` for logged-in sessions; `401` otherwise.
- Touching the session triggers CI4's `$timeToUpdate` (900s) regeneration, sliding the session cookie expiry.

### Failed-Login Lockout

- CI4 Throttler (Token Bucket) — already in vendor, no new dependencies.
- Guard is the **first statement** in `Auth::attemptLogin()`.
- Limit: 5 attempts per 15 minutes (900s) per IP, keyed as `login_{md5(ip)}`.
- On lockout:
  - AJAX → HTTP 429 + JSON
  - Form submit → redirect with `lockout_error` + `lockout_wait` flash
- On successful login: `$throttler->remove($ipKey)` resets the bucket.
- Login view renders `lockout_error` flash as a lock-icon error block with wait time in minutes.

## User Management Multi-Role Rules

- Form model uses `roles[]` checkboxes.
- Persist role membership to `xs_user_roles`.
- Keep `xs_users.role` as compatibility primary role.
- User list and APIs must render/return authoritative `roles[]`.

## Customer vs User Domain Split

- `xs_users` — internal login users (admin / provider / staff / customer accounts)
- `xs_customers` — booking customers (people who book appointments)
- `xs_appointments.customer_id` → `xs_customers.id`

These are **two distinct identity tables**. Do NOT conflate them. New logic should never use the deprecated appointment `user_id` linkage.

## Staff Scope Contract

Canonical source: `ProviderStaffModel::getProvidersForStaff($userId, 'active')`

When extracting provider IDs:

```php
array_column($rows, 'id')
```

**Preferred appointment context key:** `provider_id`

## Provider Scope Contract (Customer Visibility)

Use `CustomerAppointmentService` for all provider/staff customer scoping:

- `resolveCustomerIdsForProvider(int $providerUserId)`
- `resolveCustomerIdsForStaff(int $staffUserId)`
- `getProviderIdsForStaff(int $staffUserId)`

These methods enforce the rule that staff users only see customers belonging to providers they're assigned to via `xs_provider_staff_assignments`. Do NOT roll your own scoping query in controllers.

## Auth Email Channel

Password reset emails use `NotificationCatalog::BUSINESS_ID_DEFAULT` (value `1`) as `$businessId`. `Auth::sendResetEmail()` renders the view, then calls `MailerService::send()`. Auth has no knowledge of SMTP configuration.

See the `notifications` skill for the full `MailerService` transport contract.

## Profile Surface Notes

- `/profile` is a live account surface backed by `App\Services\ProfilePageService`. Do not reintroduce placeholder summary cards or fake recent activity.
- Profile mutations must preserve session role context via `array_merge` and write audit-log events for `user_updated`, `password_changed`, `profile_photo_updated`, and `notification_preferences_updated`.
- Provider and staff notification preferences edited from `/profile` must persist to `xs_users.notify_on_appointments`.

## Pre-Merge Grep Checks (Auth-Related)

Always run these before merging auth-related changes:

```bash
# Detect accidental single-role auth checks
rg "\$user\['role'\]\s*===|\$currentUser\['role'\]\s*==" app/ resources/

# Detect direct provider-role SQL assumptions
rg "role\s*=\s*'provider'|WHERE\s+role\s*=\s*'provider'" app/

# Detect direct session user overwrites without merge
rg "session\(\)->set\('user',\s*\[" app/
```

Any result must be reviewed and justified or fixed.

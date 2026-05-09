# Role-Based Access Control

## Roles

Three operational roles exist for authenticated login users. Customers are not login users — they exist in `xs_customers` and are never assigned a session role.

| Role | Identity | Access scope |
|---|---|---|
| `admin` | System owner / operator | Full access to all data and settings |
| `provider` | Service provider | Own appointments, schedule, staff, and services only |
| `staff` | Staff member assigned to a provider | Appointments for their assigned providers only |

---

## Storage Model — Two Layers

Role membership is stored in two places that serve different purposes:

| Layer | Table / key | Role |
|---|---|---|
| **Authoritative membership** | `xs_user_roles` (rows: `user_id`, `role`) | Source of truth for all RBAC decisions |
| **Compatibility primary** | `xs_users.role` (single string column) | Legacy fallback; kept in sync at write time |

A user may have multiple rows in `xs_user_roles` (e.g. admin + provider). The compatibility column holds the highest-privilege role for backward compatibility.

**Never make authorization decisions from `xs_users.role` alone.** It may be stale for multi-role users.

---

## Session Contract at Login

Set by the auth flow when a user logs in successfully:

| Key | Type | Value |
|---|---|---|
| `isLoggedIn` | `bool` | `true` |
| `user_id` | `int` | `xs_users.id` — top-level session key |
| `user['name']` | `string` | Display name |
| `user['email']` | `string` | Login email |
| `user['role']` | `string` | Primary compatibility role from `xs_users.role` |
| `user['roles']` | `string[]` | Full authoritative role array from `UserModel::getRolesForUser()` |
| `user['active_role']` | `string` | Highest-privilege role for UI context (admin > provider > staff) |

`user['id']` is **not** written into the session `user` array. Code that needs the current user ID reads the top-level `session('user_id')`. `AuthorizationService::getProviderId()` reads `$user['id'] ?? session()->get('user_id')` — when called with the session user array the first operand is always null and it falls through to `session('user_id')`.

Read role membership from `user['roles']` first. Use `user['role']` only as fallback.

**Mid-session session writes must use `array_merge`** to preserve role context:

```php
session()->set('user', array_merge(session()->get('user') ?? [], [
    // only fields being updated
]));
```

Only the initial login path may write a full user array from scratch.

---

## Canonical RBAC Pattern

Use this shape everywhere a role check is needed in service or controller code:

```php
$userRoles = $user['roles'] ?? [$user['active_role'] ?? $user['role'] ?? ''];
$hasAccess = !empty(array_intersect($requiredRoles, $userRoles));
```

---

## UserModel — Role Methods

### `getRolesForUser(int $userId): array`

Returns all roles assigned to the user from `xs_user_roles`. Falls back to `[$user['role']]` from `xs_users` if the table is unavailable (migration not yet run).

### `whereHasRole(string $role): static`

Canonical method for querying users by role. Uses a subquery against `xs_user_roles`. Use this instead of `->where('role', ...)` on `xs_users`:

```php
$providers = (new UserModel())->whereHasRole('provider')->findAll();
```

### `getUsersByRole(string $role): array`

Calls `whereHasRole()` and filters by active status. Schema-safe: checks for `is_active` or `status` column.

---

## AuthorizationService

`app/Services/AuthorizationService.php` — centralized server-side permission enforcement. Injected into controllers and services that need authorization decisions.

### Role constants

```php
AuthorizationService::ROLE_ADMIN    // 'admin'
AuthorizationService::ROLE_PROVIDER // 'provider'
AuthorizationService::ROLE_STAFF    // 'staff'
```

### `resolveRole(string|array $userRole): string`

Accepts either a single role string or an array. When an array is passed, returns the highest-privilege role present:

```
admin > provider > staff
```

If none match, returns `'staff'` (least privilege). All public methods call `resolveRole()` internally, so they all accept `string|array`.

### `getUserRole(?array $user): string`

Reads `$user['active_role']` first, then `$user['role']`. Normalizes legacy `'owner'` value to `'admin'`. Defaults to `'staff'` when user is null.

### `getProviderId(?array $user): ?int`

Checks `$user['roles']` for `'provider'` membership. Returns the provider ID via `$user['id'] ?? session()->get('user_id')`. Since `user['id']` is not written into the session user array, this always resolves to `session('user_id')` in practice. Returns `null` when the user is not a provider.

### `getProviderScope(string|array $userRole, ?int $providerId, ?array $user): int|array|null`

Returns the data scope for filtering. Used by `DashboardService` and other services:

| Role | Return value | Effect |
|---|---|---|
| `admin` | `null` | No filter — sees all data |
| `provider` | `int` (providerId) | Restricted to own data |
| `staff` | `int[]` of assigned provider IDs from `xs_provider_staff_assignments` | Restricted to assigned providers; `[]` when unassigned |

### Permission methods

| Method | Who passes |
|---|---|
| `canViewDashboardMetrics()` | admin, provider, staff |
| `canManageAppointment()` | admin always; provider for own appointments; staff for any visible appointment |
| `canViewSettings()` | admin only |
| `canManageUsers()` | admin only |
| `canViewBookingStatus()` | admin only |
| `canViewReports()` | admin, provider |
| `canViewAlerts()` | admin, provider, staff |

### `enforce(bool $authorized, string $message): void`

Throws `RuntimeException` when `$authorized` is false. Use for hard enforcement gates:

```php
$this->authService->enforce(
    $this->authService->canViewSettings($userRole),
    'Access denied'
);
```

---

## permissions_helper.php

`app/Helpers/permissions_helper.php` — loaded automatically by `BaseController` or manually with `helper('permissions')`.

### Functions

| Function | Returns | Notes |
|---|---|---|
| `current_user_role(): ?string` | `string\|null` | Returns `active_role` with fallback to `role`. |
| `current_user_id(): ?int` | `int\|null` | Reads `session('user_id')`. |
| `current_business_id(?int $default): int` | `int` | See business ID resolution below. |
| `has_role(string\|array $roles): bool` | `bool` | Reads `user['roles']` first, falls back to `active_role`/`role`. §4.4 canonical pattern. |
| `has_permission(string\|array $perms): bool` | `bool` | Delegates to `UserPermissionModel`. Checks role-default permissions first (from `ROLE_PERMISSIONS` constant), then custom entries in `xs_users.permissions` JSON column. |
| `is_admin(): bool` | `bool` | `has_role('admin')` |
| `is_provider(): bool` | `bool` | `has_role('provider')` |
| `is_staff(): bool` | `bool` | `has_role('staff')` |
| `can_manage_users(): bool` | `bool` | `has_permission('user_management')` |
| `can_manage_settings(): bool` | `bool` | `has_permission('system_settings')` |
| `can_create_role(string $role): bool` | `bool` | Checks `create_admin`, `create_provider`, or `create_staff` permission. |
| `role_badge_class(string $role): string` | `string` | Bootstrap badge class (legacy surfaces). |
| `get_role_badge_tailwind_class(string $role): string` | `string` | Tailwind class — use this for all current views. |
| `role_icon(string $role): string` | `string` | FontAwesome icon class for role. |

`is_customer()` does not exist. Customers are not login users.

### `current_business_id()` — Resolution Order

Reads candidates in strict priority order, returns the first positive integer found:

1. `GET business_id` or `GET businessId`
2. `POST business_id` or `POST businessId`
3. `session('business_id')` or `session('active_business_id')`
4. `session('user')['business_id' | 'active_business_id' | 'businessId' | 'activeBusinessId']`
5. `$default ?? NotificationCatalog::BUSINESS_ID_DEFAULT` (value `1`)

Result is always `max(1, resolved_value)`. Services that scope data to a business should delegate through this function via a protected `resolveBusinessId()` method.

---

## Filters

`app/Config/Filters.php` registers all filter aliases. The following are custom to this app:

| Alias | Class | Purpose |
|---|---|---|
| `auth` | `App\Filters\AuthFilter` | Session authentication. Redirects to login or returns 401 JSON for API/AJAX. |
| `role` | `App\Filters\RoleFilter` | Role check after auth. Accepts comma-separated roles: `role:admin,provider`. Also supports `role:permission:manage_users`. |
| `setup` | `App\Filters\SetupFilter` | Verifies setup wizard has been completed. |
| `setup_auth` | `App\Filters\SetupAuthFilter` | Combined setup + auth check. |
| `api_auth` | `App\Filters\ApiAuthFilter` | Session auth for API endpoints. |
| `api_cors` | `App\Filters\CorsFilter` | CORS headers for API cross-origin requests. |
| `timezone` | `App\Filters\TimezoneDetection` | Reads `X-Client-Timezone` and stores as session hint. |
| `public_rate_limit` | `App\Filters\PublicBookingRateLimiter` | Rate limiting on public booking endpoints. |
| `request_context` | `App\Filters\LogRequestContext` | Request logging context. |

### Global filter chain (every request)

**Before:**
1. `request_context` — always
2. `timezone` — except `setup` and `setup/*` routes
3. `csrf` — except `api/*` and `setup/*` routes

**After:**
1. `securityheaders` — sets `X-Frame-Options`, HSTS, `Referrer-Policy`, etc.

`securityheaders` uses the custom `App\Filters\SecurityHeaders` class, not CI4's built-in `SecureHeaders`.

### Route filter syntax

```php
// Admin only
['filter' => 'role:admin']

// Admin or provider
['filter' => 'role:admin,provider']

// Admin, provider, or staff
['filter' => 'role:admin,provider,staff']

// Permission-based
['filter' => 'role:permission:manage_users']
```

RoleFilter reads `session('user')['roles']` first, falling back to `session('user')['role']`. A user passes if **any** of their assigned roles matches any required role.

---

## `xs_users` Table — Auth-Relevant Columns

| Column | Type | Notes |
|---|---|---|
| `id` | int PK | Used as `provider_id` when role is provider |
| `role` | varchar | Compatibility primary role; keep in sync |
| `status` | varchar | `'active'` / `'inactive'` / `'suspended'` — schema-variable |
| `is_active` | tinyint | Alternative active flag — schema-variable |
| `notify_on_appointments` | tinyint | Controls whether this user receives appointment notification emails |
| `permissions` | JSON | Custom per-user permissions read by `UserPermissionModel` |
| `color` | varchar | Provider calendar color (hex) |
| `provider_id` | int | Denormalized; staff members may have their primary provider stored here |

Both `status` and `is_active` may be present. `UserModel` handles both via schema-safe detection at runtime.

---

## Related

- `app/Database/Migrations/2026-04-08-000001_CreateUserRolesTable.php` — creates `xs_user_roles`, backfills from `xs_users.role`
- `app/Models/UserPermissionModel.php` — `has_permission()` / `has_any_permission()`. Reads `xs_users` (same table as `UserModel`). Permissions are the union of role defaults from `ROLE_PERMISSIONS` constant and custom entries from `xs_users.permissions` (JSON column). No separate permissions table exists.
- `app/Models/ProviderStaffModel.php` — `getProvidersForStaff()` used by `getProviderScope()`
- `app/Controllers/Auth.php` — writes the session contract at login
- `Agent_Context_v2.md §4` — Authentication and Authorization Contract (canonical rules)
- `docs/user-management/ACCESS_CONTROL_MATRIX.md` — per-feature access matrix

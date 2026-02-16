# User Management — Audit & Fix Report

**Date:** 2026-02-13  
**Scope:** "Error loading users" / 404 on `/api/users` and `/api/user-counts` + full audit of all user-management views

---

## Root Cause

The JavaScript `baseUrl()` function in `user-management/index.php` used `window.location.origin` to build API URLs:

```js
// BEFORE (broken for subdirectory deployments)
function baseUrl(p) {
    return `${window.location.origin.replace(/\/$/,'')}/${p}`;
}
```

On production at `webscheduler.co.za/v43-mysql/`, `window.location.origin` returns only `https://webscheduler.co.za`, so all API calls hit:
- `https://webscheduler.co.za/api/users` → **404**
- `https://webscheduler.co.za/api/user-counts` → **404**

Instead of the correct:
- `https://webscheduler.co.za/v43-mysql/api/users`
- `https://webscheduler.co.za/v43-mysql/api/user-counts`

### Fix

Inject CI4's `base_url()` directly into JS:

```js
// AFTER (works for any deployment path)
const APP_BASE = '<?= rtrim(base_url(), "/") ?>/';
function baseUrl(p) { return APP_BASE + p; }
```

---

## Files Changed

### Controller: `app/Controllers/UserManagement.php`

| Method | Change |
|---|---|
| `index()` | Wrapped `getUsersBasedOnRole()` + `getUserStatsBasedOnRole()` in try-catch with safe defaults |
| `apiCounts()` | Wrapped `getUserStatsBasedOnRole()` in try-catch |
| `apiList()` | Wrapped `getUsersBasedOnRole()` in try-catch |
| `enrichUsersWithAssignments()` | Wrapped both `GROUP_CONCAT` queries (provider + staff) in try-catch — MySQL-only syntax crashes on SQLite3 |

### Views

| File | Issue | Fix |
|---|---|---|
| `index.php` | JS `baseUrl()` ignores subdirectory path | Inject PHP `base_url()` as `APP_BASE` constant |
| `create.php` | Debug `console.log` statements in form submit handler | Removed — not appropriate for production |
| `edit.php` | Leading blank line before `<?php` | Removed |
| `customers.php` | `esc()` missing `'` → `&#39;` escape | Added to character map |

---

## Other Console Errors Explained

| Error | Cause | Action |
|---|---|---|
| `beacon.min.js ERR_BLOCKED_BY_CLIENT` | Cloudflare analytics blocked by ad-blocker | No action — external script |
| `No providers to use, returning empty slots` | No provider users created yet | Expected — create a provider user first |
| `Customer search elements not found` | SPA navigated to page without customer search DOM | Expected during SPA navigation |

---

## Deployment Checklist

1. Ensure production `.env` has `database.default.DBDriver = MySQLi`
2. Upload updated `app/Controllers/UserManagement.php`
3. Upload updated `app/Views/user-management/` (all files)
4. Clear cache: `php spark cache:clear`

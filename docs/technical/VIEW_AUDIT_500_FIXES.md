# View Audit — Services, Settings, Notifications

**Date:** 2026-02-13  
**Scope:** 500 errors on Services, Settings, Notifications + full quality audit

---

## Root Cause of 500 Errors

All three 500 errors traced to the same root cause:

```
SQLite3Exception: Unable to prepare statement: no such table: xs_notification_delivery_logs
```

The **production `.env` was misconfigured** with `database.default.DBDriver = SQLite3` instead of `MySQLi`. Several queries use MySQL-specific syntax (`GROUP_CONCAT ... SEPARATOR`, `DATE_SUB`, `NOW()`, `INTERVAL`) that is incompatible with SQLite3.

### Resolution

1. **Controllers wrapped in try-catch** — all three controllers now gracefully degrade if notification tables are missing or the DB driver doesn't support certain syntax.
2. **`.env.example` updated** — added prominent warning that `SQLite3` is NOT supported in production.

---

## Files Changed

### Controllers (try-catch resilience)

| File | Change |
|---|---|
| `app/Controllers/Notifications.php` | `getNotifications()` — delivery log query and queue query each wrapped in try-catch |
| `app/Controllers/Settings.php` | All notification data loading (`getRules`, `getIntegrationStatus`, `findAll` on delivery logs) wrapped in single try-catch with safe defaults |
| `app/Controllers/Services.php` | `findWithRelations()`, `withServiceCounts()`, `getStats()` each wrapped in try-catch with fallback queries |

### Views (quality fixes)

| File | Line | Issue | Fix |
|---|---|---|---|
| `services/index.php` | 226 | Orphaned `</td>` — broken HTML | Removed |
| `services/index.php` | 25,38,51,64,86 | `$stats` keys accessed without null guards | Added `?? 0` fallback |
| `notifications/index.php` | 1–10 | Missing safe defaults for `$notifications`, `$unread_count`, `$filter` | Added `?? []`, `?? 0`, `?? 'all'` |
| `notifications/index.php` | 130–166 | Dynamic Tailwind classes (`text-<?= $color ?>-600`) not purge-safe | Replaced with class lookup maps |
| `notifications/index.php` | 143–166 | 8-branch if/elseif chain for icons | Replaced with `$iconMap` array |
| `notifications/index.php` | all | `material-symbols-rounded` inconsistent with rest of app | Changed to `material-symbols-outlined` |
| `notifications/index.php` | 221 | `deleteNotification()` used GET for destructive action | Changed to POST with CSRF token |
| `settings.php` | 1893 | JS variable shadowing: `catch (error)` shadows DOM `error` element | Renamed to `catch (saveErr)` |
| `settings.php` | 27 | `material-symbols-rounded` (1 instance among 28 `outlined`) | Changed to `material-symbols-outlined` |

### Deploy Config

| File | Change |
|---|---|
| `webschedulr-deploy/.env.example` | Added warning: `DBDriver MUST be MySQLi for production` |

---

## Audit Summary

| Category | services/index.php | notifications/index.php | settings.php |
|---|---|---|---|
| Inline CSS | 0 | 0 | 0 |
| Broken HTML | 1 (fixed) | 0 | 0 |
| Missing null guards | 5 (fixed) | 3 (fixed) | 0 |
| Dynamic Tailwind risk | 0 | 1 (fixed) | 0 |
| Icon inconsistency | 0 | All (fixed) | 1 (fixed) |
| JS bugs | 0 | 1 (fixed) | 1 (fixed) |

### Known remaining items (not changed — too risky)

- `settings.php` is 3,034 lines — should eventually be split into partials
- ~1,500 lines of inline JS in `settings.php` — should be extracted to a separate file
- Mixed `base_url()` / `site_url()` in `services/index.php` — functionally equivalent, cosmetic only

---

## Deployment Checklist

1. Ensure production `.env` has `database.default.DBDriver = MySQLi`
2. Run migrations: `php spark migrate -n App`
3. Copy updated `app/Controllers/` and `app/Views/` to production
4. Clear cache: `php spark cache:clear`

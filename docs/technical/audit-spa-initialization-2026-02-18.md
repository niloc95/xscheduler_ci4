# Technical Audit: SPA Initialization & Refresh-to-Work Issues

**Date:** 2026-02-18  
**Trigger:** Settings → General → Edit button not working until manual browser refresh after fresh setup  

---

## Executive Summary

A comprehensive audit of the frontend (JavaScript/SPA), backend (Controllers/Routes/Filters), and codebase quality identified **5 HIGH severity** and **14 MEDIUM severity** issues. The "refresh to work" pattern was caused by multiple compounding factors in the SPA script re-execution mechanism, authentication filter responses, and stale event listener registration.

---

## 1. Root Causes Found & Fixed

### 1.1 SPA Script Execution Lacks Error Isolation — `resources/js/spa.js`
| | |
|---|---|
| **Issue** | The `scripts.forEach(replaceWith)` loop had no try/catch. If any script in the settings page's 8 script blocks threw an error, subsequent scripts (including the main init) would not execute. |
| **Impact** | Edit button, tab forms, and all settings functionality fail silently on SPA navigation. |
| **Fix** | Wrapped each `replaceWith` call in try/catch with `console.error` logging. |

### 1.2 `type="module"` Script in SPA Content — `app/Views/settings.php` L2764
| | |
|---|---|
| **Issue** | `<script type="module">` with `import` inside SPA-swapped content. Module scripts created via `createElement('script')` with `type="module"` execute **asynchronously/deferred**, and static `import` statements in dynamically-created module scripts may fail in some browsers. |
| **Impact** | Time format handler doesn't initialize on SPA navigation; requires full page refresh. |
| **Fix** | Replaced static `import` with dynamic `import()` in a regular (non-module) script. Removed stale `__settingsTimeFormatSpaListenerBound` global flag. |

### 1.3 Stale `spa:navigated` Listener — `app/Views/settings.php` L2058
| | |
|---|---|
| **Issue** | `window.__settingsSpaListenerRegistered` prevented re-registration of the `spa:navigated` listener. While the listener persisted, it referenced a stale closure from the first script execution. On subsequent SPA visits, the direct `initSettingsApi()` call might not find DOM elements if timing was off, and the backup listener was stale. |
| **Impact** | Settings initialization unreliable on second+ SPA visits. |
| **Fix** | Replaced the `__settingsSpaListenerRegistered` + `spa:navigated` pattern with `xsRegisterViewInit()` — the SPA's built-in initialization system that runs registered callbacks on every navigation. Added `requestAnimationFrame` safety net for edge-case timing issues. |

### 1.4 AuthFilter Redirects AJAX Requests — `app/Filters/AuthFilter.php` L81
| | |
|---|---|
| **Issue** | When session expires, `AuthFilter::before()` always returns an HTTP 302 redirect, even for AJAX/XHR requests. JavaScript `fetch()` follows the redirect silently and receives the login page HTML instead of a parseable error. |
| **Impact** | After session timeout, all SPA features break silently — buttons stop working, forms don't submit, no error shown to user. Only a manual refresh (which triggers full login redirect) resolves it. |
| **Fix** | Added AJAX detection: returns JSON 401 `{"error":{"message":"Session expired","code":"unauthenticated"}}` for AJAX/JSON requests. Regular requests still get the redirect. Also added 401 detection in `spa.js` `fetchPage()` to force a full-page reload on session expiry. |

### 1.5 Toggle Component Event Listener Stacking — `app/Views/components/ui/toggle.php` L125
| | |
|---|---|
| **Issue** | Each toggle's inline `<script>` adds a `change` listener without any guard. On every SPA navigation to a page with toggles, new listeners stack — causing the handler to fire multiple times. |
| **Impact** | Toggle text updates fire N times (N = number of SPA visits). Mostly cosmetic but degrades performance over time. |
| **Fix** | Added `dataset.toggleBound` guard and wrapped in IIFE with null check. |

---

## 2. Additional Findings (Not Fixed — Documented for Future)

### 2.1 Frontend — HIGH Severity

| ID | File | Issue |
|----|------|-------|
| F-1 | `app/Views/appointments/index.php` L258 | `<script type="module" src="...">` in SPA content — same module re-execution risk as settings. Calendar initialization may fail on SPA nav. |
| F-2 | `app/Views/settings.php` | 8 inter-dependent script blocks with execution order dependency. Consider consolidating into 1-2 blocks. |

### 2.2 Frontend — MEDIUM Severity

| ID | File | Issue |
|----|------|-------|
| F-3 | `app/Views/styleguide/index.php` L255 | Registers `spa:navigated` listener without dedup guard — stacks on each visit |
| F-4 | `app/Views/styleguide/components.php` L200 | Same listener stacking issue |
| F-5 | `app/Views/services/create.php` L106 | IIFE without re-init guard — duplicate listeners on SPA revisit |
| F-6 | `app/Views/services/edit.php` L94 | Same as F-5 |
| F-7 | `app/Views/analytics/index.php` L201 | Chart event listeners not guarded against duplicates |
| F-8 | `app/Views/appointments/index.php` L250 | `window.__calendarPrototype` data persists across SPA navigations — stale data risk |

### 2.3 Backend — HIGH Severity

| ID | File | Issue |
|----|------|-------|
| B-1 | `app/Config/Filters.php` L127 | CSRF disabled globally (`// 'csrf'`). Only 2 routes have explicit CSRF. |
| B-2 | `app/Controllers/Settings.php` L538 | `save()` always returns redirect, never JSON. Frontend JS using `fetch()` to this endpoint gets unusable response. Mitigated because the JS currently uses the API endpoint (`/api/v1/settings`). |

### 2.4 Backend — MEDIUM Severity

| ID | File | Issue |
|----|------|-------|
| B-3 | `app/Config/Routes.php` | Inconsistent filter patterns — some groups have `setup` at group + `auth` per-route, others use `role:x` at group level |
| B-4 | `app/Config/Routes.php` L242-277 | Several API endpoints (`/api/slots`, `/api/book`, `/api/customers/*/appointments/*`) have no `api_auth` filter |
| B-5 | `app/Controllers/Api/V1/Settings.php` L255 | No CSRF token refresh in API responses — dormant risk if CSRF re-enabled |
| B-6 | `app/Controllers/Auth.php` L117 | No `session()->regenerate()` on login — session fixation risk |
| B-7 | `app/Controllers/Auth.php` L303 | `session()->destroy()` then `setFlashdata()` — flashdata may be lost |

### 2.5 Codebase Quality

| ID | Category | Finding |
|----|----------|---------|
| Q-1 | Orphaned views | 22 view files in `app/Views/components/` appear unreferenced by any controller or parent view (likely scaffolded but unused UI components) |
| Q-2 | Inline CSS | 4 view files use `style=` — all for dynamic values (PHP-computed colors, widths). Legitimate usage. |
| Q-3 | Redundant auth checks | 4+ controllers manually check `session()->get('isLoggedIn')` despite having `auth` filter on routes |
| Q-4 | Debug logging | `Settings::localUploadLog()` writes debug logs on every save — should be removed for production |

---

## 3. Fixes Applied (This Commit)

| File | Change |
|------|--------|
| `resources/js/spa.js` | Added try/catch around script `replaceWith` execution; added 401 detection in `fetchPage()` for session expiry |
| `app/Views/settings.php` | Replaced `__settingsSpaListenerRegistered` + `spa:navigated` with `xsRegisterViewInit()`; added rAF safety net; converted `type="module"` to dynamic `import()`; fixed `DOMContentLoaded` in flash message script |
| `app/Filters/AuthFilter.php` | Added AJAX-aware 401 JSON response for expired sessions |
| `app/Views/components/ui/toggle.php` | Added re-init guard to prevent event listener stacking |
| `app/Config/Routes.php` | Added `['filter' => ['setup', 'auth']]` to services route group |

---

## 4. Recommended Future Work

1. ~~**Consolidate settings.php scripts** — Merge 8 script blocks into 1-2, eliminating cross-block dependencies~~ ✅ Done (commit `a4ec63b`)
2. ~~**Fix calendar module script** — Convert `appointments/index.php` `type="module"` to dynamic import pattern~~ ✅ Done — moved from dead `extra_js` section to `scripts`, converted to dynamic `import()`
3. ~~**Enable CSRF globally** — Re-enable CSRF in Filters.php and ensure all AJAX responses include fresh tokens~~ ✅ Done — enabled with `except: api/*`, set `regenerate=false` for SPA, added `<meta>` tags to layout
4. ~~**Session regeneration** — Add `session()->regenerate()` after login in Auth controller~~ ✅ Done
5. ~~**Orphaned views cleanup** — Review and remove unused component views in `app/Views/components/`~~ ✅ Done — removed 21 orphaned files (all `dashboard/*`, `ui/*` except `flash-messages.php`, `page-header.php`)
6. ~~**Add re-init guards** — Add `dataset.initialized` guards to services/create.php, services/edit.php, analytics/index.php~~ ✅ Done

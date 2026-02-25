# SPA & Manual Refresh Audit Report

**Date:** 2025-02-17  
**Scope:** Complete technical audit of why ~99% of the application requires manual browser refresh to reflect changes.

---

## Executive Summary

The application uses a custom lightweight SPA system (`spa.js`) that intercepts link clicks and form submissions, swapping only `#spa-content` while preserving the header/sidebar/footer. The audit identified **5 critical bugs**, **7 high-priority issues**, and **12 medium/low improvements** across the codebase.

## 2026-02-24 Update — Scheduler SPA Sync

- Scheduler now emits a `scheduler:date-change` event on view/date changes so summary widgets refresh without manual reloads.
- Scheduler initialization honors `initialView` and `initialDate` passed from the appointments view.
- Appointments view re-enables Day/Week/Month toggles and week view “+X more” now expands hidden items.
- Settings saves trigger scheduler settings refresh and re-render to avoid stale time format/business hours after SPA navigation.

### Root Cause Categories

| Category | Count | Impact |
|---|---|---|
| Critical SPA bug (blanks page on non-JSON responses) | 1 | Entire page goes blank after certain form submissions |
| Controllers missing AJAX JSON responses | 4 | SPA can't process redirect responses properly |
| Views with broken init patterns (won't re-initialize on SPA nav) | 3 | Interactive features don't work after SPA navigation |
| Hard redirects bypassing SPA (`window.location.href`) | 5 | Full page reload interrupts SPA flow |
| Forced reloads (`location.reload()`) | 3 | Full page reload discards SPA state |
| Event listener accumulation on SPA nav | 4 | Memory leaks, handlers fire multiple times |
| Duplicate implementations | 3 categories | Maintenance risk, inconsistent behavior |

---

## CRITICAL BUGS

### C1. Double `res.text()` Consumption — Page Goes Blank

**File:** `resources/js/spa.js` lines 215-226  
**Impact:** When any form submission returns HTML instead of JSON, the SPA content area goes **completely blank**.

```javascript
// BUG: res.text() is called twice — body stream can only be read once
try {
    const text = await res.text();   // ← reads entire body
    data = JSON.parse(text);
} catch (e) {
    const html = await res.text();   // ← RETURNS "" (already consumed)
    el.innerHTML = html;             // ← sets content to empty string
}
```

**When this triggers:** Any controller method that returns `redirect()` instead of JSON for AJAX requests. The fetch API follows the 302 redirect transparently, receives HTML, `JSON.parse()` throws, then `res.text()` returns empty string.

**Fix:** Use the `text` variable already in scope:
```javascript
catch (e) {
    el.innerHTML = text; // already have the HTML from first res.text()
}
```

### C2. Controllers Missing AJAX JSON Responses

These controller methods always return `redirect()`, even for XHR requests from the SPA. Combined with bug C1, this causes the page to blank:

| Controller | Method | Line | Form Location |
|---|---|---|---|
| `UserManagement` | `delete()` | L538 | user-management/index.php delete buttons |
| `Profile` | `changePassword()` | L185 | profile/index.php password form |
| `Profile` | `uploadPicture()` | L246 | profile/index.php picture upload |
| `Help` | `submitTicket()` | L219 | help/index.php ticket form |

**Fix:** Add `$this->request->isAJAX()` check to each method, returning `{success, message, redirect}` JSON for XHR requests.

### C3. `window.XSNotify` Never Defined — Silent Toast Failures

**Files:**
- `app/Views/settings.php` L1894 — `window.XSNotify?.toast(...)`
- `app/Views/user-management/components/staff-providers.php` L132
- `app/Views/user-management/components/provider-staff.php` L152

**Impact:** Toast notifications for settings saves, staff assignments, and provider assignments silently fail. Users get no feedback that their action succeeded or failed, making it appear that nothing happened.

**Fix:** Either define `window.XSNotify` or replace calls with the existing `xs:flash` event system:
```javascript
document.dispatchEvent(new CustomEvent('xs:flash', {
    detail: { type: 'success', message: 'Saved successfully' }
}));
```

---

## HIGH-PRIORITY ISSUES

### H1. Views With Non-SPA-Compatible Initialization

These views use `DOMContentLoaded` or `window.load` which only fires once — after SPA navigation they never re-initialize:

| View | Line | Pattern | What Breaks |
|---|---|---|---|
| `app/Views/analytics/index.php` | L207 | `window.addEventListener('load', ...)` | Revenue charts don't render after SPA nav |
| `app/Views/profile/index.php` | L460 | `DOMContentLoaded` only | Profile tab switching breaks after SPA nav |
| `app/Views/profile/index.php` | L530 | `DOMContentLoaded` only | Duplicate profile tab logic (also broken) |

**Fix:** Convert to IIFE with `readyState` check, or use `xsRegisterViewInit()`.

### H2. Hard Redirects Bypassing SPA

These use `window.location.href = ...` after form submission, causing a full page reload instead of SPA navigation:

| File | Line | Context | Suggestion |
|---|---|---|---|
| `app/Views/services/create.php` | L184 | After service creation | Use `window.xsSPA?.navigate(url)` |
| `app/Views/services/edit.php` | L171 | After service update | Use `window.xsSPA?.navigate(url)` |
| `resources/js/modules/appointments/appointments-form.js` | L96, L103 | After appointment save | Use `window.xsSPA?.navigate(url)` |
| `resources/js/modules/scheduler/appointment-details-modal.js` | L616 | Edit button in modal | Use `window.xsSPA?.navigate(url)` |
| `app/Views/analytics/index.php` | L236 | Timeframe select change | Use `window.xsSPA?.navigate(url)` |

### H3. Forced Page Reloads

| File | Line | Context | Fix |
|---|---|---|---|
| `app/Views/user-management/components/provider-locations.php` | L262 | After adding location | DOM insert instead |
| `app/Views/user-management/components/provider-locations.php` | L347 | After set-primary | DOM update instead |
| `app/Views/settings.php` | L1476 | Reset templates | Acceptable (destructive action) |

### H4. Event Listener Accumulation

These listeners are added on every SPA navigation without guards, causing them to fire N times after N navigations:

| File | Line | Listener | Impact |
|---|---|---|---|
| `resources/js/modules/search/global-search.js` | L320+ | `document.addEventListener('click', ...)` inside `initGlobalSearch()` | Click handler fires N times |
| `resources/js/modules/filters/status-filters.js` | L538 | `window.addEventListener('scheduler:date-change', ...)` | Stats refresh fires N times |
| `app/Views/user-management/index.php` | L201 | `document.addEventListener('spa:navigated', ...)` inside inline script | New `spa:navigated` listener added each visit |
| `resources/js/app.js` + `app/Views/appointments/form.php` | L103, L954 | `initAppointmentForm()` called from both `app.js` and `xsRegisterViewInit` | May double-bind form events |

**Fix pattern:** Use a global flag or `{ once: true }` or `removeEventListener` before adding.

---

## MEDIUM-PRIORITY ISSUES

### M1. Seven Duplicate `getBaseUrl()` / `withBaseUrl()` Implementations

A canonical implementation exists at `resources/js/utils/url-helpers.js`, but 6 other files re-implement it locally:

| File | Line |
|---|---|
| `resources/js/modules/scheduler/scheduler-core.js` | L22-28 |
| `resources/js/modules/scheduler/scheduler-drag-drop.js` | L11-19 |
| `resources/js/modules/scheduler/scheduler-week-view.js` | L15-23 |
| `resources/js/modules/scheduler/appointment-details-modal.js` | L21-28 |
| `resources/js/modules/scheduler/settings-manager.js` | L11-20 |
| `resources/js/charts.js` | L23 |
| `app/Views/user-management/index.php` | L147-148 (yet another pattern: `APP_BASE`) |

**Fix:** Import from `url-helpers.js` in all modules. Remove local copies.

### M2. Five+ Duplicate `escapeHtml()` Implementations

| File | Line | Name |
|---|---|---|
| `resources/js/modules/search/global-search.js` | L23 | `escapeHtml` |
| `app/Views/customer-management/index.php` | L315 | `escapeHtml` |
| `app/Views/appointments/form.php` | L646, L945 | `escapeHtml` (twice!) |
| `app/Views/user-management/index.php` | L151 | `escapeHtml` |
| `app/Views/user-management/customers.php` | L89 | `esc` (different name!) |
| `app/Views/settings.php` | L1666 | `window.xsEscapeHtml` |

**Fix:** Create a shared utility and import it, or define once in `app.js` as `window.xsEscapeHtml`.

### M3. Four Separate Toast/Notification Systems

| System | File | Mechanism |
|---|---|---|
| SPA flash (`xs:flash`) | `resources/js/spa.js` L331 | CustomEvent → DOM alert |
| Appointment notification | `resources/js/modules/appointments/appointments-form.js` L237 | `.appointment-notification` fixed div |
| Drag-drop toast | `resources/js/modules/scheduler/scheduler-drag-drop.js` L369 | `showToast()` → fixed div |
| Setup notification | `resources/js/setup.js` L427 | `.notification` fixed div |
| XSNotify (phantom) | settings.php, staff/provider components | `window.XSNotify?.toast()` — **never defined** |

**Fix:** Consolidate on the `xs:flash` CustomEvent system. All modules should dispatch `xs:flash` instead of creating their own UI.

### M4. Orphaned JavaScript File

`resources/js/currency.js` — not imported by any Vite entry point, not referenced in any `<script>` tag, but defines `window.currencyFormatter`. It may not load at all, silently breaking currency formatting.

### M5. `window.__BUSINESS_NAME__` Never Set

Read at `resources/js/modules/scheduler/appointment-details-modal.js` L709 but never set anywhere. Always falls back to `'our business'`.

### M6. Inline CSS Usage

| File | Line | Usage | Actionable? |
|---|---|---|---|
| `app/Views/analytics/index.php` | L167 | Dynamic progress bar width | Yes — use CSS variable |
| `app/Views/setup.php` | L356 | Progress bar `width: 0%` | Yes — JS-controlled |
| `app/Views/components/dashboard/availability-status.php` | L66 | Dynamic provider color | Acceptable — dynamic data |
| `app/Views/components/dashboard/metrics-card.php` | L36 | Dynamic bg-color | Acceptable — dynamic data |

### M7. Global-Scope Functions Without Namespacing

These functions are defined at global scope without an `xs` prefix, risking collisions:

| Function | File | Conflict Risk |
|---|---|---|
| `togglePassword()` | create.php AND edit.php | Low (different SPA pages) |
| `toggleFaq()` | help/index.php | Low |
| `show()` / `hide()` | services/create.php | Medium — very generic names |
| `deleteNotification()` | notifications/index.php | Low |

---

## LOW-PRIORITY ISSUES

### L1. Orphaned CSS/JS Files

| File | Status |
|---|---|
| `resources/js/modules/calendar/prototype-helpers.js` | Only used by prototype HTML (not production) |
| `resources/css/calendar/tailwind-prototype.css` | Not imported anywhere |

### L2. Inconsistent AJAX Detection Pattern

`CustomerManagement.php` uses raw header check instead of `$this->request->isAJAX()`:
```php
$this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest'
```
Functionally equivalent but inconsistent with all other controllers.

### L3. Notifications Form Missing `data-no-spa`

`app/Views/settings.php` — the notifications form (`#notifications-settings-form`) lacks `data-no-spa="true"`, unlike all other settings tab forms. The SPA's `submitHandler` could interfere.

---

## SPA Compatibility Summary by View

| View | SPA Init | Form Handling | Post-Submit | Overall |
|---|---|---|---|---|
| Dashboard | ✅ IIFE + spa:navigated | N/A | N/A | ✅ |
| Appointments | ✅ xsRegisterViewInit | ✅ Own JS + preventDefault | ⚠️ window.location.href | ⚠️ |
| Services (list) | ✅ SPA tabs | ✅ SPA intercepts forms | ✅ SPA JSON → navigate | ✅ |
| Services (create) | ✅ IIFE | ✅ Own fetch + preventDefault | ⚠️ window.location.href | ⚠️ |
| Services (edit) | ✅ IIFE | ✅ Own fetch + preventDefault | ⚠️ window.location.href | ⚠️ |
| User Management (list) | ✅ DOMContentLoaded + spa:navigated | ✅ SPA intercepts | ❌ delete() no JSON | ⚠️ |
| User Management (create) | ✅ Inline immediate | ✅ SPA intercepts | ✅ JSON → SPA navigate | ✅ |
| User Management (edit) | ✅ IIFE | ✅ SPA intercepts | ✅ JSON → SPA navigate | ✅ |
| Customer Management | ✅ xsRegisterViewInit | ✅ SPA intercepts | ✅ JSON → SPA navigate | ✅ |
| Settings | ✅ IIFE + guards | ✅ Own fetch + data-no-spa | ✅ In-place toast | ✅ |
| Profile | ❌ DOMContentLoaded only | ⚠️ SPA intercepts but controller returns redirect | ❌ Blanks page (C1+C2) | ❌ |
| Analytics | ❌ window.load only | N/A | ⚠️ window.location.href | ❌ |
| Help | N/A (stateless) | ❌ SPA intercepts, no JSON response | ❌ Blanks page (C1+C2) | ❌ |
| Notifications | ✅ Global functions | ✅ SPA intercepts | ✅ JSON → SPA navigate | ✅ |

---

## Recommended Fix Priority

### Phase 1 — Critical (Fix First)

1. **Fix `res.text()` double-consumption bug** in `spa.js` submitForm()
2. **Add AJAX JSON responses** to UserManagement::delete(), Profile::changePassword(), Profile::uploadPicture(), Help::submitTicket()
3. **Define `XSNotify`** or replace all calls with `xs:flash` event

### Phase 2 — High Priority

4. **Fix broken view initializers** — analytics/index.php, profile/index.php
5. **Convert hard redirects to SPA navigation** — services create/edit, appointments-form.js
6. **Fix event listener accumulation** — add guards in global-search.js, status-filters.js, user-management/index.php
7. **Replace `location.reload()` with DOM updates** — provider-locations.php

### Phase 3 — Medium Priority (Code Quality)

8. **Consolidate `getBaseUrl()`** — remove 6 local copies, import from url-helpers.js
9. **Consolidate `escapeHtml()`** — single shared implementation
10. **Unify toast/notification system** — all modules use `xs:flash`
11. **Fix orphaned currency.js** — add to Vite entry or import from app.js
12. **Set `window.__BUSINESS_NAME__`** from PHP config

### Phase 4 — Low Priority (Cleanup)

13. Remove orphaned prototype files
14. Standardize AJAX detection pattern in CustomerManagement
15. Add `data-no-spa` to notifications form
16. Replace inline CSS with CSS variables where feasible
17. Add `xs` prefix to global view functions

---

## Architecture Notes

### How SPA Navigation Works

```
User clicks link → spa.js clickHandler() → fetchPage(url) → DOMParser → extract #spa-content innerHTML
→ set el.innerHTML → re-execute <script> tags → dispatch 'spa:navigated' → run xsViewInitializers
```

### How SPA Form Submission Works

```
User submits form → spa.js submitHandler() → preventDefault → fetch(action, {method, body, headers})
→ parse JSON → if success: navigate(redirect) → if error: show flash + inject validation errors
```

### Script Re-Execution After SPA Navigation

The SPA creates new `<script>` elements to replace those injected via `innerHTML` (since browsers don't execute scripts inserted via innerHTML). This works for inline scripts but external `<script src="...">` scripts may re-fetch and re-execute, potentially causing side effects.

### The `xsRegisterViewInit` Pattern (Recommended)

```javascript
function initMyView() {
    const el = document.getElementById('myElement');
    if (!el || el.dataset.initialized === 'true') return;
    el.dataset.initialized = 'true';
    // ... initialization code
}
xsRegisterViewInit(initMyView);
```

This runs on: DOMContentLoaded + every SPA navigation. The `dataset.initialized` guard prevents double-binding.

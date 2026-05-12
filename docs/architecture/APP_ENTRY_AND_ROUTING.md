# App Entry and Root Routing

**Controller:** `app/Controllers/AppFlow.php`  
**Route:** `GET /`

---

## Purpose

`AppFlow` handles the single root entry point (`/`) and routes the incoming request to the correct destination based on two sequential checks: whether setup is complete, and whether the user is logged in.

It contains no business logic and no views — only redirects.

---

## Routing Decision Tree

```
GET /
  │
  ├── is_setup_completed() == false
  │     └── redirect → /setup
  │
  └── is_setup_completed() == true
        │
        ├── session('isLoggedIn') == true
        │     └── redirect → /dashboard
        │
        └── session('isLoggedIn') == false
              └── redirect → /auth/login
```

---

## Dependencies

| Dependency | Source | Purpose |
|---|---|---|
| `is_setup_completed()` | `app/Helpers/setup_helper.php` | Checks whether the initial setup wizard has been completed |
| `session()->get('isLoggedIn')` | CI4 session | Checks authenticated session state |

---

## Notes

- There is no view rendered by this controller. Every path is a `redirect()`.
- The `setup` filter in `app/Config/Filters.php` guards most protected routes, but AppFlow is the explicit root handler that sends unauthenticated post-setup users to login.
- This controller is deliberately thin. Adding business logic here violates the design order (see `Agent_Context_v2.md §2.4`).

---

## SPA Navigation Contract

The app uses a client-side SPA layer (`resources/js/spa.js`) that intercepts link clicks and form submissions to swap `#spa-content` without a full page reload. The following rules govern when interception applies.

### Link interception — skipped when:
- The `<a>` has `data-no-spa="true"` or the class `no-spa`
- The link has a `target` attribute or a `download` attribute
- The `href` starts with `#`, `mailto:`, or `tel:`
- The link is cross-origin
- The link has `data-navlink` (FullCalendar internal navigation)
- The link is inside `.fc` (FullCalendar container)

### Form interception — skipped when:
- The `<form>` has `data-no-spa="true"` or the class `no-spa`
- The form method is not `POST`
- The form action is cross-origin

**Use `data-no-spa="true"` on any form or link that must perform a full page load:**
- File upload forms (multipart responses cannot be processed by the SPA)
- The setup wizard (`/setup/*`) — already uses full reloads via `AppFlow` redirect
- Auth forms (`/auth/login`, `/auth/logout`) — session state changes require full reload
- Any form that responds with a binary download

### SPA form JSON contract

When the SPA intercepts a POST form, it sends `X-Requested-With: XMLHttpRequest`. The controller must respond with JSON:

```json
{ "success": true, "redirect": "/target-path" }
```

On success the SPA navigates to `redirect`. On failure (`success: false`), it expects:

```json
{ "success": false, "message": "Human-readable error", "errors": { "field": "message" } }
```

### `xsRegisterViewInit` convention

View initializers registered via `xsRegisterViewInit(fn)` fire on every `spa:navigated` event for the lifetime of the session. **Every callback MUST have a DOM-existence guard as its first statement** — if the target element is absent, return immediately.

```js
xsRegisterViewInit(() => {
    const el = document.querySelector('#my-widget');
    if (!el) return; // guard — this view may not be in the current DOM
    // ... init code
});
```

---

## Related

- `app/Config/Routes.php` — registers `GET /` to `AppFlow::index`
- `app/Helpers/setup_helper.php` — `is_setup_completed()` implementation
- `app/Controllers/Setup.php` — handles the setup wizard
- `app/Controllers/Auth.php` — handles login/logout
- `app/Controllers/Dashboard.php` — post-login landing

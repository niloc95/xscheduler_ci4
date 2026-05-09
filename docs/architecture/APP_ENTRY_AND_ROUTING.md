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

## Related

- `app/Config/Routes.php` — registers `GET /` to `AppFlow::index`
- `app/Helpers/setup_helper.php` — `is_setup_completed()` implementation
- `app/Controllers/Setup.php` — handles the setup wizard
- `app/Controllers/Auth.php` — handles login/logout
- `app/Controllers/Dashboard.php` — post-login landing

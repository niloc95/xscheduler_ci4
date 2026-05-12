# Auth Hardening — Inactivity Modal, Session Ping, Login Lockout

**Implemented:** 2026-05-12  
**Status:** Complete

---

## Overview

Three layered improvements to authentication UX and security, all built on existing CI4 primitives — no new framework dependencies.

| Feature | Mechanism |
|---|---|
| Inactivity warning modal | Client-side timer + DOM modal |
| Sliding session extension | `GET /auth/ping` touches CI4 session → cookie expiry slides |
| Failed-login lockout | CI4 Throttler (Token Bucket) |

---

## Part 1 — Inactivity Warning Modal

**File:** `resources/js/modules/auth/inactivity-monitor.js`  
**Called from:** `initializeComponents()` in `resources/js/app.js`

### Timing constants

| Constant | Value | Meaning |
|---|---|---|
| `SESSION_MS` | 7 200 000 ms (2 h) | Matches `app/Config/Session.php $expiration` |
| `WARNING_MS` | 300 000 ms (5 min) | How early to show the warning |
| `COUNTDOWN_S` | 300 s | Modal countdown duration |

The idle timer fires at `SESSION_MS − WARNING_MS` = **115 minutes** after the last activity event.

### Activity events tracked

`mousemove`, `keydown`, `click`, `touchstart`, `scroll` — all passive listeners on `document`.

### Modal behaviour

- Rendered lazily into `document.body` on first show (`id="xs-session-warning-modal"`).
- Accessible: `role="alertdialog"`, `aria-modal="true"`, `aria-labelledby`.
- **Stay Logged In** → calls `GET /auth/ping` → on 200: hides modal, resets 115-min clock.
- **Log Out** → redirects to `/auth/logout`.
- Countdown reaches `0:00` → redirects to `/auth/login`.
- `setInterval` continues ticking even when the tab is hidden.

### SPA safety

On `spa:leaving` (once), the module clears both timers and removes all activity listeners. `initInactivityMonitor()` is called again after each SPA navigation via `initializeComponents()`, which re-binds fresh listeners (using `removeEventListener` before `addEventListener` to avoid duplicates).

---

## Part 2 — Session Ping Endpoint

**Route:** `GET /auth/ping` (filter: `auth`)  
**Controller:** `Auth::ping()`

```
GET /auth/ping
→ 200 {"ok": true, "expires_in": 7200}   (logged-in)
→ 401 {"ok": false}                       (not logged in)
```

CI4's session driver reads/writes the session on every request. When `$timeToUpdate` (900 s) has elapsed since last regeneration, CI4 regenerates the session ID and resets the cookie expiry — effectively **sliding the 2-hour window** with each ping.

---

## Part 3 — Failed-Login Lockout

**Files modified:** `app/Controllers/Auth.php`, `app/Views/auth/login.php`

### Throttler config

| Parameter | Value |
|---|---|
| Max attempts | 5 |
| Window | 900 s (15 min) |
| Key | `login_{md5(ip_address)}` |
| Reset on success | Yes — `$throttler->remove($ipKey)` |

### Response by request type

| Caller | Response |
|---|---|
| AJAX (`isAJAX()`) | HTTP 429 + `{"error": "Too many failed login attempts..."}` |
| Form submit | Redirect back + `lockout_error` flash + `lockout_wait` (seconds) flash |

### Login view

When `lockout_error` flash is present, a lock-icon error block is shown above the form with the human-readable wait time (minutes). The raw `lockout_wait` seconds are available on `data-wait` for potential client-side countdown use.

---

## Verification checklist

- [ ] Idle on any authenticated page for 115 min → warning modal appears with 5:00 countdown
- [ ] Click "Stay Logged In" → modal closes, timer resets, `/auth/ping` returns 200
- [ ] Click "Log Out" in modal → redirected to login
- [ ] Countdown reaches 0:00 → redirected to login
- [ ] Tab hidden during countdown → countdown still ticks
- [ ] Wrong password 5× → 6th attempt shows lockout message with wait time
- [ ] Wait lockout period → login works again
- [ ] Successful login after ≤4 failed attempts → throttle bucket resets

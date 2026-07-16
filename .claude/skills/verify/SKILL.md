---
name: verify
description: Build, launch, and drive WebScheduler CI4 to verify a change end-to-end in a real browser session.
---

# WebScheduler — Verify Recipe

## Build + serve

- `npm run build` — Vite build into `public/build/` (gitignored; the PHP server serves it directly).
- Dev server is usually already running: `php -S localhost:8080 -t public/ vendor/codeigniter4/framework/system/rewrite.php` (check `lsof -nP -iTCP:8080 -sTCP:LISTEN` first; reuse it, don't kill it).
- `.env` has `app.baseURL = http://localhost:8080/`, dev DB `local_0906` (MySQL root, password in `.env`). Environment is `development` — CI4 debugbar/Kint inject scripts that log CSP nonce errors in the console; that noise is dev-only, not an app defect.

## Authenticated session

- Playwright 1.59 is a devDependency; chromium already installed. E2e spec pattern: `tests/e2e/appointments-header.spec.js` (`ADMIN_EMAIL`/`ADMIN_PASSWORD` env vars are NOT set in `.env`).
- No known-password user exists in the dev DB. Create a throwaway admin and delete it afterwards:
  ```sql
  -- HASH from: php -r "echo password_hash('<pw>', PASSWORD_DEFAULT);"
  INSERT INTO xs_users (name,email,password_hash,role,status,is_active,created_at)
    VALUES ('Temp Verify','temp-verify@test.local','<HASH>','admin','active',1,NOW());
  INSERT INTO xs_user_roles (user_id, role, created_at)
    SELECT id,'admin',NOW() FROM xs_users WHERE email='temp-verify@test.local';
  -- cleanup: delete xs_user_roles row first, then xs_users row
  ```
- Login flow: goto `/auth/login`, fill `[name="email"]` / `[name="password"]`, click `button[type="submit"]`, wait for `**/dashboard**`.

## Driving time-based behavior (inactivity monitor etc.)

- Use Playwright's fake clock instead of editing constants: `await page.clock.install()` **before** the first `goto`, then `page.clock.fastForward(ms)`. The inactivity monitor is wall-clock based (`Date.now()`), so fast-forward drives it faithfully; network/fetch stays real, so `/auth/ping` and login work normally.
- Monitor observables: `localStorage['xs-last-activity-at']` (stamped at init, refreshed on activity), `#xs-session-warning-modal` (created on demand, `hidden` class toggled), `#xs-session-countdown`, `#xs-session-stay`.
- Session window: warning at 115 min idle, redirect to `/auth/login` at 120 min. After a client redirect the server session may still be alive in fake-clock runs (real time barely passed), so the login page bounces back to `/dashboard` — assert the `/auth/login` request happened, not the final URL.
- Run scripts with `node` from the scratchpad using `createRequire('<repo>/package.json')` to resolve `playwright` (a bare import fails outside the repo).

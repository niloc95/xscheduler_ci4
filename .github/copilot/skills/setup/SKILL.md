---
name: webscheduler-setup
description: WebScheduler setup wizard — initial configuration flow, view/layout wiring (components/setup-layout.php NOT layouts/setup.php), JS entry points (setup.js + SetupWizard class), CSS loading, FOUC prevention, database connection testing, and setup guard filter. Use when touching the setup wizard, setup routes (/setup, /setup/process, /setup/test-connection), or any setup-related views, JS, or layout. Triggers on "setup", "SetupWizard", "setup wizard", "setup route", "test connection", "components/setup-layout", or when editing app/Views/setup.php or resources/js/setup.js.
---

# WebScheduler — Setup Wizard

## 1. Routes

| Method | Path | Controller | Purpose |
|---|---|---|---|
| `GET` | `/setup` | `Setup::index` | Render the wizard |
| `POST` | `/setup/process` | `Setup::process` | Save config and create admin user |
| `POST` | `/setup/test-connection` | `Setup::testConnection` | Live DB connectivity check |

All setup routes pass through the `setup` filter. If the application is already configured, the filter redirects away from setup.

---

## 2. View Structure

- **Main view:** `app/Views/setup.php` — uses `$this->extend('components/setup-layout')`
- **Active layout:** `app/Views/components/setup-layout.php`

> ⚠️ `app/Views/layouts/setup.php` exists but is **NOT used** by the current setup flow. Do not switch to it — `components/setup-layout.php` is the active layout.

The view renders a two-step form:
- **Step 1:** Admin account (name, email, username/ID, password with strength indicator)
- **Step 2:** Database configuration (host, database, username, password, prefix, port, driver)

A progress indicator shows step 1/2 at the top.

---

## 3. Asset Wiring (Owner Section)

### CSS

`app-consolidated.scss` via Vite — same single source as all other pages:
```php
<?php foreach (vite_css('resources/scss/app-consolidated.scss') as $css): ?>
<link rel="stylesheet" href="<?= $css ?>">
<?php endforeach; ?>
```

### Icon Fonts

Both Material Symbols families loaded in `components/setup-layout.php`:
```html
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
```

### FOUC Prevention

`components/setup-layout.php` must carry the same inline blocking script as all other layouts:
```html
<script>!function(){var t=localStorage.getItem('xs-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.style.colorScheme=t;if(t==='dark')document.documentElement.style.backgroundColor='#111827';document.documentElement.classList.add('xs-no-transition')}();</script>
```

**Do not use `<script type="module" src="...theme-bootstrap.js">` in setup-layout.php** — module scripts are deferred and defeat FOUC prevention. See `frontend` skill §4.

The `xs-no-transition` cleanup runs via `dark-mode.js` initialization or an inline rAF at end of `<body>`. Verify `components/setup-layout.php` removes the class after first paint.

### Body

```html
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-200">
```

`<html>` tag must NOT carry `transition-colors duration-200` — see `ui-ux` skill §6.

### JS Entry Point

`setup.js` is loaded by `app/Views/setup.php` via the `head` section:
```php
<?= $this->section('head') ?>
<script type="module" src="<?= vite_js('resources/js/setup.js') ?>"></script>
<?= $this->endSection() ?>
```

`dark-mode.js` is loaded at end of `<body>` in `components/setup-layout.php` for the theme toggle button.

---

## 4. JS Architecture

### `resources/js/setup.js` — `SetupWizard` class

Entry is a `SetupWizard` class initialized on `DOMContentLoaded`. It handles:

- Admin account form validation (name, email, password strength indicator)
- Database configuration field validation
- `POST /setup/test-connection` via `apiRequest()` from `./core/api.js`
- Compatibility mode detection via `./setup-compat.js` (shared hosting / restricted SQL)

### Inline config in view

`app/Views/setup.php` provides a small `<script>` block with:
```js
window.appConfig = { baseUrl, siteUrl, csrfToken, csrfHeader };
```

### No SPA navigation

Setup uses full-page form submission. Do NOT add `spa.js` or `app.js` to this layout.

---

## 5. Constraints

- **Database not available at setup time** — never use CI4 models or DB queries in the setup view or its layout. All DB work happens in `Setup::process` / `Setup::testConnection` after the config is submitted.
- `SetupWizard` communicates with the backend only via `POST /setup/test-connection` and the final form submit.
- **No analytics** — do not add `analytics_head_html()` to setup pages. The business isn't configured yet, and the analytics settings don't exist.
- **No authenticated session** — setup runs before any user exists. Do not add route filters that check session state.

---

## 6. Cross-Skill References

- FOUC prevention inline script contract → `frontend` skill §4
- Icon system and font loading rules → `ui-ux` skill §1
- SCSS dark mode conventions → `ui-ux` skill §4

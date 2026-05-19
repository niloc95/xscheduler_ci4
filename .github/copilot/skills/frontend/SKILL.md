---
name: webscheduler-frontend
description: WebScheduler frontend contract — Vite entry points, SPA lifecycle and navigation, dark mode (FOUC prevention + Tailwind class strategy), shared fetch layer, profile surface, avatar/initials system, design system (`xs-card`, `xs-btn`, `xs-actions-container`), `layouts/app` layout rules, and appointments toolbar layout. Use whenever you're working in `resources/js`, `resources/scss`, `app/Views/`, the SPA layer, Vite config, theme toggles, view layouts, avatars, or any styling/layout decision. Triggers on phrases like "view", "layout", "SPA", "Vite", "dark mode", "theme", "Tailwind", "SCSS", "avatar", "initials", "xs-card", "xs-btn", "appointments view", "toolbar", "scripts section", "DOMContentLoaded", "spa:navigated", or any JS/CSS/template change.
---

# WebScheduler — Frontend Contract

## 1. Canonical Entry Points (Vite)

Defined in `vite.config.js` `rollupOptions.input`:

| Key | File |
| --- | --- |
| `main` | `resources/js/app.js` |
| `style` | `resources/scss/app-consolidated.scss` |
| `spa` | `resources/js/spa.js` |
| `dark-mode` | `resources/js/dark-mode.js` |
| `theme-bootstrap` | `resources/js/theme-bootstrap.js` |
| `app-layout-init` | `resources/js/layout/app-layout-init.js` |
| `public-booking` | `resources/js/public-booking.js` |
| `public-booking-bootstrap` | `resources/js/public-booking-bootstrap.js` |
| `unified-sidebar` | `resources/js/unified-sidebar.js` |
| `charts` | `resources/js/charts.js` |
| `setup` | `resources/js/setup.js` |

`resources/js/material-web.js` was removed — the `@material/web` bundle was unused (zero `<md-*>` elements in production views).

**Non-entry utility modules** (import only, not Vite entry points):
- `resources/js/currency.js` — currency formatting, loads settings from `/api/v1/settings/localization`
- `resources/js/utils/avatar.js` — avatar initials helper (see §6 below)

## 2. Lifecycle Contract

Core lifecycle for authenticated surfaces:

1. `app.js` loads
2. `spa.js` intercepts links/forms
3. `initializeComponents()` runs on load and after `spa:navigated`
4. View code uses `xsRegisterViewInit(fn)`
5. Initializers **must be idempotent** via dataset guards

**Do not add bare `DOMContentLoaded`-only logic for app surfaces that can SPA-navigate.**

## 3. SPA Navigation Contract

`spa.js` behavior:

- Swaps `#spa-content`
- Emits `spa:navigated` after successful swap
- Supports `data-no-spa` and `no-spa` opt-outs
- Supports `forceReload` semantics for same-URL post-mutation refresh

If a controller action redirects back to the current page, the JSON response **must** include a `redirect` key so SPA navigation can force-reload (see `api-contract` skill).

## 4. Dark Mode Contract

**Tailwind configuration:** `darkMode: 'class'` in `tailwind.config.js`. All Tailwind `dark:` utilities require `.dark` on `<html>`.

**FOUC prevention:** `resources/js/theme-bootstrap.js` runs as an **inline blocking script** (no `type="module"`) on every page load. It sets BOTH `document.documentElement.dataset.theme = theme` AND `document.documentElement.classList.toggle('dark', theme === 'dark')` before the browser paints.

**Runtime management:** `DarkModeManager` in `resources/js/dark-mode.js` handles the full lifecycle:
- On every `applyTheme()` call: sets BOTH `data-theme` attribute AND `.dark` class
- Persists to `localStorage` key `xs-theme`
- Dispatches `xs:theme-changed` custom event for downstream components
- Re-wires `[data-theme-toggle]` buttons after each `spa:navigated` event

**SCSS:** Dark overrides use `.dark { ... }` selector in `resources/scss/abstracts/_custom-properties.scss`, **not** `[data-theme="dark"]`.

**Summary:** `data-theme` attribute is always kept in sync (for external consumers and CSS that reads it), but `.dark` class is the canonical trigger for Tailwind utilities and SCSS dark overrides.

## 5. Styling Contract

- Tailwind + consolidated SCSS only
- **No inline `style` attributes** in app-facing templates — caught by pre-merge grep check

## 6. Avatar System Contract (Owner Section)

All avatar rendering — profile images and initials fallback — has a **single source of truth** on both PHP and JS sides.

### 6.1 PHP Helpers (`app/Helpers/app_helper.php`)

Four canonical helpers, loaded via `helper('app')`:

| Helper | Purpose |
| --- | --- |
| `avatar_initials(string $name, string $default = 'U'): string` | Derives 1-2 letter initials from a display name. Strips titles (Dr., Prof., Mr., Mrs., Ms., Rev.) and suffixes (MD, PhD, DDS, DO, RN, NP, PA, DVM, Jr., Sr., II, III, IV). Multi-word → first + last initial. Single-word → first 2 chars. Empty → `$default`. |
| `avatar_display_name(array $user, string $fallback = 'User'): string` | Prefers `$user['name']`; falls back to `first_name` + `last_name` concatenation. |
| `avatar_profile_image_url(array $user): ?string` | Resolves profile image URL. Paths starting with `assets/` map to `FCPATH`; paths starting with `uploads/` or `writable/` map to `WRITEPATH`. Returns `null` if no usable path. |
| `avatar_data(array $user, string $defaultInitial = 'U'): array` | Returns `['imageUrl' => ?string, 'initials' => string, 'displayName' => string]`. Use this in views for image-first rendering with initials fallback. |

`ProfilePageService::buildProfileImageUrl()` and `buildProfileInitials()` delegate to these helpers.

### 6.2 JS Utility (`resources/js/utils/avatar.js`)

Single ESM module — import directly:

```js
import { getAvatarInitials, getDisplayName } from '../../utils/avatar.js';
```

| Export | Signature | Behaviour |
| --- | --- | --- |
| `getDisplayName(entity, fallback)` | `(object, string) → string` | Prefers `entity.name`; falls back to `first_name` + `last_name`. |
| `getAvatarInitials(name, options)` | `(string, { defaultInitial? }) → string` | Same normalization rules as PHP: strip titles/suffixes, multi-word → first+last initial, single-word → first 2 chars. `options.defaultInitial` defaults to `'U'`. |

For inline `<script>` blocks in PHP views (which cannot use ESM imports), use the globals exposed by `resources/js/app.js`:

```js
window.xsGetAvatarInitials(name, defaultInitial)
window.xsGetDisplayName(entity, fallback)
```

### 6.3 Canonical Default Initials by Context

| Context | Default |
| --- | --- |
| User / staff / header | `'U'` |
| Customer | `'C'` |
| Staff assignment widget | `'S'` |
| Provider assignment widget | `'P'` |
| Scheduler provider chip | `'?'` |

### 6.4 Covered Surfaces

- `app/Views/layouts/app.php` — header user avatar (image-first via `avatar_data()`)
- `app/Views/user-management/index.php` — PHP rows + JS `userRow()` via `window.xsGetAvatarInitials`
- `app/Views/customer-management/index.php` — `avatar_data()` with `defaultInitial: 'C'`
- `app/Views/customer-management/history.php` — large customer header avatar
- `app/Views/appointments/form.php` — customer avatar placeholder (`'C'`)
- `app/Views/user-management/components/provider-staff.php` — server PHP + JS `renderStaff()` widget
- `app/Views/user-management/components/staff-providers.php` — server PHP + JS `renderProviders()` widget
- `resources/js/modules/scheduler/appointment-colors.js` — `getProviderInitials()` delegates to `getAvatarInitials`
- `resources/js/modules/customer-management/customer-search.js`
- `resources/js/modules/appointments/appointments-form.js`

### 6.5 Do Not Duplicate

**Do not reimplement initials logic in any view, controller, or JS module.** Always call the shared helper. A one-letter initial in a completed surface is a regression against this contract.

## 7. View Layout & Design System Contract (Owner Section)

### 7.1 Canonical Layout

All authenticated views **must** extend `layouts/app`. The legacy `layouts/dashboard` layout is **deprecated**; do not use it for new views or when migrating existing views.

| Layout | Status | Sections |
| --- | --- | --- |
| `layouts/app` | **Active standard** | `sidebar`, `header_title`, `header_subtitle`, `header_primary_action`, `header_controls`, `content`, `scripts`, `modals` |
| `layouts/dashboard` | **Deprecated** | `dashboard_stats`, `dashboard_actions`, `dashboard_filters`, `dashboard_content_top`, `dashboard_content` |

To suppress the header's built-in "New Appointment" CTA for a page that provides its own CTA in `content`:

```php
<?= $this->section('header_primary_action') ?>hidden<?= $this->endSection() ?>
```

### 7.2 Design System Component Classes

| Token | Element | Notes |
| --- | --- | --- |
| `xs-card` | Card container | Replaces `card card-spacious` |
| `xs-card-header` | Card header bar | Use with `xs-card-header-content` (left) + `xs-card-actions` (right) |
| `xs-card-header-content` | Left slot of card header | Contains title + subtitle |
| `xs-card-title` | Title inside `xs-card-header-content` | |
| `xs-card-subtitle` | Subtitle inside `xs-card-header-content` | |
| `xs-card-actions` | Right slot of card header | Icon buttons, secondary controls |
| `xs-card-body` | Card content area | Add `p-0` when the card contains a full-bleed table |
| `xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon` | Icon-only action button | Replaces text+icon `btn btn-secondary btn-sm` rows |
| `xs-btn xs-btn-sm xs-btn-primary` | Primary small button | |
| `xs-actions-container` | Wrapper for per-row icon action buttons | Always `justify-end` for right-aligned table cells |

### 7.3 Per-Row Action Button Pattern

All table action columns use icon-only buttons inside `xs-actions-container`. This is the **canonical pattern** — do not add visible text labels to row actions:

```php
<div class="xs-actions-container justify-end">
    <a href="<?= site_url('entity/edit/' . $row['id']) ?>"
       class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" title="Edit">
        <span class="material-symbols-outlined">edit</span>
    </a>
    <form action="<?= site_url('entity/delete/' . $row['id']) ?>" method="post"
          class="inline-flex" data-no-spa="true"
          data-confirm-message="Delete this item?">
        <?= csrf_field() ?>
        <button type="submit"
                class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-red-600 hover:text-red-700 dark:text-red-400"
                title="Delete">
            <span class="material-symbols-outlined">delete</span>
        </button>
    </form>
</div>
```

### 7.4 Flash Messages

Flash messages are rendered automatically by `layouts/app` via `$this->include('components/ui/flash-messages')`. Views must **not** include manual flash message HTML blocks (`session()->getFlashdata('message')` divs). Deleting those blocks from migrated views is required.

### 7.5 Appointments Toolbar — Mobile Two-Rail Layout

`app/Views/appointments/index.php` uses a two-rail horizontal layout inside `header_controls`:

- **Outer wrapper:** `appointments-toolbar flex flex-row items-start gap-3 md:items-center md:gap-3`
- **Rail A** (`appointments-toolbar__primary`): `flex flex-col gap-1.5 min-w-0 md:flex-row md:items-center md:gap-2`
  - Sub-row 1 (mobile): Today button + view-mode switcher
  - Sub-row 2 (mobile): Date navigation cluster (prev / label / next)
  - Collapses to single flex row on `md+`
- **Rail B** (`appointments-toolbar__secondary`): `flex items-start gap-2 min-w-0 md:items-center`
  - `#scheduler-stats-bar`: `flex flex-col gap-1 md:flex-row md:flex-nowrap md:items-center md:gap-1` — chips stack vertically on mobile, row on desktop
- Mobile filter button is **removed** from Rail B; filter toggle is desktop-only (`hidden md:inline-flex`)

**Do not revert to a single `overflow-x-auto` row** — that pattern caused date cluster overflow on narrow screens.

## 8. Shared Fetch Contract

`resources/js/core/api.js` `apiRequest()` returns `{ response, payload }`.

- For `application/json` responses, `payload` is **already parsed JSON**. Do not assume string methods such as `.match()` are available unless `typeof payload === 'string'`.
- Text or HTML responses may still return string payloads.
- Shared helpers such as `extractJSON()` must accept already-parsed objects so search surfaces remain compatible.

## 9. Profile Surface Contract

- `/profile` is a live account surface backed by `App\Services\ProfilePageService`; do not reintroduce placeholder summary cards or fake recent activity.
- `app/Views/profile/index.php` is SPA-safe and is initialized through `resources/js/modules/profile/profile-page.js` from `resources/js/app.js`.
- Profile mutations must preserve session role context via `array_merge` and write audit-log events.
- Provider and staff notification preferences edited from `/profile` must persist to `xs_users.notify_on_appointments`.

See `auth-rbac` skill for session merge details.

## 10. Dashboard Layout and Scroll UX (Reference)

Full rules live in the `rules` skill (Rule #4). Key reminders:

- No inline hard caps in templates (`max-h-[400px]`, `h-[...px]`). Use semantic SCSS classes.
- Viewport-aware sizing via `clamp()` for desktop.
- Nested scroll only for long data lists, not control groups.

## 11. Client-Side Scheduler Modules

| File | Role |
| --- | --- |
| `scheduler-core.js` | Orchestrator. Owns `this.calendarModel`, `this.appointments`, `loadData()`, `loadCalendarModel()`, `loadAppointments()`. |
| `scheduler-day-view.js` | Day column rendering. Derives `this.businessHours` via `_resolveBusinessHours(config, calendarModel)`. |
| `scheduler-week-view.js` | Week grid rendering. |
| `scheduler-month-view.js` | Month grid rendering. |
| `time-grid-utils.js` | `getBusinessHours(config)` — extracts `startHour`/`endHour` from config or `calendarModel.businessHours`. |
| `settings-manager.js` | Bootstraps `window.appTimezone` from `/api/v1/settings/localization`. |
| `appointment-details-modal.js` | Renders the appointment detail modal (entry: `/appointments?open={hash}`). |

See `scheduling` skill for the full data flow.

## 12. Datetime Parsing on the Client

All scheduler views parse API datetimes as UTC via Luxon:

```js
DateTime.fromISO(val, { zone: 'utc' }).setZone(window.appTimezone)
```

`window.appTimezone` is set by `SettingsManager` from `/api/v1/settings/localization`. **Never parse appointment datetimes as local time.**

## 13. Pre-Merge Frontend Grep Check

```bash
# Detect inline style regressions in views
rg "style=\"|<style>" app/Views resources/js
```

Any result must be reviewed.

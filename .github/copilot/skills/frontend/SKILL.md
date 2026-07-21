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

Body-level overlays or modals created by JS (outside `#spa-content`) must explicitly clean themselves up on `spa:leaving`. Hiding them is not sufficient when the DOM node survives SPA swaps; teardown must remove stale nodes, listeners, and pending timers so the next initializer run can recreate fresh state.

**Do not add bare `DOMContentLoaded`-only logic for app surfaces that can SPA-navigate.**

## 3. SPA Navigation Contract

`spa.js` behavior:

- Swaps `#spa-content`
- Emits `spa:navigated` after successful swap
- Supports `data-no-spa` and `no-spa` opt-outs
- Supports `forceReload` semantics for same-URL post-mutation refresh

If a controller action redirects back to the current page, the JSON response **must** include a `redirect` key so SPA navigation can force-reload (see `api-contract` skill).

## 4. Dark Mode Contract (Owner Section)

**Tailwind configuration:** `darkMode: 'class'` in `tailwind.config.js`. All Tailwind `dark:` utilities require `.dark` on `<html>`.

**FOUC prevention — inline blocking script:** The script is **literally inlined** in `<head>` — NOT a `<script src>` or `type="module"`. All three layouts (`app.php`, `public.php`, `auth.php`) and the standalone `booking.php` carry the same one-liner. It runs synchronously during HTML parsing, before any CSS file loads:

```html
<script {csp-script-nonce}>!function(){var t=localStorage.getItem('xs-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.style.colorScheme=t;if(t==='dark')document.documentElement.style.backgroundColor='#111827';document.documentElement.classList.add('xs-no-transition')}();</script>
```

> **CSP nonce required:** Every inline `<script>` in CI4 layouts must carry `{csp-script-nonce}`. CI4 replaces this placeholder with `nonce="…"` when `app.CSPEnabled = true`. Omitting it causes a CSP violation and the script is silently blocked in production.

What it does (in order):
1. Reads `xs-theme` from `localStorage`; falls back to `prefers-color-scheme`
2. Sets `data-theme` attribute + toggles `.dark` class on `<html>`
3. Sets `style.colorScheme = t` — system UI (scrollbars, native inputs) renders in the right scheme
4. For dark mode: sets `style.backgroundColor = '#111827'` on `<html>` — covers viewport before CSS loads
5. Adds `.xs-no-transition` class — suppresses all `transition-colors` when the CSS file first applies

**`xs-no-transition` CSS rule** lives in `resources/scss/base/_reset.scss`:
```scss
.xs-no-transition, .xs-no-transition * { transition: none !important; }
```
This prevents the 200ms animated fade that would otherwise occur when `dark:bg-gray-900` first applies to `<body>`.

**FOUC cleanup:** The guards must be removed after the first paint to restore normal transitions for user-triggered theme switches.
- **Layouts that load `app-layout-init.js`:** cleanup runs in the double `requestAnimationFrame` inside `onDomReady()`.
- **Standalone pages (e.g. `booking.php`):** cleanup is a bare inline script at end of `<body>`:
  ```html
  <script {csp-script-nonce}>requestAnimationFrame(function(){requestAnimationFrame(function(){document.documentElement.classList.remove('xs-no-transition');document.documentElement.style.backgroundColor='';document.documentElement.style.colorScheme=''})});</script>
  ```

**Dark background values by page:**
- Authenticated app (`layouts/app.php`, `auth.php`, `public.php`): `#111827` (Tailwind `gray-900` = `dark:bg-gray-900` on `<body>`)
- Public booking SPA (`booking.php`): `#0f172a` (Tailwind `slate-900` = `dark:bg-slate-900` on `<body>`)

**`<html>` tag:** must NOT carry `transition-colors duration-200` — it has no `background-color` rule. `transition-colors` lives on `<body>` only, for user-triggered theme switches.

**`theme-bootstrap` Vite entry:** still exists in `vite.config.js` and builds `resources/js/theme-bootstrap.js`, but **no layout loads it via `<script src>`**. The content is inlined directly. Do not re-introduce the module src load — it would defer execution and break FOUC prevention.

**Runtime management:** `DarkModeManager` in `resources/js/dark-mode.js` handles the full lifecycle:
- On every `applyTheme()` call: sets `data-theme` attribute, `.dark` class, AND `style.colorScheme`
- Persists to `localStorage` key `xs-theme`
- Dispatches `xs:theme-changed` custom event for downstream components
- Re-wires `[data-theme-toggle]` buttons after each `spa:navigated` event

**SCSS:** Dark overrides use `.dark { ... }` selector in `resources/scss/abstracts/_custom-properties.scss`, **not** `[data-theme="dark"]`.

**Summary:** `data-theme` attribute is always kept in sync (for external consumers), but `.dark` class is the canonical trigger for Tailwind utilities and SCSS dark overrides. See `ui-ux` skill for dark mode styling rules (color tokens, xs-no-transition usage).

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

## 7. View Layout & Design System Contract

> **Design system component tokens (`xs-card`, `xs-btn`, `xs-actions-container`) are owned by the `ui-ux` skill.** This section covers layout-level contracts only.


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

### 7.5 Appointments Toolbar — Mobile Two-Tier Layout

`app/Views/appointments/index.php` uses a two-tier stacked layout inside `header_controls`:

- **Outer wrapper:** `appointments-toolbar flex flex-col gap-2 md:gap-3` (tighter tier gap on phones).
- **Tier 1** (`flex flex-col gap-1.5 md:flex-row md:justify-between`):
  - **Nav cluster** (`appointments-toolbar__primary`): Row 1 = Today button + view switcher; Row 2 = date-navigation cluster (prev / label / next / datepicker). Collapses to one row at `md+`.
  - **Right controls:** Overview panel toggle (`#scheduler-panel-toggle`, shown below 1300px) + Filters toggle (`#advanced-filter-toggle`, desktop-only `hidden md:inline-flex`).
- **Tier 2** (`appointments-toolbar__secondary`): status filter chips (`#scheduler-stats-bar`) + provider legend (`#provider-legend`, `hidden md:flex`).

**View switcher pills are text-only and uniform** — `Agenda | Day | Week | Month`, each `px-3.5 py-2 rounded-full ... view-toggle-btn`. Do not give any one pill an icon (it unbalances the group).

**Status chips = one horizontally-scrollable row on mobile:** `#scheduler-stats-bar` is `flex-nowrap overflow-x-auto ... md:flex-wrap`; the `.appointments-toolbar__status-rail` SCSS hides the scrollbar and sets children `flex-shrink: 0`. Do **not** wrap or vertically stack them on mobile — that adds header height. (The "no single `overflow-x-auto` row" caveat applies only to the **Tier-1 date-cluster** rail, which caused overflow — not to this status rail.)

**Mobile control height:** ~40px (`min-height: 2.5rem` in the `max-width: 767px` block) — a deliberate density trade-off to reclaim header space; the header height is `position: fixed` + ResizeObserver-measured (`app-layout-init.js` → `--xs-header-height`), so trimming the toolbar directly enlarges the list area.

**Agenda date is toolbar-sourced only.** The agenda list (`scheduler-agenda-view.js`) renders **no** sticky date heading; the always-visible toolbar date label is the single source, made agenda-aware by the `agenda` case in `date-nav-label.js` (`"Today · Mon, 20 Jul"` / short day). Do not reintroduce a duplicate heading inside the list.

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

## 13. Settings Integrations Hub (Owner Section)

`app/Views/settings/tabs/integrations.php` is the Integrations tab. Its full contents are wrapped in `<section id="panel-integrations" class="tab-panel hidden">` — the tab-switcher in `spa.js` toggles `hidden` on this element. **Do not render cards or modals outside this section** or they will be permanently visible across all settings tabs.

### Integration Card Grid

Seven integration cards rendered as a 2-column `grid grid-cols-1 md:grid-cols-2 gap-4` inside `#integration-cards-grid`. **Analytics is the first card** — it replaced the old analytics `<form>` wrapper that previously sat above the grid.

| Card | Icon color | Modal | OAuth flow |
| --- | --- | --- | --- |
| Analytics | orange | `#analytics-modal` | — |
| Webhooks | indigo | `#webhook-modal` | — |
| Google Calendar | red | `#google-calendar-modal` | `/oauth/google/authorize` |
| Stripe | purple | `#stripe-modal` | — |
| Zoom | blue | `#zoom-modal` | — |
| Jitsi Meet | teal | `#jitsi-modal` | — |
| PayFast | green | `#payfast-modal` | — |

**There is no analytics form in `integrations.php`**. The old `<form id="integrations-settings-form">` was removed. `'integrations'` was also removed from `SETTINGS_TABS` in `settings-form-ui.js`. The analytics card's save handler in `integration-hub.js::wireAnalytics()` posts directly to `POST /api/v1/settings` with `integrations.analytics`, `integrations.analytics_id`, and `integrations.analytics_site_id`.

### Card States

Each card renders one of three states based on PHP variables from `IntegrationSettingsService::getIndexData()`:
1. **No credentials** — Configure button only
2. **Credentials saved, no OAuth tokens** — Configure + Connect button (Google Calendar only)
3. **Connected** — Configure + Test + Disconnect buttons

### JS Module

`resources/js/modules/settings/integration-hub.js` exports `initIntegrationHub()` (called from `app.js`).

- `wireModals()` — backdrop click, Escape key, open/close data attributes
- `wireAnalytics()` — posts `integrations.analytics` / `integrations.analytics_id` / `integrations.analytics_site_id` to `POST /api/v1/settings`; shows/hides GA4 vs Matomo fields on provider select change
- One `wire*()` function per integration (reads fields, calls `callApi()`, shows toast, reloads on success)
- `callApi(intent, channel, body)` — wraps `apiRequest()` from `core/api.js` to the `/api/v1/integrations/{save|test|disconnect}` endpoints
- `wireActionButtons()` — global handler for `[data-integration-action]` Test/Disconnect buttons

**Google Calendar copy button:** `#gc-copy-redirect-uri` copies the read-only redirect URI to the clipboard via `navigator.clipboard`. The redirect URI is auto-generated via `GoogleCalendarIntegrationService::getRedirectUri()` (`base_url('oauth/google/callback')`).

### Analytics Script Injection

`analytics_head_html()` in `app/Helpers/app_helper.php` reads `integrations.analytics`, `integrations.analytics_id`, and `integrations.analytics_site_id` from `xs_settings` and returns the appropriate script block:

- **GA4:** injects `gtag.js` with `send_page_view: true`; sets `window.__xsAnalyticsId` for SPA listener
- **Matomo:** injects Matomo tracking script with URL + site ID

**Both layouts call `analytics_head_html()` in `<head>`:**
- `layouts/public.php` — covers all public booking pages including SEO slug pages (`/booking/p/{slug}`, `/booking/s/{slug}`)
- `layouts/app.php` — covers all authenticated views; also includes a `spa:navigated` listener that fires `gtag('event','page_view',...)` / `_paq.push(['trackPageView'])` on every SPA navigation

**Settings keys** (all in `xs_settings`, no migration needed):
- `integrations.analytics` — provider: `none` / `google` / `matomo`
- `integrations.analytics_id` — GA4: `G-XXXXXXXXXX`; Matomo: the Matomo instance URL
- `integrations.analytics_site_id` — Matomo site ID (integer; empty for GA4)

## 14. Settings Live-Sync Contract (Owner Section)

Settings save through a JSON API `POST` (`apiRequest`) with **no page navigation**. SPA navigation only swaps `#spa-content` (§3), so anything rendered in the **persistent layout shell** (sidebar, `<head>`) or cached in a **JS singleton** does NOT update on save — nor even on SPA navigation. It updates only via a live DOM sync or a full reload. We do **not** full-reload after a normal settings save; we live-sync.

**Every settings form must, on a successful save:**

1. Dispatch `document.dispatchEvent(new CustomEvent('settingsSaved', { detail: <changed-keys[]> }))`. This includes the **General form** — historically only the tab forms (`initTabForm`) dispatched it, so general-settings consumers were silently stale. `initGeneralSettingsForm` now dispatches it too (including `general.company_logo` / `general.company_icon` when those upload).
2. Live-sync any persistent chrome / cached singleton the saved keys affect (table below) — never depend on the user reloading.

**Surfaces that are NOT SPA-refreshable (must be live-synced):**

| Setting | Consumer outside `#spa-content` | Live-sync action | Owner |
| --- | --- | --- | --- |
| `general.company_logo` | Sidebar logo `#main-sidebar .brand-logo` (placeholder div when unset) | swap `<img>.src`; replace placeholder with a fresh `<img>` on first upload (`syncSidebarLogo`) | `settings-form-ui.js` |
| `general.company_icon` | Favicon `<link rel="icon">` in `<head>` | set `link.href`, drop stale `type` (`syncFavicon`) | `settings-form-ui.js` |
| `general.company_name` | Sidebar `#sidebarBrandName` | updated on input by `wireSidebarBrandSync()` | `settings-form-ui.js` |
| `localization.currency*` | `window.currencyFormatter` singleton (one-shot `loaded` guard) | `currencyFormatter.refresh()` on `settingsSaved` | `app.js` handler |
| `localization.timezone`, business hours | scheduler `settingsManager` + `window.appTimezone` | `settingsManager.refresh()` + `refreshAndRender()` on `settingsSaved` | `app.js` handler |

The `app.js` `settingsSaved` handler owns **cross-view singleton refresh** (currency on any page; scheduler when present). **Branding chrome** (logo/favicon) is synced directly in `settings-form-ui.js` from the upload-response `url` — upload filenames are unique per upload (`logo_<ts>_<rand>.ext`), so `<img>.src` swaps are cache-safe.

**Rule:** a new setting consumed anywhere outside the settings panel must be added to this table with its live-sync path. A setting that only shows correctly after a manual refresh is a regression against this contract.

## 15. Pre-Merge Frontend Grep Check

```bash
# Detect inline style regressions in views
rg "style=\"|<style>" app/Views resources/js
```

Any result must be reviewed.

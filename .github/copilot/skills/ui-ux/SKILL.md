---
name: webscheduler-ui-ux
description: WebScheduler UI/UX contract — Material Symbols icon system (font loading per page type, variable font settings, canonical span usage), icon SCSS utilities (_icons.scss), design system component tokens (xs-card, xs-btn, xs-actions-container), dark mode styling rules, delivery mode UI (DELIVERY_MODE_META, icon names, pill button pattern), animation and transition conventions (xs-no-transition, transition-colors), and per-row action button pattern. Use whenever you're working on icon rendering, component styling, dark mode CSS, delivery mode badges/selectors, button patterns, or any UI pattern decision. Triggers on "icon", "material symbols", "xs-card", "xs-btn", "delivery mode badge", "dark mode css", "transition", "animation", "button pattern", "pill button", or when adding icons to any view or JS component.
---

# WebScheduler — UI/UX Contract

## 1. Icon System (Owner Section)

### 1.1 Two Font Families

| Class | Font family | Use |
|---|---|---|
| `material-symbols-outlined` | Material Symbols Outlined | Default — all icons in views, JS components, and booking SPA |
| `material-symbols-rounded` | Material Symbols Rounded | Sidebar navigation, rounded-style icon surfaces |

Both are variable fonts controlled by `font-variation-settings` (FILL, wght, GRAD, opsz). Do not mix families on the same surface unless intentional.

### 1.2 Font Loading Requirement (Critical)

The CSS class definitions are compiled locally in `resources/scss/utilities/_icons.scss` but the **glyph data** comes from `fonts.gstatic.com`. Without the Google Fonts link, icon names render as text literals.

Every page that renders `<span class="material-symbols-outlined">` MUST include:
```html
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
```

**Coverage by page type:**

| Page | Font loaded by |
|---|---|
| Authenticated app pages | `layouts/app.php` `<head>` (both Outlined + Rounded) |
| Public booking routes using `layouts/public.php` | `layouts/public.php` `<head>` (Outlined only) |
| `app/Views/public/booking.php` (standalone SPA) | Explicit `<link>` in its own `<head>` |
| Auth pages (`layouts/auth.php`) | `layouts/auth.php` `<head>` |
| Setup wizard (`components/setup-layout.php`) | `components/setup-layout.php` `<head>` |

**If you add a new standalone page** (one that does NOT extend a standard layout), you must add the font link explicitly.

### 1.3 Canonical Icon Usage

```html
<span class="material-symbols-outlined text-base">icon_name</span>
```

- Icon name as **text content** — this is how Material Symbols variable fonts work
- Size controlled by Tailwind text utilities (`text-xs`, `text-sm`, `text-base`, `text-lg`, `text-xl`)
- Do NOT use `::before` pseudo-element patterns — these belong to legacy icon fonts

### 1.4 Common Icon Names (WebScheduler)

| Purpose | Icon name |
|---|---|
| Appointment / event | `event_note` |
| Schedule / clock | `schedule` |
| Location / in-person | `location_on` |
| Video meeting (Zoom) | `video_call` |
| Video meeting (Jitsi) | `videocam` |
| Provider / badge | `badge` |
| Customer / person | `person_outline` |
| Service | `room_service` |
| Dark mode toggle | `dark_mode` / `light_mode` |
| Edit | `edit` |
| Delete | `delete` |
| Close | `close` |
| Check / confirmed | `check_circle` |
| Cancelled | `cancel` |
| Pending | `pending` |
| Add | `add` |
| Settings | `settings` |
| Placeholder / empty state | `category` / `info` |

---

## 2. Icon SCSS Utilities (`resources/scss/utilities/_icons.scss`)

### 2.1 Base Classes

`.material-symbols-outlined` and `.material-symbols-rounded` define font rendering properties (font-feature-settings, smoothing, display, variation-settings). These are declared locally so the classes work even if the Google Fonts CSS hasn't resolved yet (the glyphs still require the font file, but at least class specificity is correct).

### 2.2 `.material-icon` Component Class

Provides size and weight modifier classes:

| Modifier | Font size | opsz |
|---|---|---|
| `.xs` | 16px | 20 |
| `.sm` | 20px | 20 |
| `.md` | 24px | 24 (default) |
| `.lg` | 32px | 40 |
| `.xl` | 48px | 48 |

Weight modifiers: `.thin` (100), `.light` (200), `.regular` (400), `.medium` (500), `.bold` (700)

**Prefer Tailwind size utilities** (`text-xs`, `text-sm`) over `.material-icon.xs/sm` — they're more composable with the rest of the design system.

### 2.3 `.icon` Aliases — Do Not Use

`_icons.scss` defines `.icon.schedule`, `.icon.event`, etc. via `::before { content: 'glyph_name' }`. These are **not used** in production views — the `::before` pattern conflicts with standard Material Symbols usage. Always use the direct span pattern (`§1.3`).

---

## 3. Design System Component Tokens (Owner Section)

These tokens are defined in the compiled SCSS. Use them for all new views and components in `layouts/app` surfaces.

### 3.1 Card Tokens

| Token | Element | Notes |
|---|---|---|
| `xs-card` | Card container | Replaces `card card-spacious` |
| `xs-card-header` | Card header bar | Use with `xs-card-header-content` (left) + `xs-card-actions` (right) |
| `xs-card-header-content` | Left slot of card header | Contains title + subtitle |
| `xs-card-title` | Title inside header content | |
| `xs-card-subtitle` | Subtitle inside header content | |
| `xs-card-actions` | Right slot of card header | Icon buttons, secondary controls |
| `xs-card-body` | Card content area | Add `p-0` for full-bleed tables; `xs-card-body-compact` for tighter (~`p-4`) padding |
| `xs-card-stat` + `xs-stat-label` + `xs-stat-value` | KPI/metric tile | Extends `xs-card` with built-in `p-6`-equivalent padding; do not also add `p-6` |

`xs-card`'s radius comes from `--xs-radius-lg` (1rem) — visually equivalent to Tailwind `rounded-2xl`,
**not** `rounded-lg` (0.5rem). When converting a raw-Tailwind card (`rounded-2xl border border-gray-200
bg-white ... shadow-sm dark:border-gray-700 dark:bg-gray-800`) to `xs-card`, you can usually keep any
existing padding utility (`p-6`, `p-3`, `p-5`) on the same element — `xs-card` itself sets only
background/border/radius/shadow, no padding.

### 3.1.1 Brand Color Tokens for CTAs (Owner Section)

Primary actions, links, active tab/filter states, and "unread" indicators must use the brand
**`primary-*`** scale (`#003049`, Tailwind `primary-500` / hover `primary-600` / light-on-dark
`primary-300`/`primary-400`) — **not** `blue-600`/`blue-700`/`bg-gray-900`.

Danger/destructive actions (cancel, delete, failed-status emphasis) must use the brand
**`error-*`** scale (`#D62828`, Tailwind `error-500` / hover `error-600` / dark `error-400`/`error-300`).
Note: `tailwind.config.js` defines this scale as `error-*`, not `danger-*` — "danger" is the
conceptual name only.

This rule applies to **CTAs and interactive elements** (buttons, links, active states, unread
markers). It does **not** apply to informational/categorical color-coding (channel badges, status
badges, per-notification-type icon colors, chart accent colors) — those may keep their existing
semantic palette (blue/green/amber/purple/etc. per category) since they encode meaning, not brand.

`app/Views/notifications/index.php` is the reference implementation of both `xs-card`/`xs-card-stat`
and the `primary-*`/`error-*` CTA convention (migrated 2026-06-10). Many older views (e.g.
`profile/index.php`) still use the pre-migration `rounded-2xl` + `bg-blue-600` pattern — that is
known debt, not the target to copy from.

### 3.2 Button Tokens

| Token | Element | Notes |
|---|---|---|
| `xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon` | Icon-only action button | Replaces text+icon `btn btn-secondary btn-sm` |
| `xs-btn xs-btn-sm xs-btn-primary` | Primary small button | |
| `xs-btn xs-btn-sm xs-btn-ghost` | Secondary ghost button | |

### 3.3 Per-Row Action Button Pattern (Owner Section)

All table action columns use icon-only buttons inside `xs-actions-container`. **Do not add visible text labels to row actions.**

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

---

## 4. Dark Mode Styling Rules

- Tailwind `dark:` utilities activate when `.dark` is on `<html>` — set by the inline blocking script, maintained by `DarkModeManager`
- SCSS dark overrides: use `.dark { ... }` selector, **NOT** `[data-theme="dark"]`
- `color-scheme` property is set on `<html>` by the blocking script and `applyTheme()` — controls system UI (scrollbars, native form inputs)
- `transition-colors duration-200` belongs on `<body>` (for user-triggered theme switches), **not** on `<html>`
- See `frontend` skill §4 for the full FOUC mechanism (`xs-no-transition`, inline script, cleanup)

---

## 5. Delivery Mode UI (Owner Section)

### 5.1 Canonical `DELIVERY_MODE_META` (defined in `resources/js/public-booking.js`)

```js
const DELIVERY_MODE_META = {
  onsite:       { label: 'In Person', icon: 'location_on',  selCls: 'border-blue-300 bg-blue-50 text-blue-700',     defCls: 'border-slate-200 bg-white text-slate-700' },
  online_zoom:  { label: 'Zoom',      icon: 'video_call',   selCls: 'border-purple-300 bg-purple-50 text-purple-700', defCls: 'border-slate-200 bg-white text-slate-700' },
  online_jitsi: { label: 'Jitsi Meet', icon: 'videocam',    selCls: 'border-teal-300 bg-teal-50 text-teal-700',      defCls: 'border-slate-200 bg-white text-slate-700' },
};
```

### 5.2 Delivery Mode Selector (Pill Buttons)

Each mode renders as a `<button>` with icon + label:
```html
<button class="{selCls|defCls} rounded-full border px-3 py-1.5 text-sm flex items-center gap-1.5">
  <span class="material-symbols-outlined text-base">{meta.icon}</span>
  {meta.label}
</button>
```

### 5.3 Service Card Delivery Mode Badges

Compact version with `text-xs` icon inside a rounded badge:
```html
<span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs {meta.cls}">
  <span class="material-symbols-outlined text-xs">{meta.icon}</span>
  {meta.label}
</span>
```

### 5.4 Rules

- Always use `material-symbols-outlined` for delivery mode icons
- `location_on` = in-person; `video_call` = Zoom; `videocam` = Jitsi Meet
- For notification templates, use `{session_info}` placeholder — see `notifications` skill §11.10
- For admin appointment form, delivery mode selector is JS-rendered and matches these color conventions

### 5.5 Analytics "Appointments by Delivery Mode" Chart

`resources/js/modules/analytics/analytics-charts.js`'s `initDeliveryModeChart()` renders a doughnut
chart on the Analytics Overview tab using the same conceptual onsite/Zoom/Jitsi split as
`DELIVERY_MODE_META`, with colors `#3b82f6` (blue, in-person) / `#8b5cf6` (purple, Zoom) / `#14b8a6`
(teal, Jitsi). The companion legend list in `app/Views/analytics/index.php` uses the canonical
`location_on` / `video_call` / `videocam` icons. If `DELIVERY_MODE_META`'s colors change, update
this chart's palette to match. Data comes from `AppointmentModel::getDeliveryModeStats()`.

---

## 6. Animation & Transition Conventions

| Convention | Rule |
|---|---|
| `transition-colors duration-200` | Use on `<body>`, interactive elements (buttons, links, cards) for smooth theme transitions |
| `<html>` tag | Never put `transition-colors` here — it carries no `background-color` |
| `xs-no-transition` class | Set by FOUC blocking script; removed after first paint. Never reference in app logic or new CSS rules |
| Double `requestAnimationFrame` | Pattern for any post-paint initialization that reads layout properties (e.g. `offsetHeight`) |
| `transition-all` | Avoid — use specific properties (`transition-colors`, `transition-transform`) to prevent unintended layout thrashing |

---

## 7. Cross-Skill References

- Full FOUC mechanism + blocking script → `frontend` skill §4
- Delivery mode notification rendering → `notifications` skill §11.10
- Public booking standalone page asset contract (font loading) → `public-booking` skill §9
- Setup wizard asset contract (font loading) → `setup` skill §3

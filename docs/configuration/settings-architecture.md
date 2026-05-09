# Settings Page — Architecture Guide

**Version:** v96  
**Last updated:** 2025-07

---

## 1. Overview

The Settings page uses a **tab-based modular architecture** where a single shell file (`settings/index.php`) includes 8 tab partial files. All tabs are loaded simultaneously (not lazy-loaded) to preserve the existing SPA tab-switching behavior.

```
app/Views/settings/
├── index.php                  # Shell: layout, flash, tabs, modals, JS
└── tabs/
    ├── general.php            # Company name, email, phone, logo
    ├── localization.php       # Time format, language, currency, timezone
    ├── booking.php            # Standard + custom booking fields
    ├── business.php           # Work hours, breaks, blocked periods
    ├── legal.php              # Cookie, terms, privacy, policies
    ├── integrations.php       # Webhooks, analytics, API, LDAP
    ├── notifications.php      # Email/SMS/WhatsApp, rules, templates
    └── database.php           # DB info (read-only) + backup mgmt
```

---

## 2. Architecture Diagram

```
┌──────────────────────────────────────────────────────┐
│  layouts/app.php                                     │
│  ┌────────────────────────────────────────────────┐  │
│  │  settings/index.php                            │  │
│  │  ┌──────────────────────────────────────────┐  │  │
│  │  │  Flash Messages (error + success)        │  │  │
│  │  ├──────────────────────────────────────────┤  │  │
│  │  │  Tab Navigation (8 buttons)              │  │  │
│  │  ├──────────────────────────────────────────┤  │  │
│  │  │  #settings-content                       │  │  │
│  │  │  ┌─────────────────────────────────────┐ │  │  │
│  │  │  │  tabs/general.php      (form)       │ │  │  │
│  │  │  │  tabs/localization.php (form)       │ │  │  │
│  │  │  │  tabs/booking.php     (form)        │ │  │  │
│  │  │  │  tabs/business.php    (form)        │ │  │  │
│  │  │  │  tabs/legal.php       (form)        │ │  │  │
│  │  │  │  tabs/integrations.php(form)        │ │  │  │
│  │  │  │  tabs/notifications.php(form)       │ │  │  │
│  │  │  │  tabs/database.php    (panel)       │ │  │  │
│  │  │  └─────────────────────────────────────┘ │  │  │
│  │  ├──────────────────────────────────────────┤  │  │
│  │  │  Backup List Modal (fixed overlay)       │  │  │
│  │  │  Block Period Modal (fixed overlay)      │  │  │
│  │  ├──────────────────────────────────────────┤  │  │
│  │  │  <script> — minimal bootstrap only        │  │  │
│  │  └──────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘
```

---

## 3. Data Flow

### Controller → View

```
Settings::index()
  ├── SettingModel->getByKeys([...])  →  $settings (70+ keys)
  ├── BusinessNotificationRuleModel->... →  $notificationRules
  ├── BusinessIntegrationModel...       →  $notificationEmailIntegration
  ├── BusinessIntegrationModel...       →  $notificationSmsIntegration
  ├── BusinessIntegrationModel...       →  $notificationWhatsAppIntegration
  ├── various helpers                 →  $notificationIntegrationStatus
  ├── WhatsApp template mapping       →  $notificationWhatsAppTemplates
  ├── loadMessageTemplates()          →  $notificationMessageTemplates
  ├── DeliveryLog query               →  $notificationDeliveryLogs
  └── return view('settings/index', $data)
```

All `$data` keys are available in every tab partial because CI4's `$this->include()` shares the parent view's data scope.

### Form Submission

| Tab | Method | Target | Handler |
|---|---|---|---|
| General | JS (fetch) | `PUT /api/v1/settings` | `SettingsApi::update()` |
| Localization | JS (fetch) | `PUT /api/v1/settings` | `SettingsApi::update()` |
| Booking | JS (fetch) | `PUT /api/v1/settings` | `SettingsApi::update()` |
| Business | JS (fetch) | `PUT /api/v1/settings` | `SettingsApi::update()` |
| Legal | JS (fetch) | `PUT /api/v1/settings` | `SettingsApi::update()` |
| Integrations | JS (fetch) | `PUT /api/v1/settings` | `SettingsApi::update()` |
| Notifications | POST | `/settings/notifications` | `Settings::saveNotifications()` |
| Database | — | No form | JS-driven API calls |

---

## 4. Tab Partial Contract

Each tab partial file must follow these rules:

1. **No layout declarations** — no `$this->extend()`, no `$this->section()`
2. **Self-contained HTML** — each file wraps its content in a `<form>` tag (or `<section>` for database)
3. **Tab panel class** — root element must have `class="tab-panel hidden"` and `id="panel-{tab_name}"`
4. **CSRF token** — every form must include `<?= csrf_field() ?>`
5. **Form source** — every form must include `<input type="hidden" name="form_source" value="...">`
6. **SPA attribute** — every form must include `data-no-spa="true"`
7. **Tab form attribute** — every form must include `data-tab-form="{tab_name}"`
8. **No business logic** — PHP in partials is limited to rendering (esc, conditionals, loops)
9. **Available variables** — all controller `$data` keys are available via CI4 view scope

### Template

```php
<?php
/**
 * Settings Tab: {Tab Name}
 * Brief description.
 */
?>
            <form id="{tab}-settings-form"
                  method="POST"
                  action="<?= base_url('api/v1/settings') ?>"
                  class="mt-4 space-y-6"
                  data-tab-form="{tab}"
                  data-no-spa="true">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="{tab}_settings">
                <section id="panel-{tab}" class="tab-panel hidden">
                    <!-- Tab content here -->
                </section>
            </form>
```

---

## 5. JavaScript Architecture

Settings JavaScript lives in two external ESM modules loaded via `app.js`:

| File | Lines | Purpose |
|---|---|---|
| `resources/js/modules/settings/settings-page.js` | 594 | Core API form submission, tab management, `spa:navigated` hooks, blocked periods, backup UI |
| `resources/js/modules/settings/settings-form-ui.js` | 659 | Form lock/unlock flow, field validation helpers, WhatsApp provider toggle, SMS char counter, time format preview |

`settings/index.php` itself is ~99 lines — it contains only the view structure and tab includes. No inline `<script>` block.

---

## 6. Adding a New Tab

1. Create `app/Views/settings/tabs/{newtab}.php` following the template above
2. Add `<?= $this->include('settings/tabs/{newtab}') ?>` in `index.php` inside `#settings-content`
3. Add a tab button in the `<nav>` in `index.php`:
   ```html
   <button data-tab="{newtab}" class="...">New Tab</button>
   ```
4. Add any required JS initialization as a new IIFE in the `<script>` block
5. Pass any new view data from the controller's `index()` method
6. The SPA tab system (`initTabsInSpaContent()`) will automatically discover the new panel

---

## 7. Settings Key Prefixes

Settings are stored as key-value pairs with dot-notation prefixes:

| Prefix | Tab | Examples |
|---|---|---|
| `general.*` | General | `general.company_name`, `general.company_email` |
| `localization.*` | Localization | `localization.time_format`, `localization.timezone` |
| `booking.*` | Booking | `booking.field_first_names_display`, `booking.custom_field_1_enabled` |
| `business.*` | Business | `business.work_hours`, `business.blocked_periods` |
| `legal.*` | Legal | `legal.cookie_notice_enabled`, `legal.terms_text` |
| `integrations.*` | Integrations | `integrations.webhook_url`, `integrations.analytics_provider` |
| `notifications.*` | Notifications | `notifications.default_language` |
| `database.*` | Database | `database.backup_enabled` (stored via API) |

---

## 8. Related Files

| File | Purpose |
|---|---|
| `app/Controllers/Settings.php` | Main controller (index, saveNotifications) |
| `app/Controllers/Api/V1/Settings.php` | API controller for AJAX form saves (`PUT /api/v1/settings`) |
| `app/Models/SettingModel.php` | `getByKeys()`, `upsert()` for key-value storage |
| `app/Services/NotificationTemplateService.php` | Template rendering and preview |
| `app/Models/BusinessNotificationRuleModel.php` | Event → channel rule storage |
| `app/Models/BusinessIntegrationModel.php` | Email/SMS/WhatsApp integration config |
| `resources/js/modules/settings/settings-page.js` | Core settings JS (594 lines) |
| `resources/js/modules/settings/settings-form-ui.js` | Form UI helpers (659 lines) |
| `resources/js/spa.js` | SPA navigation + tab switching |
| `resources/scss/components/_buttons.scss` | Button component classes used in forms |

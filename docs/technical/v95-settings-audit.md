# v95 — Settings Page Audit & Fix Report

**Version:** v95  
**Date:** 2025-07-14  
**Scope:** `app/Views/settings.php`, SPA initialization, button CSS consistency  

---

## Critical Bug Fix

### Settings > General Tab — Edit Button Causes Page Refresh

**Symptom:** Clicking the Edit button on the General tab appeared to refresh the page instead of unlocking the form for editing. The form only became editable after a manual browser refresh (F5/Cmd+R).

**Root Causes Identified:**

1. **Missing `spa:navigated` fallback** — The inline `initSettingsApi()` call runs during SPA script injection (step 2 of the SPA pipeline), but there was no fallback for the `spa:navigated` event (step 5), which fires after `initTabsInSpaContent()` completes and the DOM is fully settled. If the inline call encountered any timing quirk, the edit button listener was never attached.

2. **`saveBtn?.focus()` in Edit handler** — When the Edit button click handler fired, it called `saveBtn?.focus()` which caused the browser to scroll to the Save button at the bottom of the form. This scroll was perceived as a "page refresh" by the user.

**Fixes Applied:**

| Fix | Description |
|-----|-------------|
| `spa:navigated` safety net | Added a `spa:navigated` event listener (with proper cleanup to prevent accumulation) that retries `initSettingsApi()` after the DOM is fully settled. |
| Focus first field | Replaced `saveBtn?.focus()` with `lockableFields[0]?.focus()` so the first editable field receives focus — better UX and no unexpected scroll. |
| Retain RAF fallback | Kept the existing `requestAnimationFrame` retry as an additional safety net. |

**Location:** `app/Views/settings.php` — lines 2108–2145 (init call site), lines 2320–2336 (edit handler)

---

## Button CSS Standardisation (settings.php)

Replaced raw inline Tailwind utility classes on buttons with project-standard `btn btn-*` component classes defined in `resources/scss/components/_buttons.scss`.

| Element ID | Before (inline Tailwind) | After |
|---|---|---|
| `#general-edit-btn` | `px-3 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition` | `btn btn-secondary` |
| `#general-cancel-btn` | Same as edit + `hidden` | `btn btn-ghost hidden` |
| `#reset-templates-btn` | `px-4 py-2 mr-3 rounded-lg text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700` | `btn btn-ghost mr-3` |
| `#create-backup-btn` | `px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium inline-flex items-center gap-2 transition-colors` | `btn btn-primary inline-flex items-center gap-2` |
| `#view-backups-btn` | `px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-gray-100 rounded-lg text-sm font-medium inline-flex items-center gap-2 transition-colors` | `btn btn-secondary inline-flex items-center gap-2` |
| `#block-period-cancel` | `btn btn-outline` (undefined class) | `btn btn-ghost` |
| `#save-general-btn` | `btn-submit opacity-60 cursor-not-allowed` (redundant with `:disabled` CSS) | `btn-submit` (`:disabled` handles the visual state) |

---

## Orphaned Class: `btn-outline`

The CSS class `btn-outline` was used at line 1539 on the blocked-period Cancel button but **is not defined** in any stylesheet:

- Not in `resources/scss/components/_buttons.scss`
- Not in `resources/css/scheduler.css`
- Not in any other SCSS file

Referenced aspirationally in `docs/technical/CSS_CONSOLIDATION_GUIDE.md` but never implemented.

**Fix:** Replaced with `btn btn-ghost` (defined, tested, appropriate for cancel actions).

---

## Audit Findings

### Settings.php Structure (3,144 lines)

| Section | Lines | Description |
|---|---|---|
| PHP view header (layout extend, sections) | 1–9 | CI4 view structure |
| Flash messages | 10–48 | Conditional success/error alerts |
| Tab navigation | 50–63 | 8 tabs: General, Localization, Booking, Business, Legal, Integrations, Notifications, Database |
| General form | 68–152 | Edit/Cancel/Save with API-driven AJAX |
| Localization form | 155–262 | Time format, first day, language, timezone, currency |
| Booking form | 264–423 | Customer fields, custom fields 1–6 |
| Business form | 425–516 | Hours, breaks, blocked periods |
| Legal form | 518–567 | Cookie notice, terms, privacy, cancellation, rescheduling |
| Integrations form | 569–615 | Webhooks, analytics, API integrations, LDAP |
| Notifications form | 617–1390 | Email/SMS/WhatsApp config, templates, delivery logs |
| Database panel | 1393–1495 | DB info, backup management |
| JS: Shared helpers | 1555–1585 | `xsDebugLog`, `xsGetCsrf`, `xsEscapeHtml` |
| JS: WhatsApp toggle | 1588–1612 | Provider section visibility |
| JS: Template tabs | 1615–1685 | Notification template tab switching |
| JS: Blocked periods | 1687–2063 | Add/edit/delete periods CRUD |
| JS: Brand sync | 2071–2088 | Live-updates sidebar brand name |
| JS: Main API init | 2091–2145 | `initSettingsApi()` + safety nets |
| JS: General form logic | 2146–2583 | Edit/save/cancel with file upload support |
| JS: Tab form generic | 2586–2756 | `initTabForm()` for non-General tabs |
| JS: Custom field toggles | 2759–2786 | Enable/disable custom field sections |
| JS: Time formatting | 2788–2800 | Async time format handler integration |
| JS: Database tab | 2801–3118 | Backup create/view/status with modal |

### Naming Conventions

| Convention | Usage | Status |
|---|---|---|
| **kebab-case** IDs | UI elements: `general-edit-btn`, `save-general-btn`, `block-period-form` | ✅ Consistent |
| **snake_case** IDs | Form fields matching PHP names: `company_logo`, `whatsapp_provider` | ✅ Consistent (intentional) |
| **camelCase** JS functions | `initSettingsApi`, `setLockedState`, `updateSaveButtonState` | ✅ Consistent |
| **camelCase** JS variables | `lockableFields`, `hasChanges`, `initialValues` | ✅ Consistent |

### Data Guards (SPA-safe initialisation)

All initialisation functions use `dataset` guards to prevent duplicate event listener registration:

| Guard | Location | Purpose |
|---|---|---|
| `form.dataset.apiWired === 'true'` | `initGeneralSettingsForm()`, `initTabForm()` | Prevents double-wiring of general + tab forms |
| `providerSelect.dataset.toggleWired === 'true'` | WhatsApp toggle IIFE | Prevents double-wiring provider select |
| `tab.dataset.templateTabWired === 'true'` | Template tab IIFE | Prevents double-wiring template tabs |
| `input.dataset.brandSync === 'true'` | Brand sync IIFE | Prevents double-wiring brand name input |
| `tablist.dataset.tabsInitialized === 'true'` | `initTabsInSpaContent()` (spa.js) | Prevents double-wiring main settings tabs |
| `btn.dataset.dbTabWired === 'true'` | Database tab init | Prevents double-wiring database tab button |

### All Forms Have `data-no-spa="true"` ✅

All 7 settings forms correctly opt-out of SPA form submission interception.

---

## Known Issues (Deferred)

### Inline Tailwind on Buttons (Other Views)

The following views have buttons with raw inline Tailwind instead of `btn btn-*` component classes. These are cosmetic inconsistencies — not bugs — and should be addressed in a future cleanup pass:

| File | Elements |
|---|---|
| `app/Views/categories/form.php` | Cancel link (line 79), Submit button (line 80) |
| `app/Views/customer-management/edit.php` | Submit button (line 174) |
| `app/Views/customer-management/create.php` | Submit button (line 173) |
| `app/Views/customer-management/history.php` | Filter button (line 185) |
| `app/Views/dashboard/landing.php` | Calendar link (line 51) |
| `app/Views/profile/index.php` | Save Profile (line 234), Change Password (line 312) |
| `app/Views/appointments/index.php` | Apply Filters (line 194), Clear Filters (line 198) |
| `app/Views/setup.php` | Support link (line 40), Test Connection (line 305) |

### `btn-outline` (Aspirational)

The `btn-outline` class is listed in `docs/technical/CSS_CONSOLIDATION_GUIDE.md` as a planned component but not yet implemented in `_buttons.scss`. Should be implemented when needed, or removed from the guide.

---

## Files Changed

| File | Changes |
|---|---|
| `app/Views/settings.php` | Edit button SPA fix, inline CSS → component classes, orphaned `btn-outline` fix, redundant save button classes cleanup |

## Verification

- PHP lint: ✅ Clean
- Vite build: (pending v95 build)

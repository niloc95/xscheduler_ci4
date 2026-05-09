# Provider Schedule Component — Audit Report

**Date:** 2025-02-19  
**Component:** `app/Views/user-management/components/provider-schedule.php`  
**Scope:** Full audit — duplication, redundancy, inconsistency, naming, inline CSS, orphaned code

---

## Summary

The provider schedule component was audited and refactored to:
- Switch from `type="text"` with manual parsing to native `type="time"` inputs
- Replace inline Tailwind classes with the project's `form-input` class
- Remove ~80 lines of redundant JavaScript (normalisation, 12h/24h parsing)
- Remove 140+ lines of orphaned PHP methods in UserManagement.php
- Consolidate `togglePassword()` into a single global definition

---

## Findings & Resolutions

### 1. INCONSISTENCY — Time Input Type (HIGH)

| Location | Input Type | Styling |
|----------|-----------|---------|
| `settings.php` (Business Hours) | `type="time"` | `form-input` |
| `provider-schedule.php` (Before) | `type="text"` + manual pattern | 12-class inline Tailwind |
| `provider-schedule.php` (After) | `type="time"` | `form-input` |

**Resolution:** Switched all schedule time inputs to `type="time"` with `form-input` class, matching the settings page. Browser renders the native time picker (wheel on iOS, clock on Android), handling 12h/24h display per user locale automatically.

### 2. REDUNDANT — Inline Tailwind Classes (MEDIUM)

28 time inputs (4 fields × 7 days) each repeated:
```
mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900
focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500
dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100
```

**Resolution:** Replaced with `form-input mt-1` — the existing project class from `resources/scss/components/_forms.scss`.

### 3. REDUNDANT — Manual JS Parsing (MEDIUM)

| Function | Lines | Purpose | Still needed? |
|----------|-------|---------|---------------|
| `normaliseInputValue()` | 15 | Pad/format text input values | ❌ Removed |
| `parseToMinutes()` (12h branch) | 20 | Parse AM/PM strings | ❌ Removed |
| Blur normalisation listener | 5 | Auto-correct on blur | ❌ Removed |

**Resolution:** `parseToMinutes()` simplified to handle only `HH:MM` format (native time input value). `normaliseInputValue()` and blur listener removed entirely — the browser handles formatting.

### 4. ORPHANED — PHP Variables & Data Attributes (MEDIUM)

Removed from PHP:
- `$timePattern` — not needed with native input validation
- `$inputMode` — not needed with `type="time"`
- `$timeFormat` — no longer referenced in simplified component

Removed from HTML:
- `data-time-pattern` — no JS consumer
- `data-time-example` — no JS consumer  
- `data-format-description` — no JS consumer
- `data-time-format` — no longer needed for JS parsing
- `pattern`, `inputmode`, `placeholder`, `title` attributes on inputs

Kept:
- `data-source-day`, `data-timezone` — still used
- `data-time-input`, `data-field` — used by JS selectors

### 5. ORPHANED — Controller Methods (HIGH)

Four private methods in `UserManagement.php` (lines 1088–1228) were never called — superseded by `ScheduleValidationService`:

| Method | Lines | Replaced by |
|--------|-------|-------------|
| `validateProviderScheduleInput()` | 78 | `ScheduleValidationService::validateProviderSchedule()` |
| `prepareScheduleForView()` | 38 | `ScheduleValidationService::prepareScheduleForView()` |
| `normaliseTimeString()` | 4 | `LocalizationSettingsService::normaliseTimeInput()` |
| `toBool()` | 16 | `ScheduleValidationService::toBool()` |

**Resolution:** All four methods removed. Total: **~140 lines** of dead code eliminated.

### 6. CROSS-CONCERN — `togglePassword()` (MEDIUM)

`window.togglePassword` was defined inside the schedule component IIFE (unrelated to schedules) AND duplicated in `edit.php`.

**Resolution:**
- Moved to `resources/js/app.js` as a global utility (single source of truth)
- Removed from `provider-schedule.php` and `edit.php`
- Available on all pages via the bundled app entry point

### 7. SERVER-SIDE — Time Format for Native Inputs (HIGH)

`prepareScheduleForView()` called `formatTimeForDisplay()` which returns `09:00 AM` for 12h locales — incompatible with `<input type="time">` which requires `HH:MM` (24h).

**Resolution:**
- Added `LocalizationSettingsService::formatTimeForNativeInput()` — always returns `HH:MM` 24h
- Added `ScheduleValidationService::formatTimeForNativeInput()` wrapper
- `prepareScheduleForView()` now uses `formatTimeForNativeInput()` instead of `formatTimeForDisplay()`

### 8. NO INLINE CSS ✅

No `style=` attributes found in the component. All styling uses Tailwind utility classes or project CSS classes.

### 9. NAMING CONSISTENCY ✅

| Context | Convention | Status |
|---------|-----------|--------|
| PHP variables | camelCase | ✅ Consistent |
| Form field names | snake_case | ✅ Consistent |
| Data attributes | kebab-case | ✅ Consistent |
| JS variables/functions | camelCase | ✅ Consistent |
| CSS classes | BEM / Tailwind | ✅ Consistent |

---

## Files Modified

| File | Change |
|------|--------|
| `app/Views/user-management/components/provider-schedule.php` | Full rewrite: native time inputs, `form-input`, cleaned JS |
| `app/Services/LocalizationSettingsService.php` | Added `formatTimeForNativeInput()` |
| `app/Services/ScheduleValidationService.php` | Updated `prepareScheduleForView()`, added wrapper |
| `app/Controllers/UserManagement.php` | Removed 4 orphaned methods (~140 lines) |
| `resources/js/app.js` | Added global `togglePassword()` |
| `app/Views/user-management/edit.php` | Removed duplicate `togglePassword()` |

---

## Before / After Metrics

| Metric | Before | After | Delta |
|--------|--------|-------|-------|
| provider-schedule.php total lines | 502 | ~400 | -102 |
| JS `parseToMinutes()` lines | 30 | 5 | -25 |
| `normaliseInputValue()` lines | 15 | 0 | -15 |
| Orphaned PHP methods | 4 (136 lines) | 0 | -136 |
| `togglePassword` definitions | 2 | 1 | -1 |
| Inline Tailwind class repetitions | 28 | 0 | -28 |
| Data attributes on section div | 7 | 3 | -4 |

---

## Recommendations for Future Work

1. **Extract `toggleScheduleSection()`** — this function manages provider assignments, staff assignments, color picker, and role descriptions in addition to the schedule. Consider splitting into a separate `role-toggle.js` module for better separation of concerns.

2. **Consider extracting time input markup** — the 4 time fields per day could be rendered via a PHP partial/helper to further reduce template repetition.

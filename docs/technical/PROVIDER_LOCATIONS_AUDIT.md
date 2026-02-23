# Provider Locations Component — Audit Report

**Date:** 2026-02-18  
**File audited:** `app/Views/user-management/components/provider-locations.php`  
**Related files:** `app/Controllers/Api/Locations.php`, `app/Models/LocationModel.php`

---

## Executive Summary

The "Add Location" button was not invoking any action because `window.LocationManager`
was undefined at the time inline `onclick` handlers fired. The component also contained
60+ lines of dead HTML, logic split between HTML attributes and JS, and styling
inconsistencies. All 8 issues below have been resolved.

---

## Findings & Fixes

### F-01 — CRITICAL: `const LocationManager` not on `window`

**Problem:**  
`const LocationManager = {...}` was used at the top level of a `<script>` tag.
`const` (unlike `var`) does NOT create a property on `window`. Inline event handlers
(`onclick="LocationManager.addLocation()"`) resolve identifiers through `window`, so
they found `undefined` and silently did nothing.

Additionally, when the SPA re-executes the script on a second navigation to the edit
page, a top-level `const` throws `SyntaxError: Identifier 'LocationManager' has
already been declared`, crashing the entire script block.

**Fix:**  
Replaced `const LocationManager = {...}` with `window.LocationManager = {...}`.
Window assignment is safe on repeated execution (simply overwrites), works in all
scope contexts, and is accessible from inline handlers and external scripts.

---

### F-02 — HIGH: Orphaned `<template id="locationCardTemplate">`

**Problem:**  
A 60-line `<template>` element existed in the HTML. `addLocation()` never cloned it —
it called the API and did an SPA page refresh. The template had:  
- Unsupported `name="locations[NEW][...]"` placeholders never replaced by JS  
- `required` attributes that would block form submission if rendered  
- Set-Primary and Delete buttons with no `onclick` handlers  
- A `.days-container` div documented as "Days will be populated by JS" — but no JS
  ever populated it  

**Fix:** Template removed entirely (~65 lines of dead HTML).

---

### F-03 — HIGH: Inline CSS class manipulation in `onchange` attributes

**Problem:**  
Each day-chip checkbox had an `onchange` attribute spanning ~7 `classList.toggle()`
calls, making the HTML unreadable and preventing DRY reuse:

```html
onchange="LocationManager.toggleDay(..., this.checked);
  this.closest('label').classList.toggle('bg-blue-100', this.checked);
  this.closest('label').classList.toggle('dark:bg-blue-900/30', this.checked);
  ..."
```

**Fix:**  
`onchange` simplified to `LocationManager.toggleDay(id, day, this)` (passes the
checkbox element). Styling moved to `_updateDayChip(label, active)` private method.
Optimistic UI also added: chip updates instantly, reverts on API failure.

---

### F-04 — MEDIUM: `required` mismatch between HTML and API

**Problem:**  
`address` and `contact_number` inputs on existing location cards were marked
`required` in HTML (with `*` in labels). However, after v84 the API no longer requires
these fields — only `provider_id` and `name` are mandatory. This caused browser
validation to block the outer user-edit form from submitting if those fields were
empty.

**Fix:**  
Removed `required` attribute and `*` markers from `address` and `contact_number`
fields. Location name retains `required`.

---

### F-05 — MEDIUM: Dead `dayNames`/`dayAbbr` arrays in JS object

**Problem:**  
`LocationManager` had `dayNames` and `dayAbbr` array properties mirroring the PHP
`$dayNames`/`$dayAbbr` arrays. These JS copies were only needed if the template was
being cloned (it was not). They added ~2KB of repeated data to the JS object on every
page render.

**Fix:** Arrays removed from `window.LocationManager` (kept in PHP only).

---

### F-06 — MEDIUM: Redundant `name="locations[n][...]"` form attributes

**Problem:**  
All location card inputs had `name="locations[0][name]"`, `name="locations[0][id]"`
(hidden), etc. These were submitted with the outer `user-management/update/:id` form.
`UserManagement::update()` does not process `locations[...]` POST data — all location
saves are API-driven. The attributes created a false impression of form-submission
persistence and added noise to submitted payloads.

**Fix:** `name` attributes and hidden `id` inputs removed from all location card
fields. The JS `updateLocation()` / `toggleDay()` methods remain the sole persistence
mechanism.

---

### F-07 — LOW: Inconsistent input styling

**Problem:**  
Location card inputs used raw Tailwind inline strings
(`w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg …`),
inconsistent with the rest of the app which uses the `form-input` CSS class
(standardised in the provider-schedule refactor).

**Fix:** All `<input>` and `<textarea>` elements now use `class="form-input"`.

---

### F-08 — LOW: Duplicate empty-state HTML

**Problem:**  
The "no locations" empty-state HTML block appeared twice:
1. In the PHP `if (empty($locations))` branch
2. As an inline template string inside `deleteLocation()` JS

Any copy-paste update to the empty state would need to be made in two places.

**Fix:** JS method `_emptyStateHTML()` added. `deleteLocation()` calls
`this._emptyStateHTML()`. PHP and JS now share the same markup definition (via the
shared method).

---

## Before / After Metrics

| Metric | Before | After |
|---|---|---|
| Total lines | 413 | 396 |
| Lines in `<script>` | 110 | 222 |
| Dead HTML (template) | 65 lines | 0 |
| Inline `classList.toggle` calls in HTML | 6 | 0 |
| Inline `onchange` character count (per day chip) | ~320 chars | ~55 chars |
| JS arrays mirroring PHP (`dayNames`, `dayAbbr`) | 2 | 0 |
| Form `name` attributes (never processed) | 8 | 0 |
| `required` mismatches vs API | 2 | 0 |

---

## Architecture Note — Is There a Form?

The locations component is embedded inside the user-edit `<form>`. The form submits
to `POST /user-management/update/:id`. Location data is **not** processed by that
controller — all location CRUD goes through the REST API at `/api/locations`.

The "Add Location" button carries `type="button"` (no form submission), and all
other location interactions (update field, toggle day, set primary, delete) use
`fetch()` directly. No form submission is involved in location management.

---

## Related Audit Documents

- [`PROVIDER_SCHEDULE_AUDIT.md`](PROVIDER_SCHEDULE_AUDIT.md) — time-picker refactor
- [`SPA_REFRESH_AUDIT.md`](SPA_REFRESH_AUDIT.md) — SPA navigation audit

# Design System Audit Summary

Date: 2026-03-06
Scope: design-system consistency, duplication, dead code, inline styles, asset loading
Status: baseline

## Executive Summary

This baseline confirms that the codebase has design-system inconsistency debt, but one previously suspected orphan (`resources/css/scheduler.css`) is currently active because it is imported by `resources/js/app.js`.

Top immediate wins:
1. Move layout asset loading to Vite helper functions.
2. Consolidate roadmap sequencing to reflect true dependencies.
3. Keep a single living audit document instead of phase-specific audit fragments.

## Findings

### 1) Hardcoded Built Asset Links

Severity: high

Files:
- `app/Views/layouts/app.php`

Evidence:
- Hardcoded references like `build/assets/main.js`, `build/assets/style.css`, and `build/assets/materialWeb.js` are used directly.

Risk:
- Breaks manifest-driven cache busting and increases coupling to output names.

Action:
- Replace hardcoded links with `vite_js()` / `vite_css()` based on source entries.

### 2) Animation Definition Duplication

Severity: medium

Files:
- `resources/scss/pages/_dashboard.scss`
- `resources/scss/components/_status.scss`
- `resources/scss/pages/_setup.scss`
- `resources/scss/abstracts/_mixins.scss`

Evidence:
- Duplicate or near-duplicate keyframes (including `pulse`) and file-local animation definitions.

Risk:
- Behavior drift, naming collisions, and maintenance overhead.

Action:
- Consolidate shared keyframes in `resources/scss/abstracts/_mixins.scss` and remove duplicates.

### 3) Inline Style Usage

Severity: low

Files:
- `app/Views/errors/html/error_exception.php`
- `app/Views/auth/emails/password-reset.php`

Evidence:
- 7 inline style attributes in CI error template and 2 in email template.

Risk:
- Limited. These are acceptable exceptions for framework error pages and email transport rendering.

Action:
- Keep as documented exceptions unless full template redesign is requested.

### 4) JS Entry Point Clarity

Severity: low

Files:
- `vite.config.js`
- `resources/js/charts.js`
- `resources/js/app.js`

Evidence:
- `charts.js` is the active charts entry and is also imported in `app.js`.
- No active `charts2.js` file exists in source; prior mentions are stale documentation.

Risk:
- Confusion in historical docs and cleanup proposals.

Action:
- Treat `charts.js` as canonical and remove stale references from docs when touched.

## Baseline Metrics

- Inline `style="` in `app/Views/**/*.php`: 7 matches in error template, 2 in email template.
- Active charts source files: 1 (`resources/js/charts.js`).
- Confirmed layout with hardcoded build asset references: 1 primary layout (`app/Views/layouts/app.php`).

## Verification Checklist

1. `npm run build` completes successfully after asset-link refactor.
2. Main authenticated layout loads CSS/JS via Vite helper URLs from manifest.
3. Dashboard, appointments, and scheduler pages load without missing asset errors.
4. No new inline styles introduced in app-facing views.

## Next Wave

1. Animation dedupe pass.
2. Component standardization pass (button/input/card/status badge).
3. Enforcement rules in contributing/PR checklist.

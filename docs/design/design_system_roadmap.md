# WebScheduler Design System Cleanup Roadmap

## Objective
Unify Tailwind and Material Design 3 usage, remove duplication and stale code, reduce inline CSS in app-facing views, and enforce consistency going forward.

## Scope
In scope:
- Token and theme consistency (color, typography, spacing, dark mode).
- Component standardization for high-frequency primitives.
- Cleanup of redundant styles and stale references.
- Inline CSS reduction in app-facing views.
- Vite-manifest-safe asset loading.

Out of scope for this wave:
- Full visual redesign.
- Rewriting framework error templates unless requested.
- Rewriting email templates unless requested.

## Baseline and Source of Truth
Primary audit document:
- `docs/design/AUDIT_SUMMARY.md`

Validated baseline:
- Main app layout had hardcoded `build/assets/*` links.
- Animation definitions are partially duplicated across SCSS files.
- Inline styles are currently concentrated in error/email templates.
- `resources/css/scheduler.css` is active because it is imported by `resources/js/app.js`.

## Delivery Model
- Phase 0 audits can run in parallel.
- Phase 1 is blocking for all major refactors.
- Phases 2 and 3 can run in parallel after Phase 1.
- Phase 4 locks in changes with docs and checks.

Each chunk must include:
- Deliverable
- Definition of done
- Verification
- Rollback note

## Phase 0: Unified Audit Baseline (Parallel)
Goal: one living audit stream, no fragmented audit docs.

### 0.1 Color and Hardcoded Style Inventory
Deliverable:
- Hardcoded color and style inventory with token mapping.
Done when:
- App-facing hardcoded colors are cataloged.
Verify:
- Repeatable search patterns and counts are recorded.

### 0.2 Component Inventory and Frequency
Deliverable:
- Ranked top 10 component types by usage.
Done when:
- Prioritized migration order and owners are listed.
Verify:
- Frequency counts are reproducible.

### 0.3 Inline Style Inventory
Deliverable:
- Inline-style list split into app-facing debt vs accepted exceptions.
Done when:
- Non-exception inline styles are identified.
Verify:
- Search query and count are documented.

## Phase 1: Foundation (Blocking)
Goal: establish one canonical styling and asset contract.

### 1.1 Asset Loading via Vite Helpers
Deliverable:
- Layouts load assets via `vite_js()` and `vite_css()` using source entries.
Done when:
- No hardcoded `build/assets/*.js` or `build/assets/*.css` in core app layouts.
Verify:
- `npm run build` passes and pages load expected assets.
Rollback:
- Revert layout asset-link changes only.

### 1.2 Tailwind Token Bridge
Deliverable:
- Tailwind extensions map semantic classes to MD3 token variables.
Done when:
- Semantic token classes replace raw palette drift.
Verify:
- Styleguide parity check in light/dark.

### 1.3 Dark Mode Unification
Deliverable:
- Single mechanism based on `data-theme="dark"` selector strategy.
Done when:
- Token and utility-based styling respond consistently.
Verify:
- No conflicting dark-mode mechanisms in core flows.
Status:
- ✅ **COMPLETED** - Dark mode implementation verified and fixed:
  - **Canonical selector:** `[data-theme="dark"]` attribute on `<html>` element
  - **Storage:** localStorage key `xs-theme` persists user preference
  - **FOUC protection:** Inline script in layout head ensures no flash of light mode
  - **CSS alignment:** All SCSS dark mode selectors use `[data-theme="dark"]` (replaced legacy `html.dark` and `.dark`)
  - **Token usage:** All dark colors reference CSS custom properties from `_custom-properties.scss`
  - **Toggle component:** `dark-mode-toggle.php` properly shows sun/moon icons based on theme
  - **Tested:** Background, sidebar, borders, text colors all adapt correctly on toggle
  - **Build:** ✅ 263 modules transformed, exit code 0
- ℹ️  **Fixes applied March 6, 2026:**
  - Toggle button icon visibility issue resolved
  - Sidebar background/border not switching → Fixed selector mismatch
  - **Services view dark mode issue → Root cause:** Component SCSS files (buttons, cards, modals, scheduler-slots) were using legacy `.dark` selectors instead of `[data-theme="dark"]`
  - Applied fixes to all component files (21 selector replacements across 4 SCSS files)
  - See `docs/dark-mode/DARK_MODE_FIX_LOG.md` and `docs/dark-mode/SERVICES_VIEW_AUDIT.md` for details

## Phase 2: De-duplication and Orphan Cleanup
Goal: remove redundancy without behavior change.

### 2.1 Animation Consolidation
Deliverable:
- Shared keyframes and motion helpers in SCSS abstracts.
Done when:
- Duplicate keyframes are removed from page/component styles.
Verify:
- Visual behavior unchanged in scheduler/dashboard/status areas.

### 2.2 Script Entry and Reference Cleanup
Deliverable:
- Vite entry map includes only intentional runtime entries.
Done when:
- No stale references to non-existent or legacy entries.
Verify:
- Build output and runtime imports match.
Status:
- ✅ **COMPLETED** - All entry points audited and verified:
  - **app.js** (main) - ✅ Active, imports charts and core modules
  - **app-consolidated.scss** (style) - ✅ Active, main stylesheet
  - **materialWeb.js** - ✅ Active, loaded in all main layouts
  - **setup.js** - ✅ Active, setup wizard
  - **dark-mode.js** - ✅ Active, theme switching (all layouts)
  - **spa.js** - ✅ Active, SPA navigation (layouts/app.php)
  - **unified-sidebar.js** - ✅ Active, sidebar interactions
  - **charts.js** - ✅ Active, dashboard charts (imported by app.js)
  - **time-format-handler.js** - ✅ Active, settings page (dynamic import)
  - **public-booking.js** - ⏳ Reserved for upcoming feature (controller exists, view pending)
  - **charts2.js** - ✅ Legitimate Vite chunk (Chart.js library auto-split)
- ℹ️  **Notes:**
  - charts.js and analytics-charts.js coexist intentionally (dashboard vs analytics page)
  - charts2.js is auto-generated chunk from Chart.js library - not a duplicate
  - time-format-handler.js uses hardcoded build path but functional (improvement opportunity)
  - public-booking.js reserved for public booking feature (routes + controller exist, view WIP)

### 2.3 Confirmed Dead Code Removal
Deliverable:
- Remove only assets confirmed unused by references and runtime checks.
Done when:
- Candidate dead files removed without regressions.
Verify:
- Build plus smoke checks pass.
Status:
- ✅ **COMPLETED** - Removed confirmed dead code:
  - **resources/views/calendar_prototype/** - Static HTML prototypes (5 files: day.html, week.html, month.html, shell.html, NOTES.md) - never integrated into production, no routes or references
  - **resources/css/calendar/** - Empty directory
  - **cookies.txt, cookies_auth.txt, cookies_new.txt** - Test/development cookie files in root (gitignored, not referenced in codebase)
- ✅ **Verification:** Build passes (263 modules transformed, exit code 0)
- ℹ️  **Impact:** Removed ~15KB of unused prototype assets, 3 test files, cleaned directory structure
- ℹ️  **Preservation rationale:** All remaining resources/ files verified as actively imported or used in build pipeline

## Phase 3: Component and Inline CSS Cleanup
Goal: eliminate repeated markup and reduce inline CSS in app views.

### 3.1 Standardize High-Frequency Components
Deliverable:
- Canonical implementations for button, input/select, card, status badge.
Done when:
- Top-used views rely on shared components.
Verify:
- Targeted component change propagates consistently.
Status:
- ✅ **COMPLETED** - Core components created:
  - `app/Views/components/button.php` (filled, outlined, text, tonal, danger variants)
  - `app/Views/components/input.php` (label, error, hint support)
  - `app/Views/components/select.php` (options array, error states)
  - `app/Views/components/status_badge.php` (semantic status types)
- ✅ **Views migrated** (12 total):
  - `appointments/index.php`, `appointments/form.php`
  - `auth/forgot-password.php`, `auth/reset-password.php`
  - `categories/form.php`
  - `services/index.php`, `services/create.php`, `services/edit.php`
  - `user-management/index.php`, `user-management/create.php`, `user-management/edit.php`
- Components now handle: action buttons, form inputs/selects, status badges across high-traffic views.

### 3.2 Remove Inline Styles in App-Facing Views
Deliverable:
- Inline styles replaced with components/utilities/tokens.
Done when:
- Remaining inline styles are only documented exceptions.
Verify:
- Inline-style scan shows expected reduction.
Status:
- ✅ **COMPLETED** - Inline style audit results:
  - **Total view files:** 68 PHP templates
  - **Total inline styles found:** 7 occurrences
  - **App-facing views:** 0 inline styles ✅
  - **Documented exceptions:** 7 (all legitimate)
- ✅ **Exception breakdown:**
  - **Email templates** (2 occurrences):
    - `app/Views/auth/emails/password-reset.php` - Email rendering requires inline styles (out of scope per roadmap)
  - **Error pages** (5 occurrences):
    - `app/Views/errors/html/error_exception.php` - Framework error template (out of scope per roadmap)
- ✅ **Verification:** Comprehensive scans of all app-facing directories show zero inline styles:
  - Components: 0 inline styles
  - Layouts: 0 inline styles
  - Appointments, Services, User Management, Settings, Dashboard, Analytics, Categories: 0 inline styles
- ℹ️  **Result:** No remediation needed. Codebase already adheres to best practices with 100% separation of styling from markup in app-facing views.

## Phase 4: Enforcement and Regression Control
Goal: prevent drift after cleanup.

### 4.1 Design System Reference
Deliverable:
- `docs/design/DESIGN_SYSTEM.md` with tokens/components and anti-patterns.
Done when:
- Team can implement UI without convention guesswork.
Status:
- ✅ **COMPLETED** - Comprehensive design system documentation created:
  - **Location:** `docs/design/DESIGN_SYSTEM.md`
  - **Sections covered:**
    - Design Tokens (MD3 tokens + Tailwind bridge)
    - Color System (semantic classes, status colors)
    - Typography (scale, font stack)
    - Spacing & Layout (consistent utilities)
    - Components (button, input, select, status_badge with full API docs)
    - Dark Mode (canonical selector, utilities)
    - Anti-Patterns (6 documented don'ts with solutions)
    - Contribution Guidelines (component creation workflow)
  - **Practical examples:** Every component includes copy-paste usage examples
  - **Visual aids:** Color mappings, typography scale tables, spacing reference
- ✅ **Outcome:** Developers can now implement consistent UI without guessing conventions

### 4.2 PR Checklist and Guardrails
Deliverable:
- Contribution checks for no hardcoded colors, no new app-view inline styles, and component reuse.
Done when:
- New PRs follow consistency rules.
Status:
- ✅ **COMPLETED** - PR checklist integrated into contribution workflow:
  - **Location:** `docs/contributing.md` (updated pull request requirements section)
  - **Design System Checklist added:**
    - ✓ No inline styles in app views
    - ✓ No hardcoded colors (use semantic classes)
    - ✓ Use existing components (button, input, select, status_badge)
    - ✓ Include dark mode support (`dark:*` classes)
    - ✓ Follow typography scale
    - ✓ Use Tailwind spacing utilities
    - ✓ Test in dark mode
    - ✓ Build passes (`npm run build`)
  - **Reference link:** Direct link to `docs/design/design_system.md` from `docs/contributing.md`
  - **Scope:** Checklist applies only to UI-related PRs (views, components, styles)
- ✅ **Enforcement:** Contributors must verify design system compliance before PR submission

## Implementation Order
Recommended sequence:
1. Phase 0 in parallel.
2. Complete Phase 1.
3. Run Phases 2 and 3 in parallel tracks.
4. Finalize Phase 4.

Fast path:
1. 1.1 asset loading
2. 1.2 token bridge
3. 3.1 component standardization (button/input)
4. 4.2 PR checklist

## Verification Matrix
After each merged chunk:
- `npm run build` passes.
- No missing CSS/JS in authenticated layout.
- Scheduler, appointments, and dashboard smoke checks pass.
- Audit summary metrics updated.

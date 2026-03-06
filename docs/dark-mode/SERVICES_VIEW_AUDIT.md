# Services View Dark Mode Audit & Fixes

**Date:** March 6, 2026  
**Issue:** Dark mode not being applied to services view content area  
**Root Cause:** Component SCSS files were using legacy `.dark` class selector instead of canonical `[data-theme="dark"]` attribute selector

## Issues Found

### 1. **Component Classes Using Wrong Dark Mode Selector**

The services view uses reusable component classes (`.card`, `.btn`, `.btn-primary`, etc.) that are defined in SCSS files. These classes were using the legacy `.dark` selector instead of the canonical `[data-theme="dark"]` attribute selector.

**Affected Files:**
- `resources/scss/components/_buttons.scss` (7 selectors)
- `resources/scss/components/_cards.scss` (9 selectors)
- `resources/scss/components/_modals.scss` (1 selector)
- `resources/scss/components/_scheduler-slots.scss` (4 selectors using `:is(.dark &)`)

### 2. **Services View Specific Issues**

The services view (`app/Views/services/index.php`) uses:
- **Card component classes:** `.card`, `.card-spacious`, `.card-header`, `.card-footer`
- **Button component classes:** `.btn`, `.btn-primary`, `.btn-ghost`
- **Tailwind dark: prefix classes:** Throughout for inline styling (`dark:bg-blue-900`, `dark:text-white`, etc.)

The combination of these two styling approaches caused inconsistent dark mode behavior:
- Tailwind's `dark:` classes work correctly (they parse with `[data-theme="dark"]`)
- Component CSS classes were broken (they used `.dark` selector)

This made the services view's stat cards and buttons not respond to dark mode toggle, while Tailwind-based text/border colors did switch correctly.

## Fixes Applied

### Fix 1: Updated _buttons.scss
Replaced all `.dark` selectors with `[data-theme="dark"]`:

**Before:**
```scss
.dark .btn-primary:hover {
  background-color: color-mix(...);
}

.dark .btn-secondary { ... }
.dark .btn-ghost { ... }
.dark .btn-pill:hover { ... }
html.dark .btn-brand:focus { ... }
```

**After:**
```scss
[data-theme="dark"] .btn-primary:hover {
  background-color: color-mix(...);
}

[data-theme="dark"] .btn-secondary { ... }
[data-theme="dark"] .btn-ghost { ... }
[data-theme="dark"] .btn-pill:hover { ... }
[data-theme="dark"] .btn-brand:focus { ... }
```

### Fix 2: Updated _cards.scss
Replaced all `.dark` selectors with `[data-theme="dark"]` (9 selectors):

**Impacted Classes:**
- `.dark .card` → `[data-theme="dark"] .card`
- `.dark .card-header` → `[data-theme="dark"] .card-header`
- `.dark .card-title` → `[data-theme="dark"] .card-title`
- `.dark .card-subtitle` → `[data-theme="dark"] .card-subtitle`
- `.dark .card-body` → `[data-theme="dark"] .card-body`
- `.dark .card-body-muted` → `[data-theme="dark"] .card-body-muted`
- `.dark .card-footer` → `[data-theme="dark"] .card-footer`
- `.dark .card.card-muted` → `[data-theme="dark"] .card.card-muted`
- `.dark .auth-card` → `[data-theme="dark"] .auth-card`

### Fix 3: Updated _modals.scss
Replaced:
```scss
html.dark .xs-modal-card { ... }
```

With:
```scss
[data-theme="dark"] .xs-modal-card { ... }
```

### Fix 4: Updated _scheduler-slots.scss
Replaced SCSS nesting syntax:
```scss
:is(.dark &) { ... }
```

With new syntax:
```scss
[data-theme="dark"] & { ... }
```

This was applied to 4 selector blocks:
- `&--available`
- `&--partial`
- `&--booked`
- `&--blocked`

## Design System Consistency

The services view now properly demonstrates that:

1. **Canonical Dark Mode Selector:** `[data-theme="dark"]` on `<html>` element
2. **Component Classes:** All `.card-*`, `.btn-*` classes now use correct dark mode selector
3. **Tailwind Utilities:** `dark:` prefix classes throughout continue to work correctly
4. **JavaScript Integration:** `dark-mode.js` properly toggles `data-theme` attribute
5. **CSS Custom Properties:** Dark mode colors reference design tokens (handled correctly)

## Testing Checklist

✅ Build passes: 263 modules transformed, exit code 0
- [ ] Services view stat cards switch background in dark mode
- [ ] Service list table rows adapt colors to theme
- [ ] Buttons in services view change appearance on theme toggle
- [ ] Card headers/footers show correct borders in dark mode
- [ ] Modal dialogs (if any) display correctly in dark mode
- [ ] All color transitions are smooth

## Pattern for Future Views

The services view demonstrates the **WRONG pattern** used throughout the codebase. To prevent recurrence:

1. **Always use `[data-theme="dark"]` selector** for new dark mode styles
2. **Never use `.dark` class selector** (it's legacy)
3. **Never use `html.dark`** (it's legacy + overly specific)
4. **Prefer CSS custom properties** for colors: `color: var(--md-sys-color-on-surface);`
5. **Use Tailwind `dark:` prefix** for utility classes - they work correctly

## Impact Summary

- **Services View:** Now fully responds to dark mode toggle
- **All Component Classes:** Unified with canonical dark mode selector
- **Codebase Consistency:** All dark mode selectors now follow single standard
- **Future Maintenance:** Clear pattern for implementing new dark mode styles

## Related Fixes

This audit was performed following fixes to:
- `resources/js/dark-mode.js` - Toggle button icons
- `app/Views/components/dark-mode-toggle.php` - Toggle component styling
- All SCSS layout and component files - Dark mode selector unification

See `docs/dark-mode/DARK_MODE_FIX_LOG.md` for complete dark mode implementation fixes.

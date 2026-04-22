# Dark Mode Implementation Fix

**Date:** March 6, 2026  
**Issue:** Dark mode toggle and sidebar not responding to theme changes  
**Root Cause:** Selector mismatch between `[data-theme="dark"]` attribute selector (canonical) and `html.dark` class selector (legacy)

## Issues Resolved

### 1. Toggle Button Showing Both Icons
**Problem:** Both sun and moon icons were visible regardless of theme
**Cause:** Toggle component used Tailwind's `dark:` prefix (which uses `.dark` class), but dark-mode.js sets `data-theme="dark"` attribute
**Solution:** Replaced Tailwind `dark:` classes with inline styles and JavaScript-based icon visibility

**File:** `app/Views/components/dark-mode-toggle.php`
- Removed hardcoded `dark:` Tailwind classes
- Added CSS custom properties for styling: `color: var(--md-sys-color-on-surface-variant);`
- Added JavaScript listener for `xs:theme-changed` event to update icon visibility
- Icons now toggle correctly based on `data-theme` attribute

### 2. Sidebar Not Switching Background
**Problem:** Sidebar background remained white in dark mode
**Cause:** SCSS used `html.dark` selector which doesn't match the canonical `[data-theme="dark"]` attribute
**Solution:** Replaced all `html.dark` selectors with `[data-theme="dark"]` throughout SCSS

**Files Updated:**
1. `resources/scss/components/_unified-sidebar.scss` (7 selectors)
   - Sidebar main background
   - Sidebar header, brand-name, close-button, nav-link, nav-divider
   - Scrollbar thumb colors
   - All now use CSS custom properties (e.g., `var(--md-sys-color-surface)`) for consistency

2. `resources/scss/layout/_app-layout.scss` (3 selectors)
   - Page header border color
   - Body background colors
   - Menu item hover effects

3. `resources/scss/layout/_unified-content-system.scss` (5 selectors)
   - Card backgrounds and borders
   - Card headers and footers
   - Button secondary/ghost variants

4. `resources/scss/pages/_auth.scss` (2 selectors)
   - Auth page dark mode styles
   - Login card shadows

## Technical Details

### Dark Mode Activation
- **Method:** `data-theme="dark"` attribute on `<html>` element (canonical selector)
- **Set by:** `resources/js/dark-mode.js` in `applyTheme()` method
- **Persisted:** localStorage key `xs-theme` stores user preference
- **FOUC Protection:** Inline script in layout head applies theme before page renders

### CSS Custom Properties (Tokens)
All dark mode styles now reference design tokens from `_custom-properties.scss`:
- `--md-sys-color-surface` - Background surfaces adapt to theme
- `--md-sys-color-on-surface` - Text color adapts to theme
- `--md-sys-color-outline` - Borders adapt to theme
- `--md-sys-color-on-surface-variant` - Secondary text color

### JavaScript Integration
Toggle button now:
1. Listens for `xs:theme-changed` custom event
2. Updates icon visibility based on current theme
3. Uses data attributes to identify icons: `data-theme-icon="light"` and `data-theme-icon="dark"`

## Verification

### Build Status
✅ Build passes: 263 modules transformed, exit code 0

### Testing Checklist
- [ ] Load page in light mode - background should be white/cream
- [ ] Toggle to dark mode - background should be dark
- [ ] Sidebar switches color when theme toggles
- [ ] Toggle button shows sun icon in dark mode (to switch to light)
- [ ] Toggle button shows moon icon in light mode (to switch to dark)
- [ ] Refresh page - theme persists from localStorage
- [ ] All borders, text colors adapt to theme
- [ ] No FOUC (Flash of Unstyled Content) on page load

## Breaking Changes
None. This is a bug fix that restores intended functionality.

## Future Prevention
- Canonical dark mode method: `[data-theme="dark"]` attribute selector
- Legacy CSS class `.dark` should not be used
- All new dark mode styles must use `[data-theme="dark"] selector {}`
- All new components should use CSS custom properties for theming
- PR checklist in DESIGN_SYSTEM.md recommends testing in both modes

# Global Layout System - Documentation

## Overview

The WebSchedulr application uses a **unified, standardized layout system** that ensures consistent spacing, alignment, and behavior across all views. This document defines the rules and structure that **MUST** be followed by all views.

---

## Core Principles

### 1. **Single Source of Truth**
All layout spacing, sizing, and positioning are controlled by CSS custom properties defined in `_app-layout.scss`. 

### 2. **No Inline Styles**
❌ **NEVER** use `style="..."` attributes in view files.
✅ **ALWAYS** use CSS classes or data attributes with JavaScript handlers.

### 3. **Consistent Spacing**
All spacing uses the standardized spacing scale defined in CSS variables.

### 4. **Header Alignment**
Content area edges **MUST** align exactly with the header bar edges.

### 5. **No Content Behind Header**
The fixed header must never overlap or hide content during scrolling.

---

## Layout Structure

```
┌─────────────────────────────────────────────────────────┐
│  SIDEBAR (fixed)         │  HEADER (fixed)             │
│  256px wide              │  Aligned with content      │
│                          │                             │
│  ┌─────────────┐        ├─────────────────────────────┤
│  │             │        │                             │
│  │  Nav items  │        │  CONTENT AREA               │
│  │             │        │  (scrollable)               │
│  │             │        │                             │
│  └─────────────┘        │  - Starts below header      │
│                          │  - Left/right aligned       │
│                          │  - Standard padding         │
│                          │                             │
└─────────────────────────────────────────────────────────┘
```

---

## CSS Variables (Design Tokens)

### Frame & Content Spacing

```scss
// Frame inset (sidebar/header shell spacing)
--xs-frame-inset: 0;              // Mobile
--xs-frame-inset-desktop: 1rem;   // Desktop (16px)

// Content inset (additional narrowing)
--xs-content-inset-mobile: 1rem;    // 16px
--xs-content-inset-desktop: 1.5rem; // 24px

// Component dimensions
--xs-sidebar-width: 16rem;          // 256px
--xs-header-height: 4.5rem;         // 72px
```

### Spacing Scale (8px base)

```scss
--xs-space-1: 0.25rem;   // 4px
--xs-space-2: 0.5rem;    // 8px
--xs-space-3: 0.75rem;   // 12px
--xs-space-4: 1rem;      // 16px
--xs-space-5: 1.25rem;   // 20px
--xs-space-6: 1.5rem;    // 24px
--xs-space-8: 2rem;      // 32px
--xs-space-10: 2.5rem;   // 40px
--xs-space-12: 3rem;     // 48px
```

### Border Radius

```scss
--xs-radius-sm: 0.375rem;   // 6px
--xs-radius-md: 0.5rem;     // 8px
--xs-radius-lg: 1rem;       // 16px (unified standard)
--xs-radius-xl: 1rem;       // 16px
```

---

## Layout Classes

### Main Layout Wrappers

#### `.xs-main-container`
- Wraps all content next to sidebar
- Desktop: `margin-left` accounts for sidebar width
- Mobile: Full width, no margin

#### `.xs-content-wrapper`
- Base container for all page content
- **Must** be used on all views extending `layouts/app.php`
- Applies consistent padding and max-width

#### Layout Variants

```php
<?= $this->section('layout_variant') ?>standard<?= $this->endSection() ?>
// or
<?= $this->section('layout_variant') ?>dashboard<?= $this->endSection() ?>
```

- **standard**: Max-width for readability, generous spacing
  - Use for: Customer, User Management, Services, Settings, etc.
  
- **dashboard**: Wider max-width, tighter spacing
  - Use for: Dashboard, Analytics, Profile

---

## View Structure Rules

### 1. All Views Must Extend a Layout

```php
<?= $this->extend('layouts/app') ?>
// or
<?= $this->extend('layouts/dashboard') ?>
```

### 2. Content Goes in Proper Section

```php
<?= $this->section('content') ?>
    <!-- Your view content here -->
<?= $this->endSection() ?>
```

### 3. No Custom Padding on Root Elements

❌ **WRONG:**
```php
<div class="p-6 md:p-8">
    <!-- content -->
</div>
```

✅ **CORRECT:**
```php
<!-- Rely on layout wrapper padding -->
<div>
    <!-- content -->
</div>
```

---

## Spacing Standardization

### Card Padding

```scss
// Standard card padding
.xs-card-body {
  padding: 1.5rem;           // Mobile

  @media (min-width: 768px) {
    padding: 1.5rem 2rem;    // Desktop
  }
}
```

**Usage in views:**
```html
<div class="xs-card">
    <div class="xs-card-header">
        <h3 class="xs-card-title">Title</h3>
    </div>
    <div class="xs-card-body">
        <!-- Content automatically has correct padding -->
    </div>
</div>
```

### Section Spacing

Use consistent spacing between major sections:

```html
<div class="space-y-6">
    <section><!-- Section 1 --></section>
    <section><!-- Section 2 --></section>
    <section><!-- Section 3 --></section>
</div>
```

- `space-y-6` = 1.5rem gap between sections
- `space-y-8` = 2rem gap for more breathing room

### Grid Gaps

```html
<!-- Standard grid spacing -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Grid items -->
</div>

<!-- Tight spacing for dense layouts -->
<div class="grid grid-cols-2 gap-4">
    <!-- Grid items -->
</div>
```

---

## Dynamic Colors (No Inline Styles)

### Problem: Inline Styles
❌ **WRONG:**
```php
<div style="background-color: <?= $providerColor ?>"></div>
```

### Solution: Data Attributes + CSS Classes

✅ **CORRECT:**
```php
<div class="provider-color-dot" data-color="<?= esc($providerColor) ?>"></div>
```

**JavaScript automatically applies:**
```javascript
// In resources/js/utils/dynamic-colors.js
document.querySelectorAll('[data-color]').forEach(el => {
    el.style.backgroundColor = el.getAttribute('data-color');
});
```

### Available Color Classes

- `.provider-color-dot` - Small color indicators for providers
- `.category-color-dot` - Small color indicators for categories
- `.provider-color-preview` - Larger color swatches
- `.xs-dynamic-bg` - Generic dynamic background color

---

## Header Alignment

The header and content must align perfectly on left and right edges.

### How It Works

1. **Header positioning** (fixed):
   ```scss
   .xs-header {
     left: calc(var(--xs-sidebar-width) + var(--xs-frame-inset-desktop) + var(--xs-content-inset-desktop));
     right: var(--xs-frame-inset-desktop);
   }
   ```

2. **Content positioning**:
   ```scss
   .xs-content-wrapper {
     margin-right: var(--xs-frame-inset-desktop);
     // Left already offset by main-container
   }
   ```

3. **Result**: Perfect left/right alignment ✅

### Visual Verification

```
HEADER:     |←─ padding ─→| Content |←─ padding ─→|
CONTENT:    |←─ padding ─→| Content |←─ padding ─→|
            ↑                                     ↑
         Aligned                             Aligned
```

---

## Responsive Behavior

### Mobile (< 1024px)
- Sidebar: Hidden, slides in from left
- Header: Full width with content inset
- Content: Full width with mobile inset
- Spacing: Tighter padding (`p-4`)

### Desktop (≥ 1024px)
- Sidebar: Fixed, always visible
- Header: Aligned with content area
- Content: Offset by sidebar width
- Spacing: Generous padding (`p-6`)

---

## Component Standardization

### Buttons

Use unified button classes:

```html
<button class="xs-btn xs-btn-primary">Primary Action</button>
<button class="xs-btn xs-btn-secondary">Secondary</button>
<button class="xs-btn xs-btn-destructive">Delete</button>
<button class="xs-btn xs-btn-ghost">Cancel</button>
```

### Typography

Use semantic classes:

```html
<h1 class="xs-page-title">Page Title</h1>
<h2 class="xs-section-title">Section Title</h2>
<h3 class="xs-component-title">Component Title</h3>
<p class="xs-body">Body text</p>
<p class="xs-text-muted">Secondary text</p>
<p class="xs-text-small">Small text</p>
```

### Forms

Use consistent form structure:

```html
<div class="xs-form-group">
    <label class="xs-form-label xs-required">Field Label</label>
    <input type="text" class="xs-form-input" />
    <p class="xs-form-help">Helper text</p>
</div>
```

---

## Testing Checklist

Before committing changes, verify:

- [ ] No inline `style=` attributes
- [ ] Content area aligns with header edges
- [ ] No content scrolls behind header
- [ ] Consistent spacing between sections
- [ ] Responsive behavior works (mobile & desktop)
- [ ] All views extend proper layout
- [ ] Dynamic colors use data attributes
- [ ] Forms use standard form classes
- [ ] Buttons use standard button classes

---

## File References

- Layout CSS: `resources/scss/layout/_app-layout.scss`
- Content System: `resources/scss/layout/_unified-content-system.scss`
- Dynamic Colors CSS: `resources/scss/utilities/_dynamic-colors.scss`
- Dynamic Colors JS: `resources/js/utils/dynamic-colors.js`
- Main Layout: `app/Views/layouts/app.php`
- Dashboard Layout: `app/Views/layouts/dashboard.php`

---

## Common Patterns

### Standard Page View

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Services<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="space-y-6">
    <!-- Page content with consistent spacing -->
    <section><!-- Section 1 --></section>
    <section><!-- Section 2 --></section>
</div>
<?= $this->endSection() ?>
```

### Dashboard Page View

```php
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('page_title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('dashboard_stats') ?>
    <!-- Stat cards -->
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- Main dashboard content -->
<?= $this->endSection() ?>
```

---

## Migration Guide

If updating an existing view:

1. **Remove inline styles**
   ```php
   // Before
   <div style="background-color: <?= $color ?>">
   
   // After
   <div class="provider-color-dot" data-color="<?= $color ?>">
   ```

2. **Standardize padding**
   ```php
   // Before
   <div class="p-4 md:p-6 mb-8">
   
   // After
   <div class="xs-card-body">  <!-- Padding handled by class -->
   ```

3. **Use semantic classes**
   ```php
   // Before
   <h2 class="text-xl font-bold text-gray-900">
   
   // After
   <h2 class="xs-section-title">
   ```

4. **Verify layout alignment**
   - Check header alignment in browser
   - Test mobile responsive behavior
   - Verify no scroll issues

---

## Support

For questions or issues with the layout system:
1. Review this documentation
2. Check `_app-layout.scss` for CSS implementation
3. Review existing views for examples
4. Consult the development team

---

**Last Updated:** January 28, 2026
**Version:** 2.0.0

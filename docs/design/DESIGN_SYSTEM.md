# WebSchedulr Design System

**Last Updated:** March 6, 2026  
**Status:** Active  
**Applies to:** All app-facing views (excludes error pages and email templates)

## Overview

This document defines the canonical design system for WebSchedulr CI4. It ensures consistency, maintainability, and adherence to Material Design 3 principles while leveraging Tailwind CSS utilities.

---

## Table of Contents

1. [Design Tokens](#design-tokens)
2. [Color System](#color-system)
3. [Typography](#typography)
4. [Spacing & Layout](#spacing--layout)
5. [Components](#components)
6. [Dark Mode](#dark-mode)
7. [Anti-Patterns](#anti-patterns)
8. [Contribution Guidelines](#contribution-guidelines)

---

## Design Tokens

### What Are Design Tokens?

Design tokens are named variables that store visual design attributes (colors, spacing, typography) in a centralized location. WebSchedulr uses Material Design 3 tokens as the foundation.

### Token Location

- **CSS Custom Properties:** `resources/scss/abstracts/_custom-properties.scss`
- **Tailwind Config:** `tailwind.config.js` (token bridge)

### Core Token Categories

#### Color Tokens
```css
/* Semantic MD3 tokens */
--md-sys-color-primary
--md-sys-color-on-primary
--md-sys-color-secondary
--md-sys-color-on-secondary
--md-sys-color-tertiary
--md-sys-color-error
--md-sys-color-background
--md-sys-color-surface
--md-sys-color-outline
```

#### Spacing Tokens
Use Tailwind's spacing scale (based on 0.25rem increments):
- `space-1` = 0.25rem (4px)
- `space-2` = 0.5rem (8px)
- `space-3` = 0.75rem (12px)
- `space-4` = 1rem (16px)
- `space-6` = 1.5rem (24px)
- `space-8` = 2rem (32px)

---

## Color System

### Semantic Color Classes (Tailwind + MD3 Bridge)

WebSchedulr exposes semantic color classes via `tailwind.config.js`:

```javascript
// Available Tailwind classes:
bg-background       // Main background
bg-surface          // Card/panel surfaces
bg-primary          // Primary actions
bg-secondary        // Secondary actions
bg-tertiary         // Tertiary elements
bg-error            // Error states

text-on-background  // Text on background
text-on-surface     // Text on surfaces
text-on-primary     // Text on primary
```

### Using Colors

**✅ DO:**
```php
<button class="bg-primary text-on-primary">Save</button>
<div class="bg-surface border border-outline">Card</div>
```

**❌ DON'T:**
```php
<button style="background-color: #1a73e8">Save</button>
<div class="bg-blue-600">Card</div>
```

### Status Colors

For appointment/entity statuses, use semantic status badge component:

```php
<?= view('components/status_badge', [
    'status' => 'confirmed',  // pending|confirmed|completed|cancelled|no-show|active|inactive
]) ?>
```

---

## Typography

### Font Stack

```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```

### Typography Scale

Use Tailwind typography utilities:

| Element | Class | Size |
|---------|-------|------|
| Page Title | `text-2xl font-bold` | 24px/1.5rem |
| Section Title | `text-xl font-semibold` | 20px/1.25rem |
| Card Title | `text-lg font-semibold` | 18px/1.125rem |
| Body | `text-base` | 16px/1rem |
| Small | `text-sm` | 14px/0.875rem |
| Extra Small | `text-xs` | 12px/0.75rem |

**✅ DO:**
```php
<h2 class="text-xl font-semibold text-gray-900 dark:text-white">Services</h2>
<p class="text-sm text-gray-600 dark:text-gray-400">Select a service</p>
```

**❌ DON'T:**
```php
<h2 style="font-size: 20px; font-weight: 600;">Services</h2>
<p style="font-size: 14px; color: #666;">Select a service</p>
```

---

## Spacing & Layout

### Consistent Spacing

Use Tailwind's spacing scale consistently:

| Purpose | Class | Value |
|---------|-------|-------|
| Component padding | `p-4` or `p-6` | 16px or 24px |
| Section gaps | `space-y-6` | 24px vertical |
| Form field gaps | `space-y-4` | 16px vertical |
| Inline gaps | `gap-3` or `gap-4` | 12px or 16px |

### Layout Containers

```php
<!-- Page wrapper -->
<div class="max-w-4xl mx-auto">
  <!-- Content -->
</div>

<!-- Grid layouts -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <!-- Cards -->
</div>
```

---

## Components

### Available Reusable Components

WebSchedulr provides PHP view components in `app/Views/components/`:

#### 1. Button Component

**Location:** `app/Views/components/button.php`

**Usage:**
```php
<?= view('components/button', [
    'text' => 'Save Changes',
    'type' => 'submit',           // button|submit|reset (default: button)
    'variant' => 'filled',        // filled|outlined|text|tonal|danger (default: filled)
    'size' => 'md',               // sm|md|lg (default: md)
    'href' => null,               // URL for link buttons
    'icon' => 'save',             // Material icon name (optional)
    'iconPosition' => 'left',     // left|right (default: left)
    'disabled' => false,
    'id' => 'save-btn',           // Optional
    'class' => 'ml-auto',         // Additional classes (optional)
]) ?>
```

**Variants:**
- `filled` - Primary actions (blue background)
- `outlined` - Secondary actions (border only)
- `text` - Tertiary actions (no border/background)
- `tonal` - Subtle emphasis (light background)
- `danger` - Destructive actions (red)

#### 2. Input Component

**Location:** `app/Views/components/input.php`

**Usage:**
```php
<?= view('components/input', [
    'name' => 'email',
    'label' => 'Email Address',
    'type' => 'email',            // text|email|password|number|tel|date (default: text)
    'value' => old('email', $user['email'] ?? ''),
    'required' => true,
    'disabled' => false,
    'placeholder' => 'Enter email',
    'error' => $validation->getError('email'),
    'hint' => 'We never share your email',
    'id' => 'user-email',         // Auto-generated if not provided
    'class' => 'mb-4',            // Additional classes (optional)
]) ?>
```

#### 3. Select Component

**Location:** `app/Views/components/select.php`

**Usage:**
```php
<?= view('components/select', [
    'name' => 'service_id',
    'label' => 'Service',
    'options' => [
        ['value' => '', 'label' => 'Select a service...'],
        ['value' => '1', 'label' => 'Haircut'],
        ['value' => '2', 'label' => 'Massage'],
    ],
    'value' => old('service_id', $appointment['service_id'] ?? ''),
    'required' => true,
    'disabled' => false,
    'error' => $validation->getError('service_id'),
    'id' => 'service-select',     // Auto-generated if not provided
    'class' => 'mb-4',            // Additional classes (optional)
]) ?>
```

#### 4. Status Badge Component

**Location:** `app/Views/components/status_badge.php`

**Usage:**
```php
<?= view('components/status_badge', [
    'status' => 'confirmed',      // pending|confirmed|completed|cancelled|no-show|active|inactive
    'count' => 5,                 // Optional count display
    'showDot' => true,            // Show colored dot indicator (default: true)
    'size' => 'md',               // sm|md|lg (default: md)
    'class' => 'ml-2',            // Additional classes (optional)
]) ?>
```

**Status Colors (automatic):**
- `pending` → Yellow/Amber
- `confirmed` → Blue
- `completed` → Green
- `cancelled` → Red
- `no-show` → Gray
- `active` → Green
- `inactive` → Gray

### When to Create New Components

Create a new component when:
1. The pattern appears 3+ times across different views
2. The markup is complex (10+ lines)
3. The element requires consistent behavior/styling
4. Multiple props/variants are needed

**Example:** If you find yourself copying the same button markup with different labels/icons, use the button component instead.

---

## Dark Mode

### Canonical Selector

WebSchedulr uses **attribute-based dark mode**:

```html
<html data-theme="dark">
```

**NOT:**
```html
<html class="dark">
```

### Dark Mode Utilities

All Tailwind dark mode utilities work with the `data-theme="dark"` selector:

```php
<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
  Content adapts to theme
</div>
```

### Dark Mode Manager

JavaScript dark mode toggle is handled by `resources/js/dark-mode.js`. Do not implement custom dark mode toggles.

### Testing Dark Mode

1. Toggle theme in UI (icon in header)
2. Verify all elements adapt correctly
3. Check Material Design 3 tokens respond to theme

---

## Anti-Patterns

### ❌ Anti-Pattern 1: Inline Styles in App Views

**DON'T:**
```php
<div style="margin-top: 20px; color: #333;">
  Content
</div>
```

**DO:**
```php
<div class="mt-5 text-gray-700 dark:text-gray-300">
  Content
</div>
```

**Exception:** Email templates and framework error pages may use inline styles.

---

### ❌ Anti-Pattern 2: Hardcoded Colors

**DON'T:**
```php
<button class="bg-blue-600 text-white">Save</button>
```

**DO:**
```php
<?= view('components/button', [
    'text' => 'Save',
    'variant' => 'filled'
]) ?>
```

Or use semantic classes:
```php
<button class="bg-primary text-on-primary">Save</button>
```

---

### ❌ Anti-Pattern 3: Duplicate Button Markup

**DON'T:**
```php
<!-- Repeated in 10 files -->
<button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
  Save Changes
</button>
```

**DO:**
```php
<?= view('components/button', [
    'text' => 'Save Changes',
    'type' => 'submit',
    'variant' => 'filled'
]) ?>
```

---

### ❌ Anti-Pattern 4: Inconsistent Dark Mode Classes

**DON'T:**
```php
<div class="bg-white text-black">Content</div>
```

**DO:**
```php
<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Content</div>
```

---

### ❌ Anti-Pattern 5: Non-Semantic Status Colors

**DON'T:**
```php
<span class="bg-yellow-500 text-white px-2 py-1 rounded">Pending</span>
```

**DO:**
```php
<?= view('components/status_badge', ['status' => 'pending']) ?>
```

---

### ❌ Anti-Pattern 6: Manual Form Input Markup

**DON'T:**
```php
<div>
  <label for="email" class="form-label required">Email</label>
  <input type="email" id="email" name="email" class="form-input" required />
  <?php if ($validation->getError('email')): ?>
    <p class="text-sm text-red-600"><?= $validation->getError('email') ?></p>
  <?php endif; ?>
</div>
```

**DO:**
```php
<?= view('components/input', [
    'name' => 'email',
    'label' => 'Email',
    'type' => 'email',
    'required' => true,
    'error' => $validation->getError('email')
]) ?>
```

---

## Contribution Guidelines

### Design System Checklist for PRs

Before submitting a PR that touches UI:

- [ ] **No inline `style=` attributes** in app-facing views
- [ ] **No hardcoded color values** (use semantic classes or components)
- [ ] **Use existing components** (button, input, select, status_badge) instead of duplicating markup
- [ ] **Include dark mode classes** (`dark:*`) for all color/background utilities
- [ ] **Follow typography scale** (use Tailwind text-* classes, not custom font-size)
- [ ] **Use Tailwind spacing utilities** (not arbitrary values like `mt-[17px]`)
- [ ] **Test in dark mode** before submitting

### How to Add a New Component

1. Check if similar component exists in `app/Views/components/`
2. Create component file: `app/Views/components/your-component.php`
3. Document all props with defaults and types
4. Support dark mode with `dark:*` classes
5. Test in multiple contexts (forms, modals, cards)
6. Update this document with usage examples

### Questions?

- Review existing components in `app/Views/components/`
- Check `docs/design/AUDIT_SUMMARY.md` for baseline metrics
- Ask in GitHub Discussions

---

## Resources

- **Material Design 3:** https://m3.material.io/
- **Tailwind CSS:** https://tailwindcss.com/docs
- **Design Roadmap:** `design-system-roadmap.md`
- **Audit Summary:** `docs/design/AUDIT_SUMMARY.md`
- **Component Examples:** `app/Views/components/`

---

**Version:** 1.0.0  
**Maintained by:** WebSchedulr Team

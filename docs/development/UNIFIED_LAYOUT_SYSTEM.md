# Unified Layout System Documentation

## Overview

This document describes the **single, reusable layout system** for all XScheduler views. The system eliminates layout inconsistencies and enforces design standards aligned with TailAdmin CRM quality.

**CRITICAL RULE**: All pages MUST use components from this system. NO custom layouts, spacing, or cards per page.

---

## Table of Contents

1. [Layout Variants](#layout-variants)
2. [Content Wrappers](#content-wrappers)
3. [Card System](#card-system)
4. [Page Headers](#page-headers)
5. [Typography Scale](#typography-scale)
6. [Spacing Tokens](#spacing-tokens)
7. [Button System](#button-system)
8. [Grid Systems](#grid-systems)
9. [Form Layouts](#form-layouts)
10. [Usage Examples](#usage-examples)

---

## Layout Variants

The system provides **two layout variants** to handle different content densities:

### 1. Standard Layout (`xs-content-standard`)

**Use for**: Customer Management, User Management, Services, Analytics, Settings, Notifications, Help

**Characteristics**:
- Max-width: `1200px` (optimized for readability)
- Section spacing: `1.5rem` (mobile) / `2rem` (desktop)
- Use when content requires generous whitespace and focus

**How to use**:
```php
<?= $this->extend('layouts/app') ?>
// Standard variant is the default - no action needed
```

### 2. Dashboard Layout (`xs-content-dashboard`)

**Use for**: Dashboard, Profile, Analytics Grids

**Characteristics**:
- Max-width: `1600px` (wider for dense grids)
- Section spacing: `1rem` (mobile) / `1.5rem` (desktop) - tighter
- Use when showing multiple stat cards, charts, or data grids

**How to use**:
```php
<?= $this->extend('layouts/dashboard') ?>
// OR explicitly in layouts/app:
<?= $this->section('layout_variant') ?>dashboard<?= $this->endSection() ?>
```

---

## Content Wrappers

### `.xs-content-wrapper`

**Purpose**: Primary container for all page content. Provides consistent max-width and horizontal padding.

**Applied automatically** by `layouts/app.php` - DO NOT add this manually to individual views.

**CSS**:
```scss
.xs-content-wrapper {
  width: 100%;
  max-width: 1600px;
  margin: 0 auto;
  padding-left: 1rem; // mobile
  padding-right: 1rem;
  
  @media (min-width: 768px) {
    padding-left: 1.5rem; // desktop
    padding-right: 1.5rem;
  }
}
```

---

## Card System

### Base Component: `app/Views/components/card.php`

**DO NOT create custom card HTML**. Use this component for ALL cards.

### Default Card

```php
<?= view('components/card', [
    'title' => 'Card Title',
    'subtitle' => 'Optional subtitle',
    'content' => '<p>Card content goes here</p>',
    'footer' => '<div class="xs-actions-container xs-actions-right">
                    <a href="#" class="xs-btn xs-btn-primary">Action</a>
                 </div>',
    'actions' => [
        '<button class="xs-btn xs-btn-ghost xs-btn-icon"><span class="material-symbols-outlined">more_vert</span></button>'
    ]
]) ?>
```

**Renders**:
```html
<div class="xs-card">
  <div class="xs-card-header">
    <div class="xs-card-header-content">
      <h3 class="xs-card-title">Card Title</h3>
      <div class="xs-card-subtitle">Optional subtitle</div>
    </div>
    <div class="xs-card-actions">
      <!-- action buttons -->
    </div>
  </div>
  <div class="xs-card-body">
    <p>Card content goes here</p>
  </div>
  <div class="xs-card-footer">
    <!-- footer content -->
  </div>
</div>
```

### Stat Card Variant

For displaying metrics/KPIs:

```php
<?= view('components/card', [
    'variant' => 'stat',
    'content' => '
        <div class="xs-stat-value">1,234</div>
        <div class="xs-stat-label">Total Customers</div>
        <div class="xs-stat-change xs-stat-up">+12% from last month</div>
    '
]) ?>
```

### Chart Card Variant

For visualizations:

```php
<?= view('components/card', [
    'variant' => 'chart',
    'title' => 'Revenue Trend',
    'content' => '<canvas id="revenueChart"></canvas>'
]) ?>
```

### Card Body Variants

Control padding with `bodyClass` parameter:

- **Default**: `1.5rem` (mobile) / `2rem` (desktop)
- **Compact**: `bodyClass="xs-card-body-compact"` - `1rem` / `1.5rem`
- **Spacious**: `bodyClass="xs-card-body-spacious"` - `2rem` / `3rem`

### Interactive Cards

Make cards clickable:

```php
<?= view('components/card', [
    'interactive' => true,
    'title' => 'Clickable Card',
    'content' => '<p>Click anywhere on this card</p>'
]) ?>
```

Adds hover effects: elevated shadow and blue border.

---

## Page Headers

### Component: `app/Views/components/page-header.php`

**DO NOT create custom page headers**. Use this component.

### Basic Usage

```php
<?= view('components/page-header', [
    'title' => 'Customer Management',
    'subtitle' => 'View and manage all customers in your system'
]) ?>
```

### With Actions

```php
<?= view('components/page-header', [
    'title' => 'Customer Management',
    'subtitle' => 'View and manage all customers',
    'actions' => [
        '<a href="/customers/create" class="xs-btn xs-btn-primary">
            <span class="material-symbols-outlined">add</span>
            Add Customer
        </a>',
        '<button class="xs-btn xs-btn-secondary">
            <span class="material-symbols-outlined">file_download</span>
            Export
        </button>'
    ]
]) ?>
```

### With Breadcrumbs

```php
<?= view('components/page-header', [
    'title' => 'Edit Customer',
    'breadcrumbs' => [
        ['label' => 'Customers', 'url' => '/customers'],
        ['label' => 'John Doe', 'url' => '/customers/123'],
        ['label' => 'Edit']
    ]
]) ?>
```

**Renders**:
```html
<div class="xs-page-header">
  <div class="xs-page-header-content">
    <nav class="xs-breadcrumbs">...</nav>
    <h1 class="xs-page-title">Edit Customer</h1>
    <p class="xs-text-muted">Subtitle here</p>
  </div>
  <div class="xs-page-header-actions">
    <!-- action buttons -->
  </div>
</div>
```

---

## Typography Scale

**CRITICAL**: NO custom font sizing. Use these classes only.

### Page Title (H1)

```html
<h1 class="xs-page-title">Page Title</h1>
```
- Size: `1.875rem` (mobile) / `2.25rem` (desktop)
- Weight: `700` (bold)
- Use: Page title ONLY (one per page)

### Section Title (H2)

```html
<h2 class="xs-section-title">Section Title</h2>
```
- Size: `1.5rem` (mobile) / `1.875rem` (desktop)
- Weight: `600` (semibold)
- Use: Major sections within a page

### Component Title (H3)

```html
<h3 class="xs-component-title">Component Title</h3>
```
- Size: `1.125rem`
- Weight: `600`
- Use: Card titles, form section titles

**NOTE**: Card component handles this automatically with `title` prop.

### Body Text

```html
<p class="xs-body">Body text content</p>
```
- Size: `1rem`
- Line height: `1.6`

### Muted/Secondary Text

```html
<p class="xs-text-muted">Secondary information</p>
```
- Size: `0.875rem`
- Color: Muted gray

### Small Text

```html
<span class="xs-text-small">Helper text, timestamps</span>
```
- Size: `0.75rem`

---

## Spacing Tokens

**CRITICAL**: NO inline spacing (`mt-4`, `mb-6`, etc.). Use these classes only.

### Section Spacing

```html
<section class="xs-space-section">
  <!-- Large vertical spacing for major sections -->
</section>
```
- Mobile: `2rem`
- Desktop: `3rem`

### Component Spacing

```html
<div class="xs-space-component">
  <!-- Medium spacing between components -->
</div>
```
- All breakpoints: `1.5rem`

### Element Spacing

```html
<div class="xs-space-element">
  <!-- Small spacing between related elements -->
</div>
```
- All breakpoints: `1rem`

### Grid Gaps

```html
<div class="xs-grid xs-grid-3 xs-grid-gap-default">
  <!-- Cards with default gap -->
</div>
```

Available:
- `.xs-grid-gap-default` - `1.5rem`
- `.xs-grid-gap-tight` - `1rem`
- `.xs-grid-gap-loose` - `2rem`

**NOTE**: Layout variants handle spacing automatically. You typically DON'T need these classes.

---

## Button System

**DO NOT create custom button styles**. Use these classes.

### Primary Button

```html
<button class="xs-btn xs-btn-primary">
  <span class="material-symbols-outlined">add</span>
  Create New
</button>
```

### Secondary Button

```html
<button class="xs-btn xs-btn-secondary">
  <span class="material-symbols-outlined">filter_list</span>
  Filters
</button>
```

### Destructive Button

```html
<button class="xs-btn xs-btn-destructive">
  <span class="material-symbols-outlined">delete</span>
  Delete
</button>
```

### Ghost Button

```html
<button class="xs-btn xs-btn-ghost">
  Cancel
</button>
```

### Size Variants

- **Small**: `.xs-btn-sm`
- **Default**: (no class needed)
- **Large**: `.xs-btn-lg`

### Icon-Only Button

```html
<button class="xs-btn xs-btn-ghost xs-btn-icon">
  <span class="material-symbols-outlined">more_vert</span>
</button>
```

### Action Containers

Group buttons with consistent spacing:

```html
<div class="xs-actions-container xs-actions-right">
  <button class="xs-btn xs-btn-secondary">Cancel</button>
  <button class="xs-btn xs-btn-primary">Save</button>
</div>
```

Alignment options:
- `.xs-actions-right` - Right-aligned (default for forms)
- `.xs-actions-left` - Left-aligned
- `.xs-actions-between` - Space-between

---

## Grid Systems

**DO NOT create custom grids**. Use predefined responsive grids.

### 2-Column Grid

```html
<div class="xs-grid xs-grid-2">
  <div>Column 1</div>
  <div>Column 2</div>
</div>
```

Mobile: 1 column  
Desktop (768px+): 2 columns

### 3-Column Grid

```html
<div class="xs-grid xs-grid-3">
  <div>Column 1</div>
  <div>Column 2</div>
  <div>Column 3</div>
</div>
```

Mobile: 1 column  
Tablet (768px+): 2 columns  
Desktop (1024px+): 3 columns

### 4-Column Grid

```html
<div class="xs-grid xs-grid-4">
  <!-- 4 items -->
</div>
```

Mobile: 1 column  
Small (640px+): 2 columns  
Desktop (1024px+): 4 columns

### Dashboard Stats Grid

For stat cards specifically:

```html
<div class="xs-grid xs-grid-dashboard-stats">
  <?= view('components/card', ['variant' => 'stat', ...]) ?>
  <?= view('components/card', ['variant' => 'stat', ...]) ?>
  <?= view('components/card', ['variant' => 'stat', ...]) ?>
  <?= view('components/card', ['variant' => 'stat', ...]) ?>
</div>
```

Mobile: 2 columns (tight)  
Tablet (768px+): 4 columns  
Large (1280px+): 4 columns (looser gap)

---

## Form Layouts

### Form Group

```html
<div class="xs-form-group">
  <label for="email" class="xs-form-label xs-required">Email</label>
  <input type="email" id="email" class="xs-form-input" placeholder="Enter email">
  <div class="xs-form-help">We'll never share your email</div>
  <div class="xs-form-error">Email is required</div>
</div>
```

### Form Grid (2 Columns)

```html
<div class="xs-form-grid xs-form-grid-2">
  <div class="xs-form-group">
    <label class="xs-form-label">First Name</label>
    <input type="text" class="xs-form-input">
  </div>
  <div class="xs-form-group">
    <label class="xs-form-label">Last Name</label>
    <input type="text" class="xs-form-input">
  </div>
</div>
```

### Form Actions

```html
<div class="xs-form-actions">
  <button type="button" class="xs-btn xs-btn-ghost">Cancel</button>
  <button type="submit" class="xs-btn xs-btn-primary">Save Changes</button>
</div>
```

Automatically:
- Right-aligned on desktop
- Stacked full-width on mobile (reversed order)
- Border top separator

---

## Usage Examples

### Example 1: Customer List Page (Standard Layout)

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('header_title') ?>Customers<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page-header', [
    'title' => 'Customer Management',
    'subtitle' => 'View and manage all customers in your system',
    'actions' => [
        '<a href="/customers/create" class="xs-btn xs-btn-primary">
            <span class="material-symbols-outlined">add</span>
            Add Customer
        </a>'
    ]
]) ?>

<?= view('components/card', [
    'title' => 'All Customers',
    'subtitle' => '1,234 total customers',
    'content' => '
        <div class="table-responsive">
            <table>...</table>
        </div>
    ',
    'footer' => '
        <div class="xs-actions-container xs-actions-between">
            <span class="xs-text-muted">Showing 1-25 of 1,234</span>
            <div class="flex gap-2">
                <button class="xs-btn xs-btn-sm xs-btn-secondary">Previous</button>
                <button class="xs-btn xs-btn-sm xs-btn-secondary">Next</button>
            </div>
        </div>
    '
]) ?>

<?= $this->endSection() ?>
```

### Example 2: Dashboard Page (Dashboard Layout)

```php
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('header_title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page-header', [
    'title' => 'Dashboard',
    'subtitle' => 'Overview of your business performance'
]) ?>

<div class="xs-grid xs-grid-dashboard-stats">
    <?= view('components/card', [
        'variant' => 'stat',
        'content' => '
            <div class="xs-stat-value">1,234</div>
            <div class="xs-stat-label">Total Customers</div>
            <div class="xs-stat-change xs-stat-up">+12% from last month</div>
        '
    ]) ?>
    
    <?= view('components/card', [
        'variant' => 'stat',
        'content' => '
            <div class="xs-stat-value">$45.2K</div>
            <div class="xs-stat-label">Revenue</div>
            <div class="xs-stat-change xs-stat-up">+8% from last month</div>
        '
    ]) ?>
    
    <!-- 2 more stat cards -->
</div>

<div class="xs-grid xs-grid-2 mt-6">
    <?= view('components/card', [
        'variant' => 'chart',
        'title' => 'Revenue Trend',
        'content' => '<canvas id="revenueChart"></canvas>'
    ]) ?>
    
    <?= view('components/card', [
        'title' => 'Recent Activities',
        'content' => '<ul class="space-y-3">...</ul>'
    ]) ?>
</div>

<?= $this->endSection() ?>
```

### Example 3: Create/Edit Form (Standard Layout)

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('header_title') ?>Create Customer<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page-header', [
    'title' => 'Create New Customer',
    'breadcrumbs' => [
        ['label' => 'Customers', 'url' => '/customers'],
        ['label' => 'Create']
    ]
]) ?>

<?= view('components/card', [
    'title' => 'Customer Information',
    'content' => '
        <form action="/customers/store" method="post">
            <div class="xs-form-grid xs-form-grid-2">
                <div class="xs-form-group">
                    <label for="first_name" class="xs-form-label xs-required">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="xs-form-input" required>
                </div>
                
                <div class="xs-form-group">
                    <label for="last_name" class="xs-form-label xs-required">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="xs-form-input" required>
                </div>
            </div>
            
            <div class="xs-form-group">
                <label for="email" class="xs-form-label xs-required">Email</label>
                <input type="email" id="email" name="email" class="xs-form-input" required>
                <div class="xs-form-help">We will send notifications to this email</div>
            </div>
            
            <div class="xs-form-actions">
                <a href="/customers" class="xs-btn xs-btn-ghost">Cancel</a>
                <button type="submit" class="xs-btn xs-btn-primary">Create Customer</button>
            </div>
        </form>
    '
]) ?>

<?= $this->endSection() ?>
```

---

## CSS Variables Reference

The system uses CSS custom properties for theming:

```scss
:root {
  // Layout spacing
  --xs-frame-inset-desktop: 1rem;        // Sidebar/header positioning
  --xs-content-inset-mobile: 1rem;       // Content horizontal padding (mobile)
  --xs-content-inset-desktop: 1.5rem;    // Content horizontal padding (desktop)
  --xs-sidebar-width: 16rem;
  --xs-header-height: 4.5rem;
  
  // Spacing tokens
  --xs-space-4: 1rem;
  --xs-space-6: 1.5rem;
  --xs-space-8: 2rem;
  --xs-space-12: 3rem;
  
  // Colors (set by theme)
  --xs-bg-primary: #ffffff;
  --xs-bg-secondary: #f9fafb;
  --xs-text-primary: #111827;
  --xs-text-secondary: #6b7280;
  --xs-text-muted: #9ca3af;
  --xs-border: #e5e7eb;
  --xs-border-light: #f3f4f6;
  --xs-accent: #3b82f6;
  --xs-error: #ef4444;
  --xs-success: #22c55e;
  
  // Border radius
  --xs-radius-sm: 0.375rem;
  --xs-radius-md: 0.5rem;
  --xs-radius-lg: 0.75rem;
  --xs-radius-xl: 1rem;
}

html.dark {
  --xs-bg-primary: #1f2937;
  --xs-bg-secondary: #2d3748;
  --xs-text-primary: #f9fafb;
  --xs-text-secondary: #d1d5db;
  --xs-text-muted: #9ca3af;
  --xs-border: #374151;
  --xs-border-light: #2d3748;
}
```

---

## Migration Checklist

When refactoring existing views to use this system:

- [ ] Remove all custom card HTML - use `components/card.php`
- [ ] Remove all custom page headers - use `components/page-header.php`
- [ ] Remove all inline spacing classes (`mt-4`, `mb-6`, `space-y-4`)
- [ ] Remove all custom font sizing - use typography classes
- [ ] Remove all custom button styles - use `.xs-btn` variants
- [ ] Replace custom grids with `.xs-grid-*` classes
- [ ] Choose correct layout variant (`standard` vs `dashboard`)
- [ ] Ensure no `class="main-content"` or custom wrapper divs
- [ ] Use `.xs-form-group` and related classes for forms
- [ ] Test on mobile, tablet, and desktop breakpoints
- [ ] Verify dark mode styling

---

## SCSS File Structure

```
resources/scss/
├── layout/
│   ├── _app-layout.scss              # Base app structure (sidebar, header, container)
│   └── _unified-content-system.scss  # Content wrappers, cards, buttons, grids, forms
├── components/
│   ├── _buttons.scss                 # Legacy (can be removed after migration)
│   ├── _cards.scss                   # Legacy (can be removed after migration)
│   └── _unified-sidebar.scss         # Sidebar visual styling
└── app-consolidated.scss             # Main entry point (imports all)
```

---

## Support & Questions

If you need a component that doesn't exist in this system:

1. **DO NOT create custom HTML/CSS**
2. Check if an existing component can be adapted
3. Document the requirement and discuss with the team
4. Update this system centrally before implementing across pages

This ensures consistency and prevents fragmentation.

---

**Last Updated**: 2024  
**Maintained By**: XScheduler Development Team

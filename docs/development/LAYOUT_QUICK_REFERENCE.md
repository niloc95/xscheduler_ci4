# Unified Layout System - Quick Reference

## Component Usage

### Page Header
```php
<?= view('components/page-header', [
    'title' => 'Page Title',
    'subtitle' => 'Optional description',
    'actions' => ['<button class="xs-btn xs-btn-primary">Action</button>'],
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Current Page']
    ]
]) ?>
```

### Card - Default
```php
<?= view('components/card', [
    'title' => 'Card Title',
    'subtitle' => 'Optional subtitle',
    'content' => '<p>Card body content</p>',
    'footer' => '<div class="xs-actions-container xs-actions-right">...</div>',
    'actions' => ['<button>...</button>'],
    'bodyClass' => 'xs-card-body-compact' // optional: compact, spacious
]) ?>
```

### Card - Stat
```php
<?= view('components/card', [
    'variant' => 'stat',
    'content' => '
        <div class="xs-stat-value">1,234</div>
        <div class="xs-stat-label">Total Customers</div>
        <div class="xs-stat-change xs-stat-up">+12%</div>
    '
]) ?>
```

### Card - Chart
```php
<?= view('components/card', [
    'variant' => 'chart',
    'title' => 'Revenue Trend',
    'content' => '<canvas id="chart"></canvas>'
]) ?>
```

## Layout Variants

### Standard (default)
```php
<?= $this->extend('layouts/app') ?>
// No action needed - standard is default
```
**Use for**: Customer, User, Service management, Settings, Help

### Dashboard
```php
<?= $this->extend('layouts/dashboard') ?>
// OR
<?= $this->section('layout_variant') ?>dashboard<?= $this->endSection() ?>
```
**Use for**: Dashboard, Profile, Analytics with stats/charts

## CSS Classes Quick Reference

### Typography
```html
<h1 class="xs-page-title">Page Title</h1>
<h2 class="xs-section-title">Section Title</h2>
<h3 class="xs-component-title">Component Title</h3>
<p class="xs-body">Body text</p>
<p class="xs-text-muted">Secondary text</p>
<span class="xs-text-small">Helper text</span>
```

### Buttons
```html
<!-- Primary -->
<button class="xs-btn xs-btn-primary">Save</button>

<!-- Secondary -->
<button class="xs-btn xs-btn-secondary">Cancel</button>

<!-- Destructive -->
<button class="xs-btn xs-btn-destructive">Delete</button>

<!-- Ghost -->
<button class="xs-btn xs-btn-ghost">Dismiss</button>

<!-- Sizes: sm, default, lg -->
<button class="xs-btn xs-btn-sm xs-btn-primary">Small</button>
<button class="xs-btn xs-btn-lg xs-btn-primary">Large</button>

<!-- Icon only -->
<button class="xs-btn xs-btn-icon xs-btn-ghost">
    <span class="material-symbols-outlined">more_vert</span>
</button>

<!-- With icon + text -->
<button class="xs-btn xs-btn-primary">
    <span class="material-symbols-outlined">add</span>
    Create New
</button>
```

### Button Containers
```html
<!-- Right aligned (forms) -->
<div class="xs-actions-container xs-actions-right">
    <button class="xs-btn xs-btn-ghost">Cancel</button>
    <button class="xs-btn xs-btn-primary">Save</button>
</div>

<!-- Space between -->
<div class="xs-actions-container xs-actions-between">
    <button class="xs-btn xs-btn-secondary">Back</button>
    <button class="xs-btn xs-btn-primary">Next</button>
</div>

<!-- Left aligned -->
<div class="xs-actions-container xs-actions-left">
    <button class="xs-btn xs-btn-primary">Action</button>
</div>
```

### Grids
```html
<!-- 2 columns -->
<div class="xs-grid xs-grid-2">
    <div>Col 1</div>
    <div>Col 2</div>
</div>

<!-- 3 columns -->
<div class="xs-grid xs-grid-3">...</div>

<!-- 4 columns -->
<div class="xs-grid xs-grid-4">...</div>

<!-- Dashboard stats (2 mobile, 4 desktop) -->
<div class="xs-grid xs-grid-dashboard-stats">
    <?= view('components/card', ['variant' => 'stat', ...]) ?>
    <?= view('components/card', ['variant' => 'stat', ...]) ?>
    <?= view('components/card', ['variant' => 'stat', ...]) ?>
    <?= view('components/card', ['variant' => 'stat', ...]) ?>
</div>
```

### Forms
```html
<!-- Form group -->
<div class="xs-form-group">
    <label for="email" class="xs-form-label xs-required">Email</label>
    <input type="email" id="email" class="xs-form-input" required>
    <div class="xs-form-help">Helper text</div>
    <div class="xs-form-error">Error message</div>
</div>

<!-- Form grid (2 columns on desktop) -->
<div class="xs-form-grid xs-form-grid-2">
    <div class="xs-form-group">...</div>
    <div class="xs-form-group">...</div>
</div>

<!-- Form actions (auto-styled footer) -->
<div class="xs-form-actions">
    <button type="button" class="xs-btn xs-btn-ghost">Cancel</button>
    <button type="submit" class="xs-btn xs-btn-primary">Save</button>
</div>
```

### Spacing (Use sparingly - layout handles most spacing)
```html
<!-- Section spacing (2rem mobile, 3rem desktop) -->
<section class="xs-space-section">...</section>

<!-- Component spacing (1.5rem) -->
<div class="xs-space-component">...</div>

<!-- Element spacing (1rem) -->
<div class="xs-space-element">...</div>
```

## Common Patterns

### CRUD Index Page
```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('header_title') ?>Customers<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page-header', [
    'title' => 'Customer Management',
    'subtitle' => 'View and manage all customers',
    'actions' => [
        '<a href="/create" class="xs-btn xs-btn-primary">
            <span class="material-symbols-outlined">add</span>
            Add Customer
        </a>'
    ]
]) ?>

<?= view('components/card', [
    'title' => 'All Customers',
    'subtitle' => '1,234 total',
    'content' => '<div class="overflow-x-auto"><table>...</table></div>',
    'footer' => '<!-- pagination -->'
]) ?>

<?= $this->endSection() ?>
```

### Create/Edit Form
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
                    <label class="xs-form-label xs-required">First Name</label>
                    <input type="text" class="xs-form-input" required>
                </div>
                <div class="xs-form-group">
                    <label class="xs-form-label xs-required">Last Name</label>
                    <input type="text" class="xs-form-input" required>
                </div>
            </div>
            
            <div class="xs-form-actions">
                <a href="/customers" class="xs-btn xs-btn-ghost">Cancel</a>
                <button type="submit" class="xs-btn xs-btn-primary">Create</button>
            </div>
        </form>
    '
]) ?>

<?= $this->endSection() ?>
```

### Dashboard with Stats
```php
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('header_title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page-header', [
    'title' => 'Dashboard',
    'subtitle' => 'Overview of your business'
]) ?>

<!-- Stats Grid -->
<div class="xs-grid xs-grid-dashboard-stats">
    <?php for ($i = 0; $i < 4; $i++): ?>
        <?= view('components/card', [
            'variant' => 'stat',
            'content' => '
                <div class="xs-stat-value">1,234</div>
                <div class="xs-stat-label">Metric Name</div>
                <div class="xs-stat-change xs-stat-up">+12%</div>
            '
        ]) ?>
    <?php endfor; ?>
</div>

<!-- Charts -->
<div class="xs-grid xs-grid-2">
    <?= view('components/card', [
        'variant' => 'chart',
        'title' => 'Revenue',
        'content' => '<canvas id="revenueChart"></canvas>'
    ]) ?>
    
    <?= view('components/card', [
        'variant' => 'chart',
        'title' => 'Appointments',
        'content' => '<canvas id="appointmentsChart"></canvas>'
    ]) ?>
</div>

<?= $this->endSection() ?>
```

## Rules to Remember

1. ✅ **DO** use components (`page-header`, `card`)
2. ✅ **DO** use unified CSS classes (`xs-btn`, `xs-form-input`)
3. ✅ **DO** choose the correct layout variant (`standard` vs `dashboard`)
4. ❌ **DON'T** create custom card HTML
5. ❌ **DON'T** create custom button styles
6. ❌ **DON'T** use inline spacing (`mt-4`, `mb-6`) on cards/sections
7. ❌ **DON'T** use custom font sizes - use typography classes
8. ❌ **DON'T** add `main-content` wrapper divs - layout provides it

## Migration Checklist

When updating an existing view:

- [ ] Replace custom page header with `page-header` component
- [ ] Replace custom card HTML with `card` component
- [ ] Update all button classes to `xs-btn` variants
- [ ] Update form inputs to `xs-form-input`
- [ ] Remove inline spacing utilities
- [ ] Remove custom typography classes
- [ ] Choose correct layout variant
- [ ] Remove any `main-content` wrapper divs
- [ ] Test responsive behavior (mobile, tablet, desktop)
- [ ] Test dark mode

## Need Help?

See full documentation: `docs/development/UNIFIED_LAYOUT_SYSTEM.md`

Migration example: `docs/development/MIGRATION_EXAMPLE.md`

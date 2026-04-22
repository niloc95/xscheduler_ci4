# WebSchedulr UI Standards & Component Library

## Overview

This document defines the unified templating architecture for WebSchedulr, inspired by TailAdmin's design patterns. It provides a single source of truth for layouts, navigation, spacing, typography, and reusable UI components.

## Architecture

### Layout Hierarchy

```
layouts/
├── app.php          # Main authenticated application layout
├── auth.php         # Authentication flows (login, reset password)
├── setup.php        # Installation/setup wizard
└── public.php       # Customer-facing public pages

components/ui/
├── page-header.php  # Standardized page headers with breadcrumbs
├── card.php         # Content container cards
├── stat-card.php    # Dashboard metric displays
├── button.php       # Button variants
├── table.php        # Data tables
├── pagination.php   # Pagination controls
├── flash-messages.php # Session flash notifications
├── empty-state.php  # Empty state displays
├── modal.php        # Modal dialogs
├── badge.php        # Status badges/tags
├── input.php        # Form text inputs
├── select.php       # Form dropdowns
├── textarea.php     # Form textareas
├── toggle.php       # Toggle switches
└── step-indicator.php # Multi-step progress
```

## Design Tokens

### Spacing Scale (8px base)

| Token | Value | Usage |
|-------|-------|-------|
| `--xs-space-1` | 0.25rem (4px) | Micro spacing |
| `--xs-space-2` | 0.5rem (8px) | Tight spacing |
| `--xs-space-3` | 0.75rem (12px) | Component internal |
| `--xs-space-4` | 1rem (16px) | Default spacing |
| `--xs-space-5` | 1.25rem (20px) | Medium spacing |
| `--xs-space-6` | 1.5rem (24px) | Section spacing |
| `--xs-space-8` | 2rem (32px) | Large spacing |

### Border Radius

| Token | Value | Usage |
|-------|-------|-------|
| `--radius-standard` | 0.75rem | Primary containers (cards/sidebar/header) |
| `--radius-control-sm` | 0.5rem | Compact controls |
| `--radius-control-md` | 0.625rem | Default buttons, inputs |
| `--radius-control-lg` | 0.75rem | Large controls |
| `--xs-radius-sm` | `var(--radius-control-sm)` | Legacy alias |
| `--xs-radius-md` | `var(--radius-control-md)` | Legacy alias |
| `--xs-radius-lg` | `var(--radius-standard)` | Legacy alias |
| `--xs-radius-full` | 9999px | Pills, avatars |

### Shadows

| Token | Usage |
|-------|-------|
| `--xs-shadow-sm` | Subtle elevation |
| `--xs-shadow-md` | Cards, dropdowns |
| `--xs-shadow-lg` | Modals, popovers |
| `--xs-shadow-xl` | High emphasis |

### Colors

Canonical token strategy:

- `--color-bg-primary`
- `--color-bg-secondary`
- `--color-text-primary`
- `--color-border`
- `--color-accent`

Compatibility aliases remain in place (`--ws-*` and `--xs-*`) for progressive migration.

Tailwind usage:

- **Primary**: `primary-50` through `primary-900` (brand color)
- **Success**: `green-*` (completed, active)
- **Warning**: `amber-*` (pending, attention)
- **Danger**: `red-*` (errors, destructive)
- **Info**: `blue-*` (informational)

## Layout Usage

### Authenticated Pages (layouts/app.php)

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>Page Title<?= $this->endSection() ?>

<?= $this->section('page_header') ?>
<?= $this->include('components/ui/page-header', [
    'title' => 'Page Title',
    'subtitle' => 'Optional description',
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => '/'],
        ['label' => 'Section', 'href' => '/section'],
        ['label' => 'Current Page']
    ],
    'actions' => '<button>Action</button>'
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Page content here -->
<?= $this->endSection() ?>
```

### Authentication Pages (layouts/auth.php)

```php
<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Login<?= $this->endSection() ?>

<?= $this->section('auth_title') ?>Welcome Back<?= $this->endSection() ?>
<?= $this->section('auth_subtitle') ?>Sign in to continue<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form><!-- Login form --></form>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<a href="/register">Create account</a>
<?= $this->endSection() ?>
```

### Setup Wizard (layouts/setup.php)

```php
<?= $this->extend('layouts/setup') ?>

<?= $this->section('step_indicator') ?>
<?= $this->include('components/ui/step-indicator', [
    'steps' => [
        ['label' => 'Database', 'icon' => 'database'],
        ['label' => 'Business', 'icon' => 'storefront'],
        ['label' => 'Admin', 'icon' => 'person'],
        ['label' => 'Complete', 'icon' => 'check_circle']
    ],
    'currentStep' => 2
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="p-6"><!-- Step content --></div>
<?= $this->endSection() ?>
```

## Component Reference

### Page Header

```php
<?= $this->include('components/ui/page-header', [
    'title' => 'Customers',           // Required
    'subtitle' => 'Manage customers', // Optional
    'breadcrumbs' => [...],           // Optional array
    'actions' => '<button>Add</button>' // Optional HTML
]) ?>
```

### Card

```php
<?= $this->include('components/ui/card', [
    'title' => 'Card Title',      // Optional
    'subtitle' => 'Description',  // Optional
    'icon' => 'settings',         // Optional Material Symbol
    'headerActions' => '...',     // Optional header action HTML
    'content' => '...',           // Main content
    'footer' => '...',            // Optional footer HTML
    'padding' => 'default',       // 'none', 'small', 'default', 'large'
    'class' => ''                 // Additional classes
]) ?>
```

### Stat Card

```php
<?= $this->include('components/ui/stat-card', [
    'label' => 'Total Customers',   // Required
    'value' => '1,234',             // Required
    'icon' => 'people',             // Optional
    'color' => 'blue',              // blue, green, amber, red, indigo, purple
    'trend' => [                    // Optional
        'value' => '+12%',
        'direction' => 'up'         // up, down, neutral
    ],
    'href' => '/customers'          // Optional link
]) ?>
```

### Button

```php
<?= $this->include('components/ui/button', [
    'label' => 'Save',
    'variant' => 'primary',         // primary, secondary, success, danger, ghost, outline-*
    'size' => 'md',                 // sm, md, lg
    'icon' => 'save',               // Optional left icon
    'iconRight' => 'arrow_forward', // Optional right icon
    'href' => '/path',              // Makes it a link
    'type' => 'submit',             // button, submit, reset
    'disabled' => false,
    'loading' => false
]) ?>
```

Touch target rule:
- All interactive controls must maintain a minimum 44px hit area.
- Standard buttons inherit this from the shared `.btn` primitive (`min-height: 44px`).
- Icon-only controls must use at least `w-11 h-11`.

### Table

```php
<?= $this->include('components/ui/table', [
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20',
         'render' => function($row) { return '<a href="...">Edit</a>'; }]
    ],
    'rows' => $data,
    'emptyMessage' => 'No customers found',
    'emptyIcon' => 'people',
    'sortBy' => 'name',
    'sortDir' => 'asc',
    'striped' => true,
    'hoverable' => true
]) ?>
```

### Form Inputs

```php
<!-- Text Input -->
<?= $this->include('components/ui/input', [
    'name' => 'email',
    'label' => 'Email Address',
    'type' => 'email',
    'placeholder' => 'you@example.com',
    'icon' => 'mail',
    'required' => true,
    'error' => $validation->getError('email')
]) ?>

<!-- Select -->
<?= $this->include('components/ui/select', [
    'name' => 'status',
    'label' => 'Status',
    'options' => [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive']
    ],
    'value' => 'active',
    'placeholder' => 'Select status...'
]) ?>

<!-- Textarea -->
<?= $this->include('components/ui/textarea', [
    'name' => 'notes',
    'label' => 'Notes',
    'rows' => 4,
    'maxlength' => 500,
    'showCount' => true
]) ?>

<!-- Toggle -->
<?= $this->include('components/ui/toggle', [
    'name' => 'notifications',
    'label' => 'Enable Notifications',
    'description' => 'Receive email updates',
    'checked' => true
]) ?>
```

### Empty State

```php
<?= $this->include('components/ui/empty-state', [
    'icon' => 'calendar_today',
    'title' => 'No appointments',
    'description' => 'Get started by creating your first appointment.',
    'actionLabel' => 'New Appointment',
    'actionHref' => '/appointments/new',
    'actionIcon' => 'add',
    'size' => 'md'                  // sm, md, lg
]) ?>
```

### Badge

```php
<?= $this->include('components/ui/badge', [
    'label' => 'Active',
    'variant' => 'success',         // default, primary, success, warning, danger, info
    'size' => 'md',                 // sm, md, lg
    'dot' => true,                  // Show status dot
    'pill' => true                  // Rounded pill shape
]) ?>
```

### Modal

```php
<!-- Include modal in view -->
<?= $this->include('components/ui/modal', [
    'id' => 'confirm-delete',
    'title' => 'Confirm Delete',
    'icon' => 'warning',
    'iconColor' => 'text-red-500',
    'size' => 'sm'                  // sm, md, lg, xl, full
]) ?>

<!-- Trigger with Alpine.js -->
<button @click="$dispatch('open-modal', 'confirm-delete')">Delete</button>

<!-- Close modal -->
<button @click="$dispatch('close-modal', 'confirm-delete')">Cancel</button>
```

### Pagination

```php
<?= $this->include('components/ui/pagination', [
    'currentPage' => 1,
    'totalPages' => 10,
    'totalItems' => 100,
    'perPage' => 10,
    'baseUrl' => '/customers',
    'showInfo' => true,
    'showPerPage' => true,
    'perPageOptions' => [10, 25, 50, 100]
]) ?>
```

## Icon System

Using Google Material Symbols (Outlined variant only).

```html
<span class="material-symbols-outlined">icon_name</span>
```

Do not use `material-symbols-rounded` in production views.

Common icons:
- Navigation: `home`, `arrow_back`, `menu`, `close`
- Actions: `add`, `edit`, `delete`, `save`, `search`
- Status: `check_circle`, `error`, `warning`, `info`
- Content: `person`, `calendar_today`, `settings`, `notifications`

## Dark Mode

All components support dark mode via Tailwind's `dark:` prefix.

```php
<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
```

## Responsive Breakpoints

| Breakpoint | Width | Usage |
|------------|-------|-------|
| `sm` | 640px | Mobile landscape |
| `md` | 768px | Tablets |
| `lg` | 1024px | Desktop (sidebar visible) |
| `xl` | 1280px | Large desktop |
| `2xl` | 1536px | Extra large |

## Migration Guide

### Converting Existing Views

1. Change `$this->extend('components/layout')` to `$this->extend('layouts/app')`
2. Replace inline page headers with `page-header` component
3. Wrap content cards in `card` component
4. Replace inline metric displays with `stat-card`
5. Update form fields to use form components
6. Replace custom tables with `table` component
7. Add `flash-messages` component where needed

### Before

```php
<?= $this->extend('components/layout') ?>
<?= $this->section('content') ?>
<div class="p-4">
    <h1 class="text-2xl font-bold mb-4">Customers</h1>
    <div class="bg-white rounded-lg p-4">
        <!-- content -->
    </div>
</div>
<?= $this->endSection() ?>
```

### After

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('page_header') ?>
<?= $this->include('components/ui/page-header', [
    'title' => 'Customers',
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => '/'],
        ['label' => 'Customers']
    ]
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('components/ui/flash-messages') ?>
<?= $this->include('components/ui/card', [
    'content' => '<!-- content -->'
]) ?>
<?= $this->endSection() ?>
```

## File Organization

```
app/Views/
├── layouts/           # Master layouts
├── components/
│   ├── ui/           # Reusable UI components
│   ├── forms/        # Form-specific components  
│   └── ...           # Feature components
├── dashboard/        # Dashboard views
├── appointments/     # Appointment views
├── customers/        # Customer views
└── ...
```

## Best Practices

1. **Always use semantic section names** - `title`, `page_header`, `content`, `scripts`
2. **Include flash-messages at the top of content** - User feedback first
3. **Use components for consistency** - Don't reinvent card styles
4. **Follow the spacing scale** - Use Tailwind utilities that match tokens
5. **Test dark mode** - All new components must support dark mode
6. **Mobile-first** - Start with mobile layout, add responsive classes
7. **Accessibility** - Include ARIA labels, proper heading hierarchy

# Global Header System Documentation

## Overview

The application now uses a unified **Global Header System** that consolidates all page titles, subtitles, and header actions into a single, consistent header bar displayed at the top of the layout.

### Key Principles

1. **Single Source of Truth**: Page titles and subtitles are defined ONLY in the global header, never duplicated in content areas.
2. **Consistency**: All pages use the same header styling and structure.
3. **Maintainability**: Changes to header appearance only need to be made in one place.
4. **Accessibility**: Proper semantic HTML with breadcrumb support.

---

## Components

### 1. Global Header Component
**File**: `app/Views/components/global-header.php`

Renders the fixed header bar at the top of the layout. Automatically included from `layouts/app.php`.

**Parameters**:
- `title` (string): Page title (H1) - required, defaults to "Dashboard"
- `subtitle` (string): Page subtitle/description - optional
- `actions` (array): Action buttons/links - optional
- `breadcrumbs` (array): Breadcrumb trail - optional
- `userRole` (string): Current user role - auto-detected from session
- `userName` (string): Current user name - auto-detected from session

---

## How to Use

### In Child Views

All child views extending `layouts/app` or `layouts/dashboard` should define these sections:

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('header_title') ?>
    Page Title Here
<?= $this->endSection() ?>

<?= $this->section('header_subtitle') ?>
    Optional subtitle or description
<?= $this->endSection() ?>

<?= $this->section('header_actions') ?>
    <a href="/create" class="xs-btn xs-btn-primary">
        <span class="material-symbols-outlined">add</span>
        New Item
    </a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <!-- Your page content here -->
    <!-- DO NOT include page titles or headers here -->
<?= $this->endSection() ?>
```

### Dashboard Layout Specifics

For views using `layouts/dashboard`, you can also use:

```php
<?= $this->section('header_title') ?>Dashboard Title<?= $this->endSection() ?>
<?= $this->section('header_subtitle') ?>Dashboard subtitle<?= $this->endSection() ?>
```

The dashboard layout automatically passes these to the global header via `app.php`.

---

## Helper Functions

**File**: `app/Helpers/header_helper.php`

These helpers standardize header data creation in controllers:

### `set_page_header()`
```php
$data = set_page_header(
    'Customers',                    // title
    'Manage all customer profiles', // subtitle
    [                               // actions
        create_action_button('New Customer', '/customer-management/create'),
    ],
    [                               // breadcrumbs
        create_breadcrumb_item('Dashboard', base_url('/dashboard')),
        create_breadcrumb_item('Customers'),
    ]
);

// Returns: ['page_title' => '...', 'page_subtitle' => '...', ...]
```

### `create_action_button()`
```php
$button = create_action_button(
    'Edit',              // label
    '/edit',             // url
    'primary',           // style: 'primary', 'secondary', 'ghost'
    'edit',              // Material icon name (optional)
    ['data-id' => '123'] // additional attributes (optional)
);
```

### `create_breadcrumb_item()`
```php
$item = create_breadcrumb_item(
    'Customers',                      // label
    base_url('/customer-management')  // url (optional - omit for current page)
);
```

### `get_role_display_name()`
```php
$displayName = get_role_display_name('admin');  // Returns: "Administrator"
$displayName = get_role_display_name();         // Auto-detect from session
```

---

## Layout System

### `layouts/app.php`
Master layout for all authenticated pages.

**Available Sections**:
- `title`: Browser tab title
- `head`: Additional CSS/meta tags
- `sidebar`: Custom sidebar (auto-includes unified-sidebar if not provided)
- `header_title`: **Page title (REQUIRED)**
- `header_subtitle`: Page subtitle (optional)
- `header_actions`: Action buttons (optional)
- `header_breadcrumbs`: Breadcrumb trail (optional)
- `content`: **Main page content (REQUIRED)**
- `layout_variant`: "standard" (default) or "dashboard"
- `scripts`: Additional JavaScript
- `modals`: Modal dialogs

### `layouts/dashboard.php`
Specialized layout for dashboard/analytics pages with stats, charts, and grids.

Extends `layouts/app` and provides:
- Stat cards grid
- Filter controls
- Tab navigation
- Dense content spacing

**Additional Sections**:
- `dashboard_intro`: Introductory block
- `dashboard_stats`: Stat card content
- `dashboard_stats_class`: Custom grid classes
- `dashboard_actions`: Right-aligned action buttons
- `dashboard_filters`: Filter controls
- `dashboard_tabs`: Tab navigation
- `dashboard_content_top`: Content before main body
- `dashboard_content`: Main content area

---

## Examples

### Simple Admin Page
**File**: `app/Views/customer_management/index.php`

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('header_title') ?>
    Customer Management
<?= $this->endSection() ?>

<?= $this->section('header_subtitle') ?>
    View and manage all customer profiles
<?= $this->endSection() ?>

<?= $this->section('header_actions') ?>
    <a href="<?= base_url('customer-management/create') ?>" class="xs-btn xs-btn-primary">
        <span class="material-symbols-outlined">person_add</span>
        New Customer
    </a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <!-- Customer list table and content here -->
    <!-- NO page header in content -->
<?= $this->endSection() ?>
```

### Dashboard View
**File**: `app/Views/appointments/index.php`

```php
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('header_title') ?>
    <?= esc($title) // Dynamic based on user role ?>
<?= $this->endSection() ?>

<?= $this->section('header_subtitle') ?>
    <?= $user_role === 'customer' 
        ? 'View and manage your appointments' 
        : 'Manage appointments for your business' ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_stats') ?>
    <!-- Stat cards -->
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- Main appointment list -->
<?= $this->endSection() ?>
```

---

## CSS Classes

All styling for the global header is defined in:
**File**: `resources/scss/layout/_unified-content-system.scss`

### Main Classes
- `.xs-header`: Header container
- `.xs-header-content`: Content wrapper inside header
- `.xs-header-left`: Left section (title + breadcrumbs)
- `.xs-header-right`: Right section (actions)
- `.xs-header-title-section`: Title and subtitle wrapper
- `.xs-header-title`: Page title (H1)
- `.xs-header-meta`: Subtitle container
- `.xs-header-subtitle`: Subtitle text
- `.xs-header-actions`: Action buttons container
- `.xs-breadcrumbs`: Breadcrumb navigation

---

## Migration Guide

### Updating Existing Views

If you're updating a view that previously had inline page headers:

**Before** (OLD - DO NOT USE):
```php
<?= $this->section('content') ?>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Page Title</h1>
        <p class="text-gray-600">Subtitle here</p>
    </div>
    <!-- Content -->
<?= $this->endSection() ?>
```

**After** (NEW):
```php
<?= $this->section('header_title') ?>Page Title<?= $this->endSection() ?>
<?= $this->section('header_subtitle') ?>Subtitle here<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <!-- Content starts here - no page header -->
<?= $this->endSection() ?>
```

### Removing Old Components

Delete these files (replaced by global header):
- ~~`app/Views/components/page-header.php`~~ (kept for reference, but deprecated)
- Any custom per-page header components

### In Controllers

Use the header helper:

```php
<?php namespace App\Controllers;

class CustomerManagement extends BaseController
{
    public function index()
    {
        helper('header');
        
        $data = [
            ...$this->getAllCustomers(),
            ...set_page_header(
                'Customer Management',
                'View and manage all customer profiles and contact information',
                [
                    create_action_button('New Customer', '/customer-management/create'),
                ],
                [
                    create_breadcrumb_item('Dashboard', base_url('/dashboard')),
                    create_breadcrumb_item('Customers'),
                ]
            ),
        ];
        
        return view('customer_management/index', $data);
    }
}
```

---

## Styling Guidelines

### Using Material Design Icons in Headers

All icon spacing is handled by the component. Just use standard Material icon syntax:

```php
<a href="..." class="xs-btn xs-btn-primary">
    <span class="material-symbols-outlined">add</span>
    New Item
</a>
```

### Button Styles in Header

Use standard button classes:
- `.xs-btn .xs-btn-primary`: Primary action (blue)
- `.xs-btn .xs-btn-secondary`: Secondary action (outlined)
- `.xs-btn .xs-btn-ghost`: Ghost style (minimal)

### Responsive Behavior

The global header automatically adapts:
- **Mobile**: Single column, stacked layout
- **Tablet+**: Row layout with left title and right actions
- **Desktop**: Full width with sidebar awareness

---

## Troubleshooting

### Title Not Appearing
1. Verify `header_title` section is defined in view
2. Check that `layouts/app` is extended
3. Ensure header_title is NOT empty string

### Actions Not Showing
1. Verify `header_actions` section is defined
2. Check for typos in button HTML
3. Ensure actions are inside `header_actions` section

### Breadcrumbs Not Displaying
1. Breadcrumbs require explicit passing via layout
2. Use `set_page_header()` with breadcrumbs array
3. Currently set programmatically only - not auto-generated

### Subtitle Confusing
- Subtitle is optional - if not needed, don't define `header_subtitle` section
- User role displays as subtitle if subtitle is not provided

---

## Best Practices

1. **Always use sections**: Never hardcode page titles in content
2. **Use helpers in controllers**: Use `set_page_header()` for consistency
3. **Keep titles short**: Titles should fit in one line on mobile
4. **Descriptive subtitles**: Help users understand the page purpose
5. **Minimal actions**: 2-3 action buttons max in header
6. **Breadcrumbs optional**: Only add if navigation context is complex
7. **Consistent styling**: Always use xs-btn classes for buttons

---

## Related Documentation

- [Unified Sidebar System](../features/unified-sidebar.md)
- [Layout Architecture](../architecture/layouts.md)
- [TailwindCSS + Material Design Integration](../design/design-system.md)

# Migration Example: Customer Management Index

This document shows how to migrate an existing view from custom HTML/CSS to the Unified Layout System.

## BEFORE (Original Code)

```php
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'customer-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>Customer Management<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Manage customers and their contact details<?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
            <?= esc(session()->getFlashdata('success')) ?>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- CUSTOM CARD HTML -->
    <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- CUSTOM HEADER HTML -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <!-- CUSTOM TYPOGRAPHY -->
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Customers</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">View and manage customers</p>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <!-- CUSTOM SEARCH -->
                <input type="search" id="customerSearch" 
                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg..." />
                <!-- CUSTOM BUTTON -->
                <a href="<?= base_url('customer-management/create') ?>" 
                   class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg...">
                    <span class="material-symbols-outlined">person_add</span>
                    <span class="hidden sm:inline">New</span>
                </a>
            </div>
        </div>

        <!-- Table content -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <!-- ... -->
            </table>
        </div>
    </div>
<?= $this->endSection() ?>
```

## AFTER (Unified System)

```php
<?= $this->extend('layouts/app') ?>

<?= $this->section('header_title') ?>Customers<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php 
// Page Header Component
echo view('components/page-header', [
    'title' => 'Customer Management',
    'subtitle' => 'View and manage all customers in your system',
    'actions' => [
        '<a href="' . base_url('customer-management/create') . '" class="xs-btn xs-btn-primary">
            <span class="material-symbols-outlined">person_add</span>
            New Customer
        </a>'
    ]
]);
?>

<?php
// Search and filter card
$searchContent = '
    <div class="flex flex-col md:flex-row gap-3">
        <div class="flex-1 relative">
            <input 
                type="search" 
                id="customerSearch" 
                name="q" 
                value="' . esc($q ?? '') . '" 
                placeholder="Search by name or email..." 
                autocomplete="off"
                class="xs-form-input w-full"
            />
            <div id="searchSpinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        <button type="button" class="xs-btn xs-btn-secondary">
            <span class="material-symbols-outlined">filter_list</span>
            Filters
        </button>
    </div>
';

echo view('components/card', [
    'bodyClass' => 'xs-card-body-compact',
    'content' => $searchContent
]);
?>

<?php
// Main data table card
ob_start();
?>
<div class="overflow-x-auto -mx-6 md:-mx-8">
    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600">
            <tr>
                <th class="px-6 py-4 font-semibold">Customer</th>
                <th class="px-6 py-4 font-semibold">Email</th>
                <th class="px-6 py-4 font-semibold">Phone</th>
                <th class="px-6 py-4 font-semibold">Created</th>
                <th class="px-6 py-4 font-semibold">Actions</th>
            </tr>
        </thead>
        <tbody id="customersTableBody">
        <?php if (!empty($customers)): foreach ($customers as $c): ?>
            <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        <?= esc($c['first_name'] . ' ' . $c['last_name']) ?>
                    </div>
                </td>
                <td class="px-6 py-4"><?= esc($c['email']) ?></td>
                <td class="px-6 py-4"><?= esc($c['phone'] ?? 'N/A') ?></td>
                <td class="px-6 py-4">
                    <span class="xs-text-small"><?= date('M j, Y', strtotime($c['created_at'])) ?></span>
                </td>
                <td class="px-6 py-4">
                    <div class="xs-actions-container">
                        <a href="<?= base_url('customer-management/edit/' . $c['id']) ?>" 
                           class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" 
                           title="Edit">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                        <button type="button" 
                                onclick="deleteCustomer(<?= $c['id'] ?>)" 
                                class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-red-600 dark:text-red-400" 
                                title="Delete">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center gap-2">
                        <span class="material-symbols-outlined text-4xl">person_off</span>
                        <p>No customers found</p>
                    </div>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$tableContent = ob_get_clean();

$paginationFooter = '
    <div class="xs-actions-container xs-actions-between">
        <span class="xs-text-muted">
            Showing ' . (($currentPage - 1) * $perPage + 1) . '-' . 
            min($currentPage * $perPage, $total) . ' of ' . $total . '
        </span>
        <div class="flex gap-2">
            ' . ($currentPage > 1 
                ? '<a href="?page=' . ($currentPage - 1) . '" class="xs-btn xs-btn-sm xs-btn-secondary">Previous</a>' 
                : '<button class="xs-btn xs-btn-sm xs-btn-secondary" disabled>Previous</button>') . '
            ' . ($currentPage < ceil($total / $perPage) 
                ? '<a href="?page=' . ($currentPage + 1) . '" class="xs-btn xs-btn-sm xs-btn-secondary">Next</a>' 
                : '<button class="xs-btn xs-btn-sm xs-btn-secondary" disabled>Next</button>') . '
        </div>
    </div>
';

echo view('components/card', [
    'title' => 'All Customers',
    'subtitle' => number_format($total) . ' total customers',
    'content' => $tableContent,
    'footer' => $paginationFooter,
    'actions' => [
        '<button class="xs-btn xs-btn-ghost xs-btn-icon">
            <span class="material-symbols-outlined">refresh</span>
        </button>',
        '<button class="xs-btn xs-btn-ghost xs-btn-icon">
            <span class="material-symbols-outlined">more_vert</span>
        </button>'
    ]
]);
?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Customer search with debounce
let searchTimeout;
document.getElementById('customerSearch')?.addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const spinner = document.getElementById('searchSpinner');
    spinner?.classList.remove('hidden');
    
    searchTimeout = setTimeout(() => {
        window.location.href = '<?= base_url('customer-management') ?>?q=' + encodeURIComponent(e.target.value);
    }, 500);
});

// Delete customer
function deleteCustomer(id) {
    if (confirm('Are you sure you want to delete this customer?')) {
        fetch(`<?= base_url('customer-management/delete/') ?>${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to delete customer');
            }
        });
    }
}
</script>
<?= $this->endSection() ?>
```

## Key Changes

### 1. Layout Selection

**BEFORE**: Used `layouts/dashboard` (intended for dashboard pages)

**AFTER**: Uses `layouts/app` with default `standard` variant

**Why**: Customer management is a standard CRUD interface, not a dashboard with stats. The standard layout provides better max-width (1200px) for readability.

### 2. Page Header

**BEFORE**: Custom HTML with inconsistent styling
```php
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg md:text-xl font-semibold">Customers</h2>
        <p class="text-sm text-gray-600">View and manage customers</p>
    </div>
    <div><!-- actions --></div>
</div>
```

**AFTER**: Unified page-header component
```php
<?= view('components/page-header', [
    'title' => 'Customer Management',
    'subtitle' => 'View and manage all customers in your system',
    'actions' => [...]
]) ?>
```

**Benefits**:
- Consistent responsive behavior
- Standardized typography (xs-page-title)
- Proper spacing automatically applied
- Easier to maintain

### 3. Card Component

**BEFORE**: Custom div with manual padding, border-radius, shadow classes
```php
<div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <!-- content -->
</div>
```

**AFTER**: Unified card component with header, body, footer
```php
<?= view('components/card', [
    'title' => 'All Customers',
    'subtitle' => '1,234 total customers',
    'content' => $tableContent,
    'footer' => $paginationFooter,
    'actions' => [...]
]) ?>
```

**Benefits**:
- Consistent border-radius (0.75rem)
- Standard box-shadow
- Automatic dark mode support
- Header/footer structure built-in
- Action buttons positioned correctly

### 4. Form Inputs

**BEFORE**: Custom input classes
```php
<input class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700..." />
```

**AFTER**: Unified form input class
```php
<input class="xs-form-input w-full" />
```

**Benefits**:
- Consistent padding, border-radius, transitions
- Automatic focus states
- Dark mode support
- Less repetitive code

### 5. Buttons

**BEFORE**: Custom button with manual color classes
```php
<a class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg inline-flex items-center gap-1">
    <span class="material-symbols-outlined">person_add</span>
    <span class="hidden sm:inline">New</span>
</a>
```

**AFTER**: Unified button component
```php
<a class="xs-btn xs-btn-primary">
    <span class="material-symbols-outlined">person_add</span>
    New Customer
</a>
```

**Benefits**:
- Consistent sizing across all buttons
- Standard hover/focus states
- Icon + text gap handled automatically
- Color variants (primary, secondary, destructive, ghost)

### 6. Typography

**BEFORE**: Mixed font sizes with manual classes
```php
<h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Customers</h2>
<p class="text-sm text-gray-600 dark:text-gray-400">View and manage customers</p>
```

**AFTER**: Standardized typography classes (handled by component)
- Card title automatically uses `xs-card-title` (1.125rem, semibold)
- Card subtitle uses `xs-card-subtitle` (0.875rem, muted)

**Benefits**:
- Enforced type hierarchy
- No manual font size decisions
- Consistent line heights
- Automatic dark mode colors

### 7. Spacing

**BEFORE**: Inline spacing utilities scattered throughout
```php
<div class="mb-6">...</div>
<div class="gap-4">...</div>
<div class="flex gap-2">...</div>
```

**AFTER**: Spacing controlled by layout and components
- Layout handles vertical rhythm automatically (xs-content-standard)
- Components handle internal spacing (card header/body/footer)
- Only semantic gaps where necessary (flex containers)

**Benefits**:
- Consistent vertical rhythm
- Less decision fatigue
- Easier to adjust globally

### 8. Pagination Footer

**BEFORE**: Inline footer HTML
```php
<div class="flex justify-between items-center">
    <span>Showing 1-25 of 100</span>
    <div>
        <a href="?page=1">Previous</a>
        <a href="?page=2">Next</a>
    </div>
</div>
```

**AFTER**: Card footer prop with unified button classes
```php
$paginationFooter = '
    <div class="xs-actions-container xs-actions-between">
        <span class="xs-text-muted">Showing 1-25 of 100</span>
        <div class="flex gap-2">
            <a class="xs-btn xs-btn-sm xs-btn-secondary">Previous</a>
            <a class="xs-btn xs-btn-sm xs-btn-secondary">Next</a>
        </div>
    </div>
';
```

**Benefits**:
- Consistent footer appearance across all cards
- Standard action button sizing
- Proper responsive behavior

## Migration Effort

| Aspect | Complexity | Time Estimate |
|--------|-----------|---------------|
| Replace page header | Easy | 5 min |
| Convert card HTML to component | Medium | 10-15 min |
| Update button classes | Easy | 5 min |
| Update form inputs | Easy | 3 min |
| Update typography classes | Easy | 3 min |
| Remove inline spacing | Easy | 5 min |
| Test responsive/dark mode | Medium | 10 min |
| **Total per view** | - | **~45 min** |

## Testing Checklist

After migration, verify:

- [ ] Page renders correctly on desktop (1920px, 1440px, 1280px)
- [ ] Page renders correctly on tablet (768px, 1024px)
- [ ] Page renders correctly on mobile (375px, 414px)
- [ ] Dark mode colors are correct
- [ ] All buttons are clickable and styled consistently
- [ ] Card shadows and borders look correct
- [ ] Typography hierarchy is clear
- [ ] Spacing feels consistent (not too tight or loose)
- [ ] Search input has proper focus states
- [ ] Pagination buttons work correctly
- [ ] Table is scrollable on mobile
- [ ] No console errors

## Common Pitfalls

1. **Forgetting to remove wrapper divs**: Views no longer need `<div class="main-content">` - the layout provides `xs-content-wrapper`

2. **Using wrong layout variant**: Customer CRUD uses `standard`, not `dashboard`. Only use dashboard for actual dashboards with stats.

3. **Mixing custom and unified classes**: Don't do `<button class="xs-btn px-6 py-3">`. Button sizing is handled by variants (sm, default, lg).

4. **Manual dark mode classes**: Components handle dark mode automatically. No need for `dark:bg-gray-800` etc.

5. **Inline spacing on cards**: Don't add `mt-6 mb-4` to cards. Layout variant handles spacing between sections automatically.

## Next Steps

After migrating this view, apply the same pattern to:

1. `customer_management/create.php` (form layout)
2. `customer_management/edit.php` (form layout)
3. `user_management/index.php`
4. `services/index.php`
5. All other CRUD views

Once all views are migrated, the old legacy classes in `_buttons.scss` and `_cards.scss` can be removed.

<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Services<?= $this->endSection() ?>
<?= $this->section('header_subtitle') ?>Browse and manage your service catalog<?= $this->endSection() ?>
<?= $this->section('header_primary_action') ?>hidden<?= $this->endSection() ?>

<?php
$activeTab = $activeTab ?? 'services';
helper('currency');

$serviceStatusOptions = [
    ['value' => '', 'label' => 'All statuses'],
    ['value' => 'active', 'label' => 'Active'],
    ['value' => 'inactive', 'label' => 'Inactive'],
];

$serviceCategoryOptions = [['value' => '', 'label' => 'All categories']];
foreach ($categories as $category) {
    $serviceCategoryOptions[] = [
        'value' => (string) ($category['id'] ?? ''),
        'label' => (string) ($category['name'] ?? ''),
    ];
}

$hasActiveFilters = !empty($filters['q']) || !empty($filters['category']) || !empty($filters['status']);
?>

<?= $this->section('content') ?>

<!-- Top action bar: stats (left) + context-aware CTA (right) -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
        <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base text-blue-500">design_services</span>
            <strong class="font-semibold text-gray-900 dark:text-white"><?= (int)($stats['total_services'] ?? 0) ?></strong> services
        </span>
        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
        <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base text-emerald-500">check_circle</span>
            <strong class="font-semibold text-gray-900 dark:text-white"><?= (int)($stats['active_services'] ?? 0) ?></strong> active
        </span>
        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
        <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base text-purple-500">category</span>
            <strong class="font-semibold text-gray-900 dark:text-white"><?= (int)($stats['categories'] ?? 0) ?></strong> categories
        </span>
        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
        <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base text-amber-500">payments</span>
            avg <strong class="font-semibold text-gray-900 dark:text-white"><?= format_currency($stats['avg_price'] ?? 0) ?></strong>
        </span>
    </div>
    <div class="flex-shrink-0">
        <?php if ($activeTab === 'categories'): ?>
            <?= view('components/button', [
                'tag'     => 'a',
                'href'    => site_url('services/categories/create'),
                'label'   => 'New Category',
                'icon'    => 'add',
                'variant' => 'filled',
            ]) ?>
        <?php else: ?>
            <?= view('components/button', [
                'tag'     => 'a',
                'href'    => site_url('services/create'),
                'label'   => 'Add Service',
                'icon'    => 'add',
                'variant' => 'filled',
            ]) ?>
        <?php endif; ?>
    </div>
</div>

<div class="xs-card">
    <!-- Card header: tab switcher + filter toggle (services tab only) -->
    <div class="xs-card-header">
        <div class="xs-card-header-content">
            <div class="flex items-center gap-1">
                <a href="<?= site_url('services?tab=services') ?>"
                   class="xs-btn xs-btn-sm <?= $activeTab === 'services' ? 'xs-btn-primary' : 'xs-btn-ghost' ?>">
                    <span class="material-symbols-outlined text-base">design_services</span>
                    Services
                </a>
                <a href="<?= site_url('services?tab=categories') ?>"
                   class="xs-btn xs-btn-sm <?= $activeTab === 'categories' ? 'xs-btn-primary' : 'xs-btn-ghost' ?>">
                    <span class="material-symbols-outlined text-base">category</span>
                    Categories
                </a>
            </div>
        </div>
        <?php if ($activeTab === 'services'): ?>
        <div class="xs-card-actions">
            <button type="button"
                    id="services-filter-toggle"
                    class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon <?= $hasActiveFilters ? 'text-blue-600 dark:text-blue-400' : '' ?>"
                    title="Filter services"
                    aria-controls="services-filter-panel"
                    aria-expanded="<?= $hasActiveFilters ? 'true' : 'false' ?>"
                    onclick="var p=document.getElementById('services-filter-panel');p.classList.toggle('hidden');this.setAttribute('aria-expanded',String(!p.classList.contains('hidden')));">
                <span class="material-symbols-outlined text-base">tune</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($activeTab === 'services'): ?>
    <!-- Collapsible filter panel -->
    <div id="services-filter-panel"
         class="<?= $hasActiveFilters ? '' : 'hidden' ?> border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 px-6 py-4">
        <form action="<?= current_url() ?>" method="get"
              class="flex flex-col gap-3 md:flex-row md:items-end md:gap-4">
            <input type="hidden" name="tab" value="services" />
            <div class="flex-1">
                <label for="filterQuery" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Search</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <span class="material-symbols-outlined text-base">search</span>
                    </span>
                    <input id="filterQuery" name="q" value="<?= esc($filters['q'] ?? '') ?>"
                           placeholder="Name or description…"
                           class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                </div>
            </div>
            <div class="flex-1">
                <label for="filterCategory" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Category</label>
                <?= view('components/select', [
                    'id'      => 'filterCategory',
                    'name'    => 'category',
                    'options' => $serviceCategoryOptions,
                    'value'   => (string) ($filters['category'] ?? ''),
                ]) ?>
            </div>
            <div class="flex-1">
                <label for="filterStatus" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Status</label>
                <?= view('components/select', [
                    'id'      => 'filterStatus',
                    'name'    => 'status',
                    'options' => $serviceStatusOptions,
                    'value'   => (string) ($filters['status'] ?? ''),
                ]) ?>
            </div>
            <div class="flex gap-2">
                <?= view('components/button', ['type' => 'submit', 'label' => 'Apply', 'variant' => 'filled', 'size' => 'sm']) ?>
                <?php if ($hasActiveFilters): ?>
                    <?= view('components/button', ['tag' => 'a', 'href' => site_url('services?tab=services'), 'label' => 'Clear', 'variant' => 'outlined', 'size' => 'sm']) ?>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="xs-card-body p-0">
        <?php if ($activeTab === 'services'): ?>
        <!-- Services table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-6 py-3">Service</th>
                        <th class="px-6 py-3">Details</th>
                        <th class="px-6 py-3">Bookings</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $service): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition-colors">
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-gray-900 dark:text-white"><?= esc($service['name']) ?></div>
                                    <?php if (!empty($service['description'])): ?>
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-1"><?= esc($service['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            <?= esc($service['category']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap align-top">
                                    <div class="text-gray-700 dark:text-gray-300"><?= (int)$service['duration'] ?> min</div>
                                    <div class="font-semibold text-gray-900 dark:text-white"><?= format_currency($service['price']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300 align-top">
                                    <?= (int)$service['bookings_count'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap align-top">
                                    <?= view('components/status_badge', [
                                        'status' => (string) ($service['status'] ?? 'inactive'),
                                        'label'  => ucfirst((string) ($service['status'] ?? 'inactive')),
                                    ]) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap align-top">
                                    <div class="xs-actions-container justify-end">
                                        <a href="<?= site_url('services/edit/' . (int)$service['id']) ?>"
                                           class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon"
                                           title="Edit <?= esc($service['name']) ?>">
                                            <span class="material-symbols-outlined">edit</span>
                                        </a>
                                        <form action="<?= site_url('services/delete/' . (int)$service['id']) ?>" method="post"
                                              class="inline-flex" data-no-spa="true"
                                              data-confirm-message="Delete &quot;<?= esc($service['name']) ?>&quot;? This cannot be undone.">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                    title="Delete <?= esc($service['name']) ?>">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
                                    <span class="material-symbols-outlined text-4xl">design_services</span>
                                    <p class="text-sm"><?= $hasActiveFilters ? 'No services match your filters.' : 'No services yet.' ?></p>
                                    <?php if (!$hasActiveFilters): ?>
                                        <a href="<?= site_url('services/create') ?>" class="xs-btn xs-btn-sm xs-btn-primary mt-1">Add your first service</a>
                                    <?php else: ?>
                                        <a href="<?= site_url('services?tab=services') ?>" class="xs-btn xs-btn-sm xs-btn-ghost mt-1">Clear filters</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- Categories table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3">Services</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 inline-flex h-4 w-4 flex-shrink-0 rounded-full border border-gray-200 category-color-dot"
                                              data-color="<?= esc($category['color'] ?? '#3B82F6') ?>"></span>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white"><?= esc($category['name']) ?></div>
                                            <?php if (!empty($category['description'])): ?>
                                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-1"><?= esc($category['description']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                    <?= (int)($category['services_count'] ?? 0) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?= view('components/status_badge', [
                                        'status' => !empty($category['active']) ? 'active' : 'inactive',
                                        'label'  => !empty($category['active']) ? 'Active' : 'Inactive',
                                    ]) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="xs-actions-container justify-end">
                                        <a href="<?= site_url('services/categories/edit/' . (int)$category['id']) ?>"
                                           class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon"
                                           title="Edit <?= esc($category['name']) ?>">
                                            <span class="material-symbols-outlined">edit</span>
                                        </a>
                                        <?php if (!empty($category['active'])): ?>
                                            <form action="<?= site_url('services/categories/' . (int)$category['id'] . '/deactivate') ?>"
                                                  method="post" class="inline-flex" data-no-spa="true"
                                                  data-confirm-message="Deactivate &quot;<?= esc($category['name']) ?>&quot;? Services will remain but won&apos;t be bookable.">
                                                <?= csrf_field() ?>
                                                <button type="submit"
                                                        class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                                                        title="Deactivate <?= esc($category['name']) ?>">
                                                    <span class="material-symbols-outlined">pause_circle</span>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form action="<?= site_url('services/categories/' . (int)$category['id'] . '/activate') ?>"
                                                  method="post" class="inline-flex" data-no-spa="true"
                                                  data-confirm-message="Activate &quot;<?= esc($category['name']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <button type="submit"
                                                        class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                                                        title="Activate <?= esc($category['name']) ?>">
                                                    <span class="material-symbols-outlined">play_circle</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="<?= site_url('services/categories/' . (int)$category['id'] . '/delete') ?>"
                                              method="post" class="inline-flex" data-no-spa="true"
                                              data-confirm-message="Delete &quot;<?= esc($category['name']) ?>&quot;? Linked services will become uncategorized.">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                    title="Delete <?= esc($category['name']) ?>">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
                                    <span class="material-symbols-outlined text-4xl">category</span>
                                    <p class="text-sm">No categories yet.</p>
                                    <a href="<?= site_url('services/categories/create') ?>" class="xs-btn xs-btn-sm xs-btn-primary mt-1">Create first category</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>


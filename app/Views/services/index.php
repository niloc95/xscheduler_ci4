<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Services<?= $this->endSection() ?>
<?= $this->section('header_subtitle') ?>Browse and manage available services<?= $this->endSection() ?>

<?php $activeTab = $activeTab ?? 'services'; ?>

<?= $this->section('dashboard_stats_class') ?>grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6<?= $this->endSection() ?>

<?= $this->section('dashboard_stats') ?>
    <div class="card card-spacious p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-2xl">design_services</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Services</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['total_services'] ?></p>
            </div>
        </div>
    </div>

    <div class="card card-spacious p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-2xl">check_circle</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['active_services'] ?></p>
            </div>
        </div>
    </div>

    <div class="card card-spacious p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-purple-600 dark:text-purple-400 text-2xl">category</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Categories</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['categories'] ?></p>
            </div>
        </div>
    </div>

    <div class="card card-spacious p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-2xl">calendar_month</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Bookings</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['total_bookings'] ?></p>
            </div>
        </div>
    </div>

    <div class="card card-spacious p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-2xl">attach_money</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg. Price</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php helper('currency'); echo format_currency($stats['avg_price']); ?></p>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_actions') ?>
    <a href="<?= base_url('/services?tab=categories') ?>" class="btn btn-secondary">
        <span class="material-symbols-outlined text-base">category</span>
        Manage Categories
    </a>
    <a href="<?= base_url('/services/create') ?>" class="btn btn-primary">
        <span class="material-symbols-outlined text-base">add</span>
        Add Service
    </a>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
    <?php if ($message = session()->getFlashdata('message')): ?>
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-600/40 dark:bg-emerald-900/30 dark:text-emerald-200">
            <?= esc($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error = session()->getFlashdata('error')): ?>
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-600/50 dark:bg-red-900/30 dark:text-red-200">
            <?= esc($error) ?>
        </div>
    <?php endif; ?>
    <?php $validationErrors = session()->getFlashdata('errors') ?? []; ?>
    <?php if (!empty($validationErrors)): ?>
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-600/50 dark:bg-red-900/30 dark:text-red-200">
            <ul class="list-disc space-y-1 pl-5">
                <?php foreach ((array)$validationErrors as $field => $errorText): ?>
                    <li><?= esc(is_array($errorText) ? implode(', ', $errorText) : $errorText) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_filters') ?>
    <?php if ($activeTab === 'services'): ?>
        <form action="<?= current_url() ?>" method="get" class="flex flex-col gap-3 rounded-lg border border-dashed border-gray-300 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-900/40 md:flex-row md:items-center">
            <input type="hidden" name="tab" value="services" />
            <div class="flex-1">
                <label for="filterQuery" class="sr-only">Search services</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <span class="material-symbols-outlined text-base">search</span>
                    </span>
                    <input id="filterQuery" name="q" value="<?= esc($filters['q'] ?? '') ?>" placeholder="Search services" class="block w-full rounded-md border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                </div>
            </div>
            <div class="flex flex-1 flex-col gap-3 md:flex-row">
                <div class="flex-1">
                    <label for="filterCategory" class="sr-only">Filter by category</label>
                    <select id="filterCategory" name="category" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= (($filters['category'] ?? '') == $category['id']) ? 'selected' : '' ?>><?= esc($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <label for="filterStatus" class="sr-only">Filter by status</label>
                    <select id="filterStatus" name="status" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">All statuses</option>
                        <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end md:w-auto">
                <button type="submit" class="btn btn-primary">
                    Apply
                </button>
            </div>
        </form>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <div class="card card-spacious">
        <div class="card-header flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-2">
                <a href="<?= site_url('services?tab=services') ?>" class="btn <?= $activeTab === 'services' ? 'btn-primary shadow-sm' : 'btn-ghost text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' ?>">
                    <span class="material-symbols-outlined text-base">design_services</span>
                    Services
                </a>
                <a href="<?= site_url('services?tab=categories') ?>" class="btn <?= $activeTab === 'categories' ? 'btn-primary shadow-sm' : 'btn-ghost text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' ?>">
                    <span class="material-symbols-outlined text-base">category</span>
                    Categories
                </a>
            </div>
            <?php if ($activeTab === 'services'): ?>
                <p class="text-sm text-gray-500 dark:text-gray-300">Review offerings, check provider assignments, and keep pricing aligned.</p>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-300">Group services, manage availability, and keep your catalog tidy.</p>
            <?php endif; ?>
        </div>

    <div class="card-body space-y-6">
            <?php if ($activeTab === 'services'): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Service</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Provider</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Duration</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Price</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bookings</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (!empty($services)): ?>
                                <?php foreach ($services as $service): ?>
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                        <td class="px-6 py-4 align-top">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($service['name']) ?></p>
                                                <?php if (!empty($service['description'])): ?>
                                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                                                        <?= esc($service['description']) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                                <?= esc($service['category']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                            <?= esc($service['provider']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                            <?= $service['duration'] ?> min
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                            <?php helper('currency'); echo format_currency($service['price']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?= $service['bookings_count'] ?></td>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($service['status'] === 'active'): ?>
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="<?= base_url('/services/edit/' . $service['id']) ?>" class="btn btn-secondary btn-sm">
                                                    <span class="material-symbols-outlined text-sm">edit</span>
                                                    Edit
                                                </a>
                                                <form action="<?= site_url('services/delete/' . $service['id']) ?>" method="post" onsubmit="return confirm('Delete this service?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-ghost btn-sm text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                                                        <span class="material-symbols-outlined text-sm">delete</span>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-300">
                                        No services found. Create a service to get started.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <form action="<?= site_url('services/categories') ?>" method="post" class="flex flex-col gap-3 rounded-lg border border-dashed border-gray-300 bg-gray-50/80 p-4 dark:border-gray-700 dark:bg-gray-900/40 md:flex-row md:items-center md:gap-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="active" value="1" />
                        <div class="flex-1">
                            <label for="quickCategoryName" class="sr-only">Category name</label>
                            <input id="quickCategoryName" name="name" placeholder="Quick add category" value="<?= esc(old('name', '')) ?>" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required />
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="quickCategoryColor" class="text-sm font-medium text-gray-600 dark:text-gray-300">Color</label>
                            <input id="quickCategoryColor" name="color" type="color" value="<?= esc(old('color', '#3B82F6')) ?>" class="h-10 w-12 rounded-md border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800" />
                        </div>
                        <div class="flex justify-end md:justify-start">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined text-base">add</span>
                                Add Category
                            </button>
                        </div>
                    </form>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Services</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                            <td class="px-6 py-4">
                                                <div class="flex items-start gap-3">
                                                    <span class="mt-1 inline-flex h-4 w-4 rounded-full border border-gray-200" style="background-color: <?= esc($category['color'] ?? '#3B82F6') ?>"></span>
                                                    <div>
                                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100"><?= esc($category['name']) ?></p>
                                                        <?php if (!empty($category['description'])): ?>
                                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 line-clamp-2"><?= esc($category['description']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                <?= (int)($category['services_count'] ?? 0) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if (!empty($category['active'])): ?>
                                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">Active</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <a href="<?= site_url('services/categories/edit/' . (int)$category['id']) ?>" class="btn btn-secondary btn-sm">
                                                        <span class="material-symbols-outlined text-sm">edit</span>
                                                        Edit
                                                    </a>

                                                    <?php if (!empty($category['active'])): ?>
                                                        <form action="<?= site_url('services/categories/' . (int)$category['id'] . '/deactivate') ?>" method="post" class="inline-flex" onsubmit="return confirm('Deactivate this category? Services will remain but marked inactive.');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="btn btn-ghost btn-sm text-amber-600 hover:text-amber-700 dark:text-amber-300 dark:hover:text-amber-200">
                                                                <span class="material-symbols-outlined text-sm">pause</span>
                                                                Deactivate
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form action="<?= site_url('services/categories/' . (int)$category['id'] . '/activate') ?>" method="post" class="inline-flex" onsubmit="return confirm('Activate this category?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="btn btn-ghost btn-sm text-emerald-600 hover:text-emerald-700 dark:text-emerald-300 dark:hover:text-emerald-200">
                                                                <span class="material-symbols-outlined text-sm">play_arrow</span>
                                                                Activate
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <form action="<?= site_url('services/categories/' . (int)$category['id'] . '/delete') ?>" method="post" class="inline-flex" onsubmit="return confirm('Delete this category? Any linked services will become uncategorized.');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-ghost btn-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                                            <span class="material-symbols-outlined text-sm">delete</span>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-300">
                                            No categories yet. Use the quick add form or New Category button to create one.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?= $this->endSection() ?>

<?php
// Categories list view (manageable)
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Categories<?= $this->endSection() ?>
<?= $this->section('header_subtitle') ?>Manage service categories<?= $this->endSection() ?>
<?= $this->section('header_primary_action') ?>hidden<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Back link + CTA -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <a href="<?= site_url('services?tab=categories') ?>"
       class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
        <span class="material-symbols-outlined text-base">arrow_back</span>
        Back to Services
    </a>
    <?= view('components/button', [
        'tag'     => 'a',
        'href'    => site_url('services/categories/create'),
        'label'   => 'New Category',
        'icon'    => 'add',
        'variant' => 'filled',
    ]) ?>
</div>

<div class="xs-card">
    <div class="xs-card-header">
        <div class="xs-card-header-content">
            <h3 class="xs-card-title">Categories</h3>
            <p class="xs-card-subtitle">Group services, manage availability, and keep your catalog tidy.</p>
        </div>
    </div>
    <div class="xs-card-body p-0">
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
                        <?php foreach ($categories as $c): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 inline-flex h-4 w-4 flex-shrink-0 rounded-full border border-gray-200 category-color-dot"
                                              data-color="<?= esc($c['color'] ?? '#3B82F6') ?>"></span>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white"><?= esc($c['name']) ?></div>
                                            <?php if (!empty($c['description'])): ?>
                                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-1"><?= esc($c['description']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                    <?= (int)($c['services_count'] ?? 0) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?= view('components/status_badge', [
                                        'status' => !empty($c['active']) ? 'active' : 'inactive',
                                        'label'  => !empty($c['active']) ? 'Active' : 'Inactive',
                                    ]) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="xs-actions-container justify-end">
                                        <a href="<?= site_url('services/categories/edit/' . (int)$c['id']) ?>"
                                           class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon"
                                           title="Edit <?= esc($c['name']) ?>">
                                            <span class="material-symbols-outlined">edit</span>
                                        </a>
                                        <?php if (!empty($c['active'])): ?>
                                            <form action="<?= site_url('services/categories/' . (int)$c['id'] . '/deactivate') ?>"
                                                  method="post" class="inline-flex" data-no-spa="true"
                                                  data-confirm-message="Deactivate &quot;<?= esc($c['name']) ?>&quot;? Services will remain but won&apos;t be bookable.">
                                                <?= csrf_field() ?>
                                                <button type="submit"
                                                        class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                                                        title="Deactivate <?= esc($c['name']) ?>">
                                                    <span class="material-symbols-outlined">pause_circle</span>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form action="<?= site_url('services/categories/' . (int)$c['id'] . '/activate') ?>"
                                                  method="post" class="inline-flex" data-no-spa="true"
                                                  data-confirm-message="Activate &quot;<?= esc($c['name']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <button type="submit"
                                                        class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                                                        title="Activate <?= esc($c['name']) ?>">
                                                    <span class="material-symbols-outlined">play_circle</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="<?= site_url('services/categories/' . (int)$c['id'] . '/delete') ?>"
                                              method="post" class="inline-flex" data-no-spa="true"
                                              data-confirm-message="Delete &quot;<?= esc($c['name']) ?>&quot;? Linked services will become uncategorized.">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                    title="Delete <?= esc($c['name']) ?>">
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
    </div>
</div>

<?= $this->endSection() ?>

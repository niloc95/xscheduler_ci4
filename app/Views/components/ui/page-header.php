<?php
/**
 * Page Header Component
 * 
 * Standardized page header with title, subtitle, breadcrumbs, and action buttons.
 * Use this at the top of every page for consistent structure.
 * 
 * Props:
 * @param string $title Required. Page title
 * @param string $subtitle Optional. Page subtitle/description
 * @param array $breadcrumbs Optional. Array of ['label' => 'Home', 'url' => '/']
 * @param string $actions Optional. HTML for action buttons (right side)
 * 
 * Usage:
 * <?= $this->include('components/ui/page-header', [
 *     'title' => 'Customers',
 *     'subtitle' => 'Manage your customer database',
 *     'breadcrumbs' => [
 *         ['label' => 'Dashboard', 'url' => '/dashboard'],
 *         ['label' => 'Customers']
 *     ],
 *     'actions' => '<a href="/customers/create" class="btn btn-primary">Add Customer</a>'
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$title = $title ?? 'Page Title';
$subtitle = $subtitle ?? null;
$breadcrumbs = $breadcrumbs ?? [];
$actions = $actions ?? null;
?>

<div class="xs-page-header mb-6">
    <!-- Breadcrumbs -->
    <?php if (!empty($breadcrumbs)): ?>
    <nav class="mb-3" aria-label="Breadcrumb">
        <ol class="flex items-center gap-2 text-sm">
            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                <?php $isLast = $i === count($breadcrumbs) - 1; ?>
                <li class="flex items-center gap-2">
                    <?php if (!$isLast && isset($crumb['url'])): ?>
                        <a href="<?= esc($crumb['url']) ?>" class="text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                            <?= esc($crumb['label']) ?>
                        </a>
                        <span class="material-symbols-outlined text-gray-400 text-sm">chevron_right</span>
                    <?php else: ?>
                        <span class="text-gray-900 dark:text-white font-medium"><?= esc($crumb['label']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php endif; ?>
    
    <!-- Title Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white truncate">
                <?= esc($title) ?>
            </h1>
            <?php if ($subtitle): ?>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                <?= esc($subtitle) ?>
            </p>
            <?php endif; ?>
        </div>
        
        <?php if ($actions): ?>
        <div class="flex flex-wrap gap-2 flex-shrink-0">
            <?= $actions ?>
        </div>
        <?php endif; ?>
    </div>
</div>

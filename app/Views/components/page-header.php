<?php
/**
 * Unified Page Header Component
 * 
 * Standardized page header with title, subtitle, and actions.
 * DO NOT create custom page headers - use this component.
 * 
 * @param string $title       Page title (H1)
 * @param string $subtitle    Page subtitle/description (optional)
 * @param array  $actions     Array of action buttons/links (optional)
 * @param array  $breadcrumbs Array of breadcrumb items (optional)
 */

$title = $title ?? 'Untitled Page';
$subtitle = $subtitle ?? null;
$actions = $actions ?? [];
$breadcrumbs = $breadcrumbs ?? [];
?>

<div class="xs-page-header">
    <div class="xs-page-header-content">
        <?php if (!empty($breadcrumbs)): ?>
            <nav class="xs-breadcrumbs" aria-label="Breadcrumb">
                <ol class="flex items-center gap-2 text-sm mb-2">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <li class="flex items-center gap-2">
                            <?php if ($index > 0): ?>
                                <span class="text-gray-400">/</span>
                            <?php endif; ?>
                            <?php if (isset($crumb['url'])): ?>
                                <a href="<?= esc($crumb['url']) ?>" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                                    <?= esc($crumb['label']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-900 dark:text-gray-100 font-medium">
                                    <?= esc($crumb['label']) ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>
        
        <h1 class="xs-page-title"><?= esc($title) ?></h1>
        
        <?php if ($subtitle): ?>
            <p class="xs-text-muted mt-1"><?= esc($subtitle) ?></p>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($actions)): ?>
        <div class="xs-page-header-actions">
            <?= implode('', $actions) ?>
        </div>
    <?php endif; ?>
</div>

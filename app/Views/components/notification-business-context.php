<?php
$businessContext = is_array($businessContext ?? null) ? $businessContext : [];
$containerClass = trim((string) ($containerClass ?? ''));
$title = trim((string) ($businessContext['title'] ?? 'Business Context'));
$description = trim((string) ($businessContext['description'] ?? ''));
$options = is_array($businessContext['options'] ?? null) ? $businessContext['options'] : [];
$action = is_array($businessContext['action'] ?? null) ? $businessContext['action'] : null;
?>

<div<?= $containerClass !== '' ? ' class="' . esc($containerClass) . '"' : '' ?>>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100"><?= esc($title) ?></h4>

            <?php if ($description !== ''): ?>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400"><?= esc($description) ?></p>
            <?php endif; ?>

            <?php if ($options !== []): ?>
                <?php
                    // Single-business UX: if only one option exists, show a
                    // static active badge instead of clickable pill buttons to
                    // avoid confusing "selector" chrome with nothing to switch to.
                    $multipleOptions = count($options) > 1;
                ?>
                <?php if ($multipleOptions): ?>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($options as $option): ?>
                            <?php
                                $url = trim((string) ($option['url'] ?? ''));
                                $label = trim((string) ($option['label'] ?? 'Business'));
                                $isActive = !empty($option['is_active']);
                            ?>
                            <?php if ($url !== ''): ?>
                                <a href="<?= esc($url) ?>"
                                   class="inline-flex items-center rounded-full border px-3 py-1.5 text-sm font-medium transition-colors duration-200 <?= $isActive ? 'border-blue-600 bg-blue-600 text-white shadow-sm' : 'border-gray-300 bg-white text-gray-700 hover:border-blue-300 hover:text-blue-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-blue-500 dark:hover:text-blue-300' ?>">
                                    <?= esc($label) ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php
                        $singleLabel = trim((string) ($options[0]['label'] ?? 'Current Business'));
                    ?>
                    <div class="mt-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-green-300 bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300">
                            <span class="material-symbols-outlined text-sm leading-none">check_circle</span>
                            <?= esc($singleLabel) ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($action !== null && trim((string) ($action['href'] ?? '')) !== '' && trim((string) ($action['label'] ?? '')) !== ''): ?>
            <a href="<?= esc((string) $action['href']) ?>"
               class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-blue-700">
                <?php if (trim((string) ($action['icon'] ?? '')) !== ''): ?>
                    <span class="material-symbols-outlined mr-2 text-base"><?= esc((string) $action['icon']) ?></span>
                <?php endif; ?>
                <?= esc((string) $action['label']) ?>
            </a>
        <?php endif; ?>
    </div>
</div>
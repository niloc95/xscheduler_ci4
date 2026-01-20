<?php
/**
 * Data Table Component
 * 
 * Standardized responsive table for data display.
 * Includes sorting, pagination, and empty state handling.
 * 
 * Props:
 * @param array $columns Required. Array of column definitions
 *   [['key' => 'name', 'label' => 'Name', 'sortable' => true, 'class' => 'w-1/4'], ...]
 * @param array $rows Required. Array of data rows
 * @param string $emptyMessage Optional. Message when no data
 * @param string $emptyIcon Optional. Icon for empty state
 * @param string $sortBy Optional. Current sort column
 * @param string $sortDir Optional. Current sort direction (asc/desc)
 * @param string $class Optional. Additional table classes
 * @param bool $striped Optional. Striped rows
 * @param bool $hoverable Optional. Hover effect on rows
 * 
 * Usage:
 * <?= $this->include('components/ui/table', [
 *     'columns' => [
 *         ['key' => 'name', 'label' => 'Name', 'sortable' => true],
 *         ['key' => 'email', 'label' => 'Email'],
 *         ['key' => 'actions', 'label' => '', 'class' => 'w-20']
 *     ],
 *     'rows' => $customers,
 *     'emptyMessage' => 'No customers found',
 *     'emptyIcon' => 'people'
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$columns = $columns ?? [];
$rows = $rows ?? [];
$emptyMessage = $emptyMessage ?? 'No data available';
$emptyIcon = $emptyIcon ?? 'inbox';
$sortBy = $sortBy ?? null;
$sortDir = $sortDir ?? 'asc';
$class = $class ?? '';
$striped = $striped ?? false;
$hoverable = $hoverable ?? true;
?>

<div class="xs-table-wrapper overflow-x-auto">
    <table class="xs-table w-full text-sm text-left <?= esc($class) ?>">
        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <?php foreach ($columns as $col): ?>
                <th scope="col" class="px-6 py-4 font-semibold <?= esc($col['class'] ?? '') ?>">
                    <?php if (!empty($col['sortable'])): ?>
                    <button type="button" class="group inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white transition-colors" data-sort="<?= esc($col['key']) ?>">
                        <?= esc($col['label']) ?>
                        <span class="material-symbols-outlined text-sm opacity-50 group-hover:opacity-100">
                            <?php if ($sortBy === $col['key']): ?>
                                <?= $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' ?>
                            <?php else: ?>
                                unfold_more
                            <?php endif; ?>
                        </span>
                    </button>
                    <?php else: ?>
                    <?= esc($col['label']) ?>
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (empty($rows)): ?>
            <tr>
                <td colspan="<?= count($columns) ?>" class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                        <span class="material-symbols-outlined text-5xl mb-3 opacity-50"><?= esc($emptyIcon) ?></span>
                        <p class="text-lg font-medium"><?= esc($emptyMessage) ?></p>
                    </div>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                <tr class="<?= $striped && $i % 2 === 1 ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800' ?> <?= $hoverable ? 'hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors' : '' ?>">
                    <?php foreach ($columns as $col): ?>
                    <td class="px-6 py-4 <?= esc($col['cellClass'] ?? '') ?>">
                        <?php
                        $key = $col['key'];
                        if (isset($col['render']) && is_callable($col['render'])) {
                            echo $col['render']($row, $key);
                        } elseif (isset($row[$key])) {
                            echo esc($row[$key]);
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

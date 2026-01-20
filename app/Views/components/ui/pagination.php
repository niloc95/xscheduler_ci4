<?php
/**
 * Pagination Component
 * 
 * Standardized pagination controls for data tables and lists.
 * 
 * Props:
 * @param int $currentPage Required. Current page number (1-indexed)
 * @param int $totalPages Required. Total number of pages
 * @param int $totalItems Optional. Total number of items
 * @param int $perPage Optional. Items per page
 * @param string $baseUrl Required. Base URL for pagination links
 * @param array $queryParams Optional. Additional query parameters to preserve
 * @param int $showPages Optional. Number of page buttons to show (default: 5)
 * @param bool $showInfo Optional. Show items info text
 * @param bool $showPerPage Optional. Show per page selector
 * @param array $perPageOptions Optional. Per page options
 * 
 * Usage:
 * <?= $this->include('components/ui/pagination', [
 *     'currentPage' => $pager->getCurrentPage(),
 *     'totalPages' => $pager->getPageCount(),
 *     'totalItems' => $pager->getTotal(),
 *     'perPage' => $pager->getPerPage(),
 *     'baseUrl' => '/customers',
 *     'showInfo' => true
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$currentPage = max(1, (int)($currentPage ?? 1));
$totalPages = max(1, (int)($totalPages ?? 1));
$totalItems = (int)($totalItems ?? 0);
$perPage = (int)($perPage ?? 10);
$baseUrl = $baseUrl ?? '';
$queryParams = $queryParams ?? [];
$showPages = (int)($showPages ?? 5);
$showInfo = $showInfo ?? true;
$showPerPage = $showPerPage ?? true;
$perPageOptions = $perPageOptions ?? [10, 25, 50, 100];

// Calculate start and end items for info text
$startItem = min($totalItems, ($currentPage - 1) * $perPage + 1);
$endItem = min($totalItems, $currentPage * $perPage);

// Build URL helper
function buildPaginationUrl($baseUrl, $page, $perPage, $queryParams) {
    $params = array_merge($queryParams, ['page' => $page, 'per_page' => $perPage]);
    $query = http_build_query($params);
    return $baseUrl . ($query ? '?' . $query : '');
}

// Calculate visible page range
$halfShow = floor($showPages / 2);
$startPage = max(1, $currentPage - $halfShow);
$endPage = min($totalPages, $startPage + $showPages - 1);

if ($endPage - $startPage + 1 < $showPages) {
    $startPage = max(1, $endPage - $showPages + 1);
}
?>

<?php if ($totalPages > 1 || $showInfo || $showPerPage): ?>
<div class="xs-pagination flex flex-col sm:flex-row items-center justify-between gap-4 py-4">
    <!-- Info Text -->
    <?php if ($showInfo && $totalItems > 0): ?>
    <div class="text-sm text-gray-600 dark:text-gray-400">
        Showing <span class="font-medium text-gray-900 dark:text-white"><?= $startItem ?></span> 
        to <span class="font-medium text-gray-900 dark:text-white"><?= $endItem ?></span> 
        of <span class="font-medium text-gray-900 dark:text-white"><?= number_format($totalItems) ?></span> results
    </div>
    <?php else: ?>
    <div></div>
    <?php endif; ?>
    
    <div class="flex items-center gap-4">
        <!-- Per Page Selector -->
        <?php if ($showPerPage): ?>
        <div class="flex items-center gap-2">
            <label for="pagination-per-page" class="text-sm text-gray-600 dark:text-gray-400">Per page:</label>
            <select 
                id="pagination-per-page"
                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                onchange="window.location.href = this.value"
            >
                <?php foreach ($perPageOptions as $option): ?>
                <option 
                    value="<?= buildPaginationUrl($baseUrl, 1, $option, $queryParams) ?>"
                    <?= $option == $perPage ? 'selected' : '' ?>
                >
                    <?= $option ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Page Navigation -->
        <?php if ($totalPages > 1): ?>
        <nav class="flex items-center gap-1" aria-label="Pagination">
            <!-- Previous Button -->
            <?php if ($currentPage > 1): ?>
            <a 
                href="<?= buildPaginationUrl($baseUrl, $currentPage - 1, $perPage, $queryParams) ?>"
                class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                aria-label="Previous page"
            >
                <span class="material-symbols-outlined text-lg">chevron_left</span>
            </a>
            <?php else: ?>
            <span class="p-2 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                <span class="material-symbols-outlined text-lg">chevron_left</span>
            </span>
            <?php endif; ?>
            
            <!-- First Page -->
            <?php if ($startPage > 1): ?>
            <a 
                href="<?= buildPaginationUrl($baseUrl, 1, $perPage, $queryParams) ?>"
                class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
            >
                1
            </a>
            <?php if ($startPage > 2): ?>
            <span class="px-2 py-2 text-gray-400 dark:text-gray-500">...</span>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Page Numbers -->
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i == $currentPage): ?>
            <span class="px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg">
                <?= $i ?>
            </span>
            <?php else: ?>
            <a 
                href="<?= buildPaginationUrl($baseUrl, $i, $perPage, $queryParams) ?>"
                class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
            >
                <?= $i ?>
            </a>
            <?php endif; ?>
            <?php endfor; ?>
            
            <!-- Last Page -->
            <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
            <span class="px-2 py-2 text-gray-400 dark:text-gray-500">...</span>
            <?php endif; ?>
            <a 
                href="<?= buildPaginationUrl($baseUrl, $totalPages, $perPage, $queryParams) ?>"
                class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
            >
                <?= $totalPages ?>
            </a>
            <?php endif; ?>
            
            <!-- Next Button -->
            <?php if ($currentPage < $totalPages): ?>
            <a 
                href="<?= buildPaginationUrl($baseUrl, $currentPage + 1, $perPage, $queryParams) ?>"
                class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                aria-label="Next page"
            >
                <span class="material-symbols-outlined text-lg">chevron_right</span>
            </a>
            <?php else: ?>
            <span class="p-2 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                <span class="material-symbols-outlined text-lg">chevron_right</span>
            </span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

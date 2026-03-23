<?php
/**
 * Customer Management - Index View
 *
 * Admin-focused CRUD interface for managing customer records in the database.
 * This is NOT for appointment interactions (see user-management/customers.php for that).
 * 
 * Purpose: Create, Read, Update, Delete customer profiles and contact information
 * Access: Admin role only
 * Actions: View list, Create new, Edit existing, Delete customers
 * 
 * Related: app/Views/user-management/customers.php handles booking interactions
 * 
 * REFACTORED: Now uses Unified Layout System components
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'customer-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Customers<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Search Bar and Stats Section -->
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <!-- Stat Card: Total Customers -->
    <div class="flex flex-wrap items-center gap-4 w-full lg:w-auto">
        <?= ui_dashboard_stat_card('Total Customers', $totalCustomers ?? 0, ['valueId' => 'totalCustomersCount']); ?>
    </div>

    <!-- Search Bar and Action Button -->
    <div class="flex flex-col gap-3 items-stretch lg:flex-1 lg:items-end w-full lg:w-auto">
        <div class="flex flex-wrap items-center gap-2 justify-start lg:justify-end">
            <!-- Search Input -->
            <div class="relative w-full sm:w-64 lg:w-80">
                <input 
                    type="search" 
                    id="customerSearch" 
                    name="q" 
                    value="<?= esc($q ?? '') ?>" 
                    placeholder="Search by name or email..." 
                    autocomplete="off"
                    class="w-full h-10 pl-10 pr-4 bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                />
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                <div id="searchSpinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
            
            <!-- New Customer Button -->
            <a href="<?= base_url('customer-management/create') ?>" class="xs-btn xs-btn-primary whitespace-nowrap">
                <span class="material-symbols-outlined">person_add</span>
                New Customer
            </a>
        </div>
    </div>
</div>

<?php
// Main Data Table Card
ob_start();
?>
<div class="overflow-x-auto">
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
                <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                            <?= strtoupper(substr(($c['first_name'] ?? ($c['name'] ?? 'C')), 0, 1)) ?>
                        </div>
                        <div>
                            <div class="font-medium"><?= esc($c['name'] ?? trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></div>
                            <?php if (!empty($c['address'])): ?><div class="xs-text-small"><?= esc($c['address']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4"><?= esc($c['email'] ?? '—') ?></td>
                <td class="px-6 py-4"><?= esc($c['phone'] ?? '—') ?></td>
                <td class="px-6 py-4">
                    <span class="xs-text-small"><?= !empty($c['created_at']) ? date('M j, Y', strtotime($c['created_at'])) : '—' ?></span>
                </td>
                <td class="px-6 py-4">
                    <div class="xs-actions-container">
                        <a href="<?= base_url('customer-management/history/' . esc($c['hash'] ?? '')) ?>" 
                           class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" 
                           title="View History">
                            <span class="material-symbols-outlined">history</span>
                        </a>
                        <a href="<?= base_url('customer-management/edit/' . esc($c['hash'] ?? '')) ?>" 
                           class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" 
                           title="Edit Customer">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center">
                    <div class="flex flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
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

echo view('components/card', [
    'title' => null,
    'content' => $tableContent
]);
?>

<?= $this->endSection() ?>

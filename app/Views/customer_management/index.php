<?php
/**
 * Customer Management - Index View
 *
 * Admin-focused CRUD interface for managing customer records in the database.
 * This is NOT for appointment interactions (see user_management/customers.php for that).
 * 
 * Purpose: Create, Read, Update, Delete customer profiles and contact information
 * Access: Admin role only
 * Actions: View list, Create new, Edit existing, Delete customers
 * 
 * Related: app/Views/user_management/customers.php handles booking interactions
 */
?>
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

    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Customers</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">View and manage customers</p>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <form method="get" class="flex flex-1 md:flex-none gap-2">
                    <input type="search" name="q" value="<?= esc($q ?? '') ?>" placeholder="Search name or email..." class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-1"><span class="material-symbols-outlined text-sm">search</span><span class="hidden sm:inline">Search</span></button>
                </form>
                <a href="<?= base_url('customer-management/create') ?>" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg inline-flex items-center gap-1"><span class="material-symbols-outlined">person_add</span><span class="hidden sm:inline">New</span></a>
            </div>
        </div>

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
                <tbody>
                <?php if (!empty($customers)): foreach ($customers as $c): ?>
                    <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                                    <?= strtoupper(substr(($c['first_name'] ?? ($c['name'] ?? 'C')), 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?= esc($c['name'] ?? trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></div>
                                    <?php if (!empty($c['address'])): ?><div class="text-xs text-gray-500 dark:text-gray-400"><?= esc($c['address']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= esc($c['email'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= esc($c['phone'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= !empty($c['created_at']) ? date('M j, Y', strtotime($c['created_at'])) : '—' ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="<?= base_url('customer-management/edit/' . esc($c['hash'] ?? '')) ?>" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit Customer">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No customers found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?= $this->endSection() ?>

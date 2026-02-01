<?php
/**
 * User Management - Customer Interaction View
 *
 * Allows providers and staff to search and interact with customers for booking assistance.
 * This is NOT for managing customer records (see customer-management module for that).
 * 
 * Purpose: View customer appointments, reschedule, cancel, and assist with bookings
 * Access: Provider and Staff roles only (scoped to their assigned customers)
 * 
 * Related: app/Views/customer-management/ handles CRUD operations for customer records
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Customer Interaction<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('components/ui/flash-messages') ?>

    <div class="card card-spacious">
        <div class="card-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="card-title text-xl">Customers</h2>
                <p class="card-subtitle">Search and manage customers linked to your role</p>
            </div>
            <div class="flex gap-2 w-full sm:w-auto">
                <input type="search" id="customerSearch" placeholder="Search customers..." class="flex-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" />
                <button id="customerRefresh" class="btn btn-secondary">
                    <span class="material-symbols-outlined text-sm">refresh</span>
                    <span class="hidden sm:inline text-sm">Reload</span>
                </button>
            </div>
        </div>

        <div class="card-body space-y-4">
            <div id="customers-error" class="hidden rounded-md border border-red-300/60 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-600/40 dark:bg-red-900/20 dark:text-red-200"></div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" aria-live="polite">
                <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Customer</th>
                        <th class="px-6 py-4 font-semibold">Email</th>
                        <th class="px-6 py-4 font-semibold">Phone</th>
                        <th class="px-6 py-4 font-semibold">Created</th>
                        <th class="px-6 py-4 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody id="customers-table-body">
                    <?php if (!empty($customers)): foreach ($customers as $c): ?>
                        <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-300">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs mr-3">
                                        <?= strtoupper(substr($c['first_name'] ?? ($c['name'] ?? 'C'),0,1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-medium"><?= esc($c['name'] ?? trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? ''))) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= esc($c['email'] ?? '—') ?></td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= esc($c['phone'] ?? '—') ?></td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= esc(isset($c['created_at']) ? date('M j, Y', strtotime($c['created_at'])) : '—') ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <a href="#" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" data-action="view" data-id="<?= esc($c['id'] ?? '') ?>"><span class="material-symbols-outlined">visibility</span></a>
                                    <a href="#" class="p-1 text-gray-600 dark:text-gray-400 hover:text-amber-600 dark:hover:text-amber-400" data-action="reschedule" data-id="<?= esc($c['id'] ?? '') ?>"><span class="material-symbols-outlined">schedule</span></a>
                                    <a href="#" class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400" data-action="cancel" data-id="<?= esc($c['id'] ?? '') ?>"><span class="material-symbols-outlined">cancel</span></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No customers available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    function esc(s){return (s||'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));}
    async function reloadCustomers(){/* Placeholder for future dynamic fetch */}
    function isCustomerInteractionPage() {
        const spaContent = document.getElementById('spa-content');
        return spaContent?.getAttribute('data-page-title') === 'Customer Interaction';
    }
    document.getElementById('customerRefresh')?.addEventListener('click',()=>reloadCustomers());
    if (isCustomerInteractionPage()) reloadCustomers();
    document.addEventListener('spa:navigated',()=>{ if(isCustomerInteractionPage()) reloadCustomers(); });
})();
</script>
<?= $this->endSection() ?>

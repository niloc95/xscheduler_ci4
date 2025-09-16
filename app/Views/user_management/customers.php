<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Customer Interaction<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Customer Interaction" data-page-subtitle="Manage and assist customers with bookings">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-brand material-shadow">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Customers</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Search and manage customers linked to your role</p>
            </div>
            <div class="flex gap-2 w-full sm:w-auto">
                <input type="search" id="customerSearch" placeholder="Search customers..." class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
                <button id="customerRefresh" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-1"><span class="material-symbols-outlined text-sm">refresh</span><span class="hidden sm:inline text-sm">Reload</span></button>
            </div>
        </div>

        <div id="customers-error" class="hidden mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm"></div>

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
<script>
(function(){
    function esc(s){return (s||'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));}
    async function reloadCustomers(){/* Placeholder for future dynamic fetch */}
    document.getElementById('customerRefresh')?.addEventListener('click',()=>reloadCustomers());
    document.addEventListener('spa:navigated',()=>{ if(document.querySelector('[data-page-title="Customer Interaction"]')) reloadCustomers(); });
})();
</script>
<?= $this->endSection() ?>

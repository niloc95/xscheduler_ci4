<?php
/**
 * Customer Management - History View
 *
 * Displays complete appointment history for a specific customer including:
 * - Stats summary (total, upcoming, completed, cancelled)
 * - Upcoming appointments
 * - Full appointment history with filters
 * - Provider, service, status, and date range filters
 */
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'customer-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>Customer History<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?><?= esc(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?><?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
    <div class="mb-4">
        <a href="<?= base_url('customer-management') ?>" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
            <span class="material-symbols-outlined text-lg mr-1">arrow_back</span>
            Back to Customers
        </a>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- Customer Info Header -->
    <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xl">
                    <?= strtoupper(substr(($customer['first_name'] ?? 'C'), 0, 1)) ?>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        <?= esc(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?>
                    </h2>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <?php if (!empty($customer['email'])): ?>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">email</span>
                                <?= esc($customer['email']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($customer['phone'])): ?>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">phone</span>
                                <?= esc($customer['phone']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="<?= base_url('customer-management/edit/' . esc($customer['hash'])) ?>" 
                   class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    Edit Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="text-2xl font-bold text-gray-800 dark:text-gray-200"><?= esc($stats['total'] ?? 0) ?></div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Appointments</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="text-2xl font-bold text-blue-600"><?= esc($stats['upcoming'] ?? 0) ?></div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Upcoming</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="text-2xl font-bold text-green-600"><?= esc($stats['completed'] ?? 0) ?></div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="text-2xl font-bold text-red-600"><?= esc($stats['cancelled'] ?? 0) ?></div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Cancelled</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="text-2xl font-bold text-orange-600"><?= esc($stats['no_show'] ?? 0) ?></div>
            <div class="text-sm text-gray-600 dark:text-gray-400">No Show</div>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <?php if (!empty($upcoming)): ?>
    <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-blue-600">schedule</span>
            Upcoming Appointments
        </h3>
        <div class="grid gap-3">
            <?php foreach ($upcoming as $appt): ?>
            <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-4">
                    <div class="text-center min-w-[60px]">
                        <div class="text-sm font-semibold text-blue-600"><?= date('M', strtotime($appt['start_at'])) ?></div>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-200"><?= date('j', strtotime($appt['start_at'])) ?></div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-800 dark:text-gray-200"><?= esc($appt['service_name'] ?? 'Service') ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            <?= date('l \a\t g:i A', strtotime($appt['start_at'])) ?>
                            • with <?= esc($appt['provider_name'] ?? 'Provider') ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                        <?php if ($appt['status'] === 'confirmed'): ?>bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                        <?php else: ?>bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400<?php endif; ?>">
                        <?= ucfirst(esc($appt['status'])) ?>
                    </span>
                    <a href="<?= base_url('appointments/edit/' . ($appt['hash'] ?? $appt['id'])) ?>" 
                       class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600">
                        <span class="material-symbols-outlined">edit</span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- History with Filters -->
    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <span class="material-symbols-outlined">history</span>
                Appointment History
            </h3>
        </div>

        <!-- Filters -->
        <form method="get" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="no-show" <?= ($filters['status'] ?? '') === 'no-show' ? 'selected' : '' ?>>No Show</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provider</label>
                    <select name="provider_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                        <option value="">All Providers</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?= esc($p['id']) ?>" <?= ($filters['provider_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service</label>
                    <select name="service_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                        <option value="">All Services</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= esc($s['id']) ?>" <?= ($filters['service_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                    <input type="date" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                    <input type="date" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>" 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                    Apply Filters
                </button>
                <a href="<?= base_url('customer-management/history/' . esc($customer['hash'])) ?>" 
                   class="px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-gray-100 rounded-lg">
                    Clear
                </a>
            </div>
        </form>

        <!-- Appointments Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Date & Time</th>
                        <th class="px-4 py-3 font-semibold">Service</th>
                        <th class="px-4 py-3 font-semibold">Provider</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Notes</th>
                        <th class="px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($history['data'])): ?>
                    <?php foreach ($history['data'] as $appt): ?>
                    <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                <?= date('M j, Y', strtotime($appt['start_at'])) ?>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?= date('g:i A', strtotime($appt['start_at'])) ?>
                                <?php if (!empty($appt['end_at'])): ?>
                                    - <?= date('g:i A', strtotime($appt['end_at'])) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800 dark:text-gray-200"><?= esc($appt['service_name'] ?? '—') ?></div>
                            <?php if (!empty($appt['service_duration'])): ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?= esc($appt['service_duration']) ?> min</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <?php if (!empty($appt['provider_color'])): ?>
                                    <span class="w-3 h-3 rounded-full provider-color-dot" data-color="<?= esc($appt['provider_color']) ?>"></span>
                                <?php endif; ?>
                                <?= esc($appt['provider_name'] ?? '—') ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $statusClasses = [
                                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                'confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                'no-show' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                            ];
                            $statusClass = $statusClasses[$appt['status'] ?? ''] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 py-1 text-xs font-medium rounded-full <?= $statusClass ?>">
                                <?= ucfirst(str_replace('-', ' ', esc($appt['status'] ?? ''))) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 max-w-xs truncate text-gray-500 dark:text-gray-400">
                            <?= esc($appt['notes'] ?? '—') ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="<?= base_url('appointments/view/' . ($appt['hash'] ?? $appt['id'])) ?>" 
                               class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600" title="View Details">
                                <span class="material-symbols-outlined">visibility</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <span class="material-symbols-outlined text-4xl mb-2 block">event_busy</span>
                            No appointments found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (($history['pagination']['total_pages'] ?? 1) > 1): ?>
        <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Showing page <?= esc($currentPage) ?> of <?= esc($history['pagination']['total_pages']) ?>
                (<?= esc($history['pagination']['total']) ?> total)
            </div>
            <div class="flex gap-2">
                <?php if ($currentPage > 1): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $currentPage - 1])) ?>" 
                       class="px-3 py-1 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-lg text-sm">
                        Previous
                    </a>
                <?php endif; ?>
                <?php if ($history['pagination']['has_more'] ?? false): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $currentPage + 1])) ?>" 
                       class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?= $this->endSection() ?>

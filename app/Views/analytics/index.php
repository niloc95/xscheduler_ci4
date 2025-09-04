<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'analytics']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Analytics">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white transition-colors duration-300">
                    <?= esc($title) ?>
                </h1>
                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    Monitor your business performance and insights
                </p>
            </div>
            
            <div class="mt-4 sm:mt-0">
                <select id="timeframe" class="border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    <option value="7d" <?= $timeframe === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30d" <?= $timeframe === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="3m" <?= $timeframe === '3m' ? 'selected' : '' ?>>Last 3 Months</option>
                    <option value="1y" <?= $timeframe === '1y' ? 'selected' : '' ?>>Last Year</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- Revenue Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">$<?= number_format($overview['total_revenue'], 2) ?></p>
                    <div class="flex items-center mt-2">
                        <svg class="w-4 h-4 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                        </svg>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['revenue_change'] ?>%</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Appointments Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Appointments</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($overview['total_appointments']) ?></p>
                    <div class="flex items-center mt-2">
                        <svg class="w-4 h-4 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                        </svg>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['appointments_change'] ?>%</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- New Customers Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">New Customers</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($overview['new_customers']) ?></p>
                    <div class="flex items-center mt-2">
                        <svg class="w-4 h-4 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                        </svg>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['customers_change'] ?>%</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg. Booking Value</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">$<?= number_format($overview['avg_booking_value'], 2) ?></p>
                    <div class="flex items-center mt-1">
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['booking_value_change'] ?>%</span>
                    </div>
                </div>
                <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Customer Retention</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $overview['customer_retention'] ?>%</p>
                    <div class="flex items-center mt-1">
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['retention_change'] ?>%</span>
                    </div>
                </div>
                <div class="w-10 h-10 bg-pink-100 dark:bg-pink-900 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Staff Utilization</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $overview['staff_utilization'] ?>%</p>
                    <div class="flex items-center mt-1">
                        <span class="text-sm font-medium text-red-600 dark:text-red-400"><?= $overview['utilization_change'] ?>%</span>
                    </div>
                </div>
                <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Revenue Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue Trend</h3>
                <select class="text-sm border border-gray-300 dark:border-gray-600 rounded px-3 py-1 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    <option>Daily</option>
                    <option>Monthly</option>
                </select>
            </div>
            <div class="h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400">Revenue Chart</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500">Chart implementation pending</p>
                </div>
            </div>
        </div>

        <!-- Appointments by Status -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Appointments by Status</h3>
            <div class="space-y-4">
                <?php foreach ($appointments['by_status'] as $status => $count): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-3
                                <?php if ($status === 'completed'): ?>bg-green-500
                                <?php elseif ($status === 'pending'): ?>bg-amber-500
                                <?php elseif ($status === 'cancelled'): ?>bg-red-500
                                <?php else: ?>bg-gray-500<?php endif; ?>"></div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize"><?= $status ?></span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white mr-3"><?= $count ?></span>
                            <div class="w-20 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="h-2 rounded-full
                                    <?php if ($status === 'completed'): ?>bg-green-500
                                    <?php elseif ($status === 'pending'): ?>bg-amber-500
                                    <?php elseif ($status === 'cancelled'): ?>bg-red-500
                                    <?php else: ?>bg-gray-500<?php endif; ?>"
                                    style="width: <?= ($count / array_sum($appointments['by_status'])) * 100 ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Popular Services -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Popular Services</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php foreach ($services['popular_services'] as $service): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white"><?= esc($service['name']) ?></h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400"><?= $service['bookings'] ?> bookings</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-gray-900 dark:text-white">$<?= number_format($service['revenue'], 2) ?></p>
                            <p class="text-sm text-green-600 dark:text-green-400">+<?= $service['growth'] ?>%</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Timeframe change handler
document.getElementById('timeframe').addEventListener('change', function() {
    const timeframe = this.value;
    window.location.href = `<?= current_url() ?>?timeframe=${timeframe}`;
});
</script>
<?= $this->endSection() ?>

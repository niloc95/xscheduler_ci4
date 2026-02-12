<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'analytics']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>Analytics<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Monitor your business performance and insights<?= $this->endSection() ?>

<?= $this->section('dashboard_actions') ?>
    <div class="relative">
        <select id="timeframe" class="appearance-none border border-gray-300 dark:border-gray-600 rounded-lg pl-4 pr-10 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="7d" <?= $timeframe === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
            <option value="30d" <?= $timeframe === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
            <option value="3m" <?= $timeframe === '3m' ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="1y" <?= $timeframe === '1y' ? 'selected' : '' ?>>Last Year</option>
        </select>
        <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-500 dark:text-gray-400">expand_more</span>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_stats_class') ?>grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6<?= $this->endSection() ?>

<?= $this->section('dashboard_stats') ?>
    <!-- Primary Metrics -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= format_currency($overview['total_revenue']) ?></p>
                <div class="flex items-center mt-2">
                    <span class="material-symbols-rounded text-green-500 mr-1 text-base align-middle">trending_up</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['revenue_change'] ?>%</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                <span class="material-symbols-rounded text-green-600 dark:text-green-400 text-2xl">attach_money</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Appointments</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($overview['total_appointments']) ?></p>
                <div class="flex items-center mt-2">
                    <span class="material-symbols-rounded text-green-500 mr-1 text-base align-middle">trending_up</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['appointments_change'] ?>%</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-2xl">calendar_month</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">New Customers</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($overview['new_customers']) ?></p>
                <div class="flex items-center mt-2">
                    <span class="material-symbols-rounded text-green-500 mr-1 text-base align-middle">trending_up</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['customers_change'] ?>%</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                <span class="material-symbols-rounded text-purple-600 dark:text-purple-400 text-2xl">group</span>
            </div>
        </div>
    </div>

    <!-- Secondary Metrics -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg. Booking Value</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= format_currency($overview['avg_booking_value']) ?></p>
                <div class="flex items-center mt-1">
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['booking_value_change'] ?>%</span>
                </div>
            </div>
            <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                <span class="material-symbols-rounded text-amber-600 dark:text-amber-400 text-xl">attach_money</span>
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
                <span class="material-symbols-rounded text-pink-600 dark:text-pink-400 text-xl">favorite</span>
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
                <span class="material-symbols-rounded text-indigo-600 dark:text-indigo-400 text-xl">groups</span>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue Trend</h3>
                <div class="relative">
                    <select id="revenueChartType" class="appearance-none text-sm border border-gray-300 dark:border-gray-600 rounded-lg pl-3 pr-9 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="daily">Daily</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-gray-500 dark:text-gray-400 text-base">expand_more</span>
                </div>
            </div>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

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
                                <?php elseif ($status === 'confirmed'): ?>bg-blue-500
                                <?php else: ?>bg-gray-500<?php endif; ?>"></div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize"><?= $status ?></span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white mr-3"><?= $count ?></span>
                            <div class="w-20 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <?php $totalAppointments = array_sum($appointments['by_status']); ?>
                                <div class="h-2 rounded-full
                                    <?php if ($status === 'completed'): ?>bg-green-500
                                    <?php elseif ($status === 'pending'): ?>bg-amber-500
                                    <?php elseif ($status === 'cancelled'): ?>bg-red-500
                                    <?php elseif ($status === 'confirmed'): ?>bg-blue-500
                                    <?php else: ?>bg-gray-500<?php endif; ?>"
                                    style="width: <?= $totalAppointments > 0 ? ($count / $totalAppointments) * 100 : 0 ?>%"></div>
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
                            <p class="font-semibold text-gray-900 dark:text-white"><?= format_currency($service['revenue']) ?></p>
                            <p class="text-sm text-green-600 dark:text-green-400">+<?= $service['growth'] ?>%</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Revenue data from PHP
const revenueData = <?= json_encode($revenue) ?>;
const currencySymbol = '<?= esc(get_app_currency_symbol()) ?>';

// Wait for main.js to load and expose window functions
window.addEventListener('load', function() {
    // Initialize revenue chart
    let revenueChart = null;
    let currentChartType = 'daily';

    function updateRevenueChart(type) {
        if (revenueChart) {
            revenueChart.destroy();
        }
        if (typeof window.initRevenueTrendChart === 'function') {
            revenueChart = window.initRevenueTrendChart('revenueChart', revenueData, type, currencySymbol);
            currentChartType = type;
        } else {
            console.error('initRevenueTrendChart not found on window object');
        }
    }

    // Initialize with daily view
    updateRevenueChart('daily');

    // Chart type change handler
    document.getElementById('revenueChartType').addEventListener('change', function() {
        updateRevenueChart(this.value);
    });
});

// Timeframe change handler
document.getElementById('timeframe').addEventListener('change', function() {
    const timeframe = this.value;
    window.location.href = `<?= current_url() ?>?timeframe=${timeframe}`;
});
</script>
<?= $this->endSection() ?>

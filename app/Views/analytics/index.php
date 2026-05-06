<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'analytics']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>Analytics<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Monitor your business performance and insights<?= $this->endSection() ?>

<?= $this->section('dashboard_actions') ?>
    <div class="relative">
        <select id="timeframe" class="appearance-none border border-gray-300 dark:border-gray-600 rounded-lg pl-4 pr-10 h-11 bg-white dark:bg-gray-700 text-gray-900 dark:text-white cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="7d" <?= $timeframe === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
            <option value="30d" <?= $timeframe === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
            <option value="3m" <?= $timeframe === '3m' ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="1y" <?= $timeframe === '1y' ? 'selected' : '' ?>>Last Year</option>
        </select>
        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-500 dark:text-gray-400">expand_more</span>
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
                    <span class="material-symbols-outlined text-green-500 mr-1 text-base align-middle">trending_up</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['revenue_change'] ?>%</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-2xl">attach_money</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Appointments</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($overview['total_appointments']) ?></p>
                <div class="flex items-center mt-2">
                    <span class="material-symbols-outlined text-green-500 mr-1 text-base align-middle">trending_up</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['appointments_change'] ?>%</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-2xl">calendar_month</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">New Customers</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($overview['new_customers']) ?></p>
                <div class="flex items-center mt-2">
                    <span class="material-symbols-outlined text-green-500 mr-1 text-base align-middle">trending_up</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">+<?= $overview['customers_change'] ?>%</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">from last month</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-purple-600 dark:text-purple-400 text-2xl">group</span>
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
                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-xl">attach_money</span>
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
                <span class="material-symbols-outlined text-pink-600 dark:text-pink-400 text-xl">favorite</span>
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
                <span class="material-symbols-outlined text-indigo-600 dark:text-indigo-400 text-xl">groups</span>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <?php
        $activeTab = $tab ?? 'overview';
        $tabBaseClasses = 'inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition';
        $tabActiveClasses = 'bg-blue-600 text-white shadow-sm';
        $tabInactiveClasses = 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700';
        $totalAppointments = array_sum($appointments['by_status']);
        $providerFilterState = $provider_filters['selected'] ?? ['provider_id' => null, 'service_id' => null, 'location_id' => null];
        $providerOptions = $provider_filters['provider_options'] ?? [];
        $providerServiceOptions = $provider_filters['service_options'] ?? [];
        $providerLocationOptions = $provider_filters['location_options'] ?? [];
    ?>
    <div
        class="space-y-6"
        data-analytics-tabs
        data-active-tab="<?= esc($activeTab) ?>"
        data-currency-symbol="<?= esc(get_app_currency_symbol()) ?>"
        data-revenue-payload="<?= esc(base64_encode(json_encode($revenue))) ?>"
        data-detailed-revenue-payload="<?= esc(base64_encode(json_encode($revenue_data ?? []))) ?>"
        data-comparisons-payload="<?= esc(base64_encode(json_encode($comparisons ?? []))) ?>"
        data-customer-payload="<?= esc(base64_encode(json_encode($customers ?? []))) ?>"
        data-appointment-payload="<?= esc(base64_encode(json_encode($appointments ?? []))) ?>"
        data-provider-busy-hours-payload="<?= esc(base64_encode(json_encode($revenue_data['busy_hours_distribution'] ?? []))) ?>"
    >
        <div class="flex flex-wrap gap-3">
            <button type="button" class="<?= $tabBaseClasses ?> <?= $activeTab === 'overview' ? $tabActiveClasses : $tabInactiveClasses ?>" data-analytics-tab-trigger="overview">Overview</button>
            <button type="button" class="<?= $tabBaseClasses ?> <?= $activeTab === 'revenue' ? $tabActiveClasses : $tabInactiveClasses ?>" data-analytics-tab-trigger="revenue">Revenue</button>
            <button type="button" class="<?= $tabBaseClasses ?> <?= $activeTab === 'customers' ? $tabActiveClasses : $tabInactiveClasses ?>" data-analytics-tab-trigger="customers">Customers</button>
            <button type="button" class="<?= $tabBaseClasses ?> <?= $activeTab === 'providers' ? $tabActiveClasses : $tabInactiveClasses ?>" data-analytics-tab-trigger="providers">Providers</button>
        </div>

        <section data-analytics-tab-panel="overview" class="space-y-6 <?= $activeTab === 'overview' ? '' : 'hidden' ?>">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue Trend</h3>
                        <div class="relative">
                            <select id="revenueChartType" class="appearance-none text-sm border border-gray-300 dark:border-gray-600 rounded-lg pl-3 pr-9 h-11 bg-white dark:bg-gray-700 text-gray-900 dark:text-white cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="daily">Daily</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-gray-500 dark:text-gray-400 text-base">expand_more</span>
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
                                    <progress
                                        class="w-20 h-2 rounded-full overflow-hidden appearance-none analytics-progress
                                            <?php if ($status === 'completed'): ?>analytics-progress--completed
                                            <?php elseif ($status === 'pending'): ?>analytics-progress--pending
                                            <?php elseif ($status === 'cancelled'): ?>analytics-progress--cancelled
                                            <?php elseif ($status === 'confirmed'): ?>analytics-progress--confirmed
                                            <?php else: ?>analytics-progress--default<?php endif; ?>"
                                        value="<?= (int) $count ?>"
                                        max="<?= max(1, (int) $totalAppointments) ?>"
                                        aria-label="<?= esc($status) ?> appointments"
                                    ></progress>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

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
        </section>

        <section data-analytics-tab-panel="revenue" class="space-y-6 <?= $activeTab === 'revenue' ? '' : 'hidden' ?>">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Current Window Revenue</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"><?= format_currency($comparisons['current_total'] ?? 0) ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Previous Window Revenue</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"><?= format_currency($comparisons['previous_total'] ?? 0) ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Projected Next Window</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"><?= format_currency($comparisons['forecast_next_month'] ?? 0) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Window-over-Window Revenue</h3>
                    <div class="h-72">
                        <canvas id="momComparisonChart"></canvas>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Revenue by Provider</h3>
                    <div class="h-72">
                        <canvas id="revenueByProviderChart"></canvas>
                    </div>
                </div>
            </div>

        </section>

        <section data-analytics-tab-panel="customers" class="space-y-6 <?= $activeTab === 'customers' ? '' : 'hidden' ?>">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">New vs Returning Customers</h3>
                    <div class="h-72">
                        <canvas id="newVsReturningChart"></canvas>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Peak Booking Hours</h3>
                    <div class="h-72">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section data-analytics-tab-panel="providers" class="space-y-6 <?= $activeTab === 'providers' ? '' : 'hidden' ?>">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-wrap items-end gap-4">
                    <form method="get" action="<?= current_url() ?>" class="flex flex-wrap items-end gap-4 w-full">
                        <input type="hidden" name="tab" value="providers">
                        <input type="hidden" name="timeframe" value="<?= esc($timeframe) ?>">

                        <?php if (($user_role ?? '') !== 'provider'): ?>
                            <div class="min-w-[220px]">
                                <label for="providerFilter" class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-2">Provider</label>
                                <select id="providerFilter" name="provider_id" class="w-full appearance-none border border-gray-300 dark:border-gray-600 rounded-lg px-3 h-11 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Providers</option>
                                    <?php foreach ($providerOptions as $providerOption): ?>
                                        <option value="<?= (int) ($providerOption['id'] ?? 0) ?>" <?= (int) ($providerFilterState['provider_id'] ?? 0) === (int) ($providerOption['id'] ?? 0) ? 'selected' : '' ?>>
                                            <?= esc($providerOption['name'] ?? 'Unknown Provider') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="min-w-[220px]">
                            <label for="providerServiceFilter" class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-2">Service</label>
                            <select id="providerServiceFilter" name="provider_service_id" class="w-full appearance-none border border-gray-300 dark:border-gray-600 rounded-lg px-3 h-11 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Services</option>
                                <?php foreach ($providerServiceOptions as $serviceOption): ?>
                                    <option value="<?= (int) ($serviceOption['id'] ?? 0) ?>" <?= (int) ($providerFilterState['service_id'] ?? 0) === (int) ($serviceOption['id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= esc($serviceOption['name'] ?? 'Unknown Service') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="min-w-[220px]">
                            <label for="providerLocationFilter" class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-2">Location</label>
                            <select id="providerLocationFilter" name="provider_location_id" class="w-full appearance-none border border-gray-300 dark:border-gray-600 rounded-lg px-3 h-11 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Locations</option>
                                <?php foreach ($providerLocationOptions as $locationOption): ?>
                                    <option value="<?= (int) ($locationOption['id'] ?? 0) ?>" <?= (int) ($providerFilterState['location_id'] ?? 0) === (int) ($locationOption['id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= esc($locationOption['name'] ?? 'Unknown Location') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit" class="h-11 px-4 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">Apply Filters</button>
                            <a href="<?= current_url() . '?' . http_build_query(['tab' => 'providers', 'timeframe' => $timeframe]) ?>" class="h-11 px-4 inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Provider Busy Hours</h3>
                    <div class="h-72">
                        <canvas id="providerBusyHoursChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Provider Tracking</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Provider by service, location, utilization, revenue, and busiest hour.</p>
                </div>

                <?php $providerBreakdown = $revenue_data['provider_breakdown'] ?? []; ?>
                <?php if (empty($providerBreakdown)): ?>
                    <div class="px-6 py-8 text-sm text-gray-600 dark:text-gray-400">No provider data matches the selected filters/timeframe.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Provider</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Service</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Location</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Utilization</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Revenue</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Busy Hour</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <?php foreach ($providerBreakdown as $row): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white"><?= esc($row['provider_name'] ?? 'Unknown Provider') ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?= esc($row['service_name'] ?? 'N/A') ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?= esc($row['location_name'] ?? 'N/A') ?></td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <progress
                                                    class="w-24 h-2 rounded-full overflow-hidden appearance-none analytics-progress analytics-progress--confirmed"
                                                    value="<?= (float) ($row['utilization'] ?? 0) ?>"
                                                    max="100"
                                                    aria-label="Provider utilization"
                                                ></progress>
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?= number_format((float) ($row['utilization'] ?? 0), 1) ?>%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-semibold text-right text-gray-900 dark:text-white"><?= format_currency((float) ($row['revenue'] ?? 0)) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            <?= esc($row['busy_hour_label'] ?? 'N/A') ?>
                                            <?php if ((int) ($row['busy_hour_count'] ?? 0) > 0): ?>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">(<?= (int) ($row['busy_hour_count']) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
<?= $this->endSection() ?>

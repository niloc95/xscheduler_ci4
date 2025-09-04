<?= $this->extend('components/layout') ?>

<?= $this->section('head') ?>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Layout: fixed sidebar, padded main on large screens */
        .main-content { min-height: 100vh; }
        
        /* Material Web Components custom properties */
        :root {
            --md-sys-color-primary: rgb(59, 130, 246);
            --md-sys-color-on-primary: rgb(255, 255, 255);
            --md-sys-color-surface: rgb(255, 255, 255);
            --md-sys-color-on-surface: rgb(17, 24, 39);
            --md-sys-color-surface-variant: rgb(248, 250, 252);
            --md-sys-color-outline: rgb(229, 231, 235);
            --md-sys-color-secondary: rgb(255, 152, 0);
            --md-sys-color-tertiary: rgb(255, 87, 34);
        }
        
        html.dark {
            --md-sys-color-primary: rgb(96, 165, 250);
            --md-sys-color-on-primary: rgb(17, 24, 39);
            --md-sys-color-surface: rgb(31, 41, 55);
            --md-sys-color-on-surface: rgb(243, 244, 246);
            --md-sys-color-surface-variant: rgb(55, 65, 81);
            --md-sys-color-outline: rgb(75, 85, 99);
            --md-sys-color-secondary: rgb(251, 191, 36);
            --md-sys-color-tertiary: rgb(251, 146, 60);
        }
        
    /* Custom Material shadows */
        .material-shadow {
            box-shadow: 0px 2px 4px -1px rgba(0, 0, 0, 0.2), 0px 4px 5px 0px rgba(0, 0, 0, 0.14), 0px 1px 10px 0px rgba(0, 0, 0, 0.12);
        }
        
        .material-shadow-lg {
            box-shadow: 0px 5px 5px -3px rgba(0, 0, 0, 0.2), 0px 8px 10px 1px rgba(0, 0, 0, 0.14), 0px 3px 14px 2px rgba(0, 0, 0, 0.12);
        }
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Ensure filled buttons are visibly primary-colored */
        md-filled-button {
            --md-filled-button-container-color: var(--md-sys-color-primary);
            --md-filled-button-label-text-color: var(--md-sys-color-on-primary);
        }
    </style>
<?= $this->endSection() ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'dashboard']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="main-content" data-page-title="Dashboard">

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Card 1 - Total Users -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand" style="background-color: var(--md-sys-color-primary);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Total Users</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['total_users'] ?? 2345) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-2xl">group</span>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <span class="material-symbols-outlined mr-1 text-green-300 text-base">trending_up</span>
                    <span class="opacity-80">+12% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 2 - Active Appointments -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand" style="background-color: var(--md-sys-color-secondary); color: var(--md-sys-color-on-surface);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Active Appointments</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['active_sessions'] ?? 1789) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-2xl">calendar_month</span>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <span class="material-symbols-outlined mr-1 text-green-300 text-base">trending_up</span>
                    <span class="opacity-80">+8% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 3 - Pending Requests -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand" style="background-color: var(--md-sys-color-tertiary);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Pending Requests</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['pending_tasks'] ?? 456) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-2xl">schedule</span>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <span class="material-symbols-outlined mr-1 text-red-300 text-base">trending_down</span>
                    <span class="opacity-80">-3% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 4 - Monthly Revenue -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Monthly Revenue</p>
                        <p class="text-3xl font-bold">$<?= number_format($stats['revenue'] ?? 12456) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-2xl">attach_money</span>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <span class="material-symbols-outlined mr-1 text-green-300 text-base">trending_up</span>
                    <span class="opacity-80">+15% from last month</span>
                </div>
            </md-outlined-card>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- User Growth Chart -->
            <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">User Growth</h3>
                    <button class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors duration-200">
                        <span class="material-symbols-outlined">more_vert</span>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </md-outlined-card>

            <!-- Activity Overview Chart -->
            <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Activity Overview</h3>
                    <button class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors duration-200">
                        <span class="material-symbols-outlined">more_vert</span>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </md-outlined-card>
        </div>

        <!-- Data Table -->
    <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Recent Activities</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Latest user activities and updates</p>
                </div>
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors duration-200 w-full sm:w-auto justify-center">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span>Add New</span>
                </button>
            </div>
            
            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400 transition-colors duration-300">
                    <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600 transition-colors duration-300">
                        <tr>
                            <th class="px-6 py-4 font-semibold">User</th>
                            <th class="px-6 py-4 font-semibold">Activity</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold">Date</th>
                            <th class="px-6 py-4 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($recent_activities) && !empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-300">
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-500 dark:bg-blue-600 rounded-full flex items-center justify-center text-white text-sm mr-3 transition-colors duration-300">
                                                <?= strtoupper(substr($activity['user_name'], 0, 2)) ?>
                                            </div>
                                            <?= esc($activity['user_name']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4"><?= esc($activity['activity']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="bg-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-100 dark:bg-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-900 text-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-800 dark:text-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-200 text-xs font-medium px-2.5 py-0.5 rounded capitalize transition-colors duration-300">
                                            <?= esc($activity['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><?= date('M j, Y', strtotime($activity['date'])) ?></td>
                                    <td class="px-6 py-4">
                                        <button class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 mr-2">
                                            <span class="material-symbols-outlined text-base">edit</span>
                                        </button>
                                        <button class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200">
                                            <span class="material-symbols-outlined text-base">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-300">
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-500 dark:bg-blue-600 rounded-full flex items-center justify-center text-white text-sm mr-3 transition-colors duration-300">JD</div>
                                        John Doe
                                    </div>
                                </td>
                                <td class="px-6 py-4">Scheduled meeting</td>
                                <td class="px-6 py-4">
                                    <span class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs font-medium px-2.5 py-0.5 rounded transition-colors duration-300">Active</span>
                                </td>
                                <td class="px-6 py-4">Jan 1, 2025</td>
                                <td class="px-6 py-4">
                                    <button class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 mr-2">
                                        <span class="material-symbols-outlined text-base">edit</span>
                                    </button>
                                    <button class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card Layout -->
            <div class="md:hidden space-y-4">
                <?php if (isset($recent_activities) && !empty($recent_activities)): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 transition-colors duration-300 shadow-brand">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-500 dark:bg-blue-600 rounded-full flex items-center justify-center text-white text-sm mr-3 transition-colors duration-300">
                                        <?= strtoupper(substr($activity['user_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300"><?= esc($activity['user_name']) ?></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 transition-colors duration-300"><?= date('M j, Y', strtotime($activity['date'])) ?></p>
                                    </div>
                                </div>
                                <span class="bg-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-100 dark:bg-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-900 text-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-800 dark:text-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-200 text-xs font-medium px-2.5 py-0.5 rounded capitalize transition-colors duration-300">
                                    <?= esc($activity['status']) ?>
                                </span>
                            </div>
                            <p class="text-gray-700 dark:text-gray-300 mb-3 transition-colors duration-300"><?= esc($activity['activity']) ?></p>
                            <div class="flex justify-end space-x-2">
                                <button class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </button>
                                <button class="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 transition-colors duration-300 shadow-brand">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-500 dark:bg-blue-600 rounded-full flex items-center justify-center text-white text-sm mr-3 transition-colors duration-300">JD</div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300">John Doe</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 transition-colors duration-300">Jan 1, 2025</p>
                                </div>
                            </div>
                            <span class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs font-medium px-2.5 py-0.5 rounded transition-colors duration-300">Active</span>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-3 transition-colors duration-300">Scheduled meeting</p>
                        <div class="flex justify-end space-x-2">
                            <button class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                <span class="material-symbols-outlined text-base">edit</span>
                            </button>
                            <button class="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                <span class="material-symbols-outlined text-base">delete</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scheduler (embedded SPA-like section) -->
    <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Scheduler</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Find and book time slots</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full sm:w-auto">
                    <select id="sch-service" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <?php foreach (($servicesList ?? []) as $s): ?>
                            <option value="<?= esc($s['id']) ?>"><?= esc($s['name']) ?> (<?= esc($s['duration_min']) ?> min)</option>
                        <?php endforeach; ?>
                    </select>
                    <input id="sch-provider" type="number" min="1" value="1" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Provider ID" />
                    <input id="sch-date" type="date" value="<?= date('Y-m-d') ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                </div>
            </div>
            <div id="sch-slots" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"></div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand">
                <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-blue-500 dark:text-blue-400 text-4xl">calendar_month</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">Schedule Meeting</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Create a new meeting or appointment</p>
                <md-filled-button>
                    <span slot="icon" class="material-symbols-outlined">add</span>
                    Schedule
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand">
                <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-green-500 dark:text-green-400 text-4xl">person_add</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">Add User</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Invite a new user to the system</p>
                <md-filled-button>
                    <span slot="icon" class="material-symbols-outlined">person</span>
                    Add User
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand">
                <div class="w-16 h-16 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-purple-500 dark:text-purple-400 text-4xl">analytics</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">View Reports</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Generate detailed analytics reports</p>
                <md-filled-button>
                    <span slot="icon" class="material-symbols-outlined">insights</span>
                    Reports
                </md-filled-button>
            </md-outlined-card>
        </div>
    </div>
    <!-- Page Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('dashboardSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase();
                    // Simple search feedback
                    if (query.length > 2) {
                        console.log('Searching for:', query);
                        // Here you could implement actual search functionality
                        // For now, just show visual feedback
                        e.target.classList.add('ring-2', 'ring-blue-500');
                    } else {
                        e.target.classList.remove('ring-2', 'ring-blue-500');
                    }
                });

                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const query = e.target.value;
                        if (query.trim()) {
                            // Simulate search action
                            alert(`Searching for: "${query}"`);
                        }
                    }
                });
            }
        });

        function initCharts() {
            import('<?= base_url('/build/assets/charts.js') ?>').then(module => {
                if (module?.default?.initAllCharts) module.default.initAllCharts();
            }).catch(error => {
                console.log('Charts not available:', error);
                document.querySelectorAll('.chart-container').forEach(container => {
                    container.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400 transition-colors duration-300"><p>Chart data will be displayed here</p></div>';
                });
            });
        }

        function initEmbeddedScheduler() {
            const svc = document.getElementById('sch-service');
            const prov = document.getElementById('sch-provider');
            const dateEl = document.getElementById('sch-date');
            const slots = document.getElementById('sch-slots');
            async function loadSlots() {
                if (!svc || !prov || !dateEl || !slots) return;
                slots.innerHTML = '';
                const params = new URLSearchParams({
                    service_id: svc.value,
                    provider_id: prov.value,
                    date: dateEl.value
                });
                try {
                    const res = await fetch('<?= base_url('api/slots') ?>?' + params.toString());
                    const data = await res.json();
                    (data.slots || []).forEach(s => {
                        const div = document.createElement('div');
                        div.className = 'p-3 rounded-lg border dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-between';
                        const span = document.createElement('span');
                        span.className = 'text-sm text-gray-700 dark:text-gray-200';
                        span.textContent = `${s.start} - ${s.end}`;
                        const btn = document.createElement('button');
                        btn.className = 'px-3 py-1 rounded-md text-white';
                        btn.style.backgroundColor = 'var(--md-sys-color-primary)';
                        btn.textContent = 'Book';
                        btn.addEventListener('click', () => {
                            alert(`Book ${s.start}-${s.end} (stub)`);
                        });
                        div.append(span, btn);
                        slots.appendChild(div);
                    });
                    if (!data.slots || data.slots.length === 0) {
                        slots.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">No available slots.</div>';
                    }
                } catch (e) {
                    slots.innerHTML = '<div class="text-sm text-red-600 dark:text-red-400">Failed to load slots.</div>';
                }
            }
            if (svc && prov && dateEl) {
                svc.addEventListener('change', loadSlots);
                prov.addEventListener('change', loadSlots);
                dateEl.addEventListener('change', loadSlots);
                loadSlots();
            }
        }

        // Initialize on first load
        initCharts();
        initEmbeddedScheduler();

        // Re-init on SPA navigations
        document.addEventListener('spa:navigated', () => {
            initCharts();
            initEmbeddedScheduler();
        });
    </script>
<?= $this->endSection() ?>

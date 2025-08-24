
<!DOCTYPE html>
<html lang="en" class="transition-colors duration-300">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xScheduler Dashboard - Material Design</title>
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Create new scheduled events</p>
                <md-filled-button>
                    <md-icon slot="icon">add_circle</md-icon>
                    Schedule
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300">
                <md-icon class="text-4xl text-green-500 dark:text-green-400 mb-4 transition-colors duration-300">person_add</md-icon>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">Add User</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Invite a new user to the system</p>
                <md-filled-button>
                    <md-icon slot="icon">person_add</md-icon>
                    Add User
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300">
                <md-icon class="text-4xl text-purple-500 dark:text-purple-400 mb-4 transition-colors duration-300">assessment</md-icon>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">View Reports</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Generate detailed analytics reports</p>
                <md-filled-button>
                    <md-icon slot="icon">bar_chart</md-icon>
                    Reports
                </md-filled-button>
            </md-outlined-card>
        </div>
    </div>

    <!-- Material Web Components -->
    <script src="<?= base_url('/build/assets/materialWeb.js') ?>"></script>
    
    <!-- Dark Mode Script -->
    <script src="<?= base_url('/build/assets/dark-mode.js') ?>"></script>
    
    <!-- Charts -->
    <script src="<?= base_url('/build/assets/main.js') ?>"></script>ylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Your built CSS -->
    <link href="<?= base_url('/build/assets/style.css') ?>" rel="stylesheet">
    
    <!-- Prevent flash of unstyled content -->
    <script>
        // Immediately apply dark mode class if needed
        (function() {
            const isDark = localStorage.getItem('darkMode') === 'true' || 
                          (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Force proper layout - high specificity */
        .main-content {
            min-height: 100vh;
            padding: 1rem !important;
            margin-left: 0 !important;
        }
        
        /* Desktop layout - force sidebar positioning */
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem !important; /* Force 256px offset for sidebar */
                padding: 1rem !important;
                width: calc(100% - 16rem) !important;
            }
            
            .sidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 16rem !important;
                height: 100vh !important;
                z-index: 40 !important;
                transform: translateX(0) !important;
            }
        }
        
        /* Mobile layout */
        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0) !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 1rem !important;
            }
        }
        
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
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300">

    <!-- Mobile Menu Backdrop -->
    <div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed top-0 left-0 z-40 w-64 h-screen bg-white dark:bg-gray-800 material-shadow-lg transition-colors duration-300">
        <div class="h-full px-3 py-4 overflow-y-auto">
            <!-- Logo -->
            <div class="flex items-center justify-between mb-6 p-2">
                <div class="flex items-center">
                    <md-icon-button class="text-blue-600 dark:text-blue-400">
                        <md-icon>schedule</md-icon>
                    </md-icon-button>
                    <span class="text-xl font-semibold text-gray-800 dark:text-gray-200 ml-2 transition-colors duration-300">xScheduler</span>
                </div>
                <md-icon-button id="closeSidebar" class="lg:hidden text-gray-600 dark:text-gray-400">
                    <md-icon>close</md-icon>
                </md-icon-button>
            </div>
            
            <!-- Navigation -->
            <md-list>
                <md-list-item href="#" class="mb-1">
                    <md-icon slot="start">dashboard</md-icon>
                    <div slot="headline">Dashboard</div>
                </md-list-item>
                
                <md-list-item href="/scheduler" class="mb-1">
                    <md-icon slot="start">event</md-icon>
                    <div slot="headline">Schedule</div>
                </md-list-item>
                
                <md-list-item href="#" class="mb-1">
                    <md-icon slot="start">people</md-icon>
                    <div slot="headline">Users</div>
                </md-list-item>
                
                <md-list-item href="#" class="mb-1">
                    <md-icon slot="start">analytics</md-icon>
                    <div slot="headline">Analytics</div>
                </md-list-item>
                
                <md-list-item href="#" class="mb-1">
                    <md-icon slot="start">notifications</md-icon>
                    <div slot="headline">Notifications</div>
                </md-list-item>
                
                <md-divider class="my-4"></md-divider>
                
                <md-list-item href="#" class="mb-1">
                    <md-icon slot="start">settings</md-icon>
                    <div slot="headline">Settings</div>
                </md-list-item>
                
                <md-list-item href="#" class="mb-1">
                    <md-icon slot="start">help</md-icon>
                    <div slot="headline">Help</div>
                </md-list-item>
            </md-list>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content p-4">
        <!-- Top Bar -->
        <div class="bg-white dark:bg-gray-800 material-shadow rounded-lg p-4 mb-6 transition-colors duration-300">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <md-icon-button id="menuToggle" class="lg:hidden mr-2 text-gray-600 dark:text-gray-400">
                        <md-icon>menu</md-icon>
                    </md-icon-button>
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Dashboard</h1>
                        <p class="text-gray-600 dark:text-gray-400 transition-colors duration-300">Welcome back, <?= $user['name'] ?? 'User' ?></p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Dark Mode Toggle -->
                    <?= $this->include('components/dark-mode-toggle') ?>
                    
                    <!-- Search -->
                    <div class="hidden md:block">
                        <md-outlined-text-field label="Search" type="search" class="w-64">
                            <md-icon slot="leading-icon">search</md-icon>
                        </md-outlined-text-field>
                    </div>
                    
                    <!-- Notifications -->
                    <md-icon-button class="text-gray-600 dark:text-gray-400">
                        <md-icon>notifications</md-icon>
                    </md-icon-button>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-2">
                        <div class="w-10 h-10 bg-blue-500 dark:bg-blue-600 rounded-full flex items-center justify-center text-white font-medium transition-colors duration-300">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?>
                        </div>
                        <div class="hidden md:block">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors duration-300"><?= $user['name'] ?? 'User' ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 transition-colors duration-300"><?= $user['role'] ?? 'Administrator' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Card 1 -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300" style="background-color: var(--md-sys-color-primary);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Total Users</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['total_users'] ?? 2345) ?></p>
                    </div>
                    <md-icon class="text-4xl opacity-80">people</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1 text-green-300">trending_up</md-icon>
                    <span class="opacity-80">+12% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 2 -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300" style="background-color: var(--md-sys-color-secondary); color: var(--md-sys-color-on-surface);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Active Sessions</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['active_sessions'] ?? 1789) ?></p>
                    </div>
                    <md-icon class="text-4xl opacity-80">event</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1">trending_up</md-icon>
                    <span class="opacity-80">+8% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 3 -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300" style="background-color: var(--md-sys-color-tertiary);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Pending Tasks</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['pending_tasks'] ?? 456) ?></p>
                    </div>
                    <md-icon class="text-4xl opacity-80">pending_actions</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1 text-red-300">trending_down</md-icon>
                    <span class="opacity-80">-3% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 4 -->
            <md-outlined-card class="p-6 text-white bg-red-600 dark:bg-red-700 transition-colors duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Revenue</p>
                        <p class="text-3xl font-bold">$<?= number_format($stats['revenue'] ?? 12456) ?></p>
                    </div>
                    <md-icon class="text-4xl opacity-80">attach_money</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1 text-green-300">trending_up</md-icon>
                    <span class="opacity-80">+15% from last month</span>
                </div>
            </md-outlined-card>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- User Growth Chart -->
            <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">User Growth</h3>
                    <md-icon-button class="text-gray-600 dark:text-gray-400">
                        <md-icon>more_vert</md-icon>
                    </md-icon-button>
                </div>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </md-outlined-card>

            <!-- Activity Overview Chart -->
            <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Activity Overview</h3>
                    <md-icon-button class="text-gray-600 dark:text-gray-400">
                        <md-icon>more_vert</md-icon>
                    </md-icon-button>
                </div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </md-outlined-card>
        </div>

        <!-- Data Table -->
        <md-outlined-card class="p-6 mb-6 bg-white dark:bg-gray-800 transition-colors duration-300">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Recent Activities</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Latest user activities and updates</p>
                </div>
                <md-filled-button>
                    <md-icon slot="icon">add</md-icon>
                    Add New
                </md-filled-button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400 transition-colors duration-300">
                    <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase bg-gray-50 dark:bg-gray-700 transition-colors duration-300">
                        <tr>
                            <th class="px-6 py-3">User</th>
                            <th class="px-6 py-3">Activity</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Actions</th>
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
                                        <md-icon-button class="text-gray-600 dark:text-gray-400">
                                            <md-icon>edit</md-icon>
                                        </md-icon-button>
                                        <md-icon-button class="text-gray-600 dark:text-gray-400">
                                            <md-icon>delete</md-icon>
                                        </md-icon-button>
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
                                    <md-icon-button class="text-gray-600 dark:text-gray-400"><md-icon>edit</md-icon></md-icon-button>
                                    <md-icon-button class="text-gray-600 dark:text-gray-400"><md-icon>delete</md-icon></md-icon-button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </md-outlined-card>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300">
                <md-icon class="text-4xl text-blue-500 dark:text-blue-400 mb-4 transition-colors duration-300">event_available</md-icon>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">Schedule Meeting</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Create a new meeting or appointment</p>
                <md-filled-button onclick="window.location.href='/scheduler'">
                    <md-icon slot="icon">add</md-icon>
                    Schedule
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center">
                <md-icon class="text-4xl text-green-500 mb-4">group_add</md-icon>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Add User</h3>
                <p class="text-gray-600 text-sm mb-4">Invite a new user to the system</p>
                <md-filled-button>
                    <md-icon slot="icon">person_add</md-icon>
                    Add User
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center">
                <md-icon class="text-4xl text-purple-500 mb-4">assessment</md-icon>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">View Reports</h3>
                <p class="text-gray-600 text-sm mb-4">Generate detailed analytics reports</p>
                <md-filled-button>
                    <md-icon slot="icon">bar_chart</md-icon>
                    Reports
                </md-filled-button>
            </md-outlined-card>
        </div>
    </div>

    <!-- Material Web Components -->
    <script src="<?= base_url('/build/assets/materialWeb.js') ?>"></script>
    
    <!-- Charts -->
    <script src="<?= base_url('/build/assets/main.js') ?>"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Initialize dark mode
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DarkModeManager if available
            if (typeof DarkModeManager !== 'undefined') {
                new DarkModeManager();
            }
        });
        
        // Mobile menu functionality
        const menuToggle = document.getElementById('menuToggle');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('backdrop');

        function openSidebar() {
            sidebar.classList.add('open');
            backdrop.classList.remove('hidden');
        }

        function closeSidebarFn() {
            sidebar.classList.remove('open');
            backdrop.classList.add('hidden');
        }

        menuToggle?.addEventListener('click', openSidebar);
        closeSidebar?.addEventListener('click', closeSidebarFn);
        backdrop?.addEventListener('click', closeSidebarFn);

        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Import and initialize charts
            import('<?= base_url('/build/assets/charts.js') ?>').then(module => {
                module.default.initAllCharts();
            }).catch(error => {
                console.log('Charts not available:', error);
                // Hide chart containers if charts fail to load
                document.querySelectorAll('.chart-container').forEach(container => {
                    container.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400 transition-colors duration-300"><p>Chart data will be displayed here</p></div>';
                });
            });
        });
    </script>
</body>
</html>

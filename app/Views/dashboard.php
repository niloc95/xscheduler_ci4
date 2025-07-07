
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XScheduler Dashboard - Material Design</title>
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Your built CSS -->
    <link href="<?= base_url('/build/assets/style.css') ?>" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        
        /* Material Web Components custom properties */
        :root {
            --md-sys-color-primary: rgb(59, 130, 246);
            --md-sys-color-on-primary: rgb(255, 255, 255);
            --md-sys-color-surface: rgb(255, 255, 255);
            --md-sys-color-on-surface: rgb(17, 24, 39);
            --md-sys-color-surface-variant: rgb(248, 250, 252);
            --md-sys-color-outline: rgb(229, 231, 235);
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
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Mobile Menu Backdrop -->
    <div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed top-0 left-0 z-40 w-64 h-screen bg-white material-shadow-lg">
        <div class="h-full px-3 py-4 overflow-y-auto">
            <!-- Logo -->
            <div class="flex items-center justify-between mb-6 p-2">
                <div class="flex items-center">
                    <md-icon-button class="text-blue-600">
                        <md-icon>schedule</md-icon>
                    </md-icon-button>
                    <span class="text-xl font-semibold text-gray-800 ml-2">XScheduler</span>
                </div>
                <md-icon-button id="closeSidebar" class="lg:hidden">
                    <md-icon>close</md-icon>
                </md-icon-button>
            </div>
            
            <!-- Navigation -->
            <md-list>
                <md-list-item href="#" class="mb-1">
                    <md-icon slot="start">dashboard</md-icon>
                    <div slot="headline">Dashboard</div>
                </md-list-item>
                
                <md-list-item href="#" class="mb-1">
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
    <div class="main-content p-4 lg:ml-64">
        <!-- Top Bar -->
        <div class="bg-white material-shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <md-icon-button id="menuToggle" class="lg:hidden mr-2">
                        <md-icon>menu</md-icon>
                    </md-icon-button>
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
                        <p class="text-gray-600">Welcome back, <?= $user['name'] ?? 'User' ?></p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Search -->
                    <div class="hidden md:block">
                        <md-outlined-text-field label="Search" type="search" class="w-64">
                            <md-icon slot="leading-icon">search</md-icon>
                        </md-outlined-text-field>
                    </div>
                    
                    <!-- Notifications -->
                    <md-icon-button>
                        <md-icon>notifications</md-icon>
                    </md-icon-button>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-2">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?>
                        </div>
                        <div class="hidden md:block">
                            <p class="text-sm font-medium text-gray-700"><?= $user['name'] ?? 'User' ?></p>
                            <p class="text-xs text-gray-500"><?= $user['role'] ?? 'Administrator' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Card 1 -->
            <md-outlined-card class="p-6 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-blue-100 text-sm">Total Users</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['total_users'] ?? 2345) ?></p>
                    </div>
                    <md-icon class="text-4xl text-blue-200">people</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1 text-green-300">trending_up</md-icon>
                    <span class="text-blue-100">+12% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 2 -->
            <md-outlined-card class="p-6 bg-gradient-to-r from-green-500 to-green-600 text-white">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-green-100 text-sm">Active Sessions</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['active_sessions'] ?? 1789) ?></p>
                    </div>
                    <md-icon class="text-4xl text-green-200">event</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1 text-green-300">trending_up</md-icon>
                    <span class="text-green-100">+8% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 3 -->
            <md-outlined-card class="p-6 bg-gradient-to-r from-orange-500 to-orange-600 text-white">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-orange-100 text-sm">Pending Tasks</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['pending_tasks'] ?? 456) ?></p>
                    </div>
                    <md-icon class="text-4xl text-orange-200">pending_actions</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1 text-red-300">trending_down</md-icon>
                    <span class="text-orange-100">-3% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 4 -->
            <md-outlined-card class="p-6 bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-purple-100 text-sm">Revenue</p>
                        <p class="text-3xl font-bold">$<?= number_format($stats['revenue'] ?? 12456) ?></p>
                    </div>
                    <md-icon class="text-4xl text-purple-200">attach_money</md-icon>
                </div>
                <div class="flex items-center text-sm">
                    <md-icon class="text-sm mr-1 text-green-300">trending_up</md-icon>
                    <span class="text-purple-100">+15% from last month</span>
                </div>
            </md-outlined-card>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- User Growth Chart -->
            <md-outlined-card class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">User Growth</h3>
                    <md-icon-button>
                        <md-icon>more_vert</md-icon>
                    </md-icon-button>
                </div>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </md-outlined-card>

            <!-- Activity Overview Chart -->
            <md-outlined-card class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Activity Overview</h3>
                    <md-icon-button>
                        <md-icon>more_vert</md-icon>
                    </md-icon-button>
                </div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </md-outlined-card>
        </div>

        <!-- Data Table -->
        <md-outlined-card class="p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Activities</h2>
                    <p class="text-gray-600 text-sm">Latest user activities and updates</p>
                </div>
                <md-filled-button>
                    <md-icon slot="icon">add</md-icon>
                    Add New
                </md-filled-button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
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
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm mr-3">
                                                <?= strtoupper(substr($activity['user_name'], 0, 2)) ?>
                                            </div>
                                            <?= esc($activity['user_name']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4"><?= esc($activity['activity']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="bg-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-100 text-<?= $activity['status'] === 'active' ? 'green' : ($activity['status'] === 'pending' ? 'orange' : 'red') ?>-800 text-xs font-medium px-2.5 py-0.5 rounded capitalize">
                                            <?= esc($activity['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><?= date('M j, Y', strtotime($activity['date'])) ?></td>
                                    <td class="px-6 py-4">
                                        <md-icon-button>
                                            <md-icon>edit</md-icon>
                                        </md-icon-button>
                                        <md-icon-button>
                                            <md-icon>delete</md-icon>
                                        </md-icon-button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm mr-3">JD</div>
                                        John Doe
                                    </div>
                                </td>
                                <td class="px-6 py-4">Scheduled meeting</td>
                                <td class="px-6 py-4">
                                    <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Active</span>
                                </td>
                                <td class="px-6 py-4">Jan 1, 2025</td>
                                <td class="px-6 py-4">
                                    <md-icon-button><md-icon>edit</md-icon></md-icon-button>
                                    <md-icon-button><md-icon>delete</md-icon></md-icon-button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </md-outlined-card>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <md-outlined-card class="p-6 text-center">
                <md-icon class="text-4xl text-blue-500 mb-4">event_available</md-icon>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Schedule Meeting</h3>
                <p class="text-gray-600 text-sm mb-4">Create a new meeting or appointment</p>
                <md-filled-button>
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
                    container.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>Chart data will be displayed here</p></div>';
                });
            });
        });
    </script>
</body>
</html>

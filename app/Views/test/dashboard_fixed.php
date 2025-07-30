<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XScheduler Dashboard</title>
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Your built CSS -->
    <link href="<?= base_url('/build/assets/style.css') ?>" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Layout Grid */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 256px 1fr;
            min-height: 100vh;
        }
        
        @media (max-width: 1024px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar-mobile {
                position: fixed;
                top: 0;
                left: 0;
                width: 256px;
                height: 100vh;
                background: white;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar-mobile.open {
                transform: translateX(0);
            }
            
            .sidebar-desktop {
                display: none;
            }
        }
        
        @media (min-width: 1024px) {
            .sidebar-mobile {
                display: none;
            }
        }
        
        /* Material shadows */
        .material-shadow {
            box-shadow: 0px 2px 4px -1px rgba(0, 0, 0, 0.2), 0px 4px 5px 0px rgba(0, 0, 0, 0.14), 0px 1px 10px 0px rgba(0, 0, 0, 0.12);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Backdrop -->
    <div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

    <div class="dashboard-layout">
        <!-- Sidebar Desktop -->
        <aside class="sidebar-desktop bg-white material-shadow overflow-y-auto">
            <div class="p-4">
                <!-- Logo -->
                <div class="flex items-center mb-8">
                    <span class="material-symbols-outlined text-blue-600 text-2xl mr-2">schedule</span>
                    <span class="text-xl font-semibold text-gray-800">XScheduler</span>
                </div>
                
                <!-- Navigation -->
                <nav class="space-y-2">
                    <a href="<?= base_url('dashboard') ?>" class="flex items-center p-3 text-blue-600 bg-blue-50 rounded-lg border-r-4 border-blue-500">
                        <span class="material-symbols-outlined mr-3">dashboard</span>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">event</span>
                        <span>Schedule</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">people</span>
                        <span>Users</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">analytics</span>
                        <span>Analytics</span>
                    </a>
                    
                    <div class="my-4 border-t border-gray-200"></div>
                    
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">settings</span>
                        <span>Settings</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">help</span>
                        <span>Help</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Mobile Sidebar -->
        <aside id="mobileSidebar" class="sidebar-mobile bg-white material-shadow overflow-y-auto">
            <div class="p-4">
                <!-- Logo & Close -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center">
                        <span class="material-symbols-outlined text-blue-600 text-2xl mr-2">schedule</span>
                        <span class="text-xl font-semibold text-gray-800">XScheduler</span>
                    </div>
                    <button id="closeSidebar" class="p-2 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                
                <!-- Navigation (same as desktop) -->
                <nav class="space-y-2">
                    <a href="<?= base_url('dashboard') ?>" class="flex items-center p-3 text-blue-600 bg-blue-50 rounded-lg border-r-4 border-blue-500">
                        <span class="material-symbols-outlined mr-3">dashboard</span>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">event</span>
                        <span>Schedule</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">people</span>
                        <span>Users</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">analytics</span>
                        <span>Analytics</span>
                    </a>
                    
                    <div class="my-4 border-t border-gray-200"></div>
                    
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">settings</span>
                        <span>Settings</span>
                    </a>
                    <a href="#" class="flex items-center p-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3">help</span>
                        <span>Help</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="overflow-y-auto">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b p-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <button id="menuToggle" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 mr-4">
                            <span class="material-symbols-outlined">menu</span>
                        </button>
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
                            <p class="text-gray-600">Welcome back, <?= esc($user['name']) ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- User Info with Dropdown -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100">
                                <div class="text-right hidden md:block">
                                    <p class="text-sm font-medium text-gray-700"><?= esc($user['name']) ?></p>
                                    <p class="text-xs text-gray-500 capitalize"><?= esc($user['role']) ?></p>
                                </div>
                                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($user['name'], 0, 2)) ?>
                                </div>
                                <span class="material-symbols-outlined text-gray-400">expand_more</span>
                            </button>
                            
                            <!-- User Dropdown Menu -->
                            <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50">
                                <div class="py-2">
                                    <div class="px-4 py-2 border-b border-gray-100">
                                        <p class="text-sm font-medium text-gray-900"><?= esc($user['name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= esc($user['email']) ?></p>
                                    </div>
                                    <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <span class="material-symbols-outlined mr-3 text-gray-500">person</span>
                                        Profile Settings
                                    </a>
                                    <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <span class="material-symbols-outlined mr-3 text-gray-500">lock</span>
                                        Change Password
                                    </a>
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <a href="<?= base_url('auth/logout') ?>" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <span class="material-symbols-outlined mr-3 text-red-500">logout</span>
                                            Sign Out
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Users -->
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-blue-100 text-sm">Total Users</p>
                                <p class="text-3xl font-bold"><?= number_format($stats['total_users'] ?? 0) ?></p>
                            </div>
                            <span class="material-symbols-outlined text-4xl text-blue-200">people</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="material-symbols-outlined text-sm mr-1 text-green-300">trending_up</span>
                            <span class="text-blue-100">Active users</span>
                        </div>
                    </div>

                    <!-- Active Sessions -->
                    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-green-100 text-sm">Upcoming Appointments</p>
                                <p class="text-3xl font-bold"><?= number_format($stats['active_sessions'] ?? 0) ?></p>
                            </div>
                            <span class="material-symbols-outlined text-4xl text-green-200">event</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="material-symbols-outlined text-sm mr-1 text-green-300">trending_up</span>
                            <span class="text-green-100">Scheduled</span>
                        </div>
                    </div>

                    <!-- Today's Tasks -->
                    <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-orange-100 text-sm">Today's Schedule</p>
                                <p class="text-3xl font-bold"><?= number_format($stats['pending_tasks'] ?? 0) ?></p>
                            </div>
                            <span class="material-symbols-outlined text-4xl text-orange-200">today</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="material-symbols-outlined text-sm mr-1 text-orange-300">schedule</span>
                            <span class="text-orange-100">Today</span>
                        </div>
                    </div>

                    <!-- Revenue -->
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-purple-100 text-sm">Monthly Revenue</p>
                                <p class="text-3xl font-bold">$<?= number_format($stats['revenue'] ?? 0, 2) ?></p>
                            </div>
                            <span class="material-symbols-outlined text-4xl text-purple-200">attach_money</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="material-symbols-outlined text-sm mr-1 text-green-300">trending_up</span>
                            <span class="text-purple-100">This month</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800">Recent Activities</h2>
                            <p class="text-gray-600 text-sm">Latest updates and changes</p>
                        </div>
                        <span class="text-sm text-gray-500"><?= count($recent_activities ?? []) ?> activities</span>
                    </div>
                    
                    <?php if (!empty($recent_activities)): ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="flex items-center space-x-4 p-4 border border-gray-100 rounded-lg">
                                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium">
                                        <?= strtoupper(substr($activity['user_name'] ?? 'U', 0, 2)) ?>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900"><?= esc($activity['user_name'] ?? 'Unknown User') ?></p>
                                        <p class="text-sm text-gray-500"><?= esc($activity['activity'] ?? 'No activity') ?></p>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($activity['date'] ?? 'now')) ?>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?= ($activity['status'] ?? '') === 'active' ? 'bg-green-100 text-green-800' : 
                                            (($activity['status'] ?? '') === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= ucfirst($activity['status'] ?? 'unknown') ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-gray-400 text-6xl">inbox</span>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No activities yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Start using the system to see activities here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        // Mobile menu functionality
        const menuToggle = document.getElementById('menuToggle');
        const closeSidebar = document.getElementById('closeSidebar');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const backdrop = document.getElementById('backdrop');

        function openSidebar() {
            mobileSidebar.classList.add('open');
            backdrop.classList.remove('hidden');
        }

        function closeSidebarFn() {
            mobileSidebar.classList.remove('open');
            backdrop.classList.add('hidden');
        }

        menuToggle?.addEventListener('click', openSidebar);
        closeSidebar?.addEventListener('click', closeSidebarFn);
        backdrop?.addEventListener('click', closeSidebarFn);

        // User dropdown functionality
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');

        function toggleUserDropdown() {
            userDropdown.classList.toggle('hidden');
        }

        function closeUserDropdown() {
            userDropdown.classList.add('hidden');
        }

        userMenuButton?.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleUserDropdown();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', closeUserDropdown);
        
        // Prevent dropdown from closing when clicking inside it
        userDropdown?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    </script>
</body>
</html>

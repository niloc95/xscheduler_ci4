<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSchedulr Dashboard - Real Data</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-2xl font-bold text-gray-900">WebSchedulr Dashboard</h1>
                            <p class="text-sm text-gray-500 mt-1">Real-time data from your database</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900"><?= esc($user['name']) ?></p>
                            <p class="text-xs text-gray-500"><?= esc($user['role']) ?></p>
                        </div>
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                            <?= strtoupper(substr($user['name'], 0, 2)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        .gradient-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .gradient-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .gradient-orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .gradient-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }
        
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

    <!-- Mobile Backdrop -->
    <div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed top-0 left-0 z-40 w-64 h-screen bg-white card-shadow">
        <div class="h-full px-3 py-4 overflow-y-auto">
            <!-- Logo -->
            <div class="flex items-center justify-between mb-6 p-2">
                <div class="flex items-center">
                    <span class="material-icons text-blue-600 text-2xl mr-2">schedule</span>
                    <span class="text-xl font-semibold text-gray-800">WebSchedulr</span>
                </div>
                <button id="closeSidebar" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <!-- Navigation -->
            <nav class="space-y-2">
                <a href="<?= base_url('dashboard') ?>" class="flex items-center p-2 text-gray-900 rounded-lg bg-blue-50 border-r-4 border-blue-500">
                    <span class="material-icons text-blue-500">dashboard</span>
                    <span class="ml-3 font-medium">Dashboard</span>
                </a>
                <a href="#" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-gray-100">
                    <span class="material-icons text-gray-500">event</span>
                    <span class="ml-3">Schedule</span>
                </a>
                <a href="#" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-gray-100">
                    <span class="material-icons text-gray-500">people</span>
                    <span class="ml-3">Users</span>
                </a>
                <a href="#" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-gray-100">
                    <span class="material-icons text-gray-500">analytics</span>
                    <span class="ml-3">Analytics</span>
                </a>
                <a href="#" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-gray-100">
                    <span class="material-icons text-gray-500">notifications</span>
                    <span class="ml-3">Notifications</span>
                </a>
                <hr class="my-4 border-gray-200">
                <a href="#" class="flex items-center p-2 text-gray-700 rounded-lg hover:bg-gray-100">
                    <span class="material-icons text-gray-500">settings</span>
                    <span class="ml-3">Settings</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content md:ml-64 p-4">
        <!-- Top Bar -->
        <div class="bg-white card-shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <button id="menuToggle" class="md:hidden p-2 rounded-lg hover:bg-gray-100 mr-3">
                        <span class="material-icons">menu</span>
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                        <p class="text-gray-600">Welcome back, <?= esc($user['name'] ?? 'User') ?>!</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Search -->
                    <div class="hidden md:flex relative">
                        <input type="text" placeholder="Search..." 
                               class="bg-gray-50 border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <span class="material-icons absolute right-3 top-2 text-gray-400">search</span>
                    </div>
                    
                    <!-- Notifications -->
                    <button class="relative p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg">
                        <span class="material-icons">notifications</span>
                        <span class="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full"></span>
                    </button>
                    
                    <!-- User Info -->
                    <div class="flex items-center space-x-2">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?>
                        </div>
                        <div class="hidden md:block">
                            <p class="text-sm font-medium text-gray-700"><?= esc($user['name'] ?? 'User') ?></p>
                            <p class="text-xs text-gray-500"><?= esc($user['role'] ?? 'Administrator') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Total Users -->
            <div class="gradient-blue text-white rounded-lg card-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Users</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['total_users'] ?? 0) ?></p>
                    </div>
                    <span class="material-icons text-4xl text-blue-200">people</span>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="material-icons text-green-300 text-sm mr-1">trending_up</span>
                    <span class="text-blue-100">+12% from last month</span>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="gradient-green text-white rounded-lg card-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Active Sessions</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['active_sessions'] ?? 0) ?></p>
                    </div>
                    <span class="material-icons text-4xl text-green-200">event</span>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="material-icons text-green-300 text-sm mr-1">trending_up</span>
                    <span class="text-green-100">+8% from last month</span>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="gradient-orange text-white rounded-lg card-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Pending Tasks</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['pending_tasks'] ?? 0) ?></p>
                    </div>
                    <span class="material-icons text-4xl text-orange-200">pending_actions</span>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="material-icons text-red-300 text-sm mr-1">trending_down</span>
                    <span class="text-orange-100">-3% from last month</span>
                </div>
            </div>

            <!-- Revenue -->
            <div class="gradient-purple text-white rounded-lg card-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Revenue</p>
                        <p class="text-3xl font-bold">$<?= number_format($stats['revenue'] ?? 0) ?></p>
                    </div>
                    <span class="material-icons text-4xl text-purple-200">attach_money</span>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="material-icons text-green-300 text-sm mr-1">trending_up</span>
                    <span class="text-purple-100">+15% from last month</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- User Growth Chart -->
            <div class="bg-white card-shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">User Growth</h3>
                    <button class="p-2 hover:bg-gray-100 rounded-lg">
                        <span class="material-icons text-gray-400">more_vert</span>
                    </button>
                </div>
                <div class="h-64 bg-gray-50 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <span class="material-icons text-6xl text-gray-300 mb-2">trending_up</span>
                        <p class="text-gray-500">Chart will load here</p>
                        <p class="text-sm text-gray-400">Growth: +24% this month</p>
                    </div>
                </div>
            </div>

            <!-- Activity Overview -->
            <div class="bg-white card-shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Activity Overview</h3>
                    <button class="p-2 hover:bg-gray-100 rounded-lg">
                        <span class="material-icons text-gray-400">more_vert</span>
                    </button>
                </div>
                <div class="h-64 bg-gray-50 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <span class="material-icons text-6xl text-gray-300 mb-2">donut_small</span>
                        <p class="text-gray-500">Activity chart will load here</p>
                        <p class="text-sm text-gray-400">Most active: 2-4 PM</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities Table -->
        <div class="bg-white card-shadow rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Activities</h2>
                    <p class="text-gray-600 text-sm mt-1">Latest user activities and updates</p>
                </div>
                <button class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors flex items-center">
                    <span class="material-icons text-sm mr-2">add</span>
                    Add New
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
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
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                                                <?= strtoupper(substr($activity['user_name'], 0, 2)) ?>
                                            </div>
                                            <span class="font-medium text-gray-900"><?= esc($activity['user_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700"><?= esc($activity['activity']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php 
                                        $statusClass = $activity['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                                      ($activity['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        ?>
                                        <span class="<?= $statusClass ?> text-xs font-medium px-2.5 py-0.5 rounded-full">
                                            <?= ucfirst(esc($activity['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600"><?= date('M j, Y', strtotime($activity['date'])) ?></td>
                                    <td class="px-6 py-4">
                                        <button class="text-blue-600 hover:text-blue-800 mr-3">
                                            <span class="material-icons text-sm">edit</span>
                                        </button>
                                        <button class="text-red-600 hover:text-red-800">
                                            <span class="material-icons text-sm">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    <span class="material-icons text-4xl text-gray-300 mb-2">inbox</span>
                                    <p>No recent activities</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white card-shadow rounded-lg p-6 text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-icons text-2xl text-blue-600">event_available</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Schedule Meeting</h3>
                <p class="text-gray-600 text-sm mb-4">Create a new meeting or appointment</p>
                <button class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                    Schedule
                </button>
            </div>

            <div class="bg-white card-shadow rounded-lg p-6 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-icons text-2xl text-green-600">group_add</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Add User</h3>
                <p class="text-gray-600 text-sm mb-4">Invite a new user to the system</p>
                <button class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors">
                    Add User
                </button>
            </div>

            <div class="bg-white card-shadow rounded-lg p-6 text-center">
                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-icons text-2xl text-purple-600">assessment</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">View Reports</h3>
                <p class="text-gray-600 text-sm mb-4">Generate detailed analytics reports</p>
                <button class="bg-purple-500 text-white px-6 py-2 rounded-lg hover:bg-purple-600 transition-colors">
                    Reports
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
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

        if (menuToggle) menuToggle.addEventListener('click', openSidebar);
        if (closeSidebar) closeSidebar.addEventListener('click', closeSidebarFn);
        if (backdrop) backdrop.addEventListener('click', closeSidebarFn);

        // Optional: Load your main.js if it exists
        const mainScript = document.createElement('script');
        mainScript.src = '<?= base_url("build/assets/main.js") ?>';
        mainScript.onerror = function() {
            console.log('Main script not found, continuing without it');
        };
        document.head.appendChild(mainScript);
    </script>
</body>
</html>

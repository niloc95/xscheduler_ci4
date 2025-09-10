<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Dashboard - WebSchedulr</title>
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Material Tailwind CSS -->
    <script src="https://unpkg.com/@material-tailwind/html@latest/scripts/ripple.js"></script>
    
    <!-- Tailwind CSS (your existing build) -->
    <link href="<?= base_url('/build/assets/app.css') ?>" rel="stylesheet">
    
    <style>
        /* Custom styles for Material Design shadows */
        .material-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .material-shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Sidebar -->
    <aside class="fixed top-0 left-0 z-40 w-64 h-screen bg-white material-shadow-lg">
        <div class="h-full px-3 py-4 overflow-y-auto">
            <!-- Logo -->
            <div class="flex items-center mb-5 p-2">
                <span class="material-icons text-blue-600 text-2xl mr-3">schedule</span>
                <span class="text-xl font-semibold text-gray-800">WebSchedulr</span>
            </div>
            
            <!-- Navigation -->
            <ul class="space-y-2 font-medium">
                <li>
                    <a href="#" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <span class="material-icons text-gray-500">dashboard</span>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <span class="material-icons text-gray-500">event</span>
                        <span class="ml-3">Schedule</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <span class="material-icons text-gray-500">people</span>
                        <span class="ml-3">Users</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <span class="material-icons text-gray-500">analytics</span>
                        <span class="ml-3">Analytics</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <span class="material-icons text-gray-500">settings</span>
                        <span class="ml-3">Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="p-4 ml-64">
        <!-- Top Bar -->
        <div class="bg-white material-shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
                    <p class="text-gray-600">Welcome back to WebSchedulr</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Search -->
                    <div class="relative">
                        <input type="text" placeholder="Search..." 
                               class="bg-gray-50 border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="material-icons absolute right-3 top-2 text-gray-400">search</span>
                    </div>
                    
                    <!-- Notifications -->
                    <button class="relative p-2 text-gray-600 hover:text-gray-800">
                        <span class="material-icons">notifications</span>
                        <span class="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full"></span>
                    </button>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-2">
                        <img src="https://ui-avatars.com/api/?name=User&background=3b82f6&color=fff" 
                             alt="User" class="w-8 h-8 rounded-full">
                        <span class="text-gray-700">John Doe</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Card 1 -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg material-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100">Total Users</p>
                        <p class="text-3xl font-semibold">2,345</p>
                    </div>
                    <span class="material-icons text-4xl text-blue-200">people</span>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="material-icons text-green-300 text-sm">trending_up</span>
                    <span class="text-blue-100 text-sm ml-1">+12% from last month</span>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg material-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">Active Sessions</p>
                        <p class="text-3xl font-semibold">1,789</p>
                    </div>
                    <span class="material-icons text-4xl text-green-200">event</span>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="material-icons text-green-300 text-sm">trending_up</span>
                    <span class="text-green-100 text-sm ml-1">+8% from last month</span>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-lg material-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100">Pending Tasks</p>
                        <p class="text-3xl font-semibold">456</p>
                    </div>
                    <span class="material-icons text-4xl text-orange-200">pending_actions</span>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="material-icons text-red-300 text-sm">trending_down</span>
                    <span class="text-orange-100 text-sm ml-1">-3% from last month</span>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg material-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100">Revenue</p>
                        <p class="text-3xl font-semibold">$12,456</p>
                    </div>
                    <span class="material-icons text-4xl text-purple-200">attach_money</span>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="material-icons text-green-300 text-sm">trending_up</span>
                    <span class="text-purple-100 text-sm ml-1">+15% from last month</span>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white material-shadow rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Recent Activities</h2>
                <button class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                    <span class="material-icons text-sm mr-1">add</span>
                    Add New
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">User</th>
                            <th scope="col" class="px-6 py-3">Activity</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3">Date</th>
                            <th scope="col" class="px-6 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                <div class="flex items-center">
                                    <img src="https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=fff" 
                                         alt="User" class="w-8 h-8 rounded-full mr-3">
                                    John Doe
                                </div>
                            </td>
                            <td class="px-6 py-4">Scheduled meeting</td>
                            <td class="px-6 py-4">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Active</span>
                            </td>
                            <td class="px-6 py-4">2025-01-01</td>
                            <td class="px-6 py-4">
                                <button class="text-blue-600 hover:text-blue-800">
                                    <span class="material-icons text-sm">edit</span>
                                </button>
                            </td>
                        </tr>
                        <!-- Add more rows as needed -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Chart 1 -->
            <div class="bg-white material-shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">User Growth</h3>
                <div class="h-64 bg-gray-100 rounded flex items-center justify-center">
                    <p class="text-gray-500">Chart placeholder - integrate with Chart.js or similar</p>
                </div>
            </div>

            <!-- Chart 2 -->
            <div class="bg-white material-shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Activity Overview</h3>
                <div class="h-64 bg-gray-100 rounded flex items-center justify-center">
                    <p class="text-gray-500">Chart placeholder - integrate with Chart.js or similar</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Material Web Components JavaScript -->
    <script type="module">
        // Initialize Material Design ripple effects
        if (window.mdc) {
            // Initialize any Material Web Components here
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en" class="transition-colors duration-300">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xScheduler Dashboard - Material Design</title>

    <!-- Material Symbols (for md-icon glyphs) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,opsz@20..48,FILL@0..1,GRAD@-50..200&display=swap" rel="stylesheet">
    <!-- Roboto font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Built CSS -->
    <link href="<?= base_url('/build/assets/style.css') ?>" rel="stylesheet">

    <!-- Prevent flash of unstyled content (apply dark early, using xs-theme key) -->
    <script>
        (function() {
            try {
                const stored = localStorage.getItem('xs-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = stored ? stored === 'dark' : prefersDark;
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {
                // Fallback to media query
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

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
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300">

    <?= $this->include('components/admin-sidebar', ['current_page' => 'dashboard']) ?>

    <!-- Main Content -->
    <div class="main-content p-4 lg:ml-72">
        <!-- Top Bar -->
        <div class="bg-white dark:bg-gray-800 material-shadow rounded-lg p-4 mb-6 transition-colors duration-300">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <button id="menuToggle" class="lg:hidden mr-2 p-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors duration-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Dashboard</h1>
                        <p class="text-gray-600 dark:text-gray-400 transition-colors duration-300">Welcome back, <?= $user['name'] ?? 'User' ?></p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Dark Mode Toggle -->
                    <?= $this->include('components/dark-mode-toggle') ?>
                    
                    <!-- Search -->
                    <div class="hidden md:block relative">
                        <div class="relative">
                            <input type="search" 
                                   placeholder="Search users, appointments..." 
                                   class="w-80 h-12 pl-10 pr-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors duration-300 text-sm leading-relaxed"
                                   id="dashboardSearch">
                            <svg class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Notifications -->
                    <md-icon-button class="text-gray-600 dark:text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.73 21a2 2 0 01-3.46 0"></path>
                        </svg>
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
            <!-- Card 1 - Total Users -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg" style="background-color: var(--md-sys-color-primary);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Total Users</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['total_users'] ?? 2345) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <svg class="w-4 h-4 mr-1 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span class="opacity-80">+12% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 2 - Active Appointments -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg" style="background-color: var(--md-sys-color-secondary); color: var(--md-sys-color-on-surface);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Active Appointments</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['active_sessions'] ?? 1789) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <svg class="w-4 h-4 mr-1 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span class="opacity-80">+8% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 3 - Pending Requests -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg" style="background-color: var(--md-sys-color-tertiary);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Pending Requests</p>
                        <p class="text-3xl font-bold"><?= number_format($stats['pending_tasks'] ?? 456) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <svg class="w-4 h-4 mr-1 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    </svg>
                    <span class="opacity-80">-3% from last month</span>
                </div>
            </md-outlined-card>

            <!-- Card 4 - Monthly Revenue -->
            <md-outlined-card class="p-6 text-white transition-colors duration-300 rounded-lg" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="opacity-80 text-sm">Monthly Revenue</p>
                        <p class="text-3xl font-bold">$<?= number_format($stats['revenue'] ?? 12456) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex items-center text-sm">
                    <svg class="w-4 h-4 mr-1 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span class="opacity-80">+15% from last month</span>
                </div>
            </md-outlined-card>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- User Growth Chart -->
            <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">User Growth</h3>
                    <button class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                        </svg>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </md-outlined-card>

            <!-- Activity Overview Chart -->
            <md-outlined-card class="p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Activity Overview</h3>
                    <button class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                        </svg>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </md-outlined-card>
        </div>

        <!-- Data Table -->
        <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg material-shadow">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">Recent Activities</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Latest user activities and updates</p>
                </div>
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors duration-200 w-full sm:w-auto justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
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
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
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
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
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
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 transition-colors duration-300">
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
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button class="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 transition-colors duration-300">
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
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            <button class="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg">
                <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">Schedule Meeting</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Create a new meeting or appointment</p>
                <md-filled-button>
                    <svg slot="icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Schedule
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg">
                <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">Add User</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Invite a new user to the system</p>
                <md-filled-button>
                    <svg slot="icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Add User
                </md-filled-button>
            </md-outlined-card>

            <md-outlined-card class="p-6 text-center bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg">
                <div class="w-16 h-16 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-purple-500 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2 transition-colors duration-300">View Reports</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 transition-colors duration-300">Generate detailed analytics reports</p>
                <md-filled-button>
                    <svg slot="icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Reports
                </md-filled-button>
            </md-outlined-card>
        </div>
    </div>
    <!-- Material Web Components -->
    <script src="<?= base_url('/build/assets/materialWeb.js') ?>"></script>
    <!-- Dark Mode manager as ES module (built file exports a default) -->
    <script type="module" src="<?= base_url('/build/assets/dark-mode.js') ?>"></script>
    <script src="<?= base_url('/build/assets/main.js') ?>"></script>

    <!-- Custom JavaScript -->
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

            // Mobile menu functionality
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('backdrop');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.add('open');
                    if (backdrop) {
                        backdrop.classList.remove('hidden');
                    }
                });
            }
        });

        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            import('<?= base_url('/build/assets/charts.js') ?>').then(module => {
                if (module?.default?.initAllCharts) module.default.initAllCharts();
            }).catch(error => {
                console.log('Charts not available:', error);
                document.querySelectorAll('.chart-container').forEach(container => {
                    container.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400 transition-colors duration-300"><p>Chart data will be displayed here</p></div>';
                });
            });
        });
    </script>
    
    <div class="lg:ml-72">
        <?= $this->include('components/footer') ?>
    </div>
</body>
</html>

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
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                <dd class="text-3xl font-semibold text-gray-900"><?= number_format($stats['total_users']) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Active Sessions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Upcoming Appointments</dt>
                                <dd class="text-3xl font-semibold text-gray-900"><?= number_format($stats['active_sessions']) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Today's Appointments -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Today's Schedule</dt>
                                <dd class="text-3xl font-semibold text-gray-900"><?= number_format($stats['pending_tasks']) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Monthly Revenue</dt>
                                <dd class="text-3xl font-semibold text-gray-900">$<?= number_format($stats['revenue'], 2) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Activities</h3>
                        <span class="text-sm text-gray-500"><?= count($recent_activities) ?> activities</span>
                    </div>
                    
                    <?php if (!empty($recent_activities)): ?>
                        <div class="flow-root">
                            <ul class="divide-y divide-gray-200">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <li class="py-4">
                                        <div class="flex items-center space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-sm font-medium text-gray-700">
                                                    <?= strtoupper(substr($activity['user_name'], 0, 2)) ?>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    <?= esc($activity['user_name']) ?>
                                                </p>
                                                <p class="text-sm text-gray-500 truncate">
                                                    <?= esc($activity['activity']) ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0 text-sm text-gray-500">
                                                <?= date('M j, Y', strtotime($activity['date'])) ?>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <?php
                                                $badgeColor = 'gray';
                                                if ($activity['status'] === 'active') $badgeColor = 'green';
                                                elseif ($activity['status'] === 'pending') $badgeColor = 'yellow';
                                                elseif ($activity['status'] === 'cancelled') $badgeColor = 'red';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= $badgeColor ?>-100 text-<?= $badgeColor ?>-800 capitalize">
                                                    <?= esc($activity['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No activities yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Database not configured or no data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- API Test Buttons -->
            <div class="mt-8 bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">API Endpoints (Real Data)</h3>
                <div class="flex flex-wrap gap-4">
                    <button onclick="testAPI('/dashboard/api')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Test Stats API
                    </button>
                    <button onclick="testAPI('/dashboard/charts')" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Test Charts API
                    </button>
                    <button onclick="testAPI('/dashboard/analytics')" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                        Test Analytics API
                    </button>
                    <button onclick="testAPI('/dashboard/status')" class="bg-orange-500 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded">
                        Test Database Status
                    </button>
                </div>
                <div id="apiResult" class="mt-4 p-4 bg-gray-100 rounded-md hidden">
                    <pre id="apiOutput" class="text-sm text-gray-800 whitespace-pre-wrap"></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function testAPI(endpoint) {
            const resultDiv = document.getElementById('apiResult');
            const outputPre = document.getElementById('apiOutput');
            
            try {
                const response = await fetch(endpoint);
                const data = await response.json();
                
                outputPre.textContent = `Endpoint: ${endpoint}\nStatus: ${response.status}\n\n${JSON.stringify(data, null, 2)}`;
                resultDiv.classList.remove('hidden');
            } catch (error) {
                outputPre.textContent = `Error testing ${endpoint}:\n${error.message}`;
                resultDiv.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>

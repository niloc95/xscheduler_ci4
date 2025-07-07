<?php

namespace App\Controllers;

class Dashboard extends BaseController
{
    public function index()
    {
        // Test if the method is being called
        try {
            // Mock data - replace with actual database queries
            $data = [
                'user' => [
                    'name' => 'John Doe',
                    'role' => 'Administrator',
                    'email' => 'john.doe@example.com'
                ],
                'stats' => [
                    'total_users' => 2345,
                    'active_sessions' => 1789,
                    'pending_tasks' => 456,
                    'revenue' => 12456
                ],
                'recent_activities' => [
                    [
                        'user_name' => 'John Doe',
                        'activity' => 'Scheduled meeting with client',
                        'status' => 'active',
                        'date' => '2025-01-01'
                    ],
                    [
                        'user_name' => 'Jane Smith',
                        'activity' => 'Updated project timeline',
                        'status' => 'pending',
                        'date' => '2024-12-31'
                    ],
                    [
                        'user_name' => 'Mike Johnson',
                        'activity' => 'Completed task review',
                        'status' => 'active',
                        'date' => '2024-12-30'
                    ],
                    [
                        'user_name' => 'Sarah Wilson',
                        'activity' => 'Created new user account',
                        'status' => 'active',
                        'date' => '2024-12-29'
                    ],
                    [
                        'user_name' => 'Tom Brown',
                        'activity' => 'Cancelled appointment',
                        'status' => 'cancelled',
                        'date' => '2024-12-28'
                    ]
                ]
            ];

            return view('dashboard_simple', $data);
        } catch (\Exception $e) {
            // If there's an error, return a simple message
            return "Dashboard Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine();
        }
    }

    public function api()
    {
        // API endpoint for AJAX requests
        $stats = [
            'total_users' => rand(2000, 3000),
            'active_sessions' => rand(1500, 2000),
            'pending_tasks' => rand(300, 600),
            'revenue' => rand(10000, 15000)
        ];

        return $this->response->setJSON($stats);
    }

    public function charts()
    {
        // Chart data API endpoint
        $chartData = [
            'userGrowth' => [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                'data' => [1200, 1400, 1600, 1800, 2100, 2345]
            ],
            'activity' => [
                'labels' => ['Active Sessions', 'Completed Tasks', 'Pending Tasks', 'Cancelled'],
                'data' => [1789, 856, 456, 123]
            ],
            'revenue' => [
                'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                'data' => [2800, 3200, 2900, 3300]
            ]
        ];

        return $this->response->setJSON($chartData);
    }

    public function test()
    {
        // Simple test endpoint
        return view('welcome_message');
    }

    public function simple()
    {
        // Very basic dashboard without complex assets
        $data = [
            'title' => 'XScheduler Dashboard',
            'message' => 'Dashboard is working!',
            'stats' => [
                'users' => 150,
                'sessions' => 45,
                'tasks' => 23
            ]
        ];
        
        return view('dashboard_test', $data);
    }
}

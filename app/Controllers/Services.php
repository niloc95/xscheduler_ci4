<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

class Services extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('permissions');
    }

    /**
     * Display services list
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // Check permissions - only admin and provider can access
        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Services & Categories',
            'current_page' => 'services',
            'services' => $this->getMockServices(),
            'categories' => $this->getMockCategories(),
            'user_role' => $currentRole,
            'user' => $currentUser,
            'stats' => $this->getServiceStats()
        ];

        return view('services/index', $data);
    }

    /**
     * Create new service
     */
    public function create()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $data = [
            'title' => 'Create Service',
            'current_page' => 'services',
            'categories' => $this->getMockCategories(),
            'providers' => $this->getMockProviders()
        ];

        return view('services/create', $data);
    }

    /**
     * Edit existing service
     */
    public function edit($serviceId = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        if (!$serviceId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Service not found');
        }

        $service = $this->getMockService($serviceId);

        $data = [
            'title' => 'Edit Service',
            'current_page' => 'services',
            'service' => $service,
            'categories' => $this->getMockCategories(),
            'providers' => $this->getMockProviders()
        ];

        return view('services/edit', $data);
    }

    /**
     * Categories management
     */
    public function categories()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $data = [
            'title' => 'Service Categories',
            'current_page' => 'services',
            'categories' => $this->getMockCategories()
        ];

        return view('services/categories', $data);
    }

    /**
     * Get mock services data
     */
    private function getMockServices()
    {
        return [
            [
                'id' => 1,
                'name' => 'Hair Cut & Style',
                'description' => 'Professional hair cutting and styling service',
                'category' => 'Hair Services',
                'duration' => 60,
                'price' => 35.00,
                'provider' => 'Sarah Johnson',
                'status' => 'active',
                'bookings_count' => 45
            ],
            [
                'id' => 2,
                'name' => 'Color Treatment',
                'description' => 'Full hair coloring and highlighting service',
                'category' => 'Hair Services',
                'duration' => 120,
                'price' => 85.00,
                'provider' => 'Sarah Johnson',
                'status' => 'active',
                'bookings_count' => 28
            ],
            [
                'id' => 3,
                'name' => 'Beard Trim',
                'description' => 'Professional beard trimming and shaping',
                'category' => 'Barber Services',
                'duration' => 30,
                'price' => 20.00,
                'provider' => 'Alex Brown',
                'status' => 'active',
                'bookings_count' => 62
            ],
            [
                'id' => 4,
                'name' => 'Facial Treatment',
                'description' => 'Relaxing facial with deep cleansing',
                'category' => 'Spa Services',
                'duration' => 75,
                'price' => 65.00,
                'provider' => 'Maria Garcia',
                'status' => 'active',
                'bookings_count' => 33
            ],
            [
                'id' => 5,
                'name' => 'Manicure',
                'description' => 'Complete nail care and polish',
                'category' => 'Nail Services',
                'duration' => 45,
                'price' => 25.00,
                'provider' => 'Lisa Chen',
                'status' => 'inactive',
                'bookings_count' => 15
            ]
        ];
    }

    /**
     * Get mock service by ID
     */
    private function getMockService($id)
    {
        $services = $this->getMockServices();
        foreach ($services as $service) {
            if ($service['id'] == $id) {
                return $service;
            }
        }
        return null;
    }

    /**
     * Get mock categories
     */
    private function getMockCategories()
    {
        return [
            [
                'id' => 1,
                'name' => 'Hair Services',
                'description' => 'All hair-related services',
                'services_count' => 8,
                'color' => '#3B82F6'
            ],
            [
                'id' => 2,
                'name' => 'Barber Services',
                'description' => 'Traditional barber services',
                'services_count' => 4,
                'color' => '#10B981'
            ],
            [
                'id' => 3,
                'name' => 'Spa Services',
                'description' => 'Relaxing spa treatments',
                'services_count' => 6,
                'color' => '#8B5CF6'
            ],
            [
                'id' => 4,
                'name' => 'Nail Services',
                'description' => 'Nail care and beauty',
                'services_count' => 5,
                'color' => '#F59E0B'
            ]
        ];
    }

    /**
     * Get mock providers
     */
    private function getMockProviders()
    {
        return [
            ['id' => 1, 'name' => 'Sarah Johnson', 'speciality' => 'Hair Styling'],
            ['id' => 2, 'name' => 'Alex Brown', 'speciality' => 'Barber Services'],
            ['id' => 3, 'name' => 'Maria Garcia', 'speciality' => 'Color Specialist'],
            ['id' => 4, 'name' => 'Lisa Chen', 'speciality' => 'Nail Technician']
        ];
    }

    /**
     * Get service statistics
     */
    private function getServiceStats()
    {
        return [
            'total_services' => 23,
            'active_services' => 20,
            'categories' => 4,
            'total_bookings' => 183,
            'avg_price' => 45.25
        ];
    }
}

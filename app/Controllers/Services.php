<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\CategoryModel;

class Services extends BaseController
{
    protected $userModel;
    protected $serviceModel;
    protected $categoryModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->categoryModel = new CategoryModel();
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

        // Fetch real data for dashboard while keeping view unchanged
        $services = $this->serviceModel->findWithRelations(100, 0);
        $categories = $this->categoryModel->withServiceCounts();
        $stats = $this->serviceModel->getStats();

        // Map stats to existing keys expected by the view
        $mappedStats = [
            'total_services' => $stats['total'] ?? 0,
            'active_services' => $stats['active'] ?? 0,
            'categories' => $stats['categories'] ?? 0,
            'total_bookings' => $stats['bookings'] ?? 0,
            'avg_price' => $stats['avg_price'] ?? 0,
        ];

        // Map services to keys used in the view without changing markup
        $viewServices = array_map(function ($s) {
            return [
                'id' => (int)$s['id'],
                'name' => $s['name'],
                'description' => $s['description'] ?? '',
                'category' => $s['category_name'] ?? 'Uncategorized',
                'duration' => (int)($s['duration_min'] ?? 0),
                'price' => isset($s['price']) ? (float)$s['price'] : 0,
                'provider' => $s['provider_names'] ?: '—',
                'status' => ((int)($s['active'] ?? 1)) === 1 ? 'active' : 'inactive',
                'bookings_count' => 0,
            ];
        }, $services);

        // Ensure category fields used in view exist
        $viewCategories = array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'description' => $c['description'] ?? '',
                'services_count' => (int)($c['services_count'] ?? 0),
                'color' => $c['color'] ?? '#3B82F6',
            ];
        }, $categories);

        $data = [
            'title' => 'Services & Categories',
            'current_page' => 'services',
            'services' => $viewServices,
            'categories' => $viewCategories,
            'user_role' => $currentRole,
            'user' => $currentUser,
            'stats' => $mappedStats,
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

        // Load real providers (role = provider only)
        $providers = $this->userModel->where('role', 'provider')->where('is_active', true)->orderBy('name','ASC')->findAll();
        $categories = $this->categoryModel->orderBy('name','ASC')->findAll();

        $data = [
            'title' => 'Create Service',
            'current_page' => 'services',
            'categories' => $categories,
            'providers' => $providers,
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

        $service = $this->serviceModel->find($serviceId);
        if (!$service) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Service not found');
        }

        $providers = $this->userModel->where('role', 'provider')->where('is_active', true)->orderBy('name','ASC')->findAll();
        $categories = $this->categoryModel->orderBy('name','ASC')->findAll();

        // Get currently linked providers
        $linked = $this->serviceModel->db->table('providers_services')
            ->where('service_id', $serviceId)->get()->getResultArray();
        $linkedProviders = array_map(fn($r) => (int)$r['provider_id'], $linked);

        $data = [
            'title' => 'Edit Service',
            'current_page' => 'services',
            'service' => $service,
            'categories' => $categories,
            'providers' => $providers,
            'linkedProviders' => $linkedProviders,
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

        $categories = $this->categoryModel->withServiceCounts();
        $data = [
            'title' => 'Service Categories',
            'current_page' => 'services',
            'categories' => $categories,
        ];

        return view('services/categories', $data);
    }

    /**
     * Create a category (AJAX)
     */
    public function storeCategory()
    {
        if (!session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }
        if (!has_role(['admin', 'provider'])) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        $name = trim((string)$this->request->getPost('name'));
        $description = $this->request->getPost('description');
        $color = $this->request->getPost('color') ?: '#3B82F6';

        if ($name === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Name is required']);
        }

        $id = $this->categoryModel->insert([
            'name' => $name,
            'description' => $description,
            'color' => $color,
            'active' => 1,
        ], true);
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'id' => (int)$id, 'name' => $name]);
        }
        return redirect()->back()->with('message', 'Category created');
    }

    /** Update category */
    public function updateCategory($id)
    {
        if (!session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }
        if (!has_role(['admin', 'provider'])) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }
        $data = [
            'name' => trim((string)$this->request->getPost('name')),
            'color' => $this->request->getPost('color') ?: '#3B82F6',
            'description' => $this->request->getPost('description') ?: null,
            'active' => (int)!!$this->request->getPost('active'),
        ];
        if ($data['name'] === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Name is required']);
        }
        $this->categoryModel->update((int)$id, $data);
        return $this->response->setJSON(['success' => true]);
    }

    /** Deactivate or delete a category (soft deactivate preferred) */
    public function deleteCategory($id)
    {
        if (!session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }
        if (!has_role(['admin', 'provider'])) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }
        // Prefer deactivate to maintain FK integrity
        $this->categoryModel->deactivate((int)$id);
        return $this->response->setJSON(['success' => true]);
    }

    /**
     * Store a new service (form POST)
     */
    public function store()
    {
        if (!session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }
        if (!has_role(['admin', 'provider'])) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        $input = $this->request->getPost();
        $serviceData = [
            'name' => trim($input['name'] ?? ''),
            'description' => $input['description'] ?? null,
            'duration_min' => (int)($input['duration_min'] ?? 0),
            'price' => $input['price'] !== '' ? (float)$input['price'] : null,
            'category_id' => $input['category_id'] ? (int)$input['category_id'] : null,
            'active' => isset($input['active']) ? (int)!!$input['active'] : 1,
        ];

        // Inline category creation if provided
        $newCategoryName = trim($input['new_category_name'] ?? '');
        if ($newCategoryName !== '') {
            $catId = $this->categoryModel->insert([
                'name' => $newCategoryName,
                'description' => $input['new_category_description'] ?? null,
                'color' => $input['new_category_color'] ?? '#3B82F6',
            ], true);
            $serviceData['category_id'] = $catId;
        }

        if (!$this->serviceModel->insert($serviceData)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'error' => 'Validation failed',
                    'details' => $this->serviceModel->errors()
                ]);
            }
            return redirect()->back()->with('error', 'Validation failed')->withInput();
        }

        $serviceId = (int)$this->serviceModel->getInsertID();
        $providerIds = $this->request->getPost('provider_ids') ?? [];
        if (is_string($providerIds)) {
            $providerIds = array_filter(array_map('intval', explode(',', $providerIds)));
        }
        $this->serviceModel->setProviders($serviceId, (array)$providerIds);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'id' => $serviceId]);
        }
        return redirect()->to('/services')->with('message', 'Service created');
    }

    /**
     * Update service
     */
    public function update($serviceId)
    {
        if (!session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }
        if (!has_role(['admin', 'provider'])) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        $input = $this->request->getPost();
        
        // Debug logging in development
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Service update - POST data: ' . json_encode($input));
            log_message('debug', 'Service update - Service ID: ' . $serviceId);
        }
        
        $serviceData = [
            'name' => trim($input['name'] ?? ''),
            'description' => $input['description'] ?? null,
            'duration_min' => (int)($input['duration_min'] ?? 0),
            'price' => $input['price'] !== '' ? (float)$input['price'] : null,
            'category_id' => $input['category_id'] ? (int)$input['category_id'] : null,
            'active' => isset($input['active']) ? (int)!!$input['active'] : 0,
        ];

        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Service update - Prepared data: ' . json_encode($serviceData));
        }

        $updateResult = $this->serviceModel->update((int)$serviceId, $serviceData);
        
        if (!$updateResult) {
            $errors = $this->serviceModel->errors();
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'Service update - Validation errors: ' . json_encode($errors));
            }
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'error' => 'Validation failed',
                    'details' => $errors
                ]);
            }
            return redirect()->back()->with('error', 'Validation failed')->withInput();
        }

        // Handle provider IDs
        $providerIds = $this->request->getPost('provider_ids') ?? [];
        if (is_array($providerIds)) {
            // FormData sends array correctly
            $providerIds = array_filter(array_map('intval', $providerIds));
        } elseif (is_string($providerIds)) {
            // Fallback for string format
            $providerIds = array_filter(array_map('intval', explode(',', $providerIds)));
        }
        
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Service update - Provider IDs: ' . json_encode($providerIds));
        }
        
        $this->serviceModel->setProviders((int)$serviceId, (array)$providerIds);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'id' => (int)$serviceId]);
        }
        return redirect()->to('/services')->with('message', 'Service updated');
    }

    /**
     * Delete service
     */
    public function delete($serviceId)
    {
        if (!session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }
        if (!has_role(['admin', 'provider'])) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        // Remove provider links first
        $this->serviceModel->db->table('providers_services')->delete(['service_id' => (int)$serviceId]);
        $this->serviceModel->delete((int)$serviceId);

        return $this->response->setJSON(['success' => true]);
    }
}

<?php

/**
 * =============================================================================
 * SERVICES CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Services.php
 * @description Manages the service catalog including services offered, their
 *              durations, pricing, and category organization.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /services                     : List all services
 * GET  /services/create              : Show service creation form
 * POST /services/store               : Create new service
 * GET  /services/edit/:hash          : Show edit form for service
 * POST /services/update/:hash        : Update existing service
 * POST /services/delete/:hash        : Soft delete service
 * GET  /services/categories          : List service categories
 * POST /services/categories/store    : Create new category
 * POST /services/categories/update   : Update category
 * POST /services/categories/delete   : Delete category
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Manages the service offerings that customers can book:
 * - Service definitions (name, description, duration, price)
 * - Category organization for service grouping
 * - Provider assignment (which providers offer which services)
 * - Active/inactive status management
 * 
 * SERVICE DATA:
 * -----------------------------------------------------------------------------
 * - Basic: Name, description, short description
 * - Pricing: Base price, currency (from settings)
 * - Duration: Service duration in minutes
 * - Category: Organizational grouping
 * - Providers: List of providers who can perform service
 * - Status: Active/inactive for booking availability
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Admin: Full CRUD on all services and categories
 * - Provider: Can manage own services only
 * - Staff/Customer: Read-only access
 * 
 * @see         app/Views/services/ for view templates
 * @see         app/Models/ServiceModel.php for data model
 * @see         app/Models/CategoryModel.php for categories
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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
     * Ensure the current user can manage categories.
     * Returns a redirect response if authentication is missing.
     */
    private function ensureCategoryAccess()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        return null;
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
    $activeTab = $this->request->getGet('tab');
    $activeTab = in_array($activeTab, ['services', 'categories'], true) ? $activeTab : 'services';

        // Fetch real data for dashboard while keeping view unchanged
        try {
            $services = $this->serviceModel->findWithRelations(100, 0);
        } catch (\Throwable $e) {
            log_message('error', 'Services: findWithRelations failed — ' . $e->getMessage());
            $services = $this->serviceModel->orderBy('created_at', 'DESC')->limit(100)->findAll();
        }

        try {
            $categories = $this->categoryModel->withServiceCounts();
        } catch (\Throwable $e) {
            log_message('error', 'Services: withServiceCounts failed — ' . $e->getMessage());
            $categories = $this->categoryModel->orderBy('name', 'ASC')->findAll();
        }

        try {
            $stats = $this->serviceModel->getStats();
        } catch (\Throwable $e) {
            log_message('error', 'Services: getStats failed — ' . $e->getMessage());
            $stats = ['total' => 0, 'active' => 0, 'categories' => 0, 'bookings' => 0, 'avg_price' => 0];
        }

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
                'category_id' => isset($s['category_id']) ? (int)$s['category_id'] : null,
                'duration' => (int)($s['duration_min'] ?? 0),
                'price' => isset($s['price']) ? (float)$s['price'] : 0,
                'provider' => ($s['provider_names'] ?? '') ?: '—',
                'status' => ((int)($s['active'] ?? 1)) === 1 ? 'active' : 'inactive',
                'bookings_count' => 0,
            ];
        }, $services);

        $filterCategory = $this->request->getGet('category');
        $filterStatus = $this->request->getGet('status');
        $filterQuery = trim((string)($this->request->getGet('q') ?? ''));

        $viewServices = array_values(array_filter($viewServices, static function ($service) use ($filterCategory, $filterStatus, $filterQuery) {
            if ($filterCategory !== null && $filterCategory !== '' && (int)$filterCategory !== (int)($service['category_id'] ?? 0)) {
                return false;
            }

            if ($filterStatus !== null && $filterStatus !== '' && strtolower($filterStatus) !== strtolower($service['status'])) {
                return false;
            }

            if ($filterQuery !== '') {
                $haystack = strtolower($service['name'] . ' ' . ($service['description'] ?? '') . ' ' . ($service['provider'] ?? ''));
                if (strpos($haystack, strtolower($filterQuery)) === false) {
                    return false;
                }
            }

            return true;
        }));

        // Ensure category fields used in view exist
        $viewCategories = array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'description' => $c['description'] ?? '',
                'services_count' => (int)($c['services_count'] ?? 0),
                'color' => $c['color'] ?? '#3B82F6',
                'active' => isset($c['active']) ? (int)$c['active'] : 1,
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
            'activeTab' => $activeTab,
            'filters' => [
                'q' => $filterQuery,
                'category' => $filterCategory,
                'status' => $filterStatus,
            ],
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
            // Shared form contract
            'action_url' => site_url('services/store'),
            'data' => [],
            'linkedProviders' => [],
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
            // Keep legacy variable for compatibility (if any)
            'service' => $service,
            'categories' => $categories,
            'providers' => $providers,
            'linkedProviders' => $linkedProviders,
            // Shared form contract
            'action_url' => site_url('services/update/' . (int)$serviceId),
            'data' => $service,
        ];

        return view('services/edit', $data);
    }

    /**
     * Categories management
     */
    public function categories()
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        return redirect()->to('/services?tab=categories');
    }

    /**
     * Render create category form.
     */
    public function createCategory()
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        return view('categories/create', [
            'title' => 'Create Category',
            'current_page' => 'services',
            'action_url' => site_url('services/categories'),
            'data' => [],
        ]);
    }

    /**
     * Render edit category form.
     */
    public function editCategory($id)
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        $category = $this->categoryModel->find((int)$id);
        if (!$category) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Category not found');
        }

        return view('categories/edit', [
            'title' => 'Edit Category',
            'current_page' => 'services',
            'action_url' => site_url('services/categories/update/' . (int)$id),
            'data' => $category,
        ]);
    }

    /**
     * Create a category (AJAX)
     */
    public function storeCategory()
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        $name = trim((string)$this->request->getPost('name'));
        $description = $this->request->getPost('description');
        $color = $this->request->getPost('color') ?: '#3B82F6';
        $active = $this->request->getPost('active');

        if ($name === '') {
            $errorMessage = 'Name is required';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => $errorMessage]);
            }
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        $categoryData = [
            'name' => $name,
            'description' => $description ?: null,
            'color' => $color,
            'active' => $active === null ? 1 : (int)!!$active,
        ];

        if (!$this->categoryModel->insert($categoryData, true)) {
            $errors = $this->categoryModel->errors();
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                ]);
            }

            return redirect()->back()->withInput()->with('error', 'Validation failed')->with('errors', $errors);
        }

        $id = (int)$this->categoryModel->getInsertID();

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Category created',
                'redirect' => '/services?tab=categories',
                'id' => (int)$id,
                'name' => $name
            ]);
        }

        return redirect()->to('/services?tab=categories')->with('message', 'Category created');
    }

    /** Update category */
    public function updateCategory($id)
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        $payload = [
            'name' => trim((string)$this->request->getPost('name')),
            'color' => $this->request->getPost('color') ?: '#3B82F6',
            'description' => $this->request->getPost('description') ?: null,
            'active' => (int)!!$this->request->getPost('active'),
        ];

        if ($payload['name'] === '') {
            $errorMessage = 'Name is required';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => $errorMessage]);
            }
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        if (!$this->categoryModel->update((int)$id, $payload)) {
            $errors = $this->categoryModel->errors();
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                ]);
            }

            return redirect()->back()->withInput()->with('error', 'Validation failed')->with('errors', $errors);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Category updated',
                'redirect' => '/services?tab=categories'
            ]);
        }

        return redirect()->to('/services?tab=categories')->with('message', 'Category updated');
    }

    /** Deactivate category */
    public function deactivateCategory($id)
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        $this->categoryModel->deactivate((int)$id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Category deactivated',
                'redirect' => '/services?tab=categories'
            ]);
        }

        return redirect()->to('/services?tab=categories')->with('message', 'Category deactivated');
    }

    /** Activate category */
    public function activateCategory($id)
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        $this->categoryModel->activate((int)$id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Category activated',
                'redirect' => '/services?tab=categories'
            ]);
        }

        return redirect()->to('/services?tab=categories')->with('message', 'Category activated');
    }

    /** Delete a category (hard delete) */
    public function deleteCategory($id)
    {
        if ($response = $this->ensureCategoryAccess()) {
            return $response;
        }

        // Clear category references for services before deleting.
        $this->serviceModel->where('category_id', (int)$id)->set('category_id', null)->update();

        if (!$this->categoryModel->delete((int)$id)) {
            $errors = $this->categoryModel->errors();
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Unable to delete category',
                    'errors' => $errors,
                ]);
            }

            return redirect()->back()->with('error', 'Unable to delete category')->with('errors', $errors ?? []);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Category deleted',
                'redirect' => '/services?tab=categories'
            ]);
        }

        return redirect()->to('/services?tab=categories')->with('message', 'Category deleted');
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
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->serviceModel->errors()
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
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Service created',
                'redirect' => '/services',
                'id' => $serviceId
            ]);
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
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
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
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Service updated',
                'redirect' => '/services'
            ]);
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

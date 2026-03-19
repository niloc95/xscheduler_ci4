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
    private string $providerServicePivotTable;

    public function __construct()
    {
        $this->userModel    = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->categoryModel = new CategoryModel(); // used for category dropdowns in service forms
        $this->providerServicePivotTable = $this->serviceModel->db->prefixTable('providers_services');
        helper('permissions');
    }

    /**
     * Display services list
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
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
            return redirect()->to(base_url('auth/login'));
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
            return redirect()->to(base_url('auth/login'));
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
        $linkedProviders = $this->serviceModel->getLinkedProviderIds((int) $serviceId);

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

        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Service store - POST data: ' . json_encode($input));
        }

        $serviceData = $this->buildServicePayload($input, true);

        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Service store - Prepared data: ' . json_encode($serviceData));
        }

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
            $errors = $this->serviceModel->errors();

            if (ENVIRONMENT === 'development') {
                log_message('debug', 'Service store - Validation errors: ' . json_encode($errors));
            }

            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ]);
            }
            return redirect()->back()->with('error', 'Validation failed')->with('errors', $errors)->withInput();
        }

        $serviceId = (int)$this->serviceModel->getInsertID();
        $providerIds = $this->extractPostedProviderIds($input);
        $this->serviceModel->setProviders($serviceId, (array)$providerIds);
        $assignedCount = count($providerIds);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => $assignedCount > 0
                    ? "Your service has been saved with {$assignedCount} provider(s)."
                    : 'Your service has been saved.',
                'redirect' => base_url('services'),
                'id' => $serviceId
            ]);
        }
        return redirect()->to(base_url('services'))->with(
            'message',
            $assignedCount > 0
                ? "Your service has been saved with {$assignedCount} provider(s)."
                : 'Your service has been saved.'
        );
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
        
        $serviceData = $this->buildServicePayload($input, false);

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
        $providerIds = $this->extractPostedProviderIds($input);
        
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'Service update - Provider IDs: ' . json_encode($providerIds));
        }
        
        $this->serviceModel->setProviders((int)$serviceId, (array)$providerIds);

        if (ENVIRONMENT === 'development') {
            $savedLinks = $this->serviceModel->db->table($this->providerServicePivotTable)
                ->select('provider_id')
                ->where('service_id', (int)$serviceId)
                ->orderBy('provider_id', 'ASC')
                ->get()
                ->getResultArray();
            log_message('debug', 'Service update - Saved provider links: ' . json_encode(array_column($savedLinks, 'provider_id')));
        }

        $assignedCount = count($providerIds);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => $assignedCount > 0
                    ? "Your changes have been saved with {$assignedCount} provider(s)."
                    : 'Your changes have been saved.',
                'redirect' => base_url('services')
            ]);
        }
        return redirect()->to(base_url('services'))->with(
            'message',
            $assignedCount > 0
                ? "Your changes have been saved with {$assignedCount} provider(s)."
                : 'Your changes have been saved.'
        );
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
        $this->serviceModel->db->table($this->providerServicePivotTable)->delete(['service_id' => (int)$serviceId]);
        $this->serviceModel->delete((int)$serviceId);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Service deleted', 'redirect' => base_url('services')]);
        }
        return redirect()->to(base_url('services'))->with('message', 'Service deleted');
    }

    private function buildServicePayload(array $input, bool $isCreate): array
    {
        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'description' => $input['description'] ?? null,
            'duration_min' => (int) ($input['duration_min'] ?? 0),
            'price' => ($input['price'] ?? '') !== '' ? (float) $input['price'] : null,
            'category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
            'active' => isset($input['active']) ? (int) !!$input['active'] : ($isCreate ? 1 : 0),
        ];
    }

    private function normalizeProviderIds($providerIds): array
    {
        if (is_array($providerIds)) {
            return array_values(array_unique(array_filter(array_map('intval', $providerIds))));
        }

        if (is_string($providerIds) && $providerIds !== '') {
            return array_values(array_unique(array_filter(array_map('intval', explode(',', $providerIds)))));
        }

        return [];
    }

    private function extractPostedProviderIds(array $input): array
    {
        // Standard PHP array field name: provider_ids[] => $_POST['provider_ids']
        if (array_key_exists('provider_ids', $input)) {
            return $this->normalizeProviderIds($input['provider_ids']);
        }

        // Fallback for environments that keep the bracketed key
        if (array_key_exists('provider_ids[]', $input)) {
            return $this->normalizeProviderIds($input['provider_ids[]']);
        }

        // Final fallback to direct request reads
        $fromRequest = $this->request->getPost('provider_ids');
        if ($fromRequest === null) {
            $fromRequest = $this->request->getPost('provider_ids[]');
        }

        return $this->normalizeProviderIds($fromRequest);
    }
}

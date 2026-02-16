<?php

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use App\Services\CustomerAppointmentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Customer Portal Controller
 * 
 * Public-facing controller for customers to view their appointment history.
 * Access is controlled by customer hash (no login required).
 * 
 * Routes:
 * - GET /my-appointments/{hash} - View appointments page
 * - GET /my-appointments/{hash}/data - JSON endpoint for SPA
 */
class CustomerPortalController extends BaseController
{
    protected CustomerModel $customers;
    protected CustomerAppointmentService $appointmentService;

    public function __construct()
    {
        $this->customers = new CustomerModel();
        $this->appointmentService = new CustomerAppointmentService();
    }

    /**
     * GET /my-appointments/{hash}
     * 
     * Display customer's appointment history page
     */
    public function index(string $hash)
    {
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return view('errors/html/error_404', [
                'message' => 'Customer not found. Please check your link and try again.'
            ]);
        }

        $customerId = (int) $customer['id'];

        // Get query parameters
        $tab = $this->request->getGet('tab') ?: 'upcoming';
        $page = max(1, (int) $this->request->getGet('page') ?: 1);

        // Build filters based on tab
        $filters = [];
        if ($tab === 'upcoming') {
            $filters['type'] = 'upcoming';
        } elseif ($tab === 'past') {
            $filters['type'] = 'past';
        }

        // Get appointment data
        $appointments = $this->appointmentService->getHistory($customerId, $filters, $page, 10);
        $stats = $this->appointmentService->getStats($customerId);
        $upcoming = $this->appointmentService->getUpcoming($customerId, 3);

        // Check if JSON requested
        $acceptsJson = stripos($this->request->getHeaderLine('Accept'), 'application/json') !== false;
        if ($this->request->isAJAX() || $acceptsJson) {
            return $this->response->setJSON([
                'success' => true,
                'customer' => [
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name'],
                    'email' => $customer['email'],
                ],
                'appointments' => $appointments,
                'stats' => $stats,
                'upcoming' => $upcoming,
            ]);
        }

        $context = [
            'customer' => $customer,
            'appointments' => $appointments,
            'stats' => $stats,
            'upcoming' => $upcoming,
            'currentTab' => $tab,
            'currentPage' => $page,
            'hash' => $hash,
        ];

        return view('public/my-appointments', ['context' => $context]);
    }

    /**
     * GET /my-appointments/{hash}/upcoming
     * 
     * Get upcoming appointments JSON
     */
    public function upcoming(string $hash): ResponseInterface
    {
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found'
            ]);
        }

        $limit = min(20, max(1, (int) $this->request->getGet('limit') ?: 5));
        $upcoming = $this->appointmentService->getUpcoming((int) $customer['id'], $limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $upcoming,
            'count' => count($upcoming),
        ]);
    }

    /**
     * GET /my-appointments/{hash}/history
     * 
     * Get appointment history JSON with pagination
     */
    public function history(string $hash): ResponseInterface
    {
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found'
            ]);
        }

        $page = max(1, (int) $this->request->getGet('page') ?: 1);
        $perPage = min(50, max(1, (int) $this->request->getGet('per_page') ?: 10));
        
        $filters = ['type' => 'past'];
        if ($status = $this->request->getGet('status')) {
            $filters['status'] = $status;
        }

        $history = $this->appointmentService->getHistory((int) $customer['id'], $filters, $page, $perPage);

        return $this->response->setJSON([
            'success' => true,
            ...$history,
        ]);
    }

    /**
     * GET /my-appointments/{hash}/autofill
     * 
     * Get customer autofill data for new booking
     */
    public function autofill(string $hash): ResponseInterface
    {
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found'
            ]);
        }

        $autofill = $this->appointmentService->getAutofillData((int) $customer['id']);
        
        // Remove internal ID from response
        unset($autofill['customer']['id']);
        $autofill['customer']['hash'] = $hash;

        return $this->response->setJSON([
            'success' => true,
            ...$autofill,
        ]);
    }
}

<?php

/**
 * =============================================================================
 * CUSTOMER APPOINTMENTS API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/CustomerAppointments.php
 * @description API for customer-specific appointment data including history,
 *              upcoming bookings, statistics, and autofill data.
 * 
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET  /api/customers/:id/appointments          : All appointments for customer
 * GET  /api/customers/:id/appointments/upcoming : Future appointments only
 * GET  /api/customers/:id/appointments/history  : Past appointments only
 * GET  /api/customers/:id/appointments/stats    : Appointment statistics
 * GET  /api/customers/:id/autofill              : Autofill data for booking
 * 
 * QUERY PARAMETERS:
 * -----------------------------------------------------------------------------
 * - page         : Page number (default: 1)
 * - per_page     : Items per page (default: 20, max: 100)
 * - status       : Filter by status (pending, confirmed, completed, cancelled)
 * - provider_id  : Filter by provider
 * - service_id   : Filter by service
 * - date_from    : Start date (YYYY-MM-DD)
 * - date_to      : End date (YYYY-MM-DD)
 * - type         : upcoming or past
 * 
 * STATS RESPONSE:
 * -----------------------------------------------------------------------------
 * {
 *   "total_appointments": 45,
 *   "completed": 40,
 *   "cancelled": 3,
 *   "no_shows": 2,
 *   "total_spent": 1250.00,
 *   "favorite_service": "Haircut",
 *   "favorite_provider": "John Doe"
 * }
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Supports customer management features:
 * - Customer profile appointment history tab
 * - Rebooking from past appointments
 * - Customer analytics and lifetime value
 * - Autofill for faster booking
 * 
 * @see         app/Services/CustomerAppointmentService.php for business logic
 * @see         app/Models/CustomerModel.php for customer data
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api;

use App\Services\CustomerAppointmentService;
use App\Models\CustomerModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Customer Appointments API Controller
 * 
 * Provides API endpoints for customer appointment history:
 * - GET /api/customers/{id}/appointments - All appointments for customer
 * - GET /api/customers/{id}/appointments/upcoming - Upcoming appointments
 * - GET /api/customers/{id}/appointments/history - Past appointments
 * - GET /api/customers/{id}/appointments/stats - Appointment statistics
 * - GET /api/customers/{id}/autofill - Autofill data for booking
 */
class CustomerAppointments extends BaseApiController
{
    protected CustomerAppointmentService $service;
    protected CustomerModel $customers;

    public function __construct()
    {
        $this->service = new CustomerAppointmentService();
        $this->customers = new CustomerModel();
    }

    /**
     * GET /api/customers/{id}/appointments
     * 
     * Get paginated appointments for a customer with optional filters
     * 
     * Query params:
     * - page: int (default 1)
     * - per_page: int (default 20, max 100)
     * - status: string|array (pending, confirmed, completed, cancelled, no-show)
     * - provider_id: int
     * - service_id: int
     * - date_from: string (YYYY-MM-DD)
     * - date_to: string (YYYY-MM-DD)
     * - type: string (upcoming, past)
     */
    public function index(int $customerId): ResponseInterface
    {
        // Verify customer exists
        $customer = $this->customers->find($customerId);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found',
                'customer_id' => $customerId,
            ]);
        }

        // Parse query parameters
        $page = max(1, (int) $this->request->getGet('page') ?: 1);
        $perPage = min(100, max(1, (int) $this->request->getGet('per_page') ?: 20));
        
        $filters = $this->parseFilters();

        $result = $this->service->getHistory($customerId, $filters, $page, $perPage);

        return $this->response->setJSON([
            'success' => true,
            'customer_id' => $customerId,
            ...$result,
        ]);
    }

    /**
     * GET /api/customers/{id}/appointments/upcoming
     * 
     * Get upcoming appointments for a customer
     */
    public function upcoming(int $customerId): ResponseInterface
    {
        $customer = $this->customers->find($customerId);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found',
            ]);
        }

        $limit = min(50, max(1, (int) $this->request->getGet('limit') ?: 10));
        $appointments = $this->service->getUpcoming($customerId, $limit);

        return $this->response->setJSON([
            'success' => true,
            'customer_id' => $customerId,
            'data' => $appointments,
            'count' => count($appointments),
        ]);
    }

    /**
     * GET /api/customers/{id}/appointments/history
     * 
     * Get past appointments for a customer (alias for index with type=past)
     */
    public function history(int $customerId): ResponseInterface
    {
        $customer = $this->customers->find($customerId);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found',
            ]);
        }

        $page = max(1, (int) $this->request->getGet('page') ?: 1);
        $perPage = min(100, max(1, (int) $this->request->getGet('per_page') ?: 20));
        
        $filters = $this->parseFilters();
        $filters['type'] = 'past';

        $result = $this->service->getHistory($customerId, $filters, $page, $perPage);

        return $this->response->setJSON([
            'success' => true,
            'customer_id' => $customerId,
            ...$result,
        ]);
    }

    /**
     * GET /api/customers/{id}/appointments/stats
     * 
     * Get appointment statistics for a customer
     */
    public function stats(int $customerId): ResponseInterface
    {
        $customer = $this->customers->find($customerId);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found',
            ]);
        }

        $stats = $this->service->getStats($customerId);

        return $this->response->setJSON([
            'success' => true,
            'customer_id' => $customerId,
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/customers/{id}/autofill
     * 
     * Get autofill data for prefilling booking forms
     */
    public function autofill(int $customerId): ResponseInterface
    {
        $customer = $this->customers->find($customerId);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found',
            ]);
        }

        $autofill = $this->service->getAutofillData($customerId);

        return $this->response->setJSON([
            'success' => true,
            ...$autofill,
        ]);
    }

    /**
     * GET /api/customers/by-hash/{hash}/appointments
     * 
     * Get appointments for a customer by their hash (for public access)
     */
    public function byHash(string $hash): ResponseInterface
    {
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found',
            ]);
        }

        $page = max(1, (int) $this->request->getGet('page') ?: 1);
        $perPage = min(100, max(1, (int) $this->request->getGet('per_page') ?: 20));
        
        $filters = $this->parseFilters();

        $result = $this->service->getHistory((int) $customer['id'], $filters, $page, $perPage);

        // Don't expose internal customer ID in public response
        return $this->response->setJSON([
            'success' => true,
            'customer_hash' => $hash,
            ...$result,
        ]);
    }

    /**
     * GET /api/customers/by-hash/{hash}/autofill
     * 
     * Get autofill data by customer hash (for public booking forms)
     */
    public function autofillByHash(string $hash): ResponseInterface
    {
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Customer not found',
            ]);
        }

        $autofill = $this->service->getAutofillData((int) $customer['id']);

        // Don't expose internal customer ID in public response
        unset($autofill['customer']['id']);
        $autofill['customer']['hash'] = $hash;

        return $this->response->setJSON([
            'success' => true,
            ...$autofill,
        ]);
    }

    /**
     * GET /api/appointments/search
     * 
     * Search appointments across all customers (admin only)
     */
    public function search(): ResponseInterface
    {
        $page = max(1, (int) $this->request->getGet('page') ?: 1);
        $perPage = min(100, max(1, (int) $this->request->getGet('per_page') ?: 20));
        
        $filters = $this->parseFilters();
        $filters['search'] = $this->request->getGet('q') ?: $this->request->getGet('search');

        $result = $this->service->searchAllAppointments($filters, $page, $perPage);

        return $this->response->setJSON([
            'success' => true,
            ...$result,
        ]);
    }

    /**
     * GET /api/appointments/filters
     * 
     * Get available filter options (providers, services)
     */
    public function filterOptions(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'providers' => $this->service->getProvidersForFilter(),
            'services' => $this->service->getServicesForFilter(),
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
                ['value' => 'no-show', 'label' => 'No Show'],
            ],
        ]);
    }

    /**
     * Parse filter parameters from request
     */
    protected function parseFilters(): array
    {
        $filters = [];

        if ($status = $this->request->getGet('status')) {
            $filters['status'] = is_string($status) ? explode(',', $status) : $status;
            if (count($filters['status']) === 1) {
                $filters['status'] = $filters['status'][0];
            }
        }

        if ($providerId = $this->request->getGet('provider_id')) {
            $filters['provider_id'] = (int) $providerId;
        }

        if ($serviceId = $this->request->getGet('service_id')) {
            $filters['service_id'] = (int) $serviceId;
        }

        if ($dateFrom = $this->request->getGet('date_from')) {
            $filters['date_from'] = $dateFrom;
        }

        if ($dateTo = $this->request->getGet('date_to')) {
            $filters['date_to'] = $dateTo;
        }

        if ($type = $this->request->getGet('type')) {
            $filters['type'] = $type;
        }

        return $filters;
    }
}

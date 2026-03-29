<?php

/**
 * =============================================================================
 * GLOBAL SEARCH CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Search.php
 * @description Unified search endpoint for finding customers, appointments,
 *              and services across the entire application.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /search                       : Global search (JSON response)
 * GET  /search/customers             : Search customers only
 * GET  /search/appointments          : Search appointments only
 * GET  /search/services              : Search services only
 * 
 * QUERY PARAMETERS:
 * -----------------------------------------------------------------------------
 * - q : Search query string (minimum 2 characters)
 * - type : Filter by entity type (customer, appointment, service)
 * - limit : Maximum results to return (default: 10)
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides quick search functionality for the top navigation search bar:
 * - Searches customers by name, email, phone
 * - Searches appointments by customer name, service, notes
 * - Returns combined results with relevance scoring
 * - Supports typeahead/autocomplete functionality
 * 
 * RESPONSE FORMAT:
 * -----------------------------------------------------------------------------
 * Returns JSON with categorized results:
 * {
 *   "customers": [...],
 *   "appointments": [...],
 *   "total": 15
 * }
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Requires authentication
 * - Results filtered by user role and permissions
 * 
 * @see         resources/js/components/global-search.js for frontend
 * @see         app/Models/CustomerModel::search() for customer search
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Services\GlobalSearchService;

class Search extends BaseController
{
    protected GlobalSearchService $globalSearchService;

    public function __construct(?GlobalSearchService $globalSearchService = null)
    {
        $this->globalSearchService = $globalSearchService ?? new GlobalSearchService();
    }

    /**
     * Global search endpoint
     * Searches across customers and appointments
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function index()
    {
        // Verify user is authenticated
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Unauthorized', 'success' => false]);
        }

        $q = trim((string) $this->request->getGet('q'));
        $limit = (int) ($this->request->getGet('limit') ?? 10);
        
        try {
            $results = $this->globalSearchService->search($q, $limit);

            return $this->response->setJSON([
                'success' => true,
                'customers' => $results['customers'],
                'appointments' => $results['appointments'],
                'counts' => $results['counts'],
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Search::index] Error: ' . $e->getMessage());
            
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => 'Search failed: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * Dashboard-specific search (legacy route support)
     * Redirects to main search endpoint
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function dashboard()
    {
        return $this->index();
    }
}

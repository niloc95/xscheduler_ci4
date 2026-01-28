<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\AppointmentModel;

/**
 * Search Controller
 * 
 * Handles global search functionality across customers and appointments.
 * Extracted from Dashboard controller for better separation of concerns.
 * 
 * @package App\Controllers
 */
class Search extends BaseController
{
    protected $customerModel;
    protected $appointmentModel;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
        $this->appointmentModel = new AppointmentModel();
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

        // Get search query from request
        $q = trim((string) $this->request->getGet('q'));
        
        try {
            $customers = [];
            $appointments = [];
            
            // Only search if query is not empty
            if ($q !== '') {
                // Search customers (first name, last name, email, phone)
                $customers = $this->customerModel->search(['q' => $q, 'limit' => 10]);
                
                // Search appointments - search by customer name, service name, or notes
                $appointmentsQuery = $this->appointmentModel
                    ->select('xs_appointments.*, 
                             CONCAT(xs_customers.first_name, " ", xs_customers.last_name) as customer_name,
                             xs_customers.email as customer_email,
                             xs_services.name as service_name')
                    ->join('xs_customers', 'xs_customers.id = xs_appointments.customer_id', 'left')
                    ->join('xs_services', 'xs_services.id = xs_appointments.service_id', 'left')
                    ->groupStart()
                        ->like('xs_customers.first_name', $q)
                        ->orLike('xs_customers.last_name', $q)
                        ->orLike('xs_customers.email', $q)
                        ->orLike('xs_services.name', $q)
                        ->orLike('xs_appointments.notes', $q)
                    ->groupEnd()
                    ->orderBy('xs_appointments.start_time', 'DESC')
                    ->limit(10);
                
                $appointments = $appointmentsQuery->findAll();
            }

            // Return JSON response
            return $this->response->setJSON([
                'success' => true,
                'customers' => $customers,
                'appointments' => $appointments,
                'counts' => [
                    'customers' => count($customers),
                    'appointments' => count($appointments),
                    'total' => count($customers) + count($appointments)
                ]
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

<?php

/**
 * =============================================================================
 * CUSTOMER APPOINTMENT SERVICE
 * =============================================================================
 * 
 * @file        app/Services/CustomerAppointmentService.php
 * @description Provides comprehensive appointment history and management for
 *              customers including history, filtering, and autofill.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Centralizes customer appointment operations for:
 * - Customer dashboard
 * - Appointment history API
 * - Customer autofill for booking
 * - Appointment statistics
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * getHistory($customerId, $filters, $page, $perPage)
 *   Get paginated appointment history with rich filtering
 *   Filters: status, provider_id, service_id, date_from, date_to, search
 * 
 * getUpcoming($customerId, $limit)
 *   Get upcoming confirmed appointments
 * 
 * getPast($customerId, $page, $perPage)
 *   Get past appointments (completed, cancelled, no-show)
 * 
 * getStats($customerId)
 *   Get appointment statistics:
 *   - Total appointments
 *   - Completed count
 *   - Cancelled count
 *   - No-show count
 *   - Total spent
 * 
 * getAutofillData($customerId)
 *   Get customer data for booking form autofill
 *   Includes: name, email, phone, last provider, last service
 * 
 * FILTER OPTIONS:
 * -----------------------------------------------------------------------------
 * - status      : Filter by status (scheduled, completed, cancelled, etc.)
 * - provider_id : Filter by specific provider
 * - service_id  : Filter by specific service
 * - date_from   : Filter appointments from date
 * - date_to     : Filter appointments until date
 * - search      : Search in service name, provider name
 * 
 * @see         app/Controllers/Api/CustomerAppointments.php
 * @see         app/Controllers/CustomerManagement.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ServiceModel;
use App\Models\UserModel;

/**
 * Customer Appointment History Service
 * 
 * Provides comprehensive appointment history for customers including:
 * - Upcoming appointments
 * - Past appointments (completed, cancelled, rescheduled)
 * - Filtering by provider, service, status, date range
 * - Pagination support
 * - Customer autofill data from history
 */
class CustomerAppointmentService
{
    protected AppointmentModel $appointments;
    protected CustomerModel $customers;
    protected ServiceModel $services;
    protected UserModel $users;

    public function __construct()
    {
        $this->appointments = new AppointmentModel();
        $this->customers = new CustomerModel();
        $this->services = new ServiceModel();
        $this->users = new UserModel();
    }

    /**
     * Get paginated appointment history for a customer
     * 
     * @param int $customerId Customer ID
     * @param array $filters Optional filters: status, provider_id, service_id, date_from, date_to, search
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array Paginated results with metadata
     */
    public function getHistory(int $customerId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $builder = $this->appointments->builder()
            ->select('xs_appointments.*, 
                     s.name as service_name, s.duration_min as service_duration, s.price as service_price,
                     u.name as provider_name, u.email as provider_email, u.color as provider_color')
            ->join('xs_services as s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users as u', 'u.id = xs_appointments.provider_id', 'left')
            ->where('xs_appointments.customer_id', $customerId);

        // Apply filters
        $this->applyFilters($builder, $filters);

        // Count total before pagination
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults(false);

        // Apply sorting and pagination
        $builder->orderBy('xs_appointments.start_time', 'DESC')
                ->limit($perPage, ($page - 1) * $perPage);

        $appointments = $builder->get()->getResultArray();

        // Enrich appointments with computed fields
        $appointments = array_map([$this, 'enrichAppointment'], $appointments);

        return [
            'data' => $appointments,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Get upcoming appointments for a customer
     * 
     * @param int $customerId Customer ID
     * @param int $limit Maximum number of appointments
     * @return array Upcoming appointments
     */
    public function getUpcoming(int $customerId, int $limit = 10): array
    {
        $builder = $this->appointments->builder()
            ->select('xs_appointments.*, 
                     s.name as service_name, s.duration_min as service_duration, s.price as service_price,
                     u.name as provider_name, u.email as provider_email, u.color as provider_color')
            ->join('xs_services as s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users as u', 'u.id = xs_appointments.provider_id', 'left')
            ->where('xs_appointments.customer_id', $customerId)
            ->where('xs_appointments.start_time >=', date('Y-m-d H:i:s'))
            ->whereIn('xs_appointments.status', ['pending', 'confirmed'])
            ->orderBy('xs_appointments.start_time', 'ASC')
            ->limit($limit);

        $appointments = $builder->get()->getResultArray();
        return array_map([$this, 'enrichAppointment'], $appointments);
    }

    /**
     * Get past (completed) appointments for a customer
     * 
     * @param int $customerId Customer ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Paginated past appointments
     */
    public function getPast(int $customerId, int $page = 1, int $perPage = 20): array
    {
        return $this->getHistory($customerId, [
            'type' => 'past'
        ], $page, $perPage);
    }

    /**
     * Get appointment statistics for a customer
     * 
     * @param int $customerId Customer ID
     * @return array Statistics summary
     */
    public function getStats(int $customerId): array
    {
        $now = date('Y-m-d H:i:s');
        
        $total = $this->appointments->where('customer_id', $customerId)->countAllResults(false);
        
        $upcoming = $this->appointments
            ->where('customer_id', $customerId)
            ->where('start_time >=', $now)
            ->whereIn('status', ['pending', 'confirmed'])
            ->countAllResults(false);
        
        $completed = $this->appointments
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->countAllResults(false);
        
        $cancelled = $this->appointments
            ->where('customer_id', $customerId)
            ->where('status', 'cancelled')
            ->countAllResults(false);
        
        $noShow = $this->appointments
            ->where('customer_id', $customerId)
            ->where('status', 'no-show')
            ->countAllResults(false);

        // Get most used provider
        $favoriteProvider = $this->appointments->builder()
            ->select('provider_id, COUNT(*) as count')
            ->where('customer_id', $customerId)
            ->groupBy('provider_id')
            ->orderBy('count', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        // Get most used service
        $favoriteService = $this->appointments->builder()
            ->select('service_id, COUNT(*) as count')
            ->where('customer_id', $customerId)
            ->groupBy('service_id')
            ->orderBy('count', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        // Get first and last appointment dates
        $firstAppointment = $this->appointments->builder()
            ->select('MIN(start_time) as first_date')
            ->where('customer_id', $customerId)
            ->get()
            ->getRowArray();

        $lastAppointment = $this->appointments->builder()
            ->select('MAX(start_time) as last_date')
            ->where('customer_id', $customerId)
            ->where('start_time <', $now)
            ->get()
            ->getRowArray();

        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'favorite_provider_id' => $favoriteProvider['provider_id'] ?? null,
            'favorite_service_id' => $favoriteService['service_id'] ?? null,
            'first_appointment' => $firstAppointment['first_date'] ?? null,
            'last_appointment' => $lastAppointment['last_date'] ?? null,
        ];
    }

    /**
     * Get autofill data for a customer (for prefilling new booking forms)
     * 
     * @param int $customerId Customer ID
     * @return array Autofill suggestions
     */
    public function getAutofillData(int $customerId): array
    {
        $customer = $this->customers->find($customerId);
        if (!$customer) {
            return [];
        }

        $stats = $this->getStats($customerId);
        
        $autofill = [
            'customer' => [
                'id' => $customer['id'],
                'first_name' => $customer['first_name'] ?? '',
                'last_name' => $customer['last_name'] ?? '',
                'email' => $customer['email'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'address' => $customer['address'] ?? '',
            ],
            'preferences' => [
                'favorite_provider_id' => $stats['favorite_provider_id'],
                'favorite_service_id' => $stats['favorite_service_id'],
            ],
            'stats' => [
                'total_appointments' => $stats['total'],
                'completed' => $stats['completed'],
            ],
        ];

        // Add provider and service names if available
        if ($stats['favorite_provider_id']) {
            $provider = $this->users->find($stats['favorite_provider_id']);
            $autofill['preferences']['favorite_provider_name'] = $provider['name'] ?? null;
        }

        if ($stats['favorite_service_id']) {
            $service = $this->services->find($stats['favorite_service_id']);
            $autofill['preferences']['favorite_service_name'] = $service['name'] ?? null;
        }

        return $autofill;
    }

    /**
     * Get customer by hash with appointment summary
     * 
     * @param string $hash Customer hash
     * @return array|null Customer with summary
     */
    public function getCustomerByHash(string $hash): ?array
    {
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return null;
        }

        $stats = $this->getStats((int) $customer['id']);
        
        return array_merge($customer, [
            'appointment_stats' => $stats,
        ]);
    }

    /**
     * Search appointments across all customers (for admin)
     * 
     * @param array $filters Search filters
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Paginated results
     */
    public function searchAllAppointments(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $builder = $this->appointments->builder()
            ->select('xs_appointments.*, 
                     c.first_name as customer_first_name, c.last_name as customer_last_name, 
                     c.email as customer_email, c.phone as customer_phone,
                     s.name as service_name, s.duration_min as service_duration, s.price as service_price,
                     u.name as provider_name, u.email as provider_email, u.color as provider_color')
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services as s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users as u', 'u.id = xs_appointments.provider_id', 'left');

        // Apply search term
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $builder->groupStart()
                ->like('c.first_name', $search)
                ->orLike('c.last_name', $search)
                ->orLike('c.email', $search)
                ->orLike('c.phone', $search)
                ->orLike('s.name', $search)
                ->orLike('u.name', $search)
            ->groupEnd();
        }

        // Apply other filters
        $this->applyFilters($builder, $filters);

        // Count total
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults(false);

        // Apply sorting and pagination
        $builder->orderBy('xs_appointments.start_time', 'DESC')
                ->limit($perPage, ($page - 1) * $perPage);

        $appointments = $builder->get()->getResultArray();
        $appointments = array_map([$this, 'enrichAppointment'], $appointments);

        return [
            'data' => $appointments,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Apply filters to query builder
     */
    protected function applyFilters($builder, array $filters): void
    {
        $now = date('Y-m-d H:i:s');

        // Type filter (upcoming/past)
        if (!empty($filters['type'])) {
            if ($filters['type'] === 'upcoming') {
                $builder->where('xs_appointments.start_time >=', $now)
                        ->whereIn('xs_appointments.status', ['pending', 'confirmed']);
            } elseif ($filters['type'] === 'past') {
                $builder->groupStart()
                    ->where('xs_appointments.start_time <', $now)
                    ->orWhereIn('xs_appointments.status', ['completed', 'cancelled', 'no-show'])
                ->groupEnd();
            }
        }

        // Status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $builder->whereIn('xs_appointments.status', $filters['status']);
            } else {
                $builder->where('xs_appointments.status', $filters['status']);
            }
        }

        // Provider filter
        if (!empty($filters['provider_id'])) {
            $builder->where('xs_appointments.provider_id', (int) $filters['provider_id']);
        }

        // Service filter
        if (!empty($filters['service_id'])) {
            $builder->where('xs_appointments.service_id', (int) $filters['service_id']);
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $builder->where('xs_appointments.start_time >=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $builder->where('xs_appointments.start_time <=', $filters['date_to'] . ' 23:59:59');
        }

        // Customer ID filter (for admin search)
        if (!empty($filters['customer_id'])) {
            $builder->where('xs_appointments.customer_id', (int) $filters['customer_id']);
        }
    }

    /**
     * Enrich appointment with computed fields
     */
    protected function enrichAppointment(array $appointment): array
    {
        $now = time();
        $startTime = strtotime($appointment['start_time'] ?? '');
        $endTime = strtotime($appointment['end_time'] ?? '');

        // Compute duration if not set
        if (empty($appointment['duration_min']) && $startTime && $endTime) {
            $appointment['duration_min'] = (int) (($endTime - $startTime) / 60);
        }

        // Add human-readable date/time
        if ($startTime) {
            $appointment['date_formatted'] = date('l, F j, Y', $startTime);
            $appointment['time_formatted'] = date('g:i A', $startTime);
            $appointment['datetime_formatted'] = date('M j, Y \a\t g:i A', $startTime);
            $appointment['is_past'] = $startTime < $now;
            $appointment['is_today'] = date('Y-m-d', $startTime) === date('Y-m-d');
        }

        // Status label
        $statusLabels = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no-show' => 'No Show',
        ];
        $appointment['status_label'] = $statusLabels[$appointment['status'] ?? ''] ?? ucfirst($appointment['status'] ?? '');

        // Customer full name (for admin views)
        if (isset($appointment['customer_first_name'])) {
            $appointment['customer_name'] = trim(
                ($appointment['customer_first_name'] ?? '') . ' ' . ($appointment['customer_last_name'] ?? '')
            );
        }

        return $appointment;
    }

    /**
     * Get available providers for filter dropdown
     */
    public function getProvidersForFilter(): array
    {
        return $this->users
            ->whereIn('role', ['provider', 'admin'])
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Get available services for filter dropdown
     */
    public function getServicesForFilter(): array
    {
        return $this->services
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}

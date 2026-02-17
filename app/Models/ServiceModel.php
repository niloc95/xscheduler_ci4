<?php

/**
 * =============================================================================
 * SERVICE MODEL
 * =============================================================================
 * 
 * @file        app/Models/ServiceModel.php
 * @description Data model for services offered by providers. Services define
 *              what can be booked, including duration and pricing.
 * 
 * DATABASE TABLE: xs_services
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - name            : Service name
 * - description     : Service description
 * - duration_min    : Duration in minutes
 * - price           : Service price (decimal)
 * - category_id     : FK to xs_categories (optional)
 * - active          : Is service bookable (0/1)
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * RELATED TABLES:
 * -----------------------------------------------------------------------------
 * - xs_categories         : Service categories
 * - xs_providers_services : Many-to-many with providers
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - getStats()            : Service statistics
 * - getActive()           : List active services
 * - getByCategory()       : Filter by category
 * - getByProvider()       : Services offered by provider
 * - getPopular()          : Most booked services
 * 
 * VALIDATION RULES:
 * -----------------------------------------------------------------------------
 * - name: Required, 2-255 characters
 * - duration_min: Required, positive integer
 * - price: Optional, decimal
 * - category_id: Optional, integer
 * - active: Optional, 0 or 1
 * 
 * @see         app/Controllers/Services.php for admin CRUD
 * @see         app/Controllers/Api/V1/Services.php for API
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

use App\Models\BaseModel;

class ServiceModel extends BaseModel
{
    protected $table            = 'xs_services';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
    'name', 'description', 'duration_min', 'price', 'category_id', 'active'
    ];

    // Dates
    // ...existing code...

    // Validation
    protected $validationRules      = [
        'name'         => 'required|min_length[2]|max_length[255]',
        'duration_min' => 'required|integer|greater_than[0]',
        'price'        => 'permit_empty|decimal',
        'category_id'  => 'permit_empty|integer',
        'active'       => 'permit_empty|in_list[0,1]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get service statistics
     */
    public function getStats()
    {
        $total = $this->countAll();
        $active = $this->where('active', 1)->countAllResults(false);
        $avgDuration = $this->selectAvg('duration_min')->get()->getRow()->duration_min ?? 0;
        $avgPrice = $this->selectAvg('price')->get()->getRow()->price ?? 0;

        // total categories present among services
        $distinctCategories = $this->builder()->select('COUNT(DISTINCT category_id) as cnt')->get()->getRow()->cnt ?? 0;

        // naive bookings total via appointments table if exists
        try {
            $bookings = $this->db->table('appointments')->countAllResults();
        } catch (\Throwable $e) {
            $bookings = 0;
        }

        return [
            'total' => (int)$total,
            'active' => (int)$active,
            'avg_duration' => (float)$avgDuration,
            'avg_price' => (float)$avgPrice,
            'categories' => (int)$distinctCategories,
            'bookings' => (int)$bookings,
        ];
    }

    /**
     * Get popular services based on appointment count
     */
    public function getPopularServices($limit = 5)
    {
        // Simplified - return all services for now
        return $this->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Find services with category and provider names for dashboard/table display
     */
    public function findWithRelations(?int $limit = null, ?int $offset = null): array
    {
        $isSQLite = stripos($this->db->DBDriver, 'sqlite') !== false;
        $providerConcat = $isSQLite
            ? "GROUP_CONCAT(u.name, ', ') as provider_names"
            : "GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') as provider_names";

        $builder = $this->db->table('services s')
            ->select("s.*, c.name as category_name, {$providerConcat}", false)
            ->join('categories c', 'c.id = s.category_id', 'left')
            ->join('providers_services ps', 'ps.service_id = s.id', 'left')
            ->join('users u', 'u.id = ps.provider_id', 'left')
            ->groupBy('s.id')
            ->orderBy('s.created_at', 'DESC');

        if ($limit !== null) {
            $builder->limit($limit, $offset ?? 0);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Link a service to one or more providers
     */
    public function setProviders(int $serviceId, array $providerIds): void
    {
        $table = $this->db->table('providers_services');
        // Clear existing
        $table->delete(['service_id' => $serviceId]);
        // Insert new links
        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_filter($providerIds)) as $pid) {
            $table->insert([
                'provider_id' => (int)$pid,
                'service_id'  => (int)$serviceId,
                'created_at'  => $now,
            ]);
        }
    }

    /**
     * Get popular services with booking counts and revenue
     */
    public function getPopularServicesWithStats(int $limit = 10): array
    {
        $db = \Config\Database::connect();
        
        $query = $db->query("
            SELECT 
                s.id,
                s.name,
                s.price,
                COUNT(a.id) as bookings,
                COALESCE(SUM(CASE WHEN a.status = 'completed' THEN s.price ELSE 0 END), 0) as revenue,
                ROUND(
                    ((COUNT(CASE WHEN a.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) - 
                      COUNT(CASE WHEN a.start_time >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND a.start_time < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END)) /
                     NULLIF(COUNT(CASE WHEN a.start_time >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND a.start_time < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END), 0)) * 100,
                    1
                ) as growth
            FROM xs_services s
            LEFT JOIN xs_appointments a ON s.id = a.service_id
            WHERE s.active = 1
            GROUP BY s.id, s.name, s.price
            ORDER BY bookings DESC
            LIMIT {$limit}
        ");
        
        return $query->getResultArray();
    }

    /**
     * Get service performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $db = \Config\Database::connect();
        
        // Average duration
        $avgDuration = $this->where('active', 1)->selectAvg('duration_min')->get()->getRow()->duration_min ?? 0;
        
        // Completion rate from appointments
        $appointmentQuery = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM xs_appointments
        ");
        $appointmentStats = $appointmentQuery->getRow();
        $completionRate = $appointmentStats->total > 0 
            ? round(($appointmentStats->completed / $appointmentStats->total) * 100, 1) 
            : 0;
        
        // Repeat booking rate (customers with more than 1 appointment)
        $repeatQuery = $db->query("
            SELECT 
                COUNT(DISTINCT customer_id) as total_customers,
                COUNT(DISTINCT CASE WHEN appt_count > 1 THEN customer_id END) as repeat_customers
            FROM (
                SELECT customer_id, COUNT(*) as appt_count
                FROM xs_appointments
                GROUP BY customer_id
            ) as customer_appts
        ");
        $repeatStats = $repeatQuery->getRow();
        $repeatRate = $repeatStats->total_customers > 0
            ? round(($repeatStats->repeat_customers / $repeatStats->total_customers) * 100, 1)
            : 0;
        
        return [
            'avg_duration' => round((float)$avgDuration, 1),
            'completion_rate' => $completionRate,
            'customer_satisfaction' => 0, // Would need rating system
            'repeat_booking_rate' => $repeatRate
        ];
    }
}

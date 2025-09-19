<?php

namespace App\Models;

use App\Models\BaseModel;

class ServiceModel extends BaseModel
{
    protected $table            = 'services';
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
        $builder = $this->db->table('services s')
            ->select('s.*, c.name as category_name, GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ", ") as provider_names')
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
}

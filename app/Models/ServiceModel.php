<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table            = 'services';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name', 'description', 'duration_min', 'price'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'name'         => 'required|min_length[2]|max_length[255]',
        'duration_min' => 'required|integer|greater_than[0]',
        'price'        => 'permit_empty|decimal'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get service statistics
     */
    public function getStats()
    {
        return [
            'total' => $this->countAll(),
            'avg_duration' => $this->selectAvg('duration_min')->get()->getRow()->duration_min ?? 0,
            'avg_price' => $this->selectAvg('price')->get()->getRow()->price ?? 0,
            'recent' => $this->where('created_at >=', date('Y-m-d', strtotime('-30 days')))->countAllResults()
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
}

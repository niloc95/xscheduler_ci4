<?php

namespace App\Models;

use CodeIgniter\Model;

class BlockedTimeModel extends Model
{
    protected $table            = 'blocked_times';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'provider_id', 'start_time', 'end_time', 'reason'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'start_time' => 'required|valid_date',
        'end_time'   => 'required|valid_date',
        'reason'     => 'permit_empty|max_length[255]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get blocked times for a specific provider and date range
     */
    public function getBlockedTimes($providerId = null, $startDate = null, $endDate = null)
    {
        $builder = $this->builder();

        // If provider_id is null, get global blocks
        if ($providerId === null) {
            $builder->where('provider_id IS NULL');
        } else {
            $builder->groupStart()
                   ->where('provider_id', $providerId)
                   ->orWhere('provider_id IS NULL') // Include global blocks
                   ->groupEnd();
        }

        if ($startDate) {
            $builder->where('end_time >=', $startDate);
        }

        if ($endDate) {
            $builder->where('start_time <=', $endDate);
        }

        return $builder->orderBy('start_time', 'ASC')->findAll();
    }

    /**
     * Check if a time period conflicts with blocked times
     */
    public function isTimeBlocked($providerId, $startTime, $endTime)
    {
        $blockedTimes = $this->getBlockedTimes($providerId, $startTime, $endTime);

        foreach ($blockedTimes as $blocked) {
            if ($startTime < $blocked['end_time'] && $endTime > $blocked['start_time']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a blocked time period
     */
    public function createBlockedTime($providerId, $startTime, $endTime, $reason = '')
    {
        $data = [
            'provider_id' => $providerId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => $reason
        ];

        return $this->insert($data);
    }

    /**
     * Get statistics about blocked times
     */
    public function getStats()
    {
        return [
            'total' => $this->countAll(),
            'global' => $this->where('provider_id IS NULL')->countAllResults(false),
            'provider_specific' => $this->where('provider_id IS NOT NULL')->countAllResults(false),
            'upcoming' => $this->where('start_time >', date('Y-m-d H:i:s'))->countAllResults(false)
        ];
    }
}
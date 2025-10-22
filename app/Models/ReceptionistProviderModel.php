<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceptionistProviderModel extends Model
{
    protected $table            = 'receptionist_providers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'receptionist_id',
        'provider_id',
        'assigned_by',
        'assigned_at',
        'status'
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';

    /**
     * Get all providers assigned to a receptionist
     *
     * @param int $receptionistId
     * @return array
     */
    public function getProvidersForReceptionist(int $receptionistId): array
    {
        return $this->select('xs_receptionist_providers.*, xs_users.name as provider_name, xs_users.email as provider_email')
                    ->join('xs_users', 'xs_users.id = xs_receptionist_providers.provider_id')
                    ->where('xs_receptionist_providers.receptionist_id', $receptionistId)
                    ->where('xs_receptionist_providers.status', 'active')
                    ->where('xs_users.is_active', 1)
                    ->where('xs_users.deleted_at IS NULL') // Exclude soft-deleted users
                    ->orderBy('xs_users.name', 'ASC')
                    ->findAll();
    }

    /**
     * Get all receptionists assigned to a provider
     *
     * @param int $providerId
     * @return array
     */
    public function getReceptionistsForProvider(int $providerId): array
    {
        return $this->select('xs_receptionist_providers.*, xs_users.name as receptionist_name, xs_users.email as receptionist_email')
                    ->join('xs_users', 'xs_users.id = xs_receptionist_providers.receptionist_id')
                    ->where('xs_receptionist_providers.provider_id', $providerId)
                    ->where('xs_receptionist_providers.status', 'active')
                    ->where('xs_users.is_active', 1)
                    ->where('xs_users.deleted_at IS NULL') // Exclude soft-deleted users
                    ->orderBy('xs_users.name', 'ASC')
                    ->findAll();
    }

    /**
     * Check if a receptionist is assigned to a provider
     *
     * @param int $receptionistId
     * @param int $providerId
     * @return bool
     */
    public function isAssigned(int $receptionistId, int $providerId): bool
    {
        return $this->where('receptionist_id', $receptionistId)
                    ->where('provider_id', $providerId)
                    ->where('status', 'active')
                    ->countAllResults() > 0;
    }

    /**
     * Assign a receptionist to a provider
     *
     * @param int $receptionistId
     * @param int $providerId
     * @param int $assignedBy
     * @return int|bool
     */
    public function assignReceptionist(int $receptionistId, int $providerId, int $assignedBy)
    {
        // Check if already assigned
        if ($this->isAssigned($receptionistId, $providerId)) {
            return false;
        }

        $data = [
            'receptionist_id' => $receptionistId,
            'provider_id' => $providerId,
            'assigned_by' => $assignedBy,
            'assigned_at' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];

        return $this->insert($data, false);
    }

    /**
     * Remove a receptionist from a provider
     *
     * @param int $receptionistId
     * @param int $providerId
     * @return bool
     */
    public function removeReceptionist(int $receptionistId, int $providerId): bool
    {
        return $this->where('receptionist_id', $receptionistId)
                    ->where('provider_id', $providerId)
                    ->delete();
    }

    /**
     * Get all active assignments (for admin view)
     *
     * @return array
     */
    public function getAllAssignments(): array
    {
        return $this->select('xs_receptionist_providers.*, 
                             r.name as receptionist_name, 
                             r.email as receptionist_email,
                             p.name as provider_name, 
                             p.email as provider_email')
                    ->join('xs_users as r', 'r.id = xs_receptionist_providers.receptionist_id')
                    ->join('xs_users as p', 'p.id = xs_receptionist_providers.provider_id')
                    ->where('xs_receptionist_providers.status', 'active')
                    ->where('r.is_active', 1)
                    ->where('p.is_active', 1)
                    ->where('r.deleted_at IS NULL')
                    ->where('p.deleted_at IS NULL')
                    ->orderBy('r.name', 'ASC')
                    ->findAll();
    }
}

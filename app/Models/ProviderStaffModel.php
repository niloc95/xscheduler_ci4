<?php

namespace App\Models;

use CodeIgniter\Model;

class ProviderStaffModel extends BaseModel
{
    protected $table = 'xs_provider_staff_assignments';
    protected $primaryKey = 'id';
    protected $allowedFields = ['provider_id', 'staff_id', 'assigned_by', 'status', 'assigned_at'];
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    /**
     * Get all staff assigned to a provider
     * 
     * @param int $providerId Provider user ID
     * @param string|null $status Filter by status (active, inactive, null for all)
     * @return array Staff user records with assignment metadata
     */
    public function getStaffByProvider(int $providerId, ?string $status = 'active'): array
    {
        $builder = $this->db->table($this->table . ' AS psa')
            ->select('staff.id, staff.name, staff.email, staff.phone, staff.role, staff.is_active, psa.assigned_at, psa.status, psa.assigned_by')
            ->join('xs_users AS staff', 'staff.id = psa.staff_id', 'inner')
            ->where('psa.provider_id', $providerId)
            ->where('staff.deleted_at IS NULL') // Exclude soft-deleted users
            ->whereIn('staff.role', ['staff', 'receptionist']);
        
        if ($status !== null) {
            $builder->where('psa.status', $status);
        }
        
        return $builder->orderBy('staff.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get all providers a staff member is assigned to
     * 
     * @param int $staffId Staff user ID
     * @param string|null $status Filter by status (active, inactive, null for all)
     * @return array Provider user records with assignment metadata
     */
    public function getProvidersForStaff(int $staffId, ?string $status = 'active'): array
    {
        $builder = $this->db->table($this->table . ' AS psa')
            ->select('provider.id, provider.name, provider.email, provider.role, psa.assigned_at, psa.status, psa.assigned_by')
            ->join('xs_users AS provider', 'provider.id = psa.provider_id', 'inner')
            ->where('psa.staff_id', $staffId)
            ->where('provider.deleted_at IS NULL') // Exclude soft-deleted providers
            ->where('provider.role', 'provider');
        
        if ($status !== null) {
            $builder->where('psa.status', $status);
        }
        
        return $builder->orderBy('provider.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Assign a staff member to a provider
     * 
     * @param int $providerId Provider user ID
     * @param int $staffId Staff user ID
     * @param int|null $assignedBy User ID who created the assignment (for audit)
     * @param string $status Assignment status (default: active)
     * @return bool Success
     */
    public function assignStaff(int $providerId, int $staffId, ?int $assignedBy = null, string $status = 'active'): bool
    {
        // Check for existing assignment
        $existing = $this->builder()
            ->where('provider_id', $providerId)
            ->where('staff_id', $staffId)
            ->get()
            ->getRowArray();
        
        if ($existing) {
            // If exists but inactive, reactivate it
            if ($existing['status'] !== 'active') {
                return (bool) $this->builder()
                    ->where('id', $existing['id'])
                    ->update([
                        'status' => $status,
                        'assigned_by' => $assignedBy,
                        'assigned_at' => date('Y-m-d H:i:s'),
                    ]);
            }
            // Already active - return false (duplicate)
            log_message('info', "Assignment already exists: provider={$providerId}, staff={$staffId}");
            return false;
        }

        $record = [
            'provider_id' => $providerId,
            'staff_id'    => $staffId,
            'assigned_by' => $assignedBy,
            'status'      => $status,
            'assigned_at' => date('Y-m-d H:i:s'),
        ];

        try {
            return (bool) $this->insert($record, false);
        } catch (\Throwable $e) {
            log_message('error', 'ProviderStaffModel::assignStaff failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove (or deactivate) a staff assignment from a provider
     * 
     * @param int $providerId Provider user ID
     * @param int $staffId Staff user ID
     * @param bool $softDelete If true, set status to inactive instead of deleting
     * @return bool Success
     */
    public function removeStaff(int $providerId, int $staffId, bool $softDelete = false): bool
    {
        if ($softDelete) {
            return (bool) $this->builder()
                ->where('provider_id', $providerId)
                ->where('staff_id', $staffId)
                ->update(['status' => 'inactive']);
        }
        
        return (bool) $this->builder()
            ->where('provider_id', $providerId)
            ->where('staff_id', $staffId)
            ->delete();
    }

    /**
     * Check if a staff member is assigned to a provider
     * 
     * @param int $staffId Staff user ID
     * @param int $providerId Provider user ID
     * @param string|null $status Check specific status (null for any)
     * @return bool True if assigned
     */
    public function isStaffAssignedToProvider(int $staffId, int $providerId, ?string $status = 'active'): bool
    {
        $builder = $this->builder()
            ->where('provider_id', $providerId)
            ->where('staff_id', $staffId);
        
        if ($status !== null) {
            $builder->where('status', $status);
        }
        
        return $builder->countAllResults() > 0;
    }

    /**
     * Get assignment details including who assigned and when
     * 
     * @param int $providerId Provider user ID
     * @param int $staffId Staff user ID
     * @return array|null Assignment record with assigner details
     */
    public function getAssignmentDetails(int $providerId, int $staffId): ?array
    {
        return $this->db->table($this->table . ' AS psa')
            ->select('psa.*, assigner.name as assigned_by_name, assigner.email as assigned_by_email')
            ->join('xs_users AS assigner', 'assigner.id = psa.assigned_by', 'left')
            ->where('psa.provider_id', $providerId)
            ->where('psa.staff_id', $staffId)
            ->get()
            ->getRowArray();
    }
}

<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'xs_audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
        'created_at'
    ];

    // Dates
    protected $useTimestamps = false; // Manual control
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = false;
    protected $deletedField  = false;

    /**
     * Log an audit trail event
     *
     * @param string $action      Action type (user_updated, role_changed, password_reset, etc.)
     * @param int $userId         User who performed the action
     * @param string $targetType  Type of target (user, provider, staff, etc.)
     * @param int|null $targetId  ID of the target entity
     * @param array|null $oldValue Old values (for updates)
     * @param array|null $newValue New values (for updates)
     * @return bool
     */
    public function log(
        string $action,
        int $userId,
        string $targetType = 'user',
        ?int $targetId = null,
        ?array $oldValue = null,
        ?array $newValue = null
    ): bool {
        $request = \Config\Services::request();
        
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'ip_address' => $request->getIPAddress(),
            'user_agent' => $request->getUserAgent()->getAgentString(),
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->insert($data, false) !== false;
    }

    /**
     * Get audit logs for a specific user
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->where('target_id', $userId)
                    ->where('target_type', 'user')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }

    /**
     * Get audit logs performed by a specific user
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByPerformer(int $userId, int $limit = 50): array
    {
        return $this->where('user_id', $userId)
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }

    /**
     * Get audit logs for a specific action type
     *
     * @param string $action
     * @param int $limit
     * @return array
     */
    public function getByAction(string $action, int $limit = 50): array
    {
        return $this->where('action', $action)
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }

    /**
     * Search audit logs with filters
     *
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public function search(array $filters = [], int $limit = 100): array
    {
        $builder = $this;

        if (isset($filters['user_id'])) {
            $builder = $builder->where('user_id', $filters['user_id']);
        }

        if (isset($filters['target_id'])) {
            $builder = $builder->where('target_id', $filters['target_id']);
        }

        if (isset($filters['action'])) {
            $builder = $builder->where('action', $filters['action']);
        }

        if (isset($filters['target_type'])) {
            $builder = $builder->where('target_type', $filters['target_type']);
        }

        if (isset($filters['date_from'])) {
            $builder = $builder->where('created_at >=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $builder = $builder->where('created_at <=', $filters['date_to']);
        }

        return $builder->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->findAll();
    }
}

<?php

/**
 * =============================================================================
 * AUDIT LOG MODEL
 * =============================================================================
 * 
 * @file        app/Models/AuditLogModel.php
 * @description Data model for security audit trail. Records significant
 *              system events for compliance and debugging.
 * 
 * DATABASE TABLE: audit_logs
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - user_id         : User who performed action (FK to xs_users)
 * - action          : Action type (see ACTION TYPES below)
 * - target_type     : Entity type affected (user, appointment, setting)
 * - target_id       : ID of affected entity
 * - old_value       : Previous value (JSON)
 * - new_value       : New value (JSON)
 * - ip_address      : Client IP address
 * - user_agent      : Browser user agent string
 * - created_at      : When event occurred
 * 
 * ACTION TYPES:
 * -----------------------------------------------------------------------------
 * - user_login        : Successful login
 * - user_logout       : User logged out
 * - login_failed      : Failed login attempt
 * - user_created      : New user created
 * - user_updated      : User details changed
 * - password_changed  : Password was changed
 * - role_changed      : User role modified
 * - backup_created    : Database backup created
 * - backup_restored   : Backup was restored
 * - setting_changed   : System setting modified
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - log(action, userId, ...)  : Create audit entry
 * - getByUser(userId)         : Get user's audit trail
 * - getByTarget(type, id)     : Get events for entity
 * - getRecent(limit)          : Get recent events
 * 
 * COMPLIANCE:
 * -----------------------------------------------------------------------------
 * Used for:
 * - Security incident investigation
 * - GDPR compliance (track data access)
 * - Change tracking and accountability
 * 
 * @see         app/Controllers/Api/DatabaseBackup.php for backup logging
 * @see         app/Controllers/Auth.php for login logging
 * @package     App\Models
 * @extends     CodeIgniter\Model
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

class AuditLogModel extends BaseModel
{
    protected $table      = 'audit_logs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    // Write-only log — no auto-updated_at
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $updatedField  = false;

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

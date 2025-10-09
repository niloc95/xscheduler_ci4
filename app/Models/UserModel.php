<?php

namespace App\Models;

use App\Models\BaseModel;

class UserModel extends BaseModel
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'name', 'email', 'phone', 'password_hash', 'role', 'permissions',
        'provider_id', 'status', 'last_login', 'is_active', 'reset_token', 'reset_expires'
    ];

    // Dates (handled by BaseModel)

    // Validation
    protected $validationRules      = [
        'name'  => 'required|min_length[2]|max_length[255]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'role'  => 'required|in_list[admin,provider,staff,receptionist,customer]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;


    /**
     * Get user statistics for dashboard
     */
    public function getStats()
    {
        return [
            'total' => $this->countAll(),
            'providers' => $this->where('role', 'provider')->countAllResults(false),
            'staff' => $this->where('role', 'staff')->countAllResults(false),
            'admins' => $this->where('role', 'admin')->countAllResults(false),
            'recent' => $this->where('created_at >=', date('Y-m-d', strtotime('-30 days')))->countAllResults()
        ];
    }

    /**
     * Get recent user registrations
     */
    public function getRecentUsers($limit = 5)
    {
        return $this->select('id, name, email, role, created_at')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get user growth data for charts
     */
    public function getUserGrowthData($months = 6)
    {
        $data = [];
        $labels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-{$i} months"));
            $nextDate = date('Y-m-01', strtotime("-" . ($i - 1) . " months"));
            $count = $this->where('created_at >=', $date)
                          ->where('created_at <', $nextDate)
                          ->countAllResults(false);
            $labels[] = date('M', strtotime($date));
            $data[] = $count;
        }
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get the first admin user (for dashboard display when no session)
     */
    public function getFirstAdmin()
    {
        return $this->where('role', 'admin')->first();
    }

    /**
     * Get users by role with active status
     */
    public function getUsersByRole(string $role): array
    {
        return $this->where('role', $role)
                    ->where('is_active', true)
                    ->findAll();
    }

    /**
     * Get staff for a specific provider
     */
    public function getStaffForProvider(int $providerId): array
    {
        return $this->where('role', 'staff')
                    ->where('provider_id', $providerId)
                    ->where('is_active', true)
                    ->findAll();
    }

    /**
     * Get all providers (admin and provider roles)
     */
    public function getProviders(): array
    {
        return $this->whereIn('role', ['admin', 'provider'])
                    ->where('is_active', true)
                    ->findAll();
    }

    /**
     * Check if user can be managed by another user
     */
    public function canManageUser(int $managerId, int $targetUserId): bool
    {
        $manager = $this->find($managerId);
        $target = $this->find($targetUserId);
        if (!$manager || !$target) {
            return false;
        }
        // Admins can manage everyone
        if ($manager['role'] === 'admin') {
            return true;
        }
        // Providers can manage their own staff
        if ($manager['role'] === 'provider' && $target['role'] === 'staff') {
            return $target['provider_id'] === $manager['id'];
        }
        // Users can manage themselves (limited)
        if ($managerId === $targetUserId) {
            return true;
        }
        return false;
    }

    /**
     * Create a new user with role validation
     */
    public function createUser(array $userData): int|false
    {
        // Set default values
        $userData['is_active'] = $userData['is_active'] ?? true;
        $userData['role'] = $userData['role'] ?? 'staff';
        // Hash password if provided
        if (isset($userData['password'])) {
            $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            unset($userData['password']);
        }
        return $this->insert($userData, false);
    }

    /**
     * Update user with permission check
     */
    public function updateUser(int $userId, array $userData, int $updatedBy): bool
    {
        if (!$this->canManageUser($updatedBy, $userId)) {
            return false;
        }
        // Hash password if provided
        if (isset($userData['password'])) {
            $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            unset($userData['password']);
        }
        return $this->update($userId, $userData);
    }

    /**
     * Deactivate user (soft delete alternative)
     */
    public function deactivateUser(int $userId, int $deactivatedBy): bool
    {
        if (!$this->canManageUser($deactivatedBy, $userId)) {
            return false;
        }
        return $this->update($userId, ['is_active' => false]);
    }

    /**
     * Get user with role-based visibility
     */
    public function getUserWithVisibility(int $userId, int $requesterId): ?array
    {
        $user = $this->find($userId);
        $requester = $this->find($requesterId);
        if (!$user || !$requester) {
            return null;
        }
        // Check if requester can view this user
        if (!$this->canViewUser($requesterId, $userId)) {
            return null;
        }
        // Remove sensitive information based on role
        if ($requester['role'] !== 'admin' && $requesterId !== $userId) {
            unset($user['password_hash'], $user['reset_token'], $user['reset_expires']);
        }
        return $user;
    }

    /**
     * Check if user can view another user
     */
    private function canViewUser(int $viewerId, int $targetUserId): bool
    {
        $viewer = $this->find($viewerId);
        $target = $this->find($targetUserId);
        if (!$viewer || !$target) {
            return false;
        }
        // Admins can view everyone
        if ($viewer['role'] === 'admin') {
            return true;
        }
        // Providers can view their staff
        if ($viewer['role'] === 'provider' && $target['role'] === 'staff') {
            return $target['provider_id'] === $viewer['id'];
        }
        // Users can view themselves
        if ($viewerId === $targetUserId) {
            return true;
        }
        // Staff can view their provider
        if ($viewer['role'] === 'staff' && $target['role'] === 'provider') {
            return $viewer['provider_id'] === $target['id'];
        }
        return false;
    }
}

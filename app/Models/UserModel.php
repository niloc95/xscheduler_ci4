<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\ProviderStaffModel;

class UserModel extends BaseModel
{
    protected $table            = 'xs_users';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'name', 'email', 'phone', 'password_hash', 'role', 'permissions',
        'provider_id', // DEPRECATED: Use xs_provider_staff_assignments pivot table instead
        'status', 'last_login', 'is_active', 'reset_token', 'reset_expires',
        'profile_image', 'color'
    ];

    // Model callbacks to ensure provider color is always assigned
    protected $beforeInsert = ['ensureProviderColor'];
    protected $beforeUpdate = ['ensureProviderColorOnUpdate'];

    // Dates (handled by BaseModel)

    // Validation
    protected $validationRules      = [
        'name'  => 'required|min_length[2]|max_length[255]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'role'  => 'required|in_list[admin,provider,staff,customer]'
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
        $assignments = new ProviderStaffModel();
        return $assignments->getStaffByProvider($providerId);
    }

    public function getProvidersForStaff(int $staffId): array
    {
        $assignments = new ProviderStaffModel();
        return $assignments->getProvidersForStaff($staffId);
    }

    /**
     * Get all providers (admin and provider roles)
     */
    public function getProviders(): array
    {
        return $this->where('role', 'provider')
                    ->where('is_active', true)
                    ->findAll();
    }

    /**
     * Callback: Ensure a provider has a color on insert
     */
    protected function ensureProviderColor(array $data): array
    {
        try {
            $row = $data['data'] ?? [];
            $role = $row['role'] ?? null;
            $hasColor = isset($row['color']) && is_string($row['color']) && trim($row['color']) !== '';
            if ($role === 'provider' && !$hasColor) {
                $data['data']['color'] = $this->getAvailableProviderColor();
            }
        } catch (\Throwable $e) {
            // Fail-safe: don't block insert on color assignment issues
            log_message('warning', '[UserModel::ensureProviderColor] Failed to assign provider color: ' . $e->getMessage());
        }
        return $data;
    }

    /**
     * Callback: Ensure a provider has a color on update when role becomes provider or color cleared
     */
    protected function ensureProviderColorOnUpdate(array $data): array
    {
        try {
            $row = $data['data'] ?? [];
            // Determine the target role: prefer provided role in update payload, else existing role
            $id = $data['id'][0] ?? ($row['id'] ?? null);
            $targetRole = $row['role'] ?? null;
            if (!$targetRole && $id) {
                $existing = $this->find((int)$id);
                $targetRole = $existing['role'] ?? null;
                // If color missing in payload but existing has none, assign
                $existingColor = $existing['color'] ?? null;
                if ($targetRole === 'provider' && (!isset($row['color']) || trim((string)$row['color']) === '')) {
                    if (!$existingColor) {
                        $data['data']['color'] = $this->getAvailableProviderColor();
                    }
                }
            }
            // If payload explicitly sets role to provider and no color provided, assign
            $hasColorInPayload = isset($row['color']) && is_string($row['color']) && trim($row['color']) !== '';
            if ($targetRole === 'provider' && !$hasColorInPayload) {
                // Only assign if color not previously set
                if (!isset($data['data']['color'])) {
                    $data['data']['color'] = $this->getAvailableProviderColor();
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[UserModel::ensureProviderColorOnUpdate] Failed to assign provider color on update: ' . $e->getMessage());
        }
        return $data;
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
            $assignments = new ProviderStaffModel();
            return $assignments->isStaffAssignedToProvider($targetUserId, $manager['id']);
        }
        // Users can manage themselves (limited)
        if ($managerId === $targetUserId) {
            return true;
        }
        return false;
    }

    /**
     * Create a new user with hashed password
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
        
        // Insert and return the new user ID
        $result = $this->insert($userData, true); // true = return insert ID
        
        return $result !== false ? (int) $this->getInsertID() : false;
    }

    /**
     * Update user with permission check
     */
    public function updateUser(int $userId, array $userData, int $updatedBy): bool
    {
        log_message('debug', "updateUser called: userId={$userId}, updatedBy={$updatedBy}, userData=" . json_encode($userData));
        
        if (!$this->canManageUser($updatedBy, $userId)) {
            log_message('error', "updateUser permission denied: updatedBy={$updatedBy} cannot manage userId={$userId}");
            return false;
        }
        
        // Get current user data for comparison
        $currentUser = $this->find($userId);
        log_message('debug', "updateUser - Current user data: " . json_encode($currentUser));
        
        // Hash password if provided
        if (isset($userData['password'])) {
            $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            unset($userData['password']);
            log_message('debug', "updateUser - Password was changed");
        }
        
        log_message('debug', "updateUser calling model update with data: " . json_encode($userData));
        
        // Skip model validation since controller already validated with correct user ID
        // Model validation rules use {id} placeholder which doesn't work properly in update context
        $this->skipValidation(true);
        $result = $this->update($userId, $userData);
        $this->skipValidation(false);
        
        log_message('debug', "updateUser - update() returned: " . ($result ? 'true' : 'false'));
        
        // Verify the update actually happened
        $updatedUser = $this->find($userId);
        log_message('debug', "updateUser - User after update: " . json_encode($updatedUser));
        
        if (!$result) {
            log_message('error', "updateUser failed: " . json_encode($this->errors()));
        } else {
            // Check if data actually changed
            $changed = false;
            foreach ($userData as $key => $value) {
                if (isset($updatedUser[$key]) && $updatedUser[$key] != $value) {
                    $changed = true;
                    log_message('warning', "updateUser - Field {$key} did not update! Expected: {$value}, Got: {$updatedUser[$key]}");
                }
            }
            if (!$changed) {
                log_message('debug', "updateUser successful for userId={$userId}");
            }
        }
        
        return $result;
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
            $assignments = new ProviderStaffModel();
            return $assignments->isStaffAssignedToProvider($targetUserId, $viewer['id']);
        }
        // Users can view themselves
        if ($viewerId === $targetUserId) {
            return true;
        }
        // Staff can view their provider
        if ($viewer['role'] === 'staff' && $target['role'] === 'provider') {
            $assignments = new ProviderStaffModel();
            return $assignments->isStaffAssignedToProvider($viewerId, $targetUserId);
        }
        return false;
    }

    /**
     * Get an available provider color
     * Tries to avoid duplicates by selecting least-used color from palette
     * 
     * @return string Hex color code
     */
    public function getAvailableProviderColor(): string
    {
        // Predefined color palette - vibrant, distinguishable colors
        $colorPalette = [
            '#3B82F6', // Blue
            '#10B981', // Green
            '#F59E0B', // Amber
            '#EF4444', // Red
            '#8B5CF6', // Purple
            '#EC4899', // Pink
            '#06B6D4', // Cyan
            '#F97316', // Orange
            '#84CC16', // Lime
            '#6366F1', // Indigo
            '#14B8A6', // Teal
            '#F43F5E', // Rose
        ];

        // Get color usage count for active providers
        $colorUsage = [];
        $providers = $this->where('role', 'provider')
                          ->where('is_active', true)
                          ->select('color')
                          ->findAll();

        // Count occurrences of each color
        foreach ($providers as $provider) {
            if ($provider['color']) {
                $colorUsage[$provider['color']] = ($colorUsage[$provider['color']] ?? 0) + 1;
            }
        }

        // Find least-used color from palette
        $leastUsedColor = null;
        $minCount = PHP_INT_MAX;
        
        foreach ($colorPalette as $color) {
            $count = $colorUsage[$color] ?? 0;
            if ($count < $minCount) {
                $minCount = $count;
                $leastUsedColor = $color;
            }
        }

        return $leastUsedColor ?? $colorPalette[0];
    }
}

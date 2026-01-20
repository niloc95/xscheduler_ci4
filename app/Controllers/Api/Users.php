<?php

namespace App\Controllers\Api;

use App\Models\UserModel;

/**
 * Users API Controller
 * 
 * Provides user listing and counts.
 */
class Users extends BaseApiController
{
    protected UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    /**
     * GET /api/user-counts
     * 
     * Get counts of users by role.
     */
    public function counts()
    {
        try {
            // IMPORTANT: countAllResults(false) does NOT reset builder; previous version caused cascading WHERE conditions
            // Use default reset (true) to ensure independent counts
            $counts = [];
            $counts['admins']    = (int)$this->users->where('role', 'admin')->countAllResults();
            $counts['providers'] = (int)$this->users->where('role', 'provider')->countAllResults();
            $counts['staff']     = (int)$this->users->where('role', 'staff')->countAllResults();
            // Customers are managed separately; exclude from totals
            $counts['total'] = $counts['admins'] + $counts['providers'] + $counts['staff'];
            
            return $this->ok($counts);
        } catch (\Throwable $e) {
            return $this->serverError('Failed to fetch user counts', $e->getMessage());
        }
    }

    /**
     * GET /api/users?role=provider|admin|staff
     * 
     * List users with optional role filter.
     */
    public function index()
    {
        $role  = $this->request->getGet('role');
        $q     = $this->request->getGet('q');
        $limit = (int)($this->request->getGet('limit') ?? 50);
        
        try {
            if ($role === 'staff') {
                $users = $this->users->where('role', 'staff')
                    ->like('name', $q ?? '', 'both')
                    ->limit($limit)
                    ->find();
            } elseif (in_array((string)$role, ['admin', 'provider'], true)) {
                $users = $this->users->where('role', $role)
                    ->like('name', $q ?? '', 'both')
                    ->limit($limit)
                    ->find();
            } else {
                // All users
                $users = $this->users->like('name', $q ?? '', 'both')
                    ->limit($limit)
                    ->find();
            }
            
            // Add type marker for frontend
            $users = array_map(fn($u) => $u + ['_type' => 'user'], $users);
            
            return $this->ok($users, [
                'role' => $role ?: 'all',
                'count' => count($users),
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('Failed to fetch users', $e->getMessage());
        }
    }
}

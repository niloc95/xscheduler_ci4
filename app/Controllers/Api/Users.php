<?php

/**
 * =============================================================================
 * USERS API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/Users.php
 * @description API for user listing and role-based counts used by
 *              dashboard widgets and user selection dropdowns.
 * 
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET  /api/users                         : List users (with role filter)
 * GET  /api/user-counts                   : Get user counts by role
 * GET  /api/users/:id                     : Get specific user details
 * 
 * QUERY PARAMETERS (GET /api/users):
 * -----------------------------------------------------------------------------
 * - role         : Filter by role (admin, provider, staff)
 * - is_active    : Filter by active status
 * - search       : Search by name or email
 * - page         : Page number for pagination
 * - per_page     : Items per page
 * 
 * COUNTS RESPONSE:
 * -----------------------------------------------------------------------------
 * {
 *   "data": {
 *     "admins": 2,
 *     "providers": 5,
 *     "staff": 12,
 *     "total": 19
 *   }
 * }
 * 
 * NOTE: Customers are managed separately in CustomerModel and
 * are excluded from these counts.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Supports various UI components:
 * - Dashboard user count widgets
 * - Provider selection dropdowns
 * - Staff assignment lists
 * - User management filters
 * 
 * @see         app/Models/UserModel.php for data layer
 * @see         app/Controllers/UserManagement.php for full CRUD
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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

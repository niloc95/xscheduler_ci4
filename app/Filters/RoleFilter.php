<?php

/**
 * =============================================================================
 * ROLE FILTER
 * =============================================================================
 * 
 * @file        app/Filters/RoleFilter.php
 * @description HTTP middleware for role-based access control. Checks user
 *              roles and permissions before allowing route access.
 * 
 * FILTER ALIAS: 'role'
 * 
 * USAGE IN ROUTES:
 * -----------------------------------------------------------------------------
 * Single role:
 *     ['filter' => 'role:admin']
 * 
 * Multiple roles (OR):
 *     ['filter' => 'role:admin,provider']
 * 
 * Permission check:
 *     ['filter' => 'role:permission:manage_users']
 * 
 * BEHAVIOR:
 * -----------------------------------------------------------------------------
 * Before Request:
 * 1. Verify user is authenticated (session check)
 * 2. Get user's role from session
 * 3. Check if role matches required roles
 * 4. Or check specific permission if 'permission:' prefix used
 * 5. If unauthorized: return 403 or redirect with error
 * 
 * EXAMPLES:
 * -----------------------------------------------------------------------------
 *     // Admin only
 *     $routes->get('/admin/users', 'UserManagement::index',
 *         ['filter' => 'role:admin']);
 * 
 *     // Admin or provider
 *     $routes->get('/calendar', 'Calendar::index',
 *         ['filter' => 'role:admin,provider']);
 * 
 *     // Specific permission
 *     $routes->post('/backup', 'Backup::create',
 *         ['filter' => 'role:permission:backup_restore']);
 * 
 * @see         app/Config/Filters.php for filter configuration
 * @see         app/Models/UserPermissionModel.php for permission definitions
 * @package     App\Filters
 * @implements  FilterInterface
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Filters;

use App\Models\UserPermissionModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    protected $permissionModel;

    public function __construct()
    {
        $this->permissionModel = new UserPermissionModel();
    }

    /**
     * Check user role and permissions
     * 
     * Usage in routes:
     * ['filter' => 'role:admin']
     * ['filter' => 'role:admin,provider']
     * ['filter' => 'role:permission:manage_users']
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if user is logged in first
        if (!session()->get('isLoggedIn')) {
            // Check if this is an API request
            if ($this->isApiRequest()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['error' => ['message' => 'Authentication required. Please log in.', 'code' => 'unauthenticated']]);
            }
            session()->set('redirect_url', current_url());
            return redirect()->to('/auth/login')->with('error', 'Please log in to access this page.');
        }

        $userId = session()->get('user_id');
        $user = session()->get('user');

        if (!$userId || !$user) {
            // Check if this is an API request
            if ($this->isApiRequest()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['error' => ['message' => 'Invalid session. Please log in again.', 'code' => 'session_invalid']]);
            }
            return redirect()->to('/auth/login')->with('error', 'Invalid session. Please log in again.');
        }

        // If no arguments provided, just check if logged in (already done above)
        if (empty($arguments)) {
            return;
        }

        // Parse arguments
        $requiredRoles = [];
        $requiredPermissions = [];
        $checkType = 'role'; // Default to role check

        foreach ($arguments as $arg) {
            if ($arg === 'permission') {
                $checkType = 'permission';
                continue;
            }
            
            if ($checkType === 'role') {
                $requiredRoles[] = $arg;
            } else {
                $requiredPermissions[] = $arg;
            }
        }

        // Check roles
        if (!empty($requiredRoles)) {
            if (!in_array($user['role'], $requiredRoles)) {
                return $this->unauthorizedResponse();
            }
        }

        // Check permissions
        if (!empty($requiredPermissions)) {
            if (!$this->permissionModel->hasAnyPermission($userId, $requiredPermissions)) {
                return $this->unauthorizedResponse();
            }
        }

        // All checks passed
        return;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse()
    {
        // Check if this is an API request
        if (service('request')->isAJAX() || service('request')->getHeaderLine('Accept') === 'application/json') {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['error' => ['message' => 'Access denied. Insufficient permissions.', 'code' => 'forbidden']]);
        }

        // Web request - redirect to dashboard with error
        return redirect()->to('/dashboard')->with('error', 'Access denied. You do not have permission to access that resource.');
    }

    /**
     * Check if this is an API/AJAX request
     */
    private function isApiRequest(): bool
    {
        $request = service('request');
        return $request->isAJAX() 
            || $request->getHeaderLine('Accept') === 'application/json'
            || str_starts_with($request->getPath(), 'api/');
    }
}

<?php

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

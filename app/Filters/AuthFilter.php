<?php

namespace App\Filters;

use App\Models\UserPermissionModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    protected $permissionModel;

    public function __construct()
    {
        $this->permissionModel = new UserPermissionModel();
    }

    /**
     * Enhanced authentication with basic role/permission support
     * 
     * @param RequestInterface $request
     * @param array|null       $arguments - Optional: roles or permissions to check
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if user is logged in
        if (!session()->get('isLoggedIn')) {
            // Store intended URL for redirect after login
            session()->set('redirect_url', current_url());
            
            // Redirect to login page
            return redirect()->to('/auth/login')->with('error', 'Please log in to access this page.');
        }

        // If arguments are provided, do additional role/permission checks
        if (!empty($arguments)) {
            $userId = session()->get('user_id');
            $user = session()->get('user');

            if (!$userId || !$user) {
                return redirect()->to('/auth/login')->with('error', 'Invalid session. Please log in again.');
            }

            // Simple role check if arguments are role names (customers are no longer users)
            $validRoles = ['admin', 'provider', 'staff'];
            $hasValidRole = false;

            foreach ($arguments as $arg) {
                if (in_array($arg, $validRoles) && $user['role'] === $arg) {
                    $hasValidRole = true;
                    break;
                }
            }

            // If we're doing role checks and user doesn't have required role
            if (!empty(array_intersect($arguments, $validRoles)) && !$hasValidRole) {
                return $this->unauthorizedResponse();
            }
        }

        return;
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
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
}

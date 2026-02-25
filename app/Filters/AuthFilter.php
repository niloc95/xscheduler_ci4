<?php

/**
 * =============================================================================
 * AUTHENTICATION FILTER
 * =============================================================================
 * 
 * @file        app/Filters/AuthFilter.php
 * @description HTTP middleware for session-based authentication. Validates
 *              that users are logged in before accessing protected routes.
 * 
 * FILTER ALIAS: 'auth'
 * 
 * ROUTES PROTECTED:
 * -----------------------------------------------------------------------------
 * Applied to all routes requiring authentication:
 * - /dashboard/*
 * - /appointments/*
 * - /settings/*
 * - /user-management/*
 * - /profile/*
 * - etc.
 * 
 * BEHAVIOR:
 * -----------------------------------------------------------------------------
 * Before Request:
 * 1. Check session for 'isLoggedIn' flag
 * 2. If not logged in: redirect to /auth/login
 * 3. Optionally check roles/permissions if arguments provided
 * 4. If authenticated: continue to controller
 * 
 * USAGE IN ROUTES:
 * -----------------------------------------------------------------------------
 *     $routes->get('/dashboard', 'Dashboard::index', ['filter' => 'auth']);
 *     $routes->group('admin', ['filter' => 'auth'], function($routes) {
 *         $routes->get('users', 'UserManagement::index');
 *     });
 * 
 * SESSION DATA CHECKED:
 * -----------------------------------------------------------------------------
 * - isLoggedIn : boolean flag set on successful login
 * - user_id    : ID of authenticated user
 * - user       : Array with user details (name, email, role)
 * 
 * @see         app/Config/Filters.php for filter configuration
 * @see         app/Controllers/Auth.php for login handling
 * @package     App\Filters
 * @implements  FilterInterface
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
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
            // For AJAX/API requests, return JSON 401 instead of redirect
            if ($request instanceof \CodeIgniter\HTTP\IncomingRequest
                && ($request->isAJAX() || $request->getHeaderLine('Accept') === 'application/json')
            ) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['error' => ['message' => 'Session expired. Please log in again.', 'code' => 'unauthenticated']]);
            }

            // Store intended URL for redirect after login
            session()->set('redirect_url', current_url());
            
            // Redirect to login page
            return redirect()->to(base_url('auth/login'))->with('error', 'Please log in to access this page.');
        }

        // If arguments are provided, do additional role/permission checks
        if (!empty($arguments)) {
            $userId = session()->get('user_id');
            $user = session()->get('user');

            if (!$userId || !$user) {
                return redirect()->to(base_url('auth/login'))->with('error', 'Invalid session. Please log in again.');
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
        return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. You do not have permission to access that resource.');
    }
}

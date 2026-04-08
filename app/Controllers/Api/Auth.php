<?php

/**
 * =============================================================================
 * AUTHENTICATION API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/Auth.php
 * @description API endpoints for authentication operations.
 * 
 * ROUTES HANDLED:
 * POST /api/auth/switch-role            : Switch active role for current user
 * 
 * @see         app/Controllers/Auth.php for primary auth controller
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 */

namespace App\Controllers\Api;

class Auth extends BaseApiController
{
    /**
     * Switch the current user's active role
     * 
     * Allows users with multiple roles to switch their active role context.
     * The new active role must be one of their assigned roles.
     * 
     * @return \CodeIgniter\HTTP\Response JSON response
     */
    public function switchRole()
    {
        $user = session()->get('user');
        $userId = session()->get('user_id');
        
        if (!$userId || !$user) {
            return $this->unauthorized('User not authenticated');
        }

        // Get the requested role from the request body
        $requestBody = $this->request->getJSON();
        $newRole = $requestBody->role ?? null;

        if (!$newRole) {
            return $this->badRequest('Missing required field: role');
        }

        // Validate the requested role
        $validRoles = ['admin', 'provider', 'staff'];
        if (!in_array($newRole, $validRoles, true)) {
            return $this->badRequest('Invalid role: ' . $newRole);
        }

        // Check if the user has this role
        $availableRoles = $user['roles'] ?? [];
        if (!in_array($newRole, $availableRoles, true)) {
            return $this->forbidden('You do not have access to the ' . $newRole . ' role');
        }

        // Update the session with the new active role
        $user['active_role'] = $newRole;
        session()->set('user', $user);

        return $this->ok([
            'active_role' => $newRole,
            'roles' => $availableRoles,
            'message' => 'Role switched successfully'
        ]);
    }
}

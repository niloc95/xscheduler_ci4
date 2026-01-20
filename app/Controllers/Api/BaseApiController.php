<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

/**
 * Base API Controller
 * 
 * Provides standardized response methods for all API controllers.
 * All API responses follow a consistent JSON structure:
 * 
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "error": { "message": "...", "code": "...", "details": {...} } }
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */
class BaseApiController extends BaseController
{
    /**
     * Return a successful response (HTTP 200)
     * 
     * @param mixed $data Response data
     * @param array $meta Optional metadata (pagination, timestamps, etc.)
     * @return \CodeIgniter\HTTP\Response
     */
    protected function ok($data = null, array $meta = [])
    {
        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setJSON([
                'data' => $data,
                'meta' => (object) $meta,
            ]);
    }

    /**
     * Return a created response (HTTP 201)
     * 
     * @param mixed $data Created resource data
     * @param array $meta Optional metadata
     * @return \CodeIgniter\HTTP\Response
     */
    protected function created($data = null, array $meta = [])
    {
        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setStatusCode(201)
            ->setJSON([
                'data' => $data,
                'meta' => (object) $meta,
            ]);
    }

    /**
     * Return an error response
     * 
     * @param int $status HTTP status code
     * @param string $message Human-readable error message
     * @param string|null $code Machine-readable error code
     * @param mixed $details Additional error details
     * @return \CodeIgniter\HTTP\Response
     */
    protected function error(int $status, string $message, ?string $code = null, $details = null)
    {
        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setStatusCode($status)
            ->setJSON([
                'error' => [
                    'message' => $message,
                    'code' => $code,
                    'details' => $details,
                ],
            ]);
    }

    /**
     * Return a not found error (HTTP 404)
     * 
     * @param string $message Error message
     * @return \CodeIgniter\HTTP\Response
     */
    protected function notFound(string $message = 'Resource not found')
    {
        return $this->error(404, $message, 'NOT_FOUND');
    }

    /**
     * Return an unauthorized error (HTTP 401)
     * 
     * @param string $message Error message
     * @return \CodeIgniter\HTTP\Response
     */
    protected function unauthorized(string $message = 'Authentication required')
    {
        return $this->error(401, $message, 'UNAUTHORIZED');
    }

    /**
     * Return a forbidden error (HTTP 403)
     * 
     * @param string $message Error message
     * @return \CodeIgniter\HTTP\Response
     */
    protected function forbidden(string $message = 'Access denied')
    {
        return $this->error(403, $message, 'FORBIDDEN');
    }

    /**
     * Return a validation error (HTTP 422)
     * 
     * @param array|string $errors Validation errors
     * @return \CodeIgniter\HTTP\Response
     */
    protected function validationError($errors)
    {
        return $this->error(422, 'Validation failed', 'VALIDATION_ERROR', $errors);
    }

    /**
     * Return a bad request error (HTTP 400)
     * 
     * @param string $message Error message
     * @param mixed $details Additional details
     * @return \CodeIgniter\HTTP\Response
     */
    protected function badRequest(string $message, $details = null)
    {
        return $this->error(400, $message, 'BAD_REQUEST', $details);
    }

    /**
     * Return a server error (HTTP 500)
     * 
     * @param string $message Error message
     * @param mixed $details Additional details (hidden in production)
     * @return \CodeIgniter\HTTP\Response
     */
    protected function serverError(string $message = 'Internal server error', $details = null)
    {
        // Only show details in development
        $showDetails = ENVIRONMENT !== 'production' ? $details : null;
        return $this->error(500, $message, 'SERVER_ERROR', $showDetails);
    }

    /**
     * Extract and validate pagination parameters from request
     * 
     * @param int $maxLength Maximum items per page
     * @return array [page, length, offset]
     */
    protected function paginationParams(int $maxLength = 100): array
    {
        $req = $this->request;
        $page = max(1, (int) ($req->getGet('page') ?? 1));
        $length = (int) ($req->getGet('length') ?? $req->getGet('per_page') ?? 25);
        
        if ($length < 1) $length = 25;
        if ($length > $maxLength) $length = $maxLength;
        
        $offset = ($page - 1) * $length;
        
        return [$page, $length, $offset];
    }

    /**
     * Extract and validate sort parameters from request
     * 
     * @param array $allowed Allowed sort field names
     * @param string $default Default sort field:direction
     * @return array [field, direction]
     */
    protected function sortParam(array $allowed, string $default): array
    {
        $sortRaw = (string) ($this->request->getGet('sort') ?? $default);
        $parts = explode(':', $sortRaw, 2);
        $field = $parts[0] ?? $default;
        $dir = strtolower($parts[1] ?? 'asc');
        
        if (!in_array($field, $allowed, true)) {
            $defaultParts = explode(':', $default, 2);
            $field = $defaultParts[0];
        }
        
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }
        
        return [$field, $dir];
    }

    /**
     * Build pagination metadata for response
     * 
     * @param int $page Current page
     * @param int $length Items per page
     * @param int $total Total items
     * @param string $sort Sort field:direction
     * @return array
     */
    protected function paginationMeta(int $page, int $length, int $total, string $sort = ''): array
    {
        return [
            'page' => $page,
            'per_page' => $length,
            'total' => $total,
            'total_pages' => (int) ceil($total / $length),
            'sort' => $sort,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Check if current user is authenticated
     * 
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        return (bool) session()->get('isLoggedIn');
    }

    /**
     * Get current authenticated user
     * 
     * @return array|null
     */
    protected function currentUser(): ?array
    {
        return session()->get('user');
    }

    /**
     * Check if current user has a specific role
     * 
     * @param string|array $roles Role or array of roles
     * @return bool
     */
    protected function hasRole($roles): bool
    {
        $user = $this->currentUser();
        if (!$user) return false;
        
        $userRole = $user['role'] ?? '';
        
        if (is_array($roles)) {
            return in_array($userRole, $roles, true);
        }
        
        return $userRole === $roles;
    }

    /**
     * Require authentication, return error response if not authenticated
     * 
     * @return \CodeIgniter\HTTP\Response|null Returns error response or null if authenticated
     */
    protected function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            return $this->unauthorized();
        }
        return null;
    }

    /**
     * Require specific role(s), return error response if not authorized
     * 
     * @param string|array $roles Required role(s)
     * @return \CodeIgniter\HTTP\Response|null Returns error response or null if authorized
     */
    protected function requireRole($roles)
    {
        if ($authError = $this->requireAuth()) {
            return $authError;
        }
        
        if (!$this->hasRole($roles)) {
            return $this->forbidden('Insufficient permissions');
        }
        
        return null;
    }
}

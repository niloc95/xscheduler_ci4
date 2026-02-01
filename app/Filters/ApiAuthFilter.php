<?php

/**
 * =============================================================================
 * API AUTHENTICATION FILTER
 * =============================================================================
 * 
 * @file        app/Filters/ApiAuthFilter.php
 * @description HTTP middleware for API endpoint authentication. Supports both
 *              session-based and Bearer token authentication.
 * 
 * FILTER ALIAS: 'api_auth'
 * 
 * ROUTES PROTECTED:
 * -----------------------------------------------------------------------------
 * Applied to all /api/* routes requiring authentication.
 * 
 * AUTHENTICATION METHODS:
 * -----------------------------------------------------------------------------
 * 1. Session Auth (same-origin):
 *    - Checks for active session with isLoggedIn=true
 *    - Used by internal UI making AJAX calls
 *    - No Authorization header needed
 * 
 * 2. Bearer Token:
 *    - Authorization: Bearer <token>
 *    - Token validated against configured API key
 *    - Used by external integrations
 * 
 * BEHAVIOR:
 * -----------------------------------------------------------------------------
 * Before Request:
 * 1. Check for active session (same-origin requests)
 * 2. If no session, check for Bearer token
 * 3. Validate token against Api config
 * 4. If valid: continue to controller
 * 5. If invalid: return 401 JSON response
 * 
 * RESPONSE ON FAILURE:
 * -----------------------------------------------------------------------------
 * {
 *   "error": {
 *     "message": "Unauthorized",
 *     "code": "AUTH_REQUIRED"
 *   }
 * }
 * 
 * @see         app/Config/Api.php for API configuration
 * @see         app/Config/Filters.php for filter setup
 * @package     App\Filters
 * @implements  FilterInterface
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Filters;

use App\Config\Api as ApiConfig;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Allow same-origin requests from logged-in users (session-based auth)
        // This enables internal UI (e.g., Settings page) to call API without a Bearer token.
        try {
            if (function_exists('session')) {
                $sess = session();
                if ($sess && $sess->get('isLoggedIn')) {
                    return; // Authorized by session
                }
            }
        } catch (\Throwable $e) {
            // Fall through to token checks
        }

        $config = config(ApiConfig::class);
        $auth = $request->getHeaderLine('Authorization');

        // Bearer token
        if (preg_match('/^Bearer\s+(.*)$/i', $auth ?? '', $m)) {
            $token = trim($m[1]);
            $allowed = $config->bearerTokens;
            if ($env = $config->envBearerToken()) {
                $allowed[] = $env;
            }
            if ($token !== '' && in_array($token, $allowed, true)) {
                return; // Authorized
            }
        }

        // Basic auth (dev/demo)
        if (preg_match('/^Basic\s+(.*)$/i', $auth ?? '', $m)) {
            $decoded = base64_decode($m[1], true) ?: '';
            [$u, $p] = array_pad(explode(':', $decoded, 2), 2, '');
            if ($u !== '' && isset($config->basicUsers[$u]) && hash_equals((string)$config->basicUsers[$u], $p)) {
                return; // Authorized
            }
        }

        // Unauthorized
        return service('response')
            ->setStatusCode(401)
            ->setJSON(['error' => ['message' => 'Unauthorized', 'code' => 'unauthorized']]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }
}

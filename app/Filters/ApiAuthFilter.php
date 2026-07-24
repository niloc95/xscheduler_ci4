<?php

/**
 * =============================================================================
 * API AUTHENTICATION FILTER
 * =============================================================================
 *
 * @file        app/Filters/ApiAuthFilter.php
 * @description HTTP middleware for API endpoint authentication. Supports both
 *              session-based (same-origin UI) and Bearer token (external
 *              client) authentication.
 *
 * FILTER ALIAS: 'api_auth'
 *
 * USAGE:
 * -----------------------------------------------------------------------------
 *     ['filter' => 'api_auth']                 // any authenticated caller
 *     ['filter' => 'api_auth:admin,provider']  // plus a role requirement
 *
 * AUTHENTICATION METHODS:
 * -----------------------------------------------------------------------------
 * 1. Bearer Token (external integrations):
 *    - Authorization: Bearer xsk_<prefix>_<secret>
 *    - Verified against xs_api_keys (prefix lookup + password_verify)
 *    - The key is bound to an xs_users row; that user's authoritative roles are
 *      loaded into ApiIdentity so requireRole(), provider scoping and
 *      current_business_id() behave exactly as they do for session callers.
 *
 * 2. Session Auth (same-origin UI):
 *    - Active session with isLoggedIn=true
 *    - Used by the SPA's own XHRs; no Authorization header involved
 *
 * PRECEDENCE (important):
 * -----------------------------------------------------------------------------
 * If an Authorization header is present it *decides* the request. A bad token
 * is a 401 even when the caller also holds a valid session cookie. The filter
 * previously short-circuited on the session before ever reading the header,
 * which let a browser session silently mask an invalid token.
 *
 * RESPONSE ON FAILURE:
 * -----------------------------------------------------------------------------
 * 401 { "error": { "message": "Unauthorized", "code": "unauthorized" } }
 * 403 { "error": { "message": "Insufficient permissions", "code": "forbidden" } }
 *
 * @see         app/Models/ApiKeyModel.php for the token format and verification
 * @see         app/Services/ApiIdentity.php for the resulting request identity
 * @see         app/Config/Filters.php for filter setup
 * @package     App\Filters
 * @implements  FilterInterface
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Filters;

use App\Models\ApiKeyModel;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    /** Requests allowed per key per window. */
    private const RATE_LIMIT = 120;

    /** Rate limit window, in seconds. */
    private const RATE_WINDOW = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = trim((string) $request->getHeaderLine('Authorization'));

        if ($auth !== '') {
            // An Authorization header decides the request outright — never fall
            // back to the session, or a browser cookie masks a bad token.
            $result = $this->authenticateToken($request, $auth);
            if ($result instanceof ResponseInterface) {
                return $result;
            }

            return $this->enforceRoles($arguments, $result['roles']);
        }

        if ($this->hasSession()) {
            $sessionUser = session()->get('user');
            $sessionUser = is_array($sessionUser) ? $sessionUser : [];
            // §4.4 Canonical RBAC: authoritative roles array, single role only as fallback.
            $roles = $sessionUser['roles'] ?? [$sessionUser['active_role'] ?? $sessionUser['role'] ?? ''];

            return $this->enforceRoles($arguments, is_array($roles) ? $roles : [$roles]);
        }

        return $this->unauthorized();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }

    /**
     * Verify a Bearer token and populate the request identity.
     *
     * @return array{roles: array<int, string>}|ResponseInterface Roles on success, an error response otherwise.
     */
    private function authenticateToken(RequestInterface $request, string $auth)
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return $this->unauthorized();
        }

        $token = trim($matches[1]);
        if ($token === '') {
            return $this->unauthorized();
        }

        $keyModel = new ApiKeyModel();
        $key      = $keyModel->verify($token);

        if ($key === null) {
            $this->logDenied('api.token_rejected', $request, ['reason' => 'invalid_or_expired']);
            return $this->unauthorized();
        }

        if ($limited = $this->enforceRateLimit((string) $key['key_prefix'])) {
            return $limited;
        }

        $userModel = new UserModel();
        $user      = $userModel->find((int) $key['user_id']);

        // A revoked or deactivated user must not keep a working key.
        if (!is_array($user) || ($user['status'] ?? 'active') !== 'active') {
            $this->logDenied('api.token_rejected', $request, [
                'reason'     => 'bound_user_inactive',
                'api_key_id' => (int) $key['id'],
            ]);
            return $this->unauthorized();
        }

        $roles = $userModel->getRolesForUser((int) $user['id']);

        service('apiIdentity')->setFromApiKey($key, $user, $roles);
        $keyModel->touch((int) $key['id'], $request->getIPAddress());

        helper('logging');
        log_structured('info', 'api.token_auth', [
            'api_key_id' => (int) $key['id'],
            'user_id'    => (int) $user['id'],
            'ip_address' => $request->getIPAddress(),
        ]);

        return ['roles' => $roles];
    }

    /**
     * Apply the role arguments declared on the route, if any.
     *
     * @param array<int, string>|null $arguments
     * @param array<int, string>      $userRoles
     */
    private function enforceRoles(?array $arguments, array $userRoles): ?ResponseInterface
    {
        if (empty($arguments)) {
            return null;
        }

        // §4.4 Canonical RBAC Pattern — intersect against the authoritative set.
        if (empty(array_intersect($arguments, $userRoles))) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['error' => ['message' => 'Insufficient permissions', 'code' => 'forbidden']]);
        }

        return null;
    }

    /**
     * Per-key request throttle, mirroring PublicBookingRateLimiter's cache
     * bucket approach but keyed on the API key rather than the IP.
     */
    private function enforceRateLimit(string $keyPrefix): ?ResponseInterface
    {
        $cacheKey = 'api_key_rate-' . sha1($keyPrefix);
        $cache    = cache();
        $entry    = $cache->get($cacheKey);
        $now      = time();

        if (!is_array($entry) || ($entry['expires_at'] ?? 0) <= $now) {
            $entry = ['count' => 0, 'expires_at' => $now + self::RATE_WINDOW];
        }

        if ($entry['count'] >= self::RATE_LIMIT) {
            $retryAfter = max(1, $entry['expires_at'] - $now);

            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setJSON([
                    'error' => [
                        'message' => 'Too many requests.',
                        'code'    => 'rate_limited',
                        'details' => ['retry_after' => $retryAfter],
                    ],
                ]);
        }

        $entry['count']++;
        $cache->save($cacheKey, $entry, self::RATE_WINDOW);

        return null;
    }

    private function hasSession(): bool
    {
        try {
            $session = session();

            return $session !== null && (bool) $session->get('isLoggedIn');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function unauthorized(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON(['error' => ['message' => 'Unauthorized', 'code' => 'unauthorized']]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logDenied(string $event, RequestInterface $request, array $context = []): void
    {
        helper('logging');
        log_structured('warning', $event, $context + [
            'ip_address' => $request->getIPAddress(),
            'path'       => $request instanceof \CodeIgniter\HTTP\IncomingRequest ? $request->getUri()->getPath() : null,
        ]);
    }
}

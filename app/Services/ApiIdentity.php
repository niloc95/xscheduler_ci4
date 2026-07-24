<?php

/**
 * =============================================================================
 * API IDENTITY
 * =============================================================================
 *
 * @file        app/Services/ApiIdentity.php
 * @description Request-scoped identity for token-authenticated API requests.
 *
 * WHY THIS EXISTS:
 * -----------------------------------------------------------------------------
 * Authorization across the app reads `session()->get('user')` — directly, via
 * `BaseApiController::currentUser()`, and via the `permissions` helper. A Bearer
 * token request has no session, so without a shared identity every one of those
 * call sites would need a second code path.
 *
 * Instead, `ApiAuthFilter` populates this holder from the `xs_users` row the API
 * key is bound to, using the *same* user array shape `Auth::login()` writes to
 * the session. `currentUser()` and the permissions helper then consult this
 * holder first and fall back to the session, so session callers and token
 * callers run through identical authorization logic.
 *
 * Registered as a shared service (`service('apiIdentity')`) — one instance per
 * request. It is never persisted.
 *
 * @see         app/Filters/ApiAuthFilter.php  (populates it)
 * @see         app/Controllers/Api/BaseApiController.php  (consumes it)
 * @see         app/Helpers/permissions_helper.php  (consumes it)
 * @package     App\Services
 * =============================================================================
 */

namespace App\Services;

class ApiIdentity
{
    private ?array $user = null;

    private ?int $businessId = null;

    private ?array $scopes = null;

    private ?int $keyId = null;

    private ?string $keyName = null;

    /**
     * Populate the identity from a verified API key and its bound user.
     *
     * @param array $key   Row from xs_api_keys.
     * @param array $user  Row from xs_users.
     * @param array $roles Authoritative roles from UserModel::getRolesForUser().
     */
    public function setFromApiKey(array $key, array $user, array $roles): void
    {
        helper('permissions');

        // Must match the shape Auth::login() writes to the session, or role
        // checks and provider scoping will behave differently for tokens.
        $this->user = [
            'id'            => (int) $user['id'],
            'name'          => $user['name'] ?? '',
            'email'         => $user['email'] ?? '',
            'role'          => $user['role'] ?? '',
            'roles'         => $roles,
            'active_role'   => resolve_active_role($roles, $user['role'] ?? ''),
            'profile_image' => $user['profile_image'] ?? null,
        ];

        $this->businessId = isset($key['business_id']) ? max(1, (int) $key['business_id']) : null;
        $this->keyId      = isset($key['id']) ? (int) $key['id'] : null;
        $this->keyName    = $key['name'] ?? null;

        $scopes = $key['scopes'] ?? null;
        if (is_string($scopes) && $scopes !== '') {
            $decoded = json_decode($scopes, true);
            $scopes  = is_array($decoded) ? $decoded : null;
        }
        $this->scopes = is_array($scopes) ? array_values($scopes) : null;
    }

    /**
     * True when this request was authenticated by an API key.
     */
    public function isTokenRequest(): bool
    {
        return $this->user !== null;
    }

    /**
     * Alias of isTokenRequest() for symmetry with the session-side helpers.
     */
    public function isAuthenticated(): bool
    {
        return $this->isTokenRequest();
    }

    /**
     * The bound user, in the same shape as session()->get('user').
     */
    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }

    /**
     * Authoritative role membership of the bound user.
     *
     * @return array<int, string>
     */
    public function roles(): array
    {
        return $this->user['roles'] ?? [];
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    /**
     * Declared scopes, or null when the key inherits the bound user's role
     * permissions (the common case).
     */
    public function scopes(): ?array
    {
        return $this->scopes;
    }

    /**
     * A key with no declared scopes inherits its user's role permissions, so an
     * unscoped key satisfies every scope check.
     */
    public function hasScope(string $scope): bool
    {
        if (!$this->isTokenRequest()) {
            return false;
        }

        if ($this->scopes === null) {
            return true;
        }

        return in_array($scope, $this->scopes, true);
    }

    public function keyId(): ?int
    {
        return $this->keyId;
    }

    public function keyName(): ?string
    {
        return $this->keyName;
    }
}

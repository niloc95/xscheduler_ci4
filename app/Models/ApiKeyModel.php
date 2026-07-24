<?php

/**
 * =============================================================================
 * API KEY MODEL
 * =============================================================================
 *
 * @file        app/Models/ApiKeyModel.php
 * @description Persistence and verification for external API bearer tokens.
 *
 * TOKEN FORMAT:
 * -----------------------------------------------------------------------------
 *     xsk_<12-char prefix>_<40-char secret>
 *
 * Only the prefix and a password_hash() of the secret are stored. The full
 * token is returned once, at creation, and is not recoverable afterwards.
 * Lookup is an indexed read on `key_prefix` followed by password_verify() —
 * no table scan and no comparison against the raw secret.
 *
 * Every key is bound to an `xs_users` row. The bound user supplies the identity
 * (roles, provider scope) that the token request runs under, so token callers
 * and session callers hit exactly the same authorization code.
 *
 * @see         app/Filters/ApiAuthFilter.php for the request-time verification
 * @see         app/Services/ApiIdentity.php for the resulting request identity
 * @package     App\Models
 * @extends     BaseModel
 * =============================================================================
 */

namespace App\Models;

class ApiKeyModel extends BaseModel
{
    protected $table         = 'api_keys';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'business_id',
        'user_id',
        'name',
        'key_prefix',
        'key_hash',
        'scopes',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    /** Human-facing token prefix, kept short so keys are recognisable in logs. */
    public const TOKEN_PREFIX = 'xsk_';

    private const PREFIX_LENGTH = 12;
    private const SECRET_LENGTH = 40;

    /**
     * Issue a new key for a user.
     *
     * The plaintext is returned exactly once — the caller is responsible for
     * showing it to the operator. It cannot be recovered from the stored row.
     *
     * @param array{business_id?:int,scopes?:array|null,expires_at?:string|null,created_by?:int|null} $options
     * @return array{plaintext:string,record:array}|null Null when the insert fails.
     */
    public function generate(int $userId, string $name, array $options = []): ?array
    {
        $prefix = $this->randomToken(self::PREFIX_LENGTH);
        $secret = $this->randomToken(self::SECRET_LENGTH);

        $scopes = $options['scopes'] ?? null;

        $row = [
            'business_id'  => (int) ($options['business_id'] ?? 1),
            'user_id'      => $userId,
            'name'         => $name,
            'key_prefix'   => $prefix,
            'key_hash'     => password_hash($secret, PASSWORD_DEFAULT),
            'scopes'       => is_array($scopes) ? json_encode(array_values($scopes)) : null,
            'expires_at'   => $options['expires_at'] ?? null,
            'created_by'   => $options['created_by'] ?? null,
        ];

        $id = $this->insert($row, true);
        if (!$id) {
            return null;
        }

        return [
            'plaintext' => self::TOKEN_PREFIX . $prefix . '_' . $secret,
            'record'    => $this->find($id),
        ];
    }

    /**
     * Verify a plaintext token and return the active key row it belongs to.
     *
     * Returns null for malformed tokens, unknown prefixes, bad secrets, revoked
     * keys and expired keys alike — callers must not distinguish between them.
     */
    public function verify(string $plaintext): ?array
    {
        $parsed = $this->parse($plaintext);
        if ($parsed === null) {
            return null;
        }

        [$prefix, $secret] = $parsed;

        $key = $this->findActiveByPrefix($prefix);
        if ($key === null) {
            return null;
        }

        if (!password_verify($secret, (string) $key['key_hash'])) {
            return null;
        }

        return $key;
    }

    /**
     * Look up a key by its prefix, excluding revoked and expired rows.
     */
    public function findActiveByPrefix(string $prefix): ?array
    {
        $key = $this->where('key_prefix', $prefix)->first();
        if (!is_array($key)) {
            return null;
        }

        if (!empty($key['revoked_at'])) {
            return null;
        }

        if (!empty($key['expires_at']) && strtotime((string) $key['expires_at']) <= time()) {
            return null;
        }

        return $key;
    }

    /**
     * Record usage telemetry. Best-effort: never let a telemetry write fail a
     * request that has already authenticated.
     */
    public function touch(int $id, ?string $ip = null): void
    {
        try {
            $this->update($id, [
                'last_used_at' => date('Y-m-d H:i:s'),
                'last_used_ip' => $ip,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', '[ApiKeyModel::touch] Failed to record key usage: ' . $e->getMessage());
        }
    }

    /**
     * Soft-revoke a key. Revocation takes effect on the next request.
     */
    public function revoke(int $id): bool
    {
        return (bool) $this->update($id, ['revoked_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * All keys for a user, newest first. `key_hash` is never returned.
     *
     * @return array<int, array>
     */
    public function listForUser(int $userId): array
    {
        return $this->redactAll(
            $this->where('user_id', $userId)->orderBy('created_at', 'DESC')->findAll()
        );
    }

    /**
     * Every key with its bound user's name/email, newest first, for the CLI and
     * any future admin surface. `key_hash` is never returned.
     *
     * @return array<int, array>
     */
    public function listAllWithUsers(): array
    {
        $users = $this->db->prefixTable('users');

        $rows = $this->db->table($this->db->prefixTable($this->table) . ' k')
            ->select('k.*, u.name AS user_name, u.email AS user_email')
            ->join($users . ' u', 'u.id = k.user_id', 'left')
            ->orderBy('k.created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->redactAll($rows);
    }

    /**
     * Decode the stored scopes column. Null means "inherit role permissions".
     */
    public function decodeScopes(array $key): ?array
    {
        if (empty($key['scopes'])) {
            return null;
        }

        $decoded = json_decode((string) $key['scopes'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Split a plaintext token into [prefix, secret], or null when malformed.
     *
     * @return array{0:string,1:string}|null
     */
    private function parse(string $plaintext): ?array
    {
        $plaintext = trim($plaintext);

        if (!str_starts_with($plaintext, self::TOKEN_PREFIX)) {
            return null;
        }

        $body  = substr($plaintext, strlen(self::TOKEN_PREFIX));
        $parts = explode('_', $body, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$prefix, $secret] = $parts;

        if (strlen($prefix) !== self::PREFIX_LENGTH || $secret === '') {
            return null;
        }

        return [$prefix, $secret];
    }

    /**
     * Strip the secret hash from rows leaving the model.
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private function redactAll(array $rows): array
    {
        foreach ($rows as $i => $row) {
            unset($rows[$i]['key_hash']);
        }

        return $rows;
    }

    /**
     * URL-safe random string of exactly $length characters.
     */
    private function randomToken(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max      = strlen($alphabet) - 1;
        $out      = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}

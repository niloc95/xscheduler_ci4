<?php

namespace App\Commands;

use App\Models\ApiKeyModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Issue, list and revoke external API keys.
 *
 * Keys are bound to an xs_users row; the bound user supplies the roles the
 * token request runs under. The plaintext token is shown exactly once, at
 * creation — it cannot be recovered afterwards.
 */
class ApiKeyCommand extends BaseCommand
{
    protected $group = 'api';

    protected $name = 'api:key';

    protected $description = 'Manage external API bearer tokens (create, list, revoke).';

    protected $usage = 'api:key <action> [options]';

    protected $arguments = [
        'action' => 'One of: create, list, revoke',
    ];

    protected $options = [
        '--user'    => 'create: xs_users.id the key is bound to (required)',
        '--name'    => 'create: human label for the key (required)',
        '--scopes'  => 'create: comma-separated scopes; omit to inherit the user\'s role permissions',
        '--expires' => 'create: expiry as a date or relative string, e.g. "2027-01-01" or "+90 days"',
        '--id'      => 'revoke: xs_api_keys.id to revoke (required)',
    ];

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $action = strtolower(trim((string) ($params[0] ?? '')));

        return match ($action) {
            'create' => $this->create(),
            'list'   => $this->list(),
            'revoke' => $this->revoke(),
            default  => $this->usageError(),
        };
    }

    private function create(): void
    {
        $userId = (int) CLI::getOption('user');
        $name   = trim((string) CLI::getOption('name'));

        if ($userId <= 0 || $name === '') {
            CLI::error('Both --user and --name are required.');
            CLI::write('Example: php spark api:key create --user 1 --name "Zapier prod"');
            return;
        }

        $userModel = new UserModel();
        $user      = $userModel->find($userId);

        if (!is_array($user)) {
            CLI::error("No user found with id {$userId}.");
            return;
        }

        if (($user['status'] ?? 'active') !== 'active') {
            CLI::error("User {$userId} is not active; a key bound to it would be rejected at request time.");
            return;
        }

        $roles = $userModel->getRolesForUser($userId);
        if (empty($roles)) {
            CLI::write("Warning: user {$userId} has no roles in xs_user_roles; the key will fail role-gated routes.", 'yellow');
        }

        $expiresAt = null;
        $expires   = trim((string) CLI::getOption('expires'));
        if ($expires !== '') {
            $timestamp = strtotime($expires);
            if ($timestamp === false) {
                CLI::error("Could not parse --expires value: {$expires}");
                return;
            }
            $expiresAt = date('Y-m-d H:i:s', $timestamp);
        }

        $scopes    = null;
        $scopesRaw = trim((string) CLI::getOption('scopes'));
        if ($scopesRaw !== '') {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $scopesRaw))));
        }

        $result = (new ApiKeyModel())->generate($userId, $name, [
            'scopes'     => $scopes,
            'expires_at' => $expiresAt,
        ]);

        if ($result === null) {
            CLI::error('Failed to create the API key.');
            return;
        }

        CLI::newLine();
        CLI::write('API key created.', 'green');
        CLI::write('  id      : ' . $result['record']['id']);
        CLI::write('  user    : ' . $user['name'] . ' <' . $user['email'] . '>');
        CLI::write('  roles   : ' . (empty($roles) ? '(none)' : implode(', ', $roles)));
        CLI::write('  scopes  : ' . ($scopes === null ? '(inherits role permissions)' : implode(', ', $scopes)));
        CLI::write('  expires : ' . ($expiresAt ?? 'never'));
        CLI::newLine();
        CLI::write('Token (shown once — store it now):', 'yellow');
        CLI::write('  ' . $result['plaintext'], 'light_cyan');
        CLI::newLine();
        CLI::write('Usage: curl -H "Authorization: Bearer ' . $result['plaintext'] . '" ' . rtrim(base_url(), '/') . '/api/v1/appointments');
        CLI::newLine();
    }

    private function list(): void
    {
        $keys = (new ApiKeyModel())->listAllWithUsers();

        if (empty($keys)) {
            CLI::write('No API keys issued.', 'yellow');
            return;
        }

        $rows = [];
        foreach ($keys as $key) {
            $rows[] = [
                $key['id'],
                $key['name'],
                trim(($key['user_name'] ?? '?') . ' (' . $key['user_id'] . ')'),
                $key['key_prefix'],
                $this->statusOf($key),
                $key['last_used_at'] ?? 'never',
            ];
        }

        CLI::newLine();
        CLI::table($rows, ['ID', 'Name', 'User', 'Prefix', 'Status', 'Last used']);
        CLI::newLine();
    }

    private function revoke(): void
    {
        $id = (int) CLI::getOption('id');

        if ($id <= 0) {
            CLI::error('--id is required. Run "php spark api:key list" to find it.');
            return;
        }

        $model = new ApiKeyModel();
        $key   = $model->find($id);

        if (!is_array($key)) {
            CLI::error("No API key found with id {$id}.");
            return;
        }

        if (!empty($key['revoked_at'])) {
            CLI::write("Key {$id} was already revoked at {$key['revoked_at']}.", 'yellow');
            return;
        }

        if (!$model->revoke($id)) {
            CLI::error("Failed to revoke key {$id}.");
            return;
        }

        CLI::write("Revoked key {$id} ({$key['name']}). It stops working on the next request.", 'green');
    }

    private function statusOf(array $key): string
    {
        if (!empty($key['revoked_at'])) {
            return 'revoked';
        }

        if (!empty($key['expires_at']) && strtotime((string) $key['expires_at']) <= time()) {
            return 'expired';
        }

        return 'active';
    }

    private function usageError(): void
    {
        CLI::error('Usage: php spark api:key <create|list|revoke> [options]');
        CLI::newLine();
        CLI::write('  php spark api:key create --user 1 --name "Zapier prod" [--scopes a,b] [--expires "+90 days"]');
        CLI::write('  php spark api:key list');
        CLI::write('  php spark api:key revoke --id 3');
    }
}

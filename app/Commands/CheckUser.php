<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckUser extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'check:user';
    protected $description = 'Check a specific user record';

    public function run(array $params)
    {
        $userId = $params[0] ?? 1;
        
        $db = \Config\Database::connect();
        $builder = $db->table('users');
        $user = $builder->where('id', $userId)->get()->getRowArray();
        
        if (!$user) {
            CLI::error("User ID {$userId} not found");
            return;
        }
        
        CLI::write('--- Raw Row Dump ---', 'yellow');
        CLI::write(json_encode($user, JSON_PRETTY_PRINT), 'green');

        $nullFields = array_keys(array_filter($user, static fn ($value) => $value === null));
        if ($nullFields) {
            CLI::write('Null fields: ' . implode(', ', $nullFields), 'red');
        }
        CLI::write('---------------------', 'yellow');
    }
}

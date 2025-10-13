<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CompareUsers extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'compare:users';
    protected $description = 'Compare Setup Admin vs regular admin user structures';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        CLI::write('');
        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('COMPARING SETUP ADMIN VS REGULAR ADMIN', 'yellow');
        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('');

        // Get Setup Admin (ID 1)
        $setupAdmin = $db->table('users')->where('id', 1)->get()->getRowArray();
        
        // Get regular admin (ID 3)
        $regularAdmin = $db->table('users')->where('id', 3)->get()->getRowArray();

        if (!$setupAdmin) {
            CLI::error('Setup Admin (ID 1) not found!');
            return;
        }

        if (!$regularAdmin) {
            CLI::error('Regular Admin (ID 3) not found!');
            return;
        }

        // Get all column names
        $columns = array_keys($setupAdmin);

        CLI::write('Field Comparison:', 'cyan');
        CLI::write(str_repeat('-', 70));
        CLI::write(sprintf('%-20s | %-20s | %-20s', 'Field', 'Setup Admin (ID 1)', 'Regular Admin (ID 3)'));
        CLI::write(str_repeat('-', 70));

        foreach ($columns as $column) {
            $setupValue = $setupAdmin[$column] ?? 'NULL';
            $regularValue = $regularAdmin[$column] ?? 'NULL';
            
            // Highlight differences
            $isDifferent = $setupValue !== $regularValue && 
                          !in_array($column, ['id', 'name', 'email', 'password_hash', 'created_at', 'updated_at']);
            
            $color = $isDifferent ? 'red' : 'white';
            
            // Truncate long values
            $setupValueDisplay = strlen($setupValue) > 20 ? substr($setupValue, 0, 17) . '...' : $setupValue;
            $regularValueDisplay = strlen($regularValue) > 20 ? substr($regularValue, 0, 17) . '...' : $regularValue;
            
            CLI::write(
                sprintf('%-20s | %-20s | %-20s', $column, $setupValueDisplay, $regularValueDisplay),
                $color
            );
        }

        CLI::write('');
        CLI::write(str_repeat('-', 70));
        CLI::write('KEY CHECKS:', 'yellow');
        CLI::write(str_repeat('-', 70));

        // Check for NULL/missing fields in Setup Admin
        $nullFields = [];
        foreach ($columns as $column) {
            if (in_array($column, ['updated_at', 'deleted_at', 'permissions', 'provider_id', 'phone', 'status', 'last_login', 'reset_token', 'reset_expires'])) {
                if (empty($setupAdmin[$column]) && !empty($regularAdmin[$column])) {
                    $nullFields[] = $column;
                }
            }
        }

        if (!empty($nullFields)) {
            CLI::write('⚠️  Setup Admin has NULL/empty fields that regular admin has:', 'yellow');
            foreach ($nullFields as $field) {
                CLI::write('  - ' . $field);
            }
        } else {
            CLI::write('✅ No obvious structural differences found', 'green');
        }

        CLI::write('');

        // Check timestamps
        CLI::write('TIMESTAMP ANALYSIS:', 'yellow');
        CLI::write(str_repeat('-', 70));
        CLI::write('Setup Admin:');
        CLI::write('  created_at: ' . ($setupAdmin['created_at'] ?? 'NULL'));
        CLI::write('  updated_at: ' . ($setupAdmin['updated_at'] ?? 'NULL'));
        CLI::write('Regular Admin:');
        CLI::write('  created_at: ' . ($regularAdmin['created_at'] ?? 'NULL'));
        CLI::write('  updated_at: ' . ($regularAdmin['updated_at'] ?? 'NULL'));
        CLI::write('');

        // Check if BaseModel timestamps are enabled
        CLI::write('MODEL CONFIGURATION:', 'yellow');
        CLI::write(str_repeat('-', 70));
        
        $userModel = new \App\Models\UserModel();
        CLI::write('useTimestamps: ' . ($userModel->useTimestamps ? 'YES' : 'NO'));
        CLI::write('createdField: ' . $userModel->createdField);
        CLI::write('updatedField: ' . $userModel->updatedField);
        CLI::write('');

        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('COMPARISON COMPLETE', 'yellow');
        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('');
    }
}

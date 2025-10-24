<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;

class TestUserUpdate extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'test:userupdate';
    protected $description = 'Test updating the Setup Admin user';

    public function run(array $params)
    {
        $userModel = new UserModel();

        CLI::write('');
        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('TESTING USER UPDATE FOR SETUP ADMIN', 'yellow');
        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('');

        // Get Setup Admin (ID: 1)
        $user = $userModel->find(1);
        
        if (!$user) {
            CLI::error('User ID 1 not found!');
            return;
        }

        CLI::write('Current user data:', 'cyan');
        CLI::write('  ID: ' . $user['id']);
        CLI::write('  Name: ' . $user['name']);
        CLI::write('  Email: ' . $user['email']);
        CLI::write('  Role: ' . $user['role']);
        CLI::write('  Active: ' . ($user['is_active'] ? 'Yes' : 'No'));
        CLI::write('');

        // Test 1: Update with same data (should work)
        CLI::write('TEST 1: Update with same data', 'yellow');
        CLI::write(str_repeat('-', 60));
        
        $updateData = [
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? null,
            'is_active' => 1,
            'role' => 'admin'
        ];

        CLI::write('Update data:');
        CLI::write(json_encode($updateData, JSON_PRETTY_PRINT));
        CLI::write('');

        // Test permission check
        $canManage = $userModel->canManageUser(1, 1);
        CLI::write('Can admin (ID:1) manage themselves? ' . ($canManage ? '✅ YES' : '❌ NO'), $canManage ? 'green' : 'red');
        CLI::write('');

        // Try update
        CLI::write('Attempting update...', 'yellow');
        $result = $userModel->updateUser(1, $updateData, 1);
        
        if ($result) {
            CLI::write('✅ UPDATE SUCCESSFUL', 'green');
        } else {
            CLI::error('❌ UPDATE FAILED');
            $errors = $userModel->errors();
            if (!empty($errors)) {
                CLI::write('Errors:', 'red');
                CLI::write(json_encode($errors, JSON_PRETTY_PRINT), 'red');
            }
        }
        CLI::write('');

        // Test 2: Try changing the name
        CLI::write('TEST 2: Update name only', 'yellow');
        CLI::write(str_repeat('-', 60));
        
        $updateData2 = [
            'name' => $user['name'] . ' (Test)',
            'email' => $user['email'],
            'phone' => $user['phone'] ?? null,
            'is_active' => 1,
            'role' => 'admin'
        ];

        CLI::write('Attempting to change name to: ' . $updateData2['name'], 'yellow');
        $result2 = $userModel->updateUser(1, $updateData2, 1);
        
        if ($result2) {
            CLI::write('✅ UPDATE SUCCESSFUL', 'green');
            
            // Revert the change
            CLI::write('Reverting change...', 'yellow');
            $userModel->updateUser(1, $updateData, 1);
            CLI::write('✅ Name reverted', 'green');
        } else {
            CLI::error('❌ UPDATE FAILED');
            $errors2 = $userModel->errors();
            if (!empty($errors2)) {
                CLI::write('Errors:', 'red');
                CLI::write(json_encode($errors2, JSON_PRETTY_PRINT), 'red');
            }
        }
        CLI::write('');

        // Test 3: Check validation rules
        CLI::write('TEST 3: Model validation rules', 'yellow');
        CLI::write(str_repeat('-', 60));
        
        $rules = $userModel->getValidationRules();
        CLI::write('Validation rules:');
        CLI::write(json_encode($rules, JSON_PRETTY_PRINT));
        CLI::write('');

        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('TEST COMPLETE', 'yellow');
        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('');
    }
}

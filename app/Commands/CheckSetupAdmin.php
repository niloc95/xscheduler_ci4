<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;

class CheckSetupAdmin extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'check:admin';
    protected $description = 'Check Setup Admin user configuration and permissions';

    public function run(array $params)
    {
        $userModel = new UserModel();

        CLI::write('');
        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('SETUP ADMIN USER DIAGNOSTIC', 'yellow');
        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('');

        // Find all admin users
        $admins = $userModel->where('role', 'admin')->findAll();

        if (empty($admins)) {
            CLI::error('No admin users found in database!');
            CLI::write('');
            return;
        }

        CLI::write("Found " . count($admins) . " admin user(s):", 'green');
        CLI::write('');

        foreach ($admins as $admin) {
            CLI::write("User ID: {$admin['id']}", 'cyan');
            CLI::write("  Name: {$admin['name']}");
            CLI::write("  Email: {$admin['email']}");
            CLI::write("  Role: {$admin['role']}");
            CLI::write("  Active: " . ($admin['is_active'] ? 'Yes' : 'No'));
            CLI::write("  Provider ID: " . ($admin['provider_id'] ?? 'null'));
            CLI::write("  Created: {$admin['created_at']}");
            CLI::write("  Updated: " . ($admin['updated_at'] ?? 'never'));
            
            if ($admin['id'] == 1) {
                CLI::write("  ⭐ This is user ID 1 (likely the SETUP ADMIN)", 'yellow');
            }
            CLI::write('');
        }

        // Test permissions
        CLI::write(str_repeat('-', 60));
        CLI::write('PERMISSION TESTS');
        CLI::write(str_repeat('-', 60));
        CLI::write('');

        $firstAdmin = $admins[0];
        $adminId = $firstAdmin['id'];

        CLI::write("Testing if admin (ID: {$adminId}, {$firstAdmin['name']}) can manage:");
        
        $canManageSelf = $userModel->canManageUser($adminId, $adminId);
        CLI::write("  - Themselves: " . ($canManageSelf ? '✅ YES' : '❌ NO'), $canManageSelf ? 'green' : 'red');

        if (count($admins) > 1) {
            $secondAdmin = $admins[1];
            $canManageOther = $userModel->canManageUser($adminId, $secondAdmin['id']);
            CLI::write("  - Another admin (ID: {$secondAdmin['id']}): " . ($canManageOther ? '✅ YES' : '❌ NO'), $canManageOther ? 'green' : 'red');
        }

        CLI::write('');

        // Check for duplicate emails
        CLI::write(str_repeat('-', 60));
        CLI::write('DATABASE INTEGRITY');
        CLI::write(str_repeat('-', 60));
        CLI::write('');

        $db = \Config\Database::connect();
        $emails = $db->table('users')->select('email, COUNT(*) as count')->groupBy('email')->having('count >', 1)->get()->getResultArray();
        
        if (!empty($emails)) {
            CLI::error('⚠️  WARNING: Duplicate emails found:');
            foreach ($emails as $row) {
                CLI::write("  - {$row['email']} appears {$row['count']} times", 'red');
            }
            CLI::write('');
        } else {
            CLI::write('✅ No duplicate emails found', 'green');
            CLI::write('');
        }

        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('DIAGNOSTIC COMPLETE', 'yellow');
        CLI::write(str_repeat('=', 60), 'yellow');
        CLI::write('');
    }
}

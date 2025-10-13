<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;

class TestDirectUpdate extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'test:directupdate';
    protected $description = 'Test updating Setup Admin with various methods';

    public function run(array $params)
    {
        $userModel = new UserModel();
        $db = \Config\Database::connect();

        CLI::write('');
        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('TESTING DIRECT DATABASE UPDATE ON SETUP ADMIN', 'yellow');
        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('');

        // Get current user
        $user = $userModel->find(1);
        $originalName = $user['name'];
        
        CLI::write('Current name: ' . $originalName, 'cyan');
        CLI::write('');

        // TEST 1: Try updating via Model update()
        CLI::write('TEST 1: Update via Model->update()', 'yellow');
        CLI::write(str_repeat('-', 70));
        
        $testName1 = $originalName . ' [Test1]';
        $result1 = $userModel->update(1, ['name' => $testName1]);
        
        CLI::write('Result: ' . ($result1 ? 'TRUE' : 'FALSE'));
        $user1 = $userModel->find(1);
        CLI::write('Name after update: ' . $user1['name']);
        CLI::write('Success: ' . ($user1['name'] === $testName1 ? '✅' : '❌'));
        CLI::write('');

        // TEST 2: Try via Model updateUser() 
        CLI::write('TEST 2: Update via Model->updateUser()', 'yellow');
        CLI::write(str_repeat('-', 70));
        
        $testName2 = $originalName . ' [Test2]';
        $result2 = $userModel->updateUser(1, ['name' => $testName2, 'email' => $user['email'], 'role' => 'admin', 'is_active' => 1], 1);
        
        CLI::write('Result: ' . ($result2 ? 'TRUE' : 'FALSE'));
        $user2 = $userModel->find(1);
        CLI::write('Name after update: ' . $user2['name']);
        CLI::write('Success: ' . ($user2['name'] === $testName2 ? '✅' : '❌'));
        CLI::write('');

        // TEST 3: Try raw database update
        CLI::write('TEST 3: Raw database update', 'yellow');
        CLI::write(str_repeat('-', 70));
        
        $testName3 = $originalName . ' [Test3]';
        $result3 = $db->table('users')->where('id', 1)->update(['name' => $testName3]);
        
        CLI::write('Result: ' . ($result3 ? 'TRUE' : 'FALSE'));
        $user3 = $userModel->find(1);
        CLI::write('Name after update: ' . $user3['name']);
        CLI::write('Success: ' . ($user3['name'] === $testName3 ? '✅' : '❌'));
        CLI::write('');

        // Restore original name
        CLI::write('CLEANUP: Restoring original name...', 'yellow');
        $userModel->update(1, ['name' => $originalName]);
        $userFinal = $userModel->find(1);
        CLI::write('Final name: ' . $userFinal['name']);
        CLI::write('');

        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('If all tests show ❌, there may be a database trigger or lock', 'yellow');
        CLI::write('If only certain methods fail, it\'s a model configuration issue', 'yellow');
        CLI::write(str_repeat('=', 70), 'yellow');
        CLI::write('');
    }
}

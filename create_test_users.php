<?php
/**
 * Test Users Seeder
 * Creates test users for different roles to test the role-based system
 */

require_once 'vendor/autoload.php';

// Load CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

use App\Models\UserModel;

$userModel = new UserModel();

// Test users to create
$testUsers = [
    [
        'name' => 'Service Provider Demo',
        'email' => 'provider@test.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'provider',
        'provider_id' => null,
        'permissions' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'name' => 'Staff Member Demo',
        'email' => 'staff@test.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'staff',
        'provider_id' => 2, // Will be assigned to the provider we just created
        'permissions' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'name' => 'Customer Demo',
        'email' => 'customer@test.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'customer',
        'provider_id' => null,
        'permissions' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]
];

echo "Creating test users...\n";

foreach ($testUsers as $userData) {
    // Check if user already exists
    $existingUser = $userModel->where('email', $userData['email'])->first();
    if ($existingUser) {
        echo "User {$userData['email']} already exists, skipping...\n";
        continue;
    }
    
    try {
        $userId = $userModel->insert($userData);
        if ($userId) {
            echo "Created user: {$userData['name']} ({$userData['email']}) with role: {$userData['role']}\n";
        } else {
            echo "Failed to create user: {$userData['email']}\n";
            print_r($userModel->errors());
        }
    } catch (Exception $e) {
        echo "Error creating user {$userData['email']}: " . $e->getMessage() . "\n";
    }
}

echo "\nTest users creation completed!\n";
echo "\nLogin credentials:\n";
echo "Admin: nilo.cara@gmail.com / (original password)\n";
echo "Provider: provider@test.com / password123\n";
echo "Staff: staff@test.com / password123\n";
echo "Customer: customer@test.com / password123\n";
?>

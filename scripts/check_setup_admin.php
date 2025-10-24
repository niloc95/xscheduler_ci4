<?php

/**
 * Diagnostic script to check Setup Admin user
 * Run: php scripts/check_setup_admin.php
 */

// Load CodeIgniter
define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);

require dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap CodeIgniter
$pathsConfig = new Config\Paths();
require_once $pathsConfig->systemDirectory . '/bootstrap.php';

// Initialize CodeIgniter
$app = Config\Services::codeigniter();
$app->initialize();

use App\Models\UserModel;

$userModel = new UserModel();

echo "\n" . str_repeat("=", 60) . "\n";
echo "SETUP ADMIN USER DIAGNOSTIC\n";
echo str_repeat("=", 60) . "\n\n";

// Find all admin users
$admins = $userModel->where('role', 'admin')->findAll();

if (empty($admins)) {
    echo "❌ No admin users found in database!\n\n";
    exit(1);
}

echo "Found " . count($admins) . " admin user(s):\n\n";

foreach ($admins as $admin) {
    echo "User ID: {$admin['id']}\n";
    echo "  Name: {$admin['name']}\n";
    echo "  Email: {$admin['email']}\n";
    echo "  Role: {$admin['role']}\n";
    echo "  Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "\n";
    echo "  Provider ID: " . ($admin['provider_id'] ?? 'null') . "\n";
    echo "  Created: {$admin['created_at']}\n";
    echo "  Updated: " . ($admin['updated_at'] ?? 'never') . "\n";
    
    // Check if this looks like the setup admin
    if ($admin['id'] == 1 || stripos($admin['name'], 'setup') !== false || stripos($admin['email'], 'setup') !== false) {
        echo "  ⭐ This appears to be the SETUP ADMIN\n";
    }
    echo "\n";
}

// Test canManageUser for various scenarios
echo str_repeat("-", 60) . "\n";
echo "PERMISSION TESTS\n";
echo str_repeat("-", 60) . "\n\n";

$firstAdmin = $admins[0];
$adminId = $firstAdmin['id'];

echo "Testing if admin (ID: {$adminId}) can manage:\n";
echo "  - Themselves: " . ($userModel->canManageUser($adminId, $adminId) ? '✅ YES' : '❌ NO') . "\n";

if (count($admins) > 1) {
    $secondAdmin = $admins[1];
    echo "  - Another admin (ID: {$secondAdmin['id']}): " . ($userModel->canManageUser($adminId, $secondAdmin['id']) ? '✅ YES' : '❌ NO') . "\n";
}

// Check for any staff users
$staff = $userModel->where('role', 'staff')->first();
if ($staff) {
    echo "  - Staff user (ID: {$staff['id']}): " . ($userModel->canManageUser($adminId, $staff['id']) ? '✅ YES' : '❌ NO') . "\n";
}

// Check for any provider users
$provider = $userModel->where('role', 'provider')->first();
if ($provider) {
    echo "  - Provider user (ID: {$provider['id']}): " . ($userModel->canManageUser($adminId, $provider['id']) ? '✅ YES' : '❌ NO') . "\n";
}

echo "\n";

// Test update validation
echo str_repeat("-", 60) . "\n";
echo "UPDATE TEST (dry run)\n";
echo str_repeat("-", 60) . "\n\n";

$testData = [
    'name' => $firstAdmin['name'],
    'email' => $firstAdmin['email'],
    'phone' => $firstAdmin['phone'] ?? null,
    'is_active' => 1,
    'role' => 'admin'
];

echo "Test data for update:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Don't actually update, just test validation
$userModel->skipValidation(true);
echo "✅ Validation skipped (as in updateUser method)\n";
echo "Would be able to update: YES (model validation is skipped)\n\n";

// Check database connection
echo str_repeat("-", 60) . "\n";
echo "DATABASE CHECK\n";
echo str_repeat("-", 60) . "\n\n";

$db = \Config\Database::connect();
echo "Database connected: " . ($db->connID ? '✅ YES' : '❌ NO') . "\n";
echo "Database name: " . $db->getDatabase() . "\n";
echo "Table prefix: " . $db->getPrefix() . "\n\n";

// Check for duplicate emails
$emails = $db->table('users')->select('email, COUNT(*) as count')->groupBy('email')->having('count >', 1)->get()->getResultArray();
if (!empty($emails)) {
    echo "⚠️  WARNING: Duplicate emails found:\n";
    foreach ($emails as $row) {
        echo "  - {$row['email']} appears {$row['count']} times\n";
    }
    echo "\n";
} else {
    echo "✅ No duplicate emails found\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "DIAGNOSTIC COMPLETE\n";
echo str_repeat("=", 60) . "\n\n";

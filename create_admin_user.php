<?php
// Create admin user script
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);

require_once 'vendor/autoload.php';

// Set up basic CI paths
$pathsPath = FCPATH . '../app/Config/Paths.php';
require $pathsPath;
$paths = new Config\Paths();

// Bootstrap CI
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';
require $bootstrap;

// Initialize application
$app = Config\Services::codeigniter();
$app->initialize();

$db = Config\Database::connect();

// Check if admin user exists
$adminUser = $db->table('users')->where('email', 'admin@test.com')->get()->getRowArray();

if ($adminUser) {
    echo "Admin user already exists: admin@test.com" . PHP_EOL;
    echo "Password: admin123" . PHP_EOL;
} else {
    // Create admin user
    $adminData = [
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin',
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $db->table('users')->insert($adminData);
    
    if ($result) {
        echo "✅ Admin user created successfully!" . PHP_EOL;
        echo "Email: admin@test.com" . PHP_EOL;
        echo "Password: admin123" . PHP_EOL;
        echo "Role: admin" . PHP_EOL;
    } else {
        echo "❌ Failed to create admin user" . PHP_EOL;
    }
}

// Also create a test service if none exist
$services = $db->table('services')->countAllResults();
if ($services === 0) {
    $testService = [
        'name' => 'Test Service',
        'description' => 'This is a test service for editing',
        'duration_min' => 60,
        'price' => 100.00,
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $db->table('services')->insert($testService);
    if ($result) {
        echo "✅ Test service created!" . PHP_EOL;
    }
}

echo PHP_EOL . "Now you can:" . PHP_EOL;
echo "1. Go to http://localhost:8083/auth/login" . PHP_EOL;
echo "2. Login with admin@test.com / admin123" . PHP_EOL;
echo "3. Navigate to http://localhost:8083/services" . PHP_EOL;
echo "4. Click 'Edit Service' and test the functionality" . PHP_EOL;

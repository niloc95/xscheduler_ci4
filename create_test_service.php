<?php
// Simple service insertion test
require_once 'vendor/autoload.php';

// Create a minimal CI app context
$pathsPath = realpath(FCPATH . '../app/Config/Paths.php');
if ($pathsPath === false || !is_file($pathsPath)) {
    $pathsPath = FCPATH . '../app/Config/Paths.php';
}
require $pathsPath;
$paths = new \Config\Paths();
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';
require realpath($bootstrap) ?: $bootstrap;

// Boot the framework
$app = \Config\Services::codeigniter();
$app->initialize();

$db = \Config\Database::connect();

echo "Creating test service..." . PHP_EOL;

// Insert a test service
$testService = [
    'name' => 'Test Service',
    'description' => 'This is a test service',
    'duration_min' => 60,
    'price' => 50.00,
    'active' => 1,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

$result = $db->table('services')->insert($testService);
echo "Insert result: " . ($result ? 'SUCCESS' : 'FAILED') . PHP_EOL;

// Check the current services
$services = $db->table('services')->get()->getResultArray();
echo "Total services: " . count($services) . PHP_EOL;

foreach ($services as $service) {
    echo "ID: {$service['id']}, Name: {$service['name']}, Active: {$service['active']}" . PHP_EOL;
}

echo "Done." . PHP_EOL;

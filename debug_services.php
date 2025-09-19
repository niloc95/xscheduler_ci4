<?php
// Debug script to check services functionality
require_once 'vendor/autoload.php';

// Initialize CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

// Get database connection
$db = \Config\Database::connect();

echo "=== Database Debug ===" . PHP_EOL;

// Check services table structure
$fields = $db->getFieldData('services');
echo "Services table fields:" . PHP_EOL;
foreach ($fields as $field) {
    echo "  {$field->name} ({$field->type})" . PHP_EOL;
}

// Check if we have any services
$services = $db->table('services')->get()->getResultArray();
echo PHP_EOL . "Services count: " . count($services) . PHP_EOL;

if (!empty($services)) {
    echo "First service:" . PHP_EOL;
    print_r($services[0]);
}

// Test service model
echo PHP_EOL . "=== Model Test ===" . PHP_EOL;
$serviceModel = new \App\Models\ServiceModel();

// Try to get services with relations
$servicesWithRelations = $serviceModel->findWithRelations(5);
echo "Services with relations count: " . count($servicesWithRelations) . PHP_EOL;

if (!empty($servicesWithRelations)) {
    echo "First service with relations:" . PHP_EOL;
    print_r($servicesWithRelations[0]);
}

// Check categories
$categories = $db->table('categories')->get()->getResultArray();
echo PHP_EOL . "Categories count: " . count($categories) . PHP_EOL;

echo PHP_EOL . "=== Auth Debug ===" . PHP_EOL;
// Check session
session_start();
echo "Session isLoggedIn: " . (isset($_SESSION['isLoggedIn']) ? 'true' : 'false') . PHP_EOL;
echo "Session user: " . (isset($_SESSION['user']) ? json_encode($_SESSION['user']) : 'not set') . PHP_EOL;

echo PHP_EOL . "Debug complete." . PHP_EOL;

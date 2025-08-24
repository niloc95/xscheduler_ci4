<?php
// Simple test script to create admin user and demonstrate scheduler
require_once 'vendor/autoload.php';

// Initialize CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

try {
    // Get database instance
    $db = \Config\Database::connect();
    
    // Check if we have an admin user
    $adminUser = $db->table('users')->where('role', 'admin')->get()->getRow();
    
    if (!$adminUser) {
        // Create admin user
        $userData = [
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $userId = $db->table('users')->insert($userData);
        echo "Created admin user with ID: $userId\n";
    } else {
        echo "Admin user already exists: " . $adminUser->email . "\n";
    }
    
    // Check services
    $services = $db->table('services')->get()->getResult();
    echo "Services in database: " . count($services) . "\n";
    
    // Add sample services if none exist
    if (empty($services)) {
        $sampleServices = [
            ['name' => 'Consultation', 'description' => '30-minute consultation', 'duration_min' => 30, 'price' => 50.00, 'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Follow-up', 'description' => '15-minute follow-up session', 'duration_min' => 15, 'price' => 25.00, 'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Workshop', 'description' => '1-hour workshop session', 'duration_min' => 60, 'price' => 100.00, 'created_at' => date('Y-m-d H:i:s')]
        ];
        
        foreach ($sampleServices as $service) {
            $db->table('services')->insert($service);
        }
        echo "Created sample services\n";
    }
    
    // Add sample provider
    $provider = $db->table('users')->where('role', 'provider')->get()->getRow();
    if (!$provider) {
        $providerData = [
            'name' => 'Dr. Smith',
            'email' => 'provider@test.com',
            'role' => 'provider',
            'password_hash' => password_hash('provider123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $providerId = $db->table('users')->insert($providerData);
        echo "Created provider with ID: $providerId\n";
        
        // Link provider to all services
        $services = $db->table('services')->get()->getResult();
        foreach ($services as $service) {
            $db->table('providers_services')->insert([
                'provider_id' => $providerId,
                'service_id' => $service->id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        echo "Linked provider to services\n";
    }
    
    echo "Database setup complete!\n";
    echo "Admin login: admin@test.com / admin123\n";
    echo "Provider login: provider@test.com / provider123\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
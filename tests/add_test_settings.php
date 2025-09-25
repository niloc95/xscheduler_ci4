<?php
/**
 * Simple script to add test settings
 */

// Direct MySQL connection
$host = 'localhost';
$username = 'zaadmin';
$password = ''; // Let user enter password
$database = 'ws_02';

echo "Enter MySQL password for zaadmin: ";
$password = trim(fgets(STDIN));

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $settings = [
        'general.company_name' => 'XScheduler Demo Company',
        'general.company_email' => 'demo@xscheduler.com',
        'general.company_link' => 'https://xscheduler.demo',
        'localization.date_format' => 'DMY',
        'localization.time_format' => '24h',
        'business.work_start' => '08:30',
        'business.work_end' => '18:00'
    ];
    
    foreach ($settings as $key => $value) {
        $sql = "INSERT INTO xs_settings (setting_key, setting_value, setting_type, created_by, created_at, updated_at) 
                VALUES (?, ?, 'string', 1, NOW(), NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key, $value]);
        echo "Added/Updated: $key = $value\n";
    }
    
    echo "\nTest settings added successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
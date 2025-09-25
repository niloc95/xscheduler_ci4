<?php
/**
 * Script to add the new settings fields as requested
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
    
    // Settings to add/update
    $settings = [
        // General tab - new fields
        'general.company_name' => 'XScheduler Demo Company',
        'general.company_email' => 'demo@xscheduler.com',
        'general.company_link' => 'https://xscheduler.demo',
        'general.telephone_number' => '+27 11 123 4567',
        'general.mobile_number' => '+27 82 123 4567',
        'general.business_address' => '123 Business Street\nSandton, 2196\nSouth Africa',
        
        // Localization tab - updated fields (removed date_format, added timezone and currency)
        'localization.time_format' => '24h',
        'localization.first_day' => 'Monday',
        'localization.language' => 'English',
        'localization.timezone' => 'Africa/Johannesburg',
        'localization.currency' => 'ZAR',
        
        // Business hours
        'business.work_start' => '08:30',
        'business.work_end' => '18:00',
        'business.break_start' => '12:00',
        'business.break_end' => '13:00'
    ];
    
    foreach ($settings as $key => $value) {
        // Check if setting already exists
        $checkSql = "SELECT setting_key FROM xs_settings WHERE setting_key = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$key]);
        $exists = $checkStmt->fetch();
        
        if ($exists) {
            // Update existing
            $sql = "UPDATE xs_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$value, $key]);
            echo "Updated: $key = $value\n";
        } else {
            // Insert new
            $sql = "INSERT INTO xs_settings (setting_key, setting_value, setting_type, created_at, updated_at) 
                    VALUES (?, ?, 'string', NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
            echo "Added: $key = $value\n";
        }
    }
    
    // Remove the date_format field if it exists
    $deleteSql = "DELETE FROM xs_settings WHERE setting_key = 'localization.date_format'";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute();
    echo "Removed: localization.date_format\n";
    
    echo "\nNew settings fields added successfully!\n";
    echo "✅ General tab: telephone_number, mobile_number, business_address\n";
    echo "✅ Localization tab: timezone (Africa/Johannesburg), currency (ZAR)\n";
    echo "✅ Removed: date_format field\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
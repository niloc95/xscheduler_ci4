#!/usr/bin/env php
<?php
/**
 * Test Provider Services API Endpoint
 * 
 * Usage: php test-provider-services.php [provider_id]
 * Example: php test-provider-services.php 2
 */

$providerId = $argv[1] ?? 2;
$url = "http://localhost:8080/api/v1/providers/{$providerId}/services";

echo "Testing Provider Services API Endpoint\n";
echo "=========================================\n";
echo "Provider ID: {$providerId}\n";
echo "URL: {$url}\n\n";

// Make the API call
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'X-Requested-With: XMLHttpRequest'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ CURL Error: {$error}\n";
    exit(1);
}

echo "HTTP Status: {$httpCode}\n";
echo "Response:\n";
echo str_repeat('-', 80) . "\n";

$data = json_decode($response, true);
if ($data === null) {
    echo "❌ Failed to decode JSON response\n";
    echo "Raw response: {$response}\n";
    exit(1);
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo str_repeat('-', 80) . "\n\n";

if ($httpCode === 200 && isset($data['data'])) {
    $serviceCount = count($data['data'] ?? []);
    echo "✅ SUCCESS! Found {$serviceCount} service(s) for provider {$providerId}\n\n";
    
    if ($serviceCount > 0) {
        echo "Services:\n";
        foreach ($data['data'] as $service) {
            echo "  • #{$service['id']}: {$service['name']} ({$service['duration']} min, \${$service['price']})\n";
        }
    } else {
        echo "⚠️  Provider has no services assigned\n";
    }
} else {
    $errorMsg = 'Unknown error';
    if (isset($data['error'])) {
        $errorMsg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
    }
    echo "❌ FAILED! Error: {$errorMsg}\n";
    exit(1);
}

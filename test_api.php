<?php
// Simple test script to check API endpoint
$url = 'http://localhost:8080/api/appointments?start=2025-09-01&end=2025-09-30';
$options = [
    'http' => [
        'method' => 'GET',
        'header' => 'Accept: application/json',
    ]
];
$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "Response: " . $result . "\n";
echo "HTTP Response Headers: \n";
print_r($http_response_header);
?>

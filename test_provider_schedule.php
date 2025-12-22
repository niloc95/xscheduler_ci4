<?php
require_once 'vendor/autoload.php';

// Boot CodeIgniter
$app = require_once 'app/Config/Paths.php';
$app = new \CodeIgniter\CodeIgniter($app);
$app->initialize();

// Test the model
$model = new \App\Models\ProviderScheduleModel();

echo "Testing ProviderScheduleModel...\n";

try {
    $results = $model->findAll();
    echo "Total records: " . count($results) . "\n";
    if (count($results) > 0) {
        echo "First record:\n";
        print_r($results[0]);
    }

    echo "\nTesting getActiveDay method...\n";
    $result = $model->getActiveDay(2, 'sunday');
    print_r($result);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

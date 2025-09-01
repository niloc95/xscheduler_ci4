<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// Default route - handle proper application flow
$routes->get('/', 'AppFlow::index');

// Setup Routes (accessible without authentication)
$routes->get('setup', 'Setup::index');
$routes->post('setup/process', 'Setup::process');
$routes->post('setup/test-connection', 'Setup::testConnection');

$routes->get('tw', 'Tw::tw');

// Authentication Routes (require setup to be completed)
$routes->group('auth', function($routes) {
    $routes->get('login', 'Auth::login', ['filter' => 'setup']);
    $routes->post('attemptLogin', 'Auth::attemptLogin', ['filter' => 'setup']);
    $routes->get('logout', 'Auth::logout');
    $routes->get('forgot-password', 'Auth::forgotPassword', ['filter' => 'setup']);
    $routes->post('send-reset-link', 'Auth::sendResetLink', ['filter' => 'setup']);
    $routes->get('reset-password/(:segment)', 'Auth::resetPassword/$1', ['filter' => 'setup']);
    $routes->post('update-password', 'Auth::updatePassword', ['filter' => 'setup']);
});

// Dashboard Routes (require both setup and authentication)
// Method 1: Using nested groups (setup filter on group, auth filter on individual routes)
$routes->group('dashboard', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'Dashboard::index', ['filter' => 'auth']);
    $routes->get('simple', 'Dashboard::simple', ['filter' => 'auth']);
    $routes->get('test', 'Dashboard::test', ['filter' => 'auth']);
    $routes->get('test-db', 'Dashboard::test_db', ['filter' => 'auth']);
    $routes->get('real-data', 'Dashboard::realData', ['filter' => 'auth']);
    $routes->get('api', 'Dashboard::api', ['filter' => 'auth']);
    $routes->get('charts', 'Dashboard::charts', ['filter' => 'auth']);
    $routes->get('analytics', 'Dashboard::analytics', ['filter' => 'auth']);
    $routes->get('status', 'Dashboard::status', ['filter' => 'auth']);
});

// Alternative Method 2: Using combined filter (uncomment to use instead)
// $routes->get('dashboard', 'Dashboard::index', ['filter' => 'setup_auth']);
// $routes->get('dashboard/simple', 'Dashboard::simple', ['filter' => 'setup_auth']);
// $routes->get('dashboard/test', 'Dashboard::test', ['filter' => 'setup_auth']);
// $routes->get('dashboard/test-db', 'Dashboard::test_db', ['filter' => 'setup_auth']);
// $routes->get('dashboard/real-data', 'Dashboard::realData', ['filter' => 'setup_auth']);
// $routes->get('dashboard/api', 'Dashboard::api', ['filter' => 'setup_auth']);
// $routes->get('dashboard/charts', 'Dashboard::charts', ['filter' => 'setup_auth']);
// $routes->get('dashboard/analytics', 'Dashboard::analytics', ['filter' => 'setup_auth']);
// $routes->get('dashboard/status', 'Dashboard::status', ['filter' => 'setup_auth']);

// Style Guide Routes
$routes->get('styleguide', 'Styleguide::index');
$routes->get('styleguide/components', 'Styleguide::components');
$routes->get('styleguide/scheduler', 'Styleguide::scheduler');

// Dark Mode Test Route
$routes->get('dark-mode-test', 'DarkModeTest::index');

// Scheduler Routes
// Admin/staff dashboard-facing scheduler (requires setup + auth)
$routes->group('scheduler', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'Scheduler::dashboard', ['filter' => 'auth']);
});

// Public/client-facing booking view
$routes->get('book', 'Scheduler::client', ['filter' => 'setup']);

// Scheduler API routes
$routes->group('api', ['filter' => 'setup', 'filter' => 'api_cors'], function($routes) {
    // Legacy simple endpoints
    $routes->get('slots', 'Scheduler::slots');
    $routes->post('book', 'Scheduler::book');

    // Versioned API v1
    $routes->group('v1', ['filter' => 'api_auth'], function($routes) {
        $routes->get('availabilities', 'Api\\V1\\Availabilities::index');
        $routes->resource('appointments', ['controller' => 'Api\\V1\\Appointments']);
        $routes->get('services', 'Api\\V1\\Services::index');
        $routes->get('providers', 'Api\\V1\\Providers::index');
    $routes->post('providers/(\d+)/profile-image', 'Api\\V1\\Providers::uploadProfileImage/$1');
    // Settings API
    $routes->get('settings', 'Api\\V1\\Settings::index');
    $routes->put('settings', 'Api\\V1\\Settings::update');
    });
});

// Settings (require setup + auth)
$routes->group('', ['filter' => 'setup'], function($routes) {
    $routes->get('settings', 'Settings::index', ['filter' => 'auth']);
    // Temporarily allow POST without auth to debug uploads
    $routes->post('settings', 'Settings::save');
});

// Development override: allow Settings POST without auth to debug file uploads
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $routes->group('', ['filter' => 'setup'], function($routes) {
        // Re-declare POST route without auth to take precedence
        $routes->post('settings', 'Settings::save');
    });
}

// Public assets (serve files from public/assets)
$routes->get('assets/s/(:segment)', 'Assets::settings/$1');
// Legacy provider assets from uploads/providers via controller
$routes->get('assets/p/(:segment)', 'Assets::provider/$1');
// Public assets from DB store
$routes->get('assets/db/(:any)', 'Assets::settingsDb/$1');

// Upload test (debugging only - no auth required)
$routes->get('upload-test', 'UploadTest::index');
$routes->post('upload-test/upload', 'UploadTest::doUpload');

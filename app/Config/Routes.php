<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// Default route - redirect to login if not authenticated
$routes->get('/', function() {
    if (session()->get('isLoggedIn')) {
        return redirect()->to('/dashboard');
    }
    return redirect()->to('/auth/login');
});

// Setup Routes
$routes->get('setup', 'Setup::index');
$routes->post('setup/process', 'Setup::process');
$routes->post('setup/test-connection', 'Setup::testConnection');

$routes->get('tw', 'Tw::tw');

// Authentication Routes
$routes->group('auth', function($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('attemptLogin', 'Auth::attemptLogin');
    $routes->get('logout', 'Auth::logout');
    $routes->get('forgot-password', 'Auth::forgotPassword');
    $routes->post('send-reset-link', 'Auth::sendResetLink');
    $routes->get('reset-password/(:segment)', 'Auth::resetPassword/$1');
    $routes->post('update-password', 'Auth::updatePassword');
});

// Dashboard Routes
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);
$routes->get('dashboard/simple', 'Dashboard::simple');
$routes->get('dashboard/test', 'Dashboard::test');
$routes->get('dashboard/test-db', 'Dashboard::test_db');
$routes->get('dashboard/real-data', 'Dashboard::realData');
$routes->get('dashboard/api', 'Dashboard::api');
$routes->get('dashboard/charts', 'Dashboard::charts');
$routes->get('dashboard/analytics', 'Dashboard::analytics');
$routes->get('dashboard/status', 'Dashboard::status');

// Style Guide Routes
$routes->get('styleguide', 'Styleguide::index');
$routes->get('styleguide/components', 'Styleguide::components');
$routes->get('styleguide/scheduler', 'Styleguide::scheduler');

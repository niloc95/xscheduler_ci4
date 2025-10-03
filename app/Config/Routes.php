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
    $routes->get('api/user-counts', 'Api\\Users::counts', ['filter' => 'auth']);
    $routes->get('api/users', 'Api\\Users::index', ['filter' => 'auth']);
    // $routes->get('analytics', 'Dashboard::analytics', ['filter' => 'auth']); // Moved to dedicated Analytics controller
    $routes->get('status', 'Dashboard::status', ['filter' => 'auth']);
});

// Global user/customer lightweight API endpoints (session-auth protected)
$routes->get('api/user-counts', 'Api\\Users::counts', ['filter' => 'auth']);
$routes->get('api/users', 'Api\\Users::index', ['filter' => 'auth']);

// User Management Routes (admin and provider access with different permissions)
$routes->group('user-management', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'UserManagement::index', ['filter' => 'role:admin,provider']);
    $routes->get('create', 'UserManagement::create', ['filter' => 'role:admin,provider']);
    $routes->post('store', 'UserManagement::store', ['filter' => 'role:admin,provider']);
    $routes->get('edit/(:num)', 'UserManagement::edit/$1', ['filter' => 'role:admin,provider']);
    $routes->post('update/(:num)', 'UserManagement::update/$1', ['filter' => 'role:admin,provider']);
    $routes->get('deactivate/(:num)', 'UserManagement::deactivate/$1', ['filter' => 'role:admin,provider']);
    $routes->get('activate/(:num)', 'UserManagement::activate/$1', ['filter' => 'role:admin,provider']);
});

// Customer Management Routes (admins, providers, and staff)
$routes->group('customer-management', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'CustomerManagement::index', ['filter' => 'role:admin,provider,staff']);
    $routes->get('create', 'CustomerManagement::create', ['filter' => 'role:admin,provider,staff']);
    $routes->post('store', 'CustomerManagement::store', ['filter' => 'role:admin,provider,staff']);
    $routes->get('edit/(:num)', 'CustomerManagement::edit/$1', ['filter' => 'role:admin,provider,staff']);
    $routes->post('update/(:num)', 'CustomerManagement::update/$1', ['filter' => 'role:admin,provider,staff']);
});

// Services Routes (auth required for viewing, admin/provider for management)
$routes->group('services', function($routes) {
    $routes->get('', 'Services::index');
    $routes->get('view/(:num)', 'Services::view/$1');
    $routes->get('create', 'Services::create');
    $routes->post('store', 'Services::store');
    $routes->get('edit/(:num)', 'Services::edit/$1');
    $routes->post('update/(:num)', 'Services::update/$1');
    $routes->post('delete/(:num)', 'Services::delete/$1');
    $routes->get('categories', 'Services::categories');
    $routes->get('categories/create', 'Services::createCategory');
    $routes->post('categories', 'Services::storeCategory');
    $routes->get('categories/edit/(:num)', 'Services::editCategory/$1');
    $routes->post('categories/update/(:num)', 'Services::updateCategory/$1');
    $routes->post('categories/(:num)/activate', 'Services::activateCategory/$1');
    $routes->post('categories/(:num)/deactivate', 'Services::deactivateCategory/$1');
    $routes->post('categories/(:num)/delete', 'Services::deleteCategory/$1');
});

// Analytics Routes (admin and provider access)
$routes->group('analytics', function($routes) {
    $routes->get('', 'Analytics::index');
    $routes->get('appointments', 'Analytics::appointments');
    $routes->get('revenue', 'Analytics::revenue');
    $routes->get('customers', 'Analytics::customers');
    $routes->get('export', 'Analytics::export');
});

// Notifications Routes (auth required)
$routes->group('notifications', function($routes) {
    $routes->get('', 'Notifications::index');
    $routes->post('mark-read/(:num)', 'Notifications::markRead/$1');
    $routes->post('mark-all-read', 'Notifications::markAllRead');
    $routes->post('delete/(:num)', 'Notifications::delete/$1');
    $routes->get('settings', 'Notifications::settings');
    $routes->post('update-settings', 'Notifications::updateSettings');
});

// Profile Routes (auth required)
$routes->group('profile', function($routes) {
    $routes->get('', 'Profile::index');
    $routes->get('edit', 'Profile::edit');
    $routes->post('update', 'Profile::update');
    $routes->get('password', 'Profile::password');
    $routes->post('update-password', 'Profile::updatePassword');
    $routes->post('upload-picture', 'Profile::uploadPicture');
    $routes->get('privacy', 'Profile::privacy');
    $routes->post('update-privacy', 'Profile::updatePrivacy');
    $routes->get('account', 'Profile::account');
    $routes->post('update-account', 'Profile::updateAccount');
});

// Appointments Routes (auth required)
$routes->group('appointments', function($routes) {
    $routes->get('', 'Appointments::index');
    $routes->get('view/(:num)', 'Appointments::view/$1');
    $routes->get('create', 'Appointments::create');
    $routes->post('store', 'Appointments::store');
    $routes->get('edit/(:num)', 'Appointments::edit/$1');
    $routes->post('update/(:num)', 'Appointments::update/$1');
    $routes->post('cancel/(:num)', 'Appointments::cancel/$1');
});

// Help Routes (some require auth)
$routes->group('help', function($routes) {
    $routes->get('', 'Help::index');
    $routes->get('search', 'Help::search');
    $routes->get('getting-started', 'Help::gettingStarted');
    $routes->get('appointments', 'Help::appointments');
    $routes->get('services', 'Help::services');
    $routes->get('account-billing', 'Help::accountBilling');
    $routes->get('contact', 'Help::contact');
    $routes->post('contact', 'Help::sendContact');
    $routes->get('chat', 'Help::chat');
    $routes->get('article/(:segment)', 'Help::article/$1');
    $routes->get('status', 'Help::status');
    $routes->get('keyboard-shortcuts', 'Help::keyboardShortcuts');
    $routes->get('video-tutorials', 'Help::videoTutorials');
    $routes->get('api-docs', 'Help::apiDocs');
    $routes->get('community', 'Help::community');
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
    // Default scheduler page
    $routes->get('', 'Scheduler::index', ['filter' => 'auth']);
});

// Public/client-facing booking view
$routes->get('book', 'Scheduler::client', ['filter' => 'setup']);

// Scheduler API routes
$routes->group('api', ['filter' => 'setup', 'filter' => 'api_cors'], function($routes) {
    // Legacy simple endpoints
    $routes->get('slots', 'Scheduler::slots');
    $routes->post('book', 'Scheduler::book');

    // Declare specific endpoints BEFORE resource to avoid shadowing by appointments/{id}
    // Unversioned summary metrics
    $routes->get('appointments/summary', 'Api\\V1\\Appointments::summary');
    // Unversioned counts for convenience (matches v1 controller)
    $routes->get('appointments/counts', 'Api\\V1\\Appointments::counts');
    // Unversioned appointments resource (alias to v1 controller) - restrict ID to numeric
    $routes->resource('appointments', [
        'controller' => 'Api\\V1\\Appointments',
        'placeholder' => '(:num)'
    ]);

    // Versioned API v1
    $routes->group('v1', ['filter' => 'api_auth'], function($routes) {
        $routes->get('availabilities', 'Api\\V1\\Availabilities::index');
        // Declare specific endpoints BEFORE resource to avoid shadowing by appointments/{id}
        $routes->get('appointments/summary', 'Api\\V1\\Appointments::summary');
        $routes->get('appointments/counts', 'Api\\V1\\Appointments::counts');
        // Versioned appointments resource - restrict ID to numeric
        $routes->resource('appointments', [
            'controller' => 'Api\\V1\\Appointments',
            'placeholder' => '(:num)'
        ]);
        $routes->get('services', 'Api\\V1\\Services::index');
        $routes->get('providers', 'Api\\V1\\Providers::index');
    $routes->post('providers/(\d+)/profile-image', 'Api\\V1\\Providers::uploadProfileImage/$1');
    // Settings API
    $routes->get('settings', 'Api\\V1\\Settings::index');
    $routes->put('settings', 'Api\\V1\\Settings::update');
    $routes->post('settings/logo', 'Api\\V1\\Settings::uploadLogo');
    });
});

// Settings (require setup + auth + admin role)
$routes->group('', ['filter' => 'setup'], function($routes) {
    $routes->get('settings', 'Settings::index', ['filter' => 'role:admin']);
    // Temporarily allow POST without auth to debug uploads
    $routes->post('settings', 'Settings::save', ['filter' => 'role:admin']);
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

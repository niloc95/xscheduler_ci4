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

// Standalone login route (commonly referenced)
$routes->get('login', 'Auth::login', ['filter' => 'setup']);
$routes->post('login', 'Auth::attemptLogin', ['filter' => 'setup']);

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
$routes->group('dashboard', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'Dashboard::index', ['filter' => 'auth']);
    $routes->get('api', 'Dashboard::api', ['filter' => 'auth']);
    $routes->get('api/metrics', 'Dashboard::apiMetrics', ['filter' => 'auth']); // New landing view metrics endpoint
    $routes->get('charts', 'Dashboard::charts', ['filter' => 'auth']);
    $routes->get('status', 'Dashboard::status', ['filter' => 'auth']);
    $routes->get('search', 'Dashboard::search', ['filter' => 'auth']);
});

// User Management Routes (admin and provider access with different permissions)
$routes->group('user-management', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'UserManagement::index', ['filter' => 'role:admin,provider']);
    $routes->get('create', 'UserManagement::create', ['filter' => 'role:admin,provider']);
    $routes->post('store', 'UserManagement::store', ['filter' => 'role:admin,provider']);
    $routes->get('edit/(:num)', 'UserManagement::edit/$1', ['filter' => 'role:admin,provider']);
    $routes->post('update/(:num)', 'UserManagement::update/$1', ['filter' => 'role:admin,provider']);
    $routes->post('deactivate/(:num)', 'UserManagement::deactivate/$1', ['filter' => 'role:admin,provider']);
    $routes->post('activate/(:num)', 'UserManagement::activate/$1', ['filter' => 'role:admin,provider']);
    $routes->post('delete/(:num)', 'UserManagement::delete/$1', ['filter' => 'role:admin']);
});

// Customer Management Routes (admins, providers, and staff)
$routes->group('customer-management', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'CustomerManagement::index', ['filter' => 'role:admin,provider,staff']);
    $routes->get('search', 'CustomerManagement::ajaxSearch', ['filter' => 'role:admin,provider,staff']);
    $routes->get('create', 'CustomerManagement::create', ['filter' => 'role:admin,provider,staff']);
    $routes->post('store', 'CustomerManagement::store', ['filter' => 'role:admin,provider,staff']);
    $routes->get('edit/(:any)', 'CustomerManagement::edit/$1', ['filter' => 'role:admin,provider,staff']);
    $routes->post('update/(:any)', 'CustomerManagement::update/$1', ['filter' => 'role:admin,provider,staff']);
    $routes->get('history/(:any)', 'CustomerManagement::history/$1', ['filter' => 'role:admin,provider,staff']);
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
$routes->group('profile', ['filter' => 'auth'], function($routes) {
    $routes->get('', 'Profile::index');
    $routes->post('update-profile', 'Profile::updateProfile');
    $routes->post('change-password', 'Profile::changePassword');
    $routes->post('upload-picture', 'Profile::uploadPicture');
    $routes->get('privacy', 'Profile::privacy');
    $routes->post('update-privacy', 'Profile::updatePrivacy');
    $routes->get('account', 'Profile::account');
    $routes->post('update-account', 'Profile::updateAccount');
});

// Provider schedules (auth required, controller handles authorization)
$routes->group('providers', ['filter' => 'setup'], function($routes) {
    $routes->get('(:num)/schedule', 'ProviderSchedule::index/$1', ['filter' => 'auth']);
    $routes->post('(:num)/schedule', 'ProviderSchedule::save/$1', ['filter' => 'auth']);
    $routes->delete('(:num)/schedule', 'ProviderSchedule::delete/$1', ['filter' => 'auth']);
});

// Provider staff assignments
$routes->group('provider-staff', ['filter' => 'setup'], function($routes) {
    $routes->get('provider/(:num)', 'ProviderStaff::list/$1', ['filter' => 'role:admin,provider']);
    $routes->post('assign', 'ProviderStaff::assign', ['filter' => 'role:admin,provider']);
    $routes->post('remove', 'ProviderStaff::remove', ['filter' => 'role:admin,provider']);
});

// Staff provider assignments (reverse direction)
$routes->group('staff-providers', ['filter' => 'setup'], function($routes) {
    $routes->get('staff/(:num)', 'StaffProviders::list/$1', ['filter' => 'role:admin,staff']);
    $routes->post('assign', 'StaffProviders::assign', ['filter' => 'role:admin']);
    $routes->post('remove', 'StaffProviders::remove', ['filter' => 'role:admin']);
});

// Appointments Routes (auth required)
$routes->group('appointments', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'Appointments::index', ['filter' => 'auth']);
    $routes->get('view/(:any)', 'Appointments::view/$1', ['filter' => 'auth']);
    $routes->get('create', 'Appointments::create', ['filter' => 'auth']);
    $routes->post('store', 'Appointments::store', ['filter' => 'auth']);
    $routes->get('edit/(:any)', 'Appointments::edit/$1', ['filter' => 'auth']);
    $routes->post('update/(:any)', 'Appointments::update/$1', ['filter' => 'auth']);
    $routes->put('update/(:any)', 'Appointments::update/$1', ['filter' => 'auth']);  // Support PUT method
    $routes->post('cancel/(:any)', 'Appointments::cancel/$1', ['filter' => 'auth']);
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

// Scheduler Routes
// Admin/staff dashboard-facing scheduler (requires setup + auth)
$routes->group('scheduler', ['filter' => 'setup'], function($routes) {
    // Default scheduler page
    $routes->get('', 'Scheduler::index', ['filter' => 'auth']);
});

// Public/client-facing booking view (legacy)
$routes->get('book', 'Scheduler::client', ['filter' => 'setup']);

// New dedicated public booking experience (Option B)
$routes->group('public/booking', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'PublicSite\BookingController::index', ['filter' => 'public_rate_limit']);
    $routes->get('slots', 'PublicSite\BookingController::slots', ['filter' => 'public_rate_limit']);
    $routes->get('calendar', 'PublicSite\BookingController::calendar', ['filter' => 'public_rate_limit']);
    $routes->post('', 'PublicSite\BookingController::store', ['filter' => ['public_rate_limit', 'csrf']]);
    $routes->get('(:segment)', 'PublicSite\BookingController::show/$1', ['filter' => 'public_rate_limit']);
    $routes->patch('(:segment)', 'PublicSite\BookingController::update/$1', ['filter' => ['public_rate_limit', 'csrf']]);
});

// Public customer portal - My Appointments (no auth, uses customer hash)
$routes->group('public/my-appointments', ['filter' => 'setup'], function($routes) {
    $routes->get('(:segment)', 'PublicSite\CustomerPortalController::index/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/upcoming', 'PublicSite\CustomerPortalController::upcoming/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/history', 'PublicSite\CustomerPortalController::history/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/autofill', 'PublicSite\CustomerPortalController::autofill/$1', ['filter' => 'public_rate_limit']);
});

// Scheduler API routes
$routes->group('api', ['filter' => ['setup', 'api_cors']], function($routes) {
    // Dashboard API endpoint
    $routes->get('dashboard/appointment-stats', 'Api\\Dashboard::appointmentStats');

    // Legacy simple endpoints
    $routes->get('slots', 'Scheduler::slots');
    $routes->post('book', 'Scheduler::book');

    // Calendar prototype routes removed (archived)

    // Customer Appointments API (history, stats, autofill)
    $routes->get('customers/(:num)/appointments/upcoming', 'Api\\CustomerAppointments::upcoming/$1');
    $routes->get('customers/(:num)/appointments/history', 'Api\\CustomerAppointments::history/$1');
    $routes->get('customers/(:num)/appointments/stats', 'Api\\CustomerAppointments::stats/$1');
    $routes->get('customers/(:num)/appointments', 'Api\\CustomerAppointments::index/$1');
    $routes->get('customers/(:num)/autofill', 'Api\\CustomerAppointments::autofill/$1');
    $routes->get('customers/by-hash/(:segment)/appointments', 'Api\\CustomerAppointments::byHash/$1');
    $routes->get('customers/by-hash/(:segment)/autofill', 'Api\\CustomerAppointments::autofillByHash/$1');
    $routes->get('appointments/search', 'Api\\CustomerAppointments::search');
    $routes->get('appointments/filters', 'Api\\CustomerAppointments::filterOptions');

    // Consolidated Appointments API (unversioned, future-proof)
    // Specific endpoints BEFORE resource to avoid shadowing
    $routes->post('appointments', 'Api\\Appointments::create');
    $routes->get('appointments/summary', 'Api\\Appointments::summary');
    $routes->get('appointments/counts', 'Api\\Appointments::counts');
    $routes->post('appointments/check-availability', 'Api\\Appointments::checkAvailability');
    $routes->get('appointments/(:num)', 'Api\\Appointments::show/$1');
    $routes->patch('appointments/(:num)', 'Api\\Appointments::update/$1');
    $routes->delete('appointments/(:num)', 'Api\\Appointments::delete/$1');
    $routes->patch('appointments/(:num)/status', 'Api\\Appointments::updateStatus/$1');
    $routes->post('appointments/(:num)/notify', 'Api\\Appointments::notify/$1');
    $routes->get('appointments', 'Api\\Appointments::index');
    $routes->get('dashboard/appointment-stats', 'Api\\Dashboard::appointmentStats', ['filter' => 'auth']);

    // Availability API - Comprehensive slot availability calculation
    $routes->get('availability/slots', 'Api\\Availability::slots');
    $routes->post('availability/check', 'Api\\Availability::check');
    $routes->get('availability/summary', 'Api\\Availability::summary');
    $routes->get('availability/calendar', 'Api\\Availability::calendar');
    $routes->get('availability/next-available', 'Api\\Availability::nextAvailable');

    // Locations API - Provider multi-location support
    $routes->get('locations', 'Api\\Locations::index');
    $routes->get('locations/for-date', 'Api\\Locations::forDate');
    $routes->get('locations/available-dates', 'Api\\Locations::availableDates');
    $routes->get('locations/(:num)', 'Api\\Locations::show/$1');
    $routes->post('locations', 'Api\\Locations::create');
    $routes->put('locations/(:num)', 'Api\\Locations::update/$1');
    $routes->patch('locations/(:num)', 'Api\\Locations::update/$1');
    $routes->delete('locations/(:num)', 'Api\\Locations::delete/$1');
    $routes->post('locations/(:num)/set-primary', 'Api\\Locations::setPrimary/$1');

    // Public API endpoints (no auth required)
    $routes->group('v1', function($routes) {
        // Settings endpoints - public for frontend initialization
        $routes->get('settings/calendar-config', 'Api\\V1\\Settings::calendarConfig');
        $routes->get('settings/calendarConfig', 'Api\\V1\\Settings::calendarConfig'); // Alternative naming
        $routes->get('settings/localization', 'Api\\V1\\Settings::localization');
        $routes->get('settings/booking', 'Api\\V1\\Settings::booking');
        $routes->get('settings/business-hours', 'Api\\V1\\Settings::businessHours');
        // Provider services - public for booking form
        $routes->get('providers/(:num)/services', 'Api\\V1\\Providers::services/$1');
        // Provider appointments - for monthly schedule view
        $routes->get('providers/(:num)/appointments', 'Api\\V1\\Providers::appointments/$1');
    });
    
    // Public providers endpoint (no auth required for calendar)
    $routes->get('providers', 'Api\\V1\\Providers::index');

    // Versioned API v1 (authenticated) - only non-appointment endpoints
    $routes->group('v1', ['filter' => 'api_auth'], function($routes) {
        $routes->get('services', 'Api\\V1\\Services::index');
        // Note: providers route is public (defined above) for calendar access
        $routes->post('providers/(\d+)/profile-image', 'Api\\V1\\Providers::uploadProfileImage/$1');
        // Settings API (authenticated)
        $routes->get('settings', 'Api\\V1\\Settings::index');
        // Some production hosts/WAFs block PUT requests; accept POST as well.
        $routes->match(['put', 'post'], 'settings', 'Api\\V1\\Settings::update');
        $routes->post('settings/logo', 'Api\\V1\\Settings::uploadLogo');
    });
});

// Database Backup API (admin only, outside versioned API for cleaner URLs)
$routes->group('api/database-backup', ['filter' => 'setup'], function($routes) {
    $routes->get('status', 'Api\\DatabaseBackup::status', ['filter' => 'role:admin']);
    $routes->get('list', 'Api\\DatabaseBackup::list', ['filter' => 'role:admin']);
    $routes->post('create', 'Api\\DatabaseBackup::create', ['filter' => 'role:admin']);
    $routes->post('toggle', 'Api\\DatabaseBackup::toggleBackup', ['filter' => 'role:admin']);
    $routes->get('download/(:segment)', 'Api\\DatabaseBackup::download/$1', ['filter' => 'role:admin']);
    $routes->delete('delete/(:segment)', 'Api\\DatabaseBackup::delete/$1', ['filter' => 'role:admin']);
});

// Settings (require setup + auth + admin role)
$routes->group('', ['filter' => 'setup'], function($routes) {
    $routes->get('settings', 'Settings::index', ['filter' => 'role:admin']);
    $routes->post('settings', 'Settings::save', ['filter' => 'role:admin']);
    $routes->post('settings/notifications', 'Settings::saveNotifications', ['filter' => 'role:admin']);
});

// Public assets (serve files from public/assets)
$routes->get('assets/s/(:segment)', 'Assets::settings/$1');
// Legacy provider assets from uploads/providers via controller
$routes->get('assets/p/(:segment)', 'Assets::provider/$1');
// Public assets from DB store
$routes->get('assets/db/(:any)', 'Assets::settingsDb/$1');

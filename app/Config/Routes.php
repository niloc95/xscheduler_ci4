<?php

/**
 * =============================================================================
 * ROUTES CONFIGURATION
 * =============================================================================
 * 
 * @file        app/Config/Routes.php
 * @description Defines all HTTP routes for the WebSchedulr application.
 *              Maps URLs to controller methods and applies middleware filters.
 * 
 * ROUTE GROUPS:
 * -----------------------------------------------------------------------------
 * - Public Routes     : Setup wizard, login, password reset (no auth required)
 * - Dashboard Routes  : Main dashboard and metrics (requires auth)
 * - User Management   : CRUD for users (admin/provider only)
 * - Appointments      : Booking management (authenticated users)
 * - Customers         : Customer records (staff+ roles)
 * - Services          : Service catalog management (admin only)
 * - Settings          : Application configuration (admin only)
 * - API Routes        : RESTful endpoints under /api/v1/
 * - Public Booking    : Customer-facing booking pages (no auth)
 * 
 * FILTERS APPLIED:
 * -----------------------------------------------------------------------------
 * - 'setup'           : Ensures initial setup is completed
 * - 'auth'            : Requires user to be logged in
 * - 'role:admin'      : Restricts to admin users only
 * - 'role:admin,provider' : Allows admin and provider roles
 * 
 * API VERSIONING:
 * -----------------------------------------------------------------------------
 * All API routes are versioned under /api/v1/ for future compatibility.
 * 
 * @see         app/Config/Filters.php for filter definitions
 * @see         app/Controllers/ for route handler implementations
 * @package     App\Config
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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
    $routes->get('search', 'Search::dashboard', ['filter' => 'auth']); // Moved to Search controller
});

// Global Search Route (accessible from anywhere)
$routes->get('search', 'Search::index', ['filter' => 'auth']);

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
$routes->group('services', ['filter' => ['setup', 'auth']], function($routes) {
    $routes->get('', 'Services::index');
    // Note: view route removed - use edit instead
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
$routes->group('analytics', ['filter' => 'role:admin,provider'], function($routes) {
    $routes->get('', 'Analytics::index');
    $routes->get('revenue', 'Analytics::revenue');
    $routes->get('customers', 'Analytics::customers');
    // Note: appointments and export routes removed - methods don't exist
});

// Notifications Routes (auth required)
$routes->group('notifications', ['filter' => 'auth'], function($routes) {
    $routes->get('', 'Notifications::index');
    $routes->post('mark-read/(:num)', 'Notifications::markAsRead/$1');
    $routes->post('mark-all-read', 'Notifications::markAllAsRead');
    $routes->post('delete/(:num)', 'Notifications::delete/$1');
    $routes->get('settings', 'Notifications::settings');
    // Note: update-settings removed - settings page redirects to main settings
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
    // Note: cancel route removed - use API PATCH /api/appointments/:id/status instead
});

// Help Routes (some require auth)
$routes->group('help', function($routes) {
    $routes->get('', 'Help::index');
    $routes->get('search', 'Help::search');
    $routes->get('faq', 'Help::faq');
    $routes->get('tutorials', 'Help::tutorials');
    $routes->get('contact', 'Help::contact', ['filter' => 'auth']);
    $routes->post('contact', 'Help::submitTicket', ['filter' => 'auth']);
    $routes->get('article/(:segment)', 'Help::article/$1', ['filter' => 'auth']);
    $routes->get('category/(:segment)', 'Help::category/$1', ['filter' => 'auth']);
    // Note: Removed dead routes (getting-started, appointments, services, account-billing,
    //       chat, status, keyboard-shortcuts, api-docs, community) - methods don't exist
});

// Public booking experience
// Note: Legacy /scheduler and GET /book routes removed (deprecated since v2.0, sunset 2026-03-01)
// New dedicated public booking experience (Option B)
$routes->group('booking', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'PublicSite\BookingController::index', ['filter' => 'public_rate_limit']);
    $routes->get('slots', 'PublicSite\BookingController::slots', ['filter' => 'public_rate_limit']);
    $routes->get('calendar', 'PublicSite\BookingController::calendar', ['filter' => 'public_rate_limit']);
    $routes->post('', 'PublicSite\BookingController::store', ['filter' => ['public_rate_limit', 'csrf']]);
    $routes->get('(:segment)', 'PublicSite\BookingController::show/$1', ['filter' => 'public_rate_limit']);
    $routes->patch('(:segment)', 'PublicSite\BookingController::update/$1', ['filter' => ['public_rate_limit', 'csrf']]);
});

// Public customer portal - My Appointments (no auth, uses customer hash)
$routes->group('my-appointments', ['filter' => 'setup'], function($routes) {
    $routes->get('(:segment)', 'PublicSite\CustomerPortalController::index/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/upcoming', 'PublicSite\CustomerPortalController::upcoming/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/history', 'PublicSite\CustomerPortalController::history/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/autofill', 'PublicSite\CustomerPortalController::autofill/$1', ['filter' => 'public_rate_limit']);
});

// Scheduler API routes
$routes->group('api', ['filter' => ['setup', 'api_cors']], function($routes) {
    // Dashboard API endpoint
    $routes->get('dashboard/appointment-stats', 'Api\\Dashboard::appointmentStats');

    // User Management API endpoints (admin/provider only)
    $routes->get('users', 'UserManagement::apiList', ['filter' => 'role:admin,provider']);
    $routes->get('user-counts', 'UserManagement::apiCounts', ['filter' => 'role:admin,provider']);

    // Note: Legacy GET /api/slots and POST /api/book removed (deprecated since v2.0, sunset 2026-03-01)
    // Use GET /api/availability/slots and POST /api/appointments instead

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
    $routes->patch('appointments/(:num)/notes', 'Api\\Appointments::updateNotes/$1');
    $routes->post('appointments/(:num)/notify', 'Api\\Appointments::notify/$1');
    $routes->get('appointments', 'Api\\Appointments::index');
    // Note: dashboard/appointment-stats route is defined earlier in this file (line ~191)

    // Calendar API — pre-computed server-side render models (Phase 3+ rebuild)
    // Replaces client-side slot-engine.js computation
    $routes->get('calendar/day',   'Api\\CalendarController::day',   ['filter' => 'auth']);
    $routes->get('calendar/week',  'Api\\CalendarController::week',  ['filter' => 'auth']);
    $routes->get('calendar/month', 'Api\\CalendarController::month', ['filter' => 'auth']);

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
        // Note: Use kebab-case (calendar-config) consistently
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
    // Provider schedule API (auth required for scheduler)
    $routes->get('providers/(:num)/schedule', 'Api\\V1\\Providers::schedule/$1');

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
        $routes->post('settings/icon', 'Api\\V1\\Settings::uploadIcon');
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

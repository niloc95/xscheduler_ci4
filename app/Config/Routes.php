<?php

/**
 * =============================================================================
 * ROUTES CONFIGURATION
 * =============================================================================
 * 
 * @file        app/Config/Routes.php
 * @description Defines all HTTP routes for the WebScheduler application.
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
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
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
$routes->post('setup/testConnection', 'Setup::testConnection');

// Standalone login route (commonly referenced)
$routes->get('login', 'Auth::login', ['filter' => 'setup']);
$routes->post('login', 'Auth::attemptLogin', ['filter' => 'setup']);

// Developer API portal (public — exposes the API contract only, never data).
// Registered outside the setup/auth groups so it renders pre-login.
$routes->get('developers', 'DeveloperDocs::index');
$routes->get('developers/getting-started', 'DeveloperDocs::gettingStarted');
$routes->get('developers/openapi.yaml', 'DeveloperDocs::spec');

// Authentication Routes (require setup to be completed)
$routes->group('auth', function($routes) {
    $routes->get('login', 'Auth::login', ['filter' => 'setup']);
    $routes->post('attemptLogin', 'Auth::attemptLogin', ['filter' => 'setup']);
    $routes->get('logout', 'Auth::logout');
    $routes->get('ping', 'Auth::ping', ['filter' => 'auth']);
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
    $routes->get('api/schedule', 'Dashboard::apiSchedule', ['filter' => 'auth']); // Today's Schedule fragment for polling/event refresh
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
    $routes->get('delete-preview/(:num)', 'UserManagement::deletePreview/$1', ['filter' => 'role:admin']);
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
    $routes->post('delete/(:any)', 'CustomerManagement::delete/$1', ['filter' => 'role:admin']);
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
    $routes->get('categories', 'ServiceCategories::index');
    $routes->get('categories/create', 'ServiceCategories::create');
    $routes->post('categories', 'ServiceCategories::store');
    $routes->get('categories/edit/(:num)', 'ServiceCategories::edit/$1');
    $routes->post('categories/update/(:num)', 'ServiceCategories::update/$1');
    $routes->post('categories/(:num)/activate', 'ServiceCategories::activate/$1');
    $routes->post('categories/(:num)/deactivate', 'ServiceCategories::deactivate/$1');
    $routes->post('categories/(:num)/delete', 'ServiceCategories::delete/$1');
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
    $routes->get('mark-read/(:segment)', 'Notifications::markAsRead/$1');
    $routes->post('mark-read/(:segment)', 'Notifications::markAsRead/$1');
    $routes->get('mark-all-read', 'Notifications::markAllAsRead');
    $routes->post('mark-all-read', 'Notifications::markAllRead');
    $routes->post('delete/(:segment)', 'Notifications::delete/$1');
    $routes->post('resend', 'Notifications::resend');
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
    $routes->post('update-notifications', 'Profile::updateNotifications');
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
$routes->get('sitemap.xml', 'PublicSite\SitemapController::index', ['filter' => ['setup', 'public_rate_limit']]);
$routes->get('r/(:segment)', 'PublicSite\BookingController::reference/$1', ['filter' => ['setup', 'public_rate_limit']]);

$routes->group('booking', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'PublicSite\BookingController::index', ['filter' => 'public_rate_limit']);
    $routes->get('legal', 'PublicSite\LegalController::index', ['filter' => 'public_rate_limit']);
    // Slot/calendar discovery — high read volume, generous bucket.
    $routes->get('slots', 'PublicSite\BookingController::slots', ['filter' => 'public_rate_limit:booking-slots,30,60']);
    $routes->get('calendar', 'PublicSite\BookingController::calendar', ['filter' => 'public_rate_limit:booking-calendar,30,60']);
    // Contact search — tighter limit to resist enumeration.
    $routes->get('search', 'PublicSite\BookingController::search', ['filter' => 'public_rate_limit:booking-search,10,60']);
    $routes->get('discover', 'PublicSite\BookingController::discover', ['filter' => 'public_rate_limit']);
    $routes->get('p/(:segment)', 'PublicSite\BookingController::providerPage/$1', ['filter' => 'public_rate_limit']);
    $routes->get('s/(:segment)', 'PublicSite\BookingController::servicePage/$1', ['filter' => 'public_rate_limit']);
    $routes->get('s/(:segment)/(:segment)', 'PublicSite\BookingController::serviceInCity/$1/$2', ['filter' => 'public_rate_limit']);
    // Booking creation — tightest bucket to resist spam booking.
    $routes->post('', 'PublicSite\BookingController::store', ['filter' => ['public_rate_limit:booking-create,5,60', 'csrf']]);
    $routes->get('r/(:segment)', 'PublicSite\BookingController::reference/$1', ['filter' => 'public_rate_limit']);
    $routes->patch('(:segment)/cancel', 'PublicSite\BookingController::cancel/$1', ['filter' => ['public_rate_limit:booking-mutate,10,60', 'csrf']]);
    $routes->get('(:segment)', 'PublicSite\BookingController::show/$1', ['filter' => 'public_rate_limit']);
    $routes->patch('(:segment)', 'PublicSite\BookingController::update/$1', ['filter' => ['public_rate_limit:booking-mutate,10,60', 'csrf']]);
});

// Payment webhooks — no auth, no CSRF (cryptographically signed by gateway)
// Rate-limited loosely; validation is signature-based inside the controller.
$routes->group('public/payments', ['filter' => 'setup'], function ($routes) {
    $routes->post('payfast/notify', 'PublicSite\PaymentWebhookController::payfastItn',  ['filter' => 'public_rate_limit:payment-itn,60,60']);
    $routes->post('stripe/webhook', 'PublicSite\PaymentWebhookController::stripeWebhook', ['filter' => 'public_rate_limit:payment-webhook,60,60']);
});

// Payment return / cancel pages (browser redirect from gateway)
$routes->group('booking/payment', ['filter' => ['setup', 'public_rate_limit']], function ($routes) {
    $routes->get('return', 'PublicSite\BookingController::paymentReturn');
    $routes->get('cancel', 'PublicSite\BookingController::paymentCancel');
});

// Public customer portal - My Appointments (no auth, uses customer hash)
$routes->group('my-appointments', ['filter' => 'setup'], function($routes) {
    $routes->get('(:segment)', 'PublicSite\CustomerPortalController::index/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/upcoming', 'PublicSite\CustomerPortalController::upcoming/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/history', 'PublicSite\CustomerPortalController::history/$1', ['filter' => 'public_rate_limit']);
    $routes->get('(:segment)/autofill', 'PublicSite\CustomerPortalController::autofill/$1', ['filter' => 'public_rate_limit']);
});

// CORS preflight catch-all. Routes are registered per-verb, so a cross-origin
// OPTIONS request matches nothing and CI4 throws PageNotFoundException during
// routing — before any filter runs. This route gives preflight something to
// match; the api_cors global filter then short-circuits it with 204 and the
// appropriate allow headers (or none, for a disallowed origin).
$routes->options('api/(:any)', static fn() => service('response')->setStatusCode(204));

// Scheduler API routes
/**
 * Authenticated API surface — the documented, externally callable endpoints.
 *
 * Registered twice: once unversioned (`/api/...`, the paths the SPA already
 * calls, kept as undocumented aliases) and once under `/api/v1/...`, which is
 * the canonical path for external clients. Defining them as tuples keeps the
 * two registrations from drifting.
 *
 * All of these use the `api_auth` filter, which accepts EITHER a same-origin
 * session OR a Bearer token from xs_api_keys. Order matters: specific paths are
 * listed before the generic resource route so they are not shadowed.
 *
 * @var array<int, array{0:string,1:string,2:string,3?:string}> [verb, path, handler, filter?]
 */
$xsApiAuthenticatedRoutes = [
    // Dashboard
    ['get',    'dashboard/appointment-stats', 'Api\\Dashboard::appointmentStats'],
    ['get',    'dashboard/provider-slots',    'Api\\Dashboard::providerSlots'],

    // User Management (admin/provider only)
    ['get',    'users',       'UserManagement::apiList',   'api_auth:admin,provider'],
    ['get',    'user-counts', 'UserManagement::apiCounts', 'api_auth:admin,provider'],

    // Customer Appointments (history, stats, autofill)
    ['get',    'customers/(:num)/appointments/upcoming', 'Api\\CustomerAppointments::upcoming/$1'],
    ['get',    'customers/(:num)/appointments/history',  'Api\\CustomerAppointments::history/$1'],
    ['get',    'customers/(:num)/appointments/stats',    'Api\\CustomerAppointments::stats/$1'],
    ['get',    'customers/(:num)/appointments',          'Api\\CustomerAppointments::index/$1'],
    ['get',    'customers/(:num)/autofill',              'Api\\CustomerAppointments::autofill/$1'],
    ['get',    'appointments/search',                    'Api\\CustomerAppointments::search'],
    ['get',    'appointments/filters',                   'Api\\CustomerAppointments::filterOptions'],

    // Appointments — specific endpoints BEFORE the resource routes
    ['post',   'appointments',                        'Api\\Appointments::create'],
    ['get',    'appointments/summary',                'Api\\Appointments::summary'],
    ['get',    'appointments/counts',                 'Api\\Appointments::counts'],
    ['post',   'appointments/check-availability',     'Api\\Appointments::checkAvailability'],
    ['get',    'appointments/(:num)',                 'Api\\Appointments::show/$1'],
    ['patch',  'appointments/(:num)',                 'Api\\Appointments::update/$1'],
    ['delete', 'appointments/(:num)',                 'Api\\Appointments::delete/$1'],
    ['patch',  'appointments/(:num)/status',          'Api\\Appointments::updateStatus/$1'],
    ['patch',  'appointments/(:num)/notes',           'Api\\Appointments::updateNotes/$1'],
    ['patch',  'appointments/(:num)/payment-status',  'Api\\Appointments::updatePaymentStatus/$1'],
    ['post',   'appointments/(:num)/notify',          'Api\\Appointments::notify/$1'],
    ['get',    'appointments',                        'Api\\Appointments::index'],

    // Calendar — pre-computed server-side render models (Phase 3+ rebuild)
    ['get',    'calendar/day',   'Api\\CalendarController::day'],
    ['get',    'calendar/week',  'Api\\CalendarController::week'],
    ['get',    'calendar/month', 'Api\\CalendarController::month'],

    // Locations — writes only; the reads below are public
    ['post',   'locations',                     'Api\\Locations::create'],
    ['put',    'locations/(:num)',              'Api\\Locations::update/$1'],
    ['patch',  'locations/(:num)',              'Api\\Locations::update/$1'],
    ['delete', 'locations/(:num)',              'Api\\Locations::delete/$1'],
    ['post',   'locations/(:num)/set-primary',  'Api\\Locations::setPrimary/$1'],

    // Customers — full CRUD. Reads need any authenticated caller; writes are
    // staff-and-up. The customers/(:num)/... sub-routes above are registered
    // earlier so they are matched before the bare resource routes.
    ['get',    'customers',        'Api\\V1\\Customers::index'],
    ['post',   'customers',        'Api\\V1\\Customers::create',   'api_auth:admin,provider,staff'],
    ['get',    'customers/(:num)', 'Api\\V1\\Customers::show/$1'],
    ['put',    'customers/(:num)', 'Api\\V1\\Customers::update/$1', 'api_auth:admin,provider,staff'],
    ['patch',  'customers/(:num)', 'Api\\V1\\Customers::update/$1', 'api_auth:admin,provider,staff'],
    ['delete', 'customers/(:num)', 'Api\\V1\\Customers::delete/$1', 'api_auth:admin,provider,staff'],

    // Services — full CRUD. Writes are admin/provider. (Index was previously
    // the only routed API method; the controller's write methods now have paths.)
    ['get',    'services',        'Api\\V1\\Services::index'],
    ['post',   'services',        'Api\\V1\\Services::create',   'api_auth:admin,provider'],
    ['get',    'services/(:num)', 'Api\\V1\\Services::show/$1'],
    ['put',    'services/(:num)', 'Api\\V1\\Services::update/$1', 'api_auth:admin,provider'],
    ['patch',  'services/(:num)', 'Api\\V1\\Services::update/$1', 'api_auth:admin,provider'],
    ['delete', 'services/(:num)', 'Api\\V1\\Services::delete/$1', 'api_auth:admin,provider'],

    // Categories — full CRUD. Writes are admin/provider.
    ['get',    'categories',        'Api\\V1\\Categories::index'],
    ['post',   'categories',        'Api\\V1\\Categories::create',   'api_auth:admin,provider'],
    ['get',    'categories/(:num)', 'Api\\V1\\Categories::show/$1'],
    ['put',    'categories/(:num)', 'Api\\V1\\Categories::update/$1', 'api_auth:admin,provider'],
    ['patch',  'categories/(:num)', 'Api\\V1\\Categories::update/$1', 'api_auth:admin,provider'],
    ['delete', 'categories/(:num)', 'Api\\V1\\Categories::delete/$1', 'api_auth:admin,provider'],

    // Business hours — global work window. Read any authenticated; write admin.
    ['get',    'business-hours', 'Api\\V1\\BusinessHours::index'],
    ['put',    'business-hours', 'Api\\V1\\BusinessHours::update', 'api_auth:admin'],
    ['post',   'business-hours', 'Api\\V1\\BusinessHours::update', 'api_auth:admin'],

    // Providers — write surface + management show. The public list/read routes
    // (index, /services, /appointments, /schedule) stay in the public group
    // below; these add the authenticated writes. Providers are users, so the
    // 'provider/(:num)/...' sub-routes are more specific and matched first.
    ['post',   'providers',        'Api\\V1\\Providers::create',   'api_auth:admin,provider'],
    ['get',    'providers/(:num)', 'Api\\V1\\Providers::show/$1'],
    ['put',    'providers/(:num)', 'Api\\V1\\Providers::update/$1', 'api_auth:admin,provider'],
    ['patch',  'providers/(:num)', 'Api\\V1\\Providers::update/$1', 'api_auth:admin,provider'],
    ['delete', 'providers/(:num)', 'Api\\V1\\Providers::delete/$1', 'api_auth:admin'],
];

$xsRegisterApiRoutes = static function ($routes) use ($xsApiAuthenticatedRoutes): void {
    foreach ($xsApiAuthenticatedRoutes as $route) {
        [$verb, $path, $handler] = $route;
        $routes->{$verb}($path, $handler, ['filter' => $route[3] ?? 'api_auth']);
    }
};

$routes->group('api', ['filter' => 'setup'], function($routes) use ($xsRegisterApiRoutes) {
    // Unversioned aliases — what the SPA calls today. Not documented externally.
    $xsRegisterApiRoutes($routes);

    // Canonical versioned paths for external clients.
    $routes->group('v1', static function ($routes) use ($xsRegisterApiRoutes) {
        $xsRegisterApiRoutes($routes);
    });

    // Session-only: role switching mutates the session, so it is meaningless
    // for token callers and stays on the session filter.
    $routes->post('auth/switch-role', 'Api\\Auth::switchRole', ['filter' => 'auth']);

    // Note: Legacy GET /api/slots and POST /api/book removed (deprecated since v2.0, sunset 2026-03-01)
    // Use GET /api/availability/slots and POST /api/appointments instead

    // by-hash routes are intentionally unauthenticated — protected by the 64-char customer hash (customer portal)
    $routes->get('customers/by-hash/(:segment)/appointments', 'Api\\CustomerAppointments::byHash/$1');
    $routes->get('customers/by-hash/(:segment)/autofill', 'Api\\CustomerAppointments::autofillByHash/$1');

    // Availability API - Comprehensive slot availability calculation
    $routes->get('availability/slots', 'Api\\Availability::slots');
    $routes->post('availability/check', 'Api\\Availability::check');
    $routes->get('availability/summary', 'Api\\Availability::summary');
    $routes->get('availability/calendar', 'Api\\Availability::calendar');
    $routes->get('availability/next-available', 'Api\\Availability::nextAvailable');

    // Locations API - Provider multi-location support (public reads; the
    // authenticated writes are registered in $xsApiAuthenticatedRoutes above)
    $routes->get('locations', 'Api\\Locations::index');
    $routes->get('locations/for-date', 'Api\\Locations::forDate');
    $routes->get('locations/available-dates', 'Api\\Locations::availableDates');
    $routes->get('locations/(:num)', 'Api\\Locations::show/$1');

    // Public API endpoints (no auth required)
    $routes->group('v1', function($routes) {
        // Settings endpoints - public for frontend initialization
        $routes->get('settings/calendar-config', 'Api\\V1\\Settings::calendarConfig');
        // Note: Use kebab-case (calendar-config) consistently
        $routes->get('settings/localization', 'Api\\V1\\Settings::localization');
        $routes->get('settings/booking', 'Api\\V1\\Settings::booking');
        $routes->get('settings/business-hours', 'Api\\V1\\Settings::businessHours');
        // Provider services - public for booking form
        $routes->get('providers/slug/(:segment)/services', 'Api\\V1\\Providers::servicesBySlug/$1');
        $routes->get('providers/(:num)/services', 'Api\\V1\\Providers::services/$1');
        // Provider appointments - for monthly schedule view
        $routes->get('providers/(:num)/appointments', 'Api\\V1\\Providers::appointments/$1');
        // Availability — canonical versioned path for external clients. Mirrors
        // the unversioned /api/availability/* reads (kept for the SPA).
        $routes->get('availability/slots', 'Api\\Availability::slots');
        $routes->post('availability/check', 'Api\\Availability::check');
        $routes->get('availability/summary', 'Api\\Availability::summary');
        $routes->get('availability/calendar', 'Api\\Availability::calendar');
        $routes->get('availability/next-available', 'Api\\Availability::nextAvailable');
        // Providers public reads on the canonical path (list + weekly schedule).
        // Provider writes/show are in $xsApiAuthenticatedRoutes above.
        $routes->get('providers', 'Api\\V1\\Providers::index');
        $routes->get('providers/(:num)/schedule', 'Api\\V1\\Providers::schedule/$1');
        // Locations RESTful reads on the canonical path (the niche for-date /
        // available-dates lookups stay unversioned). Writes are in the tuple.
        $routes->get('locations', 'Api\\Locations::index');
        $routes->get('locations/(:num)', 'Api\\Locations::show/$1');
    });
    
    // Public providers endpoint (no auth required for calendar)
    $routes->get('providers', 'Api\\V1\\Providers::index');
    // Provider schedule API (auth required for scheduler)
    $routes->get('providers/(:num)/schedule', 'Api\\V1\\Providers::schedule/$1');

    // Versioned API v1 (authenticated) - only non-appointment endpoints
    $routes->group('v1', ['filter' => 'api_auth'], function($routes) {
        // Note: services CRUD is registered in $xsApiAuthenticatedRoutes above.
        // Note: providers route is public (defined above) for calendar access
        $routes->post('providers/(\d+)/profile-image', 'Api\\V1\\Providers::uploadProfileImage/$1');
        // Settings API (authenticated)
        $routes->get('settings', 'Api\\V1\\Settings::index');
        // Some production hosts/WAFs block PUT requests; accept POST as well.
        $routes->match(['put', 'post'], 'settings', 'Api\\V1\\Settings::update');
        $routes->post('settings/logo', 'Api\\V1\\Settings::uploadLogo');
        $routes->post('settings/icon', 'Api\\V1\\Settings::uploadIcon');
        // Integrations hub API
        $routes->get('integrations', 'Api\\V1\\Integrations::index');
        $routes->post('integrations/save', 'Api\\V1\\Integrations::save');
        $routes->post('integrations/test', 'Api\\V1\\Integrations::test');
        $routes->post('integrations/disconnect', 'Api\\V1\\Integrations::disconnect');
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
    $routes->post('settings/integrations', 'Settings::saveIntegrations', ['filter' => 'role:admin']);
    // Google Calendar OAuth flow
    $routes->get('oauth/google/authorize', 'OAuthCallback::googleAuthorize', ['filter' => 'role:admin']);
    $routes->get('oauth/google/callback', 'OAuthCallback::googleCallback', ['filter' => 'role:admin']);
});

// In-app updater — standalone group so the prefix is unambiguous in all CI4 environments
$routes->group('admin/updater', ['filter' => 'setup'], function ($routes) {
    $routes->get('/', 'Admin\Updater::index', ['filter' => 'role:admin']);
    $routes->post('upload', 'Admin\Updater::upload', ['filter' => 'role:admin']);
    $routes->post('execute', 'Admin\Updater::execute', ['filter' => 'role:admin']);
    $routes->post('rollback', 'Admin\Updater::rollback', ['filter' => 'role:admin']);
    $routes->post('maintenance', 'Admin\Updater::toggleMaintenance', ['filter' => 'role:admin']);
});

// Public assets (serve files from public/assets)
$routes->get('assets/s/(:segment)', 'Assets::settings/$1');
// Legacy provider assets from uploads/providers via controller
$routes->get('assets/p/(:segment)', 'Assets::provider/$1');

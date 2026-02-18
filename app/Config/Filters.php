<?php

/**
 * =============================================================================
 * FILTERS CONFIGURATION
 * =============================================================================
 * 
 * @file        app/Config/Filters.php
 * @description Configures HTTP request/response filters (middleware) for the
 *              WebSchedulr application. Filters run before and/or after
 *              controller methods to handle authentication, authorization,
 *              security, and other cross-cutting concerns.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Filters intercept HTTP requests before they reach controllers and can also
 * modify responses before they're sent to clients. This centralizes security
 * and preprocessing logic.
 * 
 * CUSTOM FILTERS:
 * -----------------------------------------------------------------------------
 * - auth              : Verifies user is logged in, redirects to login if not
 * - role              : Checks user has required role(s) e.g., 'role:admin,provider'
 * - setup             : Ensures initial setup wizard is completed
 * - setup_auth        : Combined setup + auth check for protected routes
 * - api_auth          : JWT/session auth for API endpoints
 * - api_cors          : CORS headers for API cross-origin requests
 * - timezone          : Detects and stores client timezone in session
 * - public_rate_limit : Rate limiting for public booking endpoints
 * 
 * BUILT-IN FILTERS:
 * -----------------------------------------------------------------------------
 * - csrf              : Cross-Site Request Forgery protection
 * - cors              : Cross-Origin Resource Sharing headers
 * - secureheaders     : Security headers (X-Frame-Options, etc.)
 * - toolbar           : Debug toolbar (dev environment only)
 * 
 * USAGE IN ROUTES:
 * -----------------------------------------------------------------------------
 * $routes->get('admin', 'Admin::index', ['filter' => 'role:admin']);
 * $routes->group('api', ['filter' => 'api_auth'], function($routes) {...});
 * 
 * @see         app/Filters/ for filter implementations
 * @see         app/Config/Routes.php for filter application to routes
 * @package     Config
 * @extends     CodeIgniter\Config\Filters
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'securityheaders' => \App\Filters\SecurityHeaders::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
    'auth'          => \App\Filters\AuthFilter::class,
    'role'          => \App\Filters\RoleFilter::class,
    'api_cors'      => \App\Filters\CorsFilter::class,
    'api_auth'      => \App\Filters\ApiAuthFilter::class,
        'setup'         => \App\Filters\SetupFilter::class,
        'setup_auth'    => \App\Filters\SetupAuthFilter::class,
        'timezone'      => \App\Filters\TimezoneDetection::class,
        'public_rate_limit' => \App\Filters\PublicBookingRateLimiter::class,
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            // In production you may enable 'forcehttps' and 'pagecache'
        ],
        'after' => [
            // In production you may enable 'pagecache' and 'performance'
            'toolbar', // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array<string, array<string, array<string, string>>>|array<string, list<string>>
     */
    public array $globals = [
        'before' => [
            'securityheaders',
            'timezone' => ['except' => ['setup', 'setup/*']],
            // 'honeypot',
            'csrf' => ['except' => ['api/*']],
            // 'invalidchars',
        ],
        'after' => [
            // 'honeypot',
            // 'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}

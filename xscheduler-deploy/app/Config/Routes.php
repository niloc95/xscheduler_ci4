<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('setup', 'Setup::setup');
$routes->get('tw', 'Tw::tw');

// Dashboard Routes
$routes->get('dashboard', 'Dashboard::index');
$routes->get('dashboard/simple', 'Dashboard::simple');
$routes->get('dashboard/test', 'Dashboard::test');
$routes->get('dashboard/api', 'Dashboard::api');
$routes->get('dashboard/charts', 'Dashboard::charts');

// Style Guide Routes
$routes->get('styleguide', 'Styleguide::index');
$routes->get('styleguide/components', 'Styleguide::components');
$routes->get('styleguide/scheduler', 'Styleguide::scheduler');

<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('/setup', 'Setup::setup');
$routes->get('/tw', 'Tw::tw');

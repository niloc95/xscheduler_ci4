<?php

/**
 * =============================================================================
 * BASE CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/BaseController.php
 * @description Abstract base class that all WebSchedulr controllers extend.
 *              Provides shared functionality, helper loading, and common
 *              properties accessible to all child controllers.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Centralizes common controller functionality to avoid code duplication:
 * - Auto-loads frequently used helpers
 * - Initializes request/response objects
 * - Provides logger access
 * - Can define shared methods for authentication state, user data, etc.
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 * All application controllers should extend this class:
 * 
 *     class Dashboard extends BaseController
 *     {
 *         public function index()
 *         {
 *             // $this->request, $this->response, $this->logger available
 *         }
 *     }
 * 
 * SHARED HELPERS:
 * -----------------------------------------------------------------------------
 * Helpers listed in $helpers array are auto-loaded for all controllers:
 * - 'url'      : URL generation functions
 * - 'form'     : Form helper functions
 * - 'html'     : HTML helper functions
 * - 'currency' : Currency formatting (custom)
 * 
 * SECURITY NOTE:
 * -----------------------------------------------------------------------------
 * Declare any new methods as protected or private to prevent direct URL access.
 * 
 * @see         app/Controllers/ for controller implementations
 * @package     App\Controllers
 * @extends     CodeIgniter\Controller
 * @abstract
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load your UI helper
        helper('ui');
        
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = service('session');
    }
}

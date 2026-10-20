<?php

/**
 * =============================================================================
 * INITIAL SETUP WIZARD CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Setup.php
 * @description First-run setup wizard for initial application configuration
 *              including database, admin user, and business settings.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /setup                        : Display setup wizard
 * POST /setup/process                : Process setup form submission
 * POST /setup/test-database          : Test database connection
 * POST /setup/create-database        : Create database tables
 * POST /setup/create-admin           : Create admin user account
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * One-time installation wizard that guides through:
 * - Database connection configuration
 * - Database table creation (migrations)
 * - Admin account creation
 * - Basic business settings (name, timezone, currency)
 * - .env file generation
 * 
 * SETUP FLOW:
 * -----------------------------------------------------------------------------
 * 1. Check system requirements (PHP version, extensions)
 * 2. Configure database connection
 * 3. Run migrations to create tables
 * 4. Create first admin user
 * 5. Set basic business settings
 * 6. Write setup_complete.flag to prevent re-running
 * 
 * SECURITY:
 * -----------------------------------------------------------------------------
 * - Protected by SetupFilter (blocks access after completion)
 * - Validates all inputs server-side
 * - Generates secure admin password
 * - Creates .env with secure settings
 * 
 * FILE MARKERS:
 * -----------------------------------------------------------------------------
 * - writable/setup_complete.flag : Indicates setup is done
 * - .env : Environment configuration file
 * 
 * @see         app/Views/setup.php for wizard template
 * @see         app/Database/Migrations/ for table schemas
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Services\SetupInstallerService;
use CodeIgniter\HTTP\ResponseInterface;

class Setup extends BaseController
{
    protected SetupInstallerService $installer;

    public function __construct()
    {
        helper(['form', 'url', 'setup', 'setupcompatibility']);
        $this->installer = new SetupInstallerService();
    }

    public function index()
    {
        // Check if setup is already completed using global helper
        if (is_setup_completed()) {
            return redirect()->to(base_url('auth/login'))->with('info', 'Setup has already been completed. Please log in.');
        }

        return view('setup');
    }

    public function setup(): string
    {
        return $this->index();
    }

    public function process(): ResponseInterface
    {
        // Start output buffering to avoid stray output breaking redirects/headers
        if (function_exists('ob_start')) {
            @ob_start();
        }
        // Check if setup is already completed using global helper
        if (is_setup_completed()) {
            $this->cleanOutputBuffer();
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Setup has already been completed.'
            ])->setStatusCode(400);
        }

        // CSRF protection
        if (!$this->validate(['csrf_test_name' => 'required'])) {
            $this->cleanOutputBuffer();
            return $this->response->setJSON([
                'success' => false,
                'message' => 'CSRF token validation failed.',
                'errors' => ['csrf' => ['CSRF token is required']]
            ])->setStatusCode(400);
        }

        // Validation rules
        $rules = [
            'admin_name' => 'required|min_length[2]|max_length[50]',
            'admin_email' => 'required|valid_email|max_length[100]',
            'admin_userid' => 'required|min_length[3]|max_length[20]|alpha_numeric',
            'admin_password' => 'required|min_length[8]',
            'admin_password_confirm' => 'required|matches[admin_password]',
            'database_type' => 'required|in_list[mysql]',
        ];

        // Additional MySQL validation
        $rules = array_merge($rules, [
            'mysql_hostname' => 'required',
            'mysql_port' => 'required|numeric',
            'mysql_database' => 'required',
            'mysql_username' => 'required',
            'mysql_password' => 'permit_empty'
        ]);

        if (!$this->validate($rules)) {
            $this->cleanOutputBuffer();
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        // Determine AJAX vs non-AJAX once (used in both success and error paths)
        $isAjax = $this->request->isAJAX() || 
                  stripos($this->request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest') !== false ||
                  stripos($this->request->getHeaderLine('Accept'), 'application/json') !== false;

        try {
            // Process setup with proper database configuration for .env generation
            $setupData = [
                'admin' => [
                    'name' => $this->request->getPost('admin_name'),
                    'email' => $this->request->getPost('admin_email'),
                    'userid' => $this->request->getPost('admin_userid'),
                    'password' => $this->request->getPost('admin_password') // Store raw password for finalizeSetup
                ],
                'database' => [
                    'type' => $this->request->getPost('database_type')
                ]
            ];

            // Prepare database configuration for .env generation
            $dbConfig = [];
            
            $dbConfig = [
                'db_driver' => 'MySQLi',
                'db_hostname' => $this->request->getPost('mysql_hostname'),
            // Ensure port is an integer to satisfy mysqli strict types
                'db_port' => (int) ($this->request->getPost('mysql_port') ?: 3306),
                'db_database' => $this->request->getPost('mysql_database'),
                'db_username' => $this->request->getPost('mysql_username'),
                'db_password' => $this->request->getPost('mysql_password'),
                'compatibility_mode' => filter_var(
                    $this->request->getPost('compatibility_mode'),
                    FILTER_VALIDATE_BOOLEAN,
                ),
            ];

            $setupData['database']['mysql'] = [
                'hostname' => $dbConfig['db_hostname'],
                'port' => (int) $dbConfig['db_port'],
                'database' => $dbConfig['db_database'],
                'username' => $dbConfig['db_username'],
                'password' => $dbConfig['db_password']
            ];

            $connectionTest = $this->installer->testDatabaseConnection($dbConfig);
            if (!$connectionTest['success']) {
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $connectionTest['message']
                ])->setStatusCode(400);
            }

            // Apply runtime DB config so this request (migrations) uses the correct driver
            log_message('info', 'Setup: Applying runtime DB config: ' . json_encode(array_merge($dbConfig, ['db_password' => '[HIDDEN]'])));
            $this->installer->debugDatabaseConfig('before-runtime-config');
            $this->installer->applyRuntimeDatabaseConfig($dbConfig);
            $this->installer->debugDatabaseConfig('after-runtime-config');

            // Validate DB connection works before proceeding with migrations
            // Use the same connection test logic as testConnection for consistency
            try {
                log_message('info', 'Setup: Testing database connection with provided config');
                $connectionTest = $this->installer->testDatabaseConnection($dbConfig);
                
                if (!$connectionTest['success']) {
                    throw new \Exception($connectionTest['message']);
                }
                
                log_message('info', 'Setup: Database connection validated successfully via direct test');
                
                // Also confirm CodeIgniter's own connection picked up the runtime config.
                $this->installer->probeFrameworkConnection();
                
            } catch (\Throwable $e) {
                log_message('error', 'Setup: Database connection validation failed: ' . $e->getMessage());
                $this->installer->debugDatabaseConfig('validation-failed');
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Database connection failed: ' . $e->getMessage()
                ])->setStatusCode(400);
            }

            // Generate .env file first - this is critical for the application to work
            log_message('info', 'Setup: Generating .env with DB config: ' . json_encode(array_merge($dbConfig, ['db_password' => '[HIDDEN]'])));
            if (!$this->installer->generateEnvFile($dbConfig)) {
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to generate environment configuration file' . (!empty($this->installer->lastEnvError()) ? (': ' . $this->installer->lastEnvError()) : '.')
                ])->setStatusCode(500);
            }

            // Finalize setup: run migrations, create admin user, mark completed
            $finalizeResult = $this->installer->finalizeSetup($setupData);
            
            if (!$finalizeResult['success']) {
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $finalizeResult['message']
                ])->setStatusCode(500);
            }

            // If this was an AJAX request, return JSON so client JS can redirect.
            // Otherwise, fall back to a normal redirect so the browser navigates automatically.

            if ($isAjax) {
                $this->cleanOutputBuffer();
                return $this->response
                    ->setStatusCode(200)
                    ->setHeader('Content-Type', 'application/json')
                    ->setJSON([
                        'success' => true,
                        'message' => 'Setup completed successfully! Please log in with your admin account.',
                        'redirect' => base_url('auth/login')
                    ]);
            }

            // Non-AJAX fallback
            session()->setFlashdata('success', 'Setup completed successfully! Please log in with your admin account.');
            $this->cleanOutputBuffer();
            return redirect()->to(base_url('auth/login'));

        } catch (\Exception $e) {
            log_message('error', 'Setup process failed: ' . $e->getMessage());
            // On error, honor AJAX vs non-AJAX as well

            if ($isAjax) {
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Setup failed: ' . $e->getMessage()
                ])->setStatusCode(500);
            }

            session()->setFlashdata('error', 'Setup failed: ' . $e->getMessage());
            $this->cleanOutputBuffer();
            return redirect()->back();
        }
    }

    /**
     * Test database connection with provided credentials
     */
    public function testConnection(): ResponseInterface
    {
        if (strpos($this->request->getHeaderLine('Content-Type'), 'application/json') === false) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['success' => false, 'message' => 'Invalid request.']);
        }

        $body = $this->request->getJSON(true) ?? [];

        $host = trim((string) ($body['host'] ?? ''));
        $db = trim((string) ($body['db'] ?? ''));
        $user = trim((string) ($body['user'] ?? ''));
        $pass = (string) ($body['pass'] ?? '');
        $port = (int) ($body['port'] ?? 3306);
        $compatMode = filter_var($body['compatibility_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($host === '' || $db === '' || $user === '') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success'       => false,
                    'message'       => 'Hostname, database name, and username are required.',
                    'error'         => null,
                    'suggestCompat' => false,
                    'compatMode'    => $compatMode,
                ]);
        }

        if ($port < 1 || $port > 65535) {
            $port = 3306;
        }

        $result = \testDatabaseConnection(
            host:       $host,
            db:         $db,
            user:       $user,
            pass:       $pass,
            port:       $port,
            compatMode: $compatMode,
        );

        if (! $result['success'] && ! empty($result['error'])) {
            log_message('warning', '[Setup::testConnection] DB probe failed: ' . $result['error']);
        }

        unset($result['error']);

        return $this->response
            ->setStatusCode($result['success'] ? 200 : 422)
            ->setJSON($result);
    }

    /**
     * Clean any active output buffers to avoid stray output blocking redirects/headers
     */
    private function cleanOutputBuffer(): void
    {
        if (!function_exists('ob_get_level')) {
            return;
        }
        try {
            while (@ob_get_level() > 0) {
                @ob_end_clean();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

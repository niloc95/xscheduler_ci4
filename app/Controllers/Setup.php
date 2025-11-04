<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Database as DatabaseConfig;
use Exception;

class Setup extends BaseController
{
    /**
     * Holds the last error encountered during .env generation for surfacing to client
     */
    protected ?string $envError = null;

    public function __construct()
    {
        helper(['form', 'url']);
    }

    public function index()
    {
        // Check if setup is already completed
        if ($this->isSetupCompleted()) {
            return redirect()->to('/auth/login')->with('info', 'Setup has already been completed. Please log in.');
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
        // Check if setup is already completed
        if ($this->isSetupCompleted()) {
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
            'database_type' => 'required|in_list[mysql,sqlite]',
        ];

        // Additional MySQL validation
        if ($this->request->getPost('database_type') === 'mysql') {
            $rules = array_merge($rules, [
                'mysql_hostname' => 'required',
                'mysql_port' => 'required|numeric',
                'mysql_database' => 'required',
                'mysql_username' => 'required',
                'mysql_password' => 'permit_empty'
            ]);
        }

        if (!$this->validate($rules)) {
            $this->cleanOutputBuffer();
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

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
            
        if ($setupData['database']['type'] === 'mysql') {
                $dbConfig = [
                    'db_driver' => 'MySQLi',
                    'db_hostname' => $this->request->getPost('mysql_hostname'),
            // Ensure port is an integer to satisfy mysqli strict types
            'db_port' => (int) ($this->request->getPost('mysql_port') ?: 3306),
                    'db_database' => $this->request->getPost('mysql_database'),
                    'db_username' => $this->request->getPost('mysql_username'),
                    'db_password' => $this->request->getPost('mysql_password')
                ];

                $setupData['database']['mysql'] = [
                    'hostname' => $dbConfig['db_hostname'],
                    'port' => (int) $dbConfig['db_port'],
                    'database' => $dbConfig['db_database'],
                    'username' => $dbConfig['db_username'],
                    'password' => $dbConfig['db_password']
                ];

                // Test MySQL connection before proceeding
                $connectionTest = $this->testDatabaseConnection($dbConfig);
                if (!$connectionTest['success']) {
                    $this->cleanOutputBuffer();
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => $connectionTest['message']
                    ])->setStatusCode(400);
                }
            } else {
                // SQLite configuration - use full path
                $dbConfig = [
                    'db_driver' => 'SQLite3',
                    'db_hostname' => '',
                    'db_port' => '',
                    'db_database' => WRITEPATH . 'database/webschedulr.db',
                    'db_username' => '',
                    'db_password' => ''
                ];

                $setupData['database']['sqlite'] = [
                    'path' => WRITEPATH . 'database/webschedulr.db'
                ];
                
                // Test SQLite setup
                $connectionTest = $this->testDatabaseConnection($dbConfig);
                if (!$connectionTest['success']) {
                    $this->cleanOutputBuffer();
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => $connectionTest['message']
                    ])->setStatusCode(400);
                }
            }

            // Apply runtime DB config so this request (migrations) uses the correct driver
            log_message('info', 'Setup: Applying runtime DB config: ' . json_encode(array_merge($dbConfig, ['db_password' => '[HIDDEN]'])));
            $this->debugDatabaseConfig('before-runtime-config');
            $this->applyRuntimeDatabaseConfig($dbConfig);
            $this->debugDatabaseConfig('after-runtime-config');

            // Validate DB connection works before proceeding with migrations
            // Use the same connection test logic as testConnection for consistency
            try {
                log_message('info', 'Setup: Testing database connection with provided config');
                $connectionTest = $this->testDatabaseConnection($dbConfig);
                
                if (!$connectionTest['success']) {
                    throw new \Exception($connectionTest['message']);
                }
                
                log_message('info', 'Setup: Database connection validated successfully via direct test');
                
                // Also test CodeIgniter's connection to ensure runtime config worked
                // Note: initialize() can return false even when connection works, so we test with a query
                $testConnection = \Config\Database::connect();
                if (!$testConnection) {
                    log_message('warning', 'Setup: CodeIgniter DB connection object failed to create');
                } else {
                    // Test a simple query to validate the connection works
                    try {
                        $result = $testConnection->query('SELECT 1 as test');
                        if ($result && $result->getRow()) {
                            log_message('info', 'Setup: CodeIgniter database connection validated successfully');
                        } else {
                            log_message('warning', 'Setup: CodeIgniter DB query test failed (no result), but direct test passed - proceeding');
                        }
                    } catch (\Throwable $queryError) {
                        log_message('warning', 'Setup: CodeIgniter DB query test failed: ' . $queryError->getMessage() . ' - but direct test passed, proceeding');
                    }
                }
                
            } catch (\Throwable $e) {
                log_message('error', 'Setup: Database connection validation failed: ' . $e->getMessage());
                $this->debugDatabaseConfig('validation-failed');
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Database connection failed: ' . $e->getMessage()
                ])->setStatusCode(400);
            }

            // Generate .env file first - this is critical for the application to work
            log_message('info', 'Setup: Generating .env with DB config: ' . json_encode(array_merge($dbConfig, ['db_password' => '[HIDDEN]'])));
            if (!$this->generateEnvFile($dbConfig)) {
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to generate environment configuration file' . (!empty($this->envError) ? (': ' . $this->envError) : '.')
                ])->setStatusCode(500);
            }

            // Finalize setup: run migrations, create admin user, mark completed
            $finalizeResult = $this->finalizeSetup($setupData);
            
            if (!$finalizeResult['success']) {
                $this->cleanOutputBuffer();
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $finalizeResult['message']
                ])->setStatusCode(500);
            }

            // If this was an AJAX request, return JSON so client JS can redirect.
            // Otherwise, fall back to a normal redirect so the browser navigates automatically.
            $isAjax = $this->request->isAJAX() || 
                      stripos($this->request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest') !== false ||
                      stripos($this->request->getHeaderLine('Accept'), 'application/json') !== false;

            if ($isAjax) {
                $this->cleanOutputBuffer();
                return $this->response
                    ->setStatusCode(200)
                    ->setHeader('Content-Type', 'application/json')
                    ->setJSON([
                        'success' => true,
                        'message' => 'Setup completed successfully! Please log in with your admin account.',
                        'redirect' => '/auth/login'
                    ]);
            }

            // Non-AJAX fallback
            session()->setFlashdata('success', 'Setup completed successfully! Please log in with your admin account.');
            $this->cleanOutputBuffer();
            return redirect()->to('/auth/login');

        } catch (\Exception $e) {
            log_message('error', 'Setup process failed: ' . $e->getMessage());
            // On error, honor AJAX vs non-AJAX as well
            $isAjax = $this->request->isAJAX() || 
                      stripos($this->request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest') !== false ||
                      stripos($this->request->getHeaderLine('Accept'), 'application/json') !== false;

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
     * Finalize setup process:
     * - Run database migrations
     * - Seed initial data (if exists)
     * - Create admin user
     * - Write setup completion flag
     */
    protected function finalizeSetup(array $setupData): array
    {
        try {
            // Step 1: Run all migrations
            $migrationsResult = $this->runMigrations();
            if (!$migrationsResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Migration failed: ' . $migrationsResult['message']
                ];
            }

            // Step 1.5: Verify required tables exist before proceeding
            $verify = $this->verifyRequiredTables();
            if (!$verify['success']) {
                $missingList = implode(', ', $verify['missing']);
                log_message('error', 'Setup: Required tables missing after migrations: ' . $missingList);
                return [
                    'success' => false,
                    'message' => 'Required tables missing after migrations: ' . $missingList
                ];
            }

            // Step 2: Run seeders (optional)
            $seedsResult = $this->runSeeders();
            if (!$seedsResult['success']) {
                log_message('warning', 'Seeder failed: ' . $seedsResult['message']);
                // Don't fail setup if seeders fail, just log it
            }

            // Step 3: Create admin user
            $adminResult = $this->createAdminUser($setupData['admin']);
            if (!$adminResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to create admin user: ' . $adminResult['message']
                ];
            }

            // Step 4: Write setup completion flag
            $flagResult = $this->writeSetupCompletedFlag($setupData);
            if (!$flagResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to write setup completion flag: ' . $flagResult['message']
                ];
            }

            return [
                'success' => true,
                'message' => 'Setup finalized successfully'
            ];

        } catch (\Exception $e) {
            log_message('error', 'Setup finalization failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Setup finalization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify required tables exist after migrations; returns missing list if any
     */
    protected function verifyRequiredTables(): array
    {
        $required = ['users', 'services', 'appointments', 'settings'];
        $missing = [];
        try {
            $db = \Config\Database::connect();
            foreach ($required as $t) {
                if (! $db->tableExists($t)) {
                    $missing[] = $db->prefixTable($t);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Setup: verifyRequiredTables failed: ' . $e->getMessage());
            // If we cannot verify, consider as missing core tables
            $missing = $required;
        }

        return [
            'success' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Run database migrations
     */
    protected function runMigrations(): array
    {
        try {
            // Log current DB connection details for diagnostics
            try {
                $db = \Config\Database::connect();
                $driver = property_exists($db, 'DBDriver') ? $db->DBDriver : 'unknown';
                $dbname = property_exists($db, 'database') ? $db->database : '';
                log_message('info', 'Setup: Running migrations with DB driver=' . $driver . ' database=' . $dbname);
            } catch (\Throwable $e) {
                log_message('warning', 'Setup: Could not log DB connection details: ' . $e->getMessage());
            }

            $migrate = \Config\Services::migrations();
            
            // Get all migrations
            $migrations = $migrate->findMigrations();
            
            if (empty($migrations)) {
                return [
                    'success' => true,
                    'message' => 'No migrations found to run'
                ];
            }

            // Run migrations to latest
            $result = $migrate->latest();
            
            if ($result === false) {
                $error = $migrate->getCliMessages();
                return [
                    'success' => false,
                    'message' => 'Migration failed: ' . implode(', ', $error)
                ];
            }

            log_message('info', 'Migrations completed successfully');
            return [
                'success' => true,
                'message' => 'Migrations completed successfully'
            ];

        } catch (\Exception $e) {
            log_message('error', 'Migration error: ' . $e->getMessage());
            log_message('error', 'Migration stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Database migration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Run database seeders (optional)
     */
    protected function runSeeders(): array
    {
        try {
            $seeder = \Config\Database::seeder();
            
            // Check if default seeder exists
            $seederClass = 'App\Database\Seeds\MainSeeder';
            
            if (!class_exists($seederClass)) {
                // Try alternative seeder names
                $alternativeClasses = [
                    'App\Database\Seeds\DatabaseSeeder',
                    'App\Database\Seeds\InitialSeeder',
                    'App\Database\Seeds\DefaultSeeder'
                ];
                
                $seederClass = null;
                foreach ($alternativeClasses as $class) {
                    if (class_exists($class)) {
                        $seederClass = $class;
                        break;
                    }
                }
                
                if (!$seederClass) {
                    return [
                        'success' => true,
                        'message' => 'No seeders found to run'
                    ];
                }
            }

            // Run the seeder
            $seeder->call($seederClass);
            
            log_message('info', 'Database seeding completed');
            return [
                'success' => true,
                'message' => 'Database seeding completed'
            ];

        } catch (\Exception $e) {
            log_message('warning', 'Seeder error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create the admin user in the users table
     */
    protected function createAdminUser(array $adminData): array
    {
        try {
            $db = \Config\Database::connect();
            
            // Check if users table exists
            if (!$db->tableExists('users')) {
                return [
                    'success' => false,
                    'message' => 'Users table does not exist. Please ensure migrations have run properly.'
                ];
            }

            // Check if admin user already exists
            $existingUser = $db->table('users')
                              ->where('email', $adminData['email'])
                              ->orWhere('email', $adminData['userid'] . '@admin.local')
                              ->get()
                              ->getRow();

            if ($existingUser) {
                log_message('info', 'Admin user already exists, skipping creation');
                return [
                    'success' => true,
                    'message' => 'Admin user already exists'
                ];
            }

            // Prepare admin user data
            $userData = [
                'name' => $adminData['name'],
                'email' => $adminData['email'], // Use the actual email provided
                'phone' => null,
                'password_hash' => password_hash($adminData['password'], PASSWORD_DEFAULT),
                'role' => 'admin',
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Insert admin user
            $result = $db->table('users')->insert($userData);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Failed to insert admin user into database'
                ];
            }

            log_message('info', 'Admin user created successfully: ' . $userData['email']);
            return [
                'success' => true,
                'message' => 'Admin user created successfully'
            ];

        } catch (\Exception $e) {
            log_message('error', 'Admin user creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Write setup completion flag file
     */
    protected function writeSetupCompletedFlag(array $setupData): array
    {
        // Do not create flag files in production environments
        if (ENVIRONMENT === 'production') {
            log_message('info', 'Setup: Skipping flag file creation in production.');
            return [ 'success' => true, 'message' => 'Skipped flag write in production' ];
        }

        // Prefer the provided admin email; if missing, derive from userid; else use default
        $adminEmail = null;
        if (!empty($setupData['admin']['email']) && filter_var($setupData['admin']['email'], FILTER_VALIDATE_EMAIL)) {
            $adminEmail = $setupData['admin']['email'];
        } elseif (!empty($setupData['admin']['userid'])) {
            $userId = (string) $setupData['admin']['userid'];
            $adminEmail = filter_var($userId, FILTER_VALIDATE_EMAIL) ? $userId : ($userId . '@admin.local');
        } else {
            $adminEmail = 'admin@setup.local';
        }

        $flagData = [
            'completed_at' => date('Y-m-d H:i:s'),
            'admin_email' => $adminEmail,
            'database_type' => $setupData['database']['type'] ?? 'unknown',
            'version' => '1.0.0'
        ];

    // Support both legacy and new flag filenames
    $flagPathLegacy = WRITEPATH . 'setup_completed.flag';
    $flagPathNew    = WRITEPATH . 'setup_complete.flag';
    $flagDir = WRITEPATH; // both flags live directly in writable/

        // Ensure directory exists
        if (!is_dir($flagDir)) {
            if (!@mkdir($flagDir, 0755, true) && !is_dir($flagDir)) {
                $msg = 'Unable to create writable directory: ' . $flagDir;
                log_message('error', 'Setup: ' . $msg);
                return [ 'success' => false, 'message' => $msg ];
            }
        }

        // Ensure directory writable
        if (!is_writable($flagDir)) {
            // Attempt to set permissions
            @chmod($flagDir, 0775);
            if (!is_writable($flagDir)) {
                $msg = 'Directory not writable for setup flag: ' . $flagDir;
                log_message('error', 'Setup: ' . $msg);
                return [ 'success' => false, 'message' => $msg ];
            }
        }

        // Attempt to write BOTH flags for compatibility; the new flag is authoritative
        $payload = json_encode($flagData, JSON_PRETTY_PRINT);
        $writeNew = @file_put_contents($flagPathNew, $payload);
        $writeLegacy = @file_put_contents($flagPathLegacy, $payload);

        if ($writeNew === false) {
            $msg = 'Failed to write setup flag at path: ' . $flagPathNew;
            log_message('error', 'Setup: ' . $msg);
            return [ 'success' => false, 'message' => $msg ];
        }

        // Best-effort set perms
        @chmod($flagPathNew, 0644);
        if ($writeLegacy !== false) {
            @chmod($flagPathLegacy, 0644);
        } else {
            log_message('warning', 'Setup: Could not write legacy setup flag at: ' . $flagPathLegacy . ' (continuing)');
        }

        log_message('info', 'Setup completion flags written: new=' . $flagPathNew . '; legacy=' . $flagPathLegacy);
        return [ 'success' => true, 'message' => 'Flag(s) written' ];
    }

    /**
     * Generate .env file from template and user inputs
     */
    protected function generateEnvFile(array $data): bool
    {
        $envExamplePath = ROOTPATH . '.env.example';
        $envPath = ROOTPATH . '.env';

        // Verify we can write the .env file location before proceeding
        $envDir = dirname($envPath);
        if (!is_dir($envDir) || !is_writable($envDir)) {
            $this->envError = 'Directory is not writable: ' . $envDir;
            log_message('error', 'Setup: .env directory not writable: ' . $envDir);
            return false;
        }

        // Check if .env already exists
        if (file_exists($envPath)) {
            log_message('warning', 'Setup: .env file already exists, backing up as .env.backup');
            if (!copy($envPath, $envPath . '.backup')) {
                log_message('error', 'Setup: Failed to backup existing .env file');
            }
        }

        try {
            // Read the template; if missing, create a minimal default template for production
            if (file_exists($envExamplePath)) {
                $envTemplate = file_get_contents($envExamplePath);
                if ($envTemplate === false) {
                    $this->envError = 'Failed to read .env.example template';
                    log_message('error', 'Setup: Failed to read .env.example template');
                    return false;
                }
            } else {
                log_message('warning', 'Setup: .env.example not found; using minimal fallback template');
                $envTemplate = "CI_ENVIRONMENT = production\n" .
                               "app.baseURL = ''\n" .
                               "app.forceGlobalSecureRequests = true\n" .
                               "app.CSPEnabled = true\n" .
                               "database.default.DBDriver = MySQLi\n" .
                               "database.default.hostname = localhost\n" .
                               "database.default.database = \n" .
                               "database.default.username = \n" .
                               "database.default.password = \n" .
                               "database.default.port = 3306\n" .
                               "encryption.key = \n";
            }

            // Replace template variables with user inputs
            $envContent = $this->populateEnvTemplate($envTemplate, $data);

            // Write the new .env file
            $writeResult = file_put_contents($envPath, $envContent);
            if ($writeResult === false) {
                $this->envError = 'Failed to write .env to path: ' . $envPath;
                log_message('error', 'Setup: Failed to write .env file to: ' . $envPath);
                return false;
            }

            // Set proper permissions
            if (!chmod($envPath, 0644)) {
                log_message('warning', 'Setup: Failed to set .env file permissions');
            }

            $this->envError = null; // clear previous error on success
            log_message('info', 'Setup: .env file generated successfully at: ' . $envPath);
            return true;

        } catch (Exception $e) {
            $this->envError = 'Exception during .env generation: ' . $e->getMessage();
            log_message('error', 'Setup: Error generating .env file - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Populate .env template with user configuration
     */
    protected function populateEnvTemplate(string $template, array $data): string
    {
        log_message('info', 'Setup: Populating .env template with data: ' . json_encode(array_merge($data, ['db_password' => '[HIDDEN]'])));
        
        // Determine environment mode
        $environment = ENVIRONMENT === 'development' ? 'development' : 'production';

        // Smart baseURL detection for production environments
        $baseURL = $environment === 'development' ? 'http://localhost:8080/' : $this->detectProductionURL();

        $envContent = $template;

        // Helper regex replacer that updates a key line or appends if missing
        $replaceKey = function (string $content, string $key, string $value) {
            $pattern = '/^' . preg_quote($key, '/') . '\\s*=.*$/m';
            $replacement = $key . ' = ' . $value;
            if (preg_match($pattern, $content)) {
                return preg_replace($pattern, $replacement, $content);
            }
            // Append if not found
            return rtrim($content, "\r\n") . "\n" . $replacement . "\n";
        };

        // Environment/app settings
        $envContent = $replaceKey($envContent, 'CI_ENVIRONMENT', $environment);
        $envContent = $replaceKey($envContent, 'app.baseURL', "'{$baseURL}'");
        $envContent = $replaceKey($envContent, 'app.forceGlobalSecureRequests', $environment === 'production' ? 'true' : 'false');
        $envContent = $replaceKey($envContent, 'app.CSPEnabled', $environment === 'production' ? 'true' : 'false');

        // Database settings
        $envContent = $replaceKey($envContent, 'database.default.DBDriver', $data['db_driver'] ?? 'MySQLi');
        $envContent = $replaceKey($envContent, 'database.default.hostname', $data['db_hostname'] ?? 'localhost');
        $envContent = $replaceKey($envContent, 'database.default.database', $data['db_database'] ?? '');
        $envContent = $replaceKey($envContent, 'database.default.username', $data['db_username'] ?? '');
        $envContent = $replaceKey($envContent, 'database.default.password', $data['db_password'] ?? '');
    // Ensure port written as numeric value in .env
    $portValue = isset($data['db_port']) ? (int) $data['db_port'] : 3306;
    $envContent = $replaceKey($envContent, 'database.default.port', (string) $portValue);

        // Encryption key
        $envContent = $replaceKey($envContent, 'encryption.key', $this->generateEncryptionKey());

        // Setup flags
        $envContent = $replaceKey($envContent, 'setup.enabled', 'false');
        $envContent = $replaceKey($envContent, 'setup.allowMultipleRuns', 'false');

        // Add setup completion timestamp
        $envContent .= "\n# Setup completed on " . date('Y-m-d H:i:s') . "\n";

        return $envContent;
    }

    /**
     * Apply runtime DB configuration for the current request so migrations use selected DB
     */
    protected function applyRuntimeDatabaseConfig(array $data): void
    {
        try {
            /** @var DatabaseConfig $dbConfig */
            $dbConfig = config('Database');
            if (!is_object($dbConfig)) {
                log_message('warning', 'Setup: Database config is not an object');
                return;
            }

            log_message('info', 'Setup: Updating runtime database configuration');

            // Update default group
            if (property_exists($dbConfig, 'default') && is_array($dbConfig->default)) {
                $dbConfig->default['DBDriver'] = $data['db_driver'] ?? $dbConfig->default['DBDriver'] ?? 'MySQLi';
                $dbConfig->default['hostname'] = $data['db_hostname'] ?? $dbConfig->default['hostname'] ?? 'localhost';
                $dbConfig->default['database'] = $data['db_database'] ?? $dbConfig->default['database'] ?? '';
                $dbConfig->default['username'] = $data['db_username'] ?? $dbConfig->default['username'] ?? '';
                $dbConfig->default['password'] = $data['db_password'] ?? $dbConfig->default['password'] ?? '';
                // Port only relevant for MySQL - ensure it's an integer
                if (!empty($data['db_port'])) {
                    $dbConfig->default['port'] = (int) $data['db_port'];
                }
                
                log_message('info', 'Setup: Runtime DB config updated with: ' . json_encode([
                    'DBDriver' => $dbConfig->default['DBDriver'],
                    'hostname' => $dbConfig->default['hostname'],
                    'database' => $dbConfig->default['database'],
                    'username' => $dbConfig->default['username'],
                    'password' => '[HIDDEN]',
                    'port' => $dbConfig->default['port'] ?? 'default'
                ]));
            }

            // Force the framework to drop any previously shared connection and
            // establish a fresh one using the updated configuration. This prevents
            // the "first attempt fails, second works" issue due to a stale default
            // connection created earlier in the request lifecycle.
            
            // Clear all cached connections more aggressively
            try {
                // Get all connection group names
                $groups = ['default']; // Add other groups if you have them
                
                foreach ($groups as $group) {
                    try {
                        // Close existing connection if it exists
                        $existing = \Config\Database::connect($group, false);
                        if ($existing && method_exists($existing, 'close')) {
                            $existing->close();
                            log_message('info', "Setup: Closed existing {$group} database connection");
                        }
                    } catch (\Throwable $e) {
                        log_message('info', "Setup: No existing {$group} connection to close");
                    }
                }
                
                // Clear the shared connection cache by resetting the static property
                // This is a workaround for CodeIgniter's connection caching
                $reflection = new \ReflectionClass(\Config\Database::class);
                if ($reflection->hasProperty('instances')) {
                    $instancesProperty = $reflection->getProperty('instances');
                    $instancesProperty->setAccessible(true);
                    $instancesProperty->setValue(null, []);
                    log_message('info', 'Setup: Cleared Database connection cache');
                }
                
            } catch (\Throwable $e) {
                log_message('warning', 'Setup: Could not clear connection cache: ' . $e->getMessage());
            }

        } catch (\Throwable $e) {
            log_message('error', 'Setup: Could not apply runtime DB config: ' . $e->getMessage());
        }
    }

    /**
     * Debug method to log current database configuration state
     */
    protected function debugDatabaseConfig(string $context = ''): void
    {
        try {
            $prefix = $context ? "Setup[$context]: " : 'Setup: ';
            
            // Log environment variables
            $envVars = [
                'database.default.hostname' => getenv('database.default.hostname'),
                'database.default.database' => getenv('database.default.database'),
                'database.default.username' => getenv('database.default.username'),
                'database.default.password' => getenv('database.default.password') ? '[SET]' : '[NOT SET]',
                'database.default.DBDriver' => getenv('database.default.DBDriver'),
            ];
            log_message('info', $prefix . 'Environment variables: ' . json_encode($envVars));
            
            // Log CodeIgniter config
            /** @var DatabaseConfig $dbConfig */
            $dbConfig = config('Database');
            if ($dbConfig && property_exists($dbConfig, 'default')) {
                $configVars = [
                    'DBDriver' => $dbConfig->default['DBDriver'] ?? 'NOT SET',
                    'hostname' => $dbConfig->default['hostname'] ?? 'NOT SET',
                    'database' => $dbConfig->default['database'] ?? 'NOT SET',
                    'username' => $dbConfig->default['username'] ?? 'NOT SET',
                    'password' => !empty($dbConfig->default['password']) ? '[SET]' : '[NOT SET]',
                    'port' => $dbConfig->default['port'] ?? 'NOT SET',
                ];
                log_message('info', $prefix . 'CodeIgniter DB config: ' . json_encode($configVars));
            }
            
        } catch (\Throwable $e) {
            log_message('warning', $prefix . 'Could not debug DB config: ' . $e->getMessage());
        }
    }

    /**
     * Detect production URL from current request
     */
    protected function detectProductionURL(): string
    {
        // In production, prefer to leave empty for App.php auto-detection
        // But if we can reliably detect the URL, use it
        if (!empty($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                       (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') 
                       ? 'https://' : 'http://';
            
            $host = $_SERVER['HTTP_HOST'];
            
            // Handle subdirectory installations
            $path = '';
            if (!empty($_SERVER['SCRIPT_NAME'])) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                if ($scriptDir !== '/' && $scriptDir !== '.') {
                    $path = $scriptDir;
                }
            }
            
            return $protocol . $host . $path . '/';
        }
        
        // Fallback: leave empty for App.php constructor to handle
        return '';
    }

    /**
     * Generate a secure encryption key
     */
    protected function generateEncryptionKey(): string
    {
        return 'hex2bin:' . bin2hex(random_bytes(32));
    }

    /**
     * Test database connection with provided credentials
     */
    public function testConnection(): ResponseInterface
    {
        try {
            // Handle both JSON and form data
            $data = [];
            $contentType = $this->request->getHeaderLine('Content-Type');
            
            if (strpos($contentType, 'application/json') !== false) {
                // Handle JSON request
                $json = $this->request->getJSON(true);
                if (!$json) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Invalid JSON data received'
                    ])->setStatusCode(400);
                }
                $data = $json;
            } else {
                // Handle form data
                $post = $this->request->getPost();
                $data = [
                    'db_driver' => 'MySQLi',
                    'db_hostname' => $post['mysql_hostname'] ?? '',
                    'db_port' => $post['mysql_port'] ?? '3306',
                    'db_database' => $post['mysql_database'] ?? '',
                    'db_username' => $post['mysql_username'] ?? '',
                    'db_password' => $post['mysql_password'] ?? ''
                ];
            }

            // Validate required fields
            $required = ['db_driver', 'db_hostname', 'db_database', 'db_username'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => "Missing required field: {$field}"
                    ])->setStatusCode(400);
                }
            }

            // Ensure port is set and cast to integer for strict drivers
            if (empty($data['db_port'])) {
                $data['db_port'] = 3306;
            } else {
                $data['db_port'] = (int) $data['db_port'];
            }

            // Ensure password is set (can be empty)
            if (!isset($data['db_password'])) {
                $data['db_password'] = '';
            }

            // Test the connection
            $testResult = $this->testDatabaseConnection($data);

            // If connection is successful, update .env file and reset setup flag
            if ($testResult['success']) {
                log_message('info', 'Database connection test successful, updating .env file');
                
                // Update .env file with working credentials
                $envUpdateSuccess = $this->generateEnvFile($data);
                
                if ($envUpdateSuccess) {
                    // Reset setup completion flag to allow re-running setup with new credentials
                    $this->resetSetupFlags();
                    
                    log_message('info', 'Database credentials updated in .env file and setup flags reset');
                    
                    return $this->response->setJSON([
                        'success' => true,
                        'message' => $testResult['message'] . ' Database credentials have been saved to configuration file.',
                        'env_updated' => true,
                        'setup_reset' => true
                    ]);
                } else {
                    log_message('warning', 'Database connection successful but .env update failed: ' . ($this->envError ?? 'Unknown error'));
                    
                    return $this->response->setJSON([
                        'success' => true,
                        'message' => $testResult['message'] . ' Warning: Could not save credentials to configuration file.',
                        'env_updated' => false,
                        'setup_reset' => false,
                        'warning' => $this->envError ?? 'Failed to update configuration file'
                    ]);
                }
            }

            return $this->response->setJSON([
                'success' => $testResult['success'],
                'message' => $testResult['message']
            ]);

        } catch (Exception $e) {
            log_message('error', 'Database connection test failed: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Actually test the database connection
     */
    protected function testDatabaseConnection(array $config): array
    {
        if ($config['db_driver'] === 'SQLite3') {
            return $this->testSQLiteConnection($config);
        } else {
            return $this->testMySQLConnection($config);
        }
    }

    /**
     * Test SQLite connection
     */
    protected function testSQLiteConnection(array $config): array
    {
        try {
            // Use the full path directly from config
            $dbPath = $config['db_database'];
            $dbDir = dirname($dbPath);

            // Ensure directory exists and is writable
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            if (!is_writable($dbDir)) {
                return [
                    'success' => false,
                    'message' => 'Database directory is not writable: ' . $dbDir
                ];
            }

            // Test SQLite connection
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->exec('SELECT 1');

            return [
                'success' => true,
                'message' => 'SQLite connection successful. Database will be created automatically.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SQLite connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test MySQL connection
     */
    protected function testMySQLConnection(array $config): array
    {
        try {
            $dsn = "mysql:host={$config['db_hostname']};port={$config['db_port']};charset=utf8mb4";
            $pdo = new \PDO($dsn, $config['db_username'], $config['db_password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5
            ]);

            // Check if database exists
            $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
            $stmt->execute([$config['db_database']]);
            $dbExists = $stmt->fetch() !== false;

            if (!$dbExists) {
                return [
                    'success' => false,
                    'message' => "Database '{$config['db_database']}' does not exist. Please create it first."
                ];
            }

            // Test selecting the database
            $pdo->exec("USE `{$config['db_database']}`");

            return [
                'success' => true,
                'message' => 'MySQL connection successful. Database exists and is accessible.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'MySQL connection failed: ' . $e->getMessage()
            ];
        }
    }

    private function isSetupCompleted(): bool
    {
        // Check for setup completion flag files first (no database query needed)
        $flagPathNew = WRITEPATH . 'setup_complete.flag';
        $flagPathLegacy = WRITEPATH . 'setup_completed.flag';
        
        if (file_exists($flagPathNew) || file_exists($flagPathLegacy)) {
            log_message('debug', 'Setup completed: flag file found');
            return true;
        }

        // If no flag files, check .env and database (but only if credentials are set)
        $envReady = file_exists(ROOTPATH . '.env');
        if (!$envReady) {
            log_message('debug', 'Setup not completed: .env file missing');
            return false;
        }

        // Try simple DB readiness check: can we connect and does the users table exist?
        try {
            // Check if database credentials are actually configured
            $dbConfig = new \Config\Database();
            $defaultGroup = $dbConfig->{$dbConfig->defaultGroup};
            
            // If database credentials are empty, setup is not complete
            if (empty($defaultGroup['hostname']) || empty($defaultGroup['database'])) {
                log_message('debug', 'Setup not completed: database credentials not configured');
                return false;
            }

            $db = \Config\Database::connect();
            if (!$db) {
                log_message('debug', 'Setup not completed: DB connection failed');
                return false;
            }
            // Require both users and settings tables to avoid partial setup state
            $hasUsers = $db->tableExists('users');
            $hasSettings = $db->tableExists('settings');
            log_message('debug', 'Setup check: users=' . ($hasUsers ? 'yes' : 'no') . ' settings=' . ($hasSettings ? 'yes' : 'no'));
            return $hasUsers && $hasSettings;
        } catch (\Throwable $e) {
            log_message('debug', 'Setup not completed: DB check failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset setup completion flags to allow re-running setup
     */
    private function resetSetupFlags(): bool
    {
        $flagsReset = true;
        
        // Remove flag files if they exist
        $flagPathLegacy = WRITEPATH . 'setup_completed.flag';
        $flagPathNew = WRITEPATH . 'setup_complete.flag';
        
        if (file_exists($flagPathNew)) {
            if (!unlink($flagPathNew)) {
                log_message('warning', 'Failed to remove setup flag: ' . $flagPathNew);
                $flagsReset = false;
            } else {
                log_message('info', 'Removed setup flag: ' . $flagPathNew);
            }
        }
        
        if (file_exists($flagPathLegacy)) {
            if (!unlink($flagPathLegacy)) {
                log_message('warning', 'Failed to remove legacy setup flag: ' . $flagPathLegacy);
                $flagsReset = false;
            } else {
                log_message('info', 'Removed legacy setup flag: ' . $flagPathLegacy);
            }
        }
        
        return $flagsReset;
        
        if (file_exists($flagPathNew)) {
            if (!unlink($flagPathNew)) {
                log_message('warning', 'Failed to remove setup flag: ' . $flagPathNew);
                $flagsReset = false;
            } else {
                log_message('info', 'Removed setup flag: ' . $flagPathNew);
            }
        }
        
        if (file_exists($flagPathLegacy)) {
            if (!unlink($flagPathLegacy)) {
                log_message('warning', 'Failed to remove legacy setup flag: ' . $flagPathLegacy);
                $flagsReset = false;
            } else {
                log_message('info', 'Removed legacy setup flag: ' . $flagPathLegacy);
            }
        }
        
        return $flagsReset;
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

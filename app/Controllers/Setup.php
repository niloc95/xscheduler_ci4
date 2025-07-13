<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class Setup extends BaseController
{
    public function __construct()
    {
        helper(['form', 'url']);
    }

    public function index()
    {
        // Check if setup is already completed
        if ($this->isSetupCompleted()) {
            return redirect()->to('/dashboard')->with('error', 'Setup has already been completed.');
        }

        return view('setup');
    }

    public function setup(): string
    {
        return $this->index();
    }

    public function process(): ResponseInterface
    {
        // Check if setup is already completed
        if ($this->isSetupCompleted()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Setup has already been completed.'
            ])->setStatusCode(400);
        }

        // CSRF protection
        if (!$this->validate(['csrf_test_name' => 'required'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'CSRF token validation failed.',
                'errors' => ['csrf' => ['CSRF token is required']]
            ])->setStatusCode(400);
        }

        // Validation rules
        $rules = [
            'admin_name' => 'required|min_length[2]|max_length[50]',
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
                    'db_port' => $this->request->getPost('mysql_port') ?: '3306',
                    'db_database' => $this->request->getPost('mysql_database'),
                    'db_username' => $this->request->getPost('mysql_username'),
                    'db_password' => $this->request->getPost('mysql_password')
                ];

                $setupData['database']['mysql'] = [
                    'hostname' => $dbConfig['db_hostname'],
                    'port' => (int)$dbConfig['db_port'],
                    'database' => $dbConfig['db_database'],
                    'username' => $dbConfig['db_username'],
                    'password' => $dbConfig['db_password']
                ];

                // Test MySQL connection before proceeding
                $connectionTest = $this->testDatabaseConnection($dbConfig);
                if (!$connectionTest['success']) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => $connectionTest['message']
                    ])->setStatusCode(400);
                }
            } else {
                // SQLite configuration
                $dbConfig = [
                    'db_driver' => 'SQLite3',
                    'db_hostname' => '',
                    'db_port' => '',
                    'db_database' => 'xscheduler.db',
                    'db_username' => '',
                    'db_password' => ''
                ];

                $setupData['database']['sqlite'] = [
                    'path' => WRITEPATH . 'database/xscheduler.db'
                ];
                
                // Test SQLite setup
                $connectionTest = $this->testDatabaseConnection($dbConfig);
                if (!$connectionTest['success']) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => $connectionTest['message']
                    ])->setStatusCode(400);
                }
            }

            // Generate .env file first - this is critical for the application to work
            if (!$this->generateEnvFile($dbConfig)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to generate environment configuration file.'
                ])->setStatusCode(500);
            }

            // Finalize setup: run migrations, create admin user, mark completed
            $finalizeResult = $this->finalizeSetup($setupData);
            
            if (!$finalizeResult['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $finalizeResult['message']
                ])->setStatusCode(500);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Setup completed successfully!',
                'redirect' => '/dashboard'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Setup process failed: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage()
            ])->setStatusCode(500);
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
            $this->writeSetupCompletedFlag($setupData);

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
     * Run database migrations
     */
    protected function runMigrations(): array
    {
        try {
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
            return [
                'success' => false,
                'message' => $e->getMessage()
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
                              ->where('email', $adminData['userid'])
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
                'email' => filter_var($adminData['userid'], FILTER_VALIDATE_EMAIL) ? 
                          $adminData['userid'] : 
                          $adminData['userid'] . '@admin.local',
                'phone' => null,
                'password_hash' => password_hash($adminData['password'], PASSWORD_DEFAULT),
                'role' => 'admin',
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
    protected function writeSetupCompletedFlag(array $setupData): void
    {
        $flagData = [
            'completed_at' => date('Y-m-d H:i:s'),
            'admin_email' => isset($setupData['admin']['userid']) ? 
                           (filter_var($setupData['admin']['userid'], FILTER_VALIDATE_EMAIL) ? 
                            $setupData['admin']['userid'] : 
                            $setupData['admin']['userid'] . '@admin.local') : 
                           'admin@setup.local',
            'database_type' => $setupData['database']['type'],
            'version' => '1.0.0'
        ];

        $flagPath = WRITEPATH . 'setup_completed.flag';
        file_put_contents($flagPath, json_encode($flagData, JSON_PRETTY_PRINT));
        
        log_message('info', 'Setup completion flag written: ' . $flagPath);
    }

    /**
     * Generate .env file from template and user inputs
     */
    protected function generateEnvFile(array $data): bool
    {
        $envExamplePath = ROOTPATH . '.env.example';
        $envPath = ROOTPATH . '.env';

        // Check if .env.example exists
        if (!file_exists($envExamplePath)) {
            log_message('error', 'Setup: .env.example template not found');
            return false;
        }

        try {
            // Read the template
            $envTemplate = file_get_contents($envExamplePath);

            // Replace template variables with user inputs
            $envContent = $this->populateEnvTemplate($envTemplate, $data);

            // Write the new .env file
            if (file_put_contents($envPath, $envContent) === false) {
                log_message('error', 'Setup: Failed to write .env file');
                return false;
            }

            // Set proper permissions
            chmod($envPath, 0644);

            log_message('info', 'Setup: .env file generated successfully');
            return true;

        } catch (Exception $e) {
            log_message('error', 'Setup: Error generating .env file - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Populate .env template with user configuration
     */
    protected function populateEnvTemplate(string $template, array $data): string
    {
        // Determine environment mode
        $environment = ENVIRONMENT === 'development' ? 'development' : 'production';
        
        // Smart baseURL detection for production environments
        $baseURL = '';
        if ($environment === 'development') {
            $baseURL = 'http://localhost:8081/';
        } else {
            // Auto-detect production URL from current request
            $baseURL = $this->detectProductionURL();
        }

        // Define replacement patterns
        $replacements = [
            // Environment settings
            'CI_ENVIRONMENT = production' => "CI_ENVIRONMENT = {$environment}",

            // App settings - use detected URL or leave empty for auto-detection
            "app.baseURL = ''" => "app.baseURL = '{$baseURL}'",
            'app.forceGlobalSecureRequests = true' => 'app.forceGlobalSecureRequests = ' . ($environment === 'production' ? 'true' : 'false'),
            'app.CSPEnabled = true' => 'app.CSPEnabled = ' . ($environment === 'production' ? 'true' : 'false'),

            // Database settings
            'database.default.hostname = localhost' => "database.default.hostname = {$data['db_hostname']}",
            'database.default.database = xscheduler_prod' => "database.default.database = {$data['db_database']}",
            'database.default.username = your_db_user' => "database.default.username = {$data['db_username']}",
            'database.default.password = your_db_password' => "database.default.password = {$data['db_password']}",
            'database.default.DBDriver = MySQLi' => "database.default.DBDriver = {$data['db_driver']}",
            'database.default.port = 3306' => "database.default.port = {$data['db_port']}",

            // Generate encryption key
            'encryption.key = your_32_character_encryption_key_here' => 'encryption.key = ' . $this->generateEncryptionKey(),

            // Security settings for production
            'security.CSRFProtection = true' => 'security.CSRFProtection = ' . ($environment === 'production' ? 'true' : 'false'),

            // Setup completion flag
            'setup.enabled = true' => 'setup.enabled = false',
            'setup.allowMultipleRuns = false' => 'setup.allowMultipleRuns = false',
        ];

        // Apply replacements
        $envContent = $template;
        foreach ($replacements as $search => $replace) {
            $envContent = str_replace($search, $replace, $envContent);
        }

        // Add setup completion timestamp
        $envContent .= "\n# Setup completed on " . date('Y-m-d H:i:s') . "\n";

        return $envContent;
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

            // Ensure port is set
            if (empty($data['db_port'])) {
                $data['db_port'] = '3306';
            }

            // Ensure password is set (can be empty)
            if (!isset($data['db_password'])) {
                $data['db_password'] = '';
            }

            // Test the connection
            $testResult = $this->testDatabaseConnection($data);

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
            $dbPath = ROOTPATH . 'writable/database/' . $config['db_database'];
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
        return file_exists(WRITEPATH . 'setup_completed.flag');
    }
}

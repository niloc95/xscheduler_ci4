<?php

/**
 * =============================================================================
 * SETUP INSTALLER SERVICE
 * =============================================================================
 *
 * @file        app/Services/SetupInstallerService.php
 * @description The setup wizard's install steps, extracted from the Setup
 *              controller (which now only handles HTTP and validation):
 *              DB connection testing, runtime DB config, .env generation,
 *              migrations, seeders, admin user creation and the completion flag.
 */

namespace App\Services;

use Config\Database as DatabaseConfig;
use Exception;

class SetupInstallerService
{
    /** Last error from .env generation, surfaced to the wizard UI. */
    protected ?string $envError = null;

    public function __construct()
    {
        helper(['setup', 'setupcompatibility']);
    }

    public function lastEnvError(): ?string
    {
        return $this->envError;
    }

    /**
     * After applyRuntimeDatabaseConfig(): log whether CodeIgniter's own default
     * connection works. Diagnostic only — the direct PDO test is authoritative.
     */
    public function probeFrameworkConnection(): void
    {
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
    }

    /**
     * Finalize setup process:
     * - Run database migrations
     * - Seed initial data (if exists)
     * - Create admin user
     * - Write setup completion flag
     */
    public function finalizeSetup(array $setupData): array
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

            // Step 5: Seed installed version from deployed version.json (non-fatal)
            $versionFile = ROOTPATH . 'version.json';
            $installedVersion = '1.0.0';
            if (file_exists($versionFile)) {
                $versionData = json_decode(file_get_contents($versionFile), true);
                if (!empty($versionData['version'])) {
                    $installedVersion = $versionData['version'];
                }
            }
            try {
                (new \App\Models\SettingModel())->upsert('system.installed_version', $installedVersion);
                log_message('info', 'Setup: seeded system.installed_version=' . $installedVersion);
            } catch (\Throwable $e) {
                log_message('warning', 'Setup: could not seed system.installed_version: ' . $e->getMessage());
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
    private function verifyRequiredTables(): array
    {
        $required = ['users', 'services', 'appointments', 'settings'];
        $missing = [];
        $db = null;
        try {
            // Use a non-shared connection and uncached checks to avoid stale
            // table metadata right after migration execution.
            $db = \Config\Database::connect('default', false);
            $prefixMismatch = [];

            $tableNames = array_map(
                static fn(string $name): string => strtolower($name),
                $db->listTables(),
            );

            foreach ($required as $t) {
                $prefixed = $db->prefixTable($t);
                $hasPrefixed = in_array(strtolower($prefixed), $tableNames, true);
                $hasUnprefixed = in_array(strtolower($t), $tableNames, true);

                if (! $hasPrefixed) {
                    if ($prefixed !== $t && $hasUnprefixed) {
                        $prefixMismatch[] = $t;
                        continue;
                    }

                    $missing[] = $prefixed;
                }
            }

            if (! empty($prefixMismatch)) {
                log_message('warning', 'Setup: Prefix mismatch detected. Unprefixed tables exist: ' . implode(', ', $prefixMismatch));
            }
        } catch (\Throwable $e) {
            log_message('error', 'Setup: verifyRequiredTables failed: ' . $e->getMessage());
            // If we cannot verify, consider as missing core tables
            $missing = $required;
        } finally {
            if ($db !== null && method_exists($db, 'close')) {
                $db->close();
            }
            unset($db);
        }

        return [
            'success' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): array
    {
        $db = null;
        $migrate = null;
        try {
            $db = \Config\Database::connect('default', false);

            $driver = property_exists($db, 'DBDriver') ? $db->DBDriver : 'unknown';
            $dbname = property_exists($db, 'database') ? $db->database : '';
            $prefix = property_exists($db, 'DBPrefix') ? $db->DBPrefix : '';
            log_message('info', 'Setup: Running migrations with DB driver=' . $driver . ' database=' . $dbname . ' prefix=' . $prefix);

            // Force a non-shared MigrationRunner bound to the explicit runtime
            // connection so we do not accidentally use stale/shared defaults.
            $migrate = \Config\Services::migrations(config('Migrations'), $db, false);
            
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
        } finally {
            unset($migrate);
            if ($db !== null && method_exists($db, 'close')) {
                $db->close();
            }
            unset($db);
        }
    }

    /**
     * Run database seeders (optional)
     */
    private function runSeeders(): array
    {
        $db = null;
        try {
            $db = \Config\Database::connect('default', false);

            $seeder = new \CodeIgniter\Database\Seeder(config('Database'), $db);
            
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
        } finally {
            if ($db !== null && method_exists($db, 'close')) {
                $db->close();
            }
            unset($seeder, $db);
        }
    }

    /**
     * Create the admin user in the users table
     */
    public function createAdminUser(array $adminData): array
    {
        $db = null;
        try {
            // Use a non-shared connection and uncached checks to avoid stale
            // metadata after migrations in this same request.
            $db = \Config\Database::connect('default', false);
            $prefix = method_exists($db, 'getPrefix') ? (string) $db->getPrefix() : '';
            $prefixedUsersTable = $db->prefixTable('users');

            $tableNames = array_map(
                static fn(string $name): string => strtolower($name),
                $db->listTables(),
            );

            $hasPrefixed = in_array(strtolower($prefixedUsersTable), $tableNames, true);
            $hasUnprefixed = in_array('users', $tableNames, true);

            // Resolve actual users table name in DB.
            $usersTable = null;
            if ($hasPrefixed) {
                $usersTable = $prefixedUsersTable;
            } elseif ($hasUnprefixed) {
                $usersTable = 'users';
            }
            
            // Check if users table exists
            if ($usersTable === null) {
                return [
                    'success' => false,
                    'message' => 'Users table does not exist. Please ensure migrations have run properly.'
                ];
            }

            // Decide what table name to pass to Query Builder without causing
            // double prefixing.
            $builderTable = $usersTable;
            if ($usersTable === $prefixedUsersTable && $prefix !== '') {
                // Let Query Builder apply prefix once.
                $builderTable = 'users';
            }

            $prefixTemporarilyCleared = false;
            if ($usersTable === 'users' && $prefix !== '') {
                // If table is unprefixed but connection has a prefix, temporarily
                // clear it so builder operations target the actual table.
                $db->setPrefix('');
                $builderTable = 'users';
                $prefixTemporarilyCleared = true;
                log_message('warning', 'Setup: Using unprefixed users table while DB prefix is set to ' . $prefix);
            }

            try {

            // Check if admin user already exists
            $existingUser = $db->table($builderTable)
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
            $result = $db->table($builderTable)->insert($userData);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Failed to insert admin user into database'
                ];
            }

            $newAdminId = (int) $db->insertID();

            } finally {
                if ($prefixTemporarilyCleared) {
                    $db->setPrefix($prefix);
                }
            }

            // xs_user_roles is the authoritative role store; xs_users.role is only
            // the derived primary. Written after the prefix restore so the builder
            // resolves the prefixed pivot table. Non-fatal: getRolesForUser() falls
            // back to the primary role until the backfill migration heals the row.
            try {
                $db->table($db->prefixTable('user_roles'))->insert([
                    'user_id'    => $newAdminId,
                    'role'       => 'admin',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                log_message('warning', 'Setup: could not write user_roles admin row: ' . $e->getMessage());
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
        } finally {
            if ($db !== null && method_exists($db, 'close')) {
                $db->close();
            }
            unset($db);
        }
    }

    /**
     * Write setup completion flag file
     */
    private function writeSetupCompletedFlag(array $setupData): array
    {
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

    $flagPathNew = WRITEPATH . 'setup_complete.flag';
    $flagDir = WRITEPATH;

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

        // Write authoritative setup completion flag
        $payload = json_encode($flagData, JSON_PRETTY_PRINT);
        $writeNew = @file_put_contents($flagPathNew, $payload);

        if ($writeNew === false) {
            $msg = 'Failed to write setup flag at path: ' . $flagPathNew;
            log_message('error', 'Setup: ' . $msg);
            return [ 'success' => false, 'message' => $msg ];
        }

        // Best-effort set perms
        @chmod($flagPathNew, 0644);

        log_message('info', 'Setup completion flag written: ' . $flagPathNew);
        return [ 'success' => true, 'message' => 'Flag written' ];
    }

    /**
     * Generate .env file from template and user inputs
     */
    public function generateEnvFile(array $data): bool
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
                               "database.default.DBPrefix = xs_\n" .
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

        } catch (\Throwable $e) {
            $this->envError = 'Exception during .env generation: ' . $e->getMessage();
            log_message('error', 'Setup: Error generating .env file - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Populate .env template with user configuration
     */
    private function populateEnvTemplate(string $template, array $data): string
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
        $envContent = $replaceKey($envContent, 'app.CSPEnabled', 'true'); // Always enabled — policy lives in Config/ContentSecurityPolicy.php

        // Database settings
        $envContent = $replaceKey($envContent, 'database.default.DBDriver', $data['db_driver'] ?? 'MySQLi');
        $envContent = $replaceKey($envContent, 'database.default.hostname', $data['db_hostname'] ?? 'localhost');
        $envContent = $replaceKey($envContent, 'database.default.database', $data['db_database'] ?? '');
        $envContent = $replaceKey($envContent, 'database.default.username', $data['db_username'] ?? '');
        $envContent = $replaceKey($envContent, 'database.default.password', $data['db_password'] ?? '');
    // Ensure port written as numeric value in .env
    $portValue = isset($data['db_port']) ? (int) $data['db_port'] : 3306;
    $envContent = $replaceKey($envContent, 'database.default.port', (string) $portValue);

        // Ensure DBPrefix is always set
        $envContent = $replaceKey($envContent, 'database.default.DBPrefix', 'xs_');

        // Encryption key
        $envContent = $replaceKey($envContent, 'encryption.key', $this->generateEncryptionKey());

        // Detect if current request is over HTTPS for cookie/security settings
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $envContent = $replaceKey($envContent, 'cookie.secure', $isHttps ? 'true' : 'false');
        $envContent = $replaceKey($envContent, 'app.forceGlobalSecureRequests', $isHttps ? 'true' : 'false');

        // Setup flags
        $envContent = $replaceKey($envContent, 'setup.enabled', 'false');
        $envContent = $replaceKey($envContent, 'setup.allowMultipleRuns', 'false');
        $envContent = $replaceKey(
            $envContent,
            'setup.compatibility_mode',
            ! empty($data['compatibility_mode']) ? 'true' : 'false',
        );

        // Add setup completion timestamp
        $envContent .= "\n# Setup completed on " . date('Y-m-d H:i:s') . "\n";

        return $envContent;
    }

    /**
     * Apply runtime DB configuration for the current request so migrations use selected DB
     */
    public function applyRuntimeDatabaseConfig(array $data): void
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

                // Always set DBPrefix
                $dbConfig->default['DBPrefix'] = 'xs_';
                
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
    public function debugDatabaseConfig(string $context = ''): void
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
    private function detectProductionURL(): string
    {
        // Detect the full base URL including any subdirectory the app lives in.
        // This is critical for subdirectory deployments (e.g. /v50a/, /booking/).
        if (!empty($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                       (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') 
                       ? 'https://' : 'http://';
            
            $host = $_SERVER['HTTP_HOST'];
            
            // Detect subdirectory from SCRIPT_NAME (most reliable)
            $path = '';
            if (!empty($_SERVER['SCRIPT_NAME'])) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '\\') {
                    $path = rtrim($scriptDir, '/\\');
                }
            }
            
            // Cross-check with REQUEST_URI if SCRIPT_NAME gave us nothing.
            // On some shared hosts, SCRIPT_NAME is just /index.php while
            // REQUEST_URI still contains the full /subfolder/setup path.
            if ($path === '' && !empty($_SERVER['REQUEST_URI'])) {
                $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
                // Strip the CI4 route portion (e.g. /setup, /setup/process)
                // to isolate the subdirectory prefix.
                $knownSegments = ['setup', 'auth', 'dashboard', 'index.php'];
                foreach ($knownSegments as $seg) {
                    $pos = strpos($uri, '/' . $seg);
                    if ($pos !== false && $pos > 0) {
                        $path = rtrim(substr($uri, 0, $pos), '/\\');
                        break;
                    }
                }
            }
            
            $detectedUrl = $protocol . $host . $path . '/';
            log_message('info', 'Setup: Detected production URL: ' . $detectedUrl . 
                       ' (SCRIPT_NAME=' . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . 
                       ', REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? 'N/A') . ')');
            
            return $detectedUrl;
        }
        
        // Fallback: leave empty for App.php constructor to handle
        log_message('warning', 'Setup: Could not detect production URL — HTTP_HOST is empty');
        return '';
    }

    /**
     * Generate a secure encryption key
     */
    private function generateEncryptionKey(): string
    {
        return 'hex2bin:' . bin2hex(random_bytes(32));
    }

    /**
     * Actually test the database connection
     */
    public function testDatabaseConnection(array $config): array
    {
        return $this->testMySQLConnection($config);
    }

    /**
     * Test MySQL connection
     */
    private function testMySQLConnection(array $config): array
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

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'MySQL connection failed: ' . $e->getMessage()
            ];
        }
    }
}

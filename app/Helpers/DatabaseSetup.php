<?php

/**
 * =============================================================================
 * DATABASE SETUP HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/DatabaseSetup.php
 * @description Class-based helper for initializing database during setup wizard.
 *              Supports both MySQL and SQLite database backends.
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 * Used internally by Setup controller:
 *     $setup = new DatabaseSetup($setupData);
 *     $success = $setup->initialize();
 * 
 * SUPPORTED DATABASES:
 * -----------------------------------------------------------------------------
 * - MySQL/MariaDB : Full production support
 * - SQLite        : Development/testing support
 * 
 * INITIALIZATION FLOW:
 * -----------------------------------------------------------------------------
 * 1. Receives setup data array from wizard
 * 2. Determines database type (mysql/sqlite)
 * 3. Creates database connection config
 * 4. Tests connection
 * 5. Creates required tables via migrations
 * 6. Seeds initial data (admin user, default settings)
 * 7. Writes .env file with database credentials
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * initialize()         : Main entry point, routes to MySQL or SQLite
 * initializeMySQL()    : MySQL-specific initialization
 * initializeSQLite()   : SQLite-specific initialization
 * runMigrations()      : Execute database migrations
 * seedInitialData()    : Create admin user and default settings
 * writeEnvFile()       : Update .env with database config
 * 
 * SETUP DATA STRUCTURE:
 * -----------------------------------------------------------------------------
 *     [
 *         'database' => [
 *             'type' => 'mysql',
 *             'mysql' => [
 *                 'hostname' => 'localhost',
 *                 'username' => 'root',
 *                 'password' => 'secret',
 *                 'database' => 'webschedulr'
 *             ]
 *         ],
 *         'admin' => [
 *             'email' => 'admin@example.com',
 *             'password' => 'hashed_password'
 *         ]
 *     ]
 * 
 * @see         app/Controllers/Setup.php for wizard controller
 * @see         app/Database/Migrations/ for schema definitions
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Helpers;

use Config\Database;

/**
 * Database Setup Helper
 * Handles database initialization for both MySQL and SQLite
 */
class DatabaseSetup
{
    private $setupData;
    
    public function __construct(array $setupData)
    {
        $this->setupData = $setupData;
    }

    /**
     * Initialize the database based on the setup configuration
     */
    public function initialize(): bool
    {
        try {
            if ($this->setupData['database']['type'] === 'mysql') {
                return $this->initializeMySQL();
            } else {
                return $this->initializeSQLite();
            }
        } catch (\Exception $e) {
            log_message('error', 'Database initialization failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize MySQL database
     */
    private function initializeMySQL(): bool
    {
        $config = $this->setupData['database']['mysql'];
        
        // Create database configuration
        $dbConfig = [
            'DSN'      => '',
            'hostname' => $config['hostname'],
            'username' => $config['username'],
            'password' => $config['password'],
            'database' => $config['database'],
            'DBDriver' => 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => ENVIRONMENT !== 'production',
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_general_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => $config['port'],
        ];

        // Test connection and create tables
        $db = \Config\Database::connect($dbConfig);
        
        if (!$db->initialize()) {
            throw new \Exception('Failed to connect to MySQL database');
        }

        // Create tables
        $this->createTables($db);
        $this->createAdminUser($db);

        // Save database configuration to file
        $this->saveDatabase(['default' => $dbConfig]);

        return true;
    }

    /**
     * Initialize SQLite database
     */
    private function initializeSQLite(): bool
    {
        $dbPath = $this->setupData['database']['sqlite']['path'];
        $dbDir = dirname($dbPath);

        // Create database directory if it doesn't exist
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        // SQLite configuration
        $dbConfig = [
            'DSN'      => '',
            'hostname' => '',
            'username' => '',
            'password' => '',
            'database' => $dbPath,
            'DBDriver' => 'SQLite3',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => ENVIRONMENT !== 'production',
            'charset'  => 'utf8',
            'DBCollat' => '',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => '',
        ];

        // Create database and tables
        $db = \Config\Database::connect($dbConfig);
        
        if (!$db->initialize()) {
            throw new \Exception('Failed to initialize SQLite database');
        }

        $this->createTables($db);
        $this->createAdminUser($db);

        // Save database configuration
        $this->saveDatabase(['default' => $dbConfig]);

        return true;
    }

    /**
     * Create necessary database tables
     */
    private function createTables($db): void
    {
        $forge = \Config\Database::forge($db);

        try {
            // Users table
            if (!$db->tableExists('users')) {
                $forge->addField([
                    'id' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => true,
                        'auto_increment' => true,
                    ],
                    'userid' => [
                        'type' => 'VARCHAR',
                        'constraint' => 50,
                        'unique' => true,
                    ],
                    'name' => [
                        'type' => 'VARCHAR',
                        'constraint' => 100,
                    ],
                    'email' => [
                        'type' => 'VARCHAR',
                        'constraint' => 100,
                        'null' => true,
                    ],
                    'password_hash' => [
                        'type' => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'role' => [
                        'type' => 'ENUM',
                        'constraint' => ['admin', 'user'],
                        'default' => 'user',
                    ],
                    'is_active' => [
                        'type' => 'BOOLEAN',
                        'default' => true,
                    ],
                    'created_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                    'updated_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->addUniqueKey('userid');
                
                if (!$forge->createTable('users')) {
                    throw new \Exception('Failed to create users table');
                }
                log_message('info', 'Users table created successfully');
            }

            // Settings table
            if (!$db->tableExists('settings')) {
                $forge->addField([
                    'id' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => true,
                        'auto_increment' => true,
                    ],
                    'setting_key' => [
                        'type' => 'VARCHAR',
                        'constraint' => 100,
                        'unique' => true,
                    ],
                    'setting_value' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                    'setting_type' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'default' => 'string',
                    ],
                    'created_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                    'updated_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->addUniqueKey('setting_key');
                
                if (!$forge->createTable('settings')) {
                    throw new \Exception('Failed to create settings table');
                }
                log_message('info', 'Settings table created successfully');
            }

            // Sessions table (if not already created by CI4)
            if (!$db->tableExists('ci_sessions')) {
                $forge->addField([
                    'id' => [
                        'type' => 'VARCHAR',
                        'constraint' => 128,
                    ],
                    'ip_address' => [
                        'type' => 'VARCHAR',
                        'constraint' => 45,
                    ],
                    'timestamp' => [
                        'type' => 'INT',
                        'constraint' => 10,
                        'unsigned' => true,
                        'default' => 0,
                    ],
                    'data' => [
                        'type' => 'BLOB',
                    ],
                ]);
                $forge->addKey('id', true);
                $forge->addKey('timestamp');
                
                if (!$forge->createTable('ci_sessions')) {
                    throw new \Exception('Failed to create sessions table');
                }
                log_message('info', 'Sessions table created successfully');
            }

        } catch (\Exception $e) {
            log_message('error', 'Database table creation failed: ' . $e->getMessage());
            throw new \Exception('Database table creation failed: ' . $e->getMessage());
        }

        log_message('info', 'All database tables created successfully');
    }

    /**
     * Create the admin user
     */
    private function createAdminUser($db): void
    {
        try {
            $adminData = [
                'userid' => $this->setupData['admin']['userid'],
                'name' => $this->setupData['admin']['name'],
                'password_hash' => $this->setupData['admin']['password'],
                'role' => 'admin',
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $builder = $db->table('users');
            
            // Check if admin user already exists
            $existingUser = $builder->where('userid', $adminData['userid'])->get()->getRow();
            if ($existingUser) {
                log_message('info', 'Admin user already exists, updating: ' . $adminData['userid']);
                $builder->where('userid', $adminData['userid'])->update([
                    'name' => $adminData['name'],
                    'password_hash' => $adminData['password_hash'],
                    'updated_at' => $adminData['updated_at']
                ]);
            } else {
                if (!$builder->insert($adminData)) {
                    throw new \Exception('Failed to insert admin user into database');
                }
                log_message('info', 'Admin user created successfully: ' . $adminData['userid']);
            }
            
        } catch (\Exception $e) {
            log_message('error', 'Admin user creation failed: ' . $e->getMessage());
            throw new \Exception('Admin user creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Save database configuration to app/Config/Database.php
     */
    private function saveDatabase(array $config): void
    {
        $configPath = APPPATH . 'Config/Database.php';
        
        // Backup the original config file
        if (file_exists($configPath)) {
            copy($configPath, $configPath . '.backup');
        }
        
        $configContent = "<?php\n\nnamespace Config;\n\nuse CodeIgniter\\Database\\Config;\n\n";
        $configContent .= "/**\n * Database Configuration\n */\n";
        $configContent .= "class Database extends Config\n{\n";
        $configContent .= "    /**\n     * The directory that holds the Migrations and Seeds directories.\n     */\n";
        $configContent .= "    public string \$filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;\n\n";
        $configContent .= "    /**\n     * Lets you choose which connection group to use if no other is specified.\n     */\n";
        $configContent .= "    public string \$defaultGroup = 'default';\n\n";

        foreach ($config as $group => $settings) {
            $configContent .= "    /**\n     * {$group} database configuration\n     */\n";
            $configContent .= "    public array \${$group} = [\n";
            foreach ($settings as $key => $value) {
                if (is_string($value)) {
                    $formattedValue = "'" . addslashes($value) . "'";
                } elseif (is_bool($value)) {
                    $formattedValue = $value ? 'true' : 'false';
                } elseif (is_array($value)) {
                    $formattedValue = '[]';
                } elseif (is_null($value)) {
                    $formattedValue = 'null';
                } else {
                    $formattedValue = $value;
                }
                $configContent .= "        '{$key}' => {$formattedValue},\n";
            }
            $configContent .= "    ];\n\n";
        }

        $configContent .= "    /**\n     * This database connection is used when running PHPUnit database tests.\n     */\n";
        $configContent .= "    public array \$tests = [\n";
        $configContent .= "        'DSN'         => '',\n";
        $configContent .= "        'hostname'    => '127.0.0.1',\n";
        $configContent .= "        'username'    => 'root',\n";
        $configContent .= "        'password'    => '',\n";
        $configContent .= "        'database'    => ':memory:',\n";
        $configContent .= "        'DBDriver'    => 'SQLite3',\n";
        $configContent .= "        'DBPrefix'    => 'db_',\n";
        $configContent .= "        'pConnect'    => false,\n";
        $configContent .= "        'DBDebug'     => true,\n";
        $configContent .= "        'charset'     => 'utf8',\n";
        $configContent .= "        'DBCollat'    => '',\n";
        $configContent .= "        'swapPre'     => '',\n";
        $configContent .= "        'encrypt'     => false,\n";
        $configContent .= "        'compress'    => false,\n";
        $configContent .= "        'strictOn'    => false,\n";
        $configContent .= "        'failover'    => [],\n";
        $configContent .= "        'port'        => 3306,\n";
        $configContent .= "        'foreignKeys' => true,\n";
        $configContent .= "        'busyTimeout' => 1000,\n";
        $configContent .= "    ];\n\n";

        $configContent .= "    public function __construct()\n    {\n        parent::__construct();\n\n";
        $configContent .= "        // Ensure that we always set the database group to 'tests' if\n";
        $configContent .= "        // we are currently running an automated test suite, so that\n";
        $configContent .= "        // we don't overwrite live data on accident.\n";
        $configContent .= "        if (ENVIRONMENT === 'testing') {\n";
        $configContent .= "            \$this->defaultGroup = 'tests';\n        }\n    }\n}\n";

        if (file_put_contents($configPath, $configContent) === false) {
            throw new \Exception('Failed to save database configuration file');
        }
        
        log_message('info', 'Database configuration saved successfully');
    }
}

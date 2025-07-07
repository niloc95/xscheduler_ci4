<?php

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

        // Users table
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
        $forge->createTable('users');

        // Settings table
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
        $forge->createTable('settings');

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
            $forge->createTable('ci_sessions');
        }

        log_message('info', 'Database tables created successfully');
    }

    /**
     * Create the admin user
     */
    private function createAdminUser($db): void
    {
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
        $builder->insert($adminData);

        log_message('info', 'Admin user created: ' . $adminData['userid']);
    }

    /**
     * Save database configuration to app/Config/Database.php
     */
    private function saveDatabase(array $config): void
    {
        $configPath = APPPATH . 'Config/Database.php';
        $configContent = "<?php\n\nnamespace Config;\n\nuse CodeIgniter\Database\Config;\n\nclass Database extends Config\n{\n";
        $configContent .= "    public string \$filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;\n";
        $configContent .= "    public string \$defaultGroup = 'default';\n\n";

        foreach ($config as $group => $settings) {
            $configContent .= "    public array \${$group} = [\n";
            foreach ($settings as $key => $value) {
                $formattedValue = is_string($value) ? "'{$value}'" : ($value === true ? 'true' : ($value === false ? 'false' : $value));
                $configContent .= "        '{$key}' => {$formattedValue},\n";
            }
            $configContent .= "    ];\n\n";
        }

        $configContent .= "    public function __construct()\n    {\n        parent::__construct();\n\n";
        $configContent .= "        // Ensure that we always set the database group to 'tests' if\n";
        $configContent .= "        // we are currently running an automated test suite, so that\n";
        $configContent .= "        // we don't overwrite live data on accident.\n";
        $configContent .= "        if (ENVIRONMENT === 'testing') {\n";
        $configContent .= "            \$this->defaultGroup = 'tests';\n        }\n    }\n}\n";

        file_put_contents($configPath, $configContent);
        log_message('info', 'Database configuration saved');
    }
}

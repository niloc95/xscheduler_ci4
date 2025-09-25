<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SetupDebug extends BaseCommand
{
    protected $group       = 'setup';
    protected $name        = 'setup:debug';
    protected $description = 'Debug setup database configuration issues';

    public function run(array $params)
    {
        CLI::write('🐛 Setup Database Configuration Debug', 'yellow');
        CLI::newLine();

        // Test 0: Check PHP extensions
        CLI::write('0. PHP Extensions Check:', 'cyan');
        $extensions = ['mysqli', 'pdo', 'pdo_mysql'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                CLI::write("   ✅ {$ext} extension loaded", 'green');
            } else {
                CLI::write("   ❌ {$ext} extension NOT loaded", 'red');
            }
        }

        CLI::newLine();

        // Test 1: Check environment variables
        CLI::write('1. Environment Variables:', 'cyan');
        $envVars = [
            'database.default.hostname',
            'database.default.database', 
            'database.default.username',
            'database.default.password',
            'database.default.DBDriver'
        ];
        
        foreach ($envVars as $var) {
            $value = getenv($var);
            $display = $var === 'database.default.password' ? ($value ? '[SET]' : '[NOT SET]') : ($value ?: '[NOT SET]');
            CLI::write("   {$var}: {$display}");
        }
        CLI::newLine();

        // Test 2: Check CodeIgniter database config
        CLI::write('2. CodeIgniter Database Config:', 'cyan');
        try {
            $dbConfig = config('Database');
            if ($dbConfig && property_exists($dbConfig, 'default')) {
                CLI::write("   DBDriver: " . ($dbConfig->default['DBDriver'] ?? '[NOT SET]'));
                CLI::write("   hostname: " . ($dbConfig->default['hostname'] ?? '[NOT SET]'));
                CLI::write("   database: " . ($dbConfig->default['database'] ?? '[NOT SET]'));
                CLI::write("   username: " . ($dbConfig->default['username'] ?? '[NOT SET]'));
                CLI::write("   password: " . (!empty($dbConfig->default['password']) ? '[SET]' : '[NOT SET]'));
                CLI::write("   port: " . ($dbConfig->default['port'] ?? '[DEFAULT]'));
            } else {
                CLI::write("   ❌ Database config not accessible", 'red');
            }
        } catch (\Throwable $e) {
            CLI::write("   ❌ Error accessing database config: " . $e->getMessage(), 'red');
        }
        CLI::newLine();

        // Test 3: Test CodeIgniter connection
        CLI::write('3. CodeIgniter Database Connection Test:', 'cyan');
        try {
            $db = \Config\Database::connect();
            if (!$db) {
                CLI::write("   ❌ Failed to create database connection", 'red');
            } else {
                CLI::write("   ✅ Database connection object created");
                CLI::write("   Connection class: " . get_class($db));
                
                try {
                    $initialized = $db->initialize();
                    if (!$initialized) {
                        CLI::write("   ❌ Failed to initialize database connection", 'red');
                        
                        // Try to get more error details
                        if (method_exists($db, 'getLastError')) {
                            CLI::write("   Last error: " . $db->getLastError());
                        }
                        if (method_exists($db, 'getError')) {
                            $error = $db->getError();
                            CLI::write("   Error details: " . json_encode($error));
                        }
                    } else {
                        CLI::write("   ✅ Database connection initialized");
                        
                        try {
                            // Test simple query
                            $result = $db->query('SELECT 1 as test');
                            if ($result) {
                                CLI::write("   ✅ Database query successful", 'green');
                            } else {
                                CLI::write("   ❌ Database query failed", 'red');
                            }
                        } catch (\Throwable $queryError) {
                            CLI::write("   ❌ Database query error: " . $queryError->getMessage(), 'red');
                        }
                    }
                } catch (\Throwable $initError) {
                    CLI::write("   ❌ Database initialization error: " . $initError->getMessage(), 'red');
                }
            }
        } catch (\Throwable $e) {
            CLI::write("   ❌ Database connection error: " . $e->getMessage(), 'red');
        }
        CLI::newLine();

        // Test 4.5: Test direct MySQLi connection
        CLI::write('4.5. Direct MySQLi Connection Test:', 'cyan');
        try {
            $dbConfig = config('Database');
            if ($dbConfig && property_exists($dbConfig, 'default')) {
                $config = $dbConfig->default;
                
                if (stripos($config['DBDriver'] ?? '', 'mysql') !== false) {
                    // Test raw MySQLi connection
                    $mysqli = new \mysqli(
                        $config['hostname'],
                        $config['username'],
                        $config['password'],
                        $config['database'],
                        $config['port'] ?? 3306
                    );
                    
                    if ($mysqli->connect_error) {
                        CLI::write("   ❌ MySQLi connection error: " . $mysqli->connect_error, 'red');
                        CLI::write("   Error code: " . $mysqli->connect_errno, 'red');
                    } else {
                        CLI::write("   ✅ Direct MySQLi connection successful", 'green');
                        
                        // Test simple query
                        $result = $mysqli->query('SELECT 1 as test');
                        if ($result) {
                            CLI::write("   ✅ MySQLi query successful", 'green');
                        } else {
                            CLI::write("   ❌ MySQLi query failed: " . $mysqli->error, 'red');
                        }
                        
                        $mysqli->close();
                    }
                } else {
                    CLI::write("   ⚠️  Not a MySQL database, skipping MySQLi test", 'yellow');
                }
            }
        } catch (\Throwable $e) {
            CLI::write("   ❌ Direct MySQLi connection error: " . $e->getMessage(), 'red');
        }
        CLI::newLine();

        // Test 4: Test direct PDO connection (if MySQL/MySQLi)
        CLI::write('4. Direct PDO Connection Test:', 'cyan');
        try {
            $dbConfig = config('Database');
            if ($dbConfig && property_exists($dbConfig, 'default')) {
                $config = $dbConfig->default;
                
                if (stripos($config['DBDriver'] ?? '', 'mysql') !== false) {
                    $dsn = "mysql:host={$config['hostname']};port=" . ($config['port'] ?? 3306) . ";charset=utf8mb4";
                    $pdo = new \PDO($dsn, $config['username'], $config['password'], [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_TIMEOUT => 5
                    ]);
                    
                    // Test database exists
                    $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
                    $stmt->execute([$config['database']]);
                    $dbExists = $stmt->fetch() !== false;
                    
                    if ($dbExists) {
                        CLI::write("   ✅ Direct PDO connection successful, database exists", 'green');
                    } else {
                        CLI::write("   ❌ Database '{$config['database']}' does not exist", 'red');
                    }
                } elseif (stripos($config['DBDriver'] ?? '', 'sqlite') !== false) {
                    $dbPath = $config['database'];
                    $pdo = new \PDO('sqlite:' . $dbPath);
                    $pdo->exec('SELECT 1');
                    CLI::write("   ✅ Direct SQLite connection successful", 'green');
                } else {
                    CLI::write("   ⚠️  Unknown database driver: " . ($config['DBDriver'] ?? 'NOT SET'), 'yellow');
                }
            }
        } catch (\Throwable $e) {
            CLI::write("   ❌ Direct connection error: " . $e->getMessage(), 'red');
        }
        CLI::newLine();

        // Test 5: Check .env file
        CLI::write('5. .env File Check:', 'cyan');
        $envPath = ROOTPATH . '.env';
        if (file_exists($envPath)) {
            CLI::write("   ✅ .env file exists");
            
            $envContent = file_get_contents($envPath);
            $dbLines = [];
            foreach (explode("\n", $envContent) as $line) {
                if (strpos($line, 'database.default.') === 0) {
                    // Hide password value
                    if (strpos($line, 'database.default.password') === 0) {
                        $line = 'database.default.password = [HIDDEN]';
                    }
                    $dbLines[] = $line;
                }
            }
            
            if (empty($dbLines)) {
                CLI::write("   ⚠️  No database configuration found in .env", 'yellow');
            } else {
                CLI::write("   Database configuration in .env:");
                foreach ($dbLines as $line) {
                    CLI::write("     " . trim($line));
                }
            }
        } else {
            CLI::write("   ❌ .env file does not exist", 'red');
        }
        
        CLI::newLine();
        CLI::write('🔍 Debug complete!', 'green');
    }
}
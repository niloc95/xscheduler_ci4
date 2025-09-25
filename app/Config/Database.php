<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * The default database connection.
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => '',
        'password'     => '',
        'database'     => '',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    //    /**
    //     * Sample database connection for SQLite3.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'database'    => 'database.db',
    //        'DBDriver'    => 'SQLite3',
    //        'DBPrefix'    => '',
    //        'DBDebug'     => true,
    //        'swapPre'     => '',
    //        'failover'    => [],
    //        'foreignKeys' => true,
    //        'busyTimeout' => 1000,
    //        'synchronous' => null,
    //        'dateFormat'  => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for Postgre.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'public',
    //        'DBDriver'   => 'Postgre',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'port'       => 5432,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for SQLSRV.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'dbo',
    //        'DBDriver'   => 'SQLSRV',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'encrypt'    => false,
    //        'failover'   => [],
    //        'port'       => 1433,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for OCI8.
    //     *
    //     * You may need the following environment variables:
    //     *   NLS_LANG                = 'AMERICAN_AMERICA.UTF8'
    //     *   NLS_DATE_FORMAT         = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_FORMAT    = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS'
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => 'localhost:1521/XEPDB1',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'DBDriver'   => 'OCI8',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'AL32UTF8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    /**
     * This database connection is used when running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',  // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR CI DEVS
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => false,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }

        // Load database configuration from environment variables
        // This ensures .env changes are reflected in database connections
        $this->loadDatabaseFromEnvironment();
    }

    /**
     * Load database configuration from environment variables
     */
    protected function loadDatabaseFromEnvironment(): void
    {
        // Update default connection with environment variables if they exist
        if (getenv('database.default.hostname') !== false) {
            $this->default['hostname'] = getenv('database.default.hostname');
        }
        if (getenv('database.default.database') !== false) {
            $this->default['database'] = getenv('database.default.database');
        }
        if (getenv('database.default.username') !== false) {
            $this->default['username'] = getenv('database.default.username');
        }
        if (getenv('database.default.password') !== false) {
            $this->default['password'] = getenv('database.default.password');
        }
        if (getenv('database.default.DBDriver') !== false) {
            $this->default['DBDriver'] = getenv('database.default.DBDriver');
        }
        if (getenv('database.default.DBPrefix') !== false) {
            $this->default['DBPrefix'] = getenv('database.default.DBPrefix');
        }
        if (getenv('database.default.port') !== false) {
            $this->default['port'] = (int) getenv('database.default.port');
        }

        // Handle boolean values
        if (getenv('database.default.pConnect') !== false) {
            $this->default['pConnect'] = filter_var(getenv('database.default.pConnect'), FILTER_VALIDATE_BOOLEAN);
        }
        if (getenv('database.default.DBDebug') !== false) {
            $this->default['DBDebug'] = filter_var(getenv('database.default.DBDebug'), FILTER_VALIDATE_BOOLEAN);
        }

        // Charset and collation
        if (getenv('database.default.charset') !== false) {
            $this->default['charset'] = getenv('database.default.charset');
        }
        if (getenv('database.default.DBCollat') !== false) {
            $this->default['DBCollat'] = getenv('database.default.DBCollat');
        }
    }
}

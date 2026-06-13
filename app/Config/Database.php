<?php

/**
 * =============================================================================
 * DATABASE CONFIGURATION
 * =============================================================================
 * 
 * @file        app/Config/Database.php
 * @description Database connection settings for WebScheduler. Supports MySQL/MariaDB
 *              with configurable connection pooling, character sets, and failover.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Defines database connection parameters including hostname, credentials, driver,
 * and connection options. Supports multiple connection groups for different
 * environments (development, testing, production).
 * 
 * CONNECTION GROUPS:
 * -----------------------------------------------------------------------------
 * - default    : Primary database connection used by models
 * - tests      : Separate database for PHPUnit testing (optional)
 * 
 * ENVIRONMENT VARIABLES (.env):
 * -----------------------------------------------------------------------------
 * - database.default.hostname  : Database server address
 * - database.default.database  : Database name
 * - database.default.username  : Database user
 * - database.default.password  : Database password
 * - database.default.DBDriver  : Driver (MySQLi)
 * - database.default.DBPrefix  : Table prefix (default: xs_)
 * 
 * TABLE PREFIX:
 * -----------------------------------------------------------------------------
 * All WebScheduler tables use 'xs_' prefix by default:
 * - xs_users, xs_appointments, xs_customers, xs_services, etc.
 * 
 * CHARACTER SET:
 * -----------------------------------------------------------------------------
 * Uses utf8mb4 for full Unicode support including emojis.
 * 
 * @see         app/Database/Migrations/ for table schemas
 * @see         .env.example for connection examples
 * @package     Config
 * @extends     CodeIgniter\Database\Config
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

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
        'database'    => '',
        'DBDriver'    => 'MySQLi',
        'DBPrefix'    => '',
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8mb4',
        'DBCollat'    => 'utf8mb4_general_ci',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => false,
        'failover'    => [],
        'port'        => 3306,
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
    }
}

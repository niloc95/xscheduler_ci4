<?php

/**
 * SetupCompatibilityHelper
 *
 * Provides database connection testing that is safe for restricted shared hosting
 * environments such as InfinityFree, Hostinger, and cPanel shared plans.
 *
 * WHY THIS EXISTS
 * ─────────────────────────────────────────────────────────────────────────────
 * Standard MySQL installers (including CI4's default database checker) often
 * probe with:
 *   SHOW DATABASES        — Requires the SHOW DATABASES global privilege
 *   CREATE DATABASE       — Requires the CREATE privilege on *.*
 *   GRANT …               — Requires the GRANT OPTION privilege
 *
 * Shared hosting providers pre-create the database and grant limited, single-
 * database privileges to the user. Running any of the above commands results in:
 *   SQLSTATE[42000]: Access denied; you need the SHOW DATABASES privilege
 *
 * Compatibility mode bypasses all admin-level SQL and instead tests only what
 * the user's credentials are actually permitted to do:
 *   SELECT 1                           — proves a live, authenticated connection
 *   CREATE TABLE IF NOT EXISTS …       — proves table-creation rights in that DB
 *
 * This mirrors the approach used by WordPress, Joomla, and other widely-
 * deployed PHP applications that target shared hosting.
 *
 * USAGE (in Setup controller)
 * ─────────────────────────────────────────────────────────────────────────────
 *   helper('SetupCompatibility');
 *
 *   $result = testDatabaseConnection(
 *       host:            $post['mysql_hostname'],
 *       db:              $post['mysql_database'],
 *       user:            $post['mysql_username'],
 *       pass:            $post['mysql_password'],
 *       port:            (int) $post['mysql_port'],
 *       compatMode:      (bool) $post['compatibility_mode']
 *   );
 *
 *   if (! $result['success']) {
 *       return $this->response->setJSON($result)->setStatusCode(422);
 *   }
 */

// ─────────────────────────────────────────────────────────────────────────────
// Error message translation
// Maps raw PDO / MySQL error fragments to user-friendly guidance.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * List of known restricted-SQL error signatures used for:
 *   1. Friendly message substitution
 *   2. Smart detection / automatic compat-mode suggestion
 */
const WS_RESTRICTED_SQL_SIGNATURES = [
    'SHOW DATABASES'        => true,
    'CREATE DATABASE'       => true,
    'GRANT'                 => true,
    'Access denied for'     => true,
    'you need (at least one of) the SUPER' => true,
    'you need the SHOW DATABASES privilege' => true,
];

/**
 * Translate a raw PDO exception message into a user-friendly string and a
 * flag indicating whether the error looks like a hosting restriction.
 *
 * @param  string $raw   The raw exception message from PDO.
 * @return array{friendly: string, suggestCompat: bool}
 */
function translateDbError(string $raw): array
{
    // Check for well-known hosting-restriction patterns first
    foreach (array_keys(WS_RESTRICTED_SQL_SIGNATURES) as $signature) {
        if (stripos($raw, $signature) !== false) {
            return [
                'friendly'      => 'Your hosting provider restricts certain database permissions. '
                                  . 'Please enable Compatibility Mode and try again.',
                'suggestCompat' => true,
            ];
        }
    }

    // Access / credential errors
    if (stripos($raw, 'Access denied') !== false) {
        return [
            'friendly'      => 'Database access denied — please check your username and password.',
            'suggestCompat' => false,
        ];
    }

    // Unknown host / connection refused
    if (
        stripos($raw, 'could not find driver') !== false ||
        stripos($raw, 'SQLSTATE[HY000] [2002]') !== false
    ) {
        return [
            'friendly'      => 'Could not reach the database server. '
                              . 'Check that the hostname and port are correct and that the server is running.',
            'suggestCompat' => false,
        ];
    }

    // Unknown database
    if (stripos($raw, 'Unknown database') !== false) {
        return [
            'friendly'      => 'The specified database does not exist. '
                              . 'On shared hosting the database must be created via your control panel first.',
            'suggestCompat' => false,
        ];
    }

    // Generic fallback — include the raw error for the developer but mask it
    // from the user-facing friendly message
    return [
        'friendly'      => 'An unexpected database error occurred. Please verify your credentials and try again.',
        'suggestCompat' => false,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Connection factory
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a PDO connection with safe, shared-hosting-friendly options.
 *
 * NOTE: ATTR_EMULATE_PREPARES is intentionally left at its default (true) for
 * compatibility with MySQL < 5.1.17 still found on some free hosts.
 *
 * @throws \PDOException on connection failure.
 */
function createSetupPdo(
    string $host,
    string $db,
    string $user,
    string $pass,
    int    $port = 3306
): \PDO {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $db
    );

    return new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_TIMEOUT            => 5, // fast timeout for installer UX
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Standard (non-restricted) connection tests
// Used only when compatibility mode is DISABLED.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Run the standard (unrestricted) connection checks.
 * Includes SHOW DATABASES — do NOT call this in compatibility mode.
 *
 * @throws \PDOException if any check fails.
 */
function runStandardChecks(\PDO $pdo, string $db): void
{
    // Confirm the target database is visible to this user.
    // This uses SHOW DATABASES which requires a global privilege — safe for
    // dedicated / VPS / managed DB hosting, but NOT for shared hosting.
    $stmt = $pdo->prepare('SHOW DATABASES LIKE :db');
    $stmt->execute([':db' => $db]);

    if ($stmt->fetch() === false) {
        throw new \RuntimeException(
            "Database '{$db}' was not found. "
            . "Please create the database before running the installer."
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared-hosting-safe connection tests
// Used when compatibility mode is ENABLED.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Run safe, permission-limited connection checks.
 *
 * Deliberately avoids:
 *   • SHOW DATABASES   — requires SHOW DATABASES global privilege
 *   • CREATE DATABASE  — requires CREATE privilege on *.*
 *   • GRANT …          — requires GRANT OPTION
 *   • DROP TABLE       — not assumed; cleanup is optional
 *
 * Only uses:
 *   • SELECT 1         — proves live, authenticated connection
 *   • SHOW TABLES      — proves we are inside the correct database
 *   • CREATE TABLE IF NOT EXISTS — proves DDL rights within that database
 *
 * @throws \PDOException|\RuntimeException if any check fails.
 */
function runCompatibilityChecks(\PDO $pdo): void
{
    // Step 1 — verify a live, authenticated connection
    $pdo->query('SELECT 1');

    // Step 2 — verify we have table-level visibility (proves correct DB)
    $pdo->query('SHOW TABLES');

    // Step 3 — verify DDL rights within the database
    // Using a prefixed probe table name to avoid collisions with real app tables.
    // InnoDB is specified because MyISAM is missing on some hardened hosts.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `ws_install_probe` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Step 4 — best-effort cleanup (ignored on failure; DROP is not required)
    // We do NOT require DROP TABLE permission — many shared hosts grant only
    // SELECT, INSERT, UPDATE, DELETE, CREATE.
    try {
        $pdo->exec('DROP TABLE IF EXISTS `ws_install_probe`');
    } catch (\PDOException) {
        // Intentionally silenced — probe table is harmless if it stays.
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Public API
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Test a database connection in a way that is safe for the target environment.
 *
 * When $compatMode is TRUE the function uses only permissions that shared
 * hosting providers routinely grant. When FALSE it uses the full standard
 * check suite (SHOW DATABASES etc.) suitable for dedicated / VPS environments.
 *
 * The function never throws — all exceptions are caught and translated into
 * structured error responses.
 *
 * @param  string $host       MySQL hostname (e.g. "localhost" or "sql123.example.com")
 * @param  string $db         Database name (must already exist on shared hosting)
 * @param  string $user       MySQL username
 * @param  string $pass       MySQL password
 * @param  int    $port       MySQL port (default 3306)
 * @param  bool   $compatMode TRUE = shared-hosting safe, FALSE = full checks
 *
 * @return array{
 *     success:        bool,
 *     message:        string,
 *     error:          string|null,
 *     suggestCompat:  bool,
 *     compatMode:     bool
 * }
 */
function testDatabaseConnection(
    string $host,
    string $db,
    string $user,
    string $pass,
    int    $port       = 3306,
    bool   $compatMode = false
): array {
    try {
        $pdo = createSetupPdo($host, $db, $user, $pass, $port);

        if ($compatMode) {
            // Safe path: only table-level SQL — works on InfinityFree, Hostinger, cPanel
            runCompatibilityChecks($pdo);
            $message = 'Connection successful. Compatibility mode is active — '
                      . 'the installer will use only shared-hosting-safe SQL during setup.';
        } else {
            // Standard path: includes SHOW DATABASES for full pre-flight validation
            runStandardChecks($pdo, $db);
            $message = 'Connection successful. Database verified and ready.';
        }

        return [
            'success'       => true,
            'message'       => $message,
            'error'         => null,
            'suggestCompat' => false,
            'compatMode'    => $compatMode,
        ];
    } catch (\PDOException $e) {
        ['friendly' => $friendly, 'suggestCompat' => $suggestCompat]
            = translateDbError($e->getMessage());

        return [
            'success'       => false,
            'message'       => $friendly,
            'error'         => $e->getMessage(), // logged server-side only; not shown in UI
            'suggestCompat' => $suggestCompat,
            'compatMode'    => $compatMode,
        ];
    } catch (\RuntimeException $e) {
        // Thrown by runStandardChecks when the DB is not visible
        return [
            'success'       => false,
            'message'       => $e->getMessage(),
            'error'         => $e->getMessage(),
            'suggestCompat' => false,
            'compatMode'    => $compatMode,
        ];
    }
}

/**
 * Build the config array written to app/Config/Database.php (or .env) after a
 * successful installation.
 *
 * @param  string $host
 * @param  string $db
 * @param  string $user
 * @param  string $pass
 * @param  int    $port
 * @param  bool   $compatMode  Stored so the app can skip admin-SQL at runtime too.
 * @return array<string, mixed>
 */
function buildDatabaseConfig(
    string $host,
    string $db,
    string $user,
    string $pass,
    int    $port       = 3306,
    bool   $compatMode = false
): array {
    return [
        'db_host'           => $host,
        'db_port'           => $port,
        'db_name'           => $db,
        'db_user'           => $user,
        'db_pass'           => $pass,  // caller must encrypt before persistence
        'compatibility_mode'=> $compatMode,
    ];
}
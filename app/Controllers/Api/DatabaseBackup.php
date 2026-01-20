<?php

namespace App\Controllers\Api;

use App\Models\SettingModel;
use CodeIgniter\API\ResponseTrait;

/**
 * Database Backup Controller
 * 
 * Provides secure database backup functionality for admins only.
 * Implements all security requirements:
 * - Admin authentication required
 * - Master switch check (database.allow_backup setting)
 * - Randomized backup filenames
 * - Backups stored outside web root
 * - Audit logging for all operations
 */
class DatabaseBackup extends BaseApiController
{
    use ResponseTrait;

    protected SettingModel $settingModel;
    protected string $backupDirectory;

    public function __construct()
    {
        $this->settingModel = new SettingModel();
        // Default backup directory - OUTSIDE web root for security
        $this->backupDirectory = WRITEPATH . 'backups';
    }

    /**
     * Create a new database backup
     * 
     * Security Requirements:
     * - AC1: Admin authentication required (403 without)
     * - AC2: Master switch must be enabled (403 if disabled)
     * - AC3: Randomized filenames + stored outside web root
     */
    public function create()
    {
        // AC1: Verify admin authentication
        if (!$this->isAdminAuthenticated()) {
            $this->logAudit('backup_attempt_unauthorized', [
                'ip' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString(),
            ]);
            return $this->failForbidden('Access denied. Admin authentication required.');
        }

        // AC2: Check master switch
        if (!$this->isBackupEnabled()) {
            $this->logAudit('backup_attempt_disabled', [
                'user_id' => session()->get('user_id'),
                'ip' => $this->request->getIPAddress(),
            ]);
            return $this->failForbidden('Database backups are disabled. Enable in Settings → Database.');
        }

        try {
            // Ensure backup directory exists
            if (!is_dir($this->backupDirectory)) {
                if (!mkdir($this->backupDirectory, 0755, true)) {
                    throw new \RuntimeException('Unable to create backup directory');
                }
            }

            // AC3: Generate randomized filename
            $timestamp = date('Y-m-d_His');
            $randomHash = bin2hex(random_bytes(8));
            $baseFilename = "backup-{$timestamp}-{$randomHash}.sql";
            $basePath = $this->backupDirectory . DIRECTORY_SEPARATOR . $baseFilename;

            // Get database configuration from .env
            $dbConfig = $this->getDatabaseConfig();
            
            if (empty($dbConfig['database'])) {
                throw new \RuntimeException('Database name is not configured');
            }

            // Perform backup based on database type - returns actual filename (may have .gz added)
            $actualFilepath = $this->performBackup($dbConfig, $basePath);

            if ($actualFilepath === false) {
                throw new \RuntimeException('Backup process failed');
            }

            // Verify file was created
            if (!file_exists($actualFilepath)) {
                throw new \RuntimeException('Backup file was not created');
            }

            $filesize = filesize($actualFilepath);
            $filename = basename($actualFilepath);
            $backupTime = date('Y-m-d H:i:s');

            // Save backup info to settings
            $userId = session()->get('user_id');
            $this->settingModel->upsert('database.last_backup_time', $backupTime, 'string', $userId);
            $this->settingModel->upsert('database.last_backup_file', $filename, 'string', $userId);
            $this->settingModel->upsert('database.last_backup_size', $filesize, 'int', $userId);

            // Log successful backup
            $this->logAudit('backup_success', [
                'user_id' => $userId,
                'filename' => $filename,
                'size' => $filesize,
                'ip' => $this->request->getIPAddress(),
            ]);

            return $this->respond([
                'success' => true,
                'filename' => $filename,
                'timestamp' => $backupTime,
                'size' => $filesize,
                'size_formatted' => $this->formatBytes($filesize),
                'message' => 'Database backup created successfully',
            ]);

        } catch (\Exception $e) {
            // Log backup failure
            $this->logAudit('backup_failed', [
                'user_id' => session()->get('user_id'),
                'error' => $e->getMessage(),
                'ip' => $this->request->getIPAddress(),
            ]);

            return $this->fail('Backup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get backup status and last backup info
     */
    public function status()
    {
        // Admin authentication required
        if (!$this->isAdminAuthenticated()) {
            return $this->failForbidden('Access denied. Admin authentication required.');
        }

        $settings = $this->settingModel->getByKeys([
            'database.allow_backup',
            'database.last_backup_time',
            'database.last_backup_file',
            'database.last_backup_size',
        ]);

        $dbConfig = $this->getDatabaseConfig();

        return $this->respond([
            'backup_enabled' => $this->isBackupEnabled(),
            'last_backup' => [
                'time' => $settings['database.last_backup_time'] ?? null,
                'filename' => $settings['database.last_backup_file'] ?? null,
                'size' => $settings['database.last_backup_size'] ?? null,
                'size_formatted' => isset($settings['database.last_backup_size']) 
                    ? $this->formatBytes((int)$settings['database.last_backup_size']) 
                    : null,
            ],
            'database' => [
                'type' => $dbConfig['driver'] ?? 'mysql',
                'name' => $dbConfig['database'] ?? '',
                'host' => $dbConfig['hostname'] ?? 'localhost',
            ],
        ]);
    }

    /**
     * Toggle backup enabled/disabled setting
     */
    public function toggleBackup()
    {
        if (!$this->isAdminAuthenticated()) {
            return $this->failForbidden('Access denied. Admin authentication required.');
        }

        $enabled = $this->request->getJSON(true)['enabled'] ?? false;
        $userId = session()->get('user_id');

        $this->settingModel->upsert('database.allow_backup', $enabled, 'bool', $userId);

        $this->logAudit('backup_toggle', [
            'user_id' => $userId,
            'enabled' => $enabled,
            'ip' => $this->request->getIPAddress(),
        ]);

        return $this->respond([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled ? 'Database backups enabled' : 'Database backups disabled',
        ]);
    }

    /**
     * Download a backup file securely
     * 
     * Security:
     * - Admin authentication required
     * - Strict filename validation (no path traversal)
     * - File must exist in backup directory
     */
    public function download(string $filename = null)
    {
        // Admin authentication required
        if (!$this->isAdminAuthenticated()) {
            $this->logAudit('backup_download_unauthorized', [
                'ip' => $this->request->getIPAddress(),
                'filename' => $filename,
            ]);
            return $this->failForbidden('Access denied. Admin authentication required.');
        }

        if (empty($filename)) {
            return $this->failValidation('Filename is required');
        }

        // Security: Validate filename strictly - no path traversal
        if (!$this->isValidBackupFilename($filename)) {
            $this->logAudit('backup_download_invalid_filename', [
                'user_id' => session()->get('user_id'),
                'filename' => $filename,
                'ip' => $this->request->getIPAddress(),
            ]);
            return $this->failValidation('Invalid filename');
        }

        $filepath = $this->backupDirectory . DIRECTORY_SEPARATOR . $filename;

        // Verify file exists
        if (!file_exists($filepath)) {
            return $this->failNotFound('Backup file not found');
        }

        // Double-check the resolved path is inside backup directory (prevent symlink attacks)
        $realPath = realpath($filepath);
        $realBackupDir = realpath($this->backupDirectory);
        if ($realPath === false || strpos($realPath, $realBackupDir) !== 0) {
            $this->logAudit('backup_download_path_traversal_attempt', [
                'user_id' => session()->get('user_id'),
                'filename' => $filename,
                'ip' => $this->request->getIPAddress(),
            ]);
            return $this->failForbidden('Access denied');
        }

        // Log download
        $this->logAudit('backup_download', [
            'user_id' => session()->get('user_id'),
            'filename' => $filename,
            'ip' => $this->request->getIPAddress(),
        ]);

        // Send file as download
        return $this->response->download($filepath, null)->setFileName($filename);
    }

    /**
     * List available backup files
     */
    public function list()
    {
        if (!$this->isAdminAuthenticated()) {
            return $this->failForbidden('Access denied. Admin authentication required.');
        }

        $backups = [];
        
        if (is_dir($this->backupDirectory)) {
            $files = glob($this->backupDirectory . DIRECTORY_SEPARATOR . 'backup-*.sql*');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $backups[] = [
                    'filename' => $filename,
                    'size' => filesize($file),
                    'size_formatted' => $this->formatBytes(filesize($file)),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
            
            // Sort by creation time descending
            usort($backups, fn($a, $b) => strtotime($b['created']) - strtotime($a['created']));
        }

        return $this->respond([
            'success' => true,
            'backups' => $backups,
            'total' => count($backups),
        ]);
    }

    /**
     * Delete a backup file
     */
    public function delete(string $filename = null)
    {
        if (!$this->isAdminAuthenticated()) {
            return $this->failForbidden('Access denied. Admin authentication required.');
        }

        if (empty($filename) || !$this->isValidBackupFilename($filename)) {
            return $this->failValidation('Invalid filename');
        }

        $filepath = $this->backupDirectory . DIRECTORY_SEPARATOR . $filename;

        // Verify path is inside backup directory
        $realPath = realpath($filepath);
        $realBackupDir = realpath($this->backupDirectory);
        if ($realPath === false || strpos($realPath, $realBackupDir) !== 0) {
            return $this->failForbidden('Access denied');
        }

        if (!file_exists($filepath)) {
            return $this->failNotFound('Backup file not found');
        }

        if (!unlink($filepath)) {
            return $this->fail('Failed to delete backup file', 500);
        }

        $this->logAudit('backup_deleted', [
            'user_id' => session()->get('user_id'),
            'filename' => $filename,
            'ip' => $this->request->getIPAddress(),
        ]);

        return $this->respond([
            'success' => true,
            'message' => 'Backup deleted successfully',
        ]);
    }

    /**
     * Check if current user is authenticated admin
     */
    protected function isAdminAuthenticated(): bool
    {
        if (!session()->get('isLoggedIn')) {
            return false;
        }
        
        // Session stores user data in a 'user' array with 'role' key
        $user = session()->get('user');
        $role = $user['role'] ?? session()->get('user_role') ?? session()->get('role');
        return $role === 'admin';
    }

    /**
     * Check if backup functionality is enabled
     */
    protected function isBackupEnabled(): bool
    {
        $setting = $this->settingModel->getByKeys(['database.allow_backup']);
        return (bool)($setting['database.allow_backup'] ?? false);
    }

    /**
     * Validate backup filename (security - prevent path traversal)
     */
    protected function isValidBackupFilename(string $filename): bool
    {
        // Must match our naming pattern: backup-YYYY-MM-DD_HHMMSS-randomhash.sql[.gz]
        // No slashes, no dots except for extension, no special characters
        if (preg_match('/^backup-\d{4}-\d{2}-\d{2}_\d{6}-[a-f0-9]{16}\.sql(\.gz)?$/', $filename)) {
            return true;
        }
        
        // Also allow legacy format: YYYYMMDD_HHMMSS_full.sql.gz
        if (preg_match('/^\d{8}_\d{6}_full\.sql\.gz$/', $filename)) {
            return true;
        }

        return false;
    }

    /**
     * Get database configuration from .env
     */
    protected function getDatabaseConfig(): array
    {
        return [
            'driver' => env('database.default.DBDriver', 'MySQLi'),
            'hostname' => env('database.default.hostname', 'localhost'),
            'database' => env('database.default.database', ''),
            'username' => env('database.default.username', ''),
            'password' => env('database.default.password', ''),
            'port' => env('database.default.port', '3306'),
        ];
    }

    /**
     * Perform the actual database backup
     * @return string|false The actual filepath on success, false on failure
     */
    protected function performBackup(array $config, string $outputPath): string|false
    {
        $driver = strtolower($config['driver'] ?? 'mysqli');

        if (in_array($driver, ['mysqli', 'mysql'])) {
            return $this->backupMySQL($config, $outputPath);
        } elseif ($driver === 'sqlite3') {
            return $this->backupSQLite($config, $outputPath);
        }

        throw new \RuntimeException("Unsupported database driver: {$driver}");
    }

    /**
     * Backup MySQL database using mysqldump
     * @return string|false The actual filepath (with .gz if compressed) on success, false on failure
     */
    protected function backupMySQL(array $config, string $outputPath): string|false
    {
        if (!$this->isFunctionEnabled('system')) {
            throw new \RuntimeException('Database backup requires system command execution, but `system()` is disabled on this server.');
        }

        if (!$this->commandExists('mysqldump')) {
            throw new \RuntimeException('Database backup requires `mysqldump`, but it was not found on this server.');
        }

        $host = escapeshellarg($config['hostname']);
        $user = escapeshellarg($config['username']);
        $pass = $config['password'];
        $db = escapeshellarg($config['database']);
        $port = escapeshellarg($config['port'] ?? '3306');

        // Build mysqldump command
        // Use --defaults-extra-file to avoid password on command line
        $tmpConfigFile = tempnam(sys_get_temp_dir(), 'mysql_');
        file_put_contents($tmpConfigFile, "[client]\npassword=" . addslashes($pass) . "\n");
        chmod($tmpConfigFile, 0600);

        $configFile = escapeshellarg($tmpConfigFile);
        
        // Determine final output path (with or without gzip)
        $useGzip = $this->commandExists('gzip');
        $finalPath = $useGzip ? $outputPath . '.gz' : $outputPath;
        $outputFile = escapeshellarg($finalPath);

        // Build command
        if ($useGzip) {
            $command = "mysqldump --defaults-extra-file={$configFile} -h {$host} -P {$port} -u {$user} {$db} 2>/dev/null | gzip > {$outputFile}";
        } else {
            $command = "mysqldump --defaults-extra-file={$configFile} -h {$host} -P {$port} -u {$user} {$db} > {$outputFile} 2>/dev/null";
        }

        $exitCode = 0;
        system($command, $exitCode);

        // Clean up temporary config file
        @unlink($tmpConfigFile);

        // Check if file was created and has content
        if (!file_exists($finalPath) || filesize($finalPath) === 0) {
            @unlink($finalPath);
            return false;
        }

        return $finalPath;
    }

    /**
     * Backup SQLite database
     * @return string|false The actual filepath on success, false on failure
     */
    protected function backupSQLite(array $config, string $outputPath): string|false
    {
        if (!$this->isFunctionEnabled('system')) {
            throw new \RuntimeException('Database backup requires system command execution, but `system()` is disabled on this server.');
        }

        if (!$this->commandExists('sqlite3')) {
            throw new \RuntimeException('SQLite backup requires `sqlite3`, but it was not found on this server.');
        }

        $dbPath = $config['database'];
        
        if (!file_exists($dbPath)) {
            throw new \RuntimeException("SQLite database file not found: {$dbPath}");
        }

        // For SQLite, we can use the .dump command or simply copy the file
        $sqlite = escapeshellarg($dbPath);
        $output = escapeshellarg($outputPath);

        $command = "sqlite3 {$sqlite} .dump > {$output}";
        
        $exitCode = 0;
        system($command, $exitCode);

        // Return filepath on success, false on failure
        return ($exitCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) ? $outputPath : false;
    }

    /**
     * Check if a command exists on the system
     */
    protected function commandExists(string $command): bool
    {
        if ($command === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $command)) {
            return false;
        }

        // Prefer a pure-PHP lookup to avoid shell_exec/which, which may be disabled in production.
        $path = (string) getenv('PATH');
        $paths = $path !== '' ? array_filter(explode(PATH_SEPARATOR, $path)) : [];

        if (!$paths) {
            // Fallbacks for environments where PHP doesn't expose PATH.
            $paths = [
                '/usr/local/bin',
                '/usr/bin',
                '/bin',
                '/opt/homebrew/bin',
                '/opt/local/bin',
                '/usr/sbin',
                '/sbin',
            ];
        }

        // Windows: respect PATHEXT
        $extensions = [''];
        if (PHP_OS_FAMILY === 'Windows') {
            $pathext = (string) getenv('PATHEXT');
            $extensions = $pathext !== '' ? array_filter(array_map('trim', explode(';', $pathext))) : ['.EXE', '.BAT', '.CMD', '.COM'];
        }

        foreach ($paths as $dir) {
            foreach ($extensions as $ext) {
                $candidate = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $command . $ext;
                if (@is_file($candidate) && @is_executable($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a PHP function is available and not disabled via php.ini.
     */
    protected function isFunctionEnabled(string $functionName): bool
    {
        if ($functionName === '' || !function_exists($functionName)) {
            return false;
        }

        $disabled = (string) ini_get('disable_functions');
        if ($disabled === '') {
            return true;
        }

        $disabledList = array_filter(array_map('trim', explode(',', $disabled)));
        return !in_array($functionName, $disabledList, true);
    }

    /**
     * Format bytes to human readable string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Log audit event
     */
    protected function logAudit(string $action, array $data = []): void
    {
        $logPath = WRITEPATH . 'logs/database_backup_audit.log';
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'data' => $data,
        ];
        
        $logLine = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);

        // Also log to standard CI4 log
        log_message('info', "[DatabaseBackup] {$action}: " . json_encode($data));
    }
}

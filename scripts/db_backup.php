#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Simple database backup utility for WebSchedulr.
 *
 * Usage: php scripts/db_backup.php [target-directory]
 */

$rootDir = dirname(__DIR__);
$defaultOutput = $rootDir . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'backups';
$targetDir = $argv[1] ?? $defaultOutput;
$targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

if ($targetDir === '') {
    fwrite(STDERR, "Output directory is empty\n");
    exit(1);
}

if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
    fwrite(STDERR, "Unable to create output directory: {$targetDir}\n");
    exit(1);
}

$envFile = $rootDir . DIRECTORY_SEPARATOR . '.env';
$config = loadDatabaseSettings($envFile);

if ($config['database'] === '') {
    fwrite(STDERR, "Database name is not configured in .env\n");
    exit(1);
}

$timestamp = date('Ymd_His');
$backupFile = $targetDir . DIRECTORY_SEPARATOR . $timestamp . '_full.sql.gz';

$command = buildDumpCommand($config, $backupFile);

$exitCode = 0;
system($command, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Backup failed with exit code {$exitCode}\n");
    @unlink($backupFile);
    exit($exitCode);
}

echo "Backup written to {$backupFile}\n";
exit(0);

/**
 * Load database settings from the project's .env file.
 */
function loadDatabaseSettings(string $envFile): array
{
    $settings = [
        'hostname' => 'localhost',
        'database' => '',
        'username' => '',
        'password' => '',
        'port' => '3306',
    ];

    if (!is_file($envFile)) {
        return $settings;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $settings;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "'\"");

        switch ($key) {
            case 'database.default.hostname':
                $settings['hostname'] = $value;
                break;
            case 'database.default.database':
                $settings['database'] = $value;
                break;
            case 'database.default.username':
                $settings['username'] = $value;
                break;
            case 'database.default.password':
                $settings['password'] = $value;
                break;
            case 'database.default.port':
                $settings['port'] = $value;
                break;
        }
    }

    return $settings;
}

/**
 * Build the mysqldump command for the configured database.
 */
function buildDumpCommand(array $config, string $backupFile): string
{
    $envPassword = escapeshellarg($config['password']);
    $host = escapeshellarg($config['hostname']);
    $port = escapeshellarg((string) $config['port']);
    $user = escapeshellarg($config['username']);
    $database = escapeshellarg($config['database']);
    $target = escapeshellarg($backupFile);

    $options = [
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--events',
        '--set-gtid-purged=OFF',
    ];

    $optionString = implode(' ', $options);

    return sprintf(
        'MYSQL_PWD=%s mysqldump --host=%s --port=%s --user=%s %s %s | gzip > %s',
        $envPassword,
        $host,
        $port,
        $user,
        $optionString,
        $database,
        $target
    );
}

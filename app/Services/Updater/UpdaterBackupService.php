<?php

namespace App\Services\Updater;

use ZipArchive;

class UpdaterBackupService
{
    private const MAX_BACKUP_SETS = 3;

    public function createBackup(): array
    {
        $ts        = date('Ymd-His');
        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;

        if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Cannot create backup directory: ' . $backupDir);
        }

        $sqlPath   = $backupDir . "pre-update-{$ts}.sql";
        $filesPath = $backupDir . "pre-update-{$ts}-appfiles.zip";

        $this->dumpDatabasePhp($sqlPath);
        $this->zipAppFiles($filesPath);
        $this->appendLog($ts, $sqlPath, $filesPath);
        $this->pruneOldBackups($backupDir);

        return ['db' => $sqlPath, 'files' => $filesPath, 'timestamp' => $ts];
    }

    public function restoreBackup(string $timestamp): bool
    {
        $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
        $sqlPath   = $backupDir . "pre-update-{$timestamp}.sql";
        $filesPath = $backupDir . "pre-update-{$timestamp}-appfiles.zip";

        $ok = true;

        // Restore files first (app/ + public/)
        if (file_exists($filesPath)) {
            $zip = new ZipArchive();
            if ($zip->open($filesPath) === true) {
                $zip->extractTo(ROOTPATH);
                $zip->close();
            } else {
                log_message('error', '[UpdaterBackupService] Cannot open files backup: ' . $filesPath);
                $ok = false;
            }
        } else {
            log_message('error', '[UpdaterBackupService] Files backup not found: ' . $filesPath);
            $ok = false;
        }

        // Restore DB
        if (file_exists($sqlPath)) {
            $ok = $this->restoreFromSql($sqlPath) && $ok;
        } else {
            log_message('error', '[UpdaterBackupService] SQL backup not found: ' . $sqlPath);
            $ok = false;
        }

        return $ok;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function dumpDatabasePhp(string $destPath): void
    {
        $db     = \Config\Database::connect('default', false);
        $tables = $db->query('SHOW TABLES')->getResultArray();

        $fp = fopen($destPath, 'wb');
        if ($fp === false) {
            throw new \RuntimeException('Cannot open SQL dump file for writing: ' . $destPath);
        }

        fwrite($fp, "-- WebScheduler pre-update backup\n");
        fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $row) {
            $tableName = reset($row);

            // CREATE TABLE
            $createRow = $db->query("SHOW CREATE TABLE `{$tableName}`")->getRowArray();
            $createSql = $createRow['Create Table'] ?? '';
            fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");
            fwrite($fp, $createSql . ";\n\n");

            // INSERT rows
            $allRows = $db->query("SELECT * FROM `{$tableName}`")->getResultArray();
            if (empty($allRows)) {
                continue;
            }

            $columns = '`' . implode('`, `', array_keys($allRows[0])) . '`';
            fwrite($fp, "INSERT INTO `{$tableName}` ({$columns}) VALUES\n");

            $total = count($allRows);
            foreach ($allRows as $idx => $dataRow) {
                $vals = array_map(function ($val) use ($db) {
                    if ($val === null) return 'NULL';
                    return "'" . $db->escapeStr((string) $val) . "'";
                }, $dataRow);
                $line = '(' . implode(', ', $vals) . ')';
                $line .= ($idx < $total - 1) ? ',' : ';';
                fwrite($fp, $line . "\n");
            }
            fwrite($fp, "\n");
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);
    }

    private function zipAppFiles(string $destPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create app-files backup ZIP: ' . $destPath);
        }

        foreach ([APPPATH, FCPATH] as $baseDir) {
            $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!is_dir($baseDir)) {
                continue;
            }
            $root = dirname(rtrim($baseDir, DIRECTORY_SEPARATOR));

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                $filePath = $file->getPathname();
                // Skip build/ directory inside public/ — regenerated by Vite
                if (str_contains($filePath, DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $zipName = ltrim(str_replace($root, '', $filePath), DIRECTORY_SEPARATOR);
                $zip->addFile($filePath, $zipName);
            }
        }

        $zip->close();
    }

    private function restoreFromSql(string $sqlPath): bool
    {
        $db  = \Config\Database::connect('default', false);
        $sql = file_get_contents($sqlPath);

        if ($sql === false) {
            log_message('error', '[UpdaterBackupService] Cannot read SQL file: ' . $sqlPath);
            return false;
        }

        // Split on statement delimiter; skip comments and blank lines
        $statements = array_filter(
            array_map('trim', explode(";\n", $sql)),
            fn ($s) => $s !== '' && !str_starts_with($s, '--')
        );

        $ok = true;
        foreach ($statements as $stmt) {
            try {
                $db->query($stmt);
            } catch (\Throwable $e) {
                log_message('error', '[UpdaterBackupService] SQL restore error: ' . $e->getMessage());
                $ok = false;
            }
        }

        return $ok;
    }

    private function appendLog(string $ts, string $sqlPath, string $filesPath): void
    {
        $logFile = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'update-log.json';
        $log     = [];

        if (file_exists($logFile)) {
            $decoded = json_decode(file_get_contents($logFile), true);
            if (is_array($decoded)) {
                $log = $decoded;
            }
        }

        array_unshift($log, [
            'timestamp'  => $ts,
            'created_at' => date('Y-m-d H:i:s'),
            'db'         => basename($sqlPath),
            'files'      => basename($filesPath),
        ]);

        // Keep only the most recent sets in the log (prune happens separately)
        $log = array_slice($log, 0, self::MAX_BACKUP_SETS * 2);

        file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
    }

    private function pruneOldBackups(string $backupDir): void
    {
        $pattern  = $backupDir . 'pre-update-*.sql';
        $sqlFiles = glob($pattern);

        if (!is_array($sqlFiles) || count($sqlFiles) <= self::MAX_BACKUP_SETS) {
            return;
        }

        rsort($sqlFiles); // newest first
        $toDelete = array_slice($sqlFiles, self::MAX_BACKUP_SETS);

        foreach ($toDelete as $sqlFile) {
            @unlink($sqlFile);
            // Remove corresponding appfiles ZIP
            $filesZip = str_replace('.sql', '-appfiles.zip', $sqlFile);
            if (file_exists($filesZip)) {
                @unlink($filesZip);
            }
        }
    }
}

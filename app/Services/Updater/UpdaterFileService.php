<?php

namespace App\Services\Updater;

use ZipArchive;

class UpdaterFileService
{
    /**
     * Paths relative to project root that must never be overwritten.
     * These are real filesystem paths — not the assets/s/ and assets/p/ URL aliases.
     */
    private const PRESERVE_RELATIVE = [
        '.env',
        'writable/',
        'public/assets/settings/',
        'public/assets/providers/',
    ];

    public function extract(string $zipPath, string $projectRoot): array
    {
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $extracted   = 0;
        $skipped     = 0;
        $errors      = [];

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['extracted' => 0, 'skipped' => 0, 'errors' => ['Cannot open ZIP for extraction']];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // Reject path traversal
            if (str_contains($name, '..')) {
                $errors[] = 'Rejected path traversal entry: ' . $name;
                continue;
            }

            // Skip the version.json we injected — keep the deployed one after update
            if ($name === 'version.json') {
                // Extract it so the runtime deployed file reflects the new version
            }

            // Check preserve list
            if ($this->isPreserved($name)) {
                $skipped++;
                continue;
            }

            // Directories: just ensure they exist
            if (substr($name, -1) === '/') {
                $dir = $projectRoot . $name;
                if (!is_dir($dir)) {
                    if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                        $errors[] = 'Cannot create directory: ' . $name;
                    }
                }
                continue;
            }

            // Files: extract via stream to control memory
            $destPath = $projectRoot . $name;
            $destDir  = dirname($destPath);

            if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                $errors[] = 'Cannot create directory for: ' . $name;
                continue;
            }

            $stream = $zip->getStream($name);
            if ($stream === false) {
                $errors[] = 'Cannot read ZIP entry: ' . $name;
                continue;
            }

            $fp = @fopen($destPath, 'wb');
            if ($fp === false) {
                fclose($stream);
                $errors[] = 'Cannot write file: ' . $name;
                continue;
            }

            stream_copy_to_stream($stream, $fp);
            fclose($stream);
            fclose($fp);
            $extracted++;
        }

        $zip->close();

        // Clear cache after extraction so stale CI4 cache does not serve old responses
        $this->clearCache($projectRoot);

        return ['extracted' => $extracted, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function isPreserved(string $entryName): bool
    {
        foreach (self::PRESERVE_RELATIVE as $preserved) {
            // Directory entries: match prefix
            if (str_ends_with($preserved, '/')) {
                if (str_starts_with($entryName, $preserved)) {
                    return true;
                }
            } else {
                // Exact file match
                if ($entryName === $preserved) {
                    return true;
                }
            }
        }
        return false;
    }

    private function clearCache(string $projectRoot): void
    {
        $cacheDir = $projectRoot . 'writable' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($cacheDir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
                @unlink($file->getPathname());
            }
        }
    }
}

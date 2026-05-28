<?php

namespace App\Services\Updater;

use App\Models\SettingModel;
use ZipArchive;

class UpdaterValidatorService
{
    public function validate(string $zipPath): array
    {
        $errors = [];

        // 1. ZIP opens
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            return ['valid' => false, 'errors' => ['Cannot open ZIP file (code ' . $openResult . ')']];
        }

        // 2. version.json must exist at root — legacy packages without it are rejected
        $versionRaw = $zip->getFromName('version.json');
        $zip->close();

        if ($versionRaw === false) {
            return [
                'valid'  => false,
                'errors' => ['This package does not contain version.json. Only packages built with v1.0.5 or later support in-app updates. Please upload manually.'],
            ];
        }

        // 3. version.json is valid JSON with required keys
        $meta = json_decode($versionRaw, true);
        if (!is_array($meta) || empty($meta['version']) || !isset($meta['requires_migration'])) {
            return ['valid' => false, 'errors' => ['version.json is malformed or missing required fields (version, requires_migration).']];
        }

        $newVersion  = $meta['version'];
        $minVersion  = $meta['min_version'] ?? '1.0.0';

        // 4. Read installed version
        $settings          = (new SettingModel())->getByKeys(['system.installed_version']);
        $installedVersion  = $settings['system.installed_version'] ?? '1.0.0';

        // 5. No downgrades
        if (version_compare($newVersion, $installedVersion, '<=')) {
            $errors[] = "Cannot install v{$newVersion} over installed v{$installedVersion}. Downgrades are not supported.";
        }

        // 6. Minimum version compatibility
        if (version_compare($installedVersion, $minVersion, '<')) {
            $errors[] = "Installed v{$installedVersion} is below the minimum required v{$minVersion}. Please update incrementally.";
        }

        return [
            'valid'              => empty($errors),
            'version'            => $newVersion,
            'min_version'        => $minVersion,
            'requires_migration' => (bool) $meta['requires_migration'],
            'errors'             => $errors,
        ];
    }
}

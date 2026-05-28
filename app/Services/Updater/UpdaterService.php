<?php

namespace App\Services\Updater;

use App\Models\SettingModel;

class UpdaterService
{
    private UpdaterValidatorService  $validator;
    private UpdaterBackupService     $backupService;
    private UpdaterFileService       $fileService;
    private UpdaterMigrationService  $migrationService;

    public function __construct()
    {
        $this->validator        = new UpdaterValidatorService();
        $this->backupService    = new UpdaterBackupService();
        $this->fileService      = new UpdaterFileService();
        $this->migrationService = new UpdaterMigrationService();
    }

    public function run(string $zipPath): array
    {
        $enteredMaintenance = false;
        $filesMutated       = false;

        try {
            // Phase 1: Validate — safe to throw, maintenance not yet set
            $v = $this->validator->validate($zipPath);
            if (!$v['valid']) {
                return ['success' => false, 'phase' => 'validation', 'errors' => $v['errors']];
            }

            // Phase 2: Backup — safe to throw, maintenance not yet set
            $backup = $this->backupService->createBackup();

            // Phase 3: Maintenance ON
            $this->setMaintenance(true, $v['version'], 'extraction');
            $enteredMaintenance = true;

            // Phase 4: Extract files — point of no return
            $filesMutated = true;
            $ext = $this->fileService->extract($zipPath, ROOTPATH);
            if (!empty($ext['errors'])) {
                log_message('error', '[UpdaterService] Extraction errors — maintenance kept ON: ' . implode('; ', $ext['errors']));
                return [
                    'success'          => false,
                    'phase'            => 'extraction',
                    'errors'           => $ext['errors'],
                    'maintenance_left_on' => true,
                ];
            }

            // Phase 5: Run migrations
            if ($v['requires_migration']) {
                $this->setMaintenance(true, $v['version'], 'migration');
                $mig = $this->migrationService->run();
                if (!$mig['success']) {
                    log_message('error', '[UpdaterService] Migration failed — maintenance kept ON: ' . $mig['message']);
                    return [
                        'success'          => false,
                        'phase'            => 'migration',
                        'error'            => $mig['message'],
                        'maintenance_left_on' => true,
                    ];
                }
            }

            // Phase 6: Persist installed version
            (new SettingModel())->upsert('system.installed_version', $v['version']);

            // Phase 7: Clear cache and lift maintenance
            \Config\Services::cache()->clean();
            $this->setMaintenance(false);

            return [
                'success'    => true,
                'version'    => $v['version'],
                'backup_ts'  => $backup['timestamp'],
            ];

        } catch (\Throwable $e) {
            // Only clear maintenance if files have not been mutated yet
            if ($enteredMaintenance && !$filesMutated) {
                $this->setMaintenance(false);
            }
            log_message('error', '[UpdaterService] Unexpected error: ' . $e->getMessage());
            return [
                'success'          => false,
                'phase'            => 'exception',
                'error'            => $e->getMessage(),
                'maintenance_left_on' => $filesMutated,
            ];
        }
    }

    public function setMaintenance(bool $enable, string $version = '', string $phase = ''): void
    {
        $flagPath = WRITEPATH . 'maintenance.flag';

        if (!$enable) {
            @unlink($flagPath);
            return;
        }

        $data = [
            'since'   => date('Y-m-d H:i:s'),
            'version' => $version,
            'phase'   => $phase,
        ];
        $written = file_put_contents($flagPath, json_encode($data, JSON_PRETTY_PRINT));
        if ($written === false) {
            throw new \RuntimeException(
                'Cannot write maintenance flag to ' . $flagPath . '. Check that ' . WRITEPATH . ' is writable.'
            );
        }
    }

    public function isMaintenanceActive(): bool
    {
        return file_exists(WRITEPATH . 'maintenance.flag');
    }

    public function getMaintenanceData(): array
    {
        $flagPath = WRITEPATH . 'maintenance.flag';
        if (!file_exists($flagPath)) {
            return [];
        }
        return json_decode(file_get_contents($flagPath), true) ?? [];
    }
}

<?php

namespace App\Controllers\Admin;

use App\Controllers\Api\BaseApiController;
use App\Services\Updater\UpdaterService;
use App\Services\Updater\UpdaterValidatorService;

class Updater extends BaseApiController
{
    private UpdaterService $updaterService;

    public function __construct()
    {
        $this->updaterService = new UpdaterService();
    }

    /**
     * GET admin/updater/ — redirect to the System Update settings tab.
     */
    public function index()
    {
        return redirect()->to('/settings#system-update');
    }

    /**
     * POST admin/updater/upload
     *
     * Accepts a multipart file upload, validates the ZIP, and stores the
     * validated package path in the session for execute() to pick up.
     * Returns a full-page redirect to /settings#system-update with flashdata.
     */
    public function upload()
    {
        $file = $this->request->getFile('update_zip');

        if (!$file || !$file->isValid() || $file->hasMoved()) {
            session()->setFlashdata('updater_error', 'No valid ZIP file uploaded.');
            return redirect()->to('/settings#system-update');
        }

        if (strtolower($file->getClientExtension()) !== 'zip') {
            session()->setFlashdata('updater_error', 'Only .zip files are accepted.');
            return redirect()->to('/settings#system-update');
        }

        // Move to a temp location for validation
        $tmpDir  = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR;
        $tmpName = 'updater-pending-' . time() . '.zip';
        $tmpPath = $tmpDir . $tmpName;

        try {
            $file->move($tmpDir, $tmpName);
        } catch (\Throwable $e) {
            session()->setFlashdata('updater_error', 'Could not save uploaded file: ' . $e->getMessage());
            return redirect()->to('/settings#system-update');
        }

        // Validate
        $validator = new UpdaterValidatorService();
        $result    = $validator->validate($tmpPath);

        if (!$result['valid']) {
            @unlink($tmpPath);
            session()->setFlashdata('updater_error', implode(' ', $result['errors']));
            return redirect()->to('/settings#system-update');
        }

        // Store validated path in session so execute() can use it
        session()->set('updater_pending_zip', $tmpPath);
        session()->setFlashdata('updater_validation', [
            'version'            => $result['version'],
            'min_version'        => $result['min_version'],
            'requires_migration' => $result['requires_migration'],
        ]);

        return redirect()->to('/settings#system-update');
    }

    /**
     * POST admin/updater/execute
     *
     * Runs the full update: backup → maintenance → extract → migrate → done.
     * Returns JSON envelope. system-update.js navigates explicitly on success.
     */
    public function execute()
    {
        $zipPath = session()->get('updater_pending_zip');

        if (empty($zipPath) || !file_exists($zipPath)) {
            return $this->error(422, 'No validated package found. Please upload the ZIP first.', 'no_pending_zip');
        }

        $result = $this->updaterService->run($zipPath);

        // Clean up temp file regardless of outcome
        @unlink($zipPath);
        session()->remove('updater_pending_zip');

        if (!$result['success']) {
            $msg = $result['error'] ?? implode(', ', $result['errors'] ?? ['Update failed.']);
            if (!empty($result['maintenance_left_on'])) {
                $msg .= ' The site is still in maintenance mode. Rollback or clear maintenance from the System Update tab.';
            }
            return $this->error(500, $msg, 'update_failed');
        }

        session()->setFlashdata('updater_success', "Updated to v{$result['version']} successfully.");

        return $this->ok([
            'success'  => true,
            'version'  => $result['version'],
            'redirect' => base_url('settings#system-update'),
        ]);
    }

    /**
     * POST admin/updater/rollback
     *
     * Restores app/ + public/ + DB from the latest pre-update backup.
     * vendor/ and system/ are not covered.
     */
    public function rollback()
    {
        $body      = $this->request->getJSON(true) ?? [];
        $timestamp = $body['timestamp'] ?? '';

        if (empty($timestamp)) {
            return $this->error(422, 'No backup timestamp provided.', 'missing_timestamp');
        }

        $backupService = new \App\Services\Updater\UpdaterBackupService();
        $ok = $backupService->restoreBackup($timestamp);

        if (!$ok) {
            return $this->error(500, 'Rollback encountered errors. Check writable/logs/ for details.', 'rollback_failed');
        }

        // Clear maintenance after successful rollback
        $this->updaterService->setMaintenance(false);

        return $this->ok([
            'success'  => true,
            'redirect' => base_url('settings#system-update'),
        ]);
    }

    /**
     * POST admin/updater/maintenance
     *
     * Toggles maintenance mode on or off.
     * Body: { "enable": true|false }
     */
    public function toggleMaintenance()
    {
        $body   = $this->request->getJSON(true) ?? [];
        $enable = (bool) ($body['enable'] ?? false);

        $this->updaterService->setMaintenance($enable);

        return $this->ok([
            'success'     => true,
            'maintenance' => $enable,
            'redirect'    => base_url('settings#system-update'),
        ]);
    }
}

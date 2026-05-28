<?php
/**
 * Settings Tab: System Update
 *
 * Two-step update flow:
 *   1. Upload release ZIP (data-no-spa="true" — bypasses SPA interceptor)
 *   2. Execute after validation confirms version and migration requirements
 *
 * Rollback covers app/ + public/ + DB only. vendor/ and system/ are not backed up.
 * Vendor/framework regressions require re-uploading the previous release ZIP manually.
 */

$installedVersion = $settings['system.installed_version'] ?? '1.0.0';

// Read update log for history and available rollback
$logFile    = WRITEPATH . 'backups/update-log.json';
$updateLog  = [];
if (file_exists($logFile)) {
    $decoded   = json_decode(file_get_contents($logFile), true);
    $updateLog = is_array($decoded) ? array_slice($decoded, 0, 5) : [];
}

// Check if a rollback backup exists (latest entry in log)
$latestBackup    = $updateLog[0] ?? null;
$hasRollback     = $latestBackup && file_exists(WRITEPATH . 'backups/' . ($latestBackup['db'] ?? ''));

// Maintenance status
$maintenanceFlag = WRITEPATH . 'maintenance.flag';
$inMaintenance   = file_exists($maintenanceFlag);
$maintenanceData = [];
if ($inMaintenance) {
    $maintenanceData = json_decode(file_get_contents($maintenanceFlag), true) ?? [];
}

// Validation flash data from upload
$validationResult = session()->getFlashdata('updater_validation');
$validationError  = session()->getFlashdata('updater_error');
$updateSuccess    = session()->getFlashdata('updater_success');
?>

<section id="panel-system-update" class="tab-panel hidden">
    <div class="space-y-6">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-gray-500 dark:text-gray-400">system_update</span>
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">System Update</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Upload a release ZIP to update WebScheduler in-browser</p>
            </div>
        </div>

        <?php if ($inMaintenance): ?>
        <!-- Maintenance Mode Warning -->
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 shrink-0">warning</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Maintenance Mode Active</p>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                        The site is in maintenance mode
                        <?= !empty($maintenanceData['since']) ? 'since ' . esc($maintenanceData['since']) : '' ?>
                        <?= !empty($maintenanceData['phase']) ? '(phase: ' . esc($maintenanceData['phase']) . ')' : '' ?>.
                        Non-admin visitors see a 503 page. Clear maintenance once the site is healthy.
                    </p>
                    <button type="button" id="updater-clear-maintenance" class="mt-2 btn btn-sm btn-ghost text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-600">
                        <span class="material-symbols-outlined text-base">lock_open</span>
                        Clear Maintenance Mode
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($updateSuccess): ?>
        <!-- Success Banner -->
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-green-600 dark:text-green-400">check_circle</span>
                <p class="text-sm font-medium text-green-800 dark:text-green-300"><?= esc($updateSuccess) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($validationError): ?>
        <!-- Error Banner -->
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-red-600 dark:text-red-400 shrink-0">error</span>
                <div>
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">Upload failed</p>
                    <p class="text-xs text-red-700 dark:text-red-400 mt-1"><?= esc($validationError) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Version Info -->
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Installed Version</p>
                    <p class="text-sm font-mono font-semibold text-gray-900 dark:text-gray-100">v<?= esc($installedVersion) ?></p>
                </div>
                <a href="https://github.com/niloc95/webschedulr_ci4/releases" target="_blank" rel="noopener noreferrer"
                   class="btn btn-sm btn-ghost">
                    <span class="material-symbols-outlined text-base">open_in_new</span>
                    GitHub Releases
                </a>
            </div>
        </div>

        <!-- Step 1: Upload -->
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-blue-500">upload_file</span>
                Step 1 — Upload Release ZIP
            </h4>

            <?php if ($validationResult): ?>
            <!-- Validation result panel -->
            <div class="mb-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4" id="updater-validation-panel">
                <p class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">info</span>
                    Package validated — ready to apply
                </p>
                <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1">
                    <li>Version: <strong><?= esc($validationResult['version'] ?? '—') ?></strong></li>
                    <li>Minimum required: <strong><?= esc($validationResult['min_version'] ?? '—') ?></strong></li>
                    <li>Requires migration: <strong><?= ($validationResult['requires_migration'] ?? false) ? 'Yes' : 'No' ?></strong></li>
                </ul>
                <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-600 rounded text-xs text-amber-800 dark:text-amber-300">
                    <strong>Rollback scope:</strong> The in-browser rollback covers <code>app/</code>, <code>public/</code>, and the database.
                    It does <strong>not</strong> cover <code>vendor/</code> or <code>system/</code>. For framework-level regressions,
                    re-upload the previous release ZIP from GitHub Releases and restore the SQL backup from <code>writable/backups/</code>.
                </div>
            </div>

            <!-- Step 2: Execute -->
            <div class="mt-4 flex items-center gap-3">
                <button type="button" id="updater-execute-btn" class="btn btn-primary"
                        data-version="<?= esc($validationResult['version'] ?? '') ?>"
                        data-backup-ts="<?= esc($validationResult['backup_ts'] ?? '') ?>">
                    <span class="material-symbols-outlined text-base">rocket_launch</span>
                    Apply Update to v<?= esc($validationResult['version'] ?? '') ?>
                </button>
                <span class="text-xs text-gray-500 dark:text-gray-400">This will put the site in maintenance mode, replace files, and run migrations.</span>
            </div>
            <?php endif; ?>

            <!-- Upload form — data-no-spa bypasses SPA interceptor for multipart upload -->
            <form method="post"
                  action="<?= base_url('admin/updater/upload') ?>"
                  enctype="multipart/form-data"
                  class="mt-4"
                  data-no-spa="true">
                <?= csrf_field() ?>
                <div class="flex flex-col sm:flex-row items-start gap-3">
                    <div class="flex-1">
                        <input type="file"
                               name="update_zip"
                               id="updater-zip-input"
                               accept=".zip"
                               required
                               class="block w-full text-sm text-gray-700 dark:text-gray-300
                                      file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                      file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                      dark:file:bg-blue-900/30 dark:file:text-blue-300
                                      hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50
                                      border border-gray-300 dark:border-gray-600 rounded-lg p-2
                                      bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Packages larger than 50 MB may time out on shared hosting. Upload via cPanel file manager and execute manually if needed.
                        </p>
                    </div>
                    <button type="submit" class="btn btn-secondary shrink-0">
                        <span class="material-symbols-outlined text-base">search</span>
                        Validate
                    </button>
                </div>
            </form>
        </div>

        <!-- Maintenance Toggle (manual) -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-gray-500">construction</span>
                Maintenance Mode
            </h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                Enable maintenance mode to show a 503 page to non-admin visitors during planned work.
                Admin sessions are always allowed through.
            </p>
            <div class="flex gap-3">
                <button type="button" id="updater-maintenance-on" class="btn btn-sm btn-secondary <?= $inMaintenance ? 'hidden' : '' ?>">
                    <span class="material-symbols-outlined text-base">lock</span>
                    Enable Maintenance
                </button>
                <button type="button" id="updater-maintenance-off" class="btn btn-sm btn-ghost <?= $inMaintenance ? '' : 'hidden' ?>">
                    <span class="material-symbols-outlined text-base">lock_open</span>
                    Disable Maintenance
                </button>
            </div>
        </div>

        <!-- Rollback -->
        <?php if ($hasRollback): ?>
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-amber-500">history</span>
                Rollback
            </h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                Restores <code>app/</code>, <code>public/</code>, and the database from the latest pre-update backup.
                <strong>vendor/ and system/ are not covered</strong> — re-upload the previous release ZIP from GitHub Releases for those.
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                Backup: <span class="font-mono"><?= esc($latestBackup['timestamp'] ?? '') ?></span>
                (<?= esc($latestBackup['created_at'] ?? '') ?>)
            </p>
            <button type="button" id="updater-rollback-btn"
                    data-timestamp="<?= esc($latestBackup['timestamp'] ?? '') ?>"
                    class="btn btn-sm btn-danger">
                <span class="material-symbols-outlined text-base">restore</span>
                Rollback to Backup
            </button>
        </div>
        <?php endif; ?>

        <!-- Update History -->
        <?php if (!empty($updateLog)): ?>
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-gray-500">history</span>
                Update History
            </h4>
            <div class="space-y-2">
                <?php foreach ($updateLog as $entry): ?>
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 rounded px-3 py-2">
                    <span class="font-mono"><?= esc($entry['timestamp'] ?? '—') ?></span>
                    <span><?= esc($entry['created_at'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

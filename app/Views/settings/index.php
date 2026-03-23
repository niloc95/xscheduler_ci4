<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'settings']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="settings-page" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-4 md:p-6 mb-6" data-settings-api-url="<?= base_url('api/v1/settings') ?>" data-settings-logo-api-url="<?= base_url('api/v1/settings/logo') ?>" data-settings-icon-api-url="<?= base_url('api/v1/settings/icon') ?>" data-database-backup-api-url="<?= base_url('api/database-backup') ?>">

        <!-- Tabs -->
        <div class="overflow-x-auto">
            <nav class="flex gap-2 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Settings Sections">
                <button class="tab-btn active" data-tab="general">General</button>
                <button class="tab-btn" data-tab="localization">Localization</button>
                <button class="tab-btn" data-tab="booking">Booking</button>
                <button class="tab-btn" data-tab="business">Business hours</button>
                <button class="tab-btn" data-tab="legal">Legal Contents</button>
                <button class="tab-btn" data-tab="integrations">Integrations</button>
                <button class="tab-btn" data-tab="notifications">Notifications</button>
                <button class="tab-btn" data-tab="database">Database</button>
            </nav>
        </div>

    <!-- Tab Panels -->
    <div id="settings-content">

    <?= $this->include('settings/tabs/general') ?>

    <?= $this->include('settings/tabs/localization') ?>

    <?= $this->include('settings/tabs/booking') ?>

    <?= $this->include('settings/tabs/business') ?>

    <?= $this->include('settings/tabs/legal') ?>

    <?= $this->include('settings/tabs/integrations') ?>

    <?= $this->include('settings/tabs/notifications') ?>

    <?= $this->include('settings/tabs/database') ?>

        </div>

        <!-- Backup List Modal -->
        <div id="backup-list-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="card card-elevated max-w-2xl w-full mx-4 max-h-[80vh] flex flex-col">
                <div class="card-header flex items-center justify-between">
                    <h3 class="card-title">Database Backups</h3>
                    <button type="button" id="close-backup-modal" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="card-body overflow-y-auto">
                    <div id="backup-list-content">
                        <div class="text-center py-8 text-gray-500">
                            <div class="loading-spinner mx-auto mb-2"></div>
                            Loading backups...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Block Period Modal (Outside all forms) -->
        <div id="block-period-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="card card-elevated max-w-md w-full mx-4">
                <div class="card-header">
                    <h3 class="card-title" id="block-period-modal-title">Add Block Period</h3>
                </div>
                <form id="block-period-form">
                    <div class="card-body space-y-4">
                        <div class="form-field">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="block-period-start" class="form-input" required>
                        </div>
                        <div class="form-field">
                            <label class="form-label">End Date</label>
                            <input type="date" id="block-period-end" class="form-input" required>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Reason/Notes <span class="text-xs text-gray-400 font-normal">(optional)</span></label>
                            <input type="text" id="block-period-notes" class="form-input" maxlength="100" placeholder="e.g., Christmas Holiday, Office Maintenance">
                        </div>
                        <div id="block-period-error" class="text-red-500 text-sm hidden bg-red-50 dark:bg-red-900/20 p-3 rounded-lg border border-red-200 dark:border-red-800"></div>
                    </div>
                    <div class="card-footer">
                        <button type="button" id="block-period-cancel" class="btn btn-ghost">Cancel</button>
                        <button type="submit" id="block-period-save-btn" class="btn btn-primary">Save Period</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>
<?= $this->endSection() ?>

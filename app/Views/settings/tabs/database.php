<?php
/**
 * Settings Tab: Database
 *
 * Database info panel (read-only) + backup management.
 * No wrapping <form> — this is a read-only information panel
 * with JS-driven backup actions.
 * Included by settings/index.php via $this->include().
 */
?>
            <section id="panel-database" class="tab-panel hidden">
                <div class="space-y-6">
                    <!-- Database Information -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-base">database</span>
                            Database Information
                        </h4>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Database Type</label>
                                    <p id="db-info-type" class="text-sm text-gray-900 dark:text-gray-100 font-medium">Loading...</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Database Name</label>
                                    <p id="db-info-name" class="text-sm text-gray-900 dark:text-gray-100 font-medium">Loading...</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Host</label>
                                    <p id="db-info-host" class="text-sm text-gray-900 dark:text-gray-100 font-medium">Loading...</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                <span class="material-symbols-outlined text-xs align-middle">info</span>
                                Database connection is configured via the <code class="bg-gray-200 dark:bg-gray-600 px-1 rounded">.env</code> file. Contact your system administrator to change database settings.
                            </p>
                        </div>
                    </div>

                    <!-- Database Backup Section -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-base">backup</span>
                            Database Backup
                        </h4>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <!-- Backup Enable Toggle -->
                            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-gray-600">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-gray-100">Enable Database Backups</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Master switch for backup functionality</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="backup-enabled-toggle" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <!-- Last Backup Info -->
                            <div id="last-backup-info" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-600">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Last Backup</label>
                                <div id="last-backup-details" class="flex items-center gap-3">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">No backups yet</span>
                                </div>
                            </div>

                            <!-- Backup Actions -->
                            <div class="flex items-center gap-3">
                                <button type="button" id="create-backup-btn" class="btn btn-primary inline-flex items-center gap-2" disabled>
                                    <span class="material-symbols-outlined text-base">backup</span>
                                    Create Backup Now
                                </button>
                                <button type="button" id="view-backups-btn" class="btn btn-secondary inline-flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">folder_open</span>
                                    View All Backups
                                </button>
                            </div>

                            <!-- Backup Progress -->
                            <div id="backup-progress" class="mt-4 hidden">
                                <div class="flex items-center gap-3">
                                    <div class="loading-spinner"></div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Creating backup...</span>
                                </div>
                            </div>

                            <!-- Backup Error -->
                            <div id="backup-error" class="mt-4 hidden p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300 text-sm"></div>

                            <!-- Backup Success -->
                            <div id="backup-success" class="mt-4 hidden p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300 text-sm"></div>
                        </div>
                    </div>

                    <!-- Security Notice -->
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">security</span>
                            <div>
                                <h5 class="text-sm font-medium text-amber-800 dark:text-amber-200">Security Notice</h5>
                                <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                    Database backups are stored securely outside the web root. Only administrators can create and download backups. 
                                    All backup operations are logged for audit purposes.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

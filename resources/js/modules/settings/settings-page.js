import { initSettingsFormEnhancements } from './settings-form-ui.js';

const escapeHtml = (value) => {
    if (typeof window.xsEscapeHtml === 'function') {
        return window.xsEscapeHtml(value);
    }

    if (value === null || value === undefined) {
        return '';
    }

    const div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
};

const debugLog = (...args) => {
    if (typeof window.xsDebugLog === 'function') {
        window.xsDebugLog(...args);
    }
};

const getCsrfHeaders = () => {
    if (typeof window.xsGetCsrf !== 'function') {
        return {};
    }

    const { header, token } = window.xsGetCsrf();
    return token ? { [header]: token } : {};
};

const updateCsrfTokenFromResponse = (response) => {
    const newToken = response?.headers?.get('X-CSRF-TOKEN') || response?.headers?.get('x-csrf-token');
    if (!newToken) {
        return;
    }

    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        metaToken.setAttribute('content', newToken);
    }

    document.querySelectorAll('input[type="hidden"][name*="csrf"]').forEach((input) => {
        input.value = newToken;
    });
};

const unwrapApiData = (payload) => payload?.data ?? payload;

const notify = (type, title, message, autoClose = type !== 'error') => {
    if (window.XSNotify?.toast) {
        window.XSNotify.toast({
            type,
            title,
            message,
            autoClose,
            duration: autoClose ? 4000 : undefined,
        });
        return;
    }

    if (type === 'error') {
        window.alert(message);
    }
};

const parseErrorMessage = async (response, fallbackMessage) => {
    try {
        const payload = await response.json();
        return payload?.messages?.error || payload?.error?.message || payload?.message || fallbackMessage;
    } catch (_) {
        return fallbackMessage;
    }
};

const clonePeriods = (periods) => JSON.parse(JSON.stringify(periods));

function initBlockedPeriodsUI(root) {
    const container = document.getElementById('block-periods-list');
    if (!container || container.dataset.initialized === 'true') {
        return;
    }

    container.dataset.initialized = 'true';

    const settingsApiUrl = root.dataset.settingsApiUrl;
    const jsonInput = document.getElementById('blocked_periods_json');
    const emptyState = document.getElementById('block-periods-empty');
    const modal = document.getElementById('block-period-modal');
    const title = document.getElementById('block-period-modal-title');
    const form = document.getElementById('block-period-form');
    const startInput = document.getElementById('block-period-start');
    const endInput = document.getElementById('block-period-end');
    const notesInput = document.getElementById('block-period-notes');
    const errorBox = document.getElementById('block-period-error');
    const submitButton = document.getElementById('block-period-save-btn');
    const addButton = document.getElementById('add-block-period-btn');
    const cancelButton = document.getElementById('block-period-cancel');

    if (!jsonInput || !modal || !form || !startInput || !endInput || !notesInput || !errorBox || !submitButton || !emptyState) {
        return;
    }

    let blockPeriods;
    try {
        blockPeriods = JSON.parse(jsonInput.value || '[]');
        if (!Array.isArray(blockPeriods)) {
            blockPeriods = [];
        }
    } catch (_) {
        blockPeriods = [];
    }

    let editingIndex = null;

    const renderBlockPeriods = () => {
        container.innerHTML = '';

        if (!blockPeriods.length) {
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');

        blockPeriods.forEach((period, index) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors';

            const startDate = period.start ? new Date(period.start).toLocaleDateString() : '';
            const endDate = period.end ? new Date(period.end).toLocaleDateString() : '';
            const isMultiDay = period.start !== period.end;

            item.innerHTML = `
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-orange-500">event_busy</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            ${escapeHtml(startDate)}${isMultiDay ? ' - ' + escapeHtml(endDate) : ''}
                        </span>
                    </div>
                    ${period.notes ? `<p class="text-sm text-gray-600 dark:text-gray-400 ml-6">${escapeHtml(period.notes)}</p>` : ''}
                </div>
                <div class="flex gap-1 ml-4">
                    <button type="button" class="btn btn-ghost btn-sm p-2" data-edit="${index}" title="Edit period">
                        <span class="material-symbols-outlined text-sm">edit</span>
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm p-2 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" data-delete="${index}" title="Delete period">
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </div>
            `;

            container.appendChild(item);
        });
    };

    const showModalError = (message) => {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    };

    const clearModalError = () => {
        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    };

    const openModal = (mode, index = null) => {
        clearModalError();

        if (mode === 'edit' && index !== null) {
            const period = blockPeriods[index] || {};
            title.textContent = 'Edit Block Period';
            startInput.value = period.start || '';
            endInput.value = period.end || '';
            notesInput.value = period.notes || '';
            editingIndex = index;
        } else {
            title.textContent = 'Add Block Period';
            startInput.value = '';
            endInput.value = '';
            notesInput.value = '';
            editingIndex = null;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    const syncHiddenInput = () => {
        jsonInput.value = JSON.stringify(blockPeriods);
        renderBlockPeriods();
    };

    const persistBlockPeriods = async () => {
        const response = await fetch(settingsApiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...getCsrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                'business.blocked_periods': JSON.stringify(blockPeriods),
            }),
        });

        if (!response.ok) {
            throw new Error(await parseErrorMessage(response, `Save failed (HTTP ${response.status})`));
        }

        const result = await response.json();
        if (!result?.ok) {
            throw new Error(result?.message || 'Unable to save block period.');
        }

        return result;
    };

    addButton?.addEventListener('click', () => openModal('add'));

    cancelButton?.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        clearModalError();

        const start = startInput.value;
        const end = endInput.value;
        const notes = notesInput.value;

        if (!start || !end) {
            showModalError('Start and end dates are required.');
            return;
        }

        if (end < start) {
            showModalError('End date cannot be before start date.');
            return;
        }

        const previousPeriods = clonePeriods(blockPeriods);
        const nextPeriod = { start, end, notes };

        if (editingIndex !== null) {
            blockPeriods[editingIndex] = nextPeriod;
        } else {
            blockPeriods.push(nextPeriod);
        }

        const originalLabel = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving...</span>';
        submitButton.disabled = true;

        debugLog('Saving block periods', blockPeriods);

        try {
            await persistBlockPeriods();
            syncHiddenInput();
            closeModal();
            notify('success', 'Block Period Saved', editingIndex !== null ? 'Block period updated successfully.' : 'Block period added successfully.');
        } catch (error) {
            console.error('Block period save failed:', error);
            blockPeriods = previousPeriods;
            showModalError(error?.message || 'Failed to save block period. Please try again.');
            notify('error', 'Save Failed', error?.message || 'Failed to save block period. Please try again.', false);
        } finally {
            submitButton.innerHTML = originalLabel;
            submitButton.disabled = false;
        }
    });

    container.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit]');
        if (editButton) {
            openModal('edit', Number.parseInt(editButton.getAttribute('data-edit'), 10));
            return;
        }

        const deleteButton = event.target.closest('[data-delete]');
        if (!deleteButton) {
            return;
        }

        const index = Number.parseInt(deleteButton.getAttribute('data-delete'), 10);
        if (!window.confirm('Are you sure you want to delete this blocked period?')) {
            return;
        }

        const previousPeriods = clonePeriods(blockPeriods);
        blockPeriods.splice(index, 1);

        try {
            await persistBlockPeriods();
            syncHiddenInput();
            notify('success', 'Block Period Deleted', 'Block period deleted successfully.');
        } catch (error) {
            console.error('Block period delete failed:', error);
            blockPeriods = previousPeriods;
            renderBlockPeriods();
            notify('error', 'Delete Failed', error?.message || 'Failed to delete block period. Please try again.', false);
        }
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    renderBlockPeriods();
}

function initDatabaseSettingsTab(root) {
    const backupToggle = document.getElementById('backup-enabled-toggle');
    if (!backupToggle || backupToggle.dataset.initialized === 'true') {
        return;
    }

    backupToggle.dataset.initialized = 'true';

    const createBackupBtn = document.getElementById('create-backup-btn');
    const viewBackupsBtn = document.getElementById('view-backups-btn');
    const backupProgress = document.getElementById('backup-progress');
    const backupError = document.getElementById('backup-error');
    const backupSuccess = document.getElementById('backup-success');
    const lastBackupDetails = document.getElementById('last-backup-details');
    const backupListModal = document.getElementById('backup-list-modal');
    const closeBackupModal = document.getElementById('close-backup-modal');
    const backupListContent = document.getElementById('backup-list-content');
    const dbInfoType = document.getElementById('db-info-type');
    const dbInfoName = document.getElementById('db-info-name');
    const dbInfoHost = document.getElementById('db-info-host');

    if (!createBackupBtn || !viewBackupsBtn || !backupProgress || !backupError || !backupSuccess || !lastBackupDetails || !backupListModal || !backupListContent) {
        return;
    }

    const apiBase = root.dataset.databaseBackupApiUrl;

    const hideMessages = () => {
        backupError.classList.add('hidden');
        backupSuccess.classList.add('hidden');
    };

    const showError = (message) => {
        backupError.textContent = message;
        backupError.classList.remove('hidden');
    };

    const showSuccess = (message) => {
        backupSuccess.textContent = message;
        backupSuccess.classList.remove('hidden');
    };

    const setModalOpen = (open) => {
        backupListModal.classList.toggle('hidden', !open);
        backupListModal.classList.toggle('flex', open);
    };

    const updateLastBackupDisplay = (lastBackup) => {
        if (lastBackup?.time && lastBackup?.filename) {
            lastBackupDetails.innerHTML = `
                <div class="flex-1">
                    <p class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(lastBackup.time)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        ${escapeHtml(lastBackup.filename)}
                        <span class="text-gray-400">(${escapeHtml(lastBackup.size_formatted || 'Unknown size')})</span>
                    </p>
                </div>
                <a href="${apiBase}/download/${encodeURIComponent(lastBackup.filename)}"
                   data-no-spa="true"
                   download="${escapeHtml(lastBackup.filename)}"
                   class="text-blue-600 hover:text-blue-700 dark:text-blue-400 text-sm inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">download</span>
                    Download
                </a>
            `;
            return;
        }

        lastBackupDetails.innerHTML = '<span class="text-sm text-gray-500 dark:text-gray-400">No backups yet</span>';
    };

    const fetchJson = async (url, options = {}, fallbackMessage = 'Request failed') => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...getCsrfHeaders(),
                ...(options.headers || {}),
            },
        });

        updateCsrfTokenFromResponse(response);

        if (!response.ok) {
            throw new Error(await parseErrorMessage(response, fallbackMessage));
        }

        return unwrapApiData(await response.json());
    };

    const fetchLatestBackupFromList = async () => {
        try {
            const data = await fetchJson(`${apiBase}/list`, {}, 'Failed to load backups');
            if (data.backups?.length > 0) {
                const latest = data.backups[0];
                updateLastBackupDisplay({
                    time: latest.created,
                    filename: latest.filename,
                    size_formatted: latest.size_formatted,
                });
                return;
            }
        } catch (_) {
            // Fall through to empty state.
        }

        updateLastBackupDisplay(null);
    };

    const loadBackupStatus = async () => {
        try {
            const data = await fetchJson(`${apiBase}/status`, {}, 'Failed to load backup status');

            backupToggle.checked = Boolean(data.backup_enabled);
            createBackupBtn.disabled = !data.backup_enabled;

            if (dbInfoType) dbInfoType.textContent = data.database?.type || 'Unknown';
            if (dbInfoName) dbInfoName.textContent = data.database?.name || 'Unknown';
            if (dbInfoHost) dbInfoHost.textContent = data.database?.host || 'Unknown';

            if (data.last_backup?.time && data.last_backup?.filename) {
                updateLastBackupDisplay(data.last_backup);
            } else {
                await fetchLatestBackupFromList();
            }
        } catch (error) {
            console.error('Failed to load backup status:', error);
            if (dbInfoType) dbInfoType.textContent = 'Error loading';
            if (dbInfoName) dbInfoName.textContent = 'Error loading';
            if (dbInfoHost) dbInfoHost.textContent = 'Error loading';
        }
    };

    const renderBackupList = (backups) => {
        if (!Array.isArray(backups) || backups.length === 0) {
            backupListContent.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <span class="material-symbols-outlined text-4xl mb-2">folder_off</span>
                    <p>No backups found</p>
                </div>
            `;
            return;
        }

        backupListContent.innerHTML = `
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                ${backups.map((backup) => `
                    <div class="py-3 flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">${escapeHtml(backup.filename)}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                ${escapeHtml(backup.created)} • ${escapeHtml(backup.size_formatted)}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="${apiBase}/download/${encodeURIComponent(backup.filename)}"
                               data-no-spa="true"
                               download="${escapeHtml(backup.filename)}"
                               class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition"
                               title="Download">
                                <span class="material-symbols-outlined text-xl">download</span>
                            </a>
                            <button type="button"
                                    class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition delete-backup-btn"
                                    data-filename="${escapeHtml(backup.filename)}"
                                    title="Delete">
                                <span class="material-symbols-outlined text-xl">delete</span>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    };

    const loadBackupsIntoModal = async () => {
        backupListContent.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <div class="loading-spinner mx-auto mb-2"></div>
                Loading backups...
            </div>
        `;

        try {
            const data = await fetchJson(`${apiBase}/list`, {}, 'Failed to load backups');
            renderBackupList(data.backups || []);
        } catch (error) {
            console.error('Failed to load backups:', error);
            backupListContent.innerHTML = `
                <div class="text-center py-8 text-red-500">
                    <span class="material-symbols-outlined text-4xl mb-2">error</span>
                    <p>Failed to load backups</p>
                </div>
            `;
        }
    };

    backupToggle.addEventListener('change', async () => {
        const enabled = backupToggle.checked;

        try {
            await fetchJson(`${apiBase}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ enabled }),
            }, 'Failed to update backup setting');

            createBackupBtn.disabled = !enabled;
        } catch (error) {
            console.error('Failed to toggle backup:', error);
            backupToggle.checked = !enabled;
            showError(error.message || 'Failed to update backup setting');
        }
    });

    createBackupBtn.addEventListener('click', async () => {
        if (createBackupBtn.disabled) {
            return;
        }

        hideMessages();
        backupProgress.classList.remove('hidden');
        createBackupBtn.disabled = true;

        try {
            const data = await fetchJson(`${apiBase}/create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            }, 'Backup failed');

            updateLastBackupDisplay({
                time: data.timestamp,
                filename: data.filename,
                size_formatted: data.size_formatted,
            });
            showSuccess(`Backup created successfully: ${data.filename}`);
        } catch (error) {
            console.error('Backup failed:', error);
            showError(error.message || 'Failed to create backup');
        } finally {
            backupProgress.classList.add('hidden');
            createBackupBtn.disabled = !backupToggle.checked;
        }
    });

    viewBackupsBtn.addEventListener('click', async () => {
        setModalOpen(true);
        await loadBackupsIntoModal();
    });

    backupListContent.addEventListener('click', async (event) => {
        const deleteButton = event.target.closest('.delete-backup-btn');
        if (!deleteButton) {
            return;
        }

        const filename = deleteButton.dataset.filename;
        if (!filename || !window.confirm(`Delete backup "${filename}"?`)) {
            return;
        }

        try {
            await fetchJson(`${apiBase}/delete/${encodeURIComponent(filename)}`, {
                method: 'DELETE',
            }, 'Failed to delete backup');

            await loadBackupsIntoModal();
            await loadBackupStatus();
        } catch (error) {
            console.error('Delete failed:', error);
            window.alert(error.message || 'Failed to delete backup');
        }
    });

    closeBackupModal?.addEventListener('click', () => setModalOpen(false));

    backupListModal.addEventListener('click', (event) => {
        if (event.target === backupListModal) {
            setModalOpen(false);
        }
    });

    document.querySelectorAll('[data-tab="database"]').forEach((button) => {
        if (button.dataset.dbTabWired === 'true') {
            return;
        }

        button.dataset.dbTabWired = 'true';
        button.addEventListener('click', () => {
            loadBackupStatus();
        });
    });

    loadBackupStatus();
}

export function initSettingsPageEnhancements() {
    const root = document.getElementById('settings-page');
    if (!root) {
        return;
    }

    initSettingsFormEnhancements(root);
    initBlockedPeriodsUI(root);
    initDatabaseSettingsTab(root);
}
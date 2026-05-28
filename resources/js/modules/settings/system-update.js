/**
 * System Update tab — handles execute, rollback, and maintenance toggle.
 *
 * The upload form uses data-no-spa="true" and performs a full-page POST;
 * the browser lands back on /settings#system-update with validation flashdata.
 *
 * Execute / rollback / maintenance actions use apiRequest() (JSON), then
 * navigate explicitly because apiRequest() does not auto-follow redirects.
 */

import { apiRequest } from '../../core/api.js';

function navigate(dest) {
    const url = dest || '/settings#system-update';
    if (window.xsSPA?.navigate) {
        window.xsSPA.navigate(url, true, { forceReload: true });
    } else {
        window.location.assign(url);
    }
}

function toast(type, message) {
    window.XSNotify?.toast?.({ type, message });
}

async function jsonAction(url, body, confirmOpts) {
    if (confirmOpts) {
        const ok = await window.XSConfirm?.show(confirmOpts);
        if (!ok) return;
    }

    try {
        const { response, payload } = await apiRequest(url, { method: 'POST', body });
        if (!response.ok || !payload?.data?.success) {
            // BaseApiController::error() returns {error:{message,code}} at top level;
            // BaseApiController::ok() returns {data:{...}} — handle both shapes.
            const msg = payload?.data?.error
                || payload?.error?.message
                || payload?.data?.errors?.join(', ')
                || `Action failed (HTTP ${response.status}).`;
            toast('error', msg);
            return;
        }
        navigate(payload?.data?.redirect);
    } catch (err) {
        toast('error', 'Request failed: ' + err.message);
    }
}

export function initSystemUpdateTab(root) {
    const panel = root?.querySelector('#panel-system-update');
    if (!panel) return;

    // Execute button
    const executeBtn = panel.querySelector('#updater-execute-btn');
    if (executeBtn) {
        executeBtn.addEventListener('click', async () => {
            const version = executeBtn.dataset.version || 'this update';
            await jsonAction(
                '/admin/updater/execute',
                {},
                {
                    title:       'Apply Update',
                    message:     `Apply ${version}? The site will enter maintenance mode while files are replaced and migrations run. This cannot be undone automatically once extraction starts.`,
                    confirmText: 'Apply Update',
                    danger:      true,
                }
            );
        });
    }

    // Rollback button
    const rollbackBtn = panel.querySelector('#updater-rollback-btn');
    if (rollbackBtn) {
        rollbackBtn.addEventListener('click', async () => {
            const ts = rollbackBtn.dataset.timestamp || '';
            await jsonAction(
                '/admin/updater/rollback',
                { timestamp: ts },
                {
                    title:       'Rollback',
                    message:     'Restore app/, public/, and the database from the pre-update backup? This will NOT restore vendor/ or system/ — re-upload the previous release ZIP from GitHub Releases if needed.',
                    confirmText: 'Rollback',
                    danger:      true,
                }
            );
        });
    }

    // Maintenance ON
    const maintenanceOnBtn = panel.querySelector('#updater-maintenance-on');
    if (maintenanceOnBtn) {
        maintenanceOnBtn.addEventListener('click', async () => {
            await jsonAction('/admin/updater/maintenance', { enable: true }, {
                title:       'Enable Maintenance Mode',
                message:     'Put the site into maintenance mode? Non-admin visitors will see a 503 page.',
                confirmText: 'Enable',
            });
        });
    }

    // Maintenance OFF
    const maintenanceOffBtn = panel.querySelector('#updater-maintenance-off');
    if (maintenanceOffBtn) {
        maintenanceOffBtn.addEventListener('click', async () => {
            await jsonAction('/admin/updater/maintenance', { enable: false }, {
                title:       'Disable Maintenance Mode',
                message:     'Re-open the site to all visitors?',
                confirmText: 'Disable',
            });
        });
    }

    // Clear maintenance (emergency button in warning banner)
    const clearMaintenanceBtn = panel.querySelector('#updater-clear-maintenance');
    if (clearMaintenanceBtn) {
        clearMaintenanceBtn.addEventListener('click', async () => {
            await jsonAction('/admin/updater/maintenance', { enable: false }, {
                title:       'Clear Maintenance Mode',
                message:     'Clear maintenance mode and re-open the site? Only do this if you have confirmed the site is in a healthy state.',
                confirmText: 'Clear Maintenance',
                danger:      true,
            });
        });
    }
}

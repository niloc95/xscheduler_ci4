<?php
/**
 * Provider Staff Assignment Panel
 */

use CodeIgniter\I18n\Time;

$assignedStaff = $assignedStaff ?? [];
$availableStaff = $availableStaff ?? [];
$assignedIds = array_map(static fn($row) => $row['id'] ?? null, $assignedStaff);
$providerId = isset($providerId) && is_numeric($providerId) ? (int) $providerId : 0;
$missingProviderId = $providerId <= 0;
$csrfName = csrf_token();
$csrfValue = csrf_hash();
$listUrl = base_url('provider-staff/provider/' . (int) $providerId);
$assignUrl = base_url('provider-staff/assign');
$removeUrl = base_url('provider-staff/remove');
?>

<?php if ($missingProviderId): ?>
    <div class="mb-5 rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
        Staff assignments locked - Save this provider first, then return to assign staff members
    </div>
<?php endif; ?>

<div class="border-t border-gray-200 dark:border-gray-700 pt-6" data-provider-staff-manager data-provider-id="<?= esc($providerId) ?>" data-assign-url="<?= esc($assignUrl) ?>" data-remove-url="<?= esc($removeUrl) ?>" data-list-url="<?= esc($listUrl) ?>" data-csrf-name="<?= esc($csrfName) ?>" data-csrf-value="<?= esc($csrfValue) ?>">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Assigned Staff</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Team members who can access this provider's schedule</p>
        </div>
        <?php if ($canManageAssignments ?? false): ?>
            <button type="button" data-refresh-list class="hidden"></button>
        <?php endif; ?>
    </div>

    <?php if ($canManageAssignments ?? false): ?>
        <div class="mb-5 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex flex-col md:flex-row md:items-end md:space-x-4 space-y-3 md:space-y-0">
                <div class="flex-1">
                    <label for="provider-staff-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Staff Member</label>
                    <select id="provider-staff-select" data-staff-select class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Choose staff or receptionist</option>
                        <?php foreach ($availableStaff as $staff): ?>
                            <?php $isAssigned = in_array($staff['id'], $assignedIds, true); ?>
                            <option value="<?= (int) $staff['id'] ?>" <?= $isAssigned ? 'disabled' : '' ?>>
                                <?= esc($staff['name'] ?? 'Unnamed') ?><?= $isAssigned ? ' (already assigned)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="button" id="provider-staff-assign-btn" data-assign-btn class="btn btn-primary inline-flex items-center px-4 py-2" disabled aria-disabled="true">
                        <span class="material-symbols-outlined mr-2">person_add</span>
                        Assign Staff
                    </button>
                </div>
            </div>
        </div>
    <?php elseif (empty($assignedStaff)): ?>
        <div class="mb-5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-100">No staff assigned yet. An administrator can add staff from this page.</p>
        </div>
    <?php endif; ?>

    <div data-assigned-wrapper class="space-y-3">
        <?php if (!empty($assignedStaff)): ?>
            <?php foreach ($assignedStaff as $staff): ?>
                <div class="flex items-start justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800" data-staff-row data-staff-id="<?= (int) ($staff['id'] ?? 0) ?>">
                    <div>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-blue-500 flex items-center justify-center text-white font-semibold">
                                <?= strtoupper(substr($staff['name'] ?? 'S', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center space-x-2">
                                    <span><?= esc($staff['name'] ?? 'Unknown Staff') ?></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                        <?= esc(ucfirst($staff['role'] ?? 'staff')) ?>
                                    </span>
                                </div>
                                <?php if (!empty($staff['email'])): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400"><?= esc($staff['email']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($staff['phone'])): ?>
                                    <div class="text-sm text-gray-400 dark:text-gray-500"><?= esc($staff['phone']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($staff['assigned_at'])): ?>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Assigned <?= esc(Time::parse($staff['assigned_at'])->humanize()) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($canManageAssignments ?? false): ?>
                        <button type="button" class="btn btn-secondary inline-flex items-center px-3 py-2" data-remove-btn data-staff-id="<?= (int) ($staff['id'] ?? 0) ?>">
                            <span class="material-symbols-outlined mr-1 text-base">person_off</span>
                            Remove
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div data-empty-state class="p-4 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-center text-gray-500 dark:text-gray-400">
                No staff assigned to this provider yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    const container = document.querySelector('[data-provider-staff-manager]');
    if (!container) return;

    const providerId = Number(container.dataset.providerId || '0');
    const assignUrl = container.dataset.assignUrl;
    const removeUrl = container.dataset.removeUrl;
    const listUrl = container.dataset.listUrl;
    let csrfName = container.dataset.csrfName;
    let csrfValue = container.dataset.csrfValue;

    const assignBtn = container.querySelector('[data-assign-btn]');
    const staffSelect = container.querySelector('[data-staff-select]');
    const assignedWrapper = container.querySelector('[data-assigned-wrapper]');

    if (!providerId) {
        if (assignBtn) {
            assignBtn.disabled = true;
            assignBtn.setAttribute('aria-disabled', 'true');
        }
        return;
    }

    // Enable/disable assign button based on dropdown selection
    const updateAssignButtonState = () => {
        if (!assignBtn) {
            return;
        }

        const hasSelection = Boolean(staffSelect && staffSelect.value && Number(staffSelect.value) > 0);
        assignBtn.disabled = !hasSelection;
        assignBtn.setAttribute('aria-disabled', assignBtn.disabled ? 'true' : 'false');
    };

    if (assignBtn && staffSelect) {
        staffSelect.addEventListener('change', function() {
            updateAssignButtonState();
        });
        updateAssignButtonState(); // Initial state
    }

    function toast(opts) {
        if (window.XSNotify && typeof window.XSNotify.toast === 'function') {
            window.XSNotify.toast(opts);
        } else if (window.console && typeof window.console.log === 'function') {
            console.log('Toast:', opts);
        }
    }

    function updateCsrfInputs(newValue) {
        if (!newValue) return;
        csrfValue = newValue;
        const csrfInputs = document.querySelectorAll(`input[name="${csrfName}"]`);
        csrfInputs.forEach((input) => { input.value = csrfValue; });
    }

    function updateCsrfFromResponse(response) {
        const newToken = response.headers.get('X-CSRF-TOKEN');
        updateCsrfInputs(newToken);
    }

    function html(strings, ...values) {
        return strings.reduce((acc, str, idx) => acc + str + (values[idx] ?? ''), '');
    }

    function formatAssignedAt(value) {
        if (!value) return '';
        const normalized = value.replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return value;

        const now = new Date();
        const diffMs = now - date;
        const diffMinutes = Math.round(diffMs / 60000);

        if (diffMinutes < 1) return 'just now';
        if (diffMinutes < 60) return `${diffMinutes} minute${diffMinutes === 1 ? '' : 's'} ago`;

        const diffHours = Math.round(diffMinutes / 60);
        if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;

        const diffDays = Math.round(diffHours / 24);
        if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;

        return date.toLocaleDateString();
    }

    function renderStaff(items) {
        if (!assignedWrapper) return;
        if (!Array.isArray(items) || !items.length) {
            assignedWrapper.innerHTML = '<div data-empty-state class="p-4 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-center text-gray-500 dark:text-gray-400">No staff assigned to this provider yet.</div>';
            if (staffSelect) {
                Array.from(staffSelect.options).forEach((opt) => {
                    opt.disabled = false;
                    if (!opt.value) {
                        opt.textContent = opt.textContent.replace(/ \(already assigned\)$/i, '');
                    }
                });
            }
            updateAssignButtonState();
            return;
        }

        const markup = items.map((staff) => {
            const initial = (staff.name || 'S').trim().charAt(0).toUpperCase();
            const role = (staff.role || 'staff').charAt(0).toUpperCase() + (staff.role || 'staff').slice(1);
            const email = staff.email ? `<div class="text-sm text-gray-500 dark:text-gray-400">${staff.email}</div>` : '';
            const phone = staff.phone ? `<div class="text-sm text-gray-400 dark:text-gray-500">${staff.phone}</div>` : '';
            const assignedAtLabel = formatAssignedAt(staff.assigned_at);
            const assignedAt = assignedAtLabel ? `<div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Assigned ${assignedAtLabel}</div>` : '';

            return html`<div class="flex items-start justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800" data-staff-row data-staff-id="${staff.id}">
                <div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-blue-500 flex items-center justify-center text-white font-semibold">${initial || ''}</div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center space-x-2">
                                <span>${staff.name || 'Unknown Staff'}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">${role}</span>
                            </div>
                            ${email}${phone}${assignedAt}
                        </div>
                    </div>
                </div>
                ${assignBtn ? `<button type="button" class="btn btn-secondary inline-flex items-center px-3 py-2" data-remove-btn data-staff-id="${staff.id}"><span class="material-symbols-outlined mr-1 text-base">person_off</span>Remove</button>` : ''}
            </div>`;
        }).join('');

        assignedWrapper.innerHTML = markup;

        if (staffSelect) {
            const assignedIds = new Set(items.map((item) => String(item.id)));
            Array.from(staffSelect.options).forEach((opt) => {
                if (!opt.value) return;
                const assigned = assignedIds.has(opt.value);
                opt.disabled = assigned;
                if (assigned) {
                    opt.textContent = `${opt.textContent.replace(/ \(already assigned\)$/i, '')} (already assigned)`;
                } else {
                    opt.textContent = opt.textContent.replace(/ \(already assigned\)$/i, '');
                }
            });
            if (staffSelect.value) {
                var selectedOption = staffSelect.options[staffSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled) {
                    staffSelect.value = '';
                }
            }
            updateAssignButtonState();
        }
    }

    async function refreshList() {
        if (!listUrl) return;
        try {
            const response = await fetch(listUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            updateCsrfFromResponse(response);
            if (!response.ok) throw new Error('Request failed');
            const payload = await response.json();
            const staff = payload.staff ?? [];
            if (payload && payload.csrfToken) {
                updateCsrfInputs(payload.csrfToken);
            }
            renderStaff(staff);
        } catch (error) {
            toast({ type: 'error', title: 'Unable to refresh staff list', message: error.message });
        }
    }

    if (assignBtn && staffSelect) {
        assignBtn.addEventListener('click', async () => {
            const staffId = Number(staffSelect.value || '0');
            if (!staffId) {
                toast({ type: 'warning', title: 'Select staff', message: 'Please choose a staff member to assign.' });
                return;
            }

            const formData = new FormData();
            formData.append('provider_id', String(providerId));
            formData.append('staff_id', String(staffId));
            formData.append(csrfName, csrfValue);

            try {
                assignBtn.disabled = true;
                const response = await fetch(assignUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    });
                updateCsrfFromResponse(response);
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload.message || 'Failed to assign staff');
                }
                if (payload && payload.csrfToken) {
                    updateCsrfInputs(payload.csrfToken);
                }
                toast({ type: 'success', title: 'Staff assigned', message: 'Staff member now has access to this provider.' });
                const staff = payload.staff ?? [];
                staffSelect.value = '';
                renderStaff(staff);
                updateAssignButtonState();
            } catch (error) {
                toast({ type: 'error', title: 'Assignment failed', message: error.message });
            } finally {
                assignBtn.disabled = false;
                updateAssignButtonState();
            }
        });
    }

    container.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-btn]');
        if (!button) return;

        if (typeof window.confirm === 'function' && !window.confirm('Remove this assignment?')) {
            return;
        }

        const staffId = Number(button.dataset.staffId || '0');
        if (!staffId) return;

        const formData = new FormData();
        formData.append('provider_id', String(providerId));
        formData.append('staff_id', String(staffId));
        formData.append(csrfName, csrfValue);

        try {
            button.disabled = true;
            const response = await fetch(removeUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            updateCsrfFromResponse(response);
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error((payload && payload.message) || 'Failed to remove assignment');
            }
            if (payload && payload.csrfToken) {
                updateCsrfInputs(payload.csrfToken);
            }
            toast({ type: 'success', title: 'Staff removed', message: 'Staff member no longer has access to this provider.' });
            const staff = (payload && payload.staff) ? payload.staff : [];
            renderStaff(staff);
        } catch (error) {
            toast({ type: 'error', title: 'Removal failed', message: error.message });
        } finally {
            button.disabled = false;
        }
    });
})();
</script>

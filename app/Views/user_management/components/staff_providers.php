<?php
/**
 * Staff Provider Assignment Panel
 * 
 * Displays providers assigned to a staff member.
 * Allows admins to add/remove provider assignments.
 */

use CodeIgniter\I18n\Time;

$assignedProviders = $assignedProviders ?? [];
$availableProviders = $availableProviders ?? [];
$assignedIds = array_map(static fn($row) => $row['id'] ?? null, $assignedProviders);
$staffId = isset($staffId) && is_numeric($staffId) ? (int) $staffId : 0;
$missingStaffId = $staffId <= 0;

$csrfName = csrf_token();
$csrfValue = csrf_hash();
$listUrl = base_url('staff-providers/staff/' . (int) $staffId);
$assignUrl = base_url('staff-providers/assign');
$removeUrl = base_url('staff-providers/remove');
?>

<?php if ($missingStaffId): ?>
    <div class="mb-5 rounded-lg border border-blue-300 bg-blue-50 dark:border-blue-600 dark:bg-blue-900/30 p-4 text-sm text-blue-800 dark:text-blue-100">
        <div class="font-medium mb-1">Provider assignments will be available after saving</div>
        <p>Save this staff member first, then you can assign them to providers.</p>
    </div>
<?php else: ?>
<div class="border-t border-gray-200 dark:border-gray-700 pt-6" data-staff-providers-manager data-staff-id="<?= esc($staffId) ?>" data-assign-url="<?= esc($assignUrl) ?>" data-remove-url="<?= esc($removeUrl) ?>" data-list-url="<?= esc($listUrl) ?>" data-csrf-name="<?= esc($csrfName) ?>" data-csrf-value="<?= esc($csrfValue) ?>">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Assigned Providers</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Providers this staff member can assist</p>
        </div>
    </div>

    <?php if ($canManageAssignments ?? false): ?>
        <div class="mb-5 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex flex-col md:flex-row md:items-end md:space-x-4 space-y-3 md:space-y-0">
                <input type="hidden" id="staffId" value="<?= (int) $staffId ?>">
                <input type="hidden" data-csrf name="<?= esc($csrfName) ?>" value="<?= esc($csrfValue) ?>">
                <div class="flex-1">
                    <label for="staff-provider-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Provider</label>
                    <select id="staff-provider-select" data-provider-select class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Choose a provider</option>
                        <?php foreach ($availableProviders as $provider): ?>
                            <?php $isAssigned = in_array($provider['id'], $assignedIds, true); ?>
                            <option value="<?= (int) $provider['id'] ?>" <?= $isAssigned ? 'disabled' : '' ?>>
                                <?= esc($provider['name'] ?? 'Unnamed') ?><?= $isAssigned ? ' (already assigned)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="button" id="staff-provider-assign-btn" data-assign-provider class="btn btn-primary inline-flex items-center px-4 py-2" aria-disabled="true" disabled>
                        <span class="material-symbols-outlined mr-2">person_add</span>
                        Assign Provider
                    </button>
                </div>
            </div>
        </div>
    <?php elseif (empty($assignedProviders)): ?>
        <div class="mb-5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-100">No providers assigned yet. An administrator can add providers from this page.</p>
        </div>
    <?php endif; ?>

    <div data-assigned-wrapper class="space-y-3">
        <?php if (!empty($assignedProviders)): ?>
            <?php foreach ($assignedProviders as $provider): ?>
                <div class="flex items-start justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800" data-provider-row data-provider-id="<?= (int) ($provider['id'] ?? 0) ?>">
                    <div>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white font-semibold">
                                <?= strtoupper(substr($provider['name'] ?? 'P', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center space-x-2">
                                    <span><?= esc($provider['name'] ?? 'Unknown Provider') ?></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-100 dark:bg-blue-700 text-blue-700 dark:text-blue-200">
                                        Provider
                                    </span>
                                </div>
                                <?php if (!empty($provider['email'])): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400"><?= esc($provider['email']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($provider['assigned_at'])): ?>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Assigned <?= esc(Time::parse($provider['assigned_at'])->humanize()) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($canManageAssignments ?? false): ?>
                        <button type="button" class="btn btn-secondary inline-flex items-center px-3 py-2" data-remove-btn data-provider-id="<?= (int) ($provider['id'] ?? 0) ?>">
                            <span class="material-symbols-outlined mr-1 text-base">person_off</span>
                            Remove
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div data-empty-state class="p-4 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-center text-gray-500 dark:text-gray-400">
                No providers assigned to this staff member yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    const containers = document.querySelectorAll('[data-staff-providers-manager]');
    if (!containers.length) return;

    containers.forEach(function(container){
        const staffIdInput = container.querySelector('#staffId');
        const csrfInput = container.querySelector('input[data-csrf]');

        const assignUrl = container.dataset.assignUrl;
        const removeUrl = container.dataset.removeUrl;
        const listUrl = container.dataset.listUrl;

        let csrfName = csrfInput && csrfInput.name ? csrfInput.name : container.dataset.csrfName;
        let csrfValue = csrfInput && csrfInput.value ? csrfInput.value : container.dataset.csrfValue;

        const assignBtn = container.querySelector('[data-assign-provider]');
        const providerSelect = container.querySelector('[data-provider-select]');
        const assignedWrapper = container.querySelector('[data-assigned-wrapper]');

        const staffId = Number((staffIdInput && staffIdInput.value) ? staffIdInput.value : container.dataset.staffId || '0');

        function toast(opts) {
            if (window.XSNotify && typeof window.XSNotify.toast === 'function') {
                window.XSNotify.toast(opts);
            } else if (window.console && typeof window.console.log === 'function') {
                console.log('Toast:', opts);
            }
        }

        if (!staffId) {
            if (assignBtn) {
                assignBtn.disabled = true;
                assignBtn.setAttribute('aria-disabled', 'true');
            }
        }

        function updateCsrfInputs(newValue) {
            if (!newValue) return;
            csrfValue = newValue;
            if (csrfInput) {
                csrfInput.value = csrfValue;
            }
            container.dataset.csrfValue = csrfValue;
            const csrfInputs = document.querySelectorAll('input[name="' + csrfName + '"]');
            csrfInputs.forEach(function(input) { input.value = csrfValue; });
        }

        function updateCsrfFromResponse(response) {
            const newToken = response.headers.get('X-CSRF-TOKEN');
            updateCsrfInputs(newToken);
        }

        function updateAssignButtonState() {
            if (!assignBtn) {
                return;
            }
            if (!providerSelect || !staffId) {
                assignBtn.disabled = true;
                assignBtn.setAttribute('aria-disabled', 'true');
                return;
            }

            const providerId = Number(providerSelect.value || '0');
            const disabled = !providerId || providerId === 0;
            assignBtn.disabled = disabled;
            assignBtn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        }

        function html(strings) {
            var values = Array.prototype.slice.call(arguments, 1);
            return strings.reduce(function(acc, str, idx) {
                return acc + str + (values[idx] || '');
            }, '');
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
            if (diffMinutes < 60) return diffMinutes + ' minute' + (diffMinutes === 1 ? '' : 's') + ' ago';

            const diffHours = Math.round(diffMinutes / 60);
            if (diffHours < 24) return diffHours + ' hour' + (diffHours === 1 ? '' : 's') + ' ago';

            const diffDays = Math.round(diffHours / 24);
            if (diffDays < 7) return diffDays + ' day' + (diffDays === 1 ? '' : 's') + ' ago';

            return date.toLocaleDateString();
        }

        function renderProviders(items) {
            if (!assignedWrapper) return;
            if (!Array.isArray(items) || !items.length) {
                assignedWrapper.innerHTML = '<div data-empty-state class="p-4 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-center text-gray-500 dark:text-gray-400">No providers assigned to this staff member yet.</div>';
                if (providerSelect) {
                    Array.from(providerSelect.options).forEach(function(opt) {
                        opt.disabled = false;
                        if (!opt.value) {
                            opt.textContent = opt.textContent.replace(/ \(already assigned\)$/i, '');
                        }
                    });
                }
                updateAssignButtonState();
                return;
            }

            const markup = items.map(function(provider) {
                const initial = (provider.name || 'P').trim().charAt(0).toUpperCase();
                const email = provider.email ? '<div class="text-sm text-gray-500 dark:text-gray-400">' + provider.email + '</div>' : '';
                const assignedAtLabel = formatAssignedAt(provider.assigned_at);
                const assignedAt = assignedAtLabel ? '<div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Assigned ' + assignedAtLabel + '</div>' : '';

                return '<div class="flex items-start justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800" data-provider-row data-provider-id="' + provider.id + '">' +
                    '<div>' +
                        '<div class="flex items-center space-x-3">' +
                            '<div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white font-semibold">' + initial + '</div>' +
                            '<div>' +
                                '<div class="font-medium text-gray-900 dark:text-gray-100 flex items-center space-x-2">' +
                                    '<span>' + (provider.name || 'Unknown Provider') + '</span>' +
                                    '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-100 dark:bg-blue-700 text-blue-700 dark:text-blue-200">Provider</span>' +
                                '</div>' +
                                email + assignedAt +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    (assignBtn ? '<button type="button" class="btn btn-secondary inline-flex items-center px-3 py-2" data-remove-btn data-provider-id="' + provider.id + '"><span class="material-symbols-outlined mr-1 text-base">person_off</span>Remove</button>' : '') +
                '</div>';
            }).join('');

            assignedWrapper.innerHTML = markup;

            if (providerSelect) {
                const assignedIds = new Set(items.map(function(item) { return String(item.id); }));
                Array.from(providerSelect.options).forEach(function(opt) {
                    if (!opt.value) return;
                    const assigned = assignedIds.has(opt.value);
                    opt.disabled = assigned;
                    if (assigned) {
                        opt.textContent = opt.textContent.replace(/ \(already assigned\)$/i, '') + ' (already assigned)';
                    } else {
                        opt.textContent = opt.textContent.replace(/ \(already assigned\)$/i, '');
                    }
                });
                if (providerSelect.value) {
                    var selectedOption = providerSelect.options[providerSelect.selectedIndex];
                    if (selectedOption && selectedOption.disabled) {
                        providerSelect.value = '';
                    }
                }
                updateAssignButtonState();
            }
        }

        if (assignBtn && providerSelect && staffId) {
            providerSelect.addEventListener('change', updateAssignButtonState);
            updateAssignButtonState();

            assignBtn.addEventListener('click', function() {
                const providerId = Number(providerSelect.value || '0');
                if (!providerId) {
                    toast({ type: 'warning', title: 'Select provider', message: 'Please choose a provider to assign.' });
                    return;
                }

                const formData = new FormData();
                formData.append('staff_id', String(staffId));
                formData.append('provider_id', String(providerId));
                formData.append(csrfName, csrfValue);

                assignBtn.disabled = true;
                assignBtn.setAttribute('aria-disabled', 'true');

                fetch(assignUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfValue,
                    },
                })
                .then(function(response) {
                    updateCsrfFromResponse(response);
                    return response.json().then(function(payload) {
                        if (!response.ok) {
                            throw new Error(payload.message || 'Failed to assign provider');
                        }
                        return payload;
                    });
                })
                .then(function(payload) {
                    if (payload && payload.csrfToken) {
                        updateCsrfInputs(payload.csrfToken);
                    }
                    const successMessage = (payload && payload.message) ? payload.message : 'Provider now accessible to this staff member.';
                    toast({ type: 'success', title: 'Provider assigned', message: successMessage });
                    const providers = payload && payload.providers ? payload.providers : [];
                    if (providerSelect) {
                        providerSelect.value = '';
                    }
                    renderProviders(providers);
                })
                .catch(function(error) {
                    toast({ type: 'error', title: 'Assignment failed', message: error.message });
                })
                .finally(function() {
                    assignBtn.disabled = false;
                    updateAssignButtonState();
                });
            });
        } else {
            updateAssignButtonState();
        }

        container.addEventListener('click', function(event) {
            const button = event.target.closest('[data-remove-btn]');
            if (!button) return;

            if (typeof window.confirm === 'function' && !window.confirm('Remove this assignment?')) {
                return;
            }

            const providerId = Number(button.dataset.providerId || '0');
            if (!providerId) return;

            const formData = new FormData();
            formData.append('staff_id', String(staffId));
            formData.append('provider_id', String(providerId));
            formData.append(csrfName, csrfValue);

            button.disabled = true;
            fetch(removeUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfValue,
                },
            })
            .then(function(response) {
                updateCsrfFromResponse(response);
                return response.json().catch(function() { return {}; }).then(function(payload) {
                    if (!response.ok) {
                        throw new Error(payload && payload.message ? payload.message : 'Failed to remove assignment');
                    }
                    if (payload && payload.csrfToken) {
                        updateCsrfInputs(payload.csrfToken);
                    }
                    return payload;
                });
            })
            .then(function(payload) {
                const message = (payload && payload.message) ? payload.message : 'Provider no longer accessible to this staff member.';
                toast({ type: 'success', title: 'Provider removed', message: message });
                const providers = payload && payload.providers ? payload.providers : [];
                renderProviders(providers);
            })
            .catch(function(error) {
                toast({ type: 'error', title: 'Removal failed', message: error.message });
            })
            .finally(function() {
                button.disabled = false;
                updateAssignButtonState();
            });
        });
    });
})();
</script>
<?php endif; // End of !$missingStaffId check ?>

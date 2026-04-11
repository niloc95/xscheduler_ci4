<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>User Management<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Manage system users and their permissions<?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
    <!-- Flash Messages -->
    <?= $this->include('components/ui/flash-messages') ?>

    <!-- Role Filter Cards (replicated dashboard layout) -->
    <div class="mb-6">
        <div id="role-user-cards" class="grid grid-cols-2 md:grid-cols-5 gap-4" aria-live="polite"></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- Users Table (main container starts here) -->
    <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="mb-6">
            <div class="flex flex-col items-end space-y-2 text-right">
                <h2 id="users-dynamic-title" class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Total Users</h2>
               
                <?php if (($canCreateAdmin ?? false) || ($canCreateProvider ?? false) || ($canCreateStaff ?? false)): ?>
                <?= view('components/button', [
                    'tag' => 'a',
                    'href' => base_url('user-management/create'),
                    'label' => 'Add New User',
                    'icon' => 'person_add',
                    'variant' => 'filled',
                ]) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" id="usersTable">
                <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-6 py-4 font-semibold">User</th>
                        <th class="px-6 py-4 font-semibold">Role</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Assignments</th>
                        <th class="px-6 py-4 font-semibold">Created</th>
                        <th class="px-6 py-4 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                <?php if (!empty($users)): foreach ($users as $user): ?>
                    <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?= esc($user['name'] ?? 'Unknown') ?></div>
                                    <div class="text-gray-500 dark:text-gray-400 text-sm"><?= esc($user['email'] ?? '') ?></div>
                                    <?php if (!empty($user['phone'])): ?>
                                        <div class="text-gray-400 dark:text-gray-500 text-xs"><?= esc($user['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php $displayRoles = get_user_display_roles($user); ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($displayRoles as $displayRole): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= get_role_badge_classes($displayRole) ?>">
                                        <?= esc(get_role_display_name($displayRole)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?= view('components/status_badge', [
                                'status' => ($user['is_active'] ?? true) ? 'active' : 'inactive',
                                'label' => ($user['is_active'] ?? true) ? 'Active' : 'Inactive',
                            ]) ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                            <?php if (!empty($user['assignments'])): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $names = explode(', ', $user['assignments']);
                                    foreach ($names as $name): 
                                    ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <?= esc($name) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 dark:text-gray-500 italic text-sm">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                            <?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '—' ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <a href="<?= base_url('user-management/edit/' . ($user['id'] ?? 0)) ?>" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit User">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                                <?php if (($user['id'] ?? null) !== session()->get('user_id')): ?>
                                    <?php if ($user['is_active'] ?? true): ?>
                                        <form method="post" action="<?= base_url('user-management/deactivate/' . ($user['id'] ?? 0)) ?>" onsubmit="return confirm('Deactivate this user?');" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="p-1 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" title="Deactivate User">
                                                <span class="material-symbols-outlined">person_cancel</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= base_url('user-management/activate/' . ($user['id'] ?? 0)) ?>" onsubmit="return confirm('Activate this user?');" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="p-1 text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300" title="Activate User">
                                                <span class="material-symbols-outlined">person_add</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                        <button type="button"
                                                class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                                title="Delete User"
                                                data-delete-user-id="<?= (int) ($user['id'] ?? 0) ?>"
                                                data-delete-user-name="<?= esc($user['name'] ?? 'Unknown', 'attr') ?>"
                                                data-delete-user-role="<?= esc($user['role'] ?? '', 'attr') ?>">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="users-error" class="hidden mt-4 text-sm text-red-600 dark:text-red-400"></div>
    </div>

    <div id="delete-user-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-black/50" data-delete-modal-close></div>
        <div class="relative mx-auto mt-12 w-full max-w-2xl rounded-lg bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete User</h3>
                <button type="button" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" data-delete-modal-close>
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4" id="delete-modal-content"></div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 px-4 py-2 text-sm border border-outline text-on-surface hover:bg-surface-variant" data-delete-modal-close>
                    <span>Cancel</span>
                </button>
                <button type="button" id="delete-user-confirm-btn" class="inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 px-4 py-2 text-sm bg-error text-on-error hover:bg-error-600 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <span class="material-symbols-outlined text-base">delete</span>
                    <span>Delete User</span>
                </button>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function(){
    const ROLE_DEFS = [
        { key: 'total', label: 'Total Users', icon: 'groups' },
        { key: 'admins', label: 'Admins', icon: 'shield_person' },
        { key: 'providers', label: 'Providers', icon: 'badge' },
        { key: 'staff', label: 'Staff', icon: 'support_agent' }
    ];
    const roleMap = { total: 'all', admins: 'admin', providers: 'provider', staff: 'staff' };
    const state = { activeRole: 'total', initialized: false, lastCounts: null, deleteTarget: null };

    const APP_BASE = '<?= rtrim(base_url(), '/') ?>/';
    const CURRENT_USER_ID = <?= (int) session()->get('user_id') ?>;
    const IS_ADMIN = <?= ($currentUser['role'] ?? '') === 'admin' ? 'true' : 'false' ?>;
    const CSRF_NAME = '<?= csrf_token() ?>';
    let csrfHash = '<?= csrf_hash() ?>';

    const modal = document.getElementById('delete-user-modal');
    const modalContent = document.getElementById('delete-modal-content');
    const confirmDeleteBtn = document.getElementById('delete-user-confirm-btn');

    function baseUrl(path) {
        return APP_BASE + String(path || '').replace(/^\/+/, '');
    }

    function showError(msg) {
        const el = document.getElementById('users-error');
        if (!el) return;
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    function clearError() {
        const el = document.getElementById('users-error');
        if (!el) return;
        el.textContent = '';
        el.classList.add('hidden');
    }

    function escapeHtml(value) {
        if (typeof window.xsEscapeHtml === 'function') return window.xsEscapeHtml(value);
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function roleLabel(role) {
        return ({ admin: 'Administrator', provider: 'Service Provider', staff: 'Staff Member', customer: 'Customer' })[role] || role;
    }

    function getDisplayRoles(user) {
        const rawRoles = Array.isArray(user.roles) ? user.roles : [];
        const roles = [...rawRoles];
        const primaryRole = String(user.role || '').trim();

        if (primaryRole && !roles.includes(primaryRole)) {
            roles.push(primaryRole);
        }

        const order = { admin: 1, provider: 2, staff: 3, customer: 4 };
        return [...new Set(roles.filter((role) => String(role || '').trim() !== ''))]
            .sort((left, right) => (order[left] || 99) - (order[right] || 99));
    }

    function renderRoleBadges(user) {
        const badgeColors = {
            admin: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            provider: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            staff: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            customer: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
        };
        const roles = getDisplayRoles(user);

        if (!roles.length) {
            return '<span class="text-gray-400 dark:text-gray-500 italic text-sm">—</span>';
        }

        return '<div class="flex flex-wrap gap-1">'
            + roles.map((role) => `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeColors[role] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'}">${escapeHtml(roleLabel(role))}</span>`).join('')
            + '</div>';
    }

    function fmtDate(value) {
        if (!value) return '';
        try {
            return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        } catch (_e) {
            return value;
        }
    }

    async function fetchCounts() {
        try {
            const response = await fetch(baseUrl('api/user-counts'));
            if (!response.ok) throw new Error('count request failed');
            const payload = await response.json();
            state.lastCounts = payload.counts || null;
            return state.lastCounts;
        } catch (_err) {
            state.lastCounts = {
                total: <?= (int)($stats['total'] ?? 0) ?>,
                admins: <?= (int)($stats['admins'] ?? 0) ?>,
                providers: <?= (int)($stats['providers'] ?? 0) ?>,
                staff: <?= (int)($stats['staff'] ?? 0) ?>
            };
            showError('Live counts unavailable, showing cached values.');
            return state.lastCounts;
        }
    }

    function userRow(user) {
        const role = user.role || '';
        const isActive = user.is_active ?? true;
        const name = user.name || '-';
        const email = user.email || '-';
        let assignmentsHtml = '<span class="text-gray-400 dark:text-gray-500 italic text-sm">None</span>';

        if (user.assignments) {
            const names = String(user.assignments).split(', ');
            assignmentsHtml = '<div class="flex flex-wrap gap-1">'
                + names.map((n) => `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">${escapeHtml(n)}</span>`).join('')
                + '</div>';
        }

        const canDelete = IS_ADMIN && Number(user.id) !== Number(CURRENT_USER_ID);
        const deleteAction = canDelete
            ? `<button type="button" class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="Delete User" data-delete-user-id="${Number(user.id)}" data-delete-user-name="${escapeHtml(name)}" data-delete-user-role="${escapeHtml(role)}"><span class="material-symbols-outlined">delete</span></button>`
            : '';

        return `<tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">`
            + `<td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100"><div class="flex items-center"><div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">${escapeHtml(name.substring(0, 1).toUpperCase())}</div><div><div class="font-medium">${escapeHtml(name)}</div><div class="text-gray-500 dark:text-gray-400 text-sm">${escapeHtml(email)}</div>${user.phone ? `<div class="text-gray-400 dark:text-gray-500 text-xs">${escapeHtml(user.phone)}</div>` : ''}</div></div></td>`
            + `<td class="px-6 py-4">${renderRoleBadges(user)}</td>`
            + `<td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${isActive ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}">${isActive ? 'Active' : 'Inactive'}</span></td>`
            + `<td class="px-6 py-4 text-gray-500 dark:text-gray-400">${assignmentsHtml}</td>`
            + `<td class="px-6 py-4 text-gray-500 dark:text-gray-400">${escapeHtml(fmtDate(user.created_at))}</td>`
            + `<td class="px-6 py-4"><div class="flex items-center space-x-2"><a href="${baseUrl('user-management/edit/' + user.id)}" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit User"><span class="material-symbols-outlined">edit</span></a>${deleteAction}</div></td>`
            + `</tr>`;
    }

    function updateHeaderTitle() {
        const titleEl = document.getElementById('users-dynamic-title');
        if (!titleEl) return;
        const labelMap = { total: 'Total Users', admins: 'Admin Users', providers: 'Provider Users', staff: 'Staff Users' };
        titleEl.textContent = labelMap[state.activeRole] || 'Total Users';
    }

    function highlightActive() {
        document.querySelectorAll('#role-user-cards .role-card').forEach((el) => {
            const active = el.dataset.role === state.activeRole;
            el.classList.toggle('ring-2', active);
            el.classList.toggle('ring-blue-500', active);
        });
    }

    function renderCards(counts) {
        const host = document.getElementById('role-user-cards');
        if (!host) return;
        host.innerHTML = '';
        ROLE_DEFS.forEach((def) => {
            const value = counts[def.key] ?? 0;
            const card = document.createElement('div');
            card.className = 'cursor-pointer rounded-lg p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group role-card';
            card.dataset.role = def.key;
            card.innerHTML = `<div class="flex items-center justify-between mb-2"><div><p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">${def.label}</p><p class="text-2xl font-semibold text-gray-800 dark:text-gray-100" data-count>${value}</p></div><div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300"><span class="material-symbols-outlined">${def.icon}</span></div></div>`;
            card.addEventListener('click', () => {
                if (state.activeRole === def.key) return;
                state.activeRole = def.key;
                highlightActive();
                updateHeaderTitle();
                loadUsers();
            });
            host.appendChild(card);
        });
        highlightActive();
        updateHeaderTitle();
    }

    function closeDeleteModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        state.deleteTarget = null;
        if (modalContent) modalContent.innerHTML = '';
        if (confirmDeleteBtn) confirmDeleteBtn.disabled = true;
    }

    async function submitDelete() {
        if (!state.deleteTarget) return;
        if (confirmDeleteBtn) confirmDeleteBtn.disabled = true;

        try {
            const response = await fetch(baseUrl('user-management/delete/' + state.deleteTarget.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ [CSRF_NAME]: csrfHash }).toString()
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Delete failed');
            }

            closeDeleteModal();
            clearError();
            await loadUsers();
            await refreshCountsPassive();
        } catch (err) {
            showError(err?.message || 'Unable to delete user right now.');
            if (confirmDeleteBtn) confirmDeleteBtn.disabled = false;
        }
    }

    async function openDeleteModal(button) {
        if (!modal || !modalContent || !confirmDeleteBtn) return;
        const userId = Number(button.dataset.deleteUserId || 0);
        if (!userId) return;

        const target = {
            id: userId,
            name: button.dataset.deleteUserName || 'Unknown',
            role: button.dataset.deleteUserRole || ''
        };

        state.deleteTarget = target;
        confirmDeleteBtn.disabled = true;
        modalContent.innerHTML = '<p class="text-sm text-gray-600 dark:text-gray-300">Loading impact details...</p>';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');

        try {
            const response = await fetch(baseUrl('user-management/delete-preview/' + userId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Unable to load deletion details.');
            }

            const targetInfo = payload.target || target;
            const impact = payload.impact || {};
            const warningItems = [
                impact.servicesLinked > 0 ? `Unlink ${impact.servicesLinked} provider service assignment(s).` : null,
                impact.staffLinked > 0 ? `Remove ${impact.staffLinked} provider/staff assignment(s).` : null,
                impact.providerLinks > 0 ? `Remove ${impact.providerLinks} staff/provider assignment(s).` : null,
                impact.appointmentsUpcoming > 0 ? `Cancel ${impact.appointmentsUpcoming} upcoming appointment(s) and preserve history.` : null
            ].filter(Boolean);

            const blocked = payload.allowed === false;
            const blockMessage = payload.blockCode === 'LAST_ADMIN'
                ? 'This is the last active administrator and cannot be deleted.'
                : (payload.blockCode === 'SELF_DELETE'
                    ? 'You cannot delete your own user account.'
                    : 'This user cannot be deleted.');

            const typedRequired = !!payload.typedConfirmationRequired && !blocked;
            modalContent.innerHTML = `
                <div class="space-y-3">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        You are about to permanently delete <strong>${escapeHtml(targetInfo.name || target.name)}</strong>
                        (${escapeHtml(roleLabel(targetInfo.role || target.role))}).
                    </p>
                    ${warningItems.length ? `<div class="rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/30 dark:border-amber-700 p-3"><p class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-1">Impact</p><ul class="text-sm text-amber-700 dark:text-amber-300 list-disc pl-5">${warningItems.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul></div>` : ''}
                    ${blocked ? `<div class="rounded-md border border-red-300 bg-red-50 dark:bg-red-900/30 dark:border-red-700 p-3"><p class="text-sm text-red-700 dark:text-red-300">${escapeHtml(blockMessage)}</p></div>` : ''}
                    ${typedRequired ? `<div><label for="delete-confirm-typed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type <code>DELETE</code> to confirm provider deletion</label><input id="delete-confirm-typed" type="text" class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm" autocomplete="off"></div>` : ''}
                </div>
            `;

            if (blocked) {
                confirmDeleteBtn.disabled = true;
                return;
            }

            if (!typedRequired) {
                confirmDeleteBtn.disabled = false;
                return;
            }

            const typedInput = document.getElementById('delete-confirm-typed');
            if (!typedInput) {
                confirmDeleteBtn.disabled = true;
                return;
            }

            typedInput.addEventListener('input', () => {
                confirmDeleteBtn.disabled = typedInput.value.trim() !== 'DELETE';
            });
        } catch (err) {
            modalContent.innerHTML = `<p class="text-sm text-red-600 dark:text-red-400">${escapeHtml(err?.message || 'Unable to load deletion details.')}</p>`;
            confirmDeleteBtn.disabled = true;
        }
    }

    function attachDeleteTriggers() {
        if (!IS_ADMIN) return;
        document.querySelectorAll('[data-delete-user-id]').forEach((button) => {
            if (button.dataset.boundDelete === '1') return;
            button.dataset.boundDelete = '1';
            button.addEventListener('click', () => openDeleteModal(button));
        });
    }

    async function loadUsers() {
        const body = document.getElementById('users-table-body');
        if (!body) return;

        const role = roleMap[state.activeRole] || 'all';
        const url = new URL(baseUrl('api/users'));
        if (role !== 'all') url.searchParams.set('role', role);

        try {
            clearError();
            body.innerHTML = `<tr><td colspan="6" class="px-6 py-6 text-center"><div class="flex items-center justify-center gap-3 text-gray-500 dark:text-gray-400"><svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Loading...</div></td></tr>`;

            const response = await fetch(url.toString());
            if (!response.ok) throw new Error('load users failed');
            const payload = await response.json();
            const items = payload.items || [];

            if (!items.length) {
                body.innerHTML = `<tr><td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No users found.</td></tr>`;
                return;
            }

            body.innerHTML = items.map(userRow).join('');
            attachDeleteTriggers();
            if (role === 'all') await refreshCountsPassive();
        } catch (_err) {
            showError('Unable to load users. Please try again later.');
            body.innerHTML = `<tr><td colspan="6" class="px-6 py-6 text-center text-red-500">Error loading users.</td></tr>`;
        }
    }

    async function refreshCountsPassive() {
        const counts = await fetchCounts();
        if (!counts) return;
        document.querySelectorAll('#role-user-cards .role-card').forEach((card) => {
            const key = card.dataset.role;
            const value = counts[key];
            const countEl = card.querySelector('[data-count]');
            if (countEl && typeof value !== 'undefined') countEl.textContent = value;
        });
    }

    async function init(force = false) {
        const host = document.getElementById('role-user-cards');
        if (!host) return;

        const stale = !host.querySelector('.role-card');
        if (!force && state.initialized && !stale) return;

        const counts = state.lastCounts || await fetchCounts();
        renderCards(counts || {});
        updateHeaderTitle();
        attachDeleteTriggers();
        await loadUsers();
        state.initialized = true;
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', submitDelete);
    }

    if (modal) {
        modal.querySelectorAll('[data-delete-modal-close]').forEach((el) => {
            el.addEventListener('click', closeDeleteModal);
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeDeleteModal();
        }
    });

    window.initUserManagementDashboard = init;
    init();
})();
</script>
<?= $this->endSection() ?>

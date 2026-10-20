/**
 * User management dashboard (user-management/index).
 *
 * Role summary cards, role-filtered user table, and the delete-user modal with
 * server-side impact preview. Server context comes from data attributes on
 * #role-user-cards:
 *   data-current-user-id  – the signed-in user (cannot delete themselves)
 *   data-is-admin         – "true" when delete actions should be offered
 *   data-fallback-counts  – JSON counts rendered server-side, used when the
 *                           live /api/user-counts request fails
 */

import { escapeHtml } from '../../utils/html.js';
import { getBaseUrl } from '../../utils/url-helpers.js';
import { buildCsrfHeader, rotateCsrfFromResponse } from '../../core/csrf.js';
import { getAvatarInitials } from '../../utils/avatar.js';

const ROLE_DEFS = [
    { key: 'total', label: 'Total Users', icon: 'groups' },
    { key: 'admins', label: 'Admins', icon: 'shield_person' },
    { key: 'providers', label: 'Providers', icon: 'badge' },
    { key: 'staff', label: 'Staff', icon: 'support_agent' }
];
const ROLE_MAP = { total: 'all', admins: 'admin', providers: 'provider', staff: 'staff' };
const TITLE_MAP = { total: 'Total Users', admins: 'Admin Users', providers: 'Provider Users', staff: 'Staff Users' };

function baseUrl(path) {
    return `${getBaseUrl()}/${String(path || '').replace(/^\/+/, '')}`;
}

function roleLabel(role) {
    return ({ admin: 'Administrator', provider: 'Service Provider', staff: 'Staff Member', customer: 'Customer' })[role] || role;
}

function getDisplayRoles(user) {
    const roles = Array.isArray(user.roles) ? [...user.roles] : [];
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
        + roles.map((role) => `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeColors[role] || badgeColors.customer}">${escapeHtml(roleLabel(role))}</span>`).join('')
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

function parseFallbackCounts(raw) {
    try {
        const parsed = JSON.parse(raw || '{}');
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_e) {
        return {};
    }
}

export function initUserManagementList() {
    const host = document.getElementById('role-user-cards');
    if (!host || host.dataset.userListInitialized === 'true') {
        return;
    }
    host.dataset.userListInitialized = 'true';

    const currentUserId = Number(host.dataset.currentUserId || 0);
    const isAdmin = host.dataset.isAdmin === 'true';
    const fallbackCounts = parseFallbackCounts(host.dataset.fallbackCounts);

    const state = { activeRole: 'total', lastCounts: null, deleteTarget: null };

    const modal = document.getElementById('delete-user-modal');
    const modalContent = document.getElementById('delete-modal-content');
    const confirmDeleteBtn = document.getElementById('delete-user-confirm-btn');

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

    async function fetchCounts() {
        try {
            const response = await fetch(baseUrl('api/user-counts'));
            if (!response.ok) throw new Error('count request failed');
            const payload = await response.json();
            state.lastCounts = payload.counts || null;
            return state.lastCounts;
        } catch (_err) {
            state.lastCounts = {
                total: Number(fallbackCounts.total || 0),
                admins: Number(fallbackCounts.admins || 0),
                providers: Number(fallbackCounts.providers || 0),
                staff: Number(fallbackCounts.staff || 0)
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
        const avatarInitials = getAvatarInitials(name, { defaultInitial: 'U' });
        const avatarUrl = typeof user.profile_image_url === 'string' ? user.profile_image_url.trim() : '';
        const avatarHtml = avatarUrl
            ? `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(name)}" class="w-10 h-10 rounded-full object-cover mr-3" />`
            : `<div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">${escapeHtml(avatarInitials)}</div>`;
        let assignmentsHtml = '<span class="text-gray-400 dark:text-gray-500 italic text-sm">None</span>';

        if (user.assignments) {
            const names = String(user.assignments).split(', ');
            assignmentsHtml = '<div class="flex flex-wrap gap-1">'
                + names.map((n) => `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">${escapeHtml(n)}</span>`).join('')
                + '</div>';
        }

        const canDelete = isAdmin && Number(user.id) !== currentUserId;
        const deleteAction = canDelete
            ? `<button type="button" class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="Delete User" data-delete-user-id="${Number(user.id)}" data-delete-user-name="${escapeHtml(name)}" data-delete-user-role="${escapeHtml(role)}"><span class="material-symbols-outlined">delete</span></button>`
            : '';

        return `<tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">`
            + `<td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100"><div class="flex items-center">${avatarHtml}<div><div class="font-medium">${escapeHtml(name)}</div><div class="text-gray-500 dark:text-gray-400 text-sm">${escapeHtml(email)}</div>${user.phone ? `<div class="text-gray-400 dark:text-gray-500 text-xs">${escapeHtml(user.phone)}</div>` : ''}</div></div></td>`
            + `<td class="px-6 py-4">${renderRoleBadges(user)}</td>`
            + `<td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${isActive ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}">${isActive ? 'Active' : 'Inactive'}</span></td>`
            + `<td class="px-6 py-4 text-gray-500 dark:text-gray-400 hidden md:table-cell">${assignmentsHtml}</td>`
            + `<td class="px-6 py-4 text-gray-500 dark:text-gray-400 hidden md:table-cell">${escapeHtml(fmtDate(user.created_at))}</td>`
            + `<td class="px-6 py-4"><div class="flex items-center space-x-2"><a href="${baseUrl('user-management/edit/' + user.id)}" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit User"><span class="material-symbols-outlined">edit</span></a>${deleteAction}</div></td>`
            + `</tr>`;
    }

    function updateHeaderTitle() {
        const titleEl = document.getElementById('users-dynamic-title');
        if (!titleEl) return;
        titleEl.textContent = TITLE_MAP[state.activeRole] || 'Total Users';
    }

    function highlightActive() {
        host.querySelectorAll('.role-card').forEach((el) => {
            const active = el.dataset.role === state.activeRole;
            el.classList.toggle('ring-2', active);
            el.classList.toggle('ring-blue-500', active);
        });
    }

    function renderCards(counts) {
        host.innerHTML = '';
        ROLE_DEFS.forEach((def) => {
            const value = counts[def.key] ?? 0;
            const card = document.createElement('div');
            card.className = 'cursor-pointer rounded-lg p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group role-card';
            card.dataset.role = def.key;
            card.innerHTML = `<div class="flex items-center justify-between mb-2"><div><p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">${def.label}</p><p class="text-2xl font-semibold text-gray-800 dark:text-gray-100" data-count>${escapeHtml(value)}</p></div><div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300"><span class="material-symbols-outlined">${def.icon}</span></div></div>`;
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
                    'X-Requested-With': 'XMLHttpRequest',
                    ...buildCsrfHeader(),
                },
            });
            rotateCsrfFromResponse(response);

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
        if (!isAdmin) return;
        document.querySelectorAll('[data-delete-user-id]').forEach((button) => {
            if (button.dataset.boundDelete === '1') return;
            button.dataset.boundDelete = '1';
            button.addEventListener('click', () => openDeleteModal(button));
        });
    }

    async function loadUsers() {
        const body = document.getElementById('users-table-body');
        if (!body) return;

        const role = ROLE_MAP[state.activeRole] || 'all';
        const url = new URL(baseUrl('api/users'), window.location.origin);
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
        host.querySelectorAll('.role-card').forEach((card) => {
            const value = counts[card.dataset.role];
            const countEl = card.querySelector('[data-count]');
            if (countEl && typeof value !== 'undefined') countEl.textContent = value;
        });
    }

    function onKeydown(event) {
        if (!document.body.contains(host)) {
            document.removeEventListener('keydown', onKeydown);
            return;
        }
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeDeleteModal();
        }
    }

    confirmDeleteBtn?.addEventListener('click', submitDelete);
    modal?.querySelectorAll('[data-delete-modal-close]').forEach((el) => {
        el.addEventListener('click', closeDeleteModal);
    });
    document.addEventListener('keydown', onKeydown);

    (async () => {
        const counts = await fetchCounts();
        renderCards(counts || {});
        attachDeleteTriggers();
        await loadUsers();
    })();
}

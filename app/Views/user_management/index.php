<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>User Management<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="User Management" data-page-subtitle="Manage system users and their permissions">

    <!-- Flash Messages -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
            <?= esc(session()->getFlashdata('success')) ?>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <!-- Role Filter Cards (interactive) -->
    <div class="mb-6">
        <div id="role-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4" aria-live="polite">
            <!-- Card Template (cloned in JS) -->
            <template id="role-card-template">
                <div class="role-card group cursor-pointer p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col relative overflow-hidden">
                    <div class="absolute inset-0 opacity-0 group-[.active]:opacity-100 transition-opacity duration-200 pointer-events-none" style="background: linear-gradient(135deg,var(--tw-gradient-from,#2563eb),var(--tw-gradient-to,#7c3aed)); mix-blend: multiply;"></div>
                    <div class="relative z-10 flex items-start justify-between mb-2">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 group-[.active]:text-indigo-100">Label</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100 group-[.active]:text-white" data-count>0</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-100 dark:bg-gray-700 group-[.active]:bg-white/20">
                            <span class="material-symbols-outlined text-gray-600 dark:text-gray-300 text-xl group-[.active]:text-white" data-icon>group</span>
                        </div>
                    </div>
                    <div class="relative z-10 mt-auto flex items-center text-xs text-gray-500 dark:text-gray-400 group-[.active]:text-indigo-100" data-sub>Subtitle</div>
                    <div class="absolute inset-0 rounded-xl ring-2 ring-inset ring-transparent group-[.active]:ring-indigo-500/70 pointer-events-none transition-all duration-200"></div>
                </div>
            </template>
        </div>
    </div>

    <!-- Users Table -->
    <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
            <div>
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">System Users</h2>
                <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Manage user accounts and permissions</p>
            </div>
            <?php if ($canCreateAdmin || $canCreateProvider || $canCreateStaff): ?>
            <a href="<?= base_url('user-management/create') ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors duration-200 w-full sm:w-auto justify-center">
                <span class="material-symbols-outlined">person_add</span>
                <span>Add New User</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400 transition-colors duration-300" id="usersTable" aria-live="polite">
                <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600 transition-colors duration-300">
                    <tr>
                        <th class="px-6 py-4 font-semibold">User</th>
                        <th class="px-6 py-4 font-semibold">Role</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Provider</th>
                        <th class="px-6 py-4 font-semibold">Created</th>
                        <th class="px-6 py-4 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <?php foreach ($users as $user): ?>
                    <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-300">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?= esc($user['name']) ?></div>
                                    <div class="text-gray-500 dark:text-gray-400 text-sm"><?= esc($user['email']) ?></div>
                                    <?php if ($user['phone']): ?>
                                        <div class="text-gray-400 dark:text-gray-500 text-xs"><?= esc($user['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                            $badgeColors = [
                                'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                'customer' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                            ];
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeColors[$user['role']] ?? $badgeColors['customer'] ?>">
                                <?= get_role_display_name($user['role']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($user['is_active'] ?? true) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                                <?= ($user['is_active'] ?? true) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                            <?php if ($user['provider_id'] ?? false): ?>
                                <?php
                                $provider = array_filter($users, fn($u) => $u['id'] == $user['provider_id']);
                                $provider = reset($provider);
                                ?>
                                <?= $provider ? esc($provider['name']) : 'Unknown' ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <a href="<?= base_url('user-management/edit/' . $user['id']) ?>" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                                <?php if ($user['id'] !== session()->get('user_id')): ?>
                                    <?php if ($user['is_active'] ?? true): ?>
                                        <a href="<?= base_url('user-management/deactivate/' . $user['id']) ?>" 
                                           onclick="return confirm('Are you sure you want to deactivate this user?')"
                                           class="p-1 text-gray-600 dark:text-gray-400 hover:text-yellow-600 dark:hover:text-yellow-400 transition-colors duration-200">
                                            <span class="material-symbols-outlined">block</span>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url('user-management/activate/' . $user['id']) ?>" 
                                           class="p-1 text-gray-600 dark:text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition-colors duration-200">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card Layout -->
        <div class="md:hidden space-y-4">
            <?php foreach ($users as $user): ?>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 transition-colors duration-300 shadow-brand">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300"><?= esc($user['name']) ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 transition-colors duration-300"><?= esc($user['email']) ?></p>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-1 items-end">
                        <?php
                        $badgeColors = [
                            'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                            'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                            'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            'customer' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                        ];
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeColors[$user['role']] ?? $badgeColors['customer'] ?>">
                            <?= get_role_display_name($user['role']) ?>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($user['is_active'] ?? true) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                            <?= ($user['is_active'] ?? true) ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-3">
                    <span>Created: <?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                    <?php if ($user['provider_id'] ?? false): ?>
                        <?php
                        $provider = array_filter($users, fn($u) => $u['id'] == $user['provider_id']);
                        $provider = reset($provider);
                        ?>
                        <span>Provider: <?= $provider ? esc($provider['name']) : 'Unknown' ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex justify-end space-x-2">
                    <a href="<?= base_url('user-management/edit/' . $user['id']) ?>" class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                        <span class="material-symbols-outlined">edit</span>
                    </a>
                    <?php if ($user['id'] !== session()->get('user_id')): ?>
                        <?php if ($user['is_active'] ?? true): ?>
                            <a href="<?= base_url('user-management/deactivate/' . $user['id']) ?>" 
                               onclick="return confirm('Are you sure you want to deactivate this user?')"
                               class="p-2 text-gray-600 dark:text-gray-400 hover:text-yellow-600 dark:hover:text-yellow-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                <span class="material-symbols-outlined">block</span>
                            </a>
                        <?php else: ?>
                            <a href="<?= base_url('user-management/activate/' . $user['id']) ?>" 
                               class="p-2 text-gray-600 dark:text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                <span class="material-symbols-outlined">check_circle</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
    font-weight: 600;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
</style>

<div id="users-error" class="hidden mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200"></div>

<script>
// Dynamic role cards & filtering
document.addEventListener('DOMContentLoaded', () => {
    const roleMeta = [
        { key:'all',        label:'Total Users',   icon:'group',                sub:'All users & customers' },
        { key:'admin',      label:'Admins',        icon:'admin_panel_settings', sub:'Full access' },
        { key:'provider',   label:'Providers',     icon:'storefront',           sub:'Business owners' },
        { key:'staff',      label:'Staff',         icon:'groups',               sub:'Staff & reception' },
        { key:'customer',   label:'Customers',     icon:'person',               sub:'Registered clients' },
    ];
    const cardsHost = document.getElementById('role-cards');
    const tpl = document.getElementById('role-card-template');
    const tableBody = document.getElementById('users-table-body');
    let activeRole = 'all';

    function formatDate(str){ try { return new Date(str).toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});} catch(e) { return str || ''; } }

    function renderCards(counts) {
        cardsHost.querySelectorAll('.role-card').forEach(el=>el.remove());
        roleMeta.forEach(meta => {
            const node = tpl.content.firstElementChild.cloneNode(true);
            node.dataset.role = meta.key;
            node.querySelector('[data-count]').textContent = counts[meta.key==='all'?'total':meta.key] ?? 0;
            node.querySelector('[data-icon]').textContent = meta.icon;
            node.querySelector('p').textContent = meta.label;
            node.querySelector('[data-sub]').textContent = meta.sub;
            if(meta.key === activeRole) node.classList.add('active');
            node.addEventListener('click', () => {
                if(activeRole === meta.key) return; // no-op
                activeRole = meta.key;
                cardsHost.querySelectorAll('.role-card').forEach(c=>c.classList.toggle('active', c.dataset.role===activeRole));
                loadUsers();
            });
            cardsHost.appendChild(node);
        });
    }

    function userRow(u) {
        const role = u.role || (u._type === 'customer' ? 'customer' : '');
        const isActive = (u.is_active ?? true) && role !== 'customer'; // customers assumed active
        const badgeColors = {
            admin:'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            provider:'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            staff:'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            customer:'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
        };
        const displayName = u.name || [u.first_name,u.last_name].filter(Boolean).join(' ') || '—';
        const email = u.email || '—';
        return `<tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-300">
            <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100"><div class="flex items-center"><div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">${displayName.substring(0,1).toUpperCase()}</div><div><div class="font-medium">${escapeHtml(displayName)}</div><div class="text-gray-500 dark:text-gray-400 text-sm">${escapeHtml(email)}</div>${u.phone?`<div class='text-gray-400 dark:text-gray-500 text-xs'>${escapeHtml(u.phone)}</div>`:''}</div></div></td>
            <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeColors[role] || badgeColors.customer}">${roleLabel(role)}</span></td>
            <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${isActive?'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}">${isActive?'Active':'Inactive'}</span></td>
            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">${u.provider_id?`#${u.provider_id}`:'—'}</td>
            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">${formatDate(u.created_at)}</td>
            <td class="px-6 py-4">${role==='customer'?'' : `<div class='flex items-center space-x-2'><a href='${baseUrl('user-management/edit/'+u.id)}' class='p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400'><span class='material-symbols-outlined'>edit</span></a></div>`}</td>
        </tr>`;
    }

    function escapeHtml(str){ return (str||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
    function roleLabel(r){ return {admin:'Admin',provider:'Provider',staff:'Staff',customer:'Customer'}[r] || r; }
    function baseUrl(path){ return `${window.location.origin.replace(/\/$/,'')}/${path}`; }

    function showError(msg){
        const box = document.getElementById('users-error');
        box.textContent = msg;
        box.classList.remove('hidden');
    }
    function clearError(){
        const box = document.getElementById('users-error');
        box.classList.add('hidden');
        box.textContent='';
    }

    async function loadCounts() {
        try {
            const res = await fetch(baseUrl('api/user-counts'));
            if(!res.ok) throw new Error('Failed counts');
            const json = await res.json();
            renderCards(json.counts || {});
        } catch (e) {
            console.warn(e);
            // fallback with PHP-provided stats
            renderCards({
                total: <?= (int)($stats['total'] ?? 0) ?>,
                admins: <?= (int)($stats['admins'] ?? 0) ?>,
                providers: <?= (int)($stats['providers'] ?? 0) ?>,
                staff: <?= (int)($stats['staff'] ?? 0) ?>,
                customers: <?= (int)($stats['customers'] ?? 0) ?>
            });
            showError('Live counts unavailable, showing cached values.');
        }
    }

    async function loadUsers() {
        const role = activeRole;
        const url = new URL(baseUrl('api/users'));
        if (role !== 'all') url.searchParams.set('role', role);
        try {
            clearError();
            tableBody.innerHTML = `<tr><td colspan='6' class='px-6 py-6 text-center'><div class='flex items-center justify-center gap-3 text-gray-500 dark:text-gray-400'><svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Loading...</div></td></tr>`;
            const res = await fetch(url.toString());
            if(!res.ok) throw new Error('Failed users');
            const json = await res.json();
            const items = json.items || [];
            if(!items.length){
                tableBody.innerHTML = `<tr><td colspan='6' class='px-6 py-6 text-center text-gray-500 dark:text-gray-400'>No users found.</td></tr>`;
                return;
            }
            tableBody.innerHTML = items.map(userRow).join('');
            // Refresh counts after list load to ensure consistency if data changed
            loadCounts();
        } catch(e) {
            console.error(e);
            showError('Unable to load users. Please try again later.');
            tableBody.innerHTML = `<tr><td colspan='6' class='px-6 py-6 text-center text-red-500'>Error loading users.</td></tr>`;
        }
    }

    // Initial
    loadCounts();
    loadUsers();
});
</script>
<?= $this->endSection() ?>

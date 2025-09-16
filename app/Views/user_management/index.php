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

    <!-- Role Filter Cards (replicated dashboard layout) -->
    <div class="mb-6">
        <div id="role-user-cards" class="grid grid-cols-2 md:grid-cols-5 gap-4" aria-live="polite"></div>
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
                                        <script>
                                        // Unified, idempotent initializer for User Management summary cards & table
                                        (function(){
                                            const ROLE_DEFS = [
                                                { key:'total',     label:'Total Users', icon:'groups' },
                                                { key:'admins',    label:'Admins',      icon:'shield_person' },
                                                { key:'providers', label:'Providers',   icon:'badge' },
                                                { key:'staff',     label:'Staff',       icon:'support_agent' },
                                                { key:'customers', label:'Customers',   icon:'person' },
                                            ];
                                            const roleKeyToApi = { total:'all', admins:'admin', providers:'provider', staff:'staff', customers:'customer' };
                                            const state = {
                                                activeRole: 'total',
                                                initialized: false,
                                                lastCounts: null
                                            };

                                            function baseUrl(path){ return `${window.location.origin.replace(/\/$/,'')}/${path}`; }
                                            function escapeHtml(str){ return (str||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
                                            function roleLabel(r){ return {admin:'Admin',provider:'Provider',staff:'Staff',customer:'Customer'}[r] || r; }
                                            function formatDate(str){ try { return new Date(str).toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});} catch(e){ return str||''; } }
                                            function showError(msg){ const box=document.getElementById('users-error'); if(!box) return; box.textContent=msg; box.classList.remove('hidden'); }
                                            function clearError(){ const box=document.getElementById('users-error'); if(!box) return; box.classList.add('hidden'); box.textContent=''; }

                                            function userRow(u){
                                                const role=u.role || (u._type==='customer'?'customer':'');
                                                const isActive=(u.is_active??true) && role!=='customer';
                                                const badgeColors={admin:'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',provider:'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',staff:'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',customer:'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'};
                                                const displayName=u.name || [u.first_name,u.last_name].filter(Boolean).join(' ') || '—';
                                                const email=u.email||'—';
                                                return `<tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-300">`
                                                    +`<td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100"><div class="flex items-center"><div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">${escapeHtml(displayName.substring(0,1).toUpperCase())}</div><div><div class="font-medium">${escapeHtml(displayName)}</div><div class="text-gray-500 dark:text-gray-400 text-sm">${escapeHtml(email)}</div>${u.phone?`<div class='text-gray-400 dark:text-gray-500 text-xs'>${escapeHtml(u.phone)}</div>`:''}</div></div></td>`
                                                    +`<td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeColors[role]||badgeColors.customer}">${roleLabel(role)}</span></td>`
                                                    +`<td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${(isActive?'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200')}">${isActive?'Active':'Inactive'}</span></td>`
                                                    +`<td class="px-6 py-4 text-gray-500 dark:text-gray-400">${u.provider_id?`#${u.provider_id}`:'—'}</td>`
                                                    +`<td class="px-6 py-4 text-gray-500 dark:text-gray-400">${formatDate(u.created_at)}</td>`
                                                    +`<td class="px-6 py-4">${role==='customer'?'':`<div class='flex items-center space-x-2'><a href='${baseUrl('user-management/edit/'+u.id)}' class='p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400'><span class='material-symbols-outlined'>edit</span></a></div>`}</td>`
                                                    +`</tr>`;
                                            }

                                            async function fetchCounts(){
                                                try { const r=await fetch(baseUrl('api/user-counts')); if(!r.ok) throw 0; const j=await r.json(); state.lastCounts=j.counts||null; return state.lastCounts; }
                                                catch(_){
                                                    state.lastCounts = {
                                                        total: <?= (int)($stats['total'] ?? 0) ?>,
                                                        admins: <?= (int)($stats['admins'] ?? 0) ?>,
                                                        providers: <?= (int)($stats['providers'] ?? 0) ?>,
                                                        staff: <?= (int)($stats['staff'] ?? 0) ?>,
                                                        customers: <?= (int)($stats['customers'] ?? 0) ?>
                                                    };
                                                    showError('Live counts unavailable, showing cached values.');
                                                    return state.lastCounts;
                                                }
                                            }

                                            function renderCards(counts){
                                                const host=document.getElementById('role-user-cards'); if(!host) return;
                                                host.innerHTML='';
                                                ROLE_DEFS.forEach(def=>{
                                                    const val=counts[def.key]??0;
                                                    const card=document.createElement('div');
                                                    card.className='cursor-pointer rounded-lg p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group role-card';
                                                    card.dataset.role=def.key;
                                                    card.innerHTML=`<div class="flex items-center justify-between mb-2"><div><p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">${def.label}</p><p class="text-2xl font-semibold text-gray-800 dark:text-gray-100" data-count>${val}</p></div><div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300"><span class="material-symbols-outlined">${def.icon}</span></div></div>`;
                                                    card.addEventListener('click',()=>{ if(state.activeRole===def.key) return; state.activeRole=def.key; highlightActive(); loadUsers(); });
                                                    host.appendChild(card);
                                                });
                                                highlightActive();
                                            }

                                            function highlightActive(){
                                                document.querySelectorAll('#role-user-cards .role-card').forEach(el=>{
                                                    const on=el.dataset.role===state.activeRole; el.classList.toggle('ring-2',on); el.classList.toggle('ring-blue-500',on);
                                                });
                                            }

                                            async function loadUsers(){
                                                const tableBody=document.getElementById('users-table-body'); if(!tableBody) return;
                                                const role=roleKeyToApi[state.activeRole]||'all';
                                                const url=new URL(baseUrl('api/users')); if(role!=='all') url.searchParams.set('role',role);
                                                try {
                                                    clearError();
                                                    tableBody.innerHTML=`<tr><td colspan='6' class='px-6 py-6 text-center'><div class='flex items-center justify-center gap-3 text-gray-500 dark:text-gray-400'><svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Loading...</div></td></tr>`;
                                                    const r=await fetch(url.toString()); if(!r.ok) throw 0; const j=await r.json(); const items=j.items||[];
                                                    if(!items.length){ tableBody.innerHTML=`<tr><td colspan='6' class='px-6 py-6 text-center text-gray-500 dark:text-gray-400'>No users found.</td></tr>`; return; }
                                                    tableBody.innerHTML=items.map(userRow).join('');
                                                    if(role==='all') refreshCountsPassive();
                                                } catch(_){
                                                    showError('Unable to load users. Please try again later.');
                                                    tableBody.innerHTML=`<tr><td colspan='6' class='px-6 py-6 text-center text-red-500'>Error loading users.</td></tr>`;
                                                }
                                            }

                                            async function refreshCountsPassive(){
                                                const counts=await fetchCounts(); if(!counts) return; // Keep active card highlight; only update numbers
                                                document.querySelectorAll('#role-user-cards .role-card').forEach(card=>{
                                                    const key=card.dataset.role; const val=counts[key]; const countEl=card.querySelector('[data-count]'); if(countEl && typeof val!=='undefined') countEl.textContent=val;
                                                });
                                            }

                                            async function init(force=false){
                                                const host=document.getElementById('role-user-cards');
                                                if(!host) return; // Not on page
                                                const stale = !host.querySelector('.role-card');
                                                if(!force && state.initialized && !stale) return; // Already good
                                                const counts = state.lastCounts || await fetchCounts();
                                                renderCards(counts || {});
                                                await loadUsers();
                                                state.initialized = true;
                                            }

                                            // Expose for debugging / manual trigger
                                            window.initUserManagementDashboard = init;

                                            document.addEventListener('DOMContentLoaded', ()=> init());
                                            document.addEventListener('spa:navigated', ()=> init());
                                        })();
                                        </script>

    async function loadCounts() {
        try {
            const res = await fetch(baseUrl('api/user-counts'));
            if(!res.ok) throw new Error('Failed counts');
            const json = await res.json();
            renderRoleCards(json.counts || {});
        } catch (e) {
            console.warn(e);
            // fallback with PHP-provided stats
            renderRoleCards({
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
        // Map card key to API role parameter
        const map = {
            'total': 'all',
            'admins': 'admin',
            'providers': 'provider',
            'staff': 'staff', // includes receptionist in API
            'customers': 'customer'
        };
        const role = map[activeRole] || 'all';
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
            // Only refresh counts if we loaded 'all' to minimize redundant queries
            if (role === 'all') loadCounts();
        } catch(e) {
            console.error(e);
            showError('Unable to load users. Please try again later.');
            tableBody.innerHTML = `<tr><td colspan='6' class='px-6 py-6 text-center text-red-500'>Error loading users.</td></tr>`;
        }
    }

    // Initial
    loadCounts().then(()=> loadUsers());
});

// Re-initialize when returning via SPA navigation
document.addEventListener('spa:navigated', (e) => {
    if (!document.getElementById('role-user-cards')) return; // Not on user management view
    // If cards already present with counts, skip
    const existing = document.querySelectorAll('#role-user-cards .role-card');
    if (existing.length === 0) {
        // Re-run init logic by dispatching DOMContentLoaded-like sequence
        // Minimal reinvoke: fetch counts & users again
        (async function reinit(){
            try {
                const res = await fetch(baseUrl('api/user-counts'));
                if(res.ok){
                    const json = await res.json();
                    if(json.counts){
                        // Mirror renderRoleCards path (function is inside earlier closure, so reimplement lightweight here)
                        const host = document.getElementById('role-user-cards');
                        if(host){
                            const defs = [
                                { key:'total',label:'Total Users',icon:'groups' },
                                { key:'admins',label:'Admins',icon:'shield_person' },
                                { key:'providers',label:'Providers',icon:'badge' },
                                { key:'staff',label:'Staff',icon:'support_agent' },
                                { key:'customers',label:'Customers',icon:'person' },
                            ];
                            host.innerHTML='';
                            defs.forEach(def => {
                                const val = json.counts[def.key] ?? 0;
                                const card = document.createElement('div');
                                card.className='cursor-pointer rounded-lg p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group role-card';
                                card.dataset.role=def.key;
                                card.innerHTML=`<div class=\"flex items-center justify-between mb-2\"><div><p class=\"text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400\">${def.label}</p><p class=\"text-2xl font-semibold text-gray-800 dark:text-gray-100\" data-count>${val}</p></div><div class=\"w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300\"><span class=\"material-symbols-outlined\">${def.icon}</span></div></div>`;
                                card.addEventListener('click', () => {
                                    document.querySelectorAll('#role-user-cards .role-card').forEach(c=>c.classList.toggle('ring-2', c===card));
                                    document.querySelectorAll('#role-user-cards .role-card').forEach(c=>c.classList.toggle('ring-blue-500', c===card));
                                    const map = { total:'all', admins:'admin', providers:'provider', staff:'staff', customers:'customer'};
                                    const r = map[def.key] || 'all';
                                    fetch(`${baseUrl('api/users')}${r==='all'?'':'?role='+r}`).then(rsp=>rsp.json()).then(data=>{
                                        const tb = document.getElementById('users-table-body');
                                        if(!tb) return;
                                        const items = data.items||[];
                                        tb.innerHTML = items.length? items.map(u=>`<tr class=\"border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition\"><td class=\"px-6 py-4 font-medium text-gray-900 dark:text-gray-100\">${(u.name||u.first_name||'').toString()}</td><td class=\"px-6 py-4 text-gray-500 dark:text-gray-400\">${u.role||u._type||''}</td><td class=\"px-6 py-4 text-gray-500 dark:text-gray-400\">${u.email||''}</td><td class=\"px-6 py-4 text-gray-500 dark:text-gray-400\">${u.created_at||''}</td><td class=\"px-6 py-4 text-right text-xs text-blue-600\">View</td></tr>`).join('') : '<tr><td colspan=6 class=\"px-6 py-6 text-center text-gray-500 dark:text-gray-400\">No users found.</td></tr>';
                                    });
                                });
                                host.appendChild(card);
                            });
                            // Activate first card
                            const first = host.querySelector('.role-card');
                            if(first) first.click();
                        }
                    }
                }
            } catch(_){}
        })();
    }
});
</script>
<?= $this->endSection() ?>

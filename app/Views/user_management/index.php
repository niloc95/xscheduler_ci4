<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>User Management<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Manage system users and their permissions<?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
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
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- Users Table (main container starts here) -->
    <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="mb-6">
            <div class="flex flex-col items-end space-y-2 text-right">
                <h2 id="users-dynamic-title" class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Total Users</h2>
               
                <?php if (($canCreateAdmin ?? false) || ($canCreateProvider ?? false) || ($canCreateStaff ?? false)): ?>
                <a href="<?= base_url('user-management/create') ?>" class="inline-flex bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg items-center space-x-2 transition-colors duration-200">
                    <span class="material-symbols-outlined">person_add</span>
                    <span>Add New User</span>
                </a>
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
                            <?php $badgeColors = [
                                'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            ]; ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeColors[$user['role']] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' ?>">
                                <?= get_role_display_name($user['role']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($user['is_active'] ?? true) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                                <?= ($user['is_active'] ?? true) ? 'Active' : 'Inactive' ?>
                            </span>
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
                                        <form method="post" action="<?= base_url('user-management/delete/' . ($user['id'] ?? 0)) ?>" onsubmit="return confirm('⚠️ PERMANENTLY DELETE this user?\n\nThis action cannot be undone!\n\nUser: <?= esc($user['name']) ?>');" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="Delete User">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </form>
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
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function(){
    const ROLE_DEFS=[
        {key:'total',label:'Total Users',icon:'groups'},
        {key:'admins',label:'Admins',icon:'shield_person'},
        {key:'providers',label:'Providers',icon:'badge'},
        {key:'staff',label:'Staff',icon:'support_agent'}
    ];
    const roleMap={ total:'all', admins:'admin', providers:'provider', staff:'staff' };
    const state={activeRole:'total',initialized:false,lastCounts:null};
    function baseUrl(p){return `${window.location.origin.replace(/\/$/,'')}/${p}`;}
    function showError(msg){const b=document.getElementById('users-error'); if(!b) return; b.textContent=msg; b.classList.remove('hidden');}
    function clearError(){const b=document.getElementById('users-error'); if(!b) return; b.classList.add('hidden'); b.textContent='';}
    function escapeHtml(s){return (s||'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));}
    function roleLabel(r){return {admin:'Admin',provider:'Provider',staff:'Staff'}[r]||r;}
    function fmtDate(s){try{return new Date(s).toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});}catch(e){return s||'';}}
    function userRow(u){
    const role=u.role||'';
    const isActive=(u.is_active??true);
    const badgeColors={admin:'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',provider:'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',staff:'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'};
    const name=u.name||'—';
    const email=u.email||'—';
    let assignmentsHtml = '';
    if (u.assignments) {
        const names = u.assignments.split(', ');
        assignmentsHtml = '<div class="flex flex-wrap gap-1">' + names.map(n => `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">${escapeHtml(n)}</span>`).join('') + '</div>';
    } else {
        assignmentsHtml = '<span class="text-gray-400 dark:text-gray-500 italic text-sm">None</span>';
    }
        return `<tr class=\"bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700\">`
            +`<td class=\"px-6 py-4 font-medium text-gray-900 dark:text-gray-100\"><div class=\"flex items-center\"><div class=\"w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3\">${escapeHtml(name.substring(0,1).toUpperCase())}</div><div><div class=\"font-medium\">${escapeHtml(name)}</div><div class=\"text-gray-500 dark:text-gray-400 text-sm\">${escapeHtml(email)}</div>${u.phone?`<div class='text-gray-400 dark:text-gray-500 text-xs'>${escapeHtml(u.phone)}</div>`:''}</div></div></td>`
            +`<td class=\"px-6 py-4\"><span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeColors[role]||badgeColors.customer}\">${roleLabel(role)}</span></td>`
            +`<td class=\"px-6 py-4\"><span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${(isActive?'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200')}\">${isActive?'Active':'Inactive'}</span></td>`
            +`<td class=\"px-6 py-4 text-gray-500 dark:text-gray-400\">${assignmentsHtml}</td>`
            +`<td class=\"px-6 py-4 text-gray-500 dark:text-gray-400\">${fmtDate(u.created_at)}</td>`
            +`<td class=\"px-6 py-4\"><div class='flex items-center space-x-2'><a href='${baseUrl('user-management/edit/'+u.id)}' class='p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400'><span class='material-symbols-outlined'>edit</span></a></div></td>`
            +`</tr>`;
    }
    async function fetchCounts(){
        try{const r=await fetch(baseUrl('api/user-counts')); if(!r.ok) throw 0; const j=await r.json(); state.lastCounts=j.counts||null; return state.lastCounts;}catch(_){
            state.lastCounts={
                total: <?= (int)($stats['total'] ?? 0) ?>,
                admins: <?= (int)($stats['admins'] ?? 0) ?>,
                providers: <?= (int)($stats['providers'] ?? 0) ?>,
                staff: <?= (int)($stats['staff'] ?? 0) ?>
            }; showError('Live counts unavailable, showing cached values.'); return state.lastCounts; }
    }
    function updateHeaderTitle(){
        const titleEl=document.getElementById('users-dynamic-title');
        if(!titleEl) return;
        const labelMap={ total:'Total Users', admins:'Admin Users', providers:'Provider Users', staff:'Staff Users' };
        titleEl.textContent = labelMap[state.activeRole] || 'Total Users';
    }
    function renderCards(counts){
        const host=document.getElementById('role-user-cards'); if(!host) return; host.innerHTML='';
        ROLE_DEFS.forEach(def=>{ const val=counts[def.key]??0; const card=document.createElement('div'); card.className='cursor-pointer rounded-lg p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition group role-card'; card.dataset.role=def.key; card.innerHTML=`<div class=\"flex items-center justify-between mb-2\"><div><p class=\"text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400\">${def.label}</p><p class=\"text-2xl font-semibold text-gray-800 dark:text-gray-100\" data-count>${val}</p></div><div class=\"w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300\"><span class=\"material-symbols-outlined\">${def.icon}</span></div></div>`; card.addEventListener('click',()=>{ if(state.activeRole===def.key) return; state.activeRole=def.key; highlightActive(); updateHeaderTitle(); loadUsers(); }); host.appendChild(card); });
        highlightActive();
        updateHeaderTitle();
    }
    function highlightActive(){ document.querySelectorAll('#role-user-cards .role-card').forEach(el=>{ const on=el.dataset.role===state.activeRole; el.classList.toggle('ring-2',on); el.classList.toggle('ring-blue-500',on); }); }
    async function loadUsers(){ const body=document.getElementById('users-table-body'); if(!body)return; const role=roleMap[state.activeRole]||'all'; const url=new URL(baseUrl('api/users')); if(role!=='all') url.searchParams.set('role',role); try{ clearError(); body.innerHTML=`<tr><td colspan='6' class='px-6 py-6 text-center'><div class='flex items-center justify-center gap-3 text-gray-500 dark:text-gray-400'><svg class=\"animate-spin h-5 w-5 text-indigo-500\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\"><circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"></circle><path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z\"></path></svg> Loading...</div></td></tr>`; const r=await fetch(url.toString()); if(!r.ok) throw 0; const j=await r.json(); const items=j.items||[]; if(!items.length){ body.innerHTML=`<tr><td colspan='6' class='px-6 py-6 text-center text-gray-500 dark:text-gray-400'>No users found.</td></tr>`; return;} body.innerHTML=items.map(userRow).join(''); if(role==='all') refreshCountsPassive(); }catch(_){ showError('Unable to load users. Please try again later.'); body.innerHTML=`<tr><td colspan='6' class='px-6 py-6 text-center text-red-500'>Error loading users.</td></tr>`; }}
    async function refreshCountsPassive(){ const counts=await fetchCounts(); if(!counts)return; document.querySelectorAll('#role-user-cards .role-card').forEach(card=>{ const k=card.dataset.role; const v=counts[k]; const el=card.querySelector('[data-count]'); if(el && typeof v!=='undefined') el.textContent=v; }); }
    async function init(force=false){ const host=document.getElementById('role-user-cards'); if(!host)return; const stale=!host.querySelector('.role-card'); if(!force && state.initialized && !stale) return; const counts=state.lastCounts||await fetchCounts(); renderCards(counts||{}); updateHeaderTitle(); await loadUsers(); state.initialized=true; }
    window.initUserManagementDashboard=init; document.addEventListener('DOMContentLoaded',()=>init()); document.addEventListener('spa:navigated',()=>init());
})();
// (Removed previous row-selection based dynamic header script; header now tracks active role card only.)
</script>
<?= $this->endSection() ?>

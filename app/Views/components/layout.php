<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?: 'WebSchedulr' ?></title>
    
    <!-- Dark mode initialization script (must be before any styling) -->
    <script>
        // Prevent flash of unstyled content by applying theme immediately
        (function() {
            const storedTheme = localStorage.getItem('xs-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = storedTheme || (prefersDark ? 'dark' : 'light');
            
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    
    <!-- Sidebar positioning fix -->
    <style>
        @media (min-width: 1024px) {
            /* Sidebar offset + gutter (matches SCSS) */
            .main-content-container {
                margin-left: calc(16rem + 2rem) !important;
            }
        }
        /* Light mode: slightly darker light gray background */
        html:not(.dark) body {
            background-color: #e5e7eb !important; /* Tailwind gray-200 */
        }
        html:not(.dark) .main-content-container {
            background-color: #e5e7eb !important;
        }
        
        .unified-sidebar,
        #main-sidebar {
            position: fixed !important;
            width: 16rem !important;
            z-index: 50 !important;
        }
        
    @media (max-width: 1023px) {
            .unified-sidebar,
            #main-sidebar {
                transform: translateX(-100%) !important;
        top: 0 !important;
        left: 0 !important;
        height: 100vh !important;
            }
            
            .unified-sidebar.open,
            #main-sidebar.open {
                transform: translateX(0) !important;
            }
            
            .main-content-container {
                margin-left: 0 !important;
            }
        }
        /* Match sidebar hover effect for header dropdown items */
        .xs-menu-item {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 0.75rem; /* 12px to align with sidebar */
        }
        .xs-menu-item:hover {
            background: linear-gradient(135deg, rgba(247, 127, 0, 0.08), rgba(252, 191, 73, 0.06));
            color: #1f2937; /* gray-800 */
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(247, 127, 0, 0.12);
        }
        html.dark .xs-menu-item:hover {
            color: #f9fafb; /* gray-50 */
            background: linear-gradient(135deg, rgba(255, 179, 102, 0.1), rgba(255, 209, 102, 0.08));
        }
        .xs-menu-item .material-symbols-outlined {
            transition: transform .2s ease;
        }
        .xs-menu-item:hover .material-symbols-outlined {
            transform: scale(1.05);
        }
    </style>
    
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
    
    <!-- Material Design Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <script type="module" src="<?= base_url('build/assets/materialWeb.js') ?>"></script>
    <script>
        // Global base URL for API and asset helpers
        window.__BASE_URL__ = '<?= base_url() ?>';
    </script>
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-200">
    <div class="min-h-screen">
    <?= $this->renderSection('sidebar') ?>

        <div class="ml-0">
            <!-- Unified container to align header and page content -->
            <div class="main-content-container p-4 bg-gray-50 dark:bg-gray-900 transition-colors duration-200 space-y-4">
                <!-- Persistent Top Bar Header (sticky + dynamic title) -->
                <div id="stickyHeader" data-sticky-header class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm material-shadow p-4 transition-colors duration-200 sticky top-0 lg:top-4 z-40">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <button id="menuToggle" class="lg:hidden mr-2 p-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors duration-200">
                                <span class="material-symbols-outlined text-2xl">menu</span>
                            </button>
                            <div class="min-w-0">
                                <h1 id="headerTitle" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-800 dark:text-gray-200 transition-colors duration-200 truncate whitespace-nowrap">
                                    <?= $this->renderSection('header_title') ?: (isset($pageTitle) ? esc($pageTitle) : ($this->renderSection('title') ?: 'Dashboard')) ?>
                                </h1>
                                <p id="headerSubtitle" class="hidden sm:block text-gray-600 dark:text-gray-400 transition-colors duration-200">
                                    <?php 
                                    $currentUser = session()->get('user');
                                    $currentRole = $currentUser['role'] ?? 'User';
                                    $displayRole = ucfirst($currentRole);
                                    if ($currentRole === 'admin') $displayRole = 'Administrator';
                                    elseif ($currentRole === 'provider') $displayRole = 'Service Provider';
                                    elseif ($currentRole === 'staff') $displayRole = 'Staff Member';
                                    ?>
                                    Welcome back, <span class="font-medium"><?= isset($currentUser) ? esc($currentUser['name']) : 'User' ?></span> • <span class="text-blue-600 dark:text-blue-400 font-medium"><?= $displayRole ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 md:space-x-4 flex-shrink-0">
                            <!-- Dark Mode Toggle -->
                            <?= $this->include('components/dark-mode-toggle') ?>
                            
                            <!-- Search -->
                            <div class="hidden md:block relative">
                                <div class="relative">
                                    <input type="search" 
                                        placeholder="Search users, appointments..." 
                                        class="w-80 h-12 pl-10 pr-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors duration-200 text-sm leading-relaxed"
                                        id="dashboardSearch">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 pointer-events-none text-base">search</span>
                                </div>
                            </div>
                            
                            <!-- Notifications -->
                            <md-icon-button class="text-gray-600 dark:text-gray-400">
                                <span class="material-symbols-outlined">notifications</span>
                            </md-icon-button>
                            
                            <!-- User Menu Dropdown -->
                            <div class="relative" id="userMenuWrapper">
                                <?php $currentUser = session()->get('user'); ?>
                                <?php 
                                    $currentRole = $currentUser['role'] ?? 'user';
                                    $displayRole = ucfirst($currentRole);
                                    if ($currentRole === 'admin') $displayRole = 'Administrator';
                                    elseif ($currentRole === 'provider') $displayRole = 'Service Provider';
                                    elseif ($currentRole === 'staff') $displayRole = 'Staff Member';
                                ?>
                                <button id="userMenuButton" type="button" aria-haspopup="menu" aria-expanded="false"
                                    class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                                    <div class="w-10 h-10 bg-blue-500 dark:bg-blue-600 rounded-full flex items-center justify-center text-white font-medium">
                                        <?= strtoupper(substr(isset($currentUser) ? ($currentUser['name'] ?? 'U') : 'U', 0, 2)) ?>
                                    </div>
                                    <div class="hidden md:block text-left">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 leading-tight"><?= isset($currentUser) ? esc($currentUser['name']) : 'User' ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-tight"><?= $displayRole ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-gray-500 dark:text-gray-400 text-base hidden md:inline">expand_more</span>
                                </button>
                                <!-- Dropdown Panel -->
                                <div id="userMenu" role="menu" aria-labelledby="userMenuButton"
                                     class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden z-50">
                                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200"><?= isset($currentUser) ? esc($currentUser['name']) : 'User' ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= esc($currentUser['email'] ?? '') ?></p>
                                    </div>
                                    <div class="py-1">
                                        <a href="<?= base_url('profile') ?>" role="menuitem" class="xs-menu-item flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                            <span class="material-symbols-outlined text-base">person</span>
                                            Profile
                                        </a>
                                        <?php if (($currentRole ?? 'user') === 'admin'): ?>
                                        <a href="<?= base_url('settings') ?>" role="menuitem" class="xs-menu-item flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                            <span class="material-symbols-outlined text-base">settings</span>
                                            Settings
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-700">
                                        <a href="<?= base_url('auth/logout') ?>" data-no-spa="true" role="menuitem" class="xs-menu-item flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400">
                                            <span class="material-symbols-outlined text-base">logout</span>
                                            Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Mobile search row: full-width under title + icons -->
                    <div class="md:hidden mt-2">
                        <div class="relative">
                            <input type="search" 
                                placeholder="Search users, appointments..." 
                                class="w-full h-11 pl-10 pr-4 py-2.5 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors duration-200 text-sm leading-relaxed"
                                id="dashboardSearchMobile">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 pointer-events-none text-base">search</span>
                        </div>
                    </div>
                </div>

                <!-- Page Content (SPA swaps only this area) -->
                <main class="pt-0" style="padding-top: 24px;">
                    <div id="spa-content" aria-live="polite" aria-busy="false" style="scroll-margin-top: calc(var(--xs-header-offset, 0px) + 24px);">
                        <?= $this->renderSection('content') ?>
                    </div>
                </main>

                <!-- Footer aligned with content container -->
                <?= $this->include('components/footer') ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script type="module" src="<?= base_url('build/assets/main.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/spa.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/unified-sidebar.js') ?>"></script>
    <!-- Load scheduler calendar module (matches vite input: schedule-core) -->
    <script type="module" src="<?= base_url('build/assets/schedule-core.js') ?>"></script>
    
    <!-- Global Modal & Toast Containers -->
    <style>
        /* Match login card visual for modal */
        .xs-modal-card { box-shadow: 0 8px 32px rgba(0, 0, 0, 0.10); overflow: hidden; }
        html.dark .xs-modal-card { box-shadow: 0 8px 32px rgba(0, 0, 0, 0.40); }
        .xs-modal-title-brand { color: var(--md-sys-color-primary); }
        .xs-btn-primary {
            color: #fff;
            background-color: var(--md-sys-color-secondary);
        }
        .xs-btn-primary:hover { filter: brightness(0.96); }
        .xs-btn-primary:focus { outline: none; box-shadow: 0 0 0 2px rgba(249,115,22,.5); }
    /* Ensure Material Symbols center perfectly within squared icon badges */
    .xs-icon-center { display: grid; place-items: center; }
    .xs-icon-center > .material-symbols-outlined { line-height: 1; display: block; }
    </style>
    <div id="xs-modal-root" class="fixed inset-0 z-[100] hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px] opacity-0 transition-opacity duration-200" data-xs-modal-overlay></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div id="xs-modal"
                 role="dialog" aria-modal="true" aria-labelledby="xs-modal-title" aria-describedby="xs-modal-desc"
                 class="w-full max-w-md sm:max-w-lg bg-white dark:bg-gray-800 rounded-2xl xs-modal-card border border-gray-200 dark:border-gray-700 transform transition-all duration-200 scale-95 opacity-0 outline-none"
                 tabindex="-1">
                <div class="p-6 sm:p-8">
                    <div class="flex items-start gap-4">
                        <div id="xs-modal-icon" class="mt-1 hidden shrink-0"></div>
                        <div class="min-w-0 text-left">
                            <h2 id="xs-modal-title" class="text-xl sm:text-2xl font-semibold xs-modal-title-brand text-gray-900 dark:text-gray-100">Notice</h2>
                            <p id="xs-modal-desc" class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300"></p>
                        </div>
                    </div>
                </div>
                <div id="xs-modal-actions" class="px-6 sm:px-8 py-4 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row gap-3 sm:gap-2 sm:justify-end bg-gray-50 dark:bg-gray-800"></div>
            </div>
        </div>
    </div>
    <div id="xs-toast-root" class="fixed top-4 right-4 z-[101] space-y-2 pointer-events-none"></div>
    <div id="xs-aria-live" class="sr-only" aria-live="polite" aria-atomic="true"></div>
    
    <script>
    // Minimal, reusable notification system (modal + toast)
    (function(){
        const COLORS = {
            info: { ring: 'ring-blue-500', icon: 'info', bg: 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' },
            success: { ring: 'ring-green-500', icon: 'check_circle', bg: 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' },
            warning: { ring: 'ring-amber-500', icon: 'warning', bg: 'bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200' },
            error: { ring: 'ring-red-500', icon: 'error', bg: 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' }
        };

        const modalRoot = document.getElementById('xs-modal-root');
        const modal = document.getElementById('xs-modal');
        const modalOverlay = modalRoot?.querySelector('[data-xs-modal-overlay]');
        const modalTitle = document.getElementById('xs-modal-title');
        const modalDesc = document.getElementById('xs-modal-desc');
        const modalActions = document.getElementById('xs-modal-actions');
        const modalIcon = document.getElementById('xs-modal-icon');
        const toastRoot = document.getElementById('xs-toast-root');
        const ariaLive = document.getElementById('xs-aria-live');
        let lastActiveEl = null;

        function focusTrap(e){
            if (e.key !== 'Tab') return;
            const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (!focusables.length) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }

        function closeModal(){
            modalRoot.setAttribute('aria-hidden', 'true');
            modalRoot.classList.add('hidden');
            modalOverlay && modalOverlay.classList.remove('opacity-100');
            modal.classList.remove('opacity-100','scale-100');
            document.removeEventListener('keydown', onKey);
            modal.removeEventListener('keydown', focusTrap);
            if (lastActiveEl && typeof lastActiveEl.focus === 'function') {
                try { lastActiveEl.focus(); } catch(_){}
            }
        }

        function onKey(e){
            if (e.key === 'Escape') closeModal();
        }

        function buildIcon(type){
            const color = COLORS[type] || COLORS.info;
            return `<div class="${color.bg} rounded-xl w-10 h-10 sm:w-12 sm:h-12 xs-icon-center"><span class="material-symbols-outlined leading-none block text-xl sm:text-2xl">${color.icon}</span></div>`;
        }

        async function showDialog(opts){
            const { title, message, type = 'info', actions = [], autoClose = false, duration = 3000 } = opts || {};
            if (!modalRoot || !modal) throw new Error('Modal root not found');
            lastActiveEl = document.activeElement;

            const color = COLORS[type] || COLORS.info;
            modal.className = modal.className.replace(/ring-(blue|green|amber|red)-500/g,'').trim();
            modal.classList.add('focus:ring-2', color.ring);
            modalTitle.textContent = title || (type === 'success' ? 'Success' : type === 'error' ? 'Error' : type === 'warning' ? 'Warning' : 'Notice');
            modalDesc.textContent = message || '';
            modalIcon.classList.remove('hidden');
            modalIcon.innerHTML = buildIcon(type);

            modalActions.innerHTML = '';
            let resolver;
            const done = new Promise((res)=> resolver = res);
                        function addBtn(text, handler, variant){
                const btn = document.createElement('button');
                btn.type = 'button';
                                btn.className = (variant === 'primary'
                                    ? 'xs-btn-primary'
                                    : 'text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400')
                                    + ' inline-flex items-center justify-center h-10 px-4 rounded-lg font-medium transition w-full sm:w-auto';
                btn.textContent = text;
                btn.addEventListener('click', () => {
                    try { handler && handler(); } catch (e) { console.error(e); }
                    resolver && resolver({ action: text });
                    closeModal();
                });
                modalActions.appendChild(btn);
                return btn;
            }
            if (actions && actions.length) {
                actions.forEach((a,i)=> addBtn(a.text || 'OK', a.onClick, i === 0 ? 'primary' : 'secondary'));
            } else if (!autoClose) {
                addBtn('OK', null, 'primary');
            }

            modalRoot.classList.remove('hidden');
            modalRoot.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(()=>{
                modalOverlay && modalOverlay.classList.add('opacity-100');
                modal.classList.add('opacity-100','scale-100');
                const firstAction = modalActions.querySelector('button');
                (firstAction || modal).focus();
            });
            document.addEventListener('keydown', onKey);
            modal.addEventListener('keydown', focusTrap);
            modalOverlay && modalOverlay.addEventListener('click', closeModal, { once: true });

            if (autoClose) setTimeout(()=>{ resolver && resolver({ action: 'autoClose' }); closeModal(); }, Math.max(1000, duration||3000));
            try { ariaLive.textContent = (title ? title + '. ' : '') + (message || ''); } catch(_){ }
            return done;
        }

        function showToast(opts){
            const { title, message, type = 'info', duration = 3000 } = opts || {};
            if (!toastRoot) return;
            const color = COLORS[type] || COLORS.info;
            const wrap = document.createElement('div');
            wrap.className = 'pointer-events-auto flex items-center gap-3 p-3 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 animate-[fadeIn_.2s_ease-out]';
            const icon = document.createElement('div'); icon.innerHTML = `<div class="${color.bg} rounded-xl w-8 h-8 xs-icon-center"><span class=\"material-symbols-outlined leading-none block text-base\">${color.icon}</span></div>`;
            const text = document.createElement('div');
            text.innerHTML = `<div class="text-sm font-medium text-gray-800 dark:text-gray-100">${title || 'Notice'}</div><div class="text-sm text-gray-600 dark:text-gray-300">${message || ''}</div>`;
            const close = document.createElement('button');
            close.className = 'ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200';
            close.innerHTML = '<span class="material-symbols-outlined text-base">close</span>';
            close.addEventListener('click', ()=> toastRoot.removeChild(wrap));
            wrap.append(icon, text, close);
            toastRoot.appendChild(wrap);
            try { ariaLive.textContent = (title ? title + '. ' : '') + (message || ''); } catch(_){ }
            setTimeout(()=>{ if (wrap.parentNode === toastRoot) toastRoot.removeChild(wrap); }, Math.max(1000, duration||3000));
        }

        function showModal(opts){
            try {
                if (opts && (opts.autoClose || (!opts.actions || opts.actions.length === 0))) {
                    // Prefer toast for non-blocking notifications
                    if (opts.autoClose) { showToast(opts); return Promise.resolve({ action: 'autoClose' }); }
                }
                return showDialog(opts);
            } catch (e) {
                console.error('Modal failure', e, opts);
                const container = document.getElementById('spa-content') || document.body;
                const banner = document.createElement('div');
                banner.className = 'm-4 p-3 rounded-lg border border-yellow-300/60 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200';
                banner.textContent = (opts?.title ? opts.title + ': ' : '') + (opts?.message || 'An event occurred.');
                container.prepend(banner);
                return Promise.resolve({ action: 'fallback' });
            }
        }

        window.XSNotify = { show: showModal, toast: showToast };
        window.showModal = showModal; // convenience
    })();
    </script>
    <script>
        // Sync header title/subtitle with current view's declared page attributes, if present
    let XS_DEFAULT_SUBTITLE = null;
        function xsyncHeaderTitle() {
            const header = document.getElementById('headerTitle');
            const sub = document.getElementById('headerSubtitle');
            const container = document.getElementById('spa-content');
            if (!header || !container) return;
            // Look for any element that declares a data-page-title within the current content
            const el = container.querySelector('[data-page-title]');
            const t = el?.getAttribute('data-page-title');
            const s = el?.getAttribute('data-page-subtitle') || container.querySelector('[data-page-subtitle]')?.getAttribute('data-page-subtitle');
            if (t) {
                header.textContent = t;
                try { document.title = t + ' • WebSchedulr'; } catch (e) {}
            }
            if (sub) {
                if (s) {
                    sub.textContent = s;
                } else if (XS_DEFAULT_SUBTITLE !== null) {
                    sub.textContent = XS_DEFAULT_SUBTITLE;
                }
            }
        }
        function xsetHeaderOffset() {
            const header = document.querySelector('[data-sticky-header]');
            const root = document.querySelector('main');
            if (!header || !root) return;
            // Because the header is sticky with top 0/1rem, we need header height + computed top gap
            const rect = header.getBoundingClientRect();
            // Determine effective top offset from sticky positioning (0 on small, 16px on lg)
            let topOffset = 0;
            const style = window.getComputedStyle(header);
            const topVal = style.top;
            if (topVal && topVal !== 'auto') {
                const parsed = parseFloat(topVal);
                if (!Number.isNaN(parsed)) topOffset = parsed;
            }
            // Store just the header's effective height (including sticky top offset)
            const offset = Math.ceil(rect.height + topOffset);
            root.style.setProperty('--xs-header-offset', offset + 'px');
        }

        // Recalc on load, resize, and SPA navigations
        document.addEventListener('DOMContentLoaded', () => {
            // Capture the default subtitle once (e.g., Welcome back, <User>)
            const sub = document.getElementById('headerSubtitle');
            if (sub && XS_DEFAULT_SUBTITLE === null) {
                XS_DEFAULT_SUBTITLE = sub.textContent;
            }
            xsyncHeaderTitle();
            xsetHeaderOffset();
        });
        window.addEventListener('resize', xsetHeaderOffset);
        document.addEventListener('spa:navigated', () => {
            xsyncHeaderTitle();
            // Wait a tick in case content size affects header (e.g., title change wraps)
            requestAnimationFrame(() => xsetHeaderOffset());
        });

        // User menu toggle and click-away handler
        (function wireUserMenu(){
            const btn = document.getElementById('userMenuButton');
            const menu = document.getElementById('userMenu');
            if (!btn || !menu) return;
            const toggle = (open) => {
                const willOpen = open ?? menu.classList.contains('hidden');
                if (willOpen) {
                    menu.classList.remove('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                } else {
                    menu.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                }
                // Header height might change slightly
                requestAnimationFrame(() => xsetHeaderOffset());
            };
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggle();
            });
            document.addEventListener('click', (e) => {
                if (!menu.classList.contains('hidden')) {
                    const within = e.target.closest('#userMenuWrapper');
                    if (!within) toggle(false);
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !menu.classList.contains('hidden')) toggle(false);
            });
        })();

        // Global delegated handler for Settings logo upload so SPA swaps don't drop listeners
        (function wireGlobalSettingsLogoHandler(){
            const onFileChange = (e) => {
                const input = e.target;
                if (!input || input.id !== 'company_logo') return;
                const form = input.closest('form');
                if (!form) return;
                // Preview
                try {
                    const img = document.querySelector('#spa-content #company_logo_preview_img');
                    const file = input.files && input.files[0];
                    if (img && file && file.type && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (ev) => { img.src = ev.target?.result || ''; img.classList.remove('hidden'); };
                        reader.readAsDataURL(file);
                    }
                } catch (_) {}
                // Auto-submit
                try { form.requestSubmit(); } catch (_) { form.submit(); }
            };
            // Use capture to ensure we see the change even if other handlers stop propagation
            document.addEventListener('change', onFileChange, true);
        })();

        // Global delegated confirm handler: replaces native confirms with modal
        (function wireGlobalConfirmHandler(){
            const getConfirmCopy = (el) => {
                const explicit = el.getAttribute('data-confirm-message');
                if (explicit) return { title: 'Please confirm', message: explicit, type: 'warning', cta: 'Continue' };
                const kind = el.getAttribute('data-confirm');
                if (kind === 'delete') return { title: 'Delete user?', message: 'This will permanently delete the user and cannot be undone.', type: 'error', cta: 'Delete' };
                if (kind === 'deactivate') return { title: 'Deactivate user?', message: 'Are you sure you want to deactivate this user? They will lose access until reactivated.', type: 'warning', cta: 'Deactivate' };
                if (kind === 'activate') return { title: 'Activate user?', message: 'Activate this user and restore their access?', type: 'info', cta: 'Activate' };
                return { title: 'Please confirm', message: 'Are you sure you want to proceed?', type: 'warning', cta: 'Continue' };
            };
            document.addEventListener('click', (e) => {
                const link = e.target && (e.target.closest && e.target.closest('a[data-confirm]'));
                if (!link) return;
                const href = link.getAttribute('href');
                if (!href || href === '#') return; // ignore
                e.preventDefault();
                const copy = getConfirmCopy(link);
                const proceed = () => { window.location.href = href; };
                if (window.showModal) {
                    window.showModal({
                        title: copy.title,
                        message: copy.message,
                        type: copy.type,
                        actions: [
                            { text: 'Cancel' },
                            { text: copy.cta, onClick: proceed }
                        ]
                    });
                } else {
                    // As a last resort, navigate only if user clicks again (no blocking alert)
                    const banner = document.createElement('div');
                    banner.className = 'm-4 p-3 rounded-lg border border-yellow-300/60 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200';
                    banner.textContent = copy.title + ' ' + copy.message + ' Click again to confirm.';
                    const container = document.getElementById('spa-content') || document.body;
                    container.prepend(banner);
                    // Temporarily mark link as confirmed on next click
                    link.setAttribute('data-confirm-next-ok', '1');
                    const one = (evt) => {
                        const tgt = evt.target.closest && evt.target.closest('a[data-confirm]');
                        if (tgt === link && link.getAttribute('data-confirm-next-ok') === '1') {
                            evt.preventDefault();
                            link.removeAttribute('data-confirm-next-ok');
                            proceed();
                            document.removeEventListener('click', one, true);
                        }
                    };
                    document.addEventListener('click', one, true);
                }
            }, true);
        })();

        // Global handler: show provider selection when role=staff on create/edit user forms
        (function wireRoleDependentFields(){
            function updateProviderVisibility(root){
                const container = root || document;
                const roleEl = container.querySelector('#spa-content #role, #role');
                const providerWrap = container.querySelector('#spa-content #provider-selection, #provider-selection');
                const providerInput = container.querySelector('#spa-content #provider_id, #provider_id');
                if (!roleEl || !providerWrap) return;
                const val = roleEl.value;
                const show = val === 'staff';
                providerWrap.style.display = show ? 'block' : 'none';
                if (providerInput) {
                    providerInput.required = !!show;
                    if (!show) providerInput.value = '';
                }
            }
            document.addEventListener('change', (e) => {
                if (e.target && e.target.id === 'role') {
                    updateProviderVisibility(document);
                }
            }, true);
            document.addEventListener('DOMContentLoaded', () => updateProviderVisibility(document));
            document.addEventListener('spa:navigated', () => updateProviderVisibility(document));
        })();
    </script>
</body>
</html>

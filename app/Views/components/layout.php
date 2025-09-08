<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?: 'xScheduler' ?></title>
    
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
    <script src="<?= base_url('build/assets/main.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/spa.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/unified-sidebar.js') ?>"></script>
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
                try { document.title = t + ' • xScheduler'; } catch (e) {}
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
    </script>
</body>
</html>

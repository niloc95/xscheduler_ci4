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
    
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
    <script type="module" src="<?= base_url('build/assets/materialWeb.js') ?>"></script>
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-200">
    <div class="flex flex-col min-h-screen">
    <?= $this->renderSection('sidebar') ?>

        <div class="flex-1">
            <!-- Unified container to align header and page content -->
            <div class="p-4 lg:ml-72 bg-gray-50 dark:bg-gray-900 transition-colors duration-200 space-y-4">
                <!-- Persistent Top Bar Header (sticky + dynamic title) -->
                <div id="stickyHeader" data-sticky-header class="bg-white dark:bg-gray-800 material-shadow rounded-lg p-4 transition-colors duration-200 sticky top-0 lg:top-4 z-30">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <button id="menuToggle" class="lg:hidden mr-2 p-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors duration-200">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                            <?php $logoUrl = setting_url('general.company_logo'); ?>
                            <?php if ($logoUrl): ?>
                                <img src="<?= esc($logoUrl) ?>" alt="Company logo" class="h-10 w-auto mr-3 rounded" style="object-fit: contain;" />
                            <?php endif; ?>
                            <div>
                                <h1 id="headerTitle" class="text-2xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-200">
                                    <?= $this->renderSection('header_title') ?: (isset($pageTitle) ? esc($pageTitle) : ($this->renderSection('title') ?: 'Dashboard')) ?>
                                </h1>
                                <p id="headerSubtitle" class="text-gray-600 dark:text-gray-400 transition-colors duration-200">Welcome back, <?= isset($user) ? ($user['name'] ?? 'User') : 'User' ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Dark Mode Toggle -->
                            <?= $this->include('components/dark-mode-toggle') ?>
                            
                            <!-- Search -->
                            <div class="hidden md:block relative">
                                <div class="relative">
                                    <input type="search" 
                                        placeholder="Search users, appointments..." 
                                        class="w-80 h-12 pl-10 pr-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors duration-200 text-sm leading-relaxed"
                                        id="dashboardSearch">
                                    <svg class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- Notifications -->
                            <md-icon-button class="text-gray-600 dark:text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.73 21a2 2 0 01-3.46 0"></path>
                                </svg>
                            </md-icon-button>
                            
                            <!-- User Menu -->
                            <div class="flex items-center space-x-2">
                                <div class="w-10 h-10 bg-blue-500 dark:bg-blue-600 rounded-full flex items-center justify-center text-white font-medium transition-colors duration-200">
                                    <?= strtoupper(substr(isset($user) ? ($user['name'] ?? 'U') : 'U', 0, 2)) ?>
                                </div>
                                <div class="hidden md:block">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors duration-200"><?= isset($user) ? ($user['name'] ?? 'User') : 'User' ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 transition-colors duration-200"><?= isset($user) ? ($user['role'] ?? 'Administrator') : 'Administrator' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Page Content (SPA swaps only this area) -->
                <main class="min-h-screen pt-0" style="padding-top: 24px;">
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

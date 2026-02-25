<?php
/**
 * Main Authenticated App Layout
 * 
 * TailAdmin-style master layout for all authenticated pages.
 * Provides consistent structure across Dashboard, Calendar, Customers, Users, etc.
 * 
 * Sections available to child views:
 * - title: Browser tab title
 * - head: Additional <head> content (styles, meta)
 * - sidebar: Sidebar content (typically unified-sidebar include)
 * - page_header: Page header with title, subtitle, actions (use xs-page-header component)
 * - content: Main page content
 * - scripts: Additional JavaScript at end of body
 * - modals: Modal dialogs container
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */
?>
<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="WebSchedulr - Professional Appointment Scheduling">
    <title><?= $this->renderSection('title') ?: 'WebSchedulr' ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= setting_url('general.company_icon', 'assets/settings/default-icon.svg') ?>">
    
    <!-- Prevent FOUC: Apply dark mode immediately -->
    <script>
        (function() {
            const theme = localStorage.getItem('xs-theme') || 
                         (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    
    <!-- Stylesheets (all layout/component styles are now in SCSS) -->
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('build/assets/main.css') ?>">
    
    <!-- Material Design Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <!-- Material Web Components -->
    <script type="module" src="<?= base_url('build/assets/materialWeb.js') ?>"></script>
    
    <!-- Global Config -->
    <script>
        window.__BASE_URL__ = '<?= base_url() ?>';
        window.__CSRF_TOKEN__ = '<?= csrf_hash() ?>';
        window.__BUSINESS_NAME__ = '<?= esc(setting('general.business_name', 'WebSchedulr'), 'js') ?>';
    </script>
    <meta name="csrf-header" content="X-CSRF-TOKEN">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen antialiased transition-colors duration-200">
    <!-- Sidebar (Fixed position - outside flow) -->
    <?php 
    // Auto-include sidebar if section not provided
    $sidebarContent = $this->renderSection('sidebar');
    if (empty(trim($sidebarContent ?? ''))) {
        // Detect current page from URL for sidebar highlighting
        $uri = service('uri');
        $segment = $uri->getSegment(1) ?: 'dashboard';
        echo $this->include('components/unified-sidebar', ['current_page' => $segment]);
    } else {
        echo $sidebarContent;
    }
    ?>
    
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="xs-sidebar-overlay lg:hidden"></div>
    
    <!-- Blur overlay for content scrolling in top gap -->
    <div class="xs-scroll-blur" aria-hidden="true"></div>
    
    <!-- Main Content Area (margin-left accounts for fixed sidebar on desktop) -->
    <?php
        // Capture header_title ONCE (renderSection can only be called once per section)
        $headerTitleSection = trim($this->renderSection('header_title'));
        $resolvedHeaderTitle = $headerTitleSection !== '' ? $headerTitleSection : 'Dashboard';
    ?>
    <div class="xs-main-container">
        <!-- Fixed Header Bar (solid opaque, aligned with sidebar) -->
        <header class="xs-header bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 transition-colors duration-200">
                <div class="flex items-center justify-between gap-4">
                    <!-- Left: Mobile Menu + Title -->
                    <div class="flex items-center gap-3 min-w-0">
                        <button id="menu-toggle" type="button" class="lg:hidden p-2 -ml-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <span class="material-symbols-outlined">menu</span>
                        </button>
                        <div class="min-w-0">
                            <?php 
                                $userName = session()->get('user')['name'] ?? 'User';
                                $userRole = session()->get('user')['role'] ?? 'user';
                                $roleLabels = [
                                    'admin' => 'Administrator',
                                    'provider' => 'Service Provider',
                                    'staff' => 'Staff Member'
                                ];
                                $displayRole = $roleLabels[$userRole] ?? ucfirst($userRole);
                                $greeting = date('H') < 12 ? 'Good morning' : (date('H') < 17 ? 'Good afternoon' : 'Good evening');
                            ?>
                            <p class="text-xs text-gray-400 dark:text-gray-500 hidden sm:flex items-center gap-1.5 leading-none mb-0.5">
                                <span><?= date('l, F j, Y') ?></span>
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span id="header-live-clock" class="text-blue-600 dark:text-blue-400 font-mono"><?= date('H:i:s') ?></span>
                            </p>
                            <h1 id="header-title" class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white truncate leading-tight">
                                <?= esc($resolvedHeaderTitle) ?>
                            </h1>
                            <p id="header-subtitle" class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block leading-tight">
                                <span id="header-greeting" class="font-medium"><?= esc($greeting) ?>, <?= esc($userName) ?></span>
                                <span id="header-greeting-sep" class="mx-1">·</span>
                                <span><?= esc($displayRole) ?></span>
                            </p>
                            <script>
                                (function() {
                                    const clockEl = document.getElementById('header-live-clock');
                                    if (clockEl) {
                                        setInterval(() => {
                                            const now = new Date();
                                            clockEl.textContent = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                                        }, 1000);
                                    }
                                })();
                            </script>
                        </div>
                        </div>
                        
                        <!-- Right: Search, Notifications, User Menu -->
                        <div class="flex items-center gap-2 lg:gap-4 flex-shrink-0">
                            <!-- Dark Mode Toggle -->
                            <?= $this->include('components/dark-mode-toggle') ?>
                            
                            <!-- Global Search (Desktop) -->
                            <div class="hidden md:block relative" id="global-search-wrapper">
                                <input type="search" 
                                       id="global-search" 
                                       placeholder="Search..." 
                                       class="w-64 lg:w-80 h-10 pl-10 pr-4 bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                       autocomplete="off">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                                <div id="global-search-results" class="hidden absolute top-full mt-2 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl max-h-96 overflow-y-auto z-50">
                                    <div id="global-search-results-content" class="p-2">
                                        <!-- Results will be injected here -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- New Appointment (Global — visible on all pages) -->
                            <?php if (function_exists('has_role') && has_role(['customer', 'staff', 'provider', 'admin'])): ?>
                            <a href="<?= base_url('/appointments/create') ?>"
                               class="btn btn-primary inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm rounded-lg whitespace-nowrap"
                               title="New Appointment">
                                <span class="material-symbols-outlined text-base">add</span>
                                <span class="hidden lg:inline"><?= ($userRole ?? 'user') === 'customer' ? 'Book Appointment' : 'New Appointment' ?></span>
                            </a>
                            <?php endif; ?>

                            <!-- Notifications -->
                            <button type="button" class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors relative">
                                <span class="material-symbols-outlined">notifications</span>
                            </button>
                            
                            <!-- User Menu -->
                            <?php $user = session()->get('user'); ?>
                            <div class="relative" id="user-menu-wrapper">
                                <button id="user-menu-btn" type="button" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <div class="w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium text-sm">
                                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?>
                                    </div>
                                    <div class="hidden lg:block text-left">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= esc($user['name'] ?? 'User') ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($displayRole) ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-gray-400 hidden lg:block">expand_more</span>
                                </button>
                                
                                <!-- Dropdown -->
                                <div id="user-menu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= esc($user['name'] ?? 'User') ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= esc($user['email'] ?? '') ?></p>
                                    </div>
                                    <div class="py-1">
                                        <a href="<?= base_url('profile') ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <span class="material-symbols-outlined text-lg">person</span>
                                            Profile
                                        </a>
                                        <?php if ($userRole === 'admin'): ?>
                                        <a href="<?= base_url('settings') ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <span class="material-symbols-outlined text-lg">settings</span>
                                            Settings
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-700">
                                        <a href="<?= base_url('auth/logout') ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <span class="material-symbols-outlined text-lg">logout</span>
                                            Sign out
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile Search -->
                    <div class="mt-3 md:hidden" id="global-search-wrapper-mobile">
                        <div class="relative">
                            <input type="search" 
                                   id="global-search-mobile" 
                                   placeholder="Search..." 
                                   class="w-full h-10 pl-10 pr-4 bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500"
                                   autocomplete="off">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                            <div id="global-search-results-mobile" class="hidden absolute top-full mt-2 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl max-h-80 overflow-y-auto z-50">
                                <div id="global-search-results-content-mobile" class="p-2">
                                    <!-- Results will be injected here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Page-Specific Header Controls (injected by child views) -->
                    <?php $headerControlsContent = trim($this->renderSection('header_controls') ?? ''); ?>
                    <?php if ($headerControlsContent !== ''): ?>
                    <div class="xs-header-controls mt-3 pt-3 border-t border-gray-200 dark:border-gray-700" id="header-controls-slot">
                        <?= $headerControlsContent ?>
                    </div>
                    <?php endif; ?>
                </header>
        
        <!-- Content Area (starts BELOW the fixed header) -->
        <?php 
        // Determine layout variant: standard (default) or dashboard
        $layoutVariant = $this->renderSection('layout_variant') ?: 'standard';
        $contentClasses = $layoutVariant === 'dashboard' 
            ? 'xs-content-wrapper xs-content-dashboard' 
            : 'xs-content-wrapper xs-content-standard';
        ?>
        <div class="<?= $contentClasses ?>">
                
                <!-- Flash Messages -->
                <?= $this->include('components/ui/flash-messages') ?>
                
                <!-- Page Header (Optional - Use page-header component instead) -->
                <?php $pageHeader = $this->renderSection('page_header'); ?>
                <?php if (trim($pageHeader)): ?>
                    <?= $pageHeader ?>
                <?php endif; ?>
                
                <!-- Main Content -->
                <main id="spa-content" aria-live="polite" aria-busy="false" data-page-title="<?= esc($resolvedHeaderTitle) ?>">
                    <?= $this->renderSection('content') ?>
                    
                    <!-- View-specific scripts (inside spa-content for SPA re-execution) -->
                    <?= $this->renderSection('scripts') ?>
                </main>
                
                <!-- Footer -->
                <footer class="mt-8 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <p>&copy; <?= date('Y') ?> WebSchedulr. All rights reserved.</p>
                        <p>Version 2.0.0</p>
                    </div>
                </footer>
            </div>
        </div>
    
    <!-- Modal Container -->
    <div id="modal-container">
        <?= $this->renderSection('modals') ?>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
    
    <!-- Scripts -->
    <script type="module" src="<?= base_url('build/assets/main.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/spa.js') ?>"></script>
    <script type="module" src="<?= base_url('build/assets/unified-sidebar.js') ?>"></script>
    
    <!-- Layout JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Keep CSS header height token in sync with actual header size
            const headerEl = document.querySelector('.xs-header');

            function syncHeaderHeight() {
                if (!headerEl) return;
                const height = headerEl.offsetHeight;
                if (height > 0) {
                    document.documentElement.style.setProperty('--xs-header-height', `${height}px`);
                }
            }

            // Initial sync
            syncHeaderHeight();

            // Keep in sync on resize/content changes
            window.addEventListener('resize', syncHeaderHeight);

            if (window.ResizeObserver && headerEl) {
                const headerObserver = new ResizeObserver(() => syncHeaderHeight());
                headerObserver.observe(headerEl);
            }

            // Header title sync function for SPA navigation
            function syncHeaderTitle() {
                const headerEl = document.getElementById('header-title');
                const spaContent = document.getElementById('spa-content');
                if (!headerEl || !spaContent) return;
                
                // Priority order for finding page title:
                // 1. data-page-title attribute on #spa-content itself
                // 2. data-page-title on any child element (e.g., dashboard intro div)
                // 3. First h1 or h2 heading in the content
                let pageTitle = spaContent.getAttribute('data-page-title');
                
                if (!pageTitle) {
                    const titleEl = spaContent.querySelector('[data-page-title]');
                    if (titleEl) pageTitle = titleEl.getAttribute('data-page-title');
                }
                
                // Fallback: look for first prominent heading
                if (!pageTitle) {
                    const heading = spaContent.querySelector('h1:not(#header-title), h2.text-xl, h2.text-2xl');
                    if (heading) {
                        pageTitle = heading.textContent.trim();
                    }
                }
                
                if (pageTitle && pageTitle !== headerEl.textContent.trim()) {
                    headerEl.textContent = pageTitle;
                    document.title = pageTitle + ' • WebSchedulr';
                }
            }
            
            // Sync on initial load
            syncHeaderTitle();
            
            // Hide greeting on schedule/appointment views to save header space
            function syncGreetingVisibility() {
                const greetingEl = document.getElementById('header-greeting');
                const greetingSep = document.getElementById('header-greeting-sep');
                if (!greetingEl) return;
                // If header has controls slot, it's a schedule view — hide greeting
                const hasControls = document.getElementById('header-controls-slot');
                greetingEl.style.display = hasControls ? 'none' : '';
                if (greetingSep) greetingSep.style.display = hasControls ? 'none' : '';
            }

            syncGreetingVisibility();

            // Sync on SPA navigation
            document.addEventListener('spa:navigated', function() {
                // Small delay to ensure DOM is updated
                requestAnimationFrame(() => {
                    syncHeaderTitle();
                    syncGreetingVisibility();
                    syncHeaderHeight();
                });
            });
            
            // User menu toggle
            const userMenuBtn = document.getElementById('user-menu-btn');
            const userMenu = document.getElementById('user-menu');
            
            if (userMenuBtn && userMenu) {
                userMenuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userMenu.classList.toggle('hidden');
                });
                
                document.addEventListener('click', function(e) {
                    if (!userMenu.contains(e.target) && !userMenuBtn.contains(e.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }
            
            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html>

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
 * @package WebScheduler
 * @since 2.0.0
 */
?>
<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="WebScheduler - Professional Appointment Scheduling">
    <title><?= $this->renderSection('title') ?: 'WebScheduler' ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= setting_url('general.company_icon', 'assets/settings/default-icon.svg') ?>">
    
    <!-- Theme bootstrap -->
    <script type="module" src="<?= vite_js('resources/js/theme-bootstrap.js') ?>"></script>
    
    <!-- Stylesheets (resolved from Vite manifest) -->
    <?php
    $layoutStyles = array_values(array_unique(array_merge(
        vite_css('resources/scss/app-consolidated.scss'),
        vite_css('resources/js/app.js')
    )));
    foreach ($layoutStyles as $css):
    ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; ?>
    
    <!-- Material Design Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <meta name="csrf-header" content="X-CSRF-TOKEN">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen antialiased transition-colors duration-200"
    data-base-url="<?= esc(rtrim(base_url(), '/')) ?>"
    data-csrf-token="<?= esc(csrf_hash()) ?>"
    data-business-name="<?= esc(setting('general.business_name', 'WebScheduler')) ?>"
    data-default-phone-country-code="<?= esc((string) setting('localization.default_phone_country_code', '+27')) ?>">
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
        $headerSubtitleSection = trim($this->renderSection('header_subtitle'));
        $headerPrimaryActionSection = strtolower(trim($this->renderSection('header_primary_action')));
        $resolvedHeaderTitle = $headerTitleSection !== '' ? $headerTitleSection : 'Dashboard';
        $resolvedHeaderSubtitle = $headerSubtitleSection !== '' ? $headerSubtitleSection : '';
        $showHeaderPrimaryAction = $headerPrimaryActionSection !== 'hidden';
        $headerPrimaryActionState = $showHeaderPrimaryAction ? 'visible' : 'hidden';
    ?>
    <div class="xs-main-container">
        <!-- Fixed Header Bar (solid opaque, aligned with sidebar) -->
        <header class="xs-header bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 transition-colors duration-200">
                <div class="xs-header-top flex items-start justify-between gap-4">
                    <!-- Left: Mobile Menu + Page Identity -->
                    <div class="xs-header-identity flex min-w-0 flex-1 items-center gap-3">
                        <button id="menu-toggle" type="button" class="lg:hidden w-11 h-11 -ml-1 inline-flex items-center justify-center text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <span class="material-symbols-outlined">menu</span>
                        </button>
                        <div class="min-w-0 flex-1">
                            <?php 
                                $userName = session()->get('user')['name'] ?? 'User';
                                $userRole = session()->get('user')['role'] ?? 'user';
                                $roleLabels = [
                                    'admin' => 'Administrator',
                                    'provider' => 'Service Provider',
                                    'staff' => 'Staff Member'
                                ];
                                $displayRole = $roleLabels[$userRole] ?? ucfirst($userRole);
                            ?>
                            <h1 id="header-title" class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white truncate leading-tight">
                                <?= esc($resolvedHeaderTitle) ?>
                            </h1>
                            <p id="header-subtitle"
                               class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block leading-tight"
                               <?= $resolvedHeaderSubtitle !== '' ? '' : 'hidden' ?>>
                                <?= esc($resolvedHeaderSubtitle) ?>
                            </p>
                            <div class="xs-header-temporal" aria-label="Current date and time">
                                <span id="header-live-date" class="xs-header-temporal-date"><?= date('D, j M') ?></span>
                                <span class="xs-header-temporal-separator" aria-hidden="true"></span>
                                <span id="header-live-clock" class="xs-header-temporal-time">--:--:--</span>
                            </div>
                        </div>
                    </div>
                        
                        <!-- Right: Inline Utilities + User Menu -->
                        <div class="xs-header-utilities flex items-center gap-2 xl:gap-3 flex-shrink-0">
                            <!-- Global Search (Expanded Desktop) -->
                            <div class="xs-header-search hidden xl:block relative" id="global-search-wrapper">
                                <input type="search" 
                                       id="global-search" 
                                       placeholder="Search..." 
                                       class="w-60 2xl:w-80 h-11 pl-10 pr-4 bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                       autocomplete="off">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                                <div id="global-search-results" class="hidden absolute top-full mt-2 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl max-h-96 overflow-y-auto z-50">
                                    <div id="global-search-results-content" class="p-2">
                                        <!-- Results will be injected here -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- New Appointment — icon-only on mobile, icon+label at xl+ -->
                            <?php if (function_exists('has_role') && has_role(['customer', 'staff', 'provider', 'admin'])): ?>
                            <a href="<?= base_url('/appointments/create') ?>"
                               id="header-primary-action"
                               aria-label="<?= ($userRole ?? 'user') === 'customer' ? 'Book appointment' : 'New appointment' ?>"
                               title="<?= ($userRole ?? 'user') === 'customer' ? 'Book Appointment' : 'New Appointment' ?>"
                               class="xs-header-primary-action inline-flex items-center justify-center rounded-lg bg-primary-500 text-white hover:bg-primary-600 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 h-9 w-9 p-0 xl:w-auto xl:h-auto xl:px-3 xl:py-2 xl:gap-1.5 text-sm whitespace-nowrap<?= $showHeaderPrimaryAction ? '' : ' xs-header-primary-action--suppressed' ?>">
                                <span class="material-symbols-outlined text-base">add</span>
                                <span class="hidden xl:inline"><?= ($userRole ?? 'user') === 'customer' ? 'Book Appointment' : 'New Appointment' ?></span>
                            </a>
                            <?php endif; ?>
                            
                            <!-- User Menu -->
                            <?php $user = session()->get('user'); ?>
                            <?php $headerAvatar = avatar_data(is_array($user) ? $user : [], 'U'); ?>
                            <div class="relative" id="user-menu-wrapper">
                                <button id="user-menu-btn"
                                        type="button"
                                        aria-haspopup="menu"
                                        aria-controls="user-menu"
                                        aria-expanded="false"
                                        class="xs-user-menu-trigger flex min-h-11 items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <?php if (!empty($headerAvatar['image_url'])): ?>
                                        <img src="<?= esc($headerAvatar['image_url']) ?>"
                                             alt="<?= esc($headerAvatar['name'] ?: ($user['name'] ?? 'User')) ?>"
                                             class="w-9 h-9 rounded-full object-cover" />
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full bg-primary-500 flex items-center justify-center text-white font-medium text-sm">
                                            <?= esc($headerAvatar['initials']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="hidden 2xl:block text-left">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= esc($user['name'] ?? 'User') ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($displayRole) ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-gray-400 hidden xl:block">expand_more</span>
                                </button>
                                
                                <!-- Adaptive Account Panel -->
                                <div id="user-menu" class="xs-user-panel hidden absolute right-0 mt-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden z-50" role="menu">
                                    <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex items-start gap-3">
                                            <?php if (!empty($headerAvatar['image_url'])): ?>
                                                <img src="<?= esc($headerAvatar['image_url']) ?>"
                                                     alt="<?= esc($headerAvatar['name'] ?: ($user['name'] ?? 'User')) ?>"
                                                     class="w-10 h-10 rounded-full object-cover flex-shrink-0" />
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-primary-500 flex items-center justify-center text-white font-medium text-sm flex-shrink-0">
                                                    <?= esc($headerAvatar['initials']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?= esc($user['name'] ?? 'User') ?></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= esc($user['email'] ?? '') ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">Appearance</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Toggle light and dark mode</p>
                                            </div>
                                            <?= $this->include('components/dark-mode-toggle') ?>
                                        </div>
                                    </div>

                                    <div class="py-1">
                                        <button type="button" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-left">
                                            <span class="material-symbols-outlined text-lg">notifications</span>
                                            Notifications
                                        </button>
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

                    <div class="mt-3 xl:hidden" id="global-search-wrapper-mobile">
                        <div class="relative">
                            <input type="search" 
                                   id="global-search-mobile" 
                                   placeholder="Search..." 
                                   class="w-full h-11 pl-10 pr-4 bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
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
                    <main id="spa-content"
                        aria-live="polite"
                        aria-busy="false"
                        data-page-title="<?= esc($resolvedHeaderTitle) ?>"
                        data-header-primary-action="<?= esc($headerPrimaryActionState) ?>"
                        <?= $resolvedHeaderSubtitle !== '' ? 'data-page-subtitle="' . esc($resolvedHeaderSubtitle) . '"' : '' ?>>
                    <?= $this->renderSection('content') ?>
                    
                    <!-- View-specific scripts (inside spa-content for SPA re-execution) -->
                    <?= $this->renderSection('scripts') ?>
                </main>
                
                <!-- Footer -->
                <footer class="mt-8 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <p>&copy; <?= date('Y') ?> WebScheduler. All rights reserved.</p>
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
    
    <!-- Scripts (resolved from Vite manifest) -->
    <script type="module" src="<?= vite_js('resources/js/layout/app-layout-init.js') ?>"></script>
    <script type="module" src="<?= vite_js('resources/js/app.js') ?>"></script>
    <script type="module" src="<?= vite_js('resources/js/dark-mode.js') ?>"></script>
    <script type="module" src="<?= vite_js('resources/js/spa.js') ?>"></script>
    <script type="module" src="<?= vite_js('resources/js/unified-sidebar.js') ?>"></script>
</body>
</html>

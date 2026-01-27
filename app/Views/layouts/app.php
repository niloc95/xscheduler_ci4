<?php
/**
 * Main Authenticated App Layout
 * 
 * Master layout for all authenticated pages with global header system.
 * Provides consistent header, sidebar, and content structure.
 * 
 * Sections available to child views:
 * - title: Browser tab title
 * - head: Additional <head> content (styles, meta)
 * - sidebar: Sidebar content (auto-includes unified-sidebar if not provided)
 * - header_title: Page title (H1) - displayed in global header (REQUIRED)
 * - header_subtitle: Page subtitle/description (optional) - displayed in global header
 * - header_actions: Action buttons for the header (optional)
 * - header_breadcrumbs: Breadcrumb trail array (optional)
 * - content: Main page content (REQUIRED)
 * - layout_variant: "standard" or "dashboard" for content width/spacing (default: "standard")
 * - scripts: Additional JavaScript at end of body
 * - modals: Modal dialogs container
 * 
 * IMPORTANT: Do NOT use page-header component in views anymore.
 * All page titles/subtitles must be set via header_* sections.
 * The global-header component is automatically included from the layout.
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
    </script>
    
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen antialiased transition-colors duration-200">
    <!-- Sidebar (Fixed position - outside flow) -->
    <aside class="xs-sidebar" id="main-sidebar">
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
    </aside>
    
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="xs-sidebar-overlay lg:hidden" onclick="closeSidebar()"></div>
    
    <!-- Blur overlay for content scrolling in top gap -->
    <div class="xs-scroll-blur" aria-hidden="true"></div>
    
    <!-- Main Content Area (margin-left accounts for fixed sidebar on desktop) -->
    <?php
        // Capture page header data from view sections
        $headerTitle = trim($this->renderSection('header_title')) ?: 'Dashboard';
        $headerSubtitle = trim($this->renderSection('header_subtitle')) ?: '';
        $headerActions = trim($this->renderSection('header_actions')) ?: '';
        $headerBreadcrumbs = $headerBreadcrumbs ?? [];
    ?>
    <div class="xs-main-container">
        <!-- Global Header (moved here for consistency across all pages) -->
        <?= $this->include('components/global-header', [
            'title' => $headerTitle,
            'subtitle' => $headerSubtitle,
            'actions' => !empty($headerActions) ? [$headerActions] : [],
            'breadcrumbs' => $headerBreadcrumbs,
        ]) ?>
        
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
                
                <!-- Main Content (page-header component MUST NOT be used here anymore) -->
                <main id="spa-content" aria-live="polite" aria-busy="false" data-page-title="<?= esc($headerTitle) ?>">
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
        // Sidebar toggle functions (global for onclick handlers)
        function toggleSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar?.classList.toggle('open');
            overlay?.classList.toggle('active');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar?.classList.remove('open');
            overlay?.classList.remove('active');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Sync on SPA navigation
            document.addEventListener('spa:navigated', function() {
                // Small delay to ensure DOM is updated
                requestAnimationFrame(() => syncHeaderTitle());
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

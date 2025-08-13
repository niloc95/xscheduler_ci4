<?php
/**
 * Admin Sidebar Component
 * Reusable sidebar for admin dashboard views
 */
?>
<!-- Mobile Menu Backdrop -->
<div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 z-40 w-64 h-screen bg-white dark:bg-gray-800 material-shadow-lg transition-colors duration-300">
    <div class="h-full px-3 py-4 overflow-y-auto">
        <!-- Logo -->
        <div class="flex items-center justify-between mb-6 p-2">
            <div class="flex items-center">
                <md-icon-button class="text-blue-600 dark:text-blue-400">
                    <md-icon>schedule</md-icon>
                </md-icon-button>
                <span class="text-xl font-semibold text-gray-800 dark:text-gray-200 ml-2 transition-colors duration-300">xScheduler</span>
            </div>
            <md-icon-button id="closeSidebar" class="lg:hidden text-gray-600 dark:text-gray-400">
                <md-icon>close</md-icon>
            </md-icon-button>
        </div>
        
        <!-- Navigation -->
        <md-list>
            <md-list-item class="mb-1" <?= (isset($current_page) && $current_page === 'dashboard') ? 'selected' : '' ?>>
                <md-icon slot="start" class="text-gray-600 dark:text-gray-300">dashboard</md-icon>
                <div slot="headline" class="text-gray-800 dark:text-gray-200">Dashboard</div>
            </md-list-item>

            <md-list-item class="mb-1" <?= (isset($current_page) && $current_page === 'schedule') ? 'selected' : '' ?>>
                <md-icon slot="start" class="text-gray-600 dark:text-gray-300">event</md-icon>
                <div slot="headline" class="text-gray-800 dark:text-gray-200">Schedule</div>
            </md-list-item>

            <md-list-item class="mb-1" <?= (isset($current_page) && $current_page === 'users') ? 'selected' : '' ?>>
                <md-icon slot="start" class="text-gray-600 dark:text-gray-300">people</md-icon>
                <div slot="headline" class="text-gray-800 dark:text-gray-200">Users</div>
            </md-list-item>

            <md-list-item class="mb-1" <?= (isset($current_page) && $current_page === 'analytics') ? 'selected' : '' ?>>
                <md-icon slot="start" class="text-gray-600 dark:text-gray-300">analytics</md-icon>
                <div slot="headline" class="text-gray-800 dark:text-gray-200">Analytics</div>
            </md-list-item>

            <md-list-item class="mb-1" <?= (isset($current_page) && $current_page === 'notifications') ? 'selected' : '' ?>>
                <md-icon slot="start" class="text-gray-600 dark:text-gray-300">notifications</md-icon>
                <div slot="headline" class="text-gray-800 dark:text-gray-200">Notifications</div>
            </md-list-item>

            <md-divider class="my-4"></md-divider>

            <md-list-item class="mb-1" <?= (isset($current_page) && $current_page === 'settings') ? 'selected' : '' ?>>
                <md-icon slot="start" class="text-gray-600 dark:text-gray-300">settings</md-icon>
                <div slot="headline" class="text-gray-800 dark:text-gray-200">Settings</div>
            </md-list-item>

            <md-list-item class="mb-1" <?= (isset($current_page) && $current_page === 'help') ? 'selected' : '' ?>>
                <md-icon slot="start" class="text-gray-600 dark:text-gray-300">help</md-icon>
                <div slot="headline" class="text-gray-800 dark:text-gray-200">Help</div>
            </md-list-item>
        </md-list>
    </div>
</div>

<style>
/* Admin Sidebar Styles */
.sidebar { width: 16rem; }
@media (min-width: 1024px) {
    .sidebar {
        position: fixed; top: 0; left: 0; height: 100vh; z-index: 40; transform: translateX(0);
    }
}
/* Mobile: sidebar overlay */
@media (max-width: 1023px) {
    .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
    .sidebar.open { transform: translateX(0); }
}

/* Sidebar nav icon consistency */
#sidebar md-list md-icon[slot="start"] { font-size: 22px; line-height: 1; }
#sidebar md-list md-list-item { border-radius: 0.5rem; }
#sidebar md-list md-list-item:hover { background: rgba(0,0,0,0.04); }
html.dark #sidebar md-list md-list-item:hover { background: rgba(255,255,255,0.06); }
#sidebar md-list md-list-item[selected] { background: rgba(59,130,246,0.12); }
html.dark #sidebar md-list md-list-item[selected] { background: rgba(96,165,250,0.16); }
</style>

<script>
// Admin Sidebar JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu functionality
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');

    function openSidebar() {
        sidebar.classList.add('open');
        backdrop.classList.remove('hidden');
    }

    function closeSidebarFn() {
        sidebar.classList.remove('open');
        backdrop.classList.add('hidden');
    }

    menuToggle?.addEventListener('click', openSidebar);
    closeSidebar?.addEventListener('click', closeSidebarFn);
    backdrop?.addEventListener('click', closeSidebarFn);
});
</script>

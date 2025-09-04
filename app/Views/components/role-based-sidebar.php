<?php
helper('permissions');
/**
 * Role-Based Admin Sidebar Component
 * Shows different navigation options based on user role and permissions
 */

$currentUser = session()->get('user');
$currentRole = current_user_role();
?>
<!-- Mobile Menu Backdrop -->
<div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar w-64 border-r border-gray-200 dark:border-gray-700">
    <div class="h-full px-4 py-6 overflow-y-auto">
    <!-- Logo -->
    <div class="flex items-center justify-between mb-8 p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white/70 dark:bg-gray-800/60">
            <div class="flex items-center">
        <?php $logoUrl = setting_url('general.company_logo'); ?>
        <?php if ($logoUrl): ?>
                <img src="<?= esc($logoUrl) ?>" alt="Company logo" class="w-11 h-11 rounded-xl object-contain bg-white dark:bg-gray-800 p-1 mr-3 shadow-lg border border-gray-200 dark:border-gray-700" />
        <?php else: ?>
        <div class="w-11 h-11 bg-slate-900 dark:bg-slate-700 rounded-xl flex items-center justify-center mr-3 shadow-lg">
                    <span class="material-symbols-rounded text-white text-xl">calendar_month</span>
                </div>
        <?php endif; ?>
                <span class="text-xl font-bold text-slate-100 tracking-tight">xScheduler</span>
            </div>
            <button id="closeSidebar" class="lg:hidden p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-700/50 rounded-xl transition-all duration-200">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
        
        <!-- Navigation -->
        <nav class="space-y-1">
            <!-- Dashboard - Available to all authenticated users -->
            <a href="<?= base_url('/dashboard') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'dashboard') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">dashboard</span>
                <span class="nav-text">Dashboard</span>
            </a>

            <!-- Schedule/Calendar - Available to admin, provider, staff -->
            <?php if (has_role(['admin', 'provider', 'staff'])): ?>
            <a href="<?= base_url('/scheduler') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'schedule') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">calendar_month</span>
                <span class="nav-text">Schedule</span>
            </a>
            <?php endif; ?>

            <!-- Appointments - Available to all users (different views based on role) -->
            <a href="<?= base_url('/appointments') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'appointments') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">event</span>
                <span class="nav-text">Appointments</span>
            </a>

            <?php if (has_role(['admin', 'provider'])): ?>
            <div class="nav-divider"></div>

            <!-- User Management - Admin and Provider only -->
            <a href="<?= base_url('/user-management') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'user-management') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">group</span>
                <span class="nav-text">User Management</span>
            </a>

            <!-- Services - Admin and Provider only -->
            <a href="<?= base_url('/services') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'services') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">design_services</span>
                <span class="nav-text">Services</span>
            </a>

            <!-- Analytics - Admin and Provider only -->
            <a href="<?= base_url('/analytics') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'analytics') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">analytics</span>
                <span class="nav-text">Analytics</span>
            </a>
            <?php endif; ?>

            <?php if (has_role(['admin'])): ?>
            <div class="nav-divider"></div>

            <!-- Settings - Admin only -->
            <a href="<?= base_url('/settings') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'settings') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">settings</span>
                <span class="nav-text">Settings</span>
            </a>
            <?php endif; ?>

            <div class="nav-divider"></div>

            <!-- Notifications - Available to all -->
            <a href="<?= base_url('/notifications') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'notifications') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">notifications</span>
                <span class="nav-text">Notifications</span>
            </a>

            <!-- Profile - Available to all -->
            <a href="<?= base_url('/profile') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'profile') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">account_circle</span>
                <span class="nav-text">Profile</span>
            </a>

            <!-- Help - Available to all -->
            <a href="<?= base_url('/help') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'help') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">help</span>
                <span class="nav-text">Help</span>
            </a>
        </nav>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

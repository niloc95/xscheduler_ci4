<?php
helper('permissions');
/**
 * Unified Sidebar Component
 * Clean, consistent sidebar implementation
 */

$currentUser = session()->get('user');
$currentRole = current_user_role();
$bookingShareUrl = rtrim(base_url(), '/') . '/booking';
?>

<!-- Sidebar -->
<aside id="main-sidebar" class="unified-sidebar xs-sidebar">
    <div class="sidebar-content">
        <!-- Logo Section -->
        <div class="sidebar-header">
            <div class="brand-section">
                <?php $logoUrl = setting_url('general.company_logo', 'assets/settings/default-logo.svg'); ?>
                <?php if ($logoUrl): ?>
                    <img src="<?= esc($logoUrl) ?>" alt="Company logo" class="brand-logo" />
                <?php else: ?>
                    <div class="brand-logo-placeholder">
                        <span class="brand-icon material-symbols-outlined">calendar_month</span>
                    </div>
                <?php endif; ?>
                <?php 
                $brandName = trim((string) setting('general.company_name', ''));
                if ($brandName === '') { $brandName = 'WebScheduler'; }
                ?>
                <span class="brand-name" id="sidebarBrandName"><?= esc($brandName) ?></span>
                <button id="sidebar-close-btn" class="close-button lg:hidden">
                    <span class="close-icon material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="sidebar-nav">
            <!-- Dashboard - Available to all authenticated users -->
            <a href="<?= base_url('/dashboard') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'dashboard') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">dashboard</span>
                <span class="nav-text">Dashboard</span>
            </a>

            <!-- Appointments - Available to all users -->
            <a href="<?= base_url('/appointments') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'appointments') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">event</span>
                <span class="nav-text">Appointments</span>
            </a>

            <!-- Customers - below Appointments; admin/provider/staff -->
            <?php if (has_role(['admin', 'provider', 'staff'])): ?>
            <a href="<?= base_url('/customer-management') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'customer-management') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">groups</span>
                <span class="nav-text">Customers</span>
            </a>
            <?php endif; ?>

            <?php if (has_role(['admin', 'provider'])): ?>
            <div class="nav-divider"></div>

            <!-- User Management -->
            <a href="<?= base_url('/user-management') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'user-management') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">group</span>
                <span class="nav-text">User Management</span>
            </a>

            <!-- Services -->
            <a href="<?= base_url('/services') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'services') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">design_services</span>
                <span class="nav-text">Services</span>
            </a>

            <!-- Analytics -->
            <a href="<?= base_url('/analytics') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'analytics') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">analytics</span>
                <span class="nav-text">Analytics</span>
            </a>
            <?php endif; ?>

            <?php if (has_role(['admin'])): ?>
            <div class="nav-divider"></div>

            <!-- Settings - Admin only -->
            <a href="<?= base_url('/settings') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'settings') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">settings</span>
                <span class="nav-text">Settings</span>
            </a>
            <?php endif; ?>

            <div class="nav-divider"></div>

            <!-- Notifications -->
            <a href="<?= base_url('/notifications') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'notifications') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">notifications</span>
                <span class="nav-text">Notifications</span>
            </a>

            <!-- Profile -->
            <a href="<?= base_url('/profile') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'profile') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">account_circle</span>
                <span class="nav-text">Profile</span>
            </a>

            <!-- Help -->
            <a href="<?= base_url('/help') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'help') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">help</span>
                <span class="nav-text">Help</span>
            </a>

            <!-- Role Switcher (visible when user has multiple roles) -->
            <?php if ((is_array($currentUser['roles'] ?? null) && count($currentUser['roles']) > 1)): ?>
            <div class="nav-divider sidebar-rail-hidden"></div>
            <div class="px-4 py-2 sidebar-rail-hidden">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Current Role:</p>
                <div class="role-switcher-dropdown">
                    <select id="roleSwitcher" class="w-full px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                        <?php foreach ($currentUser['roles'] as $role): ?>
                            <option value="<?= esc($role) ?>" <?= ($currentRole === $role) ? 'selected' : '' ?>>
                                <?= ucfirst(esc($role)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer -->
    <div class="nav-divider"></div>
    <?php if (has_role(['admin', 'provider', 'staff'])): ?>
    <div class="px-3 pb-3 sidebar-footer-actions">
            <div class="relative" id="booking-share-wrapper" data-booking-url="<?= esc($bookingShareUrl) ?>">
                <button
                    id="booking-share-toggle"
                    type="button"
                    class="w-full flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    aria-haspopup="menu"
                    aria-controls="booking-share-menu"
                    aria-expanded="false"
                >
                    <span class="material-symbols-outlined text-lg">share</span>
                    <span class="nav-text sidebar-share-label">Share Booking Page</span>
                </button>

                <div
                    id="booking-share-menu"
                    class="hidden absolute z-30 left-0 right-0 bottom-full mb-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1"
                    role="menu"
                    aria-labelledby="booking-share-toggle"
                >
                    <button type="button" class="booking-share-action w-full flex items-center gap-2 px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700" data-action="email" role="menuitem">
                        <span class="material-symbols-outlined text-base">mail</span>
                        <span>Share via Email</span>
                    </button>
                    <button type="button" class="booking-share-action w-full flex items-center gap-2 px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700" data-action="whatsapp" role="menuitem">
                        <span class="material-symbols-outlined text-base">chat</span>
                        <span>Share via WhatsApp</span>
                    </button>
                    <button type="button" class="booking-share-action w-full flex items-center gap-2 px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700" data-action="copy" role="menuitem">
                        <span class="material-symbols-outlined text-base">content_copy</span>
                        <span>Copy Link</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="nav-divider sidebar-rail-hidden"></div>
    <?php endif; ?>
    <div class="px-4 pb-4 text-xs text-gray-500 dark:text-gray-500 text-center sidebar-footer-branding">
            <a href="https://webschedulr.co.za" target="_blank" rel="noopener noreferrer" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">
                Engineered by WebScheduler
            </a>
        </div>
    </div>
</aside>

<?php
helper('permissions');
/**
 * Unified Sidebar Component
 * Clean, consistent sidebar implementation
 */

$currentUser = session()->get('user');
$currentRole = current_user_role();
?>

<!-- Mobile Menu Backdrop -->
<div id="mobile-backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-[100] hidden lg:hidden transition-opacity duration-300"></div>

<!-- Sidebar -->
<aside id="main-sidebar" class="unified-sidebar">
    <div class="sidebar-content">
        <!-- Logo Section -->
        <div class="sidebar-header">
            <div class="brand-section">
                <?php $logoUrl = setting_url('general.company_logo'); ?>
                <?php if ($logoUrl): ?>
                    <img src="<?= esc($logoUrl) ?>" alt="Company logo" class="brand-logo" />
                <?php else: ?>
                    <div class="brand-logo-placeholder">
                        <span class="brand-icon material-symbols-rounded">calendar_month</span>
                    </div>
                <?php endif; ?>
                <?php 
                $brandName = trim((string) setting('general.company_name', ''));
                if ($brandName === '') { $brandName = 'WebSchedulr'; }
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

            <!-- Schedule/Calendar - Available to admin, provider, staff -->
            <?php if (has_role(['admin', 'provider', 'staff'])): ?>
            <a href="<?= base_url('/scheduler') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'schedule') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">calendar_month</span>
                <span class="nav-text">Schedule</span>
            </a>
            <?php endif; ?>

            <!-- Appointments - Available to all users -->
            <a href="<?= base_url('/appointments') ?>" class="nav-link <?= (isset($current_page) && $current_page === 'appointments') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">event</span>
                <span class="nav-text">Appointments</span>
            </a>

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
        </nav>

        <!-- Sidebar Footer -->
    <div class="nav-divider"></div>
    <div class="px-4 pb-4 text-xs text-gray-500 dark:text-gray-500 text-center">
            <a href="https://webschedulr.co.za" target="_blank" rel="noopener noreferrer" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">
                Engineered by WebSchedulr
            </a>
        </div>
    </div>
</aside>

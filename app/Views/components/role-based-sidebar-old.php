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
        <?php 
        $brandName = trim((string) setting('general.company_name', ''));
        if ($brandName === '') { $brandName = 'WebSchedulr'; }
        ?>
        <span class="text-xl font-bold text-slate-100 tracking-tight" id="sidebarBrandName"><?= esc($brandName) ?></span>
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
                <span class="nav-text">
                    <?php if (is_staff()): ?>
                        My Schedule
                    <?php else: ?>
                        Schedule
                    <?php endif; ?>
                </span>
            </a>
            <?php endif; ?>

            <!-- Appointments - Available to all users (different views based on role) -->
            <a href="<?= base_url('/appointments') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'appointments') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">event</span>
                <span class="nav-text">
                    <?php if (is_customer()): ?>
                        My Appointments
                    <?php else: ?>
                        Appointments
                    <?php endif; ?>
                </span>
            </a>

            <?php if (has_role(['admin', 'provider'])): ?>
            <div class="nav-divider"></div>

            <!-- User Management - Admin and Provider only -->
            <a href="<?= base_url('/user-management') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'user-management') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">group</span>
                <span class="nav-text">
                    <?php if (is_admin()): ?>
                        User Management
                    <?php else: ?>
                        My Staff
                    <?php endif; ?>
                </span>
                <?php if (is_provider()): ?>
                    <?php 
                    $staffCount = count((new \App\Models\UserModel())->getStaffForProvider(current_user_id()));
                    if ($staffCount > 0): ?>
                    <span class="ml-auto text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded-full">
                        <?= $staffCount ?>
                    </span>
                    <?php endif; ?>
                <?php endif; ?>
            </a>

            <!-- Services & Categories - Admin and Provider only -->
            <a href="<?= base_url('/services') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'services') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">design_services</span>
                <span class="nav-text">Services</span>
            </a>
            <?php endif; ?>

            <!-- Analytics - Admin and Provider only -->
            <?php if (has_role(['admin', 'provider'])): ?>
            <a href="<?= base_url('/analytics') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'analytics') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">analytics</span>
                <span class="nav-text">
                    <?php if (is_admin()): ?>
                        Analytics
                    <?php else: ?>
                        My Analytics
                    <?php endif; ?>
                </span>
            </a>
            <?php endif; ?>

            <!-- Notifications - Available to all -->
            <a href="<?= base_url('/notifications') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'notifications') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">notifications</span>
                <span class="nav-text">Notifications</span>
            </a>

            <div class="nav-divider"></div>

            <!-- Settings - Admin only -->
            <?php if (is_admin()): ?>
            <a href="<?= base_url('/settings') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'settings') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">settings</span>
                <span class="nav-text">System Settings</span>
            </a>
            <?php endif; ?>

            <!-- Profile Settings - Available to all -->
            <a href="<?= base_url('/profile') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'profile') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">account_circle</span>
                <span class="nav-text">My Profile</span>
            </a>

            <!-- Help -->
            <a href="<?= base_url('/help') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'help') ? 'active' : '' ?>">
                <span class="nav-icon material-symbols-outlined">help</span>
                <span class="nav-text">Help</span>
            </a>

            <div class="nav-divider"></div>

            <div class="px-4 pb-2 text-xs text-slate-400 text-center">
                <a href="https://webschedulr.co.za" target="_blank" rel="noopener noreferrer" class="hover:text-slate-200 transition-colors duration-200">
                    Engineered by WebSchedulr
                </a>
            </div>

            <!-- Logout -->
            <a href="<?= base_url('/auth/logout') ?>" class="nav-item text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                <span class="nav-icon material-symbols-outlined">logout</span>
                <span class="nav-text">Sign Out</span>
            </a>
        </nav>
    </div>
</div>

<style>
    /* Sidebar Styles - reuse existing styles from admin-sidebar.php */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 16rem; /* w-64 */
        z-index: 40;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        /* Material-like subtle elevation */
        box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(20px);
        /* Light theme default */
        background: rgba(255, 255, 255, 0.95);
        border-right: 1px solid rgba(229, 231, 235, 0.7);
        animation: slideInFromLeft 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Hide on mobile by default */
    @media (max-width: 1023px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
    }

    @keyframes slideInFromLeft {
        from {
            opacity: 0;
            transform: translateX(-20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
    }

    html.dark .sidebar {
        background: rgba(15, 20, 25, 0.95);
        border-right-color: rgba(45, 55, 72, 0.35);
        /* Adjusted elevation for dark mode */
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.3);
    }
                    0px 6px 8px 0px rgba(0, 0, 0, 0.35),
                    0px 1px 12px 0px rgba(0, 0, 0, 0.3);
    }

    .sidebar .text-xl {
        color: #1f2937;
        font-weight: 600;
    }

    html.dark .sidebar .text-xl {
        color: #f7fafc;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 14px 18px;
        margin: 6px 12px;
        border-radius: 14px;
        text-decoration: none;
        color: #334155;
        transition: all 0.2s ease;
        position: relative;
        cursor: pointer;
        background: transparent;
        border: none;
        outline: none;
    }

    html.dark .nav-item { color: #a0aec0; }

    .nav-item:hover {
        background: rgba(247, 127, 0, 0.12);
        color: #0f172a;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(247, 127, 0, 0.15);
    }

    html.dark .nav-item:hover { color: #f7fafc; }

    .nav-item.active {
        background: linear-gradient(135deg, #F77F00 0%, #FCBF49 100%);
        color: #003049;
        box-shadow: 0 8px 20px rgba(247, 127, 0, 0.35);
        transform: translateY(-2px);
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: -12px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 28px;
        background: linear-gradient(to bottom, #F77F00, #FCBF49);
        border-radius: 2px;
        box-shadow: 0 0 8px rgba(247, 127, 0, 0.5);
    }

    .nav-icon {
        width: 20px;
        height: 20px;
        margin-right: 12px;
        flex-shrink: 0;
        stroke-width: 2;
        opacity: 0.8;
    }

    .nav-item:hover .nav-icon,
    .nav-item.active .nav-icon {
        opacity: 1;
    }

    .nav-text {
        font-size: 0.9rem;
        font-weight: 500;
        flex: 1;
        white-space: nowrap;
        letter-spacing: 0.025em;
    }

    .nav-divider {
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(45, 55, 72, 0.6), transparent);
        margin: 20px 16px;
        opacity: 0.8;
        border-radius: 1px;
    }

    @media (max-width: 1023px) {
        .sidebar {
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            border-radius: 0;
            transform: translateX(-100%);
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        .nav-item {
            padding: 16px 20px;
            margin: 4px 12px;
            border-radius: 12px;
        }
        
        .nav-icon {
            width: 24px;
            height: 24px;
            margin-right: 16px;
        }
        
        .nav-text {
            font-size: 1rem;
        }
    }

    .nav-item:focus {
        outline: 2px solid #6366f1;
        outline-offset: 2px;
    }

    .nav-item:focus:not(:focus-visible) {
        outline: none;
    }

    .nav-item {
        overflow: hidden;
    }

    .nav-item::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s;
    }

    .nav-item:hover::after {
        left: 100%;
    }
</style>

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

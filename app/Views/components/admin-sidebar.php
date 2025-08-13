<?php
/**
 * Admin Sidebar Component
 * Reusable sidebar for admin dashboard views
 */
?>
<!-- Mobile Menu Backdrop -->
<div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-4 left-4 z-40 w-64 h-[calc(100vh-2rem)] rounded-2xl shadow-2xl">
    <div class="h-full px-4 py-6 overflow-y-auto">
        <!-- Logo -->
        <div class="flex items-center justify-between mb-8 p-3 bg-gradient-to-r from-blue-500/10 to-purple-600/10 rounded-xl border border-blue-500/20">
            <div class="flex items-center">
                <div class="w-11 h-11 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mr-3 shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">xScheduler</span>
            </div>
            <button id="closeSidebar" class="lg:hidden p-2 text-gray-400 hover:text-gray-200 hover:bg-gray-700/50 rounded-xl transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Navigation -->
        <nav class="space-y-1">
            <a href="<?= base_url('/dashboard') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'dashboard') ? 'active' : '' ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v0M8 5a2 2 0 000 4h8a2 2 0 000-4M8 5v0"></path>
                </svg>
                <span class="nav-text">Dashboard</span>
            </a>

            <a href="<?= base_url('/schedule') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'schedule') ? 'active' : '' ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="nav-text">Schedule</span>
            </a>

            <a href="<?= base_url('/users') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'users') ? 'active' : '' ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span class="nav-text">Users</span>
            </a>

            <a href="<?= base_url('/analytics') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'analytics') ? 'active' : '' ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="nav-text">Analytics</span>
            </a>

            <a href="<?= base_url('/notifications') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'notifications') ? 'active' : '' ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.73 21a2 2 0 01-3.46 0"></path>
                </svg>
                <span class="nav-text">Notifications</span>
            </a>

            <div class="nav-divider"></div>

            <a href="<?= base_url('/settings') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'settings') ? 'active' : '' ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span class="nav-text">Settings</span>
            </a>

            <a href="<?= base_url('/help') ?>" class="nav-item <?= (isset($current_page) && $current_page === 'help') ? 'active' : '' ?>">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="nav-text">Help</span>
            </a>
        </nav>
    </div>
</div>

<style>
    /* Sidebar Styles */
    .sidebar {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        background: rgba(31, 41, 55, 0.95);
        border: 1px solid rgba(55, 65, 81, 0.3);
        animation: slideInFromLeft 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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

    /* Dark mode background override */
    html.dark .sidebar {
        background: rgba(17, 24, 39, 0.95);
        border-color: rgba(75, 85, 99, 0.3);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.08);
    }

    /* Logo Area */
    .sidebar h2 {
        font-weight: 700;
        font-size: 1.5rem;
        letter-spacing: -0.025em;
        color: #ffffff;
        margin: 0;
        padding: 0;
    }

    .sidebar .text-xl {
        color: #ffffff;
        font-weight: 600;
    }

    /* Navigation Styles */
    .nav-item {
        display: flex;
        align-items: center;
        padding: 14px 18px;
        margin: 6px 12px;
        border-radius: 14px;
        text-decoration: none;
        color: #9ca3af;
        transition: all 0.2s ease;
        position: relative;
        cursor: pointer;
        background: transparent;
        border: none;
        outline: none;
    }

    .nav-item:hover {
        background: rgba(99, 102, 241, 0.12);
        color: #e5e7eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .nav-item.active {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: #ffffff;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);
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
        background: linear-gradient(to bottom, #6366f1, #8b5cf6);
        border-radius: 2px;
        box-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
    }

    /* Icon Styles */
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

    /* Text Styles */
    .nav-text {
        font-size: 0.9rem;
        font-weight: 500;
        flex: 1;
        white-space: nowrap;
        letter-spacing: 0.025em;
    }

    /* Divider */
    .nav-divider {
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(55, 65, 81, 0.6), transparent);
        margin: 20px 16px;
        opacity: 0.8;
        border-radius: 1px;
    }

    /* Mobile Styles */
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

    @media (min-width: 1024px) {
        .sidebar {
            position: fixed; 
            top: 1rem; 
            left: 1rem; 
            height: calc(100vh - 2rem); 
            z-index: 40; 
            transform: translateX(0);
        }
    }

    /* Focus States for Accessibility */
    .nav-item:focus {
        outline: 2px solid #6366f1;
        outline-offset: 2px;
    }

    .nav-item:focus:not(:focus-visible) {
        outline: none;
    }

    /* Animation for Active State */
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

    /* Card-like glow effect on sidebar */
    .sidebar::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 1rem;
        padding: 1px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3), transparent);
        mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        mask-composite: subtract;
        pointer-events: none;
    }

    /* Subtle inner glow */
    .sidebar::after {
        content: '';
        position: absolute;
        inset: 2px;
        border-radius: calc(1rem - 2px);
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05), transparent);
        pointer-events: none;
    }
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

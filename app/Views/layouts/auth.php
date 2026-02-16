<?php
/**
 * Authentication Layout
 * 
 * Minimal layout for login, registration, forgot password, and reset flows.
 * Clean, centered card design with dark mode support.
 * 
 * Sections available:
 * - title: Browser tab title
 * - head: Additional <head> content
 * - content: Auth form content
 * - footer: Optional footer content
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
    <title><?= $this->renderSection('title') ?: 'Sign In - WebSchedulr' ?></title>
    
    <!-- Prevent FOUC -->
    <script>
        (function() {
            const theme = localStorage.getItem('xs-theme') || 
                         (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
    
    <!-- Material Design Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen transition-colors duration-200">
    <!-- Dark Mode Toggle -->
    <div class="fixed top-4 right-4 z-50">
        <?= $this->include('components/dark-mode-toggle') ?>
    </div>
    
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <!-- Auth Card Container -->
        <div class="auth-card w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <!-- Logo Section -->
            <div class="px-8 pt-8 pb-6 text-center border-b border-gray-100 dark:border-gray-700">
                <?php $logoUrl = function_exists('setting_url') ? setting_url('general.company_logo', 'assets/settings/default-logo.svg') : null; ?>
                <?php if ($logoUrl): ?>
                    <img src="<?= esc($logoUrl) ?>" alt="Logo" class="h-16 w-auto mx-auto rounded-lg">
                <?php else: ?>
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-3xl">schedule</span>
                    </div>
                <?php endif; ?>
                <h1 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">
                    <?= $this->renderSection('auth_title') ?: 'Welcome' ?>
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    <?= $this->renderSection('auth_subtitle') ?: 'Sign in to continue' ?>
                </p>
            </div>
            
            <!-- Content Section -->
            <div class="px-8 py-6">
                <?= $this->renderSection('content') ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            <?php $footerContent = $this->renderSection('footer'); ?>
            <?php if (trim($footerContent)): ?>
                <?= $footerContent ?>
            <?php else: ?>
                <p>&copy; <?= date('Y') ?> WebSchedulr. All rights reserved.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script type="module" src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>

<?php
/**
 * Public Booking Layout
 * 
 * Customer-facing layout for public booking pages.
 * Clean, professional design optimized for conversion.
 * 
 * Sections available:
 * - title: Browser tab title
 * - head: Additional <head> content
 * - content: Main booking content
 * - scripts: Additional JavaScript
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
    <meta name="description" content="Book your appointment online">
    <title><?= $this->renderSection('title') ?: 'Book an Appointment' ?></title>
    
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
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-200">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
            <!-- Logo/Business Name -->
            <div class="flex items-center gap-3">
                <?php $logoUrl = function_exists('setting_url') ? setting_url('general.company_logo', 'assets/settings/default-logo.svg') : null; ?>
                <?php if ($logoUrl): ?>
                    <img src="<?= esc($logoUrl) ?>" alt="Logo" class="h-10 w-auto rounded-lg">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white">schedule</span>
                    </div>
                <?php endif; ?>
                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                    <?= esc($businessName ?? 'WebSchedulr') ?>
                </span>
            </div>
            
            <!-- Dark Mode Toggle -->
            <?= $this->include('components/dark-mode-toggle') ?>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-8">
        <?= $this->renderSection('content') ?>
    </main>
    
    <!-- Footer -->
    <footer class="border-t border-gray-200 dark:border-gray-700 mt-auto">
        <div class="max-w-4xl mx-auto px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>Powered by <a href="https://webschedulr.com" class="text-blue-600 dark:text-blue-400 hover:underline">WebSchedulr</a></p>
        </div>
    </footer>
    
    <script type="module" src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>

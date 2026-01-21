<?php
/**
 * Setup/Installation Layout
 * 
 * Minimal layout for setup wizard and installation flows.
 * No sidebar, no authentication, step-based progression.
 * 
 * Sections available:
 * - title: Page title (appended to site name)
 * - head: Additional head content (CSS, meta tags)
 * - step_indicator: Setup step indicator
 * - content: Main page content
 * - footer: Footer content
 * - scripts: Additional JavaScript
 * 
 * Usage:
 * <?= $this->extend('layouts/setup') ?>
 * <?= $this->section('title') ?>Step 1 - Database<?= $this->endSection() ?>
 * <?= $this->section('content') ?>...<?= $this->endSection() ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $this->renderSection('title') ?> - WebSchedulr Setup</title>
    
    <!-- Preconnect to Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    
    <!-- App Styles -->
    <?php if (file_exists(FCPATH . 'build/assets/app.css')): ?>
    <link rel="stylesheet" href="<?= base_url('build/assets/app.css') ?>">
    <?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
    
    <?= $this->renderSection('head') ?>
</head>
<body class="min-h-full bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 antialiased">
    
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="py-8">
            <div class="max-w-xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-center gap-3">
                    <div class="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-xl">calendar_month</span>
                    </div>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">WebSchedulr</span>
                </div>
            </div>
        </header>
        
        <!-- Step Indicator -->
        <?php if ($this->renderSection('step_indicator')): ?>
        <div class="max-w-xl mx-auto px-4 sm:px-6 mb-8">
            <?= $this->renderSection('step_indicator') ?>
        </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <main class="flex-1 py-4">
            <div class="max-w-xl mx-auto px-4 sm:px-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <?= $this->renderSection('content') ?>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="py-8">
            <div class="max-w-xl mx-auto px-4 sm:px-6 text-center">
                <?php if ($this->renderSection('footer')): ?>
                    <?= $this->renderSection('footer') ?>
                <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    &copy; <?= date('Y') ?> WebSchedulr. All rights reserved.
                </p>
                <?php endif; ?>
            </div>
        </footer>
    </div>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- App Scripts -->
    <?php if (file_exists(FCPATH . 'build/assets/app.js')): ?>
    <script type="module" src="<?= base_url('build/assets/app.js') ?>"></script>
    <?php endif; ?>
    
    <?= $this->renderSection('scripts') ?>
</body>
</html>

<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?: 'xScheduler' ?></title>
    
    <!-- Dark mode initialization script (must be before any styling) -->
    <script>
        // Prevent flash of unstyled content by applying theme immediately
        (function() {
            const storedTheme = localStorage.getItem('xs-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = storedTheme || (prefersDark ? 'dark' : 'light');
            
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
    <script type="module" src="<?= base_url('build/assets/materialWeb.js') ?>"></script>
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-200">
    <div class="flex flex-col min-h-screen">
        <div class="flex-1">
            <main class="min-h-screen">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
        
        <?= $this->include('components/footer') ?>
    </div>
    
    <!-- Scripts -->
    <script src="<?= base_url('build/assets/main.js') ?>"></script>
    <script src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
</body>
</html>

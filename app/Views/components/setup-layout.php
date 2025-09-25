<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?: 'WebSchedulr' ?></title>
    
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
    <!-- Material Design Icons (Outlined + Rounded) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
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
    <script type="module" src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
</body>
</html>

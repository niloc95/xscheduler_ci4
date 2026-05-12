<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?: 'WebScheduler' ?></title>
    
    <!-- Dark mode initialization script (must be before any styling) -->
    <script type="module" src="<?= vite_js('resources/js/theme-bootstrap.js') ?>"></script>
    
    <?php foreach (vite_css('resources/scss/app-consolidated.scss') as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; ?>
    <!-- Material Design Icons (Outlined + Rounded) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
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
    
    <!-- Scripts (setup.js is loaded by the setup view via the head section) -->
    <script type="module" src="<?= vite_js('resources/js/dark-mode.js') ?>"></script>
</body>
</html>

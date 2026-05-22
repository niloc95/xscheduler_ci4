<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?: 'WebScheduler' ?></title>
    
    <!-- Prevent FOUC: inline blocking script — runs synchronously before first paint -->
    <script>!function(){var t=localStorage.getItem('xs-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.style.colorScheme=t;if(t==='dark')document.documentElement.style.backgroundColor='#111827';document.documentElement.classList.add('xs-no-transition')}();</script>
    
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
    <!-- Remove load-time FOUC guards after first paint -->
    <script>requestAnimationFrame(function(){requestAnimationFrame(function(){document.documentElement.classList.remove('xs-no-transition');document.documentElement.style.backgroundColor='';document.documentElement.style.colorScheme=''})});</script>
</body>
</html>

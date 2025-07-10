
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?: 'xScheduler' ?></title>
    <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
    <script type="module" src="<?= base_url('build/assets/materialWeb.js') ?>"></script>
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex flex-col min-h-screen">
        <?= $this->include('components/header') ?>
        
        <div class="flex-1">
            <main class="min-h-screen">
                <div class="page-container">
                    <?= $this->renderSection('content') ?>
                </div>
            </main>
        </div>
        
        <?= $this->include('components/footer') ?>
    </div>
    <script src="<?= base_url('build/assets/main.js') ?>"></script>
</body>
</html>

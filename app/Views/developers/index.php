<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>WebScheduler API Reference</title>
    <?php foreach (vite_css('resources/js/developers.js') as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; ?>
</head>
<body>
    <div id="dev-topbar">
        <span class="brand">WebScheduler API</span>
        <span>
            <a href="<?= site_url('developers/getting-started') ?>">Getting started</a>
            &nbsp;·&nbsp;
            <a href="<?= site_url('developers/openapi.yaml') ?>">Download spec</a>
        </span>
    </div>

    <div id="redoc-container" data-spec="<?= esc($specUrl, 'attr') ?>"></div>

    <script type="module" src="<?= vite_js('resources/js/developers.js') ?>"></script>
</body>
</html>

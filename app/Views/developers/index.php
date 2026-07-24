<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>WebScheduler API Reference</title>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; }
        #dev-topbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; padding: 0.75rem 1.25rem;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            background: #0f172a; color: #f8fafc;
            border-bottom: 1px solid #1e293b;
        }
        #dev-topbar a { color: #93c5fd; text-decoration: none; font-size: 0.9rem; }
        #dev-topbar a:hover { text-decoration: underline; }
        #dev-topbar .brand { font-weight: 600; font-size: 1rem; color: #f8fafc; }
        #redoc-container { display: block; }
    </style>
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

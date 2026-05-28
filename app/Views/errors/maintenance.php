<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance — WebScheduler</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f9fafb;
            color: #111827;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        p  { font-size: 0.95rem; color: #6b7280; line-height: 1.6; margin-bottom: 0.5rem; }
        .meta { font-size: 0.8rem; color: #9ca3af; margin-top: 1.5rem; background: #f3f4f6; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: left; }
        .meta dt { font-weight: 600; color: #6b7280; }
        .meta dd { margin: 0 0 0.5rem 0; font-family: monospace; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔧</div>
        <h1>Down for Maintenance</h1>
        <p>WebScheduler is temporarily unavailable while an update is being applied.</p>
        <p>Please check back shortly — this usually takes just a few minutes.</p>

        <?php if (!empty($since) || !empty($version) || !empty($phase)): ?>
        <dl class="meta">
            <?php if (!empty($version)): ?>
            <dt>Updating to</dt>
            <dd>v<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
            <?php if (!empty($phase)): ?>
            <dt>Phase</dt>
            <dd><?= htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
            <?php if (!empty($since)): ?>
            <dt>Started</dt>
            <dd><?= htmlspecialchars($since, ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
        </dl>
        <?php endif; ?>
    </div>
</body>
</html>

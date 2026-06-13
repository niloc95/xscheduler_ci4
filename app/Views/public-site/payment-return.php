<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Received — WebScheduler</title>
    <!-- Honor the shared xs-theme preference (same key as the booking SPA and admin app) -->
    <script>!function(){var t=localStorage.getItem('xs-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.style.colorScheme=t}();</script>
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
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.07);
            max-width: 460px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.4rem; font-weight: 700; margin-bottom: 0.5rem; }
        p  { font-size: 0.95rem; color: #6b7280; line-height: 1.6; margin-bottom: 0.5rem; }
        .back { display: inline-block; margin-top: 1.5rem; font-size: 0.875rem; color: #4f46e5; text-decoration: none; }
        .back:hover { text-decoration: underline; }
        html.dark body { background: #0f172a; color: #f1f5f9; }
        html.dark .card { background: #1e293b; border-color: #334155; box-shadow: 0 4px 24px rgba(0,0,0,.4); }
        html.dark p { color: #94a3b8; }
        html.dark .back { color: #818cf8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Payment Received</h1>
        <p>Thank you! Your deposit has been received.</p>
        <p>Your booking is confirmed — please check your email for the confirmation details.</p>
        <p style="font-size:0.8rem;color:#9ca3af;margin-top:1rem;">
            Gateway: <?= esc(ucfirst($gateway ?? '')) ?>
        </p>
        <a class="back" href="<?= base_url('booking') ?>">← Book another appointment</a>
    </div>
</body>
</html>

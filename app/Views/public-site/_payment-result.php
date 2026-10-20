<?php
/**
 * Shared standalone shell for the payment gateway return/cancel pages.
 *
 * @var string        $pageTitle
 * @var string        $icon       Emoji shown above the heading
 * @var string        $heading
 * @var list<string>  $paragraphs Plain-text paragraphs (escaped here)
 * @var string        $linkText
 * @var string|null   $footnote   Optional small print under the paragraphs
 */
helper('vite');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle) ?> — WebScheduler</title>
    <!-- Honor the shared xs-theme preference (same key as the booking SPA and admin app) -->
    <script {csp-script-nonce}>!function(){var t=localStorage.getItem('xs-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.style.colorScheme=t}();</script>
    <?php foreach (vite_css('resources/scss/app-consolidated.scss') as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; ?>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 p-6 text-gray-900 dark:bg-slate-900 dark:text-slate-100">
    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white px-8 py-10 text-center shadow-lg dark:border-slate-700 dark:bg-slate-800">
        <div class="mb-4 text-5xl" aria-hidden="true"><?= esc($icon) ?></div>
        <h1 class="mb-2 text-xl font-bold"><?= esc($heading) ?></h1>
        <?php foreach ($paragraphs as $paragraph): ?>
            <p class="mb-2 text-sm leading-relaxed text-gray-500 dark:text-slate-400"><?= esc($paragraph) ?></p>
        <?php endforeach; ?>
        <?php if (!empty($footnote)): ?>
            <p class="mt-4 text-xs text-gray-400"><?= esc($footnote) ?></p>
        <?php endif; ?>
        <a class="mt-6 inline-block text-sm text-indigo-600 hover:underline dark:text-indigo-400" href="<?= base_url('booking') ?>">← <?= esc($linkText) ?></a>
    </div>
</body>
</html>

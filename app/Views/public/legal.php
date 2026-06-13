<?php
helper(['vite']);

$compiledStyles = [];
try {
    $compiledStyles = vite_css('resources/scss/app-consolidated.scss');
} catch (\Throwable $e) {
    log_message('error', 'Public legal CSS asset missing: ' . $e->getMessage());
}

$termsBody = trim((string) ($terms ?? ''));
$privacyBody = trim((string) ($privacy ?? ''));
$cancelBody = trim((string) ($cancellationPolicy ?? ''));
$rescheduleBody = trim((string) ($reschedulingPolicy ?? ''));
$cookieBody = trim((string) ($cookieNotice ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Legal') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= setting_url('general.company_icon', 'assets/settings/default-icon.svg') ?>">
    <!-- Prevent FOUC: inline blocking script — sets .dark class and background before CSS loads -->
    <script>!function(){var t=localStorage.getItem('xs-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.style.colorScheme=t;if(t==='dark')document.documentElement.style.backgroundColor='#0f172a';document.documentElement.classList.add('xs-no-transition')}();</script>
    <?php foreach ($compiledStyles as $href): ?>
        <link rel="stylesheet" href="<?= esc($href) ?>">
    <?php endforeach; ?>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <div class="rounded-3xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 shadow-sm sm:p-8">
            <div class="mb-4">
                <a class="inline-flex items-center gap-2 rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 hover:border-blue-400 hover:text-blue-700 dark:hover:text-blue-300" href="<?= esc(base_url('booking')) ?>">
                    <span aria-hidden="true">&larr;</span>
                    <span>Back to bookings</span>
                </a>
            </div>
            <h1 class="text-2xl font-semibold">Legal</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Review our terms, privacy notice, and booking policies.</p>

            <nav class="mt-6 flex flex-wrap gap-2 text-sm">
                <a class="rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:border-blue-400 hover:text-blue-700 dark:hover:text-blue-300" href="#terms">Terms</a>
                <a class="rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:border-blue-400 hover:text-blue-700 dark:hover:text-blue-300" href="#privacy">Privacy</a>
                <a class="rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:border-blue-400 hover:text-blue-700 dark:hover:text-blue-300" href="#cancellation">Cancellation</a>
                <a class="rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:border-blue-400 hover:text-blue-700 dark:hover:text-blue-300" href="#rescheduling">Rescheduling</a>
                <a class="rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:border-blue-400 hover:text-blue-700 dark:hover:text-blue-300" href="#cookies">Cookies</a>
            </nav>

            <section id="terms" class="mt-8 border-t border-slate-100 dark:border-slate-700 pt-6">
                <h2 class="text-xl font-semibold">Terms & Conditions</h2>
                <?php if (!empty($termsUrl)): ?>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
                        External terms document:
                        <a class="text-blue-700 dark:text-blue-300 underline" href="<?= esc($termsUrl) ?>" target="_blank" rel="noopener"><?= esc($termsUrl) ?></a>
                    </p>
                <?php endif; ?>
                <div class="prose prose-slate mt-3 max-w-none whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= esc($termsBody !== '' ? $termsBody : 'Terms are currently being updated.') ?></div>
            </section>

            <section id="privacy" class="mt-8 border-t border-slate-100 dark:border-slate-700 pt-6">
                <h2 class="text-xl font-semibold">Privacy Policy</h2>
                <?php if (!empty($privacyUrl)): ?>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
                        External privacy policy:
                        <a class="text-blue-700 dark:text-blue-300 underline" href="<?= esc($privacyUrl) ?>" target="_blank" rel="noopener"><?= esc($privacyUrl) ?></a>
                    </p>
                <?php endif; ?>
                <div class="prose prose-slate mt-3 max-w-none whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= esc($privacyBody !== '' ? $privacyBody : 'Privacy policy is currently being updated.') ?></div>
            </section>

            <section id="cancellation" class="mt-8 border-t border-slate-100 dark:border-slate-700 pt-6">
                <h2 class="text-xl font-semibold">Cancellation Policy</h2>
                <div class="prose prose-slate mt-3 max-w-none whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= esc($cancelBody !== '' ? $cancelBody : 'Cancellation policy is currently being updated.') ?></div>
            </section>

            <section id="rescheduling" class="mt-8 border-t border-slate-100 dark:border-slate-700 pt-6">
                <h2 class="text-xl font-semibold">Rescheduling Policy</h2>
                <div class="prose prose-slate mt-3 max-w-none whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= esc($rescheduleBody !== '' ? $rescheduleBody : 'Rescheduling policy is currently being updated.') ?></div>
            </section>

            <section id="cookies" class="mt-8 border-t border-slate-100 dark:border-slate-700 pt-6">
                <h2 class="text-xl font-semibold">Cookie Notice</h2>
                <div class="prose prose-slate mt-3 max-w-none whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= esc($cookieBody !== '' ? $cookieBody : 'Cookie notice is currently being updated.') ?></div>
            </section>
        </div>
    </main>
    <!-- Remove load-time FOUC guards after first paint (restores transitions for interactions) -->
    <script>requestAnimationFrame(function(){requestAnimationFrame(function(){document.documentElement.classList.remove('xs-no-transition');document.documentElement.style.backgroundColor='';document.documentElement.style.colorScheme=''})});</script>
</body>
</html>

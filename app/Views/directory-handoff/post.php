<?= $this->extend('layouts/public') ?>

<?= $this->section('title') ?>Redirecting to the WebScheduler Directory<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="max-w-lg mx-auto text-center py-16">
    <div class="xs-card p-8">
        <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/10">
            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">arrow_outward</span>
        </div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Taking you to the WebScheduler Directory…</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            We're sending your basic business details so you don't have to retype them.
            You'll review everything on the directory before publishing.
        </p>

        <form id="directory-handoff-form" action="<?= esc($handoffUrl) ?>" method="post" class="mt-6">
            <input type="hidden" name="payload" value="<?= esc($payload, 'attr') ?>">
            <input type="hidden" name="sig" value="<?= esc($sig, 'attr') ?>">
            <button type="submit" class="xs-btn xs-btn-primary">Continue to the directory</button>
        </form>

        <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
            If you're not redirected automatically, click the button above.
        </p>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
    (function () {
        var form = document.getElementById('directory-handoff-form');
        if (form) { setTimeout(function () { form.submit(); }, 600); }
    })();
</script>
<?= $this->endSection() ?>

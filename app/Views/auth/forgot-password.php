<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Forgot Password - WebScheduler<?= $this->endSection() ?>
<?= $this->section('auth_title') ?>Forgot Password<?= $this->endSection() ?>
<?= $this->section('auth_subtitle') ?>Enter your email address and we'll send you a reset link<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <span class="material-symbols-outlined text-red-500 mr-2">error</span>
                <span class="text-red-700"><?= session()->getFlashdata('error') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center">
                <span class="material-symbols-outlined text-green-500 mr-2">check_circle</span>
                <span class="text-green-700"><?= session()->getFlashdata('success') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('auth/send-reset-link') ?>" method="post" class="space-y-6">
        <?= csrf_field() ?>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
            <div class="relative">
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= old('email') ?>"
                    required
                    class="w-full px-4 py-3 pl-12 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                    placeholder="Enter your email"
                    <?= isset($validation) && $validation->hasError('email') ? 'aria-invalid="true"' : '' ?>
                >
                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 material-symbols-outlined text-gray-400 dark:text-gray-500">email</span>
            </div>
            <?php if (isset($validation) && $validation->hasError('email')): ?>
                <div class="mt-1 text-sm text-red-600 dark:text-red-400">
                    <?= $validation->getError('email') ?>
                </div>
            <?php endif; ?>
        </div>

        <?= view('components/button', [
            'label' => 'Send Reset Link',
            'variant' => 'filled',
            'type' => 'submit',
            'icon' => 'send',
            'class' => 'w-full btn-brand',
        ]) ?>
    </form>

    <?php if (ENVIRONMENT === 'development'): ?>
        <div class="mt-6 p-4 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20">
            <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-blue-600 dark:text-blue-300">science</span>
                <div class="text-sm text-blue-800 dark:text-blue-200">
                    <p class="font-semibold">Dev Mailpit Reset Test</p>
                    <p class="mt-1">Submit this form with an existing user email, then open Mailpit to click and test the reset URL.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a
                            href="http://127.0.0.1:8025"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 rounded-md bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 text-xs font-medium"
                        >
                            <span class="material-symbols-outlined text-sm">open_in_new</span>
                            Open Mailpit Inbox
                        </a>
                        <code class="text-xs px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-100">npm run mailpit:start</code>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-8 text-center">
        <a href="<?= base_url('auth/login') ?>"
           class="link-brand-primary inline-flex items-center text-sm hover:text-blue-700 transition-colors">
            <span class="material-symbols-outlined mr-1 text-sm">arrow_back</span>
            Back to Login
        </a>
    </div>
<?= $this->endSection() ?>


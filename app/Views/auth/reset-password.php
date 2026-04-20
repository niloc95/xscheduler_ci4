<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Reset Password - WebScheduler<?= $this->endSection() ?>
<?= $this->section('auth_title') ?>Reset Password<?= $this->endSection() ?>
<?= $this->section('auth_subtitle') ?>Enter your new password below<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <span class="material-symbols-outlined text-red-500 mr-2">error</span>
                <span class="text-red-700"><?= session()->getFlashdata('error') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('auth/update-password') ?>" method="post" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= esc($token) ?>">

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>
            <div class="relative">
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    class="w-full px-4 py-3 pl-12 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                    placeholder="Enter new password"
                    <?= isset($validation) && $validation->hasError('password') ? 'aria-invalid="true"' : '' ?>
                >
                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 material-symbols-outlined text-gray-400 dark:text-gray-500">lock</span>
            </div>
            <?php if (isset($validation) && $validation->hasError('password')): ?>
                <div class="mt-1 text-sm text-red-600 dark:text-red-400">
                    <?= $validation->getError('password') ?>
                </div>
            <?php endif; ?>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Password must be at least 8 characters long
            </div>
        </div>

        <div>
            <label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>
            <div class="relative">
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    required
                    class="w-full px-4 py-3 pl-12 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                    placeholder="Confirm new password"
                    <?= isset($validation) && $validation->hasError('password_confirm') ? 'aria-invalid="true"' : '' ?>
                >
                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 material-symbols-outlined text-gray-400 dark:text-gray-500">lock</span>
            </div>
            <?php if (isset($validation) && $validation->hasError('password_confirm')): ?>
                <div class="mt-1 text-sm text-red-600 dark:text-red-400">
                    <?= $validation->getError('password_confirm') ?>
                </div>
            <?php endif; ?>
        </div>

        <?= view('components/button', [
            'label' => 'Update Password',
            'variant' => 'filled',
            'type' => 'submit',
            'icon' => 'check_circle',
            'class' => 'w-full btn-brand',
        ]) ?>
    </form>

    <div class="mt-8 text-center">
        <a href="<?= base_url('auth/login') ?>"
           class="link-brand-primary inline-flex items-center text-sm hover:text-blue-700 transition-colors">
            <span class="material-symbols-outlined mr-1 text-sm">arrow_back</span>
            Back to Login
        </a>
    </div>
<?= $this->endSection() ?>


<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Login - WebSchedulr<?= $this->endSection() ?>
<?= $this->section('auth_title') ?>Welcome back<?= $this->endSection() ?>
<?= $this->section('auth_subtitle') ?>Sign in to your account<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg transition-colors duration-200">
            <div class="flex items-center">
                <span class="material-symbols-outlined text-red-500 dark:text-red-400 mr-2">error</span>
                <span class="text-red-700 dark:text-red-300"><?= session()->getFlashdata('error') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg transition-colors duration-200">
            <div class="flex items-center">
                <span class="material-symbols-outlined text-green-500 dark:text-green-400 mr-2">check_circle</span>
                <span class="text-green-700 dark:text-green-300"><?= session()->getFlashdata('success') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('auth/attemptLogin') ?>" method="post" class="space-y-6">
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

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
            <div class="relative">
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    class="w-full px-4 py-3 pl-12 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                    placeholder="Enter your password"
                    <?= isset($validation) && $validation->hasError('password') ? 'aria-invalid="true"' : '' ?>
                >
                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 material-symbols-outlined text-gray-400 dark:text-gray-500">lock</span>
            </div>
            <?php if (isset($validation) && $validation->hasError('password')): ?>
                <div class="mt-1 text-sm text-red-600 dark:text-red-400">
                    <?= $validation->getError('password') ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center">
                <input type="checkbox" name="remember_me" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded transition-colors duration-200">
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
            </label>

            <a href="<?= base_url('auth/forgot-password') ?>"
               class="text-sm hover:opacity-80 transition-all duration-200 link-brand-primary">
                Forgot Password?
            </a>
        </div>

        <button type="submit" class="btn btn-brand btn-block">
            <span class="material-symbols-outlined mr-2">login</span>
            Sign In
        </button>
    </form>

    <div class="mt-8 text-center">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Don't have an account?
            <a href="<?= base_url('auth/register') ?>" class="hover:opacity-80 transition-all duration-200 font-medium link-brand-primary">
                Contact Administrator
            </a>
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-500 mt-4">
            <a href="https://webschedulr.co.za" target="_blank" rel="noopener noreferrer" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">
                Engineered by WebSchedulr
            </a>
        </p>
    </div>
<?= $this->endSection() ?>

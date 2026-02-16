<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Reset Password - WebSchedulr<?= $this->endSection() ?>
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
            <md-outlined-text-field
                label="New Password"
                type="password"
                name="password"
                required
                class="w-full"
                <?= isset($validation) && $validation->hasError('password') ? 'error' : '' ?>>
                <span slot="leading-icon" class="material-symbols-outlined">lock</span>
            </md-outlined-text-field>
            <?php if (isset($validation) && $validation->hasError('password')): ?>
                <div class="mt-1 text-sm text-red-600">
                    <?= $validation->getError('password') ?>
                </div>
            <?php endif; ?>
            <div class="mt-1 text-xs text-gray-500">
                Password must be at least 8 characters long
            </div>
        </div>

        <div>
            <md-outlined-text-field
                label="Confirm Password"
                type="password"
                name="password_confirm"
                required
                class="w-full"
                <?= isset($validation) && $validation->hasError('password_confirm') ? 'error' : '' ?>>
                <span slot="leading-icon" class="material-symbols-outlined">lock</span>
            </md-outlined-text-field>
            <?php if (isset($validation) && $validation->hasError('password_confirm')): ?>
                <div class="mt-1 text-sm text-red-600">
                    <?= $validation->getError('password_confirm') ?>
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-brand w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white transition-all duration-200">
            <span class="material-symbols-outlined mr-2">check_circle</span>
            Update Password
        </button>
    </form>

    <div class="mt-8 text-center">
        <a href="<?= base_url('auth/login') ?>"
           class="link-brand-primary inline-flex items-center text-sm hover:text-blue-700 transition-colors">
            <span class="material-symbols-outlined mr-1 text-sm">arrow_back</span>
            Back to Login
        </a>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('/build/assets/materialWeb.js') ?>"></script>
<?= $this->endSection() ?>

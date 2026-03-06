<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Forgot Password - WebSchedulr<?= $this->endSection() ?>
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
            <md-outlined-text-field
                label="Email Address"
                type="email"
                name="email"
                value="<?= old('email') ?>"
                required
                class="w-full"
                <?= isset($validation) && $validation->hasError('email') ? 'error' : '' ?>>
                <span slot="leading-icon" class="material-symbols-outlined">email</span>
            </md-outlined-text-field>
            <?php if (isset($validation) && $validation->hasError('email')): ?>
                <div class="mt-1 text-sm text-red-600">
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

    <div class="mt-8 text-center">
        <a href="<?= base_url('auth/login') ?>"
           class="link-brand-primary inline-flex items-center text-sm hover:text-blue-700 transition-colors">
            <span class="material-symbols-outlined mr-1 text-sm">arrow_back</span>
            Back to Login
        </a>
    </div>
<?= $this->endSection() ?>


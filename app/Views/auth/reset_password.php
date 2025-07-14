<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Reset Password - XScheduler' ?></title>
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link href="<?= base_url('/build/assets/style.css') ?>" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        
        /* Material Web Components custom properties */
        :root {
            --md-sys-color-primary: rgb(59, 130, 246);
            --md-sys-color-on-primary: rgb(255, 255, 255);
            --md-sys-color-surface: rgb(255, 255, 255);
            --md-sys-color-on-surface: rgb(17, 24, 39);
            --md-sys-color-surface-variant: rgb(248, 250, 252);
            --md-sys-color-outline: rgb(229, 231, 235);
            --md-sys-color-error: rgb(239, 68, 68);
        }
        
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .brand-logo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body>
    <div class="login-container flex items-center justify-center p-4">
        <div class="login-card w-full max-w-md rounded-2xl p-8">
            <!-- Logo and Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-3xl">password</span>
                    </div>
                </div>
                <h1 class="brand-logo text-3xl font-bold mb-2">Reset Password</h1>
                <p class="text-gray-600">Enter your new password below</p>
            </div>

            <!-- Flash Messages -->
            <?php if (session()->getFlashdata('error')): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <span class="material-symbols-outlined text-red-500 mr-2">error</span>
                        <span class="text-red-700"><?= session()->getFlashdata('error') ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reset Password Form -->
            <form action="<?= base_url('auth/update-password') ?>" method="post" class="space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= esc($token) ?>">
                
                <!-- New Password Field -->
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

                <!-- Confirm Password Field -->
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

                <!-- Submit Button -->
                <md-filled-button type="submit" class="w-full">
                    <span slot="icon" class="material-symbols-outlined">check</span>
                    Update Password
                </md-filled-button>
            </form>

            <!-- Back to Login -->
            <div class="mt-8 text-center">
                <a href="<?= base_url('auth/login') ?>" 
                   class="inline-flex items-center text-sm text-blue-600 hover:text-blue-500 transition-colors">
                    <span class="material-symbols-outlined mr-1 text-sm">arrow_back</span>
                    Back to Login
                </a>
            </div>
        </div>
    </div>

    <!-- Material Web Components -->
    <script src="<?= base_url('/build/assets/materialWeb.js') ?>"></script>
</body>
</html>

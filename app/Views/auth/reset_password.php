<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Reset Password - XScheduler' ?></title>
    
    <!-- Dark mode initialization script -->
    <script>
        (function() {
            const storedTheme = localStorage.getItem('xs-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = storedTheme || (prefersDark ? 'dark' : 'light');
            
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link href="<?= base_url('/build/assets/style.css') ?>" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
        }
        
        .login-card {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .dark .login-card {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
    <div class="login-container flex items-center justify-center p-4">
        <!-- Dark Mode Toggle (Top Right) -->
        <div class="absolute top-4 right-4">
            <?= $this->include('components/dark-mode-toggle') ?>
        </div>
        
        <div class="login-card bg-white dark:bg-gray-800 w-full max-w-md rounded-2xl p-8 border border-gray-200 dark:border-gray-700">
            <!-- Logo and Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center transition-colors duration-200" style="background-color: var(--md-sys-color-primary);">
                        <span class="material-symbols-outlined text-white text-3xl">password</span>
                    </div>
                </div>
                <h1 class="text-3xl font-bold mb-2 transition-colors duration-200" style="color: var(--md-sys-color-primary);">Reset Password</h1>
                <p class="text-gray-600 dark:text-gray-400 transition-colors duration-200">Enter your new password below</p>
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
                <button type="submit" class="w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white transition-all duration-200" style="background-color: #F77F00;">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Update Password
                </button>
            </form>

            <!-- Back to Login -->
            <div class="mt-8 text-center">
                <a href="<?= base_url('auth/login') ?>" 
                   class="inline-flex items-center text-sm hover:text-blue-700 transition-colors" style="color: #003049;">
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

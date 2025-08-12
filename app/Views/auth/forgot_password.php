<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Forgot Password - XScheduler' ?></title>
    
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
                        <span class="material-symbols-outlined text-white text-3xl">lock_reset</span>
                    </div>
                </div>
                <h1 class="text-3xl font-bold mb-2 transition-colors duration-200" style="color: var(--md-sys-color-primary);">Forgot Password</h1>
                <p class="text-gray-600 dark:text-gray-400 transition-colors duration-200">Enter your email address and we'll send you a link to reset your password</p>
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

            <?php if (session()->getFlashdata('success')): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <span class="material-symbols-outlined text-green-500 mr-2">check_circle</span>
                        <span class="text-green-700"><?= session()->getFlashdata('success') ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Forgot Password Form -->
            <form action="<?= base_url('auth/send-reset-link') ?>" method="post" class="space-y-6">
                <?= csrf_field() ?>
                
                <!-- Email Field -->
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

                <!-- Submit Button -->
                <button type="submit" class="w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white transition-all duration-200" style="background-color: #F77F00;">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Send Reset Link
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

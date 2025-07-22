<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Login - xScheduler' ?></title>
    
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
            --md-sys-color-primary: #003049;
            --md-sys-color-on-primary: rgb(255, 255, 255);
            --md-sys-color-surface: rgb(255, 255, 255);
            --md-sys-color-on-surface: #003049;
            --md-sys-color-surface-variant: #F3F4F6;
            --md-sys-color-outline: rgb(229, 231, 235);
            --md-sys-color-error: #D62828;
        }
        
        .login-container {
            min-height: 100vh;
            background-color: #F3F4F6;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .brand-logo {
            color: #003049;
        }
    </style>
</head>
<body>
    <div class="login-container flex items-center justify-center p-4">
        <div class="login-card w-full max-w-md rounded-2xl p-8">
            <!-- Logo and Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center" style="background-color: #003049;">
                        <span class="material-symbols-outlined text-white text-3xl">schedule</span>
                    </div>
                </div>
                <h1 class="brand-logo text-3xl font-bold mb-2">xScheduler</h1>
                <p class="text-gray-600">Sign in to your account</p>
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

            <!-- Login Form -->
            <form action="<?= base_url('auth/attemptLogin') ?>" method="post" class="space-y-6">
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

                <!-- Password Field -->
                <div>
                    <md-outlined-text-field 
                        label="Password" 
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
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <md-checkbox name="remember_me"></md-checkbox>
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                    
                    <a href="<?= base_url('auth/forgot-password') ?>" 
                       class="text-sm hover:text-blue-700 transition-colors" style="color: #003049;">
                        Forgot Password?
                    </a>
                </div>

                <!-- Login Button -->
                <button type="submit" class="w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white transition-all duration-200" style="background-color: #F77F00;">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 0v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Sign In
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="<?= base_url('auth/register') ?>" class="hover:text-blue-700 transition-colors font-medium" style="color: #003049;">
                        Contact Administrator
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Material Web Components -->
    <script src="<?= base_url('/build/assets/materialWeb.js') ?>"></script>
</body>
</html>

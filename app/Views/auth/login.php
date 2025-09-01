<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Login - xScheduler' ?></title>
    
    <!-- Dark mode initialization script (prevents flash) -->
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
                    <?php $logoUrl = setting_url('general.company_logo'); ?>
                    <?php if ($logoUrl): ?>
                        <img src="<?= esc($logoUrl) ?>" alt="Company logo" class="h-16 w-auto rounded-lg bg-white dark:bg-gray-800 p-2 border border-gray-200 dark:border-gray-700" style="object-fit: contain;" />
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-2xl flex items-center justify-center transition-colors duration-200" style="background-color: var(--md-sys-color-primary);">
                            <span class="material-symbols-outlined text-white text-3xl">schedule</span>
                        </div>
                    <?php endif; ?>
                </div>
                <h1 class="text-3xl font-bold mb-2 transition-colors duration-200" style="color: var(--md-sys-color-primary);">xScheduler</h1>
                <p class="text-gray-600 dark:text-gray-400 transition-colors duration-200">Sign in to your account</p>
            </div>

            <!-- Flash Messages -->
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

            <!-- Login Form -->
            <form action="<?= base_url('auth/attemptLogin') ?>" method="post" class="space-y-6">
                <?= csrf_field() ?>
                
                <!-- Email Field -->
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

                <!-- Password Field -->
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

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember_me" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded transition-colors duration-200">
                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                    </label>
                    
                    <a href="<?= base_url('auth/forgot-password') ?>" 
                       class="text-sm hover:opacity-80 transition-all duration-200" style="color: var(--md-sys-color-primary);">
                        Forgot Password?
                    </a>
                </div>

                <!-- Login Button -->
                <button type="submit" class="w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white transition-all duration-200 hover:opacity-90 focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 dark:focus:ring-offset-gray-800" style="background-color: var(--md-sys-color-secondary);">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 0v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Sign In
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Don't have an account? 
                    <a href="<?= base_url('auth/register') ?>" class="hover:opacity-80 transition-all duration-200 font-medium" style="color: var(--md-sys-color-primary);">
                        Contact Administrator
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= base_url('/build/assets/materialWeb.js') ?>"></script>
    <script type="module" src="<?= base_url('/build/assets/dark-mode.js') ?>"></script>
</body>
</html>

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

<header class="bg-transparent transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-brand px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="<?= base_url('/') ?>" class="text-xl font-bold text-gray-900 dark:text-white transition-colors duration-200">
                    <span style="color: var(--md-sys-color-primary);">xScheduler</span>
                </a>
            </div>
            
            <!-- Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <a href="<?= base_url('/') ?>" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">Dashboard</a>
                    <a href="<?= base_url('/setup') ?>" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">Setup</a>
                    <a href="<?= base_url('/tw') ?>" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">Tailwind Test</a>
                </nav>
            
            <!-- Theme Toggle & Mobile Menu -->
                <div class="flex items-center space-x-2">
                    <!-- Dark Mode Toggle -->
                    <?= $this->include('components/dark-mode-toggle') ?>
                    
                    <!-- Mobile menu button -->
                    <div class="md:hidden">
                        <button type="button" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
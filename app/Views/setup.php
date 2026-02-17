
/**
 * Setup View
 *
 * Initial setup wizard for xScheduler. Guides new users through system configuration, admin account creation, and environment checks.
 * Uses a minimal layout and includes setup-specific scripts and styles.
 */
<?= $this->extend('components/setup-layout') ?>

<?= $this->section('title') ?>xScheduler - Initial Setup<?= $this->endSection() ?>

<?= $this->section('head') ?>
<script>
window.appConfig = {
    baseURL: '<?= base_url() ?>',
    siteURL: '<?= site_url() ?>',
    csrfToken: '<?= csrf_token() ?>',
    csrfHeaderName: '<?= csrf_header() ?>'
};
</script>
<script type="module" src="<?= base_url('build/assets/setup.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Minimal Setup Header -->
<div class="w-full bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 transition-colors duration-200">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <!-- Logo -->
        <div class="flex items-center">
            <img src="<?= base_url('assets/settings/default-logo.svg') ?>" alt="WebSchedulr" class="w-8 h-8 mr-3 transition-colors duration-200" />
            <h1 class="text-xl font-bold text-gray-900 dark:text-white transition-colors duration-200">xScheduler</h1>
        </div>
        
        <!-- Theme Toggle & Contact -->
        <div class="flex items-center space-x-4">
            <!-- Dark Mode Toggle -->
            <?= $this->include('components/dark-mode-toggle') ?>
            
            <!-- Contact Button -->
            <a href="mailto:support@xscheduler.com" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Contact
            </a>
        </div>
    </div>
</div>    <div class="min-h-screen flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
        <div class="max-w-4xl w-full">
            <!-- Setup Header -->
            <div class="text-center mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center shadow-brand border border-gray-200 dark:border-gray-700 transition-colors duration-200">
                    <img src="<?= base_url('assets/settings/default-logo.svg') ?>" alt="WebSchedulr" class="w-10 h-10" />
                </div>
                <h1 class="text-3xl font-bold mb-2 transition-colors duration-200 text-md-primary">Welcome to xScheduler</h1>
                <p class="text-gray-600 dark:text-gray-400 transition-colors duration-200">Let's set up your scheduling application in just a few steps</p>
            </div>

            <!-- Setup Form Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-brand overflow-hidden border border-gray-200 dark:border-gray-700 transition-colors duration-200">                <!-- Progress Steps -->
                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 transition-colors duration-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="setup-step-active">
                                <span class="text-white text-sm font-semibold">1</span>
                            </div>
                            <span class="text-sm font-medium transition-colors duration-200 text-md-primary">Initial Configuration</span>
                        </div>
                        <div class="flex items-center space-x-2 opacity-50">
                            <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center transition-colors duration-200">
                                <span class="text-gray-600 dark:text-gray-400 text-sm font-semibold">2</span>
                            </div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400 transition-colors duration-200">Dashboard Access</span>
                        </div>
                    </div>
                </div>

            <!-- Setup Form -->
            <form id="setupForm" class="p-6 space-y-8">
                <?= csrf_field() ?>
                
                <!-- Admin Account Section -->                    <div class="space-y-6">
                        <div class="border-b border-gray-200 dark:border-gray-600 pb-4 transition-colors duration-200">
                            <h2 class="text-xl font-semibold flex items-center transition-colors duration-200 text-md-primary">
                                <svg class="w-6 h-6 mr-2 text-md-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                System Administrator Account
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 transition-colors duration-200">Create your admin account to manage the scheduling system</p>
                        </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="admin_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                Full Name <span class="text-red-500 dark:text-red-400">*</span>
                            </label>
                            <input 
                                type="text"
                                id="admin_name"
                                name="admin_name"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                placeholder="Enter your full name">
                            <div id="admin_name_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                        </div>

                        <div>
                            <label for="admin_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                Email Address <span class="text-red-500 dark:text-red-400">*</span>
                            </label>
                            <input 
                                type="email"
                                id="admin_email"
                                name="admin_email"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                placeholder="Enter your email address">
                            <div id="admin_email_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 transition-colors duration-200">Used for login and password recovery</p>
                        </div>

                        <div>
                            <label for="admin_userid" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                Admin User ID <span class="text-red-500 dark:text-red-400">*</span>
                            </label>
                            <input 
                                type="text"
                                id="admin_userid"
                                name="admin_userid"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                placeholder="Enter username (3-20 characters)">
                            <div id="admin_userid_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 transition-colors duration-200">Only letters and numbers allowed</p>
                        </div>

                        <div>
                            <label for="admin_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                Password <span class="text-red-500 dark:text-red-400">*</span>
                            </label>
                            <input 
                                type="password"
                                id="admin_password"
                                name="admin_password"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                placeholder="Minimum 8 characters">
                            <div id="admin_password_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                            <div id="password_strength" class="mt-1">
                                <div class="flex space-x-1">
                                    <div class="h-1 w-full bg-gray-200 dark:bg-gray-600 rounded strength-bar transition-colors duration-200" data-strength="1"></div>
                                    <div class="h-1 w-full bg-gray-200 dark:bg-gray-600 rounded strength-bar transition-colors duration-200" data-strength="2"></div>
                                    <div class="h-1 w-full bg-gray-200 dark:bg-gray-600 rounded strength-bar transition-colors duration-200" data-strength="3"></div>
                                    <div class="h-1 w-full bg-gray-200 dark:bg-gray-600 rounded strength-bar transition-colors duration-200" data-strength="4"></div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 transition-colors duration-200" id="password_strength_text">Password strength indicator</p>
                            </div>
                        </div>

                        <div>
                            <label for="admin_password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                Confirm Password <span class="text-red-500 dark:text-red-400">*</span>
                            </label>
                            <input 
                                type="password"
                                id="admin_password_confirm"
                                name="admin_password_confirm"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                placeholder="Re-enter your password">
                            <div id="admin_password_confirm_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                        </div>
                    </div>
                </div>

                <!-- Database Configuration Section -->                        <div class="space-y-6">
                            <div class="border-b border-gray-200 dark:border-gray-600 pb-4 transition-colors duration-200">
                                <h2 class="text-xl font-semibold flex items-center transition-colors duration-200 text-md-primary">
                                    <svg class="w-6 h-6 mr-2 text-md-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                    </svg>
                                    Database Configuration
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 transition-colors duration-200">Choose your preferred database setup</p>
                            </div>

                    <!-- Database Type Selection -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- MySQL Option -->
                            <label class="relative cursor-pointer">
                                <input type="radio" name="database_type" value="mysql" class="sr-only peer" id="db_mysql" checked>
                                <div class="border-2 border-gray-200 dark:border-gray-600 rounded-lg p-4 peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20 transition-all hover:border-gray-300 dark:hover:border-gray-500 bg-white dark:bg-gray-700">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-semibold text-gray-900 dark:text-white transition-colors duration-200 text-md-primary">MySQL Database</h3>
                                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                        </svg>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 transition-colors duration-200">Connect to an existing MySQL server</p>
                                    <ul class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1 transition-colors duration-200">
                                        <li>• High performance and scalability</li>
                                        <li>• Requires database server setup</li>
                                        <li>• Recommended for production</li>
                                    </ul>
                                </div>
                            </label>

                            <!-- SQLite Option (hidden — MySQL required for production) -->
                            <label class="relative cursor-pointer hidden">
                                <input type="radio" name="database_type" value="sqlite" class="sr-only peer" id="db_sqlite">
                                <div class="border-2 border-gray-200 dark:border-gray-600 rounded-lg p-4 peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20 transition-all hover:border-gray-300 dark:hover:border-gray-500 bg-white dark:bg-gray-700">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-semibold text-gray-900 dark:text-white transition-colors duration-200 text-md-primary">SQLite Database</h3>
                                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 transition-colors duration-200">Zero-configuration file-based database</p>
                                    <ul class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1 transition-colors duration-200">
                                        <li>• No server setup required</li>
                                        <li>• Perfect for getting started</li>
                                        <li>• Automatic database creation</li>
                                    </ul>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- MySQL Configuration Fields -->                        <div id="mysql_config" class="space-y-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-500 dark:border-blue-400 rounded-lg p-4 transition-colors duration-200">
                                <h4 class="font-medium mb-3 transition-colors duration-200 text-md-primary">MySQL Connection Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="mysql_hostname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                        Hostname <span class="text-red-500 dark:text-red-400">*</span>
                                    </label>
                                    <input 
                                        type="text"
                                        id="mysql_hostname"
                                        name="mysql_hostname"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                        value="localhost"
                                        placeholder="localhost">
                                    <div id="mysql_hostname_error" class="mt-1 text-sm text-red-600 hidden"></div>
                                </div>

                                <div>
                                    <label for="mysql_port" class="block text-sm font-medium text-gray-700 mb-2">
                                        Port <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="number"
                                        id="mysql_port"
                                        name="mysql_port"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        value="3306"
                                        placeholder="3306">
                                    <div id="mysql_port_error" class="mt-1 text-sm text-red-600 hidden"></div>
                                </div>

                                <div>
                                    <label for="mysql_database" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                        Database Name <span class="text-red-500 dark:text-red-400">*</span>
                                    </label>
                                    <input 
                                        type="text"
                                        id="mysql_database"
                                        name="mysql_database"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                        placeholder="xscheduler">
                                    <div id="mysql_database_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden transition-colors duration-200"></div>
                                </div>

                                <div>
                                    <label for="mysql_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                        Username <span class="text-red-500 dark:text-red-400">*</span>
                                    </label>
                                    <input 
                                        type="text"
                                        id="mysql_username"
                                        name="mysql_username"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                        placeholder="Database username">
                                    <div id="mysql_username_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden transition-colors duration-200"></div>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="mysql_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-200">
                                        Password
                                    </label>
                                    <input 
                                        type="password"
                                        id="mysql_password"
                                        name="mysql_password"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200"
                                        placeholder="Database password (optional)">
                                    <div id="mysql_password_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden transition-colors duration-200"></div>
                                </div>
                            </div>

                            <!-- Test Connection Button -->
                            <div class="mt-4 flex justify-end">
                                <button id="test_connection_btn" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-brand-ocean hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-ocean transition-all duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                                    </svg>
                                    Test Connection
                                </button>
                            </div>
                            <div id="connection_result" class="mt-2 hidden"></div>
                        </div>
                    </div>                        <!-- SQLite Configuration Display -->
                        <div id="sqlite_config" class="space-y-4 hidden">
                            <div class="bg-green-50 dark:bg-green-900/20 border border-gray-300 dark:border-green-600 rounded-lg p-4 transition-colors duration-200">
                                <h4 class="font-medium mb-2 transition-colors duration-200 text-md-primary">SQLite Auto-Configuration</h4>
                                <div class="flex items-start space-x-3">
                                    <svg class="w-5 h-5 mt-0.5 transition-colors duration-200 text-md-secondary" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>                                    <div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300 transition-colors duration-200">Database will be automatically created at:</p>
                                        <code class="block mt-1 text-xs px-2 py-1 rounded font-mono bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 transition-colors duration-200">
                                            ./writable/database/xscheduler.db
                                        </code>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 transition-colors duration-200">
                                            No additional configuration required. The application will create and manage the SQLite database automatically.
                                        </p>
                                    </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200 dark:border-gray-700 transition-colors duration-200">
                    <div class="flex-1"></div>
                    
                    <button id="setup_submit_btn" type="submit" class="btn-setup-submit">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Complete Setup
                    </button>
                </div>
            </form>
        </div>                <!-- Loading Overlay -->
                <div id="loading_overlay" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-sm w-full mx-4 transition-colors duration-200">
                        <div class="flex items-center space-x-3">
                            <div class="setup-spinner"></div>
                            <span class="text-gray-900 dark:text-gray-100 transition-colors duration-200">Processing setup...</span>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 transition-colors duration-200">
                                <div id="progress_bar" class="setup-progress-bar" style="width: 0%;"></div>
                            </div>
                            <p id="progress_text" class="text-sm text-gray-600 dark:text-gray-400 mt-2 transition-colors duration-200">Initializing...</p>
                        </div>
                    </div>
                </div>
    </div>
</div>

<!-- Setup wizard will be initialized by setup.js -->
<?= $this->endSection() ?>
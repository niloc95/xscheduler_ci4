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
<style>
/* Setup-specific styles */
.notification {
    animation: slideInRight 0.3s ease-out;
}

.notification.fade-out {
    animation: slideOutRight 0.3s ease-in;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.material-shadow {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.material-shadow:hover {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
</style>
<script type="module" src="<?= base_url('build/assets/setup.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Minimal Setup Header -->
<div class="w-full bg-white border-b border-gray-200">
    <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
        <!-- Logo -->
        <div class="flex items-center">
            <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <h1 class="text-xl font-bold text-gray-900">xScheduler</h1>
        </div>
        
        <!-- Contact Button -->
        <a href="mailto:support@xscheduler.com" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            Contact
        </a>
    </div>
</div>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="max-w-4xl w-full">
        <!-- Setup Header -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center material-shadow">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome to xScheduler</h1>
            <p class="text-gray-600">Let's set up your scheduling application in just a few steps</p>
        </div>

        <!-- Setup Form Card -->
        <div class="bg-white rounded-xl material-shadow overflow-hidden">
            <!-- Progress Steps -->
            <div class="bg-gray-50 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-semibold">1</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Initial Configuration</span>
                    </div>
                    <div class="flex items-center space-x-2 opacity-50">
                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                            <span class="text-gray-600 text-sm font-semibold">2</span>
                        </div>
                        <span class="text-sm font-medium text-gray-600">Dashboard Access</span>
                    </div>
                </div>
            </div>

            <!-- Setup Form -->
            <form id="setupForm" class="p-6 space-y-8">
                <?= csrf_field() ?>
                
                <!-- Admin Account Section -->
                <div class="space-y-6">
                    <div class="border-b border-gray-200 pb-4">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            System Administrator Account
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">Create your admin account to manage the scheduling system</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="admin_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text"
                                id="admin_name"
                                name="admin_name"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter your full name">
                            <div id="admin_name_error" class="mt-1 text-sm text-red-600 hidden"></div>
                        </div>

                        <div>
                            <label for="admin_userid" class="block text-sm font-medium text-gray-700 mb-2">
                                Admin User ID <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text"
                                id="admin_userid"
                                name="admin_userid"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter username (3-20 characters)">
                            <div id="admin_userid_error" class="mt-1 text-sm text-red-600 hidden"></div>
                            <p class="mt-1 text-xs text-gray-500">Only letters and numbers allowed</p>
                        </div>

                        <div>
                            <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password"
                                id="admin_password"
                                name="admin_password"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Minimum 8 characters">
                            <div id="admin_password_error" class="mt-1 text-sm text-red-600 hidden"></div>
                            <div id="password_strength" class="mt-1">
                                <div class="flex space-x-1">
                                    <div class="h-1 w-full bg-gray-200 rounded strength-bar" data-strength="1"></div>
                                    <div class="h-1 w-full bg-gray-200 rounded strength-bar" data-strength="2"></div>
                                    <div class="h-1 w-full bg-gray-200 rounded strength-bar" data-strength="3"></div>
                                    <div class="h-1 w-full bg-gray-200 rounded strength-bar" data-strength="4"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1" id="password_strength_text">Password strength indicator</p>
                            </div>
                        </div>

                        <div>
                            <label for="admin_password_confirm" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password"
                                id="admin_password_confirm"
                                name="admin_password_confirm"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Re-enter your password">
                            <div id="admin_password_confirm_error" class="mt-1 text-sm text-red-600 hidden"></div>
                        </div>
                    </div>
                </div>

                <!-- Database Configuration Section -->
                <div class="space-y-6">
                    <div class="border-b border-gray-200 pb-4">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                            </svg>
                            Database Configuration
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">Choose your preferred database setup</p>
                    </div>

                    <!-- Database Type Selection -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- MySQL Option -->
                            <label class="relative cursor-pointer">
                                <input type="radio" name="database_type" value="mysql" class="sr-only peer" id="db_mysql">
                                <div class="border-2 border-gray-200 rounded-lg p-4 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all hover:border-gray-300">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-semibold text-gray-900">MySQL Database</h3>
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                        </svg>
                                    </div>
                                    <p class="text-sm text-gray-600">Connect to an existing MySQL server</p>
                                    <ul class="mt-2 text-xs text-gray-500 space-y-1">
                                        <li>• High performance and scalability</li>
                                        <li>• Requires database server setup</li>
                                        <li>• Recommended for production</li>
                                    </ul>
                                </div>
                            </label>

                            <!-- SQLite Option -->
                            <label class="relative cursor-pointer">
                                <input type="radio" name="database_type" value="sqlite" class="sr-only peer" id="db_sqlite" checked>
                                <div class="border-2 border-gray-200 rounded-lg p-4 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all hover:border-gray-300">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-semibold text-gray-900">SQLite Database</h3>
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-sm text-gray-600">Zero-configuration file-based database</p>
                                    <ul class="mt-2 text-xs text-gray-500 space-y-1">
                                        <li>• No server setup required</li>
                                        <li>• Perfect for getting started</li>
                                        <li>• Automatic database creation</li>
                                    </ul>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- MySQL Configuration Fields -->
                    <div id="mysql_config" class="space-y-4 hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="font-medium text-blue-900 mb-3">MySQL Connection Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="mysql_hostname" class="block text-sm font-medium text-gray-700 mb-2">
                                        Hostname <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text"
                                        id="mysql_hostname"
                                        name="mysql_hostname"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
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
                                    <label for="mysql_database" class="block text-sm font-medium text-gray-700 mb-2">
                                        Database Name <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text"
                                        id="mysql_database"
                                        name="mysql_database"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="xscheduler">
                                    <div id="mysql_database_error" class="mt-1 text-sm text-red-600 hidden"></div>
                                </div>

                                <div>
                                    <label for="mysql_username" class="block text-sm font-medium text-gray-700 mb-2">
                                        Username <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text"
                                        id="mysql_username"
                                        name="mysql_username"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Database username">
                                    <div id="mysql_username_error" class="mt-1 text-sm text-red-600 hidden"></div>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="mysql_password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Password
                                    </label>
                                    <input 
                                        type="password"
                                        id="mysql_password"
                                        name="mysql_password"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Database password (optional)">
                                    <div id="mysql_password_error" class="mt-1 text-sm text-red-600 hidden"></div>
                                </div>
                            </div>

                            <!-- Test Connection Button -->
                            <div class="mt-4 flex justify-end">
                                <button id="test_connection_btn" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                                    </svg>
                                    Test Connection
                                </button>
                            </div>
                            <div id="connection_result" class="mt-2 hidden"></div>
                        </div>
                    </div>

                    <!-- SQLite Configuration Display -->
                    <div id="sqlite_config" class="space-y-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="font-medium text-green-900 mb-2">SQLite Auto-Configuration</h4>
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <p class="text-sm text-green-800">Database will be automatically created at:</p>
                                    <code class="block mt-1 text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-mono">
                                        ./writable/database/appdb.sqlite
                                    </code>
                                    <p class="text-xs text-green-700 mt-2">
                                        No additional configuration required. The application will create and manage the SQLite database automatically.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                    <button type="button" onclick="window.location.href='/'" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Home
                    </button>
                    
                    <div class="flex-1"></div>
                    
                    <button id="setup_submit_btn" type="submit" class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Complete Setup
                    </button>
                </div>
            </form>
        </div>

        <!-- Loading Overlay -->
        <div id="loading_overlay" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
            <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
                <div class="flex items-center space-x-3">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                    <span class="text-gray-900">Processing setup...</span>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="progress_bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <p id="progress_text" class="text-sm text-gray-600 mt-2">Initializing...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Setup wizard will be initialized by setup.js -->
<?= $this->endSection() ?>
<?= $this->extend('components/layout') ?>

<?= $this->section('title') ?>xScheduler - Initial Setup<?= $this->endSection() ?>

<?= $this->section('head') ?>
<script type="module" src="<?= base_url('build/assets/setup.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="max-w-4xl w-full">
        <!-- Setup Header -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center material-shadow">
                <md-icon class="text-blue-600 text-3xl">settings</md-icon>
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
                            <md-icon class="mr-2 text-blue-600">admin_panel_settings</md-icon>
                            System Administrator Account
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">Create your admin account to manage the scheduling system</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="admin_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <md-filled-text-field 
                                id="admin_name"
                                name="admin_name"
                                required
                                class="w-full"
                                placeholder="Enter your full name">
                            </md-filled-text-field>
                            <div id="admin_name_error" class="mt-1 text-sm text-red-600 hidden"></div>
                        </div>

                        <div>
                            <label for="admin_userid" class="block text-sm font-medium text-gray-700 mb-2">
                                Admin User ID <span class="text-red-500">*</span>
                            </label>
                            <md-filled-text-field 
                                id="admin_userid"
                                name="admin_userid"
                                required
                                class="w-full"
                                placeholder="Enter username (3-20 characters)">
                            </md-filled-text-field>
                            <div id="admin_userid_error" class="mt-1 text-sm text-red-600 hidden"></div>
                            <p class="mt-1 text-xs text-gray-500">Only letters and numbers allowed</p>
                        </div>

                        <div>
                            <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <md-filled-text-field 
                                id="admin_password"
                                name="admin_password"
                                type="password"
                                required
                                class="w-full"
                                placeholder="Minimum 8 characters">
                            </md-filled-text-field>
                            <div id="admin_password_error" class="mt-1 text-sm text-red-600 hidden"></div>
                            <div id="password_strength" class="mt-1">
                                <div class="flex space-x-1">
                                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                                    <div class="h-1 w-full bg-gray-200 rounded"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Password strength indicator</p>
                            </div>
                        </div>

                        <div>
                            <label for="admin_password_confirm" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <md-filled-text-field 
                                id="admin_password_confirm"
                                name="admin_password_confirm"
                                type="password"
                                required
                                class="w-full"
                                placeholder="Re-enter your password">
                            </md-filled-text-field>
                            <div id="admin_password_confirm_error" class="mt-1 text-sm text-red-600 hidden"></div>
                        </div>
                    </div>
                </div>

                <!-- Database Configuration Section -->
                <div class="space-y-6">
                    <div class="border-b border-gray-200 pb-4">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <md-icon class="mr-2 text-blue-600">database</md-icon>
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
                                        <md-icon class="text-gray-400 peer-checked:text-blue-600">database</md-icon>
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
                                        <md-icon class="text-gray-400 peer-checked:text-blue-600">file_present</md-icon>
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
                                    <md-filled-text-field 
                                        id="mysql_hostname"
                                        name="mysql_hostname"
                                        class="w-full"
                                        value="localhost"
                                        placeholder="localhost">
                                    </md-filled-text-field>
                                </div>

                                <div>
                                    <label for="mysql_port" class="block text-sm font-medium text-gray-700 mb-2">
                                        Port <span class="text-red-500">*</span>
                                    </label>
                                    <md-filled-text-field 
                                        id="mysql_port"
                                        name="mysql_port"
                                        type="number"
                                        class="w-full"
                                        value="3306"
                                        placeholder="3306">
                                    </md-filled-text-field>
                                </div>

                                <div>
                                    <label for="mysql_database" class="block text-sm font-medium text-gray-700 mb-2">
                                        Database Name <span class="text-red-500">*</span>
                                    </label>
                                    <md-filled-text-field 
                                        id="mysql_database"
                                        name="mysql_database"
                                        class="w-full"
                                        placeholder="xscheduler">
                                    </md-filled-text-field>
                                </div>

                                <div>
                                    <label for="mysql_username" class="block text-sm font-medium text-gray-700 mb-2">
                                        Username <span class="text-red-500">*</span>
                                    </label>
                                    <md-filled-text-field 
                                        id="mysql_username"
                                        name="mysql_username"
                                        class="w-full"
                                        placeholder="Database username">
                                    </md-filled-text-field>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="mysql_password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Password
                                    </label>
                                    <md-filled-text-field 
                                        id="mysql_password"
                                        name="mysql_password"
                                        type="password"
                                        class="w-full"
                                        placeholder="Database password (optional)">
                                    </md-filled-text-field>
                                </div>
                            </div>

                            <!-- Test Connection Button -->
                            <div class="mt-4 flex justify-end">
                                <md-filled-button id="test_connection_btn" type="button">
                                    <md-icon slot="icon">wifi_protected_setup</md-icon>
                                    Test Connection
                                </md-filled-button>
                            </div>
                            <div id="connection_result" class="mt-2 hidden"></div>
                        </div>
                    </div>

                    <!-- SQLite Configuration Display -->
                    <div id="sqlite_config" class="space-y-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="font-medium text-green-900 mb-2">SQLite Auto-Configuration</h4>
                            <div class="flex items-start space-x-3">
                                <md-icon class="text-green-600 mt-0.5">check_circle</md-icon>
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
                    <md-outlined-button type="button" onclick="window.location.href='/'">
                        <md-icon slot="icon">arrow_back</md-icon>
                        Back to Home
                    </md-outlined-button>
                    
                    <div class="flex-1"></div>
                    
                    <md-filled-button id="setup_submit_btn" type="submit">
                        <md-icon slot="icon">rocket_launch</md-icon>
                        Complete Setup
                    </md-filled-button>
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
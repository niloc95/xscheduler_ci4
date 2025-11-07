<?php

if (!function_exists('is_setup_completed')) {
    /**
     * Check if the application setup has been completed
     * Single source of truth for setup status across the application
     * 
     * @return bool
     */
    function is_setup_completed(): bool
    {
        // Check for setup completion flag files first (fastest check, no DB needed)
        $flagPathNew = WRITEPATH . 'setup_complete.flag';
        $flagPathLegacy = WRITEPATH . 'setup_completed.flag';
        
        if (file_exists($flagPathNew) || file_exists($flagPathLegacy)) {
            log_message('debug', 'Setup completed: flag file found');
            return true;
        }

        // If no flag files, check .env exists
        if (!file_exists(ROOTPATH . '.env')) {
            log_message('debug', 'Setup not completed: .env file missing');
            return false;
        }

        // Ensure database credentials are present before attempting connection
        try {
            /** @var \Config\Database $dbConfig */
            $dbConfig = config('Database');
            if (!is_object($dbConfig) || !property_exists($dbConfig, 'default')) {
                log_message('debug', 'Setup not completed: Database config missing default group');
                return false;
            }
            $defaultConfig = (array) $dbConfig->default;

            $hostname = $defaultConfig['hostname'] ?? '';
            $database = $defaultConfig['database'] ?? '';

            if (empty($hostname) || empty($database)) {
                log_message('debug', sprintf('Setup not completed: DB credentials incomplete (hostname="%s", database="%s")', $hostname, $database));
                return false;
            }

            // Try to connect and check for required tables
            log_message('debug', 'Setup check: Attempting DB connection to verify tables');
            $db = \Config\Database::connect();
            if (!$db) {
                log_message('debug', 'Setup not completed: DB connection failed');
                return false;
            }
            
            // Require both users and settings tables to avoid partial setup state
            $hasUsers = $db->tableExists('users');
            $hasSettings = $db->tableExists('settings');
            log_message('debug', 'Setup check: users=' . ($hasUsers ? 'yes' : 'no') . ' settings=' . ($hasSettings ? 'yes' : 'no'));
            
            return $hasUsers && $hasSettings;
            
        } catch (\Throwable $e) {
            log_message('error', 'Setup check exception: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ensure_setup_completed')) {
    /**
     * Ensure setup is completed, redirect to setup if not
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse|null
     */
    function ensure_setup_completed()
    {
        if (!is_setup_completed()) {
            return redirect()->to('/setup')->with('info', 'Please complete the initial setup first.');
        }
        
        return null;
    }
}

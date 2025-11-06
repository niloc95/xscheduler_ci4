<?php

if (!function_exists('is_setup_completed')) {
    /**
     * Check if the application setup has been completed
     * 
     * @return bool
     */
    function is_setup_completed(): bool
    {
        // Production: require .env and DB readiness; ignore flags
        if (ENVIRONMENT === 'production') {
            if (!file_exists(ROOTPATH . '.env')) {
                return false;
            }

            // Ensure database credentials are present before attempting connection
            /** @var \Config\Database $dbConfig */
            $dbConfig = config('Database');
            if (!is_object($dbConfig) || !property_exists($dbConfig, 'default')) {
                return false;
            }
            $defaultConfig = (array) $dbConfig->default;

            $hostname = $defaultConfig['hostname'] ?? '';
            $database = $defaultConfig['database'] ?? '';

            if (empty($hostname) || empty($database)) {
                return false;
            }

            try {
                $db = \Config\Database::connect();
                if (!$db) {
                    return false;
                }
                return $db->tableExists('users');
            } catch (\Throwable $e) {
                return false;
            }
        }

        // Non-production: rely on flag files for local/dev convenience
        $flagPathNew = WRITEPATH . 'setup_complete.flag';
        $flagPathLegacy = WRITEPATH . 'setup_completed.flag';
        return file_exists($flagPathNew) || file_exists($flagPathLegacy);
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

<?php

if (!function_exists('is_setup_completed')) {
    /**
     * Check if the application setup has been completed
     * 
     * @return bool
     */
    function is_setup_completed(): bool
    {
        $flagPath = WRITEPATH . 'setup_completed.flag';
        
        if (!file_exists($flagPath)) {
            return false;
        }

        // Additional check: verify .env file exists
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) {
            return false;
        }

        return true;
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

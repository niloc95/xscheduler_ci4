<?php

namespace App\Controllers;

class AppFlow extends BaseController
{
    /**
     * Handle application entry point and route users correctly
     */
    public function index()
    {
        // Check if setup is completed
        if (!$this->isSetupCompleted()) {
            // Setup not completed - redirect to setup
            return redirect()->to('/setup');
        }

        // Setup is completed - check if user is logged in
        if (session()->get('isLoggedIn')) {
            // User is logged in - go to dashboard
            return redirect()->to('/dashboard');
        }

        // User is not logged in - go to login
        return redirect()->to('/auth/login');
    }

    /**
     * Check if the application setup has been completed
     */
    private function isSetupCompleted(): bool
    {
        // Check for setup completion flag file
        $flagPath = WRITEPATH . 'setup_completed.flag';
        
        if (!file_exists($flagPath)) {
            return false;
        }

        // Additional check: verify .env file exists and has database config
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) {
            return false;
        }

        // Optional: Check if critical database tables exist
        try {
            $db = \Config\Database::connect();
            
            // Check if users table exists (primary indicator of completed setup)
            if (!$db->tableExists('users')) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // Database connection failed - setup probably not complete
            log_message('warning', 'Database connection failed during setup check: ' . $e->getMessage());
            return false;
        }
    }
}

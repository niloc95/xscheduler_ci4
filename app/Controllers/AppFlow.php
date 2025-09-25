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
        helper('setup');
        return is_setup_completed();
    }
}

<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        // Check if setup is completed using centralized helper (prod-safe)
        helper('setup');
        if (!is_setup_completed()) {
            return redirect()->to('/setup');
        }

        // If setup is completed, redirect to dashboard
        return redirect()->to('/dashboard');
    }
}

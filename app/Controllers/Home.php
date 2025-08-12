<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        // Check if setup is completed
        if (!file_exists(WRITEPATH . 'setup_completed.flag')) {
            return redirect()->to('/setup');
        }

        // If setup is completed, redirect to dashboard
        return redirect()->to('/dashboard');
    }
}

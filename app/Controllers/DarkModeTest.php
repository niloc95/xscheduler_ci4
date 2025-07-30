<?php
namespace App\Controllers;

class DarkModeTest extends BaseController
{
    public function index()
    {
        return view('test/dark_mode_test', [
            'title' => 'Dark Mode Test - xScheduler'
        ]);
    }
}

<?php

namespace App\Controllers;

class Styleguide extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Style Guide - xScheduler'
        ];
        return view('test/styleguide/index', $data);
    }
    
    public function components()
    {
        $data = [
            'title' => 'Components - Style Guide'
        ];
        return view('test/styleguide/components', $data);
    }
    
    public function scheduler()
    {
        $data = [
            'title' => 'Scheduler Components - Style Guide'
        ];
        return view('test/styleguide/scheduler', $data);
    }
}
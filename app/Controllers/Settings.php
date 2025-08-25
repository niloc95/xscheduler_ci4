<?php

namespace App\Controllers;

class Settings extends BaseController
{
    public function index()
    {
        $data = [
            'user' => session()->get('user') ?? [
                'name' => 'System Administrator',
                'role' => 'admin',
                'email' => 'admin@xscheduler.com',
            ],
        ];

        return view('settings', $data);
    }

    public function save()
    {
        // Stub: accept POST and flash success; persistence to be implemented.
        if ($this->request->getMethod() === 'post') {
            session()->setFlashdata('success', 'Settings saved (stub).');
        }
        return redirect()->to(base_url('settings'));
    }
}

<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;
use CodeIgniter\Email\Email;

class Auth extends BaseController
{
    protected $userModel;
    protected $email;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->email = \Config\Services::email();
    }

    /**
     * Show login form
     */
    public function login()
    {
        // Check if setup is completed first
        if (!$this->isSetupCompleted()) {
            return redirect()->to('/setup')->with('info', 'Please complete the initial setup first.');
        }

        // If user is already logged in, redirect to dashboard
        if (session()->get('user_id')) {
            return redirect()->to('/dashboard');
        }

        $data = [
            'title' => 'Login - WebSchedulr',
            'validation' => $this->validator
        ];

        return view('auth/login', $data);
    }

    /**
     * Process login form
     */
    public function attemptLogin()
    {
        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        // Find user by email
        $user = $this->userModel->where('email', $email)->first();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            session()->setFlashdata('error', 'Invalid email or password.');
            return redirect()->back()->withInput();
        }

        // Set session data
        $sessionData = [
            'user_id' => $user['id'],
            'user' => [
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'isLoggedIn' => true
        ];

        session()->set($sessionData);
        session()->setFlashdata('success', 'Welcome back, ' . $user['name'] . '!');

        // Redirect to intended URL or dashboard
        $redirectUrl = session()->get('redirect_url') ?: '/dashboard';
        session()->remove('redirect_url');
        
        return redirect()->to($redirectUrl);
    }

    /**
     * Show forgot password form
     */
    public function forgotPassword()
    {
        $data = [
            'title' => 'Forgot Password - WebSchedulr',
            'validation' => $this->validator
        ];

        return view('auth/forgot_password', $data);
    }

    /**
     * Process forgot password form and send reset email
     */
    public function sendResetLink()
    {
        $rules = [
            'email' => 'required|valid_email'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $email = $this->request->getPost('email');
        $user = $this->userModel->where('email', $email)->first();

        if (!$user) {
            session()->setFlashdata('error', 'No account found with that email address.');
            return redirect()->back()->withInput();
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database (you might want to create a password_resets table)
        // For now, we'll use a simple approach with the users table
        $this->userModel->update($user['id'], [
            'reset_token' => $token,
            'reset_expires' => $expires
        ]);

        // Send reset email
        $resetLink = base_url("auth/reset-password/{$token}");
        
        try {
            $this->sendResetEmail($user['email'], $user['name'], $resetLink);
            session()->setFlashdata('success', 'Password reset link has been sent to your email.');
        } catch (\Exception $e) {
            log_message('error', 'Failed to send reset email: ' . $e->getMessage());
            session()->setFlashdata('error', 'Failed to send reset email. Please try again.');
        }

        return redirect()->to('/auth/login');
    }

    /**
     * Show password reset form
     */
    public function resetPassword($token = null)
    {
        if (!$token) {
            session()->setFlashdata('error', 'Invalid reset token.');
            return redirect()->to('/auth/login');
        }

        // Verify token and check expiration
        $user = $this->userModel->where('reset_token', $token)
                               ->where('reset_expires >', date('Y-m-d H:i:s'))
                               ->first();

        if (!$user) {
            session()->setFlashdata('error', 'Invalid or expired reset token.');
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Reset Password - WebSchedulr',
            'token' => $token,
            'validation' => $this->validator
        ];

        return view('auth/reset_password', $data);
    }

    /**
     * Process password reset form
     */
    public function updatePassword()
    {
        $rules = [
            'token' => 'required',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]'
        ];

        if (!$this->validate($rules)) {
            $token = $this->request->getPost('token');
            return redirect()->to("/auth/reset-password/{$token}")
                           ->withInput()
                           ->with('validation', $this->validator);
        }

        $token = $this->request->getPost('token');
        $password = $this->request->getPost('password');

        // Verify token again
        $user = $this->userModel->where('reset_token', $token)
                               ->where('reset_expires >', date('Y-m-d H:i:s'))
                               ->first();

        if (!$user) {
            session()->setFlashdata('error', 'Invalid or expired reset token.');
            return redirect()->to('/auth/login');
        }

        // Update password and clear reset token
        $this->userModel->update($user['id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'reset_token' => null,
            'reset_expires' => null
        ]);

        session()->setFlashdata('success', 'Your password has been updated successfully. Please login with your new password.');
        return redirect()->to('/auth/login');
    }

    /**
     * Logout user
     */
    public function logout()
    {
        session()->destroy();
        session()->setFlashdata('success', 'You have been logged out successfully.');
        return redirect()->to('/auth/login');
    }

    /**
     * Send password reset email
     */
    private function sendResetEmail($email, $name, $resetLink)
    {
        $this->email->setTo($email);
        $this->email->setFrom('noreply@webschedulr.com', 'WebSchedulr');
        $this->email->setSubject('Password Reset Request - WebSchedulr');

        $message = view('auth/emails/password_reset', [
            'name' => $name,
            'resetLink' => $resetLink
        ]);

        $this->email->setMessage($message);

        if (!$this->email->send()) {
            throw new \Exception('Failed to send email: ' . $this->email->printDebugger());
        }
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

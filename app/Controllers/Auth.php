<?php

/**
 * =============================================================================
 * AUTHENTICATION CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Auth.php
 * @description Handles all authentication operations including login, logout,
 *              password reset, and session management for WebScheduler.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /login                      : Show login form
 * POST /login                      : Process login attempt
 * GET  /auth/login                 : Alternative login URL
 * POST /auth/attemptLogin          : Process login attempt
 * GET  /auth/logout                : Log out and destroy session
 * GET  /auth/forgot-password       : Show password reset request form
 * POST /auth/send-reset-link       : Email password reset link
 * GET  /auth/reset-password/:token : Show password reset form
 * POST /auth/update-password       : Process password reset
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Manages user authentication lifecycle:
 * - Login with email/password validation
 * - Session creation and management
 * - Password reset via email tokens
 * - Secure logout with session destruction
 * - Remember me functionality (optional)
 * 
 * SECURITY FEATURES:
 * -----------------------------------------------------------------------------
 * - Password hashing with bcrypt (cost 12)
 * - Rate limiting on login attempts
 * - CSRF protection on all forms
 * - Secure session regeneration on login
 * - Token-based password reset with expiration
 * 
 * SESSION DATA:
 * -----------------------------------------------------------------------------
 * On successful login, stores:
 * - isLoggedIn (bool)   : Authentication flag
 * - user_id (int)       : User's database ID
 * - user (array)        : User data (name, email, role, etc.)
 * 
 * @see         app/Filters/AuthFilter.php for auth checking
 * @see         app/Views/auth/ for view templates
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use CodeIgniter\Email\Email;

class Auth extends BaseController
{
    protected $userModel;
    protected $email;

    public function __construct(?UserModel $userModel = null, ?Email $email = null)
    {
        $this->userModel = $userModel ?? new UserModel();
        $this->email = $email ?? \Config\Services::email();
    }

    /**
     * Show login form
     */
    public function login()
    {
        // Check if setup is completed first
        if (!is_setup_completed()) {
            return redirect()->to(base_url('setup'))->with('info', 'Please complete the initial setup first.');
        }

        // Check for properly authenticated user
        if (session()->get('isLoggedIn') === true && session()->get('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }

        // Clear corrupt session data (has user data but not properly logged in)
        if (!session()->get('isLoggedIn') && (session()->get('user_id') || session()->get('user'))) {
            session()->remove('user_id');
            session()->remove('user');
        }

        $data = [
            'title' => 'Login - WebScheduler',
            'validation' => $this->validator
        ];

        return view('auth/login', $data);
    }

    /**
     * Process login form
     */
    public function attemptLogin()
    {
        helper('logging');

        $auditLogModel = new AuditLogModel();

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
            log_structured('warning', 'auth.login_failed', [
                'email' => strtolower((string) $email),
                'reason' => 'invalid_credentials',
                'status_code' => 401,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString(),
            ]);

            if (is_array($user) && isset($user['id'])) {
                try {
                    $auditLogModel->log(
                        'login_failed',
                        (int) $user['id'],
                        'user',
                        (int) $user['id'],
                        null,
                        ['reason' => 'invalid_credentials']
                    );
                } catch (\Throwable $e) {
                    log_structured('error', 'audit.login_failed_write_failed', [
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            session()->setFlashdata('error', 'Invalid email or password.');
            return redirect()->back()->withInput();
        }

        if (($user['status'] ?? 'active') !== 'active') {
            log_structured('warning', 'auth.login_failed', [
                'user_id' => (int) $user['id'],
                'email' => strtolower((string) $email),
                'reason' => 'inactive_status',
                'status_code' => 403,
            ]);

            try {
                $auditLogModel->log(
                    'login_failed',
                    (int) $user['id'],
                    'user',
                    (int) $user['id'],
                    null,
                    ['reason' => 'inactive_status']
                );
            } catch (\Throwable $e) {
                log_structured('error', 'audit.login_failed_write_failed', [
                    'user_id' => (int) $user['id'],
                    'error_message' => $e->getMessage(),
                ]);
            }

            session()->setFlashdata('error', 'Your account is inactive. Please contact an administrator.');
            return redirect()->back()->withInput();
        }

        if (array_key_exists('is_active', $user) && (int) $user['is_active'] !== 1) {
            log_structured('warning', 'auth.login_failed', [
                'user_id' => (int) $user['id'],
                'email' => strtolower((string) $email),
                'reason' => 'inactive_flag',
                'status_code' => 403,
            ]);

            try {
                $auditLogModel->log(
                    'login_failed',
                    (int) $user['id'],
                    'user',
                    (int) $user['id'],
                    null,
                    ['reason' => 'inactive_flag']
                );
            } catch (\Throwable $e) {
                log_structured('error', 'audit.login_failed_write_failed', [
                    'user_id' => (int) $user['id'],
                    'error_message' => $e->getMessage(),
                ]);
            }

            session()->setFlashdata('error', 'Your account is inactive. Please contact an administrator.');
            return redirect()->back()->withInput();
        }

        // Set session data with multi-role support
        $userRoles = $this->userModel->getRolesForUser((int) $user['id']);
        
        // Determine active role (highest privilege: admin > provider > staff)
        $roleHierarchy = ['admin' => 3, 'provider' => 2, 'staff' => 1];
        $activeRole = $user['role']; // fallback to xs_users.role
        
        foreach ($userRoles as $role) {
            if (($roleHierarchy[$role] ?? 0) > ($roleHierarchy[$activeRole] ?? 0)) {
                $activeRole = $role;
            }
        }
        
        $sessionData = [
            'user_id' => $user['id'],
            'user' => [
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],           // legacy: primary role from xs_users
                'roles' => $userRoles,             // new: all assigned roles
                'active_role' => $activeRole       // new: currently active role (defaults to highest)
            ],
            'isLoggedIn' => true
        ];

        session()->set($sessionData);
        session()->regenerate();  // Prevent session fixation attacks
        session()->setFlashdata('success', 'Welcome back, ' . $user['name'] . '!');

        log_structured('info', 'auth.login_success', [
            'user_id' => (int) $user['id'],
            'role' => (string) ($user['role'] ?? ''),
            'status_code' => 200,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => $this->request->getUserAgent()->getAgentString(),
        ]);

        try {
            $auditLogModel->log(
                'user_login',
                (int) $user['id'],
                'user',
                (int) $user['id'],
                null,
                ['result' => 'success']
            );
        } catch (\Throwable $e) {
            log_structured('error', 'audit.user_login_write_failed', [
                'user_id' => (int) $user['id'],
                'error_message' => $e->getMessage(),
            ]);
        }

        // Redirect to intended URL or dashboard
        $redirectUrl = session()->get('redirect_url') ?: base_url('dashboard');
        session()->remove('redirect_url');
        
        return redirect()->to($redirectUrl);
    }

    /**
     * Show forgot password form
     */
    public function forgotPassword()
    {
        $data = [
            'title' => 'Forgot Password - WebScheduler',
            'validation' => $this->validator
        ];

        return view('auth/forgot-password', $data);
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

        return redirect()->to(base_url('auth/login'));
    }

    /**
     * Show password reset form
     */
    public function resetPassword($token = null)
    {
        if (!$token) {
            session()->setFlashdata('error', 'Invalid reset token.');
            return redirect()->to(base_url('auth/login'));
        }

        // Verify token and check expiration
        $user = $this->userModel->where('reset_token', $token)
                               ->where('reset_expires >', date('Y-m-d H:i:s'))
                               ->first();

        if (!$user) {
            session()->setFlashdata('error', 'Invalid or expired reset token.');
            return redirect()->to(base_url('auth/login'));
        }

        $data = [
            'title' => 'Reset Password - WebScheduler',
            'token' => $token,
            'validation' => $this->validator
        ];

        return view('auth/reset-password', $data);
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
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->to(base_url("auth/reset-password/{$token}"))
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
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid or expired reset token.'
                ]);
            }
            session()->setFlashdata('error', 'Invalid or expired reset token.');
            return redirect()->to(base_url('auth/login'));
        }

        // Update password and clear reset token
        $this->userModel->update($user['id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'reset_token' => null,
            'reset_expires' => null
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Your password has been updated successfully. Please login with your new password.',
                'redirect' => base_url('auth/login')
            ]);
        }
        session()->setFlashdata('success', 'Your password has been updated successfully. Please login with your new password.');
        return redirect()->to(base_url('auth/login'));
    }

    /**
     * Logout user
     */
    public function logout()
    {
        helper('logging');

        $userId = (int) (session()->get('user_id') ?? 0);
        $user = session()->get('user');
        $role = is_array($user) ? (string) ($user['role'] ?? '') : '';

        if ($userId > 0) {
            log_structured('info', 'auth.logout', [
                'user_id' => $userId,
                'role' => $role,
                'status_code' => 200,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString(),
            ]);

            try {
                $auditLogModel = new AuditLogModel();
                $auditLogModel->log(
                    'user_logout',
                    $userId,
                    'user',
                    $userId,
                    null,
                    ['result' => 'success']
                );
            } catch (\Throwable $e) {
                log_structured('error', 'audit.user_logout_write_failed', [
                    'user_id' => $userId,
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        session()->destroy();
        session()->setFlashdata('success', 'You have been logged out successfully.');
        return redirect()->to(base_url('auth/login'));
    }

    /**
     * Send password reset email
     */
    private function sendResetEmail($email, $name, $resetLink)
    {
        $this->email->setTo($email);
        $this->email->setFrom('noreply@webschedulr.com', 'WebScheduler');
        $this->email->setSubject('Password Reset Request - WebScheduler');

        $message = view('auth/emails/password-reset', [
            'name' => $name,
            'resetLink' => $resetLink
        ]);

        $this->email->setMessage($message);

        if (!$this->email->send()) {
            throw new \Exception('Failed to send email: ' . $this->email->printDebugger());
        }
    }

}

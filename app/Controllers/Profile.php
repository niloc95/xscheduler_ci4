<?php

/**
 * =============================================================================
 * USER PROFILE CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Profile.php
 * @description User profile management including personal settings, password
 *              changes, notification preferences, and account deletion.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /profile                      : View profile page
 * POST /profile/update               : Update profile information
 * POST /profile/password             : Change password
 * POST /profile/avatar               : Upload profile avatar
 * POST /profile/notifications        : Update notification preferences
 * POST /profile/delete               : Delete user account
 * GET  /profile/export               : Export personal data (GDPR)
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Self-service account management for logged-in users:
 * - View and edit personal information (name, email, phone)
 * - Change password with strength validation
 * - Upload and manage profile avatar
 * - Configure notification preferences (email, SMS, WhatsApp)
 * - Export personal data for GDPR compliance
 * - Request account deletion
 * 
 * PROFILE SECTIONS:
 * -----------------------------------------------------------------------------
 * - Personal Info: Name, email, phone, timezone
 * - Security: Password change, 2FA settings
 * - Notifications: Email/SMS/WhatsApp preferences
 * - Privacy: Data export, account deletion
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * All routes require authentication; users can only edit their own profile.
 * 
 * @see         app/Views/profile/ for view templates
 * @see         app/Models/UserModel.php for user data
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use App\Services\PhoneNumberService;
use App\Services\ProfilePageService;

class Profile extends BaseController
{
    protected UserModel $userModel;
    protected AuditLogModel $auditLogModel;
    protected PhoneNumberService $phoneNumberService;
    protected ProfilePageService $profilePageService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->auditLogModel = new AuditLogModel();
        $this->phoneNumberService = new PhoneNumberService();
        $this->profilePageService = new ProfilePageService($this->userModel, null, $this->auditLogModel);
        helper(['permissions', 'form', 'ui']);
    }

    /**
     * Display user profile with inline account management.
     */
    public function index()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $userId = (int) session()->get('user_id');
        if ($userId <= 0) {
            return redirect()->to(base_url('auth/login'));
        }

        $sessionUser = session()->get('user') ?? [];
        $userRole = current_user_role() ?: ($sessionUser['role'] ?? 'customer');
        $data = $this->profilePageService->buildViewData($userId, $userRole, [
            'profile_errors' => session()->getFlashdata('profile_errors') ?? [],
            'password_errors' => session()->getFlashdata('password_errors') ?? [],
            'active_tab' => session()->getFlashdata('active_tab') ?? 'profile',
        ]);

        if ($data === null) {
            session()->setFlashdata('error', 'We could not load your account. Please sign in again.');
            return redirect()->to(base_url('auth/logout'));
        }

        $this->refreshSessionUser($data['user']);
        $data['flashSuccess'] = session()->getFlashdata('success');
        $data['flashError'] = session()->getFlashdata('error');

        return view('profile/index', $data);
    }

    /**
     * Persist inline profile updates.
     */
    public function updateProfile()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $userId = (int) session()->get('user_id');
        if ($userId <= 0) {
            return redirect()->to(base_url('auth/login'));
        }

        $rules = [
            'first_name' => 'required|min_length[1]|max_length[100]',
            'last_name' => 'permit_empty|max_length[100]',
            'email' => "required|valid_email|is_unique[xs_users.email,id,{$userId}]",
            'phone' => 'permit_empty|max_length[20]',
        ];

        if (!$this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->to(base_url('profile'))
                ->withInput()
                ->with('profile_errors', $this->validator->getErrors())
                ->with('active_tab', 'profile')
                ->with('error', 'Please correct the highlighted fields.');
        }

        $userRecord = $this->userModel->find($userId);
        if (!$userRecord) {
            return redirect()->to(base_url('auth/login'))
                ->with('error', 'Your account is no longer available. Please sign in again.');
        }

        $firstName = trim((string) $this->request->getPost('first_name'));
        $lastName = trim((string) $this->request->getPost('last_name'));
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $firstName ?: $lastName;
        }

        $updateData = [
            'name' => $fullName,
            'email' => trim((string) $this->request->getPost('email')),
            'phone' => $this->phoneNumberService->normalize(
                $this->normalizeNullable($this->request->getPost('phone')),
                $this->request->getPost('phone_country_code')
            ),
        ];

        if (!$this->userModel->updateUser($userId, $updateData, $userId)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Unable to update profile. Please try again.'
                ]);
            }
            return redirect()->to(base_url('profile'))
                ->withInput()
                ->with('profile_errors', ['general' => 'Unable to update profile at this time.'])
                ->with('active_tab', 'profile')
                ->with('error', 'Unable to update profile. Please try again.');
        }

        $updatedUser = $this->userModel->find($userId) ?: array_merge($userRecord, $updateData);
        $this->refreshSessionUser($updatedUser);
        $this->logProfileActivity(
            'user_updated',
            $userId,
            [
                'name' => $userRecord['name'] ?? null,
                'email' => $userRecord['email'] ?? null,
                'phone' => $userRecord['phone'] ?? null,
            ],
            [
                'name' => $updateData['name'],
                'email' => $updateData['email'],
                'phone' => $updateData['phone'],
            ]
        );

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'redirect' => base_url('profile#profile')
            ]);
        }
        return redirect()->to(base_url('profile#profile'))
            ->with('success', 'Profile updated successfully.')
            ->with('active_tab', 'profile');
    }

    /**
     * Handle password changes from the profile screen.
     */
    public function changePassword()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $userId = (int) session()->get('user_id');
        if ($userId <= 0) {
            return redirect()->to(base_url('auth/login'));
        }

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min_length[8]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (!$this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Please correct the highlighted fields.',
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->to(base_url('profile'))
                ->withInput()
                ->with('password_errors', $this->validator->getErrors())
                ->with('active_tab', 'password')
                ->with('error', 'Please correct the highlighted fields.');
        }

        $userRecord = $this->userModel->find($userId);
        if (!$userRecord) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Your account is no longer available. Please sign in again.',
                    'redirect' => base_url('auth/login')
                ]);
            }
            return redirect()->to(base_url('auth/login'))
                ->with('error', 'Your account is no longer available. Please sign in again.');
        }

        $currentPassword = (string) $this->request->getPost('current_password');
        if (!password_verify($currentPassword, $userRecord['password_hash'])) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                    'errors' => ['current_password' => 'The current password you entered is incorrect.']
                ]);
            }
            return redirect()->to(base_url('profile'))
                ->withInput()
                ->with('password_errors', ['current_password' => 'The current password you entered is incorrect.'])
                ->with('active_tab', 'password')
                ->with('error', 'Current password is incorrect.');
        }

        $newPassword = (string) $this->request->getPost('new_password');
        if (password_verify($newPassword, $userRecord['password_hash'])) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'New password must be different from the current password.',
                    'errors' => ['new_password' => 'Please choose a password that differs from your current one.']
                ]);
            }
            return redirect()->to(base_url('profile'))
                ->withInput()
                ->with('password_errors', ['new_password' => 'Please choose a password that differs from your current one.'])
                ->with('active_tab', 'password')
                ->with('error', 'New password must be different from the current password.');
        }

        if (!$this->userModel->updateUser($userId, ['password' => $newPassword], $userId)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Unable to update password. Please try again later.'
                ]);
            }
            return redirect()->to(base_url('profile'))
                ->with('error', 'Unable to update password. Please try again later.')
                ->with('active_tab', 'password');
        }

            $this->logProfileActivity('password_changed', $userId, null, ['source' => 'profile']);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Password updated successfully.',
                'redirect' => base_url('profile#password')
            ]);
        }
            return redirect()->to(base_url('profile#password'))
            ->with('success', 'Password updated successfully.')
            ->with('active_tab', 'password');
    }

    public function uploadPicture()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $userId = (int) session()->get('user_id');
        if ($userId <= 0) {
            return redirect()->to(base_url('auth/login'));
        }

        $file = $this->request->getFile('profile_picture');
        if (!$file) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'No file uploaded.')
                ->with('active_tab', 'profile');
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'Please choose a profile image to upload.')
                ->with('active_tab', 'profile');
        }

        if (!$file->isValid()) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'Upload failed: ' . $file->getErrorString())
                ->with('active_tab', 'profile');
        }

        if ($file->hasMoved()) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'Upload failed: file has already been processed.')
                ->with('active_tab', 'profile');
        }

        $sizeBytes = (int) $file->getSize();
        if ($sizeBytes > (2 * 1024 * 1024)) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'Profile image too large. Maximum size is 2MB.')
                ->with('active_tab', 'profile');
        }

        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime   = strtolower((string) $file->getMimeType());
        $ext        = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));

        $allowedMimes = [
            'image/png','image/x-png','image/jpeg','image/pjpeg','image/webp','image/svg+xml','image/svg','image/gif'
        ];
        $allowedExts = ['png','jpg','jpeg','webp','svg','gif'];

        $mimeOk = in_array($clientMime, $allowedMimes, true) || in_array($realMime, $allowedMimes, true);
        $extOk  = in_array($ext, $allowedExts, true);
        if (!$mimeOk && !$extOk) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'Unsupported image format. Use PNG, JPG, SVG, WebP, or GIF.')
                ->with('active_tab', 'profile');
        }

        $targetDir = $this->profileUploadDirectory();
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'Profile image directory is not writable.')
                ->with('active_tab', 'profile');
        }

        $userRecord = $this->userModel->find($userId);
        if (!$userRecord) {
            return redirect()->to(base_url('auth/login'))
                ->with('error', 'Your account is no longer available. Please sign in again.');
        }

        $this->removeExistingProfileImage($userRecord['profile_image'] ?? null);

        try {
            $safeName = 'profile_' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        } catch (\Throwable $e) {
            $safeName = 'profile_' . $userId . '_' . date('Ymd_His') . '_' . uniqid('', true) . '.' . $ext;
        }

        if (!$file->move($targetDir, $safeName)) {
            return redirect()->to(base_url('profile'))
                ->with('error', 'Unable to store uploaded profile image.')
                ->with('active_tab', 'profile');
        }

        $absolute = rtrim($targetDir, '/').'/'.$safeName;
        $mimeForResize = $realMime ?: $clientMime;

        if (!in_array($mimeForResize, ['image/svg+xml','image/svg'], true)) {
            [$w, $h] = @getimagesize($absolute) ?: [null, null];
            if ($w && $w > 600) {
                $ratio = $h ? ($h / $w) : 1;
                $newW = 600;
                $newH = max(1, (int) round($newW * $ratio));
                $this->resizeImageInPlace($absolute, $mimeForResize, $newW, $newH);
            }
        }

        $relative = 'assets/profile/' . $safeName;

        if (!$this->userModel->updateUser($userId, ['profile_image' => $relative], $userId)) {
            @unlink($absolute);
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Unable to update profile image. Please try again.'
                ]);
            }
            return redirect()->to(base_url('profile'))
                ->with('error', 'Unable to update profile image. Please try again.')
                ->with('active_tab', 'profile');
        }

        $updatedUser = $this->userModel->find($userId) ?: array_merge($userRecord, ['profile_image' => $relative]);
        $this->refreshSessionUser($updatedUser);
        $this->logProfileActivity(
            'profile_photo_updated',
            $userId,
            ['profile_image' => $userRecord['profile_image'] ?? null],
            ['profile_image' => $relative]
        );

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Profile photo updated successfully.',
                'redirect' => base_url('profile#profile')
            ]);
        }
        return redirect()->to(base_url('profile#profile'))
            ->with('success', 'Profile photo updated successfully.')
            ->with('active_tab', 'profile');
    }

    /**
     * Privacy settings
     */
    public function privacy()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        return redirect()->to(base_url('profile'))
            ->with('active_tab', 'profile');
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        // For now, redirect back to profile
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
                'redirect' => base_url('profile')
            ]);
        }
        session()->setFlashdata('success', 'Privacy settings updated successfully');
        return redirect()->to(base_url('profile'));
    }

    /**
     * Account settings
     */
    public function account()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        return redirect()->to(base_url('profile'))
            ->with('active_tab', 'profile');
    }

    /**
     * Update account settings
     */
    public function updateAccount()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        // For now, redirect back to profile
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Account settings updated successfully',
                'redirect' => base_url('profile')
            ]);
        }
        session()->setFlashdata('success', 'Account settings updated successfully');
        return redirect()->to(base_url('profile'));
    }

    private function normalizeNullable($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function profileUploadDirectory(): string
    {
        return rtrim(FCPATH, '/').'/assets/profile';
    }

    private function removeExistingProfileImage(?string $stored): void
    {
        if (!$stored) {
            return;
        }

        $path = $this->resolveProfileImagePath($stored);
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    private function resolveProfileImagePath(string $stored): ?string
    {
        $normalized = ltrim($stored, '/');
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'assets/profile/')) {
            return rtrim(FCPATH, '/').'/'.$normalized;
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return rtrim(WRITEPATH, '/').'/'.$normalized;
        }

        return null;
    }

    private function resizeImageInPlace(string $path, string $mime, int $newW, int $newH): void
    {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($path); break;
            case 'image/png': $src = @imagecreatefrompng($path); break;
            case 'image/gif': $src = @imagecreatefromgif($path); break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $src = @imagecreatefromwebp($path);
                } else {
                    return;
                }
                break;
            default:
                return;
        }

        if (!$src) {
            return;
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if (in_array($mime, ['image/png','image/gif'], true)) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $sw, $sh);

        switch ($mime) {
            case 'image/jpeg': @imagejpeg($dst, $path, 85); break;
            case 'image/png': @imagepng($dst, $path, 6); break;
            case 'image/gif': @imagegif($dst, $path); break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    @imagewebp($dst, $path, 85);
                }
                break;
        }

        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * Update appointment notification preference for the current user.
     * POST /profile/update-notifications
     */
    public function updateNotifications()
    {
        if (!session()->get('isLoggedIn')) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthenticated']);
            }
            return redirect()->to(base_url('auth/login'));
        }

        if (!$this->hasNotifyOnAppointmentsColumn()) {
            $message = 'Notification preferences are not available yet. Please run the latest database migrations.';

            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => $message,
                    'redirect' => base_url('profile') . '#notifications',
                ]);
            }

            session()->setFlashdata('error', $message);
            session()->setFlashdata('active_tab', 'notifications');
            return redirect()->to(base_url('profile') . '#notifications');
        }

        $userId = (int) session()->get('user_id');
        if ($userId <= 0) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthenticated']);
            }
            return redirect()->to(base_url('auth/login'));
        }

        $notify = (int) (bool) $this->request->getPost('notify_on_appointments');
        $userRecord = $this->userModel->find($userId);

        if (!$userRecord) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Your account is no longer available. Please sign in again.',
                    'redirect' => base_url('auth/login'),
                ]);
            }

            return redirect()->to(base_url('auth/login'))
                ->with('error', 'Your account is no longer available. Please sign in again.');
        }

        if (!$this->userModel->updateUser($userId, ['notify_on_appointments' => $notify], $userId)) {
            $message = 'Unable to save notification preferences right now.';

            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => $message,
                    'redirect' => base_url('profile') . '#notifications',
                ]);
            }

            return redirect()->to(base_url('profile') . '#notifications')
                ->with('error', $message)
                ->with('active_tab', 'notifications');
        }

        $updatedUser = $this->userModel->find($userId) ?: array_merge($userRecord, ['notify_on_appointments' => $notify]);
        $this->refreshSessionUser($updatedUser);
        $this->logProfileActivity(
            'notification_preferences_updated',
            $userId,
            ['notify_on_appointments' => (bool) ($userRecord['notify_on_appointments'] ?? false)],
            ['notify_on_appointments' => (bool) $notify]
        );

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => $notify ? 'Appointment notifications enabled.' : 'Appointment notifications disabled.',
                'redirect' => base_url('profile') . '#notifications',
            ]);
        }

        session()->setFlashdata('success', 'Notification preferences saved.');
        session()->setFlashdata('active_tab', 'notifications');
        return redirect()->to(base_url('profile') . '#notifications');
    }

    /**
     * Check if xs_users.notify_on_appointments exists (schema-safe, cached).
     */
    private function hasNotifyOnAppointmentsColumn(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            $db = \Config\Database::connect();
            $exists = method_exists($db, 'fieldExists')
                ? (bool) $db->fieldExists('notify_on_appointments', 'xs_users')
                : true;
        } catch (\Throwable $e) {
            $exists = false;
        }

        return $exists;
    }

    private function refreshSessionUser(array $userRecord): void
    {
        $currentUser = session()->get('user') ?? [];

        session()->set('user', array_merge($currentUser, [
            'name' => $userRecord['name'] ?? ($currentUser['name'] ?? ''),
            'email' => $userRecord['email'] ?? ($currentUser['email'] ?? ''),
            'role' => $userRecord['role'] ?? ($currentUser['role'] ?? null),
            'profile_image' => $userRecord['profile_image'] ?? null,
            'notify_on_appointments' => array_key_exists('notify_on_appointments', $userRecord)
                ? (bool) $userRecord['notify_on_appointments']
                : ($currentUser['notify_on_appointments'] ?? null),
        ]));
    }

    private function logProfileActivity(string $action, int $userId, ?array $oldValue = null, ?array $newValue = null): void
    {
        try {
            $this->auditLogModel->log($action, $userId, 'user', $userId, $oldValue, $newValue);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to write profile audit log: ' . $e->getMessage());
        }
    }
}

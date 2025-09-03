<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

class Profile extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('permissions');
    }

    /**
     * Display user profile
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'My Profile',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/index', $data);
    }

    /**
     * Edit profile form
     */
    public function edit()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Edit Profile',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/edit', $data);
    }

    /**
     * Update profile
     */
    public function update()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Profile updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Change password form
     */
    public function password()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Change Password',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/password', $data);
    }

    /**
     * Update password
     */
    public function updatePassword()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Password updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Upload profile picture
     */
    public function uploadPicture()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Profile picture updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Privacy settings
     */
    public function privacy()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Privacy Settings',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/privacy', $data);
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Privacy settings updated successfully');
        return redirect()->to('/profile');
    }

    /**
     * Account settings
     */
    public function account()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();

        $data = [
            'title' => 'Account Settings',
            'current_page' => 'profile',
            'user_role' => $currentRole,
            'user' => $currentUser,
        ];

        return view('profile/account', $data);
    }

    /**
     * Update account settings
     */
    public function updateAccount()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // For now, redirect back to profile
        session()->setFlashdata('success', 'Account settings updated successfully');
        return redirect()->to('/profile');
    }
}

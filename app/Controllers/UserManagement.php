<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\UserPermissionModel;

class UserManagement extends BaseController
{
    protected $userModel;
    protected $permissionModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->permissionModel = new UserPermissionModel();
    }

    /**
     * Display user management dashboard
     */
    public function index()
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        // Get users based on current user's role and permissions
        $users = $this->getUsersBasedOnRole($currentUserId);
        $stats = $this->getUserStatsBasedOnRole($currentUserId);

        $data = [
            'title' => 'User Management - XScheduler',
            'currentUser' => $currentUser,
            'users' => $users,
            'stats' => $stats,
            'canCreateAdmin' => $this->permissionModel->hasPermission($currentUserId, 'create_admin'),
            'canCreateProvider' => $this->permissionModel->hasPermission($currentUserId, 'create_provider'),
            'canCreateStaff' => $this->permissionModel->hasPermission($currentUserId, 'create_staff'),
        ];

        return view('user_management/index', $data);
    }

    /**
     * Show create user form
     */
    public function create()
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        // Determine which roles the current user can create
        $availableRoles = $this->getAvailableRolesForUser($currentUserId);
        
        if (empty($availableRoles)) {
            return redirect()->to('/user-management')
                           ->with('error', 'You do not have permission to create users.');
        }

        // Get providers for staff assignment
        $providers = [];
        if (in_array('staff', $availableRoles)) {
            $providers = $this->userModel->getProviders();
        }

        // Get stats for the help panel
        $stats = $this->getUserStatsBasedOnRole($currentUserId);

        $data = [
            'title' => 'Create User - XScheduler',
            'currentUser' => $currentUser,
            'availableRoles' => $availableRoles,
            'providers' => $providers,
            'stats' => $stats,
            'validation' => $this->validator
        ];

        return view('user_management/create', $data);
    }

    /**
     * Process create user form
     */
    public function store()
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        // Validation rules
        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'role' => 'required|in_list[admin,provider,staff,customer]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'phone' => 'permit_empty|max_length[20]',
        ];

        // Add provider validation for staff
        if ($this->request->getPost('role') === 'staff') {
            $rules['provider_id'] = 'required|numeric';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $role = $this->request->getPost('role');
        
        // Check if current user can create this role
        if (!$this->canCreateRole($currentUserId, $role)) {
            return redirect()->back()
                           ->with('error', 'You do not have permission to create users with this role.');
        }

        $userData = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
            'role' => $role,
            'password' => $this->request->getPost('password'),
            'is_active' => true,
        ];

        // Set provider_id for staff
        if ($role === 'staff') {
            $providerId = $this->request->getPost('provider_id');
            
            // Validate that the provider exists and current user can assign to it
            if (!$this->canAssignToProvider($currentUserId, $providerId)) {
                return redirect()->back()
                               ->with('error', 'Invalid provider selection.');
            }
            
            $userData['provider_id'] = $providerId;
        }

        $userId = $this->userModel->createUser($userData);

        if ($userId) {
            return redirect()->to('/user-management')
                           ->with('success', 'User created successfully.');
        } else {
            return redirect()->back()
                           ->with('error', 'Failed to create user. Please try again.');
        }
    }

    /**
     * Show edit user form
     */
    public function edit(int $userId)
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            return redirect()->to('/user-management')
                           ->with('error', 'User not found.');
        }

        // Check permission to edit this user
        if (!$this->userModel->canManageUser($currentUserId, $userId)) {
            return redirect()->to('/user-management')
                           ->with('error', 'You do not have permission to edit this user.');
        }

        // Get available roles for this user
        $availableRoles = $this->getAvailableRolesForUser($currentUserId, $user);
        
        // Get providers for staff assignment
        $providers = [];
        if (in_array('staff', $availableRoles) || $user['role'] === 'staff') {
            $providers = $this->userModel->getProviders();
        }

        $data = [
            'title' => 'Edit User - XScheduler',
            'currentUser' => $currentUser,
            'user' => $user,
            'availableRoles' => $availableRoles,
            'providers' => $providers,
            'validation' => $this->validator
        ];

        return view('user_management/edit', $data);
    }

    /**
     * Process edit user form
     */
    public function update(int $userId)
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            return redirect()->to('/user-management')
                           ->with('error', 'User not found.');
        }

        // Check permission to edit this user
        if (!$this->userModel->canManageUser($currentUserId, $userId)) {
            return redirect()->to('/user-management')
                           ->with('error', 'You do not have permission to edit this user.');
        }

        // Validation rules
        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'email' => "required|valid_email|is_unique[users.email,id,{$userId}]",
            'phone' => 'permit_empty|max_length[20]',
        ];

        // Add password rules if provided
        if ($this->request->getPost('password')) {
            $rules['password'] = 'required|min_length[8]';
            $rules['password_confirm'] = 'required|matches[password]';
        }

        // Add role validation if user can change roles
        if ($this->canChangeUserRole($currentUserId, $userId)) {
            $rules['role'] = 'required|in_list[admin,provider,staff,customer]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $updateData = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
        ];

        // Add password if provided
        if ($this->request->getPost('password')) {
            $updateData['password'] = $this->request->getPost('password');
        }

        // Add role if user can change it
        if ($this->canChangeUserRole($currentUserId, $userId)) {
            $newRole = $this->request->getPost('role');
            if ($newRole !== $user['role']) {
                if (!$this->canCreateRole($currentUserId, $newRole)) {
                    return redirect()->back()
                                   ->with('error', 'You do not have permission to assign this role.');
                }
                $updateData['role'] = $newRole;
            }
        }

        // Handle provider assignment for staff
        if (($user['role'] === 'staff' || ($updateData['role'] ?? '') === 'staff') && $this->request->getPost('provider_id')) {
            $providerId = $this->request->getPost('provider_id');
            if ($this->canAssignToProvider($currentUserId, $providerId)) {
                $updateData['provider_id'] = $providerId;
            }
        }

        if ($this->userModel->updateUser($userId, $updateData, $currentUserId)) {
            // Update session if user updated themselves
            if ($currentUserId === $userId) {
                $updatedUser = $this->userModel->find($userId);
                session()->set('user', [
                    'name' => $updatedUser['name'],
                    'email' => $updatedUser['email'],
                    'role' => $updatedUser['role']
                ]);
            }

            return redirect()->to('/user-management')
                           ->with('success', 'User updated successfully.');
        } else {
            return redirect()->back()
                           ->with('error', 'Failed to update user. Please try again.');
        }
    }

    /**
     * Deactivate user
     */
    public function deactivate(int $userId)
    {
        $currentUserId = session()->get('user_id');

        if (!$currentUserId) {
            return redirect()->to('/auth/login');
        }

        if ($currentUserId === $userId) {
            return redirect()->to('/user-management')
                           ->with('error', 'You cannot deactivate your own account.');
        }

        if ($this->userModel->deactivateUser($userId, $currentUserId)) {
            return redirect()->to('/user-management')
                           ->with('success', 'User deactivated successfully.');
        } else {
            return redirect()->to('/user-management')
                           ->with('error', 'Failed to deactivate user or insufficient permissions.');
        }
    }

    /**
     * Activate user
     */
    public function activate(int $userId)
    {
        $currentUserId = session()->get('user_id');

        if (!$currentUserId) {
            return redirect()->to('/auth/login');
        }

        if ($this->userModel->update($userId, ['is_active' => true])) {
            return redirect()->to('/user-management')
                           ->with('success', 'User activated successfully.');
        } else {
            return redirect()->to('/user-management')
                           ->with('error', 'Failed to activate user.');
        }
    }

    // Helper methods

    private function getUsersBasedOnRole(int $currentUserId): array
    {
        $currentUser = $this->userModel->find($currentUserId);
        if (!$currentUser) {
            return [];
        }

        switch ($currentUser['role']) {
            case 'admin':
                return $this->userModel->findAll();
                
            case 'provider':
                // Providers can see their staff and themselves
                return $this->userModel
                    ->where('provider_id', $currentUserId)
                    ->orWhere('id', $currentUserId)
                    ->findAll();
                    
            case 'staff':
            case 'customer':
                // Staff and customers can only see themselves
                return [$currentUser];
                
            default:
                return [];
        }
    }

    private function getUserStatsBasedOnRole(int $currentUserId): array
    {
        $currentUser = $this->userModel->find($currentUserId);
        if (!$currentUser) {
            return [];
        }

        if ($currentUser['role'] === 'admin') {
            return $this->userModel->getStats();
        } elseif ($currentUser['role'] === 'provider') {
            $staff = $this->userModel->getStaffForProvider($currentUserId);
            return [
                'total' => count($staff) + 1, // +1 for the provider
                'staff' => count($staff),
                'providers' => 1,
                'customers' => 0,
                'admins' => 0,
                'recent' => 0
            ];
        }

        return ['total' => 1, 'staff' => 0, 'providers' => 0, 'customers' => 0, 'admins' => 0, 'recent' => 0];
    }

    private function getAvailableRolesForUser(int $currentUserId, ?array $targetUser = null): array
    {
        $roles = [];
        
        if ($this->permissionModel->hasPermission($currentUserId, 'create_admin')) {
            $roles[] = 'admin';
        }
        if ($this->permissionModel->hasPermission($currentUserId, 'create_provider')) {
            $roles[] = 'provider';
        }
        if ($this->permissionModel->hasPermission($currentUserId, 'create_staff')) {
            $roles[] = 'staff';
        }
        
        // Everyone can create customers (if they have user management permissions)
        if ($this->permissionModel->hasPermission($currentUserId, 'user_management')) {
            $roles[] = 'customer';
        }

        return $roles;
    }

    private function canCreateRole(int $currentUserId, string $role): bool
    {
        switch ($role) {
            case 'admin':
                return $this->permissionModel->hasPermission($currentUserId, 'create_admin');
            case 'provider':
                return $this->permissionModel->hasPermission($currentUserId, 'create_provider');
            case 'staff':
                return $this->permissionModel->hasPermission($currentUserId, 'create_staff');
            case 'customer':
                return $this->permissionModel->hasPermission($currentUserId, 'user_management');
            default:
                return false;
        }
    }

    private function canAssignToProvider(int $currentUserId, int $providerId): bool
    {
        $currentUser = $this->userModel->find($currentUserId);
        
        // Admins can assign to any provider
        if ($currentUser['role'] === 'admin') {
            return true;
        }
        
        // Providers can only assign to themselves
        if ($currentUser['role'] === 'provider') {
            return $currentUserId === $providerId;
        }
        
        return false;
    }

    private function canChangeUserRole(int $currentUserId, int $targetUserId): bool
    {
        $currentUser = $this->userModel->find($currentUserId);
        
        // Only admins can change user roles
        return $currentUser['role'] === 'admin';
    }
}

# User Edit Bug - Quick Reference

## Problem
User edit was failing with "Failed to update user" error and redirecting to Services view.

## Root Causes

### 1. UserModel - Missing Role Validation
```php
// app/Models/UserModel.php
'role' => 'required|in_list[admin,provider,staff,receptionist,customer]'
// Was missing 'staff' - caused validation failure for staff users
```

### 2. Controller - Missing is_active Handling
```php
// app/Controllers/UserManagement.php - update()
$updateData['is_active'] = $this->request->getPost('is_active') ? true : false;
// Checkboxes don't send value when unchecked
```

### 3. Controller - Incomplete Role Logic
```php
// Always include role in updateData when provided
if ($newRole) {
    $updateData['role'] = $newRole;
}
```

## Solution
- ✅ Added staff/customer to UserModel validation rules
- ✅ Explicit is_active checkbox handling in controller
- ✅ Improved role change logic to handle same-role updates
- ✅ Enhanced provider assignment validation
- ✅ Redesigned edit form UI with help panels

## Testing Commands
```bash
# View the changes
git show 4f899a3

# Test user edit
php spark serve
# Navigate to: http://localhost:8080/user-management
# Click edit on any user, make changes, save

# Check database
mysql -u root -p
USE xscheduler_db;
SELECT id, name, email, role, is_active FROM users;
```

## Key Files Modified
- `app/Models/UserModel.php` - Validation rules
- `app/Controllers/UserManagement.php` - Update logic
- `app/Views/user_management/edit.php` - Form UI

## Common Pitfalls to Avoid

### ❌ Don't forget checkbox handling
```php
// WRONG - unchecked checkbox = no POST value
$updateData['is_active'] = $this->request->getPost('is_active');

// RIGHT - explicit boolean conversion
$updateData['is_active'] = $this->request->getPost('is_active') ? true : false;
```

### ❌ Don't skip role in validation
```php
// WRONG - missing roles
'role' => 'required|in_list[admin,provider,receptionist]'

// RIGHT - all system roles
'role' => 'required|in_list[admin,provider,staff,receptionist,customer]'
```

### ❌ Don't ignore provider assignment
```php
// WRONG - partial check
if ($user['role'] === 'staff' && $this->request->getPost('provider_id')) {

// RIGHT - check final role and validate
$finalRole = $updateData['role'] ?? $user['role'];
if ($finalRole === 'staff') {
    // Validate and assign
}
```

## Validation Rules Reference

### User Model Rules
```php
'name'  => 'required|min_length[2]|max_length[255]'
'email' => 'required|valid_email|is_unique[users.email,id,{id}]'
'role'  => 'required|in_list[admin,provider,staff,receptionist,customer]'
```

### Controller Rules (on update)
```php
'name'              => 'required|min_length[2]|max_length[255]'
'email'             => "required|valid_email|is_unique[users.email,id,{$userId}]"
'phone'             => 'permit_empty|max_length[20]'
'password'          => 'required|min_length[8]' (if provided)
'password_confirm'  => 'required|matches[password]' (if password provided)
'role'              => 'required|in_list[admin,provider,staff]' (if can change)
'provider_id'       => 'required|numeric' (if role=staff)
```

## Debug Tips

### Check validation errors
```php
// In controller
if (!$this->validate($rules)) {
    log_message('error', 'User update validation failed: ' . json_encode($this->validator->getErrors()));
    return redirect()->back()->withInput()->with('validation', $this->validator);
}
```

### Check model save result
```php
// In UserModel::updateUser()
if (!$this->update($userId, $userData)) {
    log_message('error', 'User update failed for ID: ' . $userId . ', Errors: ' . json_encode($this->errors()));
    return false;
}
```

### Check form POST data
```php
// In controller
log_message('debug', 'Update POST data: ' . json_encode($this->request->getPost()));
```

## Related Documentation
- `docs/USER_EDIT_BUG_FIX.md` - Full bug report and fix details
- `docs/architecture/ROLE_BASED_SYSTEM.md` - Role system architecture
- `app/Views/user_management/create.php` - Create form reference

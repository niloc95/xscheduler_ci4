# Provider ↔ Staff Assignment System - Complete Implementation Guide

## Overview
This document describes the fully rebuilt Provider–Staff linking system that enables:
- Clean database relationships with audit trail
- Role-based access control (RBAC)
- Unified frontend components for bidirectional assignments
- Comprehensive error handling and validation

---

## 1. Database Schema

### Table: `xs_provider_staff_assignments`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT(11) UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `provider_id` | INT(11) UNSIGNED | NOT NULL, FK → xs_users.id | Provider user ID |
| `staff_id` | INT(11) UNSIGNED | NOT NULL, FK → xs_users.id | Staff/Receptionist user ID |
| `assigned_by` | INT(11) UNSIGNED | NULL, FK → xs_users.id | User who created assignment (audit) |
| `status` | VARCHAR(20) | DEFAULT 'active' | Assignment status (active/inactive) |
| `assigned_at` | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Assignment timestamp |

### Indexes & Constraints

```sql
-- Unique constraint: One provider can't have duplicate staff
UNIQUE INDEX provider_staff_unique (provider_id, staff_id)

-- Foreign keys with cascade delete
CONSTRAINT fk_provider FOREIGN KEY (provider_id) 
    REFERENCES xs_users(id) ON DELETE CASCADE

CONSTRAINT fk_staff FOREIGN KEY (staff_id) 
    REFERENCES xs_users(id) ON DELETE CASCADE

CONSTRAINT fk_assigned_by FOREIGN KEY (assigned_by) 
    REFERENCES xs_users(id) ON DELETE SET NULL

-- Performance index
INDEX idx_status (status)
```

### Migration Files
1. `2025-10-17-000002_CreateProviderStaffAssignments.php` - Initial table creation
2. `2025-10-21-173900_EnhanceProviderStaffAssignments.php` - Added assigned_by and status columns

---

## 2. Model Layer

### File: `app/Models/ProviderStaffModel.php`

#### Key Methods

##### Get Assignments
```php
// Get all staff for a provider
$staff = $model->getStaffByProvider($providerId, 'active');

// Get all providers for a staff member
$providers = $model->getProvidersForStaff($staffId, 'active');

// Get assignment details with audit info
$details = $model->getAssignmentDetails($providerId, $staffId);
```

##### Create/Update Assignments
```php
// Assign staff to provider (with audit trail)
$success = $model->assignStaff(
    providerId: 8,
    staffId: 7,
    assignedBy: session()->get('user_id'), // Current user ID for audit
    status: 'active'
);

// Check if assignment exists
$exists = $model->isStaffAssignedToProvider($staffId, $providerId, 'active');
```

##### Remove Assignments
```php
// Hard delete
$model->removeStaff($providerId, $staffId, softDelete: false);

// Soft delete (set status to 'inactive')
$model->removeStaff($providerId, $staffId, softDelete: true);
```

#### Features
- **Duplicate Prevention**: Checks existing assignments before creating
- **Soft Delete Support**: Can deactivate instead of delete
- **Audit Trail**: Tracks who assigned and when
- **Status Filtering**: Query active, inactive, or all assignments
- **Reactivation**: Inactive assignments can be reactivated

---

## 3. Controller Layer

### File: `app/Controllers/ProviderStaff.php`

Handles provider → staff assignments from provider's perspective.

#### Endpoints

##### POST `/provider-staff/assign`
```json
{
  "provider_id": 8,
  "staff_id": 7
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Staff member assigned successfully.",
  "staff": [...],
  "csrfToken": "new_token_here"
}
```

##### POST `/provider-staff/remove`
```json
{
  "provider_id": 8,
  "staff_id": 7
}
```

##### GET `/provider-staff/provider/{providerId}`
Returns all staff assigned to provider.

---

### File: `app/Controllers/StaffProviders.php`

Handles staff → provider assignments from staff's perspective (inverse view).

#### Endpoints

##### POST `/staff-providers/assign`
```json
{
  "staff_id": 7,
  "provider_id": 8
}
```

##### POST `/staff-providers/remove`
```json
{
  "staff_id": 7,
  "provider_id": 8
}
```

##### GET `/staff-providers/staff/{staffId}`
Returns all providers assigned to staff member.

---

## 4. Role-Based Access Control (RBAC)

### Permission Matrix

| Role | View Assignments | Assign Staff | Remove Staff | Notes |
|------|------------------|--------------|--------------|-------|
| **Admin** | All | ✓ Any provider | ✓ Any provider | Full access |
| **Provider** | Own staff only | ✓ Own staff | ✓ Own staff | Can manage their own team |
| **Staff/Receptionist** | Own providers | ✗ | ✗ | Read-only |
| **Customer** | ✗ | ✗ | ✗ | No access |

### Implementation Example (from ProviderStaff.php)
```php
$role = $currentUser['role'] ?? '';
if ($role === 'admin') {
    // Admins can assign any provider
} elseif ($role === 'provider' && $currentUserId === $providerId) {
    // Providers can assign their own staff
} else {
    return $this->failForbidden('You do not have permission...');
}
```

---

## 5. Frontend Integration

### Components

#### Provider Perspective: `app/Views/user_management/components/provider_staff.php`
- Shows staff assigned to a provider
- Allows adding/removing staff (if permitted)
- Embedded in provider edit view

#### Staff Perspective: `app/Views/user_management/components/staff_providers.php`
- Shows providers a staff member can access
- Allows admin to manage provider access
- Embedded in staff/receptionist edit view

### Key Features
- **Hidden Inputs**: Includes `staffId`/`providerId` and CSRF token
- **Button State Management**: Disabled until valid selection
- **Dynamic Refresh**: Updates list after assign/remove without page reload
- **CSRF Token Rotation**: Updates token from response headers
- **Toast Notifications**: Success/error feedback via XSNotify
- **Duplicate Prevention**: Disables already-assigned options

### JavaScript Flow (staff_providers.php)
```javascript
// 1. Initialize component per container
containers.forEach(function(container) {
    const staffId = container.querySelector('#staffId').value;
    const csrfInput = container.querySelector('[data-csrf]');
    
    // 2. Button state management
    function updateAssignButtonState() {
        const providerId = Number(providerSelect.value || '0');
        assignBtn.disabled = !providerId || providerId === 0;
    }
    
    // 3. Assign action
    assignBtn.addEventListener('click', function() {
        const formData = new FormData();
        formData.append('staff_id', staffId);
        formData.append('provider_id', providerId);
        formData.append(csrfName, csrfValue);
        
        fetch(assignUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(response => {
            updateCsrfFromResponse(response);
            return response.json();
        })
        .then(payload => {
            updateCsrfInputs(payload.csrfToken);
            toast({ type: 'success', message: payload.message });
            renderProviders(payload.providers);
            providerSelect.value = ''; // Clear selection
        });
    });
});
```

---

## 6. Create vs Edit View Handling

### Create View (`app/Views/user_management/create.php`)
- **No ID Available**: Shows info banner instead of assignment component
- **Message**: "Save this provider first, then return to assign staff members"
- **Behavior**: After save, redirects to edit view where ID exists

### Edit View (`app/Views/user_management/edit.php`)
- **ID Available**: Shows full assignment component
- **Data Passed**:
  ```php
  'providerId' => $user['id'],
  'staffId' => $user['id'],
  'assignedStaff' => [...],
  'availableStaff' => [...],
  'canManageAssignments' => true/false
  ```

---

## 7. Security Features

### CSRF Protection
- Token validated on every POST request
- New token returned in `X-CSRF-TOKEN` header
- Frontend updates all CSRF inputs with new token
- Prevents cross-site request forgery attacks

### Authorization Checks
1. **Authentication**: User must be logged in
2. **Role Check**: Must have permission for action
3. **Ownership**: Providers can only manage own staff (unless admin)
4. **Entity Validation**: Verifies provider/staff exist and have correct roles

### SQL Injection Prevention
- All IDs cast to integers
- Query builder uses prepared statements
- Foreign key constraints prevent orphaned records

---

## 8. Error Handling

### Common Error Responses

#### 401 Unauthorized
```json
{
  "status": 401,
  "error": "Authentication required."
}
```

#### 403 Forbidden
```json
{
  "status": 403,
  "error": "Only administrators can assign staff."
}
```

#### 409 Resource Exists
```json
{
  "status": 409,
  "error": "Staff member is already assigned to this provider."
}
```

#### 500 Server Error
```json
{
  "status": 500,
  "error": "Failed to assign staff: [error details]"
}
```

### Frontend Error Display
```javascript
.catch(function(error) {
    toast({ 
        type: 'error', 
        title: 'Assignment failed', 
        message: error.message 
    });
})
```

---

## 9. Testing Checklist

### Database Tests
- [ ] Table exists with correct schema
- [ ] Unique constraint prevents duplicates
- [ ] Foreign keys cascade on delete
- [ ] Status column defaults to 'active'
- [ ] Assigned_by tracks audit trail

### Model Tests
```php
// Test assignment creation
$result = $model->assignStaff(8, 7, 1);
assertTrue($result);

// Test duplicate prevention
$result = $model->assignStaff(8, 7, 1);
assertFalse($result); // Should fail - already assigned

// Test retrieval
$staff = $model->getStaffByProvider(8);
assertCount(1, $staff);
assertEquals(7, $staff[0]['id']);

// Test removal
$model->removeStaff(8, 7);
$staff = $model->getStaffByProvider(8);
assertEmpty($staff);
```

### Controller Tests
```php
// Test RBAC - admin can assign
$response = $this->actingAs(['role' => 'admin'])
    ->post('/provider-staff/assign', [
        'provider_id' => 8,
        'staff_id' => 7
    ]);
$response->assertStatus(201);

// Test RBAC - staff cannot assign
$response = $this->actingAs(['role' => 'staff'])
    ->post('/provider-staff/assign', [
        'provider_id' => 8,
        'staff_id' => 7
    ]);
$response->assertStatus(403);

// Test provider can assign own staff
$response = $this->actingAs(['id' => 8, 'role' => 'provider'])
    ->post('/provider-staff/assign', [
        'provider_id' => 8,
        'staff_id' => 7
    ]);
$response->assertStatus(201);

// Test provider cannot assign to others
$response = $this->actingAs(['id' => 8, 'role' => 'provider'])
    ->post('/provider-staff/assign', [
        'provider_id' => 9, // Different provider
        'staff_id' => 7
    ]);
$response->assertStatus(403);
```

### Frontend Tests
1. **Button Enable/Disable**
   - [ ] Button disabled initially
   - [ ] Button enables when provider selected
   - [ ] Button disables after assign (while processing)
   - [ ] Button re-enables after response

2. **Assignment Flow**
   - [ ] Select provider from dropdown
   - [ ] Click assign button
   - [ ] Success toast appears
   - [ ] Provider appears in assigned list
   - [ ] Dropdown resets to empty
   - [ ] Already-assigned providers show as disabled

3. **Removal Flow**
   - [ ] Click remove button on assigned provider
   - [ ] Confirmation dialog appears
   - [ ] Success toast on confirm
   - [ ] Provider removed from list
   - [ ] Provider re-appears in dropdown as available

4. **CSRF Handling**
   - [ ] Initial CSRF token present
   - [ ] Token sent in POST request
   - [ ] New token received in response
   - [ ] Hidden input updated with new token

---

## 10. Acceptance Criteria

### ✅ Database
- [x] Table `xs_provider_staff_assignments` exists
- [x] Columns: id, provider_id, staff_id, assigned_by, status, assigned_at
- [x] Unique constraint on (provider_id, staff_id)
- [x] Foreign keys with CASCADE/SET NULL

### ✅ Model
- [x] Methods: assignStaff, removeStaff, getStaffByProvider, getProvidersForStaff
- [x] Supports assigned_by parameter (audit trail)
- [x] Supports status field (active/inactive)
- [x] Prevents duplicates
- [x] Soft delete option

### ✅ Controllers
- [x] ProviderStaff controller with assign/remove/list endpoints
- [x] StaffProviders controller with assign/remove/list endpoints
- [x] RBAC enforcement (admin, provider self-management)
- [x] CSRF token rotation
- [x] Comprehensive error responses

### ✅ Frontend
- [x] provider_staff.php component (provider perspective)
- [x] staff_providers.php component (staff perspective)
- [x] Hidden inputs for ID and CSRF
- [x] Button enable/disable logic
- [x] Fetch POST with FormData
- [x] Dynamic list refresh
- [x] Toast notifications
- [x] Create view shows info banner (no ID yet)
- [x] Edit view shows full component (ID available)

### ✅ Security
- [x] CSRF protection on all mutations
- [x] Role-based authorization
- [x] Input validation (IDs, role checks)
- [x] SQL injection prevention
- [x] Audit trail (assigned_by)

---

## 11. Maintenance & Future Enhancements

### Current Limitations
- No bulk assignment support
- No assignment history/changelog
- Status is simple string (could be enum)
- No notification on assignment changes

### Potential Improvements
1. **Bulk Operations**: Assign multiple staff at once
2. **Assignment History**: Track all changes with timestamps
3. **Notifications**: Email/in-app alerts on assignment changes
4. **Permissions**: More granular permissions (read-only assignments)
5. **Analytics**: Track assignment patterns and staff utilization
6. **Import/Export**: CSV upload for bulk assignments
7. **Assignment Templates**: Predefined staff groups

---

## 12. Troubleshooting

### Issue: "Provider identifier missing" banner on edit page
**Cause**: `$providerId` not passed to component or is null  
**Fix**: Verify controller passes `'providerId' => $user['id']` to edit view

### Issue: Assign button stays disabled
**Cause**: JavaScript not finding hidden `staffId` input or CSRF token  
**Fix**: Check browser console, verify inputs exist with correct data attributes

### Issue: CSRF token mismatch error
**Cause**: Token not refreshed after POST or page cached  
**Fix**: Ensure `updateCsrfFromResponse()` reads `X-CSRF-TOKEN` header

### Issue: Duplicate assignment error
**Cause**: Trying to assign already-assigned staff  
**Fix**: Check `isStaffAssignedToProvider()` before calling `assignStaff()`

### Issue: 403 Forbidden when provider tries to assign
**Cause**: Provider trying to assign staff to different provider  
**Fix**: Ensure `providerId === currentUserId` in RBAC check

---

## Contact & Support

For questions or issues with the Provider–Staff Assignment System:
- **Docs**: `/docs/PROVIDER_STAFF_ASSIGNMENT_SYSTEM.md`
- **Migration Logs**: Check `xs_migrations` table
- **Error Logs**: `writable/logs/log-YYYY-MM-DD.log`
- **Database**: Use `php spark db:table xs_provider_staff_assignments` to inspect data

Last Updated: October 21, 2025

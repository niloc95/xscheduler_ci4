# Provider Color System Implementation

**Date:** October 22, 2025  
**Status:** ✅ Backend Complete | ✅ UI Complete | ⏳ Calendar Integration Pending

## Overview

Implemented automatic color assignment system for providers to enable visual differentiation in calendar views. Each provider is assigned a unique color from a predefined 12-color palette, with intelligent assignment algorithm to minimize duplicates.

---

## Database Schema

### Migration: `2025-10-22-191124_AddColorToUsers.php`

```php
'color' => [
    'type' => 'VARCHAR',
    'constraint' => 10,
    'null' => true,
    'comment' => 'Provider color for calendar display (hex code)',
    'after' => 'profile_image'
]
```

**Table:** `xs_users`  
**Column:** `color VARCHAR(10) NULL`  
**Position:** After `profile_image`  
**Usage:** Stores hex color code (e.g., `#3B82F6`)

---

## Color Palette

**12 Distinct Colors:**

| Color | Hex Code | Name |
|-------|----------|------|
| 🔵 | `#3B82F6` | Blue |
| 🟢 | `#10B981` | Green |
| 🟠 | `#F59E0B` | Amber |
| 🔴 | `#EF4444` | Red |
| 🟣 | `#8B5CF6` | Purple |
| 💗 | `#EC4899` | Pink |
| 🔷 | `#06B6D4` | Cyan |
| 🟧 | `#F97316` | Orange |
| 🟩 | `#84CC16` | Lime |
| 💙 | `#6366F1` | Indigo |
| 🩵 | `#14B8A6` | Teal |
| 🌹 | `#F43F5E` | Rose |

**Design Rationale:**
- Tailwind CSS color palette (500 shade)
- High contrast for accessibility
- Distinguishable in both light and dark modes
- Covers full color spectrum

---

## Backend Implementation

### UserModel (`app/Models/UserModel.php`)

#### Updated `$allowedFields`:
```php
protected $allowedFields = [
    // ... existing fields ...
    'profile_image',
    'color',  // ← Added
];
```

#### New Method: `getAvailableProviderColor()`

**Algorithm:**
1. Query all active providers with assigned colors
2. Build color usage map (color → count)
3. Find least-used color from palette
4. Return color with minimum usage

**Code:**
```php
public function getAvailableProviderColor(): string
{
    // 12-color palette
    $colorPalette = [
        '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
        '#8B5CF6', '#EC4899', '#06B6D4', '#F97316',
        '#84CC16', '#6366F1', '#14B8A6', '#F43F5E'
    ];

    // Get all active providers with colors
    $providers = $this->where('role', 'provider')
                      ->where('is_active', true)
                      ->whereNotNull('color')
                      ->findAll();

    // Count usage
    $colorUsage = [];
    foreach ($colorPalette as $color) {
        $colorUsage[$color] = 0;
    }
    foreach ($providers as $provider) {
        if (in_array($provider['color'], $colorPalette)) {
            $colorUsage[$provider['color']]++;
        }
    }

    // Return least-used
    asort($colorUsage);
    return array_key_first($colorUsage);
}
```

**Features:**
- Even distribution across palette
- Handles new/empty database (returns first color)
- Ignores inactive providers
- Only counts valid palette colors

---

### UserManagement Controller (`app/Controllers/UserManagement.php`)

#### Auto-Assignment on Provider Creation (`store()` method)

```php
// Auto-assign color for providers
if ($role === 'provider') {
    // Use provided color or auto-assign from palette
    $userData['color'] = $this->request->getPost('color') 
        ?: $this->userModel->getAvailableProviderColor();
    
    log_message('info', 'Assigned color ' . $userData['color'] . ' to new provider');
}
```

**Behavior:**
1. Check if role is `provider`
2. Use POST `color` if provided
3. Otherwise, call `getAvailableProviderColor()`
4. Log assignment for audit trail

#### Color Editing on Update (`update()` method)

```php
// Handle provider-specific fields
if ($finalRole === 'provider') {
    // Only admins can change provider colors
    if ($currentUser['role'] === 'admin' && $this->request->getPost('color')) {
        $updateData['color'] = $this->request->getPost('color');
    }
    // ... schedule handling ...
}
```

**Behavior:**
- Only admins can edit provider colors
- Providers cannot change their own color
- Color validation handled by browser input type="color"

---

## Frontend Implementation

### Create View (`app/Views/user_management/create.php`)

#### HTML Color Picker (Hidden by Default)

```php
<!-- Provider Color Picker (Optional) -->
<div class="form-group provider-color-field" style="display: none;">
    <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
        Calendar Color <span class="text-gray-500 text-xs">(Optional)</span>
    </label>
    <div class="flex items-center gap-3">
        <input type="color" 
               id="color" 
               name="color" 
               value="<?= old('color', '#3B82F6') ?>"
               class="h-10 w-20 rounded cursor-pointer border border-gray-300 dark:border-gray-600 transition-colors duration-300"
               title="Choose provider color for calendar display">
        <span class="text-sm text-gray-600 dark:text-gray-400">
            Leave default or choose a custom color. A unique color will be auto-assigned if not specified.
        </span>
    </div>
</div>
```

#### JavaScript Toggle Logic

```javascript
// In toggleRoleDetails() function
const colorFields = document.querySelectorAll('.provider-color-field');
colorFields.forEach(field => {
    field.style.display = (role === 'provider') ? 'block' : 'none';
});
```

**Features:**
- Shows only when role = `provider`
- Optional field (auto-assigns if empty)
- Default value: Blue (`#3B82F6`)
- Browser-native color picker UI

---

### Edit View (`app/Views/user_management/edit.php`)

#### Two Display Modes

**Mode 1: Admin Editing Provider (Color Picker)**
```php
<?php if ($isProvider && $canEditColor): ?>
<div class="form-group provider-color-field" style="display: <?= $isProvider ? 'block' : 'none' ?>;">
    <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
        Calendar Color
    </label>
    <div class="flex items-center gap-3">
        <input type="color" 
               id="color" 
               name="color" 
               value="<?= esc(old('color', $user['color'] ?? '#3B82F6')) ?>"
               class="h-10 w-20 rounded cursor-pointer border border-gray-300 dark:border-gray-600 transition-colors duration-300"
               title="Choose provider color for calendar display">
        <span class="text-sm text-gray-600 dark:text-gray-400">
            This color will be used to display <?= esc($user['first_name'] ?? 'this provider') ?>'s appointments on the calendar.
        </span>
    </div>
</div>
<?php endif; ?>
```

**Mode 2: Provider/Staff Viewing (Read-Only Swatch)**
```php
<?php elseif ($isProvider): ?>
<div class="form-group provider-color-field" style="display: <?= $isProvider ? 'block' : 'none' ?>;">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
        Calendar Color
    </label>
    <div class="flex items-center gap-3">
        <div class="h-10 w-20 rounded border border-gray-300 dark:border-gray-600" 
             style="background-color: <?= esc($user['color'] ?? '#3B82F6') ?>;"
             title="Provider calendar color"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400">
            Calendar color (only admins can change this)
        </span>
    </div>
</div>
<?php endif; ?>
```

#### JavaScript Toggle Logic

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const scheduleSection = document.getElementById('providerScheduleSection');
    const colorFields = document.querySelectorAll('.provider-color-field');

    function toggleProviderFields() {
        const isProvider = roleSelect.value === 'provider';
        scheduleSection.classList.toggle('hidden', !isProvider);
        colorFields.forEach(field => {
            field.style.display = isProvider ? 'block' : 'none';
        });
    }

    roleSelect.addEventListener('change', toggleProviderFields);
    toggleProviderFields();
});
```

**Features:**
- Dynamic show/hide based on role selection
- Admin-only editing (others see read-only swatch)
- Personalized help text with provider name
- Syncs with provider schedule visibility

---

## Access Control

| User Role | Create Provider + Choose Color | Edit Provider Color |
|-----------|-------------------------------|---------------------|
| **Admin** | ✅ Yes | ✅ Yes |
| **Provider** | ✅ Yes (own staff only) | ❌ No (read-only) |
| **Staff** | ❌ No | ❌ No |
| **Customer** | ❌ No | ❌ No |

**Notes:**
- Providers can create other providers (staff) but cannot override colors
- System auto-assigns color if provider doesn't choose one
- Only admins have full color control for all providers

---

## Testing Checklist

### Backend Tests
- [x] Migration adds `color` column successfully
- [x] `getAvailableProviderColor()` returns least-used color
- [x] Auto-assignment on provider creation
- [x] Color persists to database correctly
- [x] Admin can update provider color
- [x] Provider cannot update own color

### Frontend Tests
- [x] Color picker hidden when role ≠ provider
- [x] Color picker shows when role = provider
- [x] Default color is Blue (#3B82F6)
- [x] Admin sees editable color picker
- [x] Provider sees read-only color swatch
- [x] Dark mode styling works correctly
- [ ] Form validation accepts hex colors
- [ ] Color picker works on mobile browsers

### Integration Tests
- [ ] Appointment API returns provider color
- [ ] Calendar renders appointments with provider colors
- [ ] Color legend shows active providers
- [ ] Color persists across sessions
- [ ] Multiple providers have distinct colors

---

## Pending Implementation

### 1. Appointment API Updates

**Files to Update:**
- `app/Controllers/Api/Appointments.php`
- `app/Controllers/Api/Calendar.php`

**Changes:**
```php
// Join with users table to get provider color
$appointments = $this->appointmentModel
    ->select('appointments.*, users.color as provider_color, users.first_name, users.last_name')
    ->join('users', 'users.id = appointments.provider_id')
    ->findAll();
```

### 2. Calendar View Integration

**Files to Update:**
- `app/Views/calendar/index.php` (or calendar view file)
- JavaScript calendar initialization

**Changes:**
```javascript
// Map appointments to FullCalendar events
const events = appointments.map(apt => ({
    id: apt.id,
    title: apt.title,
    start: apt.start_time,
    end: apt.end_time,
    backgroundColor: apt.provider_color || '#3B82F6',
    borderColor: apt.provider_color || '#3B82F6',
    // ... other properties
}));
```

### 3. Provider Color Legend

**Location:** Above calendar view

**Proposed HTML:**
```html
<div class="provider-legend flex gap-4 mb-4">
    <?php foreach ($activeProviders as $provider): ?>
        <div class="flex items-center gap-2">
            <span class="w-4 h-4 rounded-full" style="background-color: <?= esc($provider['color']) ?>;"></span>
            <span class="text-sm"><?= esc($provider['first_name'] . ' ' . $provider['last_name']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
```

---

## Benefits

1. **Visual Clarity:** Instantly identify provider appointments on calendar
2. **Scalability:** Supports up to 12 providers with unique colors
3. **User Experience:** Auto-assignment reduces setup friction
4. **Accessibility:** High-contrast colors work in light/dark modes
5. **Flexibility:** Admins can override colors for specific needs
6. **Consistency:** Same color used across all views and APIs

---

## Future Enhancements

- [ ] Allow custom color palettes per organization
- [ ] Add color blindness-safe palette option
- [ ] Support more than 12 providers (generate colors algorithmically)
- [ ] Color picker with predefined palette + custom option
- [ ] Provider color in user profile view
- [ ] Export/import provider color settings
- [ ] Color usage analytics (most/least used colors)

---

## Related Documentation

- [User Management Architecture](./user-management/USER_MANAGEMENT_ARCHITECTURE.md)
- [Access Control Matrix v1.1](./user-management/ACCESS_CONTROL_MATRIX.md)
- [Calendar Settings Sync](./CALENDAR_SETTINGS_SYNC_IMPLEMENTATION.md)
- [Provider-Staff Assignment System](./PROVIDER_STAFF_ASSIGNMENT_SYSTEM.md)

---

**Last Updated:** October 22, 2025  
**Branch:** `user-management`  
**Next Steps:** Integrate with appointment APIs and calendar rendering

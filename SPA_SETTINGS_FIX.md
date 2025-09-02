# SPA Settings Form Value Persistence Fix

## Issue Description
When navigating away from the Settings page and then returning to it (without refreshing), the field values disappeared. However, upon refreshing the page, the original values reappeared.

## Root Cause Analysis
The problem was caused by a **race condition in the Single Page Application (SPA) navigation**:

1. **SPA Navigation**: When clicking a link, the SPA system replaced the entire DOM content of `#spa-content`
2. **Empty Form Fields**: The new DOM had empty form fields (since they're rendered server-side without values)
3. **AJAX Loading**: The JavaScript `initSettingsApi()` function made an AJAX call to load values
4. **Race Condition**: The user could see empty fields before the AJAX response populated them
5. **Navigation Timing**: If user navigated away before AJAX completed, values were lost

## Solution Implemented

### 1. Server-Side Value Loading
**Before**: Form fields were empty and populated via AJAX
```php
// OLD: Empty form fields
<input name="company_name" required class="form-input" placeholder="Acme Inc." />
```

**After**: Form fields are populated server-side with current values
```php  
// NEW: Pre-populated form fields
<input name="company_name" required class="form-input" placeholder="Acme Inc." 
       value="<?= esc($settings['general.company_name'] ?? '') ?>" />
```

### 2. Enhanced Settings Controller
**Modified**: `app/Controllers/Settings.php`
- Added `SettingModel` initialization in `index()` method
- Loaded all necessary settings from database
- Passed settings array to view via `$data['settings']`

```php
public function index()
{
    // Load current settings to pass to the view
    $settingModel = new SettingModel();
    $settings = $settingModel->getByKeys([
        'general.company_name',
        'general.company_email', 
        'general.company_link',
        // ... all other settings
    ]);
    
    $data = [
        'user' => session()->get('user'),
        'settings' => $settings, // Pass settings to view
    ];

    return view('settings', $data);
}
```

### 3. Updated Form Fields
**Modified**: All form fields in `app/Views/settings.php`

**Text Inputs**:
```php
<input name="company_name" value="<?= esc($settings['general.company_name'] ?? '') ?>" />
```

**Select Dropdowns**:
```php
<select name="date_format">
    <option value="DMY" <?= ($settings['localization.date_format'] ?? '') === 'DMY' ? 'selected' : '' ?>>DMY</option>
    <option value="MDY" <?= ($settings['localization.date_format'] ?? '') === 'MDY' ? 'selected' : '' ?>>MDY</option>
</select>
```

**Time Inputs**:
```php
<input type="time" name="work_start" value="<?= esc($settings['business.work_start'] ?? '09:00') ?>">
```

**Textareas**:
```php
<textarea name="blocked_periods"><?= esc(is_array($settings['business.blocked_periods'] ?? '') ? json_encode($settings['business.blocked_periods']) : ($settings['business.blocked_periods'] ?? '')) ?></textarea>
```

### 4. Simplified JavaScript Logic
**Modified**: JavaScript in `app/Views/settings.php`
- Removed AJAX call to load values (no longer needed)
- Simplified initialization to use server-provided values
- Maintained edit/cancel functionality
- Kept logo preview functionality

**Before**: AJAX-dependent initialization
```javascript
// Load existing values
fetch(`${apiBase}?prefix=general.`)
    .then(r => r.json())
    .then(({ ok, data }) => {
        // Populate form fields from AJAX response
    });
```

**After**: Direct initialization with server values
```javascript
// Store initial values for cancel functionality (already populated server-side)
let initialValues = {
    company_name: (generalPanel.querySelector('[name="company_name"]')?.value || ''),
    company_email: (generalPanel.querySelector('[name="company_email"]')?.value || ''),
    company_link: (generalPanel.querySelector('[name="company_link"]')?.value || '')
};

// Start locked by default - values are already populated from server
setLockedState(true);
```

## Benefits of This Solution

### ✅ **Immediate Value Display**
- Form fields show correct values instantly upon page load
- No waiting for AJAX calls or loading states
- Better user experience with no flash of empty content

### ✅ **SPA Navigation Compatibility**  
- Values persist correctly during SPA navigation
- No race conditions between DOM replacement and AJAX calls
- Consistent behavior whether navigating via SPA or direct page load

### ✅ **Performance Improvement**
- Eliminates unnecessary AJAX request on every page load
- Faster page rendering since values are embedded in HTML
- Reduced server requests and JavaScript complexity

### ✅ **Reliability Enhancement**
- No dependency on JavaScript for initial value display
- Works even if JavaScript fails or loads slowly
- More robust fallback behavior

## Testing Instructions

To verify the fix:

1. **Start the server**: `php spark serve --host=0.0.0.0 --port=8080`
2. **Navigate to Settings**: Go to http://localhost:8080/settings
3. **Check Initial Values**: Form fields should show current values immediately
4. **Navigate Away**: Click on Dashboard or another page
5. **Return to Settings**: Click on Settings again
6. **Verify Persistence**: Form fields should still show correct values (no blanks)
7. **Edit Values**: Click Edit, modify some fields
8. **Navigate and Return**: Values should be preserved during navigation
9. **Test Cancel**: Edit values, click Cancel - should revert to saved values

## Files Modified

1. **`app/Controllers/Settings.php`**
   - Enhanced `index()` method to load and pass settings to view

2. **`app/Views/settings.php`**  
   - Updated all form fields to use server-provided values
   - Simplified JavaScript initialization logic
   - Maintained edit/cancel/preview functionality

## Summary

The issue was successfully resolved by **shifting from client-side AJAX value loading to server-side value embedding**. This eliminates the race condition that occurred during SPA navigation and provides a more reliable, performant, and user-friendly experience.

The fix ensures that:
- ✅ Settings form values are immediately visible
- ✅ Values persist correctly during SPA navigation  
- ✅ No flash of empty content or loading states
- ✅ Better performance with fewer HTTP requests
- ✅ More reliable operation independent of JavaScript timing

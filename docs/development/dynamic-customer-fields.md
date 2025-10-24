# Dynamic Customer Form Fields Implementation

## Overview
The Customer Management system now dynamically adapts form fields based on Settings → Booking Tab configuration. This ensures consistency between customer creation and the booking process, preventing validation issues.

## Problem Statement
Previously, all customer form fields were displayed regardless of the booking settings configuration. This led to:
- **Inconsistencies**: Fields required during booking but not captured during customer creation
- **Validation conflicts**: Server-side validation differed from booking form requirements
- **Poor UX**: Customers seeing disabled fields that serve no purpose

## Solution Architecture

### Components Created

#### 1. **BookingSettingsService** (`app/Services/BookingSettingsService.php`)
Centralized service for managing booking field configuration.

**Key Methods:**
- `getFieldConfiguration()`: Returns complete field config (display + required flags)
- `getValidationRules()`: Generates CodeIgniter validation rules based on settings
- `getValidationRulesForUpdate($customerId)`: Rules with unique email exception for updates
- `isFieldDisplayed($fieldName)`: Check if field should be visible
- `isFieldRequired($fieldName)`: Check if field is required
- `getVisibleFields()`: Array of visible field names
- `getRequiredFields()`: Array of required field names

**Supported Fields:**
- `first_name` → `booking.first_names_display` / `booking.first_names_required`
- `last_name` → `booking.surname_display` / `booking.surname_required`
- `email` → `booking.email_display` / `booking.email_required`
- `phone` → `booking.phone_display` / `booking.phone_required`
- `address` → `booking.address_display` / `booking.address_required`
- `notes` → `booking.notes_display` / `booking.notes_required`

#### 2. **CustomerManagement Controller Updates**
**Modified Methods:**
- `create()`: Passes `$fieldConfig` to view
- `store()`: Uses dynamic validation rules, only saves displayed fields
- `edit()`: Passes `$fieldConfig` to view
- `update()`: Uses dynamic validation rules with ID-aware email uniqueness check

**Key Changes:**
```php
// Old approach (hardcoded validation)
$rules = [
    'first_name' => 'permit_empty|max_length[100]',
    'email' => 'required|valid_email|is_unique[customers.email]',
    // ... all fields always validated
];

// New approach (dynamic based on settings)
$rules = $this->bookingSettings->getValidationRules();
// Only displayed fields are validated and saved
```

#### 3. **View Templates Updated**
**Files Modified:**
- `app/Views/customer_management/create.php`
- `app/Views/customer_management/edit.php`

**Dynamic Rendering:**
```php
<?php if ($fieldConfig['first_name']['display'] ?? false): ?>
    <div>
        <label>
            First name
            <?php if ($fieldConfig['first_name']['required'] ?? false): ?>
                <span class="text-red-500">*</span>
            <?php endif; ?>
        </label>
        <input 
            type="text" 
            name="first_name" 
            <?php if ($fieldConfig['first_name']['required'] ?? false): ?>required<?php endif; ?>
        />
    </div>
<?php endif; ?>
```

**Features:**
- Conditional field rendering (only visible if `display=true`)
- Dynamic `required` attribute based on settings
- Visual asterisk (*) indicator for required fields
- Maintains Tailwind CSS styling and dark mode support

## Settings Configuration

### Location
Settings → Booking Tab

### Available Controls
Each field has two toggles:
1. **Display Checkbox**: Show/hide field in customer form
2. **Required Checkbox**: Make field mandatory (only if Display is enabled)

### Example Configuration
```
✅ First Names Display    ✅ First Names Required
✅ Surname Display        ⬜ Surname Required
✅ Email Display          ✅ Email Required
✅ Phone Display          ✅ Phone Required
⬜ Address Display        ⬜ Address Required
✅ Notes Display          ⬜ Notes Required
```

This configuration would:
- Show: First Name* (required), Surname, Email* (required), Phone* (required), Notes
- Hide: Address field completely

## Testing Guide

### Manual Testing Steps

1. **Navigate to Settings → Booking Tab**
   - Access: `http://localhost/settings` (admin login required)

2. **Configure Field Visibility**
   - Test Case 1: Disable "Address Display" → Save
   - Test Case 2: Enable "Phone Required" → Save
   - Test Case 3: Disable "First Names Display" → Save

3. **Test Create Customer Form**
   - Navigate to `http://localhost/customer-management/create`
   - Verify:
     - ✅ Hidden fields do NOT appear
     - ✅ Required fields show red asterisk (*)
     - ✅ Required fields have `required` HTML attribute
     - ✅ Form layout adapts properly (no empty gaps)

4. **Test Form Submission**
   - **Test Required Field Validation:**
     - Leave required field empty → Submit
     - Should see browser validation error
   
   - **Test Server-Side Validation:**
     - Disable browser validation (dev tools)
     - Submit with missing required field
     - Should see CodeIgniter validation error
   
   - **Test Successful Submission:**
     - Fill all visible required fields
     - Submit → Should redirect to `/customer-management` with success message

5. **Test Edit Customer Form**
   - Click "Edit" on existing customer
   - Verify same field visibility as create form
   - Test update with changed field values
   - Verify email uniqueness check works (except for current customer)

6. **Test Dynamic Updates**
   - Create customer with Phone field visible
   - Go to Settings → Disable "Phone Display" → Save
   - Reload Create Customer form
   - Verify Phone field no longer appears
   - Create new customer without phone
   - Edit existing customer (with phone data)
   - Verify phone field still hidden even though data exists

### Automated Testing (Future)

**Unit Tests:**
```php
// Test BookingSettingsService
testGetFieldConfiguration() // Verifies correct settings mapping
testGetValidationRules() // Checks generated rules
testIsFieldDisplayed() // Tests visibility logic
testIsFieldRequired() // Tests required logic
```

**Integration Tests:**
```php
// Test CustomerManagement
testCreateFormShowsOnlyEnabledFields()
testStoreValidatesRequiredFields()
testStoreIgnoresHiddenFields()
testUpdateRespectsFieldSettings()
```

**End-to-End Tests (Cypress):**
```javascript
// Test full workflow
describe('Customer Form Dynamic Fields', () => {
  it('hides disabled fields', () => {
    cy.visit('/settings')
    cy.get('[name="booking_address_display"]').uncheck()
    cy.get('button[type="submit"]').click()
    cy.visit('/customer-management/create')
    cy.get('[name="address"]').should('not.exist')
  })
})
```

## Validation Logic

### Display Logic
- `display=true` → Field appears in form
- `display=false` → Field hidden entirely (not rendered)

### Required Logic
- `required=true AND display=true` → Field has `required` attribute + server validation
- `required=true AND display=false` → Field ignored (hidden fields can't be required)
- `required=false` → Field optional, uses `permit_empty` validation

### Field-Specific Rules
| Field | Display | Required | Validation Rules |
|-------|---------|----------|------------------|
| first_name | ✅ | ✅ | `required\|max_length[100]` |
| first_name | ✅ | ⬜ | `permit_empty\|max_length[100]` |
| first_name | ⬜ | N/A | `permit_empty` |
| email | ✅ | ✅ | `required\|valid_email\|is_unique[customers.email]` |
| phone | ✅ | ✅ | `required\|max_length[20]` |

## Database Considerations

### Field Storage
- **Hidden fields**: Not submitted, not validated, not saved
- **Existing data**: Preserved even if field becomes hidden
- **Re-enabling fields**: Previously saved data becomes visible again

### Migration Impact
No database changes required. This is purely a **presentation layer** feature using existing `xs_customers` table schema.

## Future Enhancements

### Phase 2: Custom Fields Support
Extend to support custom fields defined in booking settings:
- `booking.custom_field_1_enabled`
- `booking.custom_field_1_title`
- `booking.custom_field_1_type` (text, textarea, select, etc.)
- `booking.custom_field_1_required`

### Phase 3: Appointment Booking Integration
Reuse `BookingSettingsService` in:
- Public appointment booking form
- API endpoints for customer registration
- Customer self-service portal

### Phase 4: Conditional Logic
Add dependencies between fields:
- "If Phone Required, show Phone Type dropdown"
- "If Address shown, enable Address Autocomplete"

### Phase 5: Field Ordering
Allow admins to reorder fields via drag-and-drop in Settings.

## API/Service Usage

### In Controllers
```php
use App\Services\BookingSettingsService;

class YourController extends BaseController {
    protected $bookingSettings;
    
    public function __construct() {
        $this->bookingSettings = new BookingSettingsService();
    }
    
    public function yourMethod() {
        // Get full config
        $config = $this->bookingSettings->getFieldConfiguration();
        
        // Get validation rules
        $rules = $this->bookingSettings->getValidationRules();
        
        // Check specific field
        if ($this->bookingSettings->isFieldDisplayed('phone')) {
            // Phone field is enabled
        }
    }
}
```

### In Views
```php
<!-- Check if field should be displayed -->
<?php if ($fieldConfig['email']['display'] ?? false): ?>
    <input 
        name="email" 
        <?php if ($fieldConfig['email']['required'] ?? false): ?>required<?php endif; ?>
    />
<?php endif; ?>
```

## Troubleshooting

### Issue: Field still shows despite being disabled
**Solution:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh page (Ctrl+F5)
3. Check Settings table: `SELECT * FROM xs_settings WHERE setting_key LIKE 'booking.%_display'`
4. Verify controller is loading fresh config (not cached)

### Issue: Validation error for hidden field
**Cause:** Form might have hidden `<input type="hidden">` fields

**Solution:**
- Remove any hardcoded hidden inputs for customer fields
- Let controller determine which fields to process

### Issue: Required field not enforced
**Check:**
1. HTML `required` attribute present? (View Source)
2. Server-side validation rule includes `required`? (Check logs)
3. JavaScript validation not disabled? (Check console)

## Related Documentation
- [Settings Configuration Guide](./configuration/settings-guide.md)
- [Customer Management Overview](./customer-management.md)
- [Booking Flow Architecture](./SCHEDULER_ARCHITECTURE.md)

## Changelog

### 2025-01-13 - Initial Implementation
- Created `BookingSettingsService` with field configuration logic
- Updated `CustomerManagement` controller to use dynamic validation
- Modified `create.php` and `edit.php` views for conditional rendering
- Documented testing procedures and API usage

---

**Implemented By:** GitHub Copilot  
**Date:** January 13, 2025  
**Branch:** `user-management` (to be merged to main after testing)

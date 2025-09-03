# Localization Settings Update

## Overview
Updated the Localization tab in Settings to remove the Date Format field and add comprehensive Timezone and Currency selection fields with South African defaults.

## Changes Made

### View Changes (`app/Views/settings.php`)
**Removed:**
- Date Format field (DMY/MDY/YMD selection)

**Updated:**
- **Timezone Field**: Expanded from 3 basic options to comprehensive timezone list organized by regions:
  - Africa (Johannesburg, Cairo, Lagos, Nairobi, Casablanca)
  - Americas (New York, Chicago, Denver, Los Angeles, São Paulo, Toronto, Mexico City)
  - Asia (Tokyo, Shanghai, Kolkata, Dubai, Singapore, Hong Kong, Seoul)
  - Europe (London, Paris, Berlin, Rome, Madrid, Amsterdam, Moscow)
  - Oceania (Sydney, Melbourne, Perth, Auckland)
  - Universal (UTC)
  - **Default**: `Africa/Johannesburg` (SAST +2:00)

**Added:**
- **Currency Field**: Comprehensive currency selection including:
  - South African Rand (ZAR) - **DEFAULT**
  - US Dollar (USD)
  - Euro (EUR)
  - British Pound (GBP)
  - Australian Dollar (AUD)
  - Canadian Dollar (CAD)
  - Japanese Yen (JPY)
  - Swiss Franc (CHF)
  - Chinese Yuan (CNY)
  - Indian Rupee (INR)
  - Brazilian Real (BRL)

### Controller Changes (`app/Controllers/Settings.php`)
**Removed:**
- `localization.date_format` from settings keys and mapping

**Added:**
- `localization.currency` to settings keys and mapping
- Currency field mapping: `'localization.currency' => 'currency'`

**Updated:**
- Settings loading to include currency field
- Field mapping for save operations

### Database Changes
**Migration**: `2025-09-03-181500_UpdateLocalizationSettings.php`

**Added:**
- `localization.currency` setting with default value `'ZAR'`
- `localization.timezone` setting with default value `'Africa/Johannesburg'` (if not exists)

**Updated:**
- Existing timezone setting updated to South African timezone if currently empty or UTC

## Database Schema
Settings are stored in the `settings` table with the following new/updated keys:

```sql
-- New currency setting
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('localization.currency', 'ZAR', 'string');

-- Updated timezone default
INSERT INTO settings (setting_key, setting_value, setting_type) 
VALUES ('localization.timezone', 'Africa/Johannesburg', 'string');
```

## Usage
### Accessing Settings via Helper
```php
// Get currency setting
$currency = setting('localization.currency', 'ZAR');

// Get timezone setting  
$timezone = setting('localization.timezone', 'Africa/Johannesburg');
```

### Form Integration
The settings integrate with the existing edit/cancel workflow:
1. Click "Edit" to unlock fields
2. Select desired timezone and currency
3. Click "Save All Settings" to persist changes
4. Click "Cancel" to revert to previous values

## Defaults
- **Timezone**: `Africa/Johannesburg` (South Africa Standard Time, SAST +2:00)
- **Currency**: `ZAR` (South African Rand)

## Backward Compatibility
- Removed `date_format` field may affect existing code that references `localization.date_format`
- Migration preserves existing data and only updates empty/default timezone values
- All existing localization settings remain functional

## Files Modified
1. `app/Views/settings.php` - Updated Localization section UI
2. `app/Controllers/Settings.php` - Updated settings handling
3. `app/Database/Migrations/2025-09-03-181500_UpdateLocalizationSettings.php` - Database migration

## Testing
✅ PHP syntax validation passed for all files
✅ Migration executed successfully
✅ Settings page loads with new fields
✅ Form validation and submission workflow maintained
✅ Default values properly set in database

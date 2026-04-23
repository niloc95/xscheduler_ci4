# Appointment Color-Coding System

## Overview

The appointment system uses a dual-color identification scheme:
- **Status Colors** (Primary): Background/border colors indicate appointment status
- **Provider Dots** (Secondary): Small colored dots identify which provider owns the appointment

This allows users to quickly identify both the appointment's current state and the assigned provider at a glance.

## Status Colors

### Light Mode

| Status    | Background | Border    | Text      | Badge     |
|-----------|------------|-----------|-----------|-----------|
| Pending   | #FEF3C7    | #F59E0B   | #78350F   | #F59E0B   |
| Confirmed | #DBEAFE    | #3B82F6   | #1E3A8A   | #3B82F6   |
| Completed | #D1FAE5    | #10B981   | #064E3B   | #10B981   |
| Cancelled | #FEE2E2    | #EF4444   | #7F1D1D   | #EF4444   |
| No Show   | #F3F4F6    | #6B7280   | #1F2937   | #6B7280   |

### Dark Mode

| Status    | Background | Border    | Text      | Badge     |
|-----------|------------|-----------|-----------|-----------|
| Pending   | #78350F    | #F59E0B   | #FEF3C7   | #F59E0B   |
| Confirmed | #1E3A8A    | #3B82F6   | #DBEAFE   | #3B82F6   |
| Completed | #064E3B    | #10B981   | #D1FAE5   | #10B981   |
| Cancelled | #7F1D1D    | #EF4444   | #FEE2E2   | #EF4444   |
| No Show   | #374151    | #9CA3AF   | #F3F4F6   | #9CA3AF   |

## Provider Colors

Provider colors are stored in the `xs_users.color` column (VARCHAR 10, stores hex codes).

**Default Color**: `#3B82F6` (blue-500) if no color is assigned.

Each provider's color appears as a small dot (2-3px circle) within appointment cards, allowing multiple providers' appointments to be visually distinguished even when they have the same status.

## Implementation

### Core Module

All color logic is centralized in:
```javascript
resources/js/modules/scheduler/appointment-colors.js
```

### Key Functions

#### `getStatusColors(status, darkMode)`
Returns color scheme object for a given status:
```javascript
{
  bg: '#FEF3C7',      // Background color
  border: '#F59E0B',  // Border color
  text: '#78350F',    // Text color
  dot: '#F59E0B'      // Badge/dot color
}
```

#### `getProviderColor(provider)`
Returns provider's assigned color or default blue.

#### `isDarkMode()`
Detects if dark mode is active (checks DOM class or system preference).

#### `getAppointmentStyles(appointment, provider)`
Returns complete CSS style string for appointment cards.

#### `getProviderDotHtml(provider, size)`
Generates HTML for provider color dot indicator.

### View Integration

The color system is integrated into all scheduler views:

**Month View** (`scheduler-month-view.js`):
- Appointment blocks have status-colored backgrounds with left border
- Provider dot appears before time

**Week View** (`scheduler-week-view.js`):
- Time slot appointments have status colors
- Provider dot in appointment header

**Day View** (`scheduler-day-view.js`):
- Full appointment cards with status colors
- Provider dot next to time display

**Daily Appointments Section**:
- List items have status-colored backgrounds
- Provider dots identify ownership

## Visual Design

### Appointment Card Structure

```
┌─────────────────────────────────────┐
│ ████ [•] 9:00 AM    [Confirmed]     │  ← Status bg/border + provider dot + badge
│      John Doe                        │
│      Haircut Service                 │
│      with Jane Smith                 │
└─────────────────────────────────────┘
```

- **Left border** (4px): Status color (border shade)
- **Background**: Status color (lighter bg shade)
- **Provider dot** (•): Provider's assigned color (2-3px circle)
- **Status badge**: Status color (darker dot shade)
- **Text**: Status-appropriate contrast color

### Color Hierarchy

1. **Status color** dominates (background + border) - Most important information
2. **Provider dot** identifies ownership - Quick glance identification
3. **Text** maintains readability - Auto-calculated contrast

## Database Schema

### Provider Color Storage

```sql
ALTER TABLE xs_users ADD COLUMN color VARCHAR(10) NULL 
COMMENT 'Provider color for calendar display (hex code)';
```

**Migration**: `2025-10-22-191124_AddColorToUsers.php`

### Appointment Status Values

- `pending` - Awaiting confirmation
- `confirmed` - Confirmed and scheduled
- `completed` - Service completed
- `cancelled` - Cancelled by user or admin
- `no-show` - Customer did not show up

## Usage Examples

### In JavaScript

```javascript
import { getStatusColors, getProviderColor, isDarkMode } from './appointment-colors.js';

// Get colors for an appointment
const darkMode = isDarkMode();
const statusColors = getStatusColors(appointment.status, darkMode);
const providerColor = getProviderColor(provider);

// Apply to element
element.style.backgroundColor = statusColors.bg;
element.style.borderColor = statusColors.border;
element.style.color = statusColors.text;

// Add provider dot
const dot = `<span style="background-color: ${providerColor}" class="w-2 h-2 rounded-full"></span>`;
```

### In PHP Views

Provider colors are passed to views via the controller and rendered in JavaScript.

Status colors are handled entirely in JavaScript for consistency with dynamic updates.

## Accessibility

- All status colors meet WCAG AA contrast requirements (4.5:1 minimum)
- Text colors are automatically calculated for optimal readability
- Dark mode provides appropriate contrast ratios
- Status is also indicated by text labels, not color alone

## Future Enhancements

- [ ] Allow admins to customize status colors in settings
- [ ] Color picker for provider color assignment
- [ ] Color blind mode with patterns/icons
- [ ] Export color scheme to CSS custom properties
- [ ] Status color legend in UI

## Related Files

- `resources/js/modules/scheduler/appointment-colors.js` - Core color utilities
- `resources/js/modules/scheduler/scheduler-month-view.js` - Month view rendering
- `resources/js/modules/scheduler/scheduler-week-view.js` - Week view rendering
- `resources/js/modules/scheduler/scheduler-day-view.js` - Day view rendering
- `app/Database/Migrations/2025-10-22-191124_AddColorToUsers.php` - Provider color schema

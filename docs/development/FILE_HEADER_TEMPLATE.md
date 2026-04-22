# File Header Comment Template & Standards

**Reference:** [CODEBASE_INDEX.md](./CODEBASE_INDEX.md)

---

## Purpose

Standardized file headers for all retained files in the codebase. This ensures:
- ✅ New developers understand file purpose immediately
- ✅ Files are traceable to audit documentation
- ✅ Dependencies are explicit
- ✅ Status is clear (Active/Legacy/Deprecated)
- ✅ Maintenance owner is identifiable

---

## Template for PHP Files

### Controllers

```php
<?php

/**
 * UserManagement Controller
 * 
 * PURPOSE:
 * Handles CRUD operations for user accounts (providers, staff, admins).
 * Includes user creation, editing, deletion, role management, and status control.
 * 
 * ENTRY POINTS:
 * GET  /user-management              → Shows all users (admin/provider only)
 * GET  /user-management/create       → Shows user creation form
 * POST /user-management/store        → Stores new user
 * GET  /user-management/edit/{id}    → Shows user edit form
 * POST /user-management/update/{id}  → Updates user
 * POST /user-management/deactivate   → Deactivates user
 * POST /user-management/activate     → Activates user
 * POST /user-management/delete       → Deletes user (admin only)
 * 
 * FILTERS APPLIED:
 * - 'setup': Ensures application is set up
 * - 'auth': Requires user to be logged in
 * - 'role:admin,provider': Restricts to admin/provider roles
 * 
 * DEPENDENCIES:
 * - UserModel: Data access for users
 * - RoleFilter: Role-based access control
 * - Views: layouts/app.php, user_management/*, components/*
 * 
 * RELATED FILES:
 * - app/Models/UserModel.php
 * - app/Filters/RoleFilter.php
 * - app/Views/user_management/
 * - app/Config/Routes.php (Lines 52-63)
 * - docs/CODEBASE_INDEX.md (Controllers section)
 * 
 * STATUS: ✅ ACTIVE - Fully operational
 * LAST MODIFIED: [Date]
 * MODIFIED BY: [Developer name]
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Controllers → UserManagement.php
 * See docs/CODEBASE_AUDIT.md → File Inventory → Controllers section
 */

namespace App\Controllers;

use App\Models\UserModel;

class UserManagement extends BaseController
{
    // ... controller code
}
```

### Models

```php
<?php

/**
 * UserModel - Data Access Layer for Users
 * 
 * PURPOSE:
 * Provides database operations for the xs_users table.
 * Handles querying, creating, updating, and deleting user records.
 * Manages relationships with roles, permissions, and appointments.
 * 
 * DATABASE TABLE:
 * xs_users
 * - id (int, primary key)
 * - email (varchar, unique)
 * - password_hash (varchar)
 * - role (enum: admin, provider, staff)
 * - status (enum: active, inactive, suspended)
 * - created_at (timestamp)
 * - updated_at (timestamp)
 * 
 * KEY METHODS:
 * - search($term): Search users by name/email
 * - findByEmail($email): Get user by email
 * - findByRole($role): Get all users with role
 * - activate($id) / deactivate($id): Control user status
 * 
 * RELATIONSHIPS:
 * - hasMany('appointments'): User's appointments as provider
 * - hasMany('audit_logs'): User's activity audit trail
 * - belongsToMany('permissions'): User permissions
 * 
 * USED BY:
 * - UserManagement controller
 * - Auth controller
 * - Dashboard controller
 * - Multiple commands
 * 
 * STATUS: ✅ ACTIVE - Fully operational
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Models → UserModel
 * See docs/CODEBASE_AUDIT.md → File Inventory → Models section
 */

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends BaseModel
{
    // ... model code
}
```

### Commands/CLI

```php
<?php

/**
 * SendAppointmentReminders Command
 * 
 * PURPOSE:
 * Sends email/SMS reminders to customers about upcoming appointments.
 * Runs on a CRON schedule (typically daily or per-appointment basis).
 * 
 * EXECUTION:
 * php spark command:remind-appointments
 * 
 * SCHEDULE (Recommended):
 * - 8:00 AM daily for 24-hour reminders
 * - Optional: 1 hour before each appointment
 * 
 * ALGORITHM:
 * 1. Query appointments 24 hours in future
 * 2. Get customer contact preferences
 * 3. Send email via configured mailer
 * 4. Send SMS via Twilio (if configured)
 * 5. Log delivery in notification_delivery_logs
 * 6. Mark appointment as reminder_sent = 1
 * 
 * DEPENDENCIES:
 * - AppointmentModel: Fetch upcoming appointments
 * - NotificationQueueModel: Queue management
 * - Email configuration (app/Config/Email.php)
 * - Twilio API (if SMS enabled)
 * 
 * ERROR HANDLING:
 * - Failed emails logged and retried
 * - SMS failures recorded but don't block process
 * - Command exits gracefully on database errors
 * 
 * RELATED:
 * - SendAppointmentSmsReminders.php
 * - SendAppointmentWhatsAppReminders.php
 * 
 * STATUS: ✅ ACTIVE - Production-ready
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Commands → SendAppointmentReminders
 */

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class SendAppointmentReminders extends BaseCommand
{
    // ... command code
}
```

---

## Template for JavaScript Files

### Main Module

```javascript
/**
 * app.js - Main Application Module
 * 
 * PURPOSE:
 * Entry point for all client-side application logic.
 * Initializes components, sets up event listeners, and manages page-level functionality.
 * 
 * RESPONSIBILITIES:
 * - Initialize global search functionality
 * - Set up SPA navigation
 * - Initialize charts and widgets
 * - Manage sidebar state
 * - Handle dark mode toggle
 * - Setup session handlers
 * 
 * KEY FUNCTIONS:
 * - initGlobalSearch(): Global header search (line 349)
 *   └─ Endpoint: GET /dashboard/search?q=<query>
 *   └─ Returns: {customers: [], appointments: [], success: true}
 * - initializeComponents(): Component initialization (line 550+)
 * - initScheduler(): Calendar scheduler (if applicable)
 * - initCharts(): Dashboard charts
 * 
 * IMPORTS/DEPENDENCIES:
 * - spa.js: SPA routing
 * - charts.js: Chart components
 * - dark-mode.js: Theme management
 * - Material Design Icons
 * 
 * ENTRY POINT:
 * Called from: app/Views/layouts/app.php (via <script> tag)
 * Timing: After DOM ready
 * 
 * EVENT LISTENERS:
 * - Window: load, resize, storage
 * - Document: click (for dropdowns, modals)
 * - Input: change (for forms)
 * 
 * SHARED STATE:
 * - window.xsRegisterViewInit: Custom view initialization registry
 * - window.getBaseUrl(): Get application base URL
 * - sessionStorage: Tab-specific state
 * - localStorage: Persistent user preferences
 * 
 * SIZE: 975 lines
 * RECOMMENDATION: ⚠️ Should be modularized (see CODEBASE_AUDIT.md)
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Assets → JavaScript Modules
 * See docs/CODEBASE_AUDIT.md → Cleanup Plan → Phase 3
 */

// Initialize global search
function initGlobalSearch() {
    // ... implementation
}

// More functions...

// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeComponents();
    initGlobalSearch();
});
```

### Utility Module

```javascript
/**
 * time-format-handler.js - Time Formatting Utilities
 * 
 * PURPOSE:
 * Provides consistent time and date formatting across the application.
 * Handles timezone conversions and user-preferred formats.
 * 
 * EXPORTS:
 * - formatDateTime(isoString): Format to "MMM DD, YYYY HH:MM AM/PM"
 * - formatDate(isoString): Format to "MMM DD, YYYY"
 * - formatTime(isoString): Format to "HH:MM AM/PM"
 * - parseISO(dateString): Parse ISO string to Date object
 * 
 * USAGE:
 * import { formatDateTime } from './time-format-handler.js';
 * const formatted = formatDateTime('2026-01-28T14:30:00Z');
 * // Result: "Jan 28, 2026 2:30 PM"
 * 
 * DEPENDENCIES:
 * - Intl.DateTimeFormat (native browser API)
 * - User's browser timezone
 * 
 * TIMEZONE HANDLING:
 * - Converts server timezone (UTC) to user timezone
 * - User timezone from: app/Filters/TimezoneDetection.php
 * - Stored in: sessionStorage.userTimezone
 * 
 * USED BY:
 * - app.js: Dashboard
 * - charts.js: Chart labels
 * - public-booking.js: Appointment selection
 * 
 * RELATED:
 * - app/Filters/TimezoneDetection.php
 * - app/Config/App.php (timezone settings)
 * 
 * STATUS: ✅ ACTIVE
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Assets → JavaScript Modules
 */

export function formatDateTime(isoString) {
    // ... implementation
}

// Export other functions...
```

---

## Template for View Files

### Component View

```php
<?php
/**
 * page-header Component
 * 
 * PURPOSE:
 * Renders standardized page header with title, subtitle, and action buttons.
 * Provides consistent header styling across all pages.
 * 
 * INCLUDED BY:
 * All main feature views (customers, appointments, services, etc.)
 * 
 * PARAMETERS (via $this->include()):
 * - $title (string, required): Page title
 * - $subtitle (string, optional): Descriptive subtitle
 * - $actions (array, optional): Array of HTML buttons
 *   Example: ['<a href="...">New Item</a>', '<button>Refresh</button>']
 * - $icon (string, optional): Material icon name
 * 
 * STYLING:
 * - Uses xs-page-header class (from _app-layout.scss)
 * - Responsive: Full width on mobile, fixed width on desktop
 * - Dark mode compatible
 * 
 * EXAMPLE USAGE:
 * echo view('components/page-header', [
 *     'title' => 'Customer Management',
 *     'subtitle' => 'View and manage all customer profiles',
 *     'actions' => [
 *         '<a href="' . base_url('customer-management/create') . 
 *         '" class="xs-btn xs-btn-primary">New Customer</a>'
 *     ],
 *     'icon' => 'people'
 * ]);
 * 
 * RELATED COMPONENTS:
 * - card.php: Content wrapper
 * - ui/stat-card.php: Statistics display
 * 
 * DESIGN TOKENS (from _app-layout.scss):
 * - --xs-radius-lg: Border radius
 * - --xs-space-*: Spacing variables
 * 
 * STATUS: ✅ ACTIVE - Recently standardized
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Views → Components
 * See docs/GLOBAL_LAYOUT_SYSTEM.md
 */
?>

<div class="xs-page-header">
    <div class="flex items-center gap-4">
        <?php if (!empty($icon)): ?>
            <span class="material-symbols-outlined text-3xl">
                <?= $icon ?>
            </span>
        <?php endif; ?>
        <div>
            <h1 class="text-2xl font-bold"><?= $title ?></h1>
            <?php if (!empty($subtitle)): ?>
                <p class="text-gray-600 dark:text-gray-400">
                    <?= $subtitle ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($actions)): ?>
        <div class="flex gap-2">
            <?php foreach ($actions as $action): ?>
                <?= $action ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
```

### Feature View

```php
<?php
/**
 * customer_management/index.php - Customer List View
 * 
 * PURPOSE:
 * Displays searchable, paginated list of all customers.
 * Allows filtering, editing, viewing history, and bulk operations.
 * 
 * ENTRY POINT:
 * GET /customer-management (from CustomerManagement::index)
 * 
 * DATA PROVIDED (from controller):
 * - $customers (array): List of customer records
 * - $q (string, optional): Current search query
 * - $page (int, optional): Current page number
 * - $total (int): Total customer count
 * - $perPage (int): Records per page
 * 
 * FEATURES:
 * - Search by name or email (live, 300ms debounce)
 * - Sortable columns
 * - Pagination
 * - Edit/delete/view-history actions
 * - Responsive table layout
 * - Dark mode support
 * 
 * COMPONENTS USED:
 * - page-header: Title and "New Customer" button
 * - card: Container wrapper
 * - pagination: Page navigation
 * - Material icons: Edit, delete, history icons
 * 
 * SEARCH BEHAVIOR:
 * - GET /customer-management/search?q=<query> (AJAX)
 * - Returns JSON with matching customers
 * - Updates table dynamically
 * - No page reload
 * 
 * SCRIPTS INCLUDED:
 * - initCustomerSearch() (local): Handles search input
 * - chart.js (global): Optional analytics
 * 
 * STYLING:
 * - Table styling: xs-table-* classes
 * - Button styling: xs-btn-* classes
 * - Responsive: xs-responsive-table
 * 
 * LAYOUT:
 * - extends: layouts/app.php
 * - sidebar: components/unified-sidebar.php
 * - Uses: xs-page-header, xs-page-body canonical structure
 * 
 * STATUS: ✅ ACTIVE - Recently refactored
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Views → Feature Views
 * See docs/GLOBAL_LAYOUT_SYSTEM.md → Canonical Structure
 * See app/Controllers/CustomerManagement.php
 */
?>

<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
    <!-- Page header with title and actions -->
    <?php echo view('components/page-header', [
        'title' => 'Customer Management',
        'subtitle' => 'View and manage all customer profiles',
        'actions' => ['<a href="..." class="xs-btn xs-btn-primary">New Customer</a>']
    ]); ?>
    
    <!-- Search and filters card -->
    <!-- Results table card -->
    <!-- Pagination -->
    
    <script>
    // Search initialization
    function initCustomerSearch() {
        // ... local search logic
    }
    </script>
<?= $this->endSection() ?>
```

---

## Template for SCSS Files

### Component Stylesheet

```scss
/**
 * _buttons.scss - Button Component Styles
 * 
 * PURPOSE:
 * Defines all button variants used throughout the application.
 * Ensures consistent button styling, sizing, and interactions.
 * 
 * CLASSES PROVIDED:
 * - .xs-btn: Base button class
 * - .xs-btn-primary: Primary action button
 * - .xs-btn-secondary: Secondary action button
 * - .xs-btn-danger: Destructive action button
 * - .xs-btn-sm: Small button variant
 * - .xs-btn-icon: Icon-only button
 * - .xs-btn-loading: Loading state (spinner)
 * 
 * USAGE:
 * <button class="xs-btn xs-btn-primary">Click Me</button>
 * <a href="..." class="xs-btn xs-btn-secondary xs-btn-sm">Edit</a>
 * 
 * DESIGN TOKENS (from _app-layout.scss):
 * - --xs-primary-color
 * - --xs-secondary-color
 * - --xs-radius-lg
 * 
 * STATES:
 * - :hover - Slightly darker/different shade
 * - :active - Pressed appearance
 * - :disabled - Grayed out, no pointer
 * - :focus - Outline for accessibility
 * 
 * RESPONSIVE:
 * - Mobile: Larger tap target (44px min)
 * - Desktop: Standard size
 * 
 * DARK MODE:
 * - All variants have dark mode variants
 * - Uses CSS custom properties for color switching
 * 
 * DEPRECATED:
 * - .old-btn: Use .xs-btn instead (remove in v2.0)
 * 
 * USED BY:
 * - All views and components
 * - Public booking interface
 * - Forms and dialogs
 * 
 * STATUS: ✅ ACTIVE - Core component
 * 
 * AUDIT REFERENCE:
 * See docs/CODEBASE_INDEX.md → Frontend Assets → SCSS
 */

// Base button styles
.xs-btn {
    // ... base styles
}

// Variants
.xs-btn-primary {
    // ... primary variant
}

// More styles...
```

---

## Implementation Checklist

When adding file headers to existing files:

- [ ] File type correctly identified (Controller, Model, View, etc.)
- [ ] Purpose statement is clear and concise
- [ ] Entry points listed (routes, imports, CLI commands)
- [ ] Dependencies explicitly documented
- [ ] Related files referenced
- [ ] Status is clear (Active, Legacy, Deprecated, Experimental)
- [ ] Last modified date noted
- [ ] Link to audit documentation included
- [ ] Examples provided where helpful
- [ ] No redundant information (DRY principle)

---

## File Header Locations in Repository

**Template:**
- This file: `docs/FILE_HEADER_TEMPLATE.md`

**Reference:**
- Main Audit: `docs/CODEBASE_AUDIT.md`
- Index: `docs/CODEBASE_INDEX.md`
- Config Docs: `docs/CODEBASE_AUDIT_CONFIG.md`

**Enforcing Standards:**
1. Add headers to all new files
2. Update headers when modifying files
3. Review headers in code review
4. Link to CODEBASE_INDEX.md in all headers

---

**Status:** ✅ Template Ready for Implementation

**Next Step:** Apply headers to critical files (top 20) as part of cleanup phase 1.


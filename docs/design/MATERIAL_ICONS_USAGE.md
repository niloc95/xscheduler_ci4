# Material Icons Usage Guide

## Overview
WebSchedulr now includes Google Material Symbols with comprehensive utility classes for easy icon usage throughout the application.

## CDN Integration
Material Symbols fonts are loaded via CDN in the main layout:
- **Outlined**: Primary icon style
- **Rounded**: Alternative rounded style 

```html
<!-- Already included in app/Views/components/layout.php -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
```

## Basic Usage

### Method 1: Using Icon Classes (Recommended)
```html
<!-- Basic icons -->
<span class="icon schedule"></span>
<span class="icon person"></span> 
<span class="icon settings"></span>

<!-- With size variants -->
<span class="icon schedule xs"></span>   <!-- 16px -->
<span class="icon schedule sm"></span>   <!-- 20px -->
<span class="icon schedule md"></span>   <!-- 24px (default) -->
<span class="icon schedule lg"></span>   <!-- 32px -->
<span class="icon schedule xl"></span>   <!-- 48px -->

<!-- With weight variants -->
<span class="icon schedule thin"></span>
<span class="icon schedule light"></span>
<span class="icon schedule regular"></span>
<span class="icon schedule medium"></span>
<span class="icon schedule bold"></span>

<!-- With color utilities -->
<span class="icon schedule text-primary"></span>
<span class="icon schedule text-accent"></span>
<span class="icon schedule text-success"></span>
<span class="icon schedule text-warning"></span>
<span class="icon schedule text-error"></span>
```

### Method 2: Direct Material Icon Class
```html
<!-- Manual icon names -->
<span class="material-icon">schedule</span>
<span class="material-icon">event</span>
<span class="material-icon">person</span>

<!-- With modifiers -->
<span class="material-icon filled lg">star</span>
<span class="material-icon rounded text-accent">favorite</span>
```

### Method 3: Raw Text (For custom icons)
```html
<span class="material-icon">custom_icon_name</span>
```

## Available Pre-defined Icons

### Scheduling & Calendar
- `.icon.schedule` - Clock/time icon
- `.icon.event` - Event/calendar event
- `.icon.calendar-today` - Today's calendar
- `.icon.calendar-month` - Monthly calendar view
- `.icon.access-time` - Time access icon
- `.icon.appointment` - Event note icon
- `.icon.service` - Room service icon

### People & Business
- `.icon.person` - Single person
- `.icon.people` - Multiple people
- `.icon.client` - Client/customer (outlined person)
- `.icon.provider` - Service provider (badge)
- `.icon.business` - Business/company

### Navigation & Actions
- `.icon.dashboard` - Dashboard home
- `.icon.home` - Home icon
- `.icon.menu` - Hamburger menu
- `.icon.close` - Close/X icon
- `.icon.arrow-back` - Back arrow
- `.icon.arrow-forward` - Forward arrow
- `.icon.expand-more` - Expand down
- `.icon.expand-less` - Collapse up

### CRUD Operations
- `.icon.add` - Add/plus icon
- `.icon.edit` - Edit/pencil icon
- `.icon.delete` - Delete/trash icon
- `.icon.save` - Save/disk icon

### Status & Feedback
- `.icon.check-circle` - Success/complete
- `.icon.cancel` - Cancel/error
- `.icon.pending` - Pending/clock
- `.icon.warning` - Warning triangle
- `.icon.error` - Error icon
- `.icon.info` - Information icon

### Settings & Tools
- `.icon.settings` - Settings/gear
- `.icon.notifications` - Bell/notifications
- `.icon.search` - Search/magnify
- `.icon.filter-list` - Filter options
- `.icon.sort` - Sort/arrange
- `.icon.analytics` - Analytics/chart
- `.icon.revenue` - Payments/money

### Files & Content
- `.icon.folder` - Folder icon
- `.icon.insert-drive-file` - Generic file
- `.icon.picture-as-pdf` - PDF file
- `.icon.download` - Download arrow
- `.icon.upload` - Upload arrow
- `.icon.print` - Print icon

### Communication
- `.icon.mail` - Email/mail
- `.icon.phone` - Phone/call
- `.icon.message` - Message/chat

### UI Controls
- `.icon.visibility` - Show/eye open
- `.icon.visibility-off` - Hide/eye closed
- `.icon.light-mode` - Light theme
- `.icon.dark-mode` - Dark theme

## Size Reference
- `xs`: 16px (opsz: 20)
- `sm`: 20px (opsz: 20) 
- `md`: 24px (opsz: 24) - Default
- `lg`: 32px (opsz: 40)
- `xl`: 48px (opsz: 48)

## Weight Reference
- `thin`: 100
- `light`: 200
- `regular`: 400 - Default
- `medium`: 500
- `bold`: 700

## Style Variations
- Default: Outlined style
- `.filled`: Filled style (FILL: 1)
- `.rounded`: Rounded style (uses Material Symbols Rounded font)

## Color Integration
Icons work seamlessly with WebSchedulr's design system colors:
- `.text-primary` - Primary text color
- `.text-secondary` - Secondary text color  
- `.text-muted` - Muted text color
- `.text-accent` - Brand accent color
- `.text-success` - Success green
- `.text-warning` - Warning orange
- `.text-error` - Error red
- `.text-info` - Info blue

## Examples in Context

### Buttons with Icons
```html
<button class="btn btn-primary">
  <span class="icon add sm"></span>
  New Appointment
</button>

<button class="btn btn-secondary">
  <span class="icon edit sm"></span>
  Edit Service
</button>
```

### Navigation Menu
```html
<nav>
  <a href="/dashboard" class="nav-link">
    <span class="icon dashboard"></span>
    Dashboard
  </a>
  <a href="/appointments" class="nav-link">
    <span class="icon schedule"></span>
    Appointments  
  </a>
  <a href="/clients" class="nav-link">
    <span class="icon people"></span>
    Clients
  </a>
</nav>
```

### Status Indicators
```html
<div class="status-confirmed">
  <span class="icon check-circle text-success"></span>
  Confirmed
</div>

<div class="status-pending">
  <span class="icon pending text-warning"></span>
  Pending
</div>

<div class="status-cancelled">
  <span class="icon cancel text-error"></span>
  Cancelled
</div>
```

### Table Headers
```html
<th>
  Service <span class="icon sort xs text-muted"></span>
</th>
<th>
  Date <span class="icon calendar-today xs text-muted"></span>
</th>
```

## Custom Icons
To use icons not included in the predefined list:
1. Find the icon name from [Google Material Symbols](https://fonts.google.com/icons)
2. Use the direct approach:
```html
<span class="material-icon">icon_name_here</span>
```

## Performance Notes
- Icons are loaded via CDN with optimal font-display settings
- CSS utilities are compiled and optimized by Vite
- Variable fonts provide efficient loading and rendering
- All icon classes are purged by Tailwind if unused

## Browser Support
Material Symbols are supported in:
- Chrome 88+
- Firefox 89+
- Safari 14+
- Edge 88+

Fallback: Basic text characters will display if fonts fail to load.

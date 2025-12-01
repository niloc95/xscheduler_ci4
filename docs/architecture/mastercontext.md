/* ----------------------------------------------------------------------------
 * @webSchedulr - Online Appointment Scheduler
 *
 * @package     @webSchedulr - Online Appointments
 * @author      N N.Cara <nilo.cara@frontend.co.za>
 * @copyright   Copyright (c) Nilo Cara
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://webschedulr.co.za
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

# WebSchedulr CI4 - Master Context Document

## Project Overview

**WebSchedulr** is a modern scheduling application built with CodeIgniter 4, featuring a Material Design dashboard with Tailwind CSS, designed for deployment to standard hosting providers with zero configuration requirements.

### Core Architecture
- **Backend**: CodeIgniter 4 (PHP framework)
- **Frontend**: Tailwind CSS 3.4.17 with Material Design 3.0 components
- **UI Framework**: Material Web Components (@material/web), CoreUI 5.4.0
- **Charts**: Chart.js 4.5.0 for analytics and data visualization
- **Build System**: Vite 6.3.5 with multi-entry point compilation
- **Asset Management**: SCSS with PostCSS processing, Material Design tokens
- **Deployment**: Production-ready standalone package for shared hosting

## Project Structure

```
WebSchedulr_ci4/
├── app/                          # CodeIgniter 4 application
│   ├── Controllers/              # Request handlers
│   │   ├── BaseController.php    # Base controller with UI helper loading
│   │   ├── Api/                  # API controllers
│   │   │   └── Availability.php  # Availability/scheduling API endpoints
│   │   ├── PublicSite/           # Public-facing controllers
│   │   │   └── BookingController.php # Public booking page
│   │   ├── Dashboard.php         # Material Design dashboard controller
│   │   ├── Appointments.php      # Appointment CRUD controller
│   │   ├── Home.php             # Default welcome controller
│   │   ├── Setup.php            # Setup wizard controller
│   │   └── Styleguide.php       # Design system documentation
│   ├── Services/                # Business logic services
│   │   └── AvailabilityService.php # Core scheduling/availability engine
│   ├── Config/                  # Application configuration
│   │   ├── App.php              # Main app config (baseURL, indexPage)
│   │   └── Routes.php           # URL routing definitions
│   ├── Helpers/                 # Custom helper functions
│   │   ├── ui_helper.php        # UI component helper functions
│   │   └── vite_helper.php      # Vite asset management helpers
│   └── Views/                   # Template files
│       ├── appointments/        # Appointment management views
│       │   └── form.php         # Create/edit appointment form
│       ├── components/          # Reusable view components
│       │   ├── layout.php       # Main layout template
│       │   ├── header.php       # Header component
│       │   └── footer.php       # Footer component
│       ├── public_booking/      # Public booking views
│       │   └── index.php        # Public booking SPA container
│       ├── dashboard.php        # Production Material Design dashboard
│       ├── setup.php            # Setup wizard view
│       └── styleguide/          # Design system documentation
├── resources/                   # Frontend assets
│   ├── js/
│   │   ├── app.js              # Main JavaScript entry point
│   │   ├── public-booking.js   # Public booking SPA
│   │   ├── modules/
│   │   │   ├── appointments/
│   │   │   │   └── time-slots-ui.js  # Admin time slot picker
│   │   │   └── calendar/
│   │   │       └── calendar-utils.js # Shared calendar utilities
│   │   ├── material-web.js     # Material Web Components setup
│   │   └── charts.js           # Chart.js configurations and utilities
│   └── scss/
│       ├── app.scss            # Main SCSS file with Material Design tokens
│       └── components.scss     # Custom component definitions
├── public/                     # Web-accessible files
│   ├── build/assets/           # Compiled assets (Vite output)
│   │   ├── style.css          # Compiled Tailwind + Material styles
│   │   ├── main.js            # App logic + Chart.js bundle
│   │   ├── public-booking.js  # Public booking SPA bundle
│   │   └── materialWeb.js     # Material Web Components bundle
│   ├── index.php              # Application entry point
│   └── .htaccess              # Apache rewrite rules with security headers
├── docs/                      # Documentation
│   ├── architecture/          # Architecture documentation
│   ├── design/                # Design documentation
│   │   └── PRELOADED_AVAILABILITY_SYSTEM.md # Availability system docs
│   └── SCHEDULING_SYSTEM.md   # Core scheduling documentation
├── scripts/                   # Build and deployment scripts
│   └── package.js             # Production deployment packaging script
├── system/                    # CodeIgniter 4 framework (copied to deployment)
├── vendor/                    # Composer dependencies
├── writable/                  # Cache, logs, uploads
├── webschedulr-deploy/         # Production deployment package
├── vite.config.js            # Vite build configuration (multi-entry)
├── tailwind.config.js        # Tailwind CSS with Material Design tokens
└── package.json              # Node.js dependencies and build scripts
```

## Design System Implementation

### Material Design Architecture
The application implements Google's Material Design 3.0 specification using multiple frameworks:

#### Material Web Components Integration
- **@material/web**: Official Google Material Design web components
- **Material Icons**: Google's icon system with 2000+ icons
- **Material Design Tokens**: CSS custom properties for theming
- **Responsive Components**: Mobile-first Material UI patterns

#### Framework Rollup Strategy
```scss
// Layer-based CSS architecture
@import '@coreui/coreui/scss/coreui.scss';      // Base UI framework
@import 'tailwindcss/base';                     // Tailwind reset
@import 'tailwindcss/components';               // Tailwind components
@import 'tailwindcss/utilities';                // Tailwind utilities

@layer components {
  .btn-primary { /* Material Design button with Tailwind utilities */ }
  .card-material { /* Material Design elevated cards */ }
  .dashboard-grid { /* Responsive dashboard layout */ }
}
```

#### Multi-Framework CSS Approach
```scss
/* Material Design 3.0 CSS Variables */
:root {
  --md-sys-color-primary: rgb(59, 130, 246);
  --md-sys-color-on-primary: rgb(255, 255, 255);
  --md-sys-color-surface: rgb(255, 255, 255);
  --md-sys-color-outline: rgb(229, 231, 235);
}

/* Custom Material shadows */
.material-shadow {
  box-shadow: 0px 2px 4px -1px rgba(0, 0, 0, 0.2),
              0px 4px 5px 0px rgba(0, 0, 0, 0.14),
              0px 1px 10px 0px rgba(0, 0, 0, 0.12);
}
```

#### Chart.js Integration
```javascript
// Chart configurations with Material Design styling
export const materialChartDefaults = {
  responsive: true,
  plugins: {
    legend: {
      labels: { 
        font: { family: 'Roboto', size: 14 },
        color: 'var(--md-sys-color-on-surface)'
      }
    }
  },
  scales: {
    y: { 
      grid: { color: 'var(--md-sys-color-outline)' },
      ticks: { color: 'var(--md-sys-color-on-surface)' }
    }
  }
};
```

#### SCSS Component Layer (`resources/scss/components.scss`)
```scss
@layer components {
  .btn-primary { /* Tailwind utilities compiled to component */ }
  .card { /* Card component styles */ }
  .form-input { /* Standardized form inputs */ }
  .alert { /* Alert component variations */ }
}
```

#### PHP Helper Functions (`app/Helpers/ui_helper.php`)
- `ui_button($text, $href, $type, $attributes)` - Material Design button generation
- `ui_card($title, $content, $footer)` - Material elevated card components
- `ui_alert($message, $type, $title)` - Material Design alert variants
- `ui_dashboard_card($title, $value, $trend, $icon)` - Dashboard statistics cards

#### Vite Asset Management (`app/Helpers/vite_helper.php`)
- `vite_asset($path)` - Production/development asset URL resolution
- `vite_js($entry)` - JavaScript bundle loading with HMR support
- `vite_css($entry)` - CSS bundle loading with hot reload

#### Dashboard Component System
```php
// Material Design dashboard cards
echo ui_dashboard_card(
    'Total Users', 
    '2,345', 
    '+12%', 
    'people',
    'gradient-blue'
);

// Charts integration
echo ui_chart_container('user-growth-chart', 'line');
```

#### View Components (`app/Views/components/`)
- **layout.php**: Master page template with Material Design shell
- **header.php**: Material top app bar with navigation drawer trigger
- **footer.php**: Material Design footer with proper typography

#### Dashboard Views (`app/Views/`)
- **dashboard.php**: Production Material Design 3.0 dashboard
- **dashboard_simple.php**: Lightweight Material dashboard variant
- **dashboard_example.php**: Development examples and components showcase
- **material_web_example.php**: Material Web Components demonstration

### Styling Guide & Standards

#### Material Design Implementation
```html
<!-- Material Web Components Usage -->
<md-filled-button onclick="handleAction()">
  <md-icon slot="icon">add</md-icon>
  Create New
</md-filled-button>

<!-- Material Design Cards -->
<div class="bg-white rounded-lg material-shadow p-6">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold text-gray-800">Card Title</h3>
    <md-icon-button>
      <md-icon>more_vert</md-icon>
    </md-icon-button>
  </div>
</div>
```

#### Typography System (Material Design + Tailwind)
```html
<!-- Display styles -->
h1.text-4xl.font-bold.text-gray-900        # Display Large
h2.text-3xl.font-bold.text-gray-900        # Display Medium
h3.text-2xl.font-bold.text-gray-900        # Display Small

<!-- Headline styles -->
h4.text-xl.font-semibold.text-gray-800     # Headline Large
h5.text-lg.font-medium.text-gray-800       # Headline Medium
h6.text-base.font-medium.text-gray-800     # Headline Small

<!-- Body styles -->
.text-base.text-gray-700                   # Body Large
.text-sm.text-gray-600                     # Body Medium
.text-xs.text-gray-500                     # Body Small
```

#### Color System (Material Design Tokens)
```scss
// Primary colors
.bg-primary          // --md-sys-color-primary (Blue 500)
.text-on-primary     // --md-sys-color-on-primary (White)

// Surface colors  
.bg-surface          // --md-sys-color-surface (White)
.text-on-surface     // --md-sys-color-on-surface (Gray 900)

// Gradient variants
.gradient-blue       // Primary gradient
.gradient-green      // Success gradient
.gradient-orange     // Warning gradient
.gradient-purple     // Info gradient
```

### Standardization Patterns

#### Layout Patterns
```html
.page-container     # Container with responsive padding
.content-wrapper    # Centering wrapper
.content-main       # Main content area with max-width
```

#### Component States
```html
.time-slot-available    # Available time slots
.time-slot-selected     # User-selected slots
.time-slot-booked      # Unavailable slots
.time-slot-past        # Past time slots
```

#### Typography Hierarchy
```html
h1.text-3xl.font-bold      # Page titles
h2.text-2xl.font-semibold  # Section titles
h3.text-xl.font-medium     # Subsection titles
h4.text-lg.font-medium     # Component titles
```

## Build System Configuration

### Build System Configuration

#### Vite Multi-Entry Configuration (`vite.config.js`)
```javascript
export default defineConfig({
  build: {
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'resources/js/app.js'),
        materialWeb: resolve(__dirname, 'resources/js/material-web.js'),
        charts: resolve(__dirname, 'resources/js/charts.js'),
        style: resolve(__dirname, 'resources/scss/app.scss')
      },
      output: {
        entryFileNames: 'assets/[name].js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: 'assets/[name].[ext]'
      }
    }
  }
});
```

#### Framework Integration Points
- **Input Entries**: 
  - `main.js` - Core application logic + Chart.js
  - `materialWeb.js` - Material Web Components bundle
  - `style.css` - Tailwind + Material + CoreUI styles
- **Output Assets**: `public/build/assets/` with static naming
- **HMR Support**: Development hot module replacement
- **Production Optimization**: Minification, tree-shaking, asset optimization

#### Chart.js Build Integration
```javascript
// resources/js/charts.js
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

// Material Design chart defaults
Chart.defaults.font.family = 'Roboto, sans-serif';
Chart.defaults.color = 'var(--md-sys-color-on-surface)';
```

### Tailwind Configuration (`tailwind.config.js`)
- **Content Sources**: `./app/Views/**/*.php`, `./resources/**/*.{js,ts,jsx,tsx,vue}`
- **Plugins**: `@tailwindcss/forms`, `@tailwindcss/typography`
- **Custom Colors**: Primary, secondary color schemes
- **Extended Spacing**: Additional spacing utilities

### PostCSS Processing
- **Tailwind CSS**: Utility-first CSS framework
- **Autoprefixer**: Browser compatibility
- **SCSS Processing**: Modern compiler with deprecation handling

## Deployment Strategy

### Zero-Configuration Deployment
The application is designed for deployment to any standard hosting provider without server configuration:

#### Packaging Process (`scripts/package.js`)
1. **Asset Compilation**: Vite builds optimized CSS/JS
2. **File Copying**: Essential CI4 files copied to deployment folder
3. **Path Updates**: Production-ready asset paths
4. **Configuration**: Environment-specific settings
5. **Documentation**: Deployment instructions included

#### Generated Package Structure
```
webschedulr-deploy/
├── app/              # Application logic
├── system/           # CI4 framework
├── public/           # Web root (point domain here)
│   ├── build/assets/ # Compiled frontend assets
│   ├── index.php     # Application entry
│   └── .htaccess     # Rewrite rules
├── writable/         # Needs 755 permissions
├── vendor/           # PHP dependencies
├── .env              # Environment configuration
└── DEPLOY-README.md  # Deployment instructions
```

#### Hosting Compatibility
- **Shared Hosting**: GoDaddy, Bluehost, HostGator
- **VPS/Cloud**: Any Apache/Nginx server
- **Subdomain/Subfolder**: Flexible deployment paths
- **Requirements**: PHP 7.4+, mod_rewrite enabled

## Technology Stack

### Backend Dependencies
```json
{
  "codeigniter4/framework": "^4.x",
  "PHP": ">=7.4"
}
```

### Frontend Dependencies & Framework Rollup
```json
{
  "dependencies": {
    "@coreui/coreui": "^5.4.0",               // Base UI framework
    "@coreui/icons": "^3.0.1",                // CoreUI icon system
    "@material/web": "^2.3.0",                // Material Web Components
    "@material-tailwind/html": "^3.0.0",      // Material Tailwind HTML
    "@material/button": "^14.0.0",            // Individual Material components
    "@material/card": "^14.0.0",
    "@material/drawer": "^14.0.0",
    "@material/icon-button": "^14.0.0",
    "@material/list": "^14.0.0",
    "@material/textfield": "^14.0.0",
    "@material/top-app-bar": "^14.0.0",
    "material-icons": "^1.13.14",             // Google Material Icons
    "chart.js": "^4.5.0",                     // Charts and data visualization
    "tailwindcss": "^3.4.17",                 // Utility-first CSS
    "@tailwindcss/forms": "^0.5.7",           // Form styling
    "@tailwindcss/typography": "^0.5.10",     // Typography utilities
    "@headlessui/tailwindcss": "^0.2.2",      // Headless UI integration
    "sass": "^1.89.2",                        // SCSS processing
    "vite": "^6.3.5"                          // Build system
  }
}
```

#### CSS Framework Architecture
```scss
// Import order for optimal cascade
@import '@coreui/coreui/scss/coreui.scss';      // 1. Base framework
@import '@material/button/styles.scss';         // 2. Material components
@import '@material/card/styles.scss';
@import 'tailwindcss/base';                     // 3. Tailwind reset
@import 'tailwindcss/components';               // 4. Tailwind components  
@import 'tailwindcss/utilities';                // 5. Tailwind utilities

// Custom layer for Material + Tailwind hybrid
@layer components {
  .dashboard-card {
    @apply bg-white rounded-lg p-6;
    box-shadow: var(--md-elevation-2);
  }
}
```

### Development Tools
```json
{
  "autoprefixer": "^10.4.21",
  "postcss": "^8.5.6"
}
```

## Development Workflow

### Local Development
```bash
# Start development server
php spark serve

# Watch and compile assets
npm run dev

# Build for production
npm run build

# Create deployment package
npm run package
```

### Routes Configuration
```php
// Public routes
$routes->get('/', 'Home::index');                    # Welcome page
$routes->get('setup', 'Setup::setup');               # Setup wizard
$routes->get('book', 'PublicSite\BookingController::index');  # Public booking

// API routes
$routes->group('api', function($routes) {
  $routes->get('availability/calendar', 'Api\Availability::calendar');
  $routes->get('availability/slots', 'Api\Availability::slots');
  $routes->post('availability/check', 'Api\Availability::check');
});

// Admin routes (requires authentication)
$routes->get('appointments', 'Appointments::index');
$routes->get('appointments/create', 'Appointments::create');
$routes->get('appointments/(:num)/edit', 'Appointments::edit/$1');
```

### Environment Configuration
```ini
# Development
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'

# Production (auto-configured during packaging)
CI_ENVIRONMENT = production
app.baseURL = ''  # Flexible for any domain
```

## Scheduling & Availability System

The core scheduling system provides real-time availability calculation with preloaded calendar data for optimal UX.

### Architecture Overview
```
┌─────────────────────────────────────────────────────────────────────┐
│                    AVAILABILITY ARCHITECTURE                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Frontend                    API Layer                 Backend       │
│  ────────                    ─────────                 ───────       │
│                                                                      │
│  ┌──────────────┐     ┌──────────────────┐     ┌─────────────────┐  │
│  │ Admin Form   │────▶│ Api\Availability │────▶│ Availability    │  │
│  │ time-slots-  │     │ ::calendar()     │     │ Service         │  │
│  │ ui.js        │     └──────────────────┘     │                 │  │
│  └──────────────┘                              │ getCalendar     │  │
│                                                │ Availability()  │  │
│  ┌──────────────┐     ┌──────────────────┐     │                 │  │
│  │ Public       │────▶│ PublicSite\      │────▶│ 5-min cache     │  │
│  │ Booking SPA  │     │ Booking::        │     │ per combo       │  │
│  │ public-      │     │ calendar()       │     └─────────────────┘  │
│  │ booking.js   │     └──────────────────┘              │           │
│  └──────────────┘                                       ▼           │
│         │                                      ┌─────────────────┐  │
│         │         Calendar Response            │ Database        │  │
│         ▼         ─────────────────            │ - appointments  │  │
│  ┌──────────────┐  {availableDates,            │ - schedules     │  │
│  │ Date Pills   │   slotsByDate,               │ - blocked_times │  │
│  │ Auto-select  │   defaultDate}               │ - services      │  │
│  │ Slot Grid    │                              └─────────────────┘  │
│  └──────────────┘                                                   │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### Key Components

#### Backend Service (`app/Services/AvailabilityService.php`)
- `getCalendarAvailability()` - Pre-compute 60-day availability window
- `getAvailableSlots()` - Get slots for a specific date
- `isSlotAvailable()` - Validate slot availability
- 5-minute server-side caching per provider/service/date combo

#### API Endpoints
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/availability/calendar` | GET | 60-day preloaded availability |
| `/api/availability/slots` | GET | Single-day slot list |
| `/api/availability/check` | POST | Validate specific slot |
| `/public/booking/calendar` | GET | Public calendar (no auth) |

#### Frontend Modules
| Module | Location | Purpose |
|--------|----------|---------|
| `calendar-utils.js` | `resources/js/modules/calendar/` | Shared normalization utilities |
| `time-slots-ui.js` | `resources/js/modules/appointments/` | Admin form time slot picker |
| `public-booking.js` | `resources/js/` | Public booking SPA |

### Preloaded Availability UX
When user selects provider + service:
1. **Immediate feedback**: Date pills show first 5 available dates
2. **Auto-selection**: First available date auto-selected if none specified
3. **Slot grid**: Time slots populate instantly from cached calendar
4. **No empty states**: Warning shown if no availability in 60 days

### Caching Strategy
- **Server (PHP)**: 5-minute TTL per provider/service/timezone
- **Client (JS)**: 1-minute TTL per browser session
- **Cache invalidation**: Manual clear or TTL expiry

For detailed documentation, see:
- `docs/SCHEDULING_SYSTEM.md` - Core scheduling logic
- `docs/design/PRELOADED_AVAILABILITY_SYSTEM.md` - Frontend integration

## Application Entry Points

### Setup View - Initial Configuration Entry Point

The Setup View serves as the primary entry point for new installations, providing a comprehensive wizard for initial system configuration.

#### Features
- **System Administrator Registration**: Full name, User ID, password with strength validation
- **Database Configuration**: Choice between MySQL connection and zero-config SQLite
- **Real-time Validation**: Form validation with immediate feedback
- **Connection Testing**: MySQL connection verification before setup
- **Progress Tracking**: Visual progress indicator during setup process
- **Security**: CSRF protection, rate limiting, one-time setup enforcement

#### Implementation Details

**Controller**: `app/Controllers/Setup.php`
- `index()` - Display setup form (redirect if already completed)
- `process()` - Handle setup form submission with validation
- `testConnection()` - Test MySQL database connection

**View**: `app/Views/setup.php`
- Material Design 3.0 interface with responsive layout
- Progressive form with database type selection
- Password strength indicator and validation
- Loading overlay with progress tracking

**JavaScript**: `resources/js/setup.js`
- `SetupWizard` class for form management
- Real-time validation and password strength checking
- AJAX connection testing and form submission
- Error handling and user feedback

**Database Helper**: `app/Helpers/DatabaseSetup.php`
- Automated table creation for both MySQL and SQLite
- Admin user creation with secure password hashing
- Database configuration file generation

#### Post-Setup Behavior
1. **Setup Completion**: Creates `writable/setup_completed.flag`
2. **Route Protection**: Setup route redirects to dashboard when completed
3. **SPA Transition**: Redirects to Material Design dashboard (SPA mode)
4. **Database Ready**: All necessary tables and admin user created

### Dashboard SPA - Main Application Interface

After successful setup completion, users are redirected to the dashboard which operates as a Single Page Application (SPA).

#### SPA Features
- **Material Design 3.0**: Complete Material Web Components integration
- **Chart.js Analytics**: Real-time data visualization
- **Responsive Design**: Mobile-first approach with collapsible navigation
- **Hot Module Replacement**: Development-time asset reloading
- **Production Optimized**: Minified assets with static naming for deployment

#### Entry Point Flow
```
Setup View (First Time) → Database Configuration → Dashboard SPA
Dashboard (Returning) → Authentication Check → Dashboard SPA
```

## Current Implementation Status

### ✅ Completed Features
- [x] CodeIgniter 4 foundation with proper structure
- [x] Vite build system with SCSS/JS compilation
- [x] Tailwind CSS integration with CoreUI components
- [x] Custom component library with @layer components
- [x] PHP UI helper functions for consistent components
- [x] Reusable view component system
- [x] Comprehensive design system documentation
- [x] Zero-configuration deployment packaging
- [x] Responsive design patterns
- [x] Accessibility considerations
- [x] Authentication flow with integrated profile management
- [x] Automated database backup tooling and runbook

### 🔄 In Progress
- [ ] Scheduler-specific components implementation
- [ ] Time slot management system
- [ ] Calendar view components
- [ ] Appointment booking functionality

### 📋 Planned Features
- [ ] Database schema for scheduling
- [ ] Email notifications
- [ ] Calendar integrations
- [ ] Multi-timezone support
- [ ] Mobile app considerations

## Key Design Decisions

### 1. Hybrid CSS Approach
**Decision**: Use Tailwind utilities with custom @layer components
**Rationale**: Combines Tailwind's utility benefits with component consistency
**Impact**: Maintainable, scalable, and designer-friendly

### 2. PHP Helper Functions
**Decision**: Create UI helper functions instead of complex view partials
**Rationale**: Simpler API, consistent output, easier to maintain
**Impact**: Reduced code duplication, consistent component rendering

### 3. Zero-Config Deployment
**Decision**: Package application for any hosting provider
**Rationale**: Broadest compatibility, no server requirements
**Impact**: Easy deployment, suitable for clients with basic hosting

### 4. Static Asset Paths
**Decision**: Use consistent asset naming instead of hashed names
**Rationale**: Simplifies deployment, reduces complexity
**Impact**: Easier debugging, predictable file structure

## File Naming Conventions

### Controllers
- PascalCase class names: `Setup.php`, `Styleguide.php`
- camelCase method names: `setup()`, `components()`

### Views
- lowercase with hyphens: `setup.php`, `components.php`
- Directory organization: `components/`, `styleguide/`

### CSS Classes
- Component classes: `.btn-primary`, `.card-header`
- State classes: `.time-slot-available`, `.appointment-upcoming`
- Layout classes: `.page-container`, `.content-wrapper`

### JavaScript
- camelCase functions: `startSetup()`, `initializeComponents()`
- Event handlers: `DOMContentLoaded` pattern

## Performance Considerations

### CSS Optimization
- Tailwind purging removes unused styles
- Component layer compiled once
- PostCSS optimizes output

### JavaScript Optimization
- Vite bundles and minifies
- CoreUI components loaded on-demand
- Modern ES modules support

### Deployment Optimization
- Static asset optimization
- Gzip compression via .htaccess
- Browser caching headers

## Security Considerations

### CodeIgniter 4 Security
- CSRF protection enabled
- XSS filtering built-in
- Input validation framework

### Deployment Security
- .htaccess security headers
- Directory browsing disabled
- Sensitive files outside web root

### Frontend Security
- Content Security Policy ready
- XSS protection in helpers
- ARIA attributes for accessibility

## Data Protection & Backups

- **Backup Utility**: `scripts/db_backup.php` generates timestamped gzipped MySQL dumps using environment-sourced credentials with safety checks for target directories.
- **Documentation**: `docs/DB_BACKUP_PLAN.md` outlines full backup workflow, weekly incremental strategy, and retention guidance for shared hosting environments.
- **Runtime Integration**: Script supports CLI execution (`php scripts/db_backup.php --connection=default`) and cron scheduling; logs output path for audit trails.
- **Storage Strategy**: Dumps persisted under `builds/backups/` with date-based naming; ensure directory has write access (`755`) prior to scheduling.
- **Validation**: Initial dry runs verified mysql client availability, credential parsing, and generated archives stored at `builds/backups/YYYYMMDD_full.sql.gz`.

## Maintenance Guidelines

### Code Standards
- Follow CodeIgniter 4 coding standards
- Use PSR-4 autoloading
- Maintain consistent formatting

### Documentation
- Update this mastercontext.md for major changes
- Document new components in style guide
- Comment complex functionality

### Version Control
- Feature branch workflow
- Conventional commit messages
- Tag releases for deployment

## Troubleshooting Guide

### Common Build Issues
1. **Module not found errors**: Run `npm install`
2. **Tailwind classes not working**: Check `tailwind.config.js` content paths
3. **SCSS compilation errors**: Verify `@import` paths in `app.scss`

### Deployment Issues
1. **404 errors**: Check .htaccess and mod_rewrite
2. **Asset not loading**: Verify baseURL in App.php
3. **Permissions errors**: Set writable/ to 755

### Development Issues
1. **Routes not working**: Check Routes.php syntax
2. **Helper functions undefined**: Verify helper loading in BaseController
3. **Views not rendering**: Check extend/section syntax

---

**Last Updated**: October 2025
**Version**: 1.3.0
**Maintainer**: Development Team

## Recent Development Updates

### January 2025 - Complete Setup View Implementation

**Commit**: `bac09ac` - `feat: Complete WebSchedulr setup view with icon fixes, production URL handling, and deployment packaging`

This major update completes the WebSchedulr setup view implementation with several critical fixes and enhancements:

#### 🔧 Icon System Overhaul
- **Issue**: Material Design icons not displaying universally across browsers and hosting environments
- **Solution**: Replaced Material Design icon font with embedded SVG icons
- **Impact**: Universal compatibility, faster loading, no external dependencies
- **Documentation**: `ICON-DISPLAY-FIX.md`

#### 🌐 Production URL Handling
- **Issue**: Setup wizard failing on production environments due to hardcoded localhost URLs
- **Solution**: Implemented dynamic URL detection and flexible baseURL configuration
- **Changes**: 
  - Updated `setup.js` to detect current domain automatically
  - Modified `setup.php` to handle various hosting configurations
  - Added robust URL validation and fallback mechanisms
- **Impact**: Seamless deployment to any hosting provider
- **Documentation**: `PRODUCTION-URL-FIX.md`

#### 🎨 Setup View Completion
- **Achievement**: Complete implementation of the setup wizard entry point
- **Features Added**:
  - Material Design 3.0 interface with responsive layout
  - Progressive form with database type selection (MySQL/SQLite)
  - Real-time validation and password strength checking
  - AJAX connection testing and form submission
  - Loading overlay with progress tracking
  - Security features: CSRF protection, rate limiting, one-time setup enforcement
- **New Files**:
  - `app/Views/components/setup-layout.php` - Dedicated layout for setup wizard
  - `test_setup.php` - Testing script for setup functionality
- **Documentation**: `SETUP_COMPLETION_REPORT.md`

#### 📦 Deployment Packaging System
- **Feature**: Automated deployment package generation
- **Implementation**: 
  - Enhanced `scripts/package.js` for production-ready packaging
  - Automated asset compilation and optimization
  - Deployment-specific configuration handling
  - Complete webschedulr-deploy/ package with documentation
- **Benefits**:
  - Zero-configuration deployment to any hosting provider
  - Optimized assets and streamlined file structure
  - Comprehensive deployment documentation
- **Documentation**: `ZIP-DEPLOYMENT-SUMMARY.md`

#### 📁 File Structure Improvements
- **Added**: New documentation files for tracking specific fixes and features
- **Enhanced**: Deployment package structure with updated configurations
- **Removed**: Obsolete `scripts/package_not_working.js`
- **Updated**: All related view components and styling

#### 🔄 Asset Pipeline Updates
- **Rebuilt**: All frontend assets with latest configurations
- **Optimized**: CSS and JavaScript bundles for production
- **Updated**: Package dependencies and build scripts

### Development Impact

This update represents a significant milestone in the WebSchedulr project:

1. **Production Readiness**: The application is now fully deployable to any hosting provider
2. **Universal Compatibility**: Icon and URL handling works across all environments
3. **Complete Setup Flow**: Users can now properly configure the application on first run
4. **Deployment Automation**: Packaging system enables one-click deployment preparation
5. **Documentation**: Comprehensive tracking of all changes and fixes

### Next Steps

With the setup view complete and deployment system in place, the project is ready for:
- Database schema implementation
- User authentication system
- Core scheduling functionality
- Production deployment testing

### July 2025 - Environment Configuration & Deployment Workflow Completion

**Phase**: Environment Setup and Production Deployment Enhancement

This comprehensive update establishes a robust, user-friendly environment configuration and deployment workflow for WebSchedulr, ensuring production-ready deployment packages and seamless setup processes.

#### 🔧 Environment Configuration Overhaul
- **Approach**: Setup-driven environment configuration (no env-switch script)
- **Implementation**: 
  - Enhanced Setup controller to generate `.env` from `.env.example`
  - Database connection testing and encryption key generation
  - Comprehensive `.env.example` template for production
  - Clean separation between development and production configurations
- **Benefits**:
  - User-friendly setup wizard handles all environment configuration
  - No manual `.env` file editing required
  - Automatic validation and error handling
- **Documentation**: `docs/configuration/SETUP-DRIVEN-ENV-CONFIG.md`

#### 📦 Deployment Package System Enhancement
- **Issue**: Previous packaging system had incomplete file inclusion and path issues
- **Solution**: Complete refactoring of build and deployment scripts
- **Key Improvements**:
  - Fixed `scripts/build-config.js` to avoid modifying local development environment
  - Enhanced `scripts/package.js` with proper file copying and system directory handling
  - Corrected ZIP archiver to include all required files and directories
  - Added comprehensive validation and progress logging
- **Results**: Deployment package includes all necessary files: `vendor/`, `system/`, `writable/`, etc.
  - Local development environment remains unaffected during packaging
  - Consistent, tested deployment package generation
  - Verified file counts and directory structure match expectations

#### 🛠️ Build System Improvements
- **Problem**: Build process conflicts between development and deployment
- **Resolution**:
  - Separated development and deployment build processes
  - Fixed environment variable handling in production packages
  - Removed dev dependency removal during packaging (caused issues)
  - Streamlined npm scripts to avoid double builds and conflicts
- **Impact**:
  - Reliable, repeatable deployment package generation
  - No interference with local development workflow
  - Consistent asset compilation and optimization

#### 📋 Configuration Management
- **Enhanced `.env` Handling**:
  - Fixed invalid array syntax in development `.env`
  - Corrected production path configuration
  - Resolved "WRITEPATH is not set correctly" deployment errors
  - Improved `app/Config/Paths.php` for deployment compatibility
- **Setup Controller Improvements**:
  - Environment generation from template
  - Database connection validation
  - Encryption key generation
  - Error handling and user feedback

#### 🔍 Validation & Testing
- **Deployment Package Validation**:
  - Automated file count verification
  - Directory structure validation
  - ZIP extraction testing
  - Real-world deployment testing scenarios
- **Quality Assurance**:
  - Verified ZIP contains all required directories
  - Confirmed file permissions and structure
  - Tested on multiple hosting environments
  - Validated setup wizard functionality

#### 📚 Documentation Updates
- **New Documentation**:
  - `docs/configuration/SETUP-DRIVEN-ENV-CONFIG.md` - Environment setup guide
  - `docs/SETUP-WORKFLOW-COMPLETE.md` - Complete workflow documentation
  - Updated deployment instructions in package README files
- **Improved Organization**:
  - Consolidated configuration documentation
  - Clear deployment procedure documentation
  - Troubleshooting guides for common issues

#### 🚀 Deployment Workflow
- **Complete Process**:
  1. `npm run build` - Compile frontend assets
  2. `npm run package` - Generate deployment package and ZIP
  3. Upload ZIP to hosting provider
  4. Extract and point domain to `public/` folder
  5. Run setup wizard for initial configuration
- **Zero-Configuration Requirements**:
  - No server-side configuration needed
  - Works with any PHP hosting provider
  - Automatic environment detection and setup
  - Self-contained deployment package

#### 🔒 Production Security
- **Enhanced Security Measures**:
  - Production-appropriate `.env` template
  - Secure file permissions in deployment package
  - Proper directory access restrictions
  - Environment-specific security settings

### Development Impact - July 2025

This environment and deployment enhancement represents a major milestone:

1. **Production Readiness**: Robust deployment workflow suitable for any hosting provider
2. **User Experience**: Simplified setup process with comprehensive wizard
3. **Developer Experience**: Clean separation of development and deployment concerns
4. **Reliability**: Validated, tested deployment packages with complete file inclusion
5. **Maintainability**: Well-documented, reproducible deployment process

### Technical Achievements

- ✅ Complete deployment package with all required files and directories
- ✅ Setup-driven environment configuration (no manual `.env` editing)
- ✅ Validated ZIP archiver with proper file inclusion
- ✅ Fixed production path handling and WRITEPATH issues
- ✅ Enhanced build scripts with proper error handling
- ✅ Comprehensive validation and testing procedures
- ✅ Updated documentation and deployment guides

### Current Status - July 2025

**Environment Setup**: Complete and validated
**Deployment Packaging**: Complete and tested
**Documentation**: Comprehensive and up-to-date
**Next Phase**: Ready for core application feature development

The WebSchedulr application now has a robust, production-ready deployment workflow that enables seamless deployment to any hosting provider with zero configuration requirements.

### July 2025 - MySQL Test Connection Fix & Production URL Auto-Detection

**Phase**: Setup Wizard Enhancement and Production Compatibility

This critical update resolves MySQL test connection issues in both development and production environments, ensuring seamless database configuration during setup.

#### 🔧 MySQL Test Connection Resolution
- **Issues Identified**:
  - Production environments showing "undefined" error on connection test
  - Development environments failing with "Failed to parse JSON string. Error: Syntax error"
  - Mismatch between JavaScript FormData and PHP JSON expectations
- **Root Cause Analysis**:
  - JavaScript fetch() sending FormData but PHP controller expecting JSON
  - Missing comprehensive error handling for network failures
  - Insufficient validation and user feedback mechanisms
- **Technical Solution**:
  - Updated `resources/js/setup.js` to send proper JSON with correct headers
  - Enhanced `app/Controllers/Setup.php` to handle both JSON and FormData
  - Added comprehensive error handling and validation
  - Implemented CSRF token integration for security

#### 🌐 Production URL Auto-Detection Enhancement
- **Problem**: Production deployments failing with 500 errors when `app.baseURL` is empty
- **Investigation**: CodeIgniter's default auto-detection insufficient for hosting environments
- **Implementation**:
  - Enhanced `app/Config/App.php` with robust constructor-based URL detection
  - Added support for proxy headers (X-Forwarded-Proto, X-Forwarded-SSL)
  - Implemented subdirectory installation handling
  - Created fallback mechanisms for various hosting configurations
- **Results**: Zero-configuration URL detection working across all hosting providers

#### 🛠️ Technical Features

**Frontend Enhancements (`setup.js`)**:
```javascript
// Enhanced connection testing with proper JSON handling
const response = await fetch('setup/test-connection', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
    },
    body: JSON.stringify(connectionData)
});
```

**Backend Improvements (`Setup.php`)**:
```php
// Dual-format support for connection testing
$contentType = $this->request->getHeaderLine('Content-Type');
if (strpos($contentType, 'application/json') !== false) {
    $data = $this->request->getJSON(true);
} else {
    $data = $this->request->getPost(); // FormData fallback
}
```

**Production URL Detection (`App.php`)**:
```php
// Robust URL auto-detection constructor
public function __construct() {
    parent::__construct();
    if (empty($this->baseURL) && !empty($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                   (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                   (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') 
                   ? 'https://' : 'http://';
        $this->baseURL = $protocol . $_SERVER['HTTP_HOST'] . $path . '/';
    }
}
```

#### 📋 User Experience Improvements
- **Enhanced Error Messages**:
  - ✅ "MySQL connection successful. Database exists and is accessible."
  - ❌ "Missing required field: db_hostname"
  - ❌ "Database 'test_db' does not exist. Please create it first."
  - ❌ "MySQL connection failed: Access denied for user"
- **Visual Feedback**:
  - Added error display divs for all MySQL form fields
  - Improved connection test loading states
  - Better validation messaging and user guidance

#### 🔄 Deployment Package Updates
- **Enhanced Package Generation**:
  - Updated deployment package includes all MySQL fixes
  - Constructor injection during packaging for URL detection
  - Updated setup view with proper error containers
  - Comprehensive testing and validation
- **Package Specifications**:
  - ZIP size: 2.76 MB
  - File count: 1800+ files
  - All required directories: vendor/, system/, writable/, public/
  - Production-ready configuration

#### 📚 Documentation Enhancement
- **New Documentation Files**:
  - `docs/deployment/MYSQL-TEST-CONNECTION-FIX.md` - Detailed fix documentation
  - `docs/deployment/PRODUCTION-URL-AUTO-DETECTION.md` - URL detection guide
- **Technical Coverage**:
  - Root cause analysis and solution implementation
  - Cross-environment compatibility testing
  - Error message reference and troubleshooting guide
  - Deployment validation procedures

#### 🔍 Production Testing & Validation
- **Multi-Environment Testing**:
  - ✅ Development (localhost): JSON parsing issues resolved
  - ✅ Production (hosting providers): undefined errors eliminated
  - ✅ Proxy environments: X-Forwarded headers handled correctly
  - ✅ Subdirectory installations: Path detection working
- **Error Scenario Coverage**:
  - Network timeouts and connection failures
  - Invalid database credentials and missing databases
  - Required field validation and user input errors
  - CSRF token validation and security measures

### Development Impact - MySQL Fix

This MySQL test connection fix represents a critical reliability improvement:

1. **Universal Compatibility**: Setup wizard now works on any hosting environment
2. **Enhanced UX**: Clear, actionable error messages instead of technical failures
3. **Production Readiness**: Robust error handling for real-world deployment scenarios
4. **Developer Experience**: Comprehensive logging and debugging capabilities
5. **Security**: Proper CSRF integration and validation

### Technical Achievements - July 2025

- ✅ MySQL test connection working in all environments
- ✅ Production URL auto-detection with proxy support
- ✅ Enhanced error handling and user feedback
- ✅ Dual-format request handling (JSON/FormData)
- ✅ Comprehensive validation and security measures
- ✅ Updated deployment package with all fixes
- ✅ Cross-hosting provider compatibility validated

### Deployment Readiness Status

**Setup Wizard**: Fully functional with robust database testing
**URL Detection**: Automatic across all hosting environments
**Error Handling**: Comprehensive with clear user feedback
**Security**: CSRF protection and validation implemented
**Documentation**: Complete with troubleshooting guides
**Deployment Package**: Production-ready with 2.76 MB ZIP

The WebSchedulr setup process is now enterprise-ready with bulletproof database configuration and universal hosting compatibility.

### July 2025 - GitHub Actions CI/CD Implementation

**Phase**: DevOps Automation and Quality Assurance

This implementation establishes a comprehensive CI/CD pipeline with GitHub Actions, providing automated testing, security scanning, and deployment package generation for enterprise-level development workflow.

#### 🚀 CI/CD Pipeline Architecture
- **Comprehensive Automation**: Multi-job pipeline covering build, test, security, and deployment
- **Matrix Strategy**: Node.js 18 LTS and PHP 8.1 for optimal compatibility
- **Artifact Management**: Structured retention policies for builds, packages, and reports
- **Environment Testing**: Real MySQL service integration for connection testing
- **Performance Monitoring**: Bundle size analysis and build optimization

#### 🔧 Workflow Implementation

**Main CI/CD Pipeline (`.github/workflows/ci-cd.yml`)**:
```yaml
# Comprehensive pipeline with 5 specialized jobs
jobs:
  build-and-test:        # Asset compilation and validation
  setup-test:            # MySQL connection testing with real database
  create-deployment-package: # Production package generation
  code-quality:          # Security and quality checks
  performance-analysis:  # Bundle size and performance monitoring
```

**Release Automation (`.github/workflows/release.yml`)**:
```yaml
# Automated release creation for tagged versions
on:
  push:
    tags: [ 'v*.*.*' ]
  workflow_dispatch:     # Manual release capability
```

**Security Pipeline (`.github/workflows/security.yml`)**:
```yaml
# Comprehensive security scanning
jobs:
  security-scan:         # NPM vulnerability audit
  php-security-scan:     # PHP security analysis
  dependency-review:     # License compliance and dependency review
```

**Documentation Workflow (`.github/workflows/docs.yml`)**:
```yaml
# Documentation validation and maintenance
jobs:
  validate-docs:         # Markdown linting and link validation
  generate-docs-index:   # Automated documentation index generation
```

#### 🛠️ Technical Features

**Build System Integration**:
- ✅ **Asset Compilation**: Automated Vite build with validation
- ✅ **Dependency Caching**: NPM and Composer cache optimization
- ✅ **Build Validation**: Asset existence and size verification
- ✅ **Multi-Environment**: Development and production configurations

**Database Testing**:
- ✅ **MySQL Service**: Real MySQL 8.0 container for connection testing
- ✅ **Setup Validation**: Automated setup wizard endpoint testing
- ✅ **Connection Testing**: JSON API validation for database connectivity
- ✅ **Environment Simulation**: Production-like testing scenarios

**Deployment Automation**:
- ✅ **Package Generation**: Automated `npm run package` execution
- ✅ **Package Validation**: ZIP content and size verification
- ✅ **Artifact Storage**: 90-day retention for deployment packages
- ✅ **Release Assets**: Automated GitHub release creation with packages

#### 📊 Quality Assurance

**Security Scanning**:
```yaml
# Multi-layered security approach
- NPM audit for Node.js vulnerabilities
- PHP security advisory checking
- File permission validation
- Sensitive file detection
- License compliance verification
```

**Performance Monitoring**:
```yaml
# Asset and performance analysis
- Bundle size tracking with gzip analysis
- Build time optimization
- Performance regression detection
- Asset optimization validation
```

**Code Quality**:
```yaml
# Comprehensive quality checks
- Markdown linting for documentation
- Link validation for documentation accuracy
- File permission security scanning
- Dependency review and compliance
```

#### 🔄 Workflow Triggers

**Automated Triggers**:
- ✅ **Push Events**: Main and env-setup-config-build branches
- ✅ **Pull Requests**: Comprehensive validation before merge
- ✅ **Release Tags**: Automated v*.*.* tag processing
- ✅ **Scheduled Scans**: Weekly security and dependency checks

**Manual Triggers**:
- ✅ **Workflow Dispatch**: Manual pipeline execution
- ✅ **Emergency Releases**: Manual release creation capability
- ✅ **Security Patches**: On-demand security scanning

#### 📦 Artifact Management

**Build Artifacts (7-day retention)**:
- Frontend compiled assets (CSS, JS bundles)
- Performance analysis reports
- Build validation reports

**Deployment Packages (90-day retention)**:
- Production-ready ZIP files with deployment info
- Release-specific packages with version tagging
- Complete deployment directories for manual deployment

**Security Reports (30-day retention)**:
- NPM vulnerability scan results
- PHP security analysis reports
- Dependency review summaries

#### 🔍 Integration Benefits

**Developer Experience**:
- ✅ **Automated Validation**: Catch issues before merge
- ✅ **Comprehensive Testing**: Real database connection validation
- ✅ **Artifact Downloads**: Easy access to deployment packages
- ✅ **Documentation Automation**: Auto-generated indices and validation

**Production Readiness**:
- ✅ **Validated Deployments**: Every package tested before release
- ✅ **Security Compliance**: Automated vulnerability detection
- ✅ **Performance Monitoring**: Bundle size and optimization tracking
- ✅ **Release Automation**: Consistent, reliable release process

**Quality Assurance**:
- ✅ **Multi-Environment Testing**: Development and production simulation
- ✅ **Security First**: Proactive vulnerability management
- ✅ **Documentation Quality**: Automated validation and maintenance
- ✅ **Performance Tracking**: Continuous optimization monitoring

#### 🚦 Workflow Status Indicators

**Badge Integration**:
```markdown
![CI/CD Pipeline](https://github.com/niloc95/xscheduler_ci4/workflows/WebSchedulr%20CI/CD%20Pipeline/badge.svg)
![Security Scan](https://github.com/niloc95/xscheduler_ci4/workflows/Security%20&%20Dependency%20Checks/badge.svg)
![Documentation](https://github.com/niloc95/xscheduler_ci4/workflows/Documentation/badge.svg)
```

**Artifact Access**:
- **Deployment Packages**: Available in GitHub Actions artifacts
- **Security Reports**: Downloadable from workflow runs
- **Performance Analysis**: Bundle size tracking and optimization reports

#### 📚 Documentation Integration

**Automated Documentation**:
- ✅ **Index Generation**: Auto-updated documentation structure
- ✅ **Link Validation**: Broken link detection and reporting
- ✅ **Markdown Linting**: Consistent documentation formatting
- ✅ **Master Context Validation**: Required section verification

**Documentation Coverage**:
- ✅ **CI/CD Setup**: Complete workflow documentation
- ✅ **Security Procedures**: Vulnerability management guidelines
- ✅ **Deployment Process**: Automated package generation documentation
- ✅ **Troubleshooting**: Common issues and resolution guides

### Development Impact - GitHub Actions

This GitHub Actions implementation represents a major DevOps maturity milestone:

1. **Enterprise Readiness**: Professional CI/CD pipeline suitable for production environments
2. **Quality Assurance**: Automated testing and validation at every stage
3. **Security First**: Proactive vulnerability management and compliance checking
4. **Developer Productivity**: Automated workflows reduce manual overhead
5. **Release Reliability**: Consistent, tested deployment package generation

### Technical Achievements - CI/CD

- ✅ **Multi-job Pipeline**: 5 specialized jobs covering all aspects of development
- ✅ **Real Database Testing**: MySQL service integration for authentic testing
- ✅ **Security Automation**: NPM audit, PHP scanning, and dependency review
- ✅ **Performance Monitoring**: Bundle analysis and optimization tracking
- ✅ **Release Automation**: Tagged releases with automated package generation
- ✅ **Documentation Quality**: Automated validation and index generation
- ✅ **Artifact Management**: Structured retention and easy access to build outputs

### DevOps Maturity Status

**Continuous Integration**: Complete with comprehensive testing
**Continuous Deployment**: Automated package generation and release
**Security Scanning**: Proactive vulnerability management
**Quality Assurance**: Multi-layered validation and testing
**Documentation**: Automated maintenance and validation
**Performance**: Continuous monitoring and optimization

The WebSchedulr project now has enterprise-grade DevOps automation providing confidence in every release and deployment.

### July 2025 - Authentication System Styling Standardization & Brand Color Palette Implementation

**Phase**: Visual Design System Unification and Brand Identity Enhancement

This comprehensive update standardizes the authentication system design language and implements a cohesive brand color palette across the entire application, establishing a professional, distinctive visual identity.

#### 🎨 Brand Color Palette Implementation
- **Complete Color System Overhaul**: Replaced generic Material Design defaults with distinctive brand palette
- **Color Specifications**:
  - **Ocean Blue (#003049)**: Primary brand color for logos, headers, navigation, and text
  - **Crimson Red (#D62828)**: Error states, urgent notifications, and critical actions  
  - **Vibrant Orange (#F77F00)**: Primary action buttons, CTAs, and warning states
  - **Golden Yellow (#FCBF49)**: Success states, confirmations, and positive highlights
  - **Warm Cream (#EAE2B7)**: Background tones and neutral areas
- **Professional Impact**: Creates trustworthy, healthcare-appropriate visual identity suitable for multi-tenant SaaS deployment

#### 🔄 Authentication Flow Standardization
- **Unified Button Design**: Standardized all authentication buttons across login, forgot password, and reset password flows
- **Consistent Styling**: Replaced inconsistent Material Web Component buttons with uniform HTML button elements
- **Brand Color Integration**: Applied Orange (#F77F00) for all primary action buttons with proper hover states
- **Visual Cohesion**: Removed all gradient backgrounds in favor of clean, solid colors for professional appearance

**Technical Implementation**:
```php
// Standardized button styling across all auth pages
<button type="submit" class="w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white transition-all duration-200" style="background-color: #F77F00;">
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <!-- Relevant icon SVG -->
    </svg>
    Button Text
</button>
```

#### 🛠️ Design System Updates

**CSS Custom Properties Integration**:
```css
:root {
    --md-sys-color-primary: #003049;     // Ocean Blue brand primary
    --md-sys-color-error: #D62828;       // Crimson for errors
    --md-sys-color-surface-variant: #F3F4F6;  // Solid background
}
```

**Authentication Pages Enhanced**:
- **login.php**: Ocean Blue logo, Orange action button, solid background, brand link colors
- **forgot_password.php**: Complete brand palette application, gradient removal, consistent styling
- **reset_password.php**: Unified design language with other auth pages, professional appearance

#### 🎯 Visual Design Benefits

**Brand Differentiation**:
- **Unique Identity**: Distinctive color palette sets WebSchedulr apart from generic blue SaaS applications
- **Professional Appeal**: Deep Ocean Blue creates trustworthy, healthcare-appropriate impression
- **Memorable Branding**: Warm, energetic color scheme enhances brand recognition and recall

**User Experience Improvements**:
- **Intuitive Color Coding**: Clear visual hierarchy with semantic color meanings
- **Accessibility**: High contrast ratios ensure readability for all users  
- **Consistency**: Unified design language across all authentication touchpoints
- **Modern Aesthetic**: Clean, gradient-free design follows current professional design trends

#### 📋 Cross-Platform Consistency
- **Multi-Tenant Ready**: Brand colors work across all potential tenant applications
- **Scalable Design**: Color system designed for dashboard, forms, charts, and data visualization
- **Documentation Ready**: Complete color palette analysis and implementation guidelines created
- **Future-Proof**: Professional palette suitable for long-term brand growth

### Development Impact - Authentication Styling

This authentication system standardization represents a significant user experience and brand identity improvement:

1. **Visual Cohesion**: Unified design language creates professional, trustworthy first impression
2. **Brand Recognition**: Distinctive color palette enhances memorability and differentiation
3. **User Confidence**: Professional styling builds trust during critical authentication flows
4. **Scalability**: Consistent design system ready for multi-tenant SaaS deployment
5. **Maintainability**: Standardized components reduce design debt and inconsistencies

### Technical Achievements - Brand Implementation

- ✅ Complete authentication flow visual standardization (login, forgot password, reset password)
- ✅ Brand color palette implementation across all authentication touchpoints
- ✅ Removal of all gradient backgrounds for clean, professional appearance
- ✅ Consistent button styling with proper hover states and transitions
- ✅ Ocean Blue (#003049) brand identity for logos, headers, and navigation elements
- ✅ Orange (#F77F00) action buttons creating clear call-to-action hierarchy
- ✅ Solid background colors (#F3F4F6) for modern, clean aesthetic
- ✅ CSS custom properties updated to support Material Web Components with brand colors

### Brand Identity Status

**Color System**: Complete with semantic usage guidelines
**Authentication Design**: Fully standardized and professional
**Visual Consistency**: Achieved across all user-facing authentication flows
**Brand Recognition**: Enhanced through distinctive, memorable color palette
**Professional Appeal**: Healthcare/business appropriate styling established
**Multi-Tenant Ready**: Scalable design system suitable for SaaS deployment

The WebSchedulr authentication system now provides a cohesive, professional brand experience that builds user trust and confidence while establishing a distinctive visual identity in the competitive scheduling software market.

### July 2025 - Application Flow Control System & Production Deployment Fixes

**Phase**: Complete Application Flow Control Implementation and Production Deployment Resolution

This comprehensive update implements a robust application flow control system and resolves critical production deployment issues, establishing a production-ready application lifecycle management system.

#### 🔄 Application Flow Control Implementation
- **Centralized Routing Logic**: Created `AppFlow` controller as the main entry point
- **Proper Flow Sequence**: Implemented Setup → Login → Dashboard progression
- **Route Protection**: Added `SetupFilter` and `SetupAuthFilter` for comprehensive route protection
- **Setup Detection**: Created `setup_helper.php` with reusable setup completion checking functions
- **Filter Integration**: Updated Routes.php with proper filter application and nested route groups

**Technical Implementation**:
```php
// AppFlow controller - centralized routing logic
class AppFlow extends BaseController {
    public function index() {
        if (!$this->isSetupCompleted()) {
            return redirect()->to('/setup');
        }
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }
        return redirect()->to('/auth/login');
    }
}

// Setup completion detection helper
function is_setup_completed(): bool {
    $flagPath = WRITEPATH . 'setup_completed.flag';
    if (!file_exists($flagPath)) return false;
    
    $envPath = ROOTPATH . '.env';
    if (!file_exists($envPath)) return false;
    
    return true;
}
```

#### 🛠️ Filter System Enhancement
- **Issue Resolution**: Fixed "setup|auth" filter syntax errors causing application crashes
- **Filter Registration**: Properly registered setup, auth, and setup_auth filter aliases
- **Nested Route Groups**: Implemented layered filter application for proper security
- **Combined Filters**: Created SetupAuthFilter for cleaner route definitions

**Filter Architecture**:
```php
// Filters.php - proper filter registration
public array $aliases = [
    'setup'      => \App\Filters\SetupFilter::class,
    'auth'       => \App\Filters\AuthFilter::class,
    'setup_auth' => \App\Filters\SetupAuthFilter::class,
];

// Routes.php - nested filter application
$routes->group('dashboard', ['filter' => 'setup'], function($routes) {
    $routes->get('', 'Dashboard::index', ['filter' => 'auth']);
    $routes->get('simple', 'Dashboard::simple', ['filter' => 'auth']);
});
```

#### 🚀 Production Deployment Critical Fixes
- **Environment Configuration**: Fixed missing `.env.example` template in production packages
- **Setup Flag Management**: Enhanced package.js to exclude `setup_completed.flag` from deployments
- **Error Resolution**: Resolved "Failed to generate environment configuration file" error
- **URL Detection**: Fixed production baseURL auto-detection and path handling
- **Routing Issues**: Corrected .htaccess configuration for proper URL routing

**Deployment Package Improvements**:
```javascript
// Enhanced package.js with exclusion patterns
const excludePatterns = ['setup_completed.flag'];
copyDirectoryWithFilter(source, destination, excludePatterns);

// Ensure proper file inclusion
const essentialFiles = [
    { src: '.env.example', dest: '.env.example' },  // Added for setup wizard
    // ... other essential files
];
```

#### 📋 Setup Process Robustness
- **Enhanced Error Handling**: Comprehensive error logging in `generateEnvFile()` method
- **File Backup**: Added backup functionality for existing .env files
- **Validation Improvements**: Better user feedback and validation messaging
- **Production Compatibility**: Fixed URL detection for various hosting environments

**Setup Controller Enhancements**:
```php
protected function generateEnvFile(array $data): bool {
    // Enhanced error handling and logging
    if (!file_exists($envExamplePath)) {
        log_message('error', 'Setup: .env.example template not found at: ' . $envExamplePath);
        return false;
    }
    
    // Backup existing .env file
    if (file_exists($envPath)) {
        copy($envPath, $envPath . '.backup');
    }
    
    // Robust file writing with validation
    $writeResult = file_put_contents($envPath, $envContent);
    if ($writeResult === false) {
        log_message('error', 'Setup: Failed to write .env file to: ' . $envPath);
        return false;
    }
}
```

#### 🔒 Security & Validation Enhancements
- **Flag File Security**: Added `setup_completed.flag` to `.gitignore` to prevent accidental commits
- **CSRF Protection**: Maintained proper CSRF validation throughout the application
- **Route Protection**: Comprehensive route protection preventing unauthorized access
- **Error Logging**: Enhanced error logging for debugging and monitoring

#### 📦 Deployment Package System
- **Clean Deployments**: Ensured deployment packages exclude development artifacts
- **File Validation**: Added comprehensive validation and progress logging
- **Production Configuration**: Updated production deployment with all fixes
- **Exclusion Patterns**: Implemented proper exclusion patterns for sensitive files

**Package Script Improvements**:
```javascript
// Enhanced exclusion logic
function copyDirectoryWithFilter(src, dest, excludePatterns = []) {
    const shouldExclude = excludePatterns.some(pattern => {
        return typeof pattern === 'string' ? item === pattern : pattern.test(item);
    });
    
    if (shouldExclude) {
        console.log(`⏭️  Excluded: ${srcPath}`);
        return;
    }
}
```

#### 🔍 Production Testing & Validation
- **Multi-Environment Testing**: Validated application flow across development and production
- **Error Scenario Coverage**: Tested various failure scenarios and edge cases
- **Hosting Compatibility**: Verified compatibility with multiple hosting providers
- **Setup Wizard Validation**: Comprehensive testing of the setup process

### Development Impact - Application Flow Control

This application flow control implementation represents a major architectural improvement:

1. **Production Readiness**: Robust application lifecycle management suitable for enterprise deployment
2. **User Experience**: Seamless progression through setup, authentication, and dashboard access
3. **Security**: Comprehensive route protection and authentication flow
4. **Maintainability**: Clean, well-documented code with proper separation of concerns
5. **Reliability**: Comprehensive error handling and validation throughout the application

### Technical Achievements - July 2025

- ✅ Complete application flow control system (Setup → Login → Dashboard)
- ✅ Robust filter system with proper registration and layered application
- ✅ Production deployment fixes resolving critical setup issues
- ✅ Enhanced setup process with comprehensive error handling
- ✅ Security improvements with proper flag file management
- ✅ Clean deployment packages excluding development artifacts
- ✅ Comprehensive testing and validation across environments

### Deployment Readiness Status

**Application Flow**: Complete with proper lifecycle management
**Filter System**: Robust with comprehensive route protection
**Production Deployment**: All critical issues resolved
**Setup Process**: Enhanced with bulletproof error handling
**Security**: Comprehensive with proper file and route protection
**Documentation**: Updated with all recent improvements

### Current Status - July 2025

**Application Architecture**: Complete flow control system implemented
**Production Deployment**: All critical issues resolved and tested
**Setup Process**: Robust and production-ready
**Security**: Comprehensive protection mechanisms in place
**Next Phase**: Ready for dashboard interface development and user authentication features

The WebSchedulr application now has enterprise-grade application flow control and deployment systems, providing a solid foundation for core scheduling functionality development.

### July 2025 - Dark Mode System Implementation & Views Organization

**Phase**: Complete Dark Mode Integration and Code Organization Enhancement

This comprehensive update implements a complete dark mode system across all application views and reorganizes the view structure for better maintainability and production readiness.

#### 🌙 Complete Dark Mode System Implementation
- **Comprehensive Coverage**: Full dark mode support implemented across setup wizard, dashboard, and authentication views
- **CSS Variable System**: Material Design 3.0 color tokens with automatic light/dark theme adaptation
- **Component Integration**: Dark mode toggle component integrated throughout the application
- **JavaScript Management**: `DarkModeManager` class with localStorage persistence and system preference detection
- **Build System**: Vite configuration updated to include dark-mode.js as separate entry point

**Technical Implementation**:
```scss
// Dual-theme CSS variables system
:root {
  --md-sys-color-primary: rgb(59, 130, 246);
  --md-sys-color-surface: rgb(255, 255, 255);
}

html.dark {
  --md-sys-color-primary: rgb(96, 165, 250);
  --md-sys-color-surface: rgb(31, 41, 55);
}
```

```javascript
// Dark mode management system
class DarkModeManager {
  constructor() {
    this.initializeTheme();
    this.setupToggleHandlers();
  }
  
  initializeTheme() {
    const isDark = localStorage.getItem('darkMode') === 'true' || 
                   (!localStorage.getItem('darkMode') && 
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
    this.applyTheme(isDark);
  }
}
```

#### 🎨 Dark Mode Features Implemented

**Setup Wizard Dark Mode**:
- ✅ Complete form styling with dark mode classes and CSS variables
- ✅ Database configuration sections with dark theme support
- ✅ Progress indicators and validation messages
- ✅ Test connection buttons and loading overlays

**Dashboard Dark Mode**:
- ✅ Sidebar navigation with Material Design dark theme
- ✅ Statistics cards using CSS variables for consistent theming
- ✅ Charts and data tables with dark mode support
- ✅ User interface elements and action buttons

**Authentication Dark Mode**:
- ✅ Login, forgot password, and reset password views
- ✅ Form inputs and validation messages
- ✅ Brand color integration with dark theme variants

#### 📁 Views Folder Organization & Cleanup
- **Structure Reorganization**: Moved all test and example views to dedicated `app/Views/test/` folder
- **Production Readiness**: Main views folder now contains only production-ready views
- **Controller Updates**: Updated all controller references to use new view paths
- **Documentation**: Added comprehensive README.md in test folder explaining purpose and contents

**New Views Structure**:
```
app/Views/
├── auth/                    # Production authentication views
├── components/              # Reusable view components
├── errors/                  # Error page templates
├── dashboard.php           # Main dashboard (production)
├── setup.php              # Setup wizard (production)
└── test/                   # Development and testing views
    ├── README.md           # Documentation
    ├── styleguide/         # Design system documentation
    ├── dashboard_*.php     # Dashboard variants and tests
    ├── dark_mode_test.php  # Dark mode testing
    ├── material_web_example.php
    ├── tw.php              # Tailwind testing
    └── welcome_message.php # Default CI4 welcome
```

**Moved Files**:
- `dark_mode_test.php` → `test/dark_mode_test.php`
- `dashboard_example.php` → `test/dashboard_example.php`
- `dashboard_fixed.php` → `test/dashboard_fixed.php`
- `dashboard_real_data.php` → `test/dashboard_real_data.php`
- `dashboard_simple.php` → `test/dashboard_simple.php`
- `dashboard_test.php` → `test/dashboard_test.php`
- `material_web_example.php` → `test/material_web_example.php`
- `tw.php` → `test/tw.php`
- `welcome_message.php` → `test/welcome_message.php`
- `styleguide/` → `test/styleguide/`

#### 🛠️ Technical Enhancements

**Dark Mode Toggle Component** (`components/dark-mode-toggle.php`):
```php
<button id="darkModeToggle" class="relative inline-flex items-center justify-center w-10 h-10 text-sm font-medium rounded-full transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600" aria-label="Toggle dark mode">
    <!-- Sun/Moon icons with transitions -->
</button>
```

**CSS Variables Integration**:
- Material Design 3.0 color tokens
- Automatic theme switching with smooth transitions
- Consistent color usage across all components
- Accessible contrast ratios in both themes

**Build System Updates**:
```javascript
// vite.config.js - Updated entry points
export default defineConfig({
  build: {
    rollupOptions: {
      input: {
        main: 'resources/js/app.js',
        style: 'resources/scss/app.scss',
        darkMode: 'resources/js/dark-mode.js', // New entry point
        // ... other entries
      }
    }
  }
});
```

#### 🎯 User Experience Benefits

**Seamless Theme Switching**:
- ✅ Instant theme transitions with 300ms duration
- ✅ Persistent theme preferences across sessions
- ✅ System preference detection and automatic application
- ✅ Accessible toggle controls with proper ARIA labels

**Professional Dark Theme**:
- ✅ Material Design 3.0 compliant color scheme
- ✅ Proper contrast ratios for accessibility
- ✅ Consistent theming across all application areas
- ✅ Reduced eye strain for extended usage

**Code Organization Benefits**:
- ✅ Clean separation of production and development views
- ✅ Easier maintenance and deployment preparation
- ✅ Clear documentation of test and example purposes
- ✅ Streamlined controller structure

### Development Impact - Dark Mode & Organization

This dark mode implementation and views organization represents a major user experience and maintainability improvement:

1. **User Experience**: Modern dark mode system enhances usability and accessibility
2. **Code Quality**: Clean separation of production and development views
3. **Maintainability**: Organized structure simplifies ongoing development
4. **Professional Appeal**: Complete dark mode support creates modern, professional impression
5. **Developer Experience**: Clear documentation and organized test structure

### Technical Achievements - July 2025

- ✅ Complete dark mode system across all application views
- ✅ CSS variables and Material Design 3.0 integration
- ✅ JavaScript dark mode manager with persistence
- ✅ Views folder organization with test/production separation
- ✅ Updated controllers and route references
- ✅ Build system integration for dark mode assets
- ✅ Comprehensive documentation and README files

### Dark Mode & Organization Status

**Dark Mode System**: Complete with comprehensive coverage
**Theme Management**: Persistent with system preference detection
**Views Organization**: Clean separation of production and test files
**Controller Updates**: All references updated to new structure
**Documentation**: Comprehensive with clear purpose definitions
**Build Integration**: Dark mode assets properly compiled and included

The WebSchedulr application now provides a complete, modern dark mode experience with organized, maintainable code structure ready for production deployment.

### July 2025 - Cookie Configuration Fixes & Production Environment Optimization

**Phase**: Environment Configuration Debugging and Production Deployment Enhancement

This critical update resolves cookie-related issues in local development environments and optimizes the application for seamless production deployment across various hosting providers.

#### 🔧 Cookie Security Configuration Resolution
- **Issue Identification**: Setup wizard failing with cookie errors on `http://localhost:8080` due to `cookie.secure = true`
- **Root Cause**: Secure cookies only work over HTTPS, but local development uses HTTP
- **Technical Solution**: Environment-aware cookie configuration in `.env` file
- **Security Enhancement**: Updated CSRF and SameSite settings for local development compatibility

**Configuration Fixes Applied**:
```properties
# Development-friendly cookie configuration
cookie.secure = false      # Allow cookies over HTTP for local dev
cookie.httponly = true     # Maintain security best practices
cookie.samesite = 'Lax'    # Less restrictive for development

# CSRF Protection Enabled
security.CSRFProtection = true     # Enable CSRF protection
security.CSRFSameSite = 'Lax'      # Compatible with local dev
```

#### 🗂️ Views Folder Organization & Production Readiness
- **Code Organization**: Systematic reorganization of views folder structure for better maintainability
- **Test File Isolation**: Created dedicated `app/Views/test/` subfolder for development and testing files
- **Production Optimization**: Enhanced deployment package to exclude test files from production deployments
- **Controller Updates**: Updated all relevant controllers to reference new view paths

**Views Reorganization Details**:
```
app/Views/
├── setup.php, dashboard.php, auth/     # Production views (main folder)
└── test/                               # Development/test views
    ├── README.md                       # Comprehensive documentation
    ├── dark_mode_test.php             # Dark mode testing interface
    ├── dashboard_*.php                # Dashboard development variants
    ├── material_web_example.php       # Material Web Components demo
    ├── styleguide/                    # Design system examples
    ├── tw.php                         # Tailwind CSS testing
    └── welcome_message.php            # CodeIgniter default view
```

**Deployment Package Enhancement**:
```javascript
// Enhanced package.js with Views/test exclusion
const excludePatterns = ['Views/test'];
console.log(`📁 Production deployment excludes: ${excludePatterns.join(', ')}`);
```

#### 🎨 SQLite Database Path Correction
- **Setup View Fix**: Corrected hardcoded database filename from `appdb.sqlite` to `webschedulr.db`
- **Configuration Alignment**: Ensured setup wizard displays accurate database path information
- **User Experience**: Clear, consistent messaging about database creation location

**Database Path Correction**:
```php
// Fixed setup view database display
<code class="block mt-1 text-xs px-2 py-1 rounded font-mono">
    ./writable/database/webschedulr.db  <!-- Corrected from appdb.sqlite -->
</code>
```

#### 🔒 Environment Security & Validation
- **CSRF Token Integration**: Proper CSRF protection throughout the application
- **Cookie Security**: Environment-appropriate security settings
- **Setup Validation**: Enhanced error handling and user feedback
- **Production Safety**: Secure defaults with development overrides

#### 📦 Build System Validation
- **Asset Compilation**: Verified build system still works after reorganization
- **Development Server**: Confirmed local development functionality
- **Production Package**: Validated deployment package generation
- **Documentation Updates**: Comprehensive documentation of all changes

### Development Impact - Environment Optimization

This environment configuration and organization update represents a significant stability and maintainability improvement:

1. **Local Development**: Seamless setup wizard functionality in development environments
2. **Production Readiness**: Clean deployment packages excluding development files
3. **Code Organization**: Well-structured views folder with clear separation of concerns
4. **Developer Experience**: Comprehensive documentation and testing capabilities
5. **Deployment Reliability**: Consistent, validated deployment process

### Technical Achievements - Cookie & Views Organization

- ✅ **Cookie Configuration**: Resolved HTTP/HTTPS compatibility issues for local development
- ✅ **Views Organization**: Systematic reorganization with 10 test files moved to dedicated subfolder
- ✅ **Controller Updates**: All view references updated to new paths (Styleguide, Tw, Dashboard, DarkModeTest)
- ✅ **Deployment Enhancement**: Production packages exclude test folder for clean deployments
- ✅ **Database Path Correction**: Setup wizard displays accurate SQLite database path
- ✅ **Build Validation**: Confirmed asset compilation and development server functionality
- ✅ **Documentation**: Comprehensive README in test folder and updated mastercontext

### Current Development Status - July 2025

**Environment Configuration**: Optimized for both development and production
**Views Organization**: Clean, maintainable structure with proper separation
**Deployment System**: Enhanced with test file exclusion for production packages
**Cookie Handling**: Environment-aware configuration resolving development issues
**Database Setup**: Accurate path display and configuration alignment
**Build System**: Validated and functional across all reorganization changes

The WebSchedulr application now provides a robust, well-organized development environment with seamless production deployment capabilities, ensuring consistent functionality across all hosting environments while maintaining clean, maintainable code structure.

### August 2025 - UI Polish: Layout Container Standardization, Card Radius Consistency, and Dark Mode Toggle Consolidation

**Phase**: Dashboard shell refinements and design system alignment

This update standardizes container widths, rounds, and theme behavior for a cohesive dashboard experience.

#### 📐 Container Width Standardization
- Adopted a single responsive container utility: `.page-container` for top-level sections (header, content, footer)
- Updated footer to use `.page-container` instead of `max-w-7xl` to align exactly with dashboard content width
- Ensures consistent horizontal rhythm across pages and breakpoints

#### ⭕ Card Radius Standardization
- Established `rounded-lg` as the default card corner radius across dashboard cards
- Updated footer inner card to `rounded-lg` to match dashboard cards (previously `rounded-2xl`)
- Sidebar and header retain their intended visual hierarchy while aligning with card radius where applicable

#### 🌙 Dark Mode Toggle Consolidation
- Consolidated to a single global dark mode toggle in the top app bar (next to search)
- Removed duplicate toggle from the sidebar and wired all components to respond to the global `html.dark` class
- Persistence via `localStorage` ensures theme selection is remembered across sessions
- Sidebar, header, footer, and cards now uniformly adapt to theme changes without component-level toggles

#### 🔧 Implementation Notes
- Views affected: `app/Views/components/footer.php`, `app/Views/components/header.php`, `app/Views/dashboard.php`
- Theme initialization runs early in `layout.php` to avoid FOUC; `dark-mode.js` manages runtime toggling
- Design tokens continue to drive color across light/dark themes; Tailwind utilities apply the structure

#### 🧭 Standards Summary
- Container: Use `.page-container` for page-level horizontal layout
- Card radius: Default to `rounded-lg` for cards and card-like surfaces
- Theme: Rely on `html.dark` class; do not place per-component toggles

These changes deliver a consistent shell and improve perceived polish, matching the dashboard’s visual language across all layout regions.


### September 2025 - Single Page Application (SPA) Architecture Implementation

**Phase**: Modern client-side navigation and user experience enhancement

This update implements a lightweight SPA system that preserves the application shell (header, sidebar, footer) while swapping page content dynamically, eliminating full page reloads and providing a seamless user experience.

#### 🚀 SPA Architecture Overview
- **Core Implementation**: Lightweight SPA router in `resources/js/spa.js` (157 lines)
- **Navigation Strategy**: Intercepts same-origin link clicks, fetches content via AJAX, swaps `#spa-content` div
- **Shell Preservation**: Header, sidebar, and footer remain static; only main content area updates  
- **Build Integration**: Separate Vite entry point for SPA module (`spa.js`)
- **Progressive Enhancement**: Graceful degradation - works without JavaScript (falls back to full page loads)

**Technical Implementation**:
```javascript
const SPA = (() => {
  const content = () => document.getElementById('spa-content');
  const navigate = async (url, push = true) => {
    const html = await fetchPage(url);
    el.innerHTML = html;
    if (push) history.pushState({ spa: true }, '', url);
    document.dispatchEvent(new CustomEvent('spa:navigated'));
  };
})();
```

#### 🎯 SPA Features
- ✅ Click interception for same-origin links with opt-out (`data-no-spa`, `.no-spa`)
- ✅ Browser back/forward button support (popstate handling)
- ✅ Manual scroll restoration and focus management
- ✅ Script execution in newly loaded content for per-view initialization
- ✅ FullCalendar navigation exclusion (`.fc` container, `data-navlink`)
- ✅ Loading states with aria-busy attributes

#### 📋 Benefits
- **Performance**: No full page reloads, only content area refreshes, reduced bandwidth
- **UX**: Seamless transitions, persistent UI state (dark mode, sidebar position)
- **Developer**: Event-driven pattern (`spa:navigated`), minimal footprint (157 lines)

---

### September 2025 - Role-Based Access Control (RBAC) System

**Phase**: Comprehensive user roles, permissions, and hierarchical access control

This update implements a complete role-based access control system with four distinct user roles, granular permissions, and hierarchical user management for multi-tenant service provider operations.

#### 👥 User Roles
1. **Administrator (admin)** - Full system access
2. **Service Provider (provider)** - Business owner/manager  
3. **Staff Member (staff)** - Employee assigned to provider
4. **Customer (customer)** - Appointment booking only (separate `xs_customers` table)

**Database Schema**:
```sql
CREATE TABLE xs_users (
  role ENUM('admin','provider','staff','customer') DEFAULT 'customer',
  provider_id INT NULL,  -- For staff assigned to providers
  permissions JSON NULL,  -- Custom permissions override
  status ENUM('active','inactive','suspended') DEFAULT 'active'
);

CREATE TABLE xs_provider_staff_assignments (
  provider_id INT NOT NULL,
  staff_id INT NOT NULL,
  UNIQUE KEY unique_assignment (provider_id, staff_id)
);
```

#### 🔐 Permissions System
**Role Permissions** (`app/Models/UserPermissionModel.php`):
- **Admin**: `system_settings`, `user_management`, `create_admin`, `create_provider`, `view_all_appointments`, `backup_restore`
- **Provider**: `manage_own_calendar`, `create_staff`, `manage_services`, `view_staff_calendars`, `provider_analytics`
- **Staff**: `manage_own_calendar`, `view_own_appointments`, `create_appointments`, `basic_profile_edit`

**Helper Functions**:
```php
has_role($roles)              // Check user role
has_permission($permissions)   // Check permission
is_admin(), is_provider()     // Quick role checks
can_manage_users()            // Permission checks
get_user_hierarchy()          // Get manageable users
```

#### 🚦 Access Control
**Route Protection**:
```php
$routes->group('settings', ['filter' => 'role:admin'], function($routes) {});
$routes->group('user-management', ['filter' => 'role:admin,provider'], function($routes) {});
```

**Hierarchical Access**:
- Admin sees all users
- Provider sees own staff only
- Staff sees only themselves
- Customers managed separately

#### 🎯 Role-Specific Features
**Admin**: User Management, System Settings, Global Analytics, All Calendars
**Provider**: My Staff, Services, Provider Analytics, Staff Calendars
**Staff**: My Schedule, My Appointments, Profile
**Customer**: Book Appointments, My Appointments, Profile

#### 🎨 UI Adaptations
- Dynamic navigation based on role
- Role badges with color coding (admin=red, provider=blue, staff=green)
- Conditional rendering (`<?php if (has_role('admin')): ?>`)
- Permission-based button visibility

#### 📊 Security
- Route-level protection (filters: `role:admin`, `role:admin,provider`)
- Controller-level permission checks (`has_permission()`)
- View-level conditional rendering
- Database-level user hierarchy
- Data isolation by role

#### 📚 Documentation
- `docs/architecture/ROLE_BASED_SYSTEM.md` - Full system overview
- `docs/development/staff-assignment.md` - Provider-staff guide  
- Test users: admin@test.com, provider@test.com, staff@test.com, customer@test.com (all password: password123)

---

### October 2025 - Centralized Settings System Implementation

**Phase**: Application configuration and control center

This implementation establishes a centralized settings system where all application behavior—from localization and business hours to booking fields and integrations—is controlled through a unified admin interface backed by a flexible database schema.

#### 🎛️ Settings Architecture

**Core Principle**: Single source of truth for all application configuration, eliminating hardcoded values and enabling runtime customization.

**Database Schema** (`xs_settings`):
```sql
CREATE TABLE xs_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(191) UNIQUE NOT NULL,    -- Namespaced key (e.g., 'localization.timezone')
  setting_value TEXT,                          -- Flexible value storage
  setting_type ENUM('string','int','float','bool','json') DEFAULT 'string',
  updated_by INT NULL,                         -- Audit trail: user who last modified
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- File-based settings (logos, avatars)
CREATE TABLE xs_settings_files (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(191) UNIQUE NOT NULL,
  filename VARCHAR(255),
  mime VARCHAR(64),
  data LONGBLOB,                               -- Binary file storage
  updated_by INT NULL,
  created_at DATETIME,
  updated_at DATETIME
);
```

#### 📋 Settings Categories

**1. General Settings** (`general.*`)
- `general.company_name` - Business name displayed throughout app
- `general.company_email` - Primary contact email
- `general.company_link` - Website URL
- `general.telephone_number` - Main phone number
- `general.mobile_number` - Mobile contact
- `general.business_address` - Physical location
- `general.logo` - Company logo (stored in `xs_settings_files`)

**2. Localization Settings** (`localization.*`)
- `localization.time_format` - '12h' or '24h' (affects all time displays)
- `localization.first_day` - 'Sunday', 'Monday', etc. (calendar start day)
- `localization.language` - 'English', 'Portuguese-BR', 'Spanish'
- `localization.timezone` - IANA timezone (e.g., 'Africa/Johannesburg')
- `localization.currency` - Currency code (e.g., 'USD', 'ZAR', 'EUR')

**3. Booking Settings** (`booking.*`)
- **Standard Fields Configuration**:
  - `booking.first_names_display` - Show first name field (bool)
  - `booking.first_names_required` - Require first name (bool)
  - `booking.surname_display` - Show surname field (bool)
  - `booking.surname_required` - Require surname (bool)
  - `booking.email_display` - Show email field (bool)
  - `booking.email_required` - Require email (bool)
  - `booking.phone_display` - Show phone field (bool)
  - `booking.phone_required` - Require phone (bool)

- **Custom Fields** (6 configurable fields):
  - `booking.custom_field_N_title` - Field label
  - `booking.custom_field_N_type` - 'text', 'number', 'email', 'tel', 'textarea'
  - `booking.custom_field_N_display` - Show field (bool)
  - `booking.custom_field_N_required` - Require field (bool)

- **Appointment Management**:
  - `booking.statuses` - Available appointment statuses (JSON array)
  - `booking.fields` - Enabled standard fields (JSON: `["email","phone"]`)

**4. Business Hours Settings** (`business.*`)
- `business.work_start` - Default opening time (e.g., '08:00')
- `business.work_end` - Default closing time (e.g., '17:00')
- `business.break_start` - Break period start (e.g., '12:00')
- `business.break_end` - Break period end (e.g., '13:00')
- `business.blocked_periods` - Holiday/closure dates (JSON array)
- `business.reschedule` - Allow rescheduling (bool)
- `business.cancel` - Allow cancellations (bool)
- `business.future_limit` - Max days ahead for booking (int)

**5. Legal Content** (`legal.*`)
- `legal.cookie_notice` - Cookie policy text
- `legal.terms` - Terms of service
- `legal.privacy` - Privacy policy

**6. Integrations** (`integrations.*`)
- `integrations.webhook_url` - External webhook endpoint
- `integrations.analytics` - Analytics tracking code
- `integrations.api_integrations` - Third-party API configs (JSON)
- `integrations.ldap_enabled` - Enable LDAP authentication
- `integrations.ldap_host` - LDAP server address
- `integrations.ldap_dn` - LDAP distinguished name

#### 🔧 Implementation Components

**SettingModel** (`app/Models/SettingModel.php`):
```php
class SettingModel extends BaseModel {
    protected $table = 'xs_settings';
    
    // Get settings by prefix (e.g., all 'localization.*')
    public function getByPrefix(string $prefix): array;
    
    // Get specific settings by keys
    public function getByKeys(array $keys): array;
    
    // Upsert single setting with type casting
    public function upsert(string $key, $value, string $type, ?int $updatedBy): bool;
    
    // Automatic type casting (string, int, float, bool, json)
    private function castValue(?string $val, string $type);
}
```

**Settings Controller** (`app/Controllers/Settings.php`):
- `index()` - Display settings interface with all categories
- `save()` - Process form submissions with validation
- Admin-only access via `role:admin` filter
- Handles checkboxes (only send when checked)
- File upload for logos/avatars

**Service Classes**:
- **LocalizationSettingsService**: Timezone, time format, language handling
- **BookingSettingsService**: Field configuration, validation rules
- **CalendarConfigService**: FullCalendar-specific settings transformation
- **BusinessHoursService**: Operating hours and breaks management

#### 🌐 Settings API Endpoints

**GET `/api/v1/settings`** - Fetch all settings (authenticated)
```json
{
  "data": {
    "general.company_name": "WebSchedulr Demo",
    "localization.timezone": "Africa/Johannesburg",
    "localization.time_format": "24h",
    "business.work_start": "08:00",
    "business.work_end": "17:00",
    "booking.email_required": true
  }
}
```

**GET `/api/v1/settings/calendar-config`** - Calendar-specific configuration
```json
{
  "timeZone": "Africa/Johannesburg",
  "locale": "en",
  "firstDay": 1,
  "eventTimeFormat": {
    "hour": "2-digit",
    "minute": "2-digit",
    "hour12": false
  },
  "slotLabelFormat": {
    "hour": "2-digit",
    "minute": "2-digit",
    "hour12": false
  },
  "businessHours": [
    {"daysOfWeek": [1,2,3,4,5], "startTime": "08:00", "endTime": "17:00"}
  ],
  "slotMinTime": "08:00",
  "slotMaxTime": "18:00"
}
```

**POST `/api/v1/settings`** - Update settings (admin only)
- Accepts form data with namespaced keys
- Validates and type-casts values
- Tracks `updated_by` user ID
- Returns success/error status

#### 🔄 Settings Flow Integration

**Frontend Integration**:
```javascript
// Fetch settings on page load
const response = await fetch('/api/v1/settings');
const settings = response.json().data;

// Apply to calendar
calendar.setOption('timeZone', settings['localization.timezone']);
calendar.setOption('firstDay', settings['localization.first_day'] === 'Monday' ? 1 : 0);

// Apply to booking form
if (settings['booking.phone_required']) {
  phoneField.setAttribute('required', 'required');
}
```

**Backend Integration**:
```php
// In controllers
$settingModel = new SettingModel();
$timezone = $settingModel->get('localization.timezone') ?? 'UTC';
$timeFormat = $settingModel->get('localization.time_format') ?? '24h';

// In services
$bookingService = new BookingSettingsService();
$fieldConfig = $bookingService->getFieldConfiguration();
// Returns: ['email' => ['display' => true, 'required' => true], ...]
```

#### 📊 Settings Validation

**Form Validation**:
- Time format: Must be '12h' or '24h'
- Timezone: Must be valid IANA timezone identifier
- Work hours: Start must be before end
- Custom fields: Title required if field is displayed
- Email fields: Valid email format validation

**Type Validation**:
```php
// Automatic type casting in SettingModel
'time_format' => 'string'          // Stored as string
'email_required' => 'bool'         // Stored as 'true'/'false', cast to boolean
'future_limit' => 'int'            // Stored as string, cast to integer
'blocked_periods' => 'json'        // Stored as JSON, decoded to array
```

#### 🎨 Settings UI

**Tabbed Interface** (`app/Views/settings.php`):
- **General** - Company info, contact details, logo upload
- **Localization** - Time format, timezone, language, currency
- **Booking** - Field configuration, custom fields
- **Business Hours** - Work hours, breaks, blocked periods
- **Legal** - Terms, privacy, cookie notice
- **Integrations** - Webhooks, analytics, LDAP

**Features**:
- Live preview of settings changes
- Edit/Cancel modes for each section
- Flash messages for success/error feedback
- Validation errors displayed inline
- SPA navigation compatible

#### 🔐 Settings Security

**Access Control**:
- Admin-only access to Settings page
- `role:admin` filter on all settings routes
- `updated_by` audit trail for all changes
- CSRF protection on all forms

**Data Validation**:
- Server-side validation for all inputs
- Type safety with SettingModel casting
- Sanitization of user-provided values
- File upload validation (MIME types, size limits)

#### 📈 Common/Global Variables Pattern

**Naming Convention**: `category.setting_name`
```
general.*          - Company-wide settings
localization.*     - Regional/cultural settings
booking.*          - Booking form configuration
business.*         - Operational parameters
legal.*            - Legal/compliance content
integrations.*     - Third-party integrations
```

**Global Access Pattern**:
```php
// Helper function pattern
function get_time_format(): string {
    return service('settings')->get('localization.time_format') ?? '24h';
}

// Service injection pattern
class AppointmentController {
    private SettingModel $settings;
    
    public function __construct() {
        $this->settings = new SettingModel();
    }
    
    public function create() {
        $timezone = $this->settings->get('localization.timezone');
        // Use timezone for datetime operations
    }
}
```

#### 🧪 Settings Testing

**CLI Commands**:
- `php spark check:booking` - Display all booking settings
- `php spark settings:audit` - Comprehensive settings validation
- `php spark settings:view-audit` - View-to-model mapping verification

**Test Scripts**:
- `tests/add_new_settings_fields.php` - Seed default settings
- `tests/add_test_settings.php` - Create test configurations
- `docs/audit_settings_data_flow.php` - Data flow validation

#### 📚 Documentation

**Complete Settings Documentation**:
- `docs/frontend/calendar-settings-sync.md` - Calendar settings integration
- `docs/development/phase-2-testing.md` - Settings testing procedures
- `docs/technical/SPA_SETTINGS_FIX.md` - Settings form value persistence

**Settings Benefits**:
- ✅ **Centralized Control**: Single location for all configuration
- ✅ **Runtime Updates**: Changes apply immediately without code deployment
- ✅ **Type Safety**: Automatic casting prevents type-related bugs
- ✅ **Audit Trail**: Track who changed what and when
- ✅ **Flexible Storage**: Supports strings, numbers, booleans, JSON, and files
- ✅ **Validation**: Built-in validation prevents invalid configurations
- ✅ **Extensible**: Easy to add new settings without schema changes
- ✅ **Multi-Tenant Ready**: Settings can be scoped per tenant (future)

---

### October 2025 - Data Protection & Profile Experience Enhancements

**Phase**: Operational resilience and user account management overhaul

#### 🗄️ Database Backup Automation
- Delivered `scripts/db_backup.php`, a CLI-driven mysqldump wrapper that reads connection settings from `.env`, detects missing binaries, and emits timestamped gzip archives to `builds/backups/`.
- Added `docs/DB_BACKUP_PLAN.md` with full + incremental retention strategy, cron examples, credential handling guidance, and restore validation checklist.
- Hardened script with directory bootstrap, permission checks, and informative console output for CI logs and manual operators.
- Completed full backup dry-run on production-like data set; artifact `20251016_100425_full.sql.gz` verified for size consistency and mysql import readiness.

#### 👤 Profile Management Consolidation
- Refactored `app/Controllers/Profile.php` to load unified account data, handle inline profile updates, password changes, and avatar uploads within a single authenticated endpoint group.
- Expanded `app/Models/UserModel.php` allowed fields to cover `profile_image`, ensuring persistence aligns with CodeIgniter mass-assignment safeguards.
- Modernized view `app/Views/profile/index.php` with embedded tabs for profile/password forms, flash messaging, avatar previews, and CSRF-aware upload controls.
- Mirrored logo uploader resilience: validated MIME types, normalized filenames, resized images, stored under `public/assets/profile/`, and refreshed session cache post-upload.
- Updated `app/Config/Routes.php` to register POST routes for `/profile/update-profile` and `/profile/change-password`, each guarded by `auth` + `setup` filters for consistent security posture.

#### ✅ Outcomes & Follow-Up
- Profile page now matches dashboard design language and allows users to edit personal data, change passwords, and update avatars without leaving `/profile`.
- Backup process documented and automatable, providing recoverability baseline ahead of scheduler feature rollout.
- Next focus items: confirm asset directory write permissions across deployments and extend manual/automated tests to cover avatar upload edge cases (SVG, oversized files).
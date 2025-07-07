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

# xScheduler CI4 - Master Context Document

## Project Overview

**xScheduler** is a modern scheduling application built with CodeIgniter 4, featuring a Material Design dashboard with Tailwind CSS, designed for deployment to standard hosting providers with zero configuration requirements.

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
xScheduler_ci4/
├── app/                          # CodeIgniter 4 application
│   ├── Controllers/              # Request handlers
│   │   ├── BaseController.php    # Base controller with UI helper loading
│   │   ├── Dashboard.php         # Material Design dashboard controller
│   │   ├── Home.php             # Default welcome controller
│   │   ├── Setup.php            # Setup wizard controller
│   │   ├── Styleguide.php       # Design system documentation
│   │   └── Tw.php               # Tailwind testing controller
│   ├── Config/                  # Application configuration
│   │   ├── App.php              # Main app config (baseURL, indexPage)
│   │   └── Routes.php           # URL routing definitions
│   ├── Helpers/                 # Custom helper functions
│   │   ├── ui_helper.php        # UI component helper functions
│   │   └── vite_helper.php      # Vite asset management helpers
│   └── Views/                   # Template files
│       ├── components/          # Reusable view components
│       │   ├── layout.php       # Main layout template
│       │   ├── header.php       # Header component
│       │   └── footer.php       # Footer component
│       ├── dashboard.php        # Production Material Design dashboard
│       ├── dashboard_simple.php # Simplified dashboard variant
│       ├── dashboard_example.php # Dashboard development examples
│       ├── material_web_example.php # Material Web Components showcase
│       ├── styleguide/          # Design system documentation
│       │   ├── index.php        # Style guide home
│       │   └── components.php   # Component showcase
│       ├── setup.php            # Setup wizard view
│       └── tw.php               # Tailwind test page
├── resources/                   # Frontend assets
│   ├── js/
│   │   ├── app.js              # Main JavaScript entry point
│   │   ├── material-web.js     # Material Web Components setup
│   │   └── charts.js           # Chart.js configurations and utilities
│   └── scss/
│       ├── app.scss            # Main SCSS file with Material Design tokens
│       └── components.scss     # Custom component definitions
├── public/                     # Web-accessible files
│   ├── build/assets/           # Compiled assets (Vite output)
│   │   ├── style.css          # Compiled Tailwind + Material styles
│   │   ├── main.js            # App logic + Chart.js bundle
│   │   └── materialWeb.js     # Material Web Components bundle
│   ├── index.php              # Application entry point
│   └── .htaccess              # Apache rewrite rules with security headers
├── scripts/                   # Build and deployment scripts
│   └── package.js             # Production deployment packaging script
├── system/                    # CodeIgniter 4 framework (copied to deployment)
├── vendor/                    # Composer dependencies
├── writable/                  # Cache, logs, uploads
├── xscheduler-deploy/         # Production deployment package
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
xscheduler-deploy/
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
$routes->get('/', 'Home::index');                    # Welcome page
$routes->get('setup', 'Setup::setup');               # Setup wizard
$routes->get('tw', 'Tw::tw');                       # Tailwind test
$routes->get('styleguide', 'Styleguide::index');     # Design system docs
$routes->get('styleguide/components', 'Styleguide::components');
$routes->get('styleguide/scheduler', 'Styleguide::scheduler');
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

### 🔄 In Progress
- [ ] Scheduler-specific components implementation
- [ ] Time slot management system
- [ ] Calendar view components
- [ ] Appointment booking functionality

### 📋 Planned Features
- [ ] Database schema for scheduling
- [ ] User authentication system
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

**Last Updated**: January 2025
**Version**: 1.0.0
**Maintainer**: Development Team
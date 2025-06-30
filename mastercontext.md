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

**xScheduler** is a modern scheduling application built with CodeIgniter 4 and Tailwind CSS, designed for deployment to standard hosting providers with zero configuration requirements.

### Core Architecture
- **Backend**: CodeIgniter 4 (PHP framework)
- **Frontend**: Tailwind CSS 3.4.17 with CoreUI components
- **Build System**: Vite 6.3.5
- **Asset Management**: SCSS with PostCSS processing
- **Deployment**: Standalone package for shared hosting

## Project Structure

```
xScheduler_ci4/
├── app/                          # CodeIgniter 4 application
│   ├── Controllers/              # Request handlers
│   │   ├── BaseController.php    # Base controller with UI helper loading
│   │   ├── Home.php             # Default welcome controller
│   │   ├── Setup.php            # Setup wizard controller
│   │   ├── Styleguide.php       # Design system documentation
│   │   └── Tw.php               # Tailwind testing controller
│   ├── Config/                  # Application configuration
│   │   ├── App.php              # Main app config (baseURL, indexPage)
│   │   └── Routes.php           # URL routing definitions
│   ├── Helpers/                 # Custom helper functions
│   │   └── ui_helper.php        # UI component helper functions
│   └── Views/                   # Template files
│       ├── components/          # Reusable view components
│       │   ├── layout.php       # Main layout template
│       │   ├── header.php       # Header component
│       │   └── footer.php       # Footer component
│       ├── styleguide/          # Design system documentation
│       │   ├── index.php        # Style guide home
│       │   └── components.php   # Component showcase
│       ├── setup.php            # Setup wizard view
│       └── tw.php               # Tailwind test page
├── resources/                   # Frontend assets
│   ├── js/
│   │   └── app.js              # Main JavaScript entry point
│   └── scss/
│       ├── app.scss            # Main SCSS file
│       └── components.scss     # Custom component definitions
├── public/                     # Web-accessible files
│   ├── build/assets/           # Compiled assets (Vite output)
│   ├── index.php              # Application entry point
│   └── .htaccess              # Apache rewrite rules
├── scripts/                   # Build and deployment scripts
│   └── package.js             # Deployment packaging script
├── system/                    # CodeIgniter 4 framework
├── vendor/                    # Composer dependencies
├── writable/                  # Cache, logs, uploads
├── vite.config.js            # Vite build configuration
├── tailwind.config.js        # Tailwind CSS configuration
└── package.json              # Node.js dependencies and scripts
```

## Design System Implementation

### Component Architecture
The application uses a hybrid approach combining Tailwind utilities with custom components:

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
- `ui_button($text, $href, $type, $attributes)` - Standardized button generation
- `ui_card($title, $content, $footer)` - Card component wrapper
- `ui_alert($message, $type, $title)` - Alert component with variants

#### View Components (`app/Views/components/`)
- **layout.php**: Master page template with header/footer inclusion
- **header.php**: Navigation and branding
- **footer.php**: Site footer with links

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

### Vite Configuration (`vite.config.js`)
- **Input**: `resources/js/app.js`, `resources/scss/app.scss`
- **Output**: `public/build/assets/`
- **Base Path**: `./` for flexible deployment
- **Asset Naming**: Static names for production consistency

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

### Frontend Dependencies
```json
{
  "@coreui/coreui": "^5.4.0",
  "@coreui/icons": "^3.0.1",
  "tailwindcss": "^3.4.17",
  "@tailwindcss/forms": "^0.5.7",
  "@tailwindcss/typography": "^0.5.10",
  "sass": "^1.89.2",
  "vite": "^6.3.5"
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
/* ----------------------------------------------------------------------------
 * @webSchedulr - Online Appointment Scheduler
 *
 * @package     @webSchedulr - Online Appointments
 * @author      N N.Cara <nilo.cara@frontend.co.za>
 * @copyright   Copyright (c) Nilo Cara
 * @license     Proprietary Commercial License (see LICENSE-PROPRIETARY)
 * @link        https://webschedulr.co.za
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

# @webSchedulr - Online Appointment Scheduler

A modern, zero-configuration scheduling application built with CodeIgniter 4 and Tailwind CSS, designed for deployment to any standard hosting provider without server configuration requirements.

## 🚀 Features

- **Modern Design System**: Tailwind CSS 3.4.17 with custom component library
- **Zero-Config Deployment**: Deploy to any hosting provider instantly
- **Responsive Design**: Mobile-first approach with CoreUI components
- **Reusable Components**: Standardized UI components with PHP helpers
- **Developer Friendly**: Comprehensive style guide and documentation
- **Production Ready**: Optimized build system with Vite
- **Security Enhanced**: IP protection, security headers, and proprietary licensing
- **Role-Based Access**: Complete user management with role-based permissions

## 🏗️ Architecture

### Tech Stack
- **Backend**: CodeIgniter 4 (PHP 8.1+)
- **Frontend**: Tailwind CSS + CoreUI Components
- **Build System**: Vite 6.3.5 with SCSS/PostCSS
- **Asset Management**: Optimized compilation and packaging
- **Deployment**: Standalone packages for shared hosting

### Design System
- **Component Library**: Custom SCSS components with `@layer` directive
- **PHP Helpers**: `ui_button()`, `ui_card()`, `ui_alert()` functions
- **View Components**: Reusable layout templates and partials
- **Style Guide**: Live documentation at `/styleguide`

## 📦 Quick Start

### Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/webschedulr-ci4.git
   cd webschedulr-ci4
   ```

2. **Install dependencies**
   ```bash
   # PHP dependencies
   composer install
   
   # Node.js dependencies
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Build assets and start development**
   ```bash
   # Build frontend assets
   npm run build
   
   # Start development server
   php spark serve
   ```

5. **Access the application**
   - Main app: `http://localhost:8080`
   - Setup wizard: `http://localhost:8080/setup`
   - Style guide: `http://localhost:8080/styleguide`
   - Tailwind test: `http://localhost:8080/tw`

### Development Commands

```bash
# Watch and compile assets during development
npm run dev

# Build optimized assets for production
npm run build

# Create deployment package
npm run package

# Preview production build
npm run preview
```

## 🌐 Deployment

### Zero-Configuration Deployment

@webSchedulr is designed for hassle-free deployment to any hosting provider:

1. **Build deployment package**
   ```bash
   npm run build  # This creates the webschedulr-deploy folder
   ```

2. **Upload to hosting provider**
   - Upload entire contents of `webschedulr-deploy/` folder
   - Point domain/subdomain to the `public/` directory

3. **Configure environment**
   - Update `.env` file with production database credentials
   - Set `writable/` folder permissions to 755

4. **You're live!** - Zero server configuration needed

### Hosting Compatibility

✅ **Shared Hosting**: GoDaddy, Bluehost, HostGator, cPanel hosting  
✅ **VPS/Cloud**: DigitalOcean, AWS, Linode, Vultr  
✅ **Managed Hosting**: Cloudways, WP Engine, SiteGround  
✅ **Subdomain/Subfolder**: Flexible deployment paths  

**Requirements**: PHP 7.4+, Apache with mod_rewrite (or Nginx equivalent)

### Deployment Options

#### Subdomain Deployment
```
subdomain.yourdomain.com/
├── app/
├── system/
├── public/          ← Point subdomain here
└── writable/
```

#### Subfolder Deployment
```
yourdomain.com/scheduler/
├── app/
├── system/
├── public/          ← Point folder here
└── writable/
```

## 🎨 Design System

### Component Usage

#### Buttons
```php
<?= ui_button('Primary Action', '/action', 'primary') ?>
<?= ui_button('Secondary Action', '/cancel', 'secondary') ?>
```

#### Cards
```php
<?= ui_card(
    'Card Title',
    '<p>Card content goes here</p>',
    '<p>Optional footer</p>'
) ?>
```

#### Alerts
```php
<?= ui_alert('Success message', 'success', 'Well done!') ?>
<?= ui_alert('Important info', 'info', 'Notice') ?>
<?= ui_alert('Warning message', 'warning', 'Attention') ?>
<?= ui_alert('Error message', 'error', 'Oops!') ?>
```

#### Layout Components
```php
<?= $this->extend('components/layout') ?>
<?= $this->section('content') ?>
<div class="content-wrapper">
    <div class="content-main">
        <!-- Your content here -->
    </div>
</div>
<?= $this->endSection() ?>
```

### CSS Component Classes

```css
/* Layout */
.page-container     /* Container with responsive padding */
.content-wrapper    /* Centering wrapper */
.content-main       /* Main content area with max-width */

/* Components */
.btn-primary        /* Primary button styling */
.btn-secondary      /* Secondary button styling */
.card               /* Card container */
.card-header        /* Card header with title */
.card-body          /* Card content area */
.alert              /* Base alert styling */
.alert-info         /* Info alert variant */
.form-input         /* Standardized form inputs */

/* Scheduler States */
.time-slot-available    /* Available time slots */
.time-slot-selected     /* User-selected slots */
.time-slot-booked      /* Unavailable slots */
.time-slot-past        /* Past time slots */
```

## 📁 Project Structure

```
WebSchedulr_ci4/
├── app/                          # CodeIgniter 4 application
│   ├── Controllers/              # Request handlers
│   │   ├── BaseController.php    # Base with UI helper loading
│   │   ├── Setup.php            # Setup wizard
│   │   └── Styleguide.php       # Design system docs
│   ├── Helpers/                 # Custom helper functions
│   │   └── ui_helper.php        # UI component helpers
│   └── Views/                   # Template files
│       ├── components/          # Reusable view components
│       ├── styleguide/          # Design system documentation
│       └── *.php               # Page templates
├── resources/                   # Frontend assets
│   ├── js/app.js               # Main JavaScript
│   └── scss/
│       ├── app.scss            # Main SCSS file
│       └── components.scss     # Component definitions
├── public/                     # Web-accessible files
│   ├── build/assets/           # Compiled assets
│   └── index.php              # Application entry point
├── scripts/package.js          # Deployment packaging
├── vite.config.js             # Build configuration
├── tailwind.config.js         # Tailwind configuration
└── mastercontext.md           # Complete project documentation
```

## 🛠️ Development Guidelines

### Adding New Components

1. **Define SCSS component**
   ```scss
   // resources/scss/components.scss
   @layer components {
     .my-component {
       @apply bg-white rounded-lg shadow-sm border;
     }
   }
   ```

2. **Create PHP helper**
   ```php
   // app/Helpers/ui_helper.php
   function ui_my_component($content) {
       return "<div class=\"my-component\">{$content}</div>";
   }
   ```

3. **Document in style guide**
   ```php
   // app/Views/styleguide/components.php
   // Add usage examples and documentation
   ```

### Code Standards

- **PHP**: Follow CodeIgniter 4 coding standards
- **CSS**: Use Tailwind utilities, custom components for reuse
- **JavaScript**: Modern ES6+, event-driven architecture
- **Naming**: PascalCase controllers, camelCase methods, kebab-case CSS

## 🔒 Security Features

- **CSRF Protection**: Enabled by default in CodeIgniter 4
- **XSS Filtering**: Built-in input sanitization
- **Secure Headers**: Security headers in .htaccess
- **Input Validation**: Form validation framework
- **Safe Deployment**: Sensitive files outside web root

## 📚 Documentation

- **Master Context**: Complete project overview in `mastercontext.md`
- **Style Guide**: Live documentation at `/styleguide`
- **Deployment Guide**: Instructions in `DEPLOY-README.md`
- **API Documentation**: Controller and helper function docs

## 🐛 Troubleshooting

### Common Issues

**Build Errors**
```bash
# Module not found
npm install

# Tailwind classes not working
# Check tailwind.config.js content paths

# SCSS compilation errors
# Verify @import paths in app.scss
```

**Deployment Issues**
```bash
# 404 errors - Check .htaccess and mod_rewrite
# Assets not loading - Verify baseURL in App.php
# Permission errors - Set writable/ to 755
```

**Development Issues**
```bash
# Routes not working - Check Routes.php syntax
# Helper functions undefined - Verify BaseController helper loading
# Views not rendering - Check extend/section syntax
```

## 📈 Roadmap

### Current Status ✅
- [x] CodeIgniter 4 foundation
- [x] Tailwind CSS design system
- [x] Component library and helpers
- [x] Zero-config deployment
- [x] Style guide documentation

### In Progress 🔄
- [ ] Scheduler components
- [ ] Time slot management
- [ ] Calendar views
- [ ] Appointment booking

### Planned Features 📋
- [ ] Database schema
- [ ] User authentication
- [ ] Email notifications
- [ ] Calendar integrations
- [ ] Multi-timezone support

## 🤝 Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

## � Documentation

Comprehensive documentation is organized in the `/docs` directory:

### 🏗️ Architecture
- **[Master Context](docs/architecture/mastercontext.md)** - Complete technical overview
- **[Role-Based System](docs/architecture/ROLE_BASED_SYSTEM.md)** - User permissions and access control
- **[Implementation Plan](docs/architecture/IMPLEMENTATION-PLAN.md)** - Development roadmap

### 🔧 Configuration  
- **[Settings Implementation](docs/configuration/SETTINGS_IMPLEMENTATION_VERIFIED.md)** - Settings system guide
- **[Contact Fields](docs/configuration/SETTINGS_CONTACT_FIELDS.md)** - Contact information setup
- **[Localization Updates](docs/configuration/LOCALIZATION_SETTINGS_UPDATE.md)** - Multi-language support

### 🚀 Deployment
- **[Merge Summary](docs/deployment/MERGE_SUMMARY.md)** - Branch consolidation history
- **[Production Guide](docs/deployment/PRODUCTION_FIX_GUIDE.md)** - Production deployment steps
- **[ZIP Deployment](docs/deployment/ZIP-DEPLOYMENT-SUMMARY.md)** - Package deployment method

### 🛡️ Security
- **[Implementation Guide](docs/security/SECURITY_IMPLEMENTATION_GUIDE.md)** - Security setup instructions
- **[Security Status](docs/security/SECURITY_STATUS.md)** - Current security measures
- **[Compliance](docs/compliance/)** - Security compliance documentation

### 🔧 Technical
- **[SPA Settings Fix](docs/technical/SPA_SETTINGS_FIX.md)** - Single-page app configuration
- **[Commands Reference](docs/technical/command.md)** - CLI commands and usage
- **[Icon Display Fix](docs/technical/ICON-DISPLAY-FIX.md)** - UI icon troubleshooting

### 📋 Project Files
- **[Requirements](docs/REQUIREMENTS.md)** - System requirements and specifications
- **[Setup Workflow](docs/SETUP-WORKFLOW-COMPLETE.md)** - Complete setup guide
- **[Notes](docs/Notes.md)** - Development notes and changelog

## �📄 License

This project is licensed under the GPL-3.0 License - see the [LICENSE](LICENSE) file for details.

## 🔗 Links

- **Website**: [https://webschedulr.co.za](https://webschedulr.co.za)
- **Documentation**: See `mastercontext.md` for complete technical details
- **Style Guide**: Visit `/styleguide` in your local installation
- **Support**: Contact [nilo.cara@frontend.co.za](mailto:nilo.cara@frontend.co.za)

---

**Made with ❤️ by Nilo Cara**  
*Building modern, accessible web applications*








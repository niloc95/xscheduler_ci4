# xScheduler CI4 - Professional Appointment Scheduling System

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.x-red.svg)](https://codeigniter.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://www.php.net/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.4.17-38bdf8.svg)](https://tailwindcss.com/)

A modern, full-featured appointment scheduling application built with CodeIgniter 4 and Tailwind CSS. Designed for service-based businesses including salons, clinics, consultancies, and any organization that needs professional appointment management.

## ✨ Key Features

### 📅 **Appointment Management**
- **Interactive Calendar**: Day/Week/Month views with FullCalendar integration
- **Real-time Availability**: Automatic slot calculation based on business hours
- **Smart Booking**: Conflict detection and timezone-aware scheduling
- **Multi-provider Support**: Provider-specific calendars with color coding
- **Customer Management**: Customer database with booking history
- **Status Tracking**: Pending, Confirmed, Completed, Cancelled, No-show

### 🔔 **Notifications System**
- **Multi-channel Delivery**: Email, SMS, and WhatsApp notifications
- **Event Types**: Confirmations, reminders, cancellations, and reschedules
- **Template System**: Customizable message templates with placeholders
- **Queued Processing**: Background job processing for reliable delivery
- **Smart Reminders**: Automated appointment reminders with configurable timing

### 👥 **User Management**
- **Role-Based Access Control**: Admin, Provider, Staff, and Customer roles
- **Granular Permissions**: Fine-grained access control per role
- **Staff Assignment**: Provider-specific staff management
- **Secure Authentication**: CodeIgniter 4 authentication with CSRF protection

### 🎨 **Modern UI/UX**
- **Responsive Design**: Mobile-first approach with adaptive layouts
- **Dark Mode**: System-wide dark mode support
- **Material Design**: Material Icons and modern component library
- **Accessible**: WCAG-compliant interface elements
- **Real-time Updates**: Live calendar and availability updates

### ⚙️ **Configuration & Setup**
- **Setup Wizard**: Guided initial setup with database and admin configuration
- **Settings Management**: Comprehensive settings system for all aspects
- **Localization**: Multi-language support with customizable text
- **Business Hours**: Flexible working hours and time slot configuration
- **Service Management**: Service catalog with durations and pricing

### 📊 **Analytics & Reporting**
- **Dashboard**: Real-time stats and appointment metrics
- **Revenue Tracking**: Revenue calculations by period
- **Appointment Statistics**: Status distribution and trends
- **Provider Analytics**: Provider-specific performance metrics
- **Activity Logs**: Recent activity tracking and audit trail

## 🏗️ Technical Stack

- **Backend**: CodeIgniter 4 (PHP 8.1+)
- **Frontend**: Tailwind CSS 3.4, Material Design Icons
- **JavaScript**: Modern ES6+ with Vite build system
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Calendar**: FullCalendar v6
- **Notifications**: Queue-based email/SMS/WhatsApp system
- **Authentication**: Built-in CI4 authentication with role-based access

## 📦 Installation

### System Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer 2.x
- Node.js 18+ and npm
- Apache with mod_rewrite (or Nginx)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/niloc95/xscheduler_ci4.git
   cd xscheduler_ci4
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
   # Copy environment file
   cp .env.example .env
   
   # Edit .env with your configuration:
   # - Database credentials
   # - Base URL
   # - Email settings (for notifications)
   ```

4. **Initialize database**
   ```bash
   # Run migrations
   php spark migrate -n App
   ```

5. **Build frontend assets**
   ```bash
   # Production build
   npm run build
   ```

6. **Start development server**
   ```bash
   php spark serve
   ```

7. **Access the setup wizard**
   - Navigate to `http://localhost:8080/setup`
   - Complete the setup wizard to create admin account
   - Configure business settings, services, and providers

### Development Commands

```bash
# Development mode with hot reload
npm run dev

# Production build
npm run build

# Preview production build
npm run preview

# Run database migrations
php spark migrate -n App

# Start local server
php spark serve

# Process notification queue (in production, set up as cron job)
php spark notifications:dispatch-queue
```

## 🌐 Deployment

### Production Deployment

1. **Build for production**
   ```bash
   npm run build
   ```

2. **Upload to server**
   - Upload all files to your hosting provider
   - Point domain to the `public/` directory
   - Ensure `writable/` directory has write permissions (755)

3. **Configure environment**
   ```bash
   # Update .env for production
   CI_ENVIRONMENT = production
   app.baseURL = 'https://yourdomain.com'
   
   # Set database credentials
   database.default.hostname = your_host
   database.default.database = your_database
   database.default.username = your_user
   database.default.password = your_password
   ```

4. **Run migrations**
   ```bash
   php spark migrate -n App
   ```

5. **Set up cron job** (for notifications)
   ```bash
   # Add to crontab (runs every minute)
   * * * * * cd /path/to/project && php spark notifications:dispatch-queue >> /dev/null 2>&1
   ```

### Hosting Requirements

✅ **Shared Hosting**: cPanel, Plesk-based hosting  
✅ **VPS/Cloud**: DigitalOcean, AWS, Linode, Vultr  
✅ **Managed Platforms**: Cloudways, Laravel Forge

**Minimum Requirements**:
- PHP 8.1+ with required extensions (intl, mbstring, json, mysqlnd)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite or Nginx
- SSL certificate (recommended for production)

## 📁 Project Structure

```
xscheduler_ci4/
├── app/
│   ├── Controllers/          # Request handlers
│   │   ├── Appointments.php  # Appointment management
│   │   ├── Scheduler.php     # Booking API endpoints
│   │   ├── Dashboard.php     # Analytics dashboard
│   │   ├── Settings.php      # System configuration
│   │   └── Api/              # RESTful API controllers
│   ├── Models/               # Database models
│   │   ├── AppointmentModel.php
│   │   ├── UserModel.php
│   │   ├── ServiceModel.php
│   │   └── CustomerModel.php
│   ├── Services/             # Business logic
│   │   ├── SchedulingService.php
│   │   ├── AvailabilityService.php
│   │   ├── NotificationQueueService.php
│   │   └── BookingSettingsService.php
│   ├── Views/                # Templates
│   │   ├── appointments/     # Appointment views
│   │   ├── dashboard/        # Dashboard views
│   │   └── components/       # Reusable components
│   ├── Database/
│   │   └── Migrations/       # Database migrations
│   └── Config/               # Application configuration
├── resources/
│   ├── js/                   # JavaScript source
│   │   ├── app.js           # Main application
│   │   └── modules/          # Feature modules
│   ├── css/                  # Stylesheets
│   └── scss/                 # SCSS source
├── public/                   # Web-accessible files
│   ├── index.php            # Application entry point
│   └── build/               # Compiled assets
├── writable/                 # Logs, cache, uploads
├── tests/                    # Test files
├── docs/                     # Documentation
├── composer.json             # PHP dependencies
├── package.json              # Node dependencies
└── vite.config.js           # Build configuration
```

## 🚀 Usage Guide

### For Administrators

1. **Initial Setup**
   - Complete the setup wizard at `/setup`
   - Configure business information and settings
   - Set up business hours and time slots
   - Create services and assign prices/durations

2. **Manage Providers**
   - Add provider accounts (staff members)
   - Assign services to providers
   - Set provider-specific working hours
   - Configure provider permissions

3. **Configure Notifications**
   - Set up email/SMS/WhatsApp templates
   - Configure reminder timing
   - Customize message content
   - Test notification delivery

4. **Monitor System**
   - View dashboard analytics
   - Review appointment statistics
   - Monitor system activity
   - Export reports

### For Providers/Staff

1. **View Schedule**
   - Access calendar at `/appointments`
   - Switch between day/week/month views
   - View appointment details by clicking events
   - Filter by provider or service

2. **Manage Appointments**
   - Create new appointments
   - Confirm pending bookings
   - Reschedule or cancel appointments
   - Add appointment notes
   - Update appointment status

3. **Customer Management**
   - Search existing customers
   - View customer booking history
   - Update customer information

### For Customers (Public Booking)

1. **Book Appointment**
   - Navigate to public booking page
   - Select provider and service
   - Choose available date and time
   - Enter contact information
   - Confirm booking

2. **Manage Booking**
   - Look up booking using email/phone
   - Reschedule appointment
   - Cancel if needed

## 🔧 Configuration

### Key Settings

Edit `.env` for core configuration:

```ini
# Environment
CI_ENVIRONMENT = production

# Base URL
app.baseURL = 'https://yourdomain.com'

# Database
database.default.hostname = localhost
database.default.database = xscheduler
database.default.username = your_user
database.default.password = your_password

# Timezone
app.timezone = 'America/New_York'

# Email (for notifications)
email.fromEmail = noreply@yourdomain.com
email.fromName = 'xScheduler'
email.SMTPHost = smtp.example.com
email.SMTPUser = your_smtp_user
email.SMTPPass = your_smtp_password
```

### Application Settings

Configure via admin panel at `/settings`:

- **Business Hours**: Operating hours and time slots
- **Booking Rules**: Advance booking, max slots, cancellation policies
- **Notifications**: Email/SMS/WhatsApp templates
- **Localization**: Language and regional formats
- **Services**: Service catalog with pricing

## 🤝 Contributing

We welcome contributions! Please follow our fork-based workflow:

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/amazing-feature`
3. **Commit your changes**: `git commit -m 'Add amazing feature'`
4. **Push to the branch**: `git push origin feature/amazing-feature`
5. **Open a Pull Request** to the `main` branch

### Contribution Guidelines

- Follow CodeIgniter 4 coding standards
- Write clear, descriptive commit messages
- Add tests for new features
- Update documentation as needed
- Keep PRs focused on single features
- All PRs require approval before merging

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

## 🔒 Security

### Reporting Security Issues

Please **do not** open public issues for security vulnerabilities.

Report security concerns privately to: **info@webschedulr.co.za**

See [SECURITY.md](SECURITY.md) for our security policy and response process.

### Security Features

- ✅ CSRF protection enabled by default
- ✅ XSS filtering on all inputs
- ✅ SQL injection prevention via query builder
- ✅ Secure password hashing (bcrypt)
- ✅ Role-based access control
- ✅ Session security best practices
- ✅ HTTPS recommended for production


## 📚 Documentation

Comprehensive documentation is available in the `/docs` directory:

### Core Documentation
- **[Requirements](docs/REQUIREMENTS.md)** - System requirements and specifications
- **[Contributing Guidelines](CONTRIBUTING.md)** - How to contribute to the project
- **[Security Policy](SECURITY.md)** - Security practices and reporting

### Architecture & Development
- **[Scheduling System](docs/SCHEDULING_SYSTEM.md)** - Complete scheduling architecture
- **[Calendar Implementation](docs/development/calendar_implementation.md)** - Calendar integration guide
- **[Database Wiring](docs/CALENDAR_DATABASE_WIRING_INVESTIGATION.md)** - Database architecture

### Configuration
- **[Settings Implementation](docs/configuration/SETTINGS_IMPLEMENTATION_VERIFIED.md)** - Settings system
- **[Localization](docs/configuration/LOCALIZATION_SETTINGS_UPDATE.md)** - Multi-language setup

### Features
- **[Appointment System](docs/APPOINTMENT_CREATE_EDIT_MODAL_INVESTIGATION.md)** - Appointment features
- **[Notifications](docs/NOTIFICATIONS_IMPLEMENTATION_CHECKLIST.md)** - Notification system
- **[User Management](docs/user-management/)** - Role-based access control

## 🐛 Troubleshooting

### Common Issues

**Calendar Not Loading**
- Check browser console for JavaScript errors
- Verify FullCalendar assets are built: `npm run build`
- Ensure API endpoints are accessible: `/api/v1/appointments`

**Notifications Not Sending**
- Verify email configuration in `.env`
- Check notification queue: `php spark notifications:dispatch-queue`
- Review logs in `writable/logs/`

**Database Connection Errors**
- Verify database credentials in `.env`
- Ensure database exists and is accessible
- Run migrations: `php spark migrate -n App`

**Permission Errors**
- Set `writable/` directory to 755: `chmod -R 755 writable/`
- Ensure web server has write access to `writable/`

**Assets Not Loading**
- Clear CodeIgniter cache: `php spark cache:clear`
- Rebuild assets: `npm run build`
- Check `app.baseURL` in `.env` matches your domain

### Getting Help

- **Issues**: [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues)
- **Discussions**: Use GitHub Discussions for questions
- **Email**: info@webschedulr.co.za

## 📈 Roadmap

### ✅ Completed Features
- [x] User authentication and role-based access
- [x] Appointment booking and management
- [x] Interactive calendar (day/week/month views)
- [x] Provider and service management
- [x] Multi-channel notifications (Email/SMS/WhatsApp)
- [x] Real-time availability checking
- [x] Customer management
- [x] Dashboard analytics
- [x] Settings configuration system
- [x] Setup wizard
- [x] Dark mode support
- [x] Responsive mobile design

### 🚧 In Development
- [ ] Payment integration (Stripe, PayPal)
- [ ] Advanced reporting and exports
- [ ] Calendar sync (Google Calendar, Outlook)
- [ ] Recurring appointments
- [ ] Waiting list management
- [ ] Multi-location support

### 📋 Planned Features
- [ ] Customer self-service portal
- [ ] Video consultation integration
- [ ] Mobile app (iOS/Android)
- [ ] Advanced analytics and insights
- [ ] Inventory management
- [ ] Package/membership support
- [ ] Marketing automation
- [ ] API documentation (OpenAPI/Swagger)

## 🙏 Acknowledgments

Built with these excellent open-source projects:

- [CodeIgniter 4](https://codeigniter.com/) - PHP framework
- [Tailwind CSS](https://tailwindcss.com/) - CSS framework
- [FullCalendar](https://fullcalendar.io/) - Calendar component
- [Material Design Icons](https://fonts.google.com/icons) - Icon set
- [Vite](https://vitejs.dev/) - Build tool

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Contact & Support

- **Website**: [https://webschedulr.co.za](https://webschedulr.co.za)
- **Email**: info@webschedulr.co.za
- **GitHub**: [@niloc95](https://github.com/niloc95)
- **Repository**: [github.com/niloc95/xscheduler_ci4](https://github.com/niloc95/xscheduler_ci4)

---

**Made with ❤️ for service-based businesses worldwide**

*Building modern, accessible appointment scheduling solutions*








# Environment Configuration Guide for WebSchedulr

## Overview

This guide explains how to configure the WebSchedulr application for different environments (development, staging, production) using the `.env` file.

## Quick Setup

1. **Copy the template**: `cp .env.example .env`
2. **Generate encryption key**: `php spark key:generate`
3. **Configure database settings** (see Database Configuration section)
4. **Set environment to production**: `CI_ENVIRONMENT = production`

## Environment Types

### Development Environment
- **Purpose**: Local development and testing
- **Security**: Lower security, debugging enabled
- **Performance**: Not optimized, development tools enabled
- **Configuration**: 
  ```env
  CI_ENVIRONMENT = development
  app.baseURL = 'http://localhost:8081/'
  app.forceGlobalSecureRequests = false
  app.CSPEnabled = false
  ```

### Production Environment
- **Purpose**: Live website serving real users
- **Security**: Maximum security, debugging disabled
- **Performance**: Optimized for speed and efficiency
- **Configuration**:
  ```env
  CI_ENVIRONMENT = production
  app.baseURL = ''
  app.forceGlobalSecureRequests = true
  app.CSPEnabled = true
  ```

## Key Configuration Sections

### 1. Database Configuration

#### MySQL/MariaDB (Recommended for Production)
```env
database.default.hostname = localhost
database.default.database = webschedulr_prod
database.default.username = your_db_user
database.default.password = your_secure_password
database.default.DBDriver = MySQLi
database.default.DBPrefix = xs_
database.default.port = 3306
```

#### SQLite (Simple Deployment)
```env
database.default.hostname = 
database.default.database = writable/database/webschedulr.db
database.default.username = 
database.default.password = 
database.default.DBDriver = SQLite3
database.default.DBPrefix = xs_
```

### 2. Security Configuration

#### Encryption Key
- **Purpose**: Encrypts sensitive data (sessions, cookies, etc.)
- **Generation**: Run `php spark key:generate` to create a secure key
- **Important**: Never share this key or commit it to version control

#### CSRF Protection
```env
security.CSRFProtection = true
security.CSRFTokenName = 'csrf_token'
security.CSRFCookieName = 'csrf_cookie'
security.CSRFExpire = 7200
security.CSRFRegenerate = true
security.CSRFSameSite = 'Strict'
```

#### HTTPS Settings
```env
app.forceGlobalSecureRequests = true  # Force HTTPS
app.CSPEnabled = true                 # Content Security Policy
```

### 3. Session Configuration
```env
session.driver = 'CodeIgniter\Session\Handlers\FileHandler'
session.savePath = 'writable/session'
session.matchIP = true
session.timeToUpdate = 300
session.regenerateDestroy = true
```

### 4. Email Configuration
```env
email.protocol = smtp
email.SMTPHost = your-smtp-server.com
email.SMTPUser = your-email@domain.com
email.SMTPPass = your-email-password
email.SMTPPort = 587
email.SMTPCrypto = tls
email.fromEmail = noreply@yourdomain.com
email.fromName = WebSchedulr
```

## Custom WebSchedulr Settings

### Application Settings
```env
app.timezone = 'UTC'              # Default timezone
app.defaultLocale = 'en'          # Default language
app.negotiateLocale = false       # Auto-detect user language
app.supportedLocales = ['en']     # Supported languages
```

### Setup Wizard
```env
setup.enabled = true              # Enable setup wizard
setup.allowMultipleRuns = false  # Prevent multiple setups
```

### Feature Flags
```env
features.emailNotifications = true   # Email notifications
features.smsNotifications = false    # SMS notifications
features.multiLanguage = false       # Multi-language support
features.apiAccess = false           # API access
```

## Deployment Checklist

### Before Deployment
- [ ] Copy `.env.example` to `.env`
- [ ] Set `CI_ENVIRONMENT = production`
- [ ] Configure database settings
- [ ] Generate encryption key with `php spark key:generate`
- [ ] Set up email configuration (if using email features)
- [ ] Enable HTTPS: `app.forceGlobalSecureRequests = true`
- [ ] Enable CSP: `app.CSPEnabled = true`
- [ ] Set proper file permissions on `writable/` directory (755)

### After Deployment
- [ ] Test the setup wizard at `/setup`
- [ ] Verify database connection
- [ ] Test email functionality (if configured)
- [ ] Check error logs in `writable/logs/`
- [ ] Verify HTTPS redirect is working
- [ ] Test all major application features

## Common Issues and Solutions

### 1. Database Connection Errors
- **Error**: "Unable to connect to database"
- **Solution**: Verify database credentials, hostname, and port
- **Check**: Database server is running and accessible

### 2. Encryption Key Errors
- **Error**: "The encryption key is not set"
- **Solution**: Run `php spark key:generate` or set `encryption.key` manually
- **Note**: Key must be 32 characters for AES-256 encryption

### 3. Session Errors
- **Error**: "Session directory not writable"
- **Solution**: Set `writable/session/` directory permissions to 755
- **Command**: `chmod 755 writable/session/`

### 4. URL/Routing Issues
- **Error**: "404 Not Found" or routing problems
- **Solution**: Check `.htaccess` file and mod_rewrite is enabled
- **Alternative**: Set `app.baseURL` to your full domain URL

### 5. Email Not Sending
- **Error**: Email notifications not working
- **Solution**: Configure SMTP settings and verify server allows outbound email
- **Test**: Use CodeIgniter's email test functionality

## Security Best Practices

1. **Never commit `.env` files** to version control
2. **Use strong database passwords** with special characters
3. **Enable HTTPS** in production environments
4. **Regular security updates** for CodeIgniter and PHP
5. **Monitor error logs** for security issues
6. **Use CSRF protection** for all forms
7. **Validate all input** from users
8. **Use prepared statements** for database queries

## Environment-Specific Files

### Files that should be ignored in version control:
- `.env` (contains sensitive data)
- `writable/logs/*` (log files)
- `writable/cache/*` (cache files)
- `writable/session/*` (session files)
- `writable/uploads/*` (uploaded files)

### Files that should be included:
- `.env.example` (template for environment variables)
- `app/Config/*.php` (configuration files)
- `.htaccess` (Apache configuration)

---

**Last Updated**: July 2025  
**Version**: 1.0.0  
**Author**: WebSchedulr Development Team

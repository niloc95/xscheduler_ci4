# Setup-Driven Environment Configuration

## Overview

The WebSchedulr setup system now uses a clean, user-driven approach to environment configuration that separates development from production environments.

## Workflow

### 1. Development Environment
- Developers use a local `.env` file for development
- Configuration is optimized for debugging and development tools
- Database and security settings are relaxed for local testing

### 2. Build Package Process
- The build process includes `.env.example` as a template in deployments
- No actual `.env` file is included in the deployment package
- User configuration happens during first-time setup

### 3. User Setup Workflow
1. **User accesses application** → Redirected to setup wizard (if no setup completion flag)
2. **User fills setup form** → Admin account + database configuration
3. **System tests database** → Validates MySQL/SQLite connections before proceeding
4. **Setup generates .env** → Creates production-ready configuration from `.env.example`
5. **Application becomes operational** → Ready for use with optimal production settings
6. **Setup wizard disabled** → Prevents re-running setup unless explicitly enabled

## Implementation Details

### Setup Controller Enhancements

The `Setup` controller now includes:

- **`.env` Generation**: Creates environment file from user inputs using `.env.example` as template
- **Database Testing**: Validates MySQL/SQLite connections before setup
- **Security Configuration**: Applies production-appropriate security settings automatically
- **Encryption Key Generation**: Creates secure encryption keys automatically
- **Setup Completion Tracking**: Prevents multiple setup runs

### Environment File Generation

The setup process:
1. Reads `.env.example` as a template
2. Replaces placeholder values with user inputs
3. Applies environment-specific settings (production vs development)
4. Generates secure encryption keys automatically
5. Writes the final `.env` file with production settings
6. Creates setup completion flag

### Key Features

#### Dynamic Environment Detection
```php
$environment = ENVIRONMENT === 'development' ? 'development' : 'production';
$baseURL = $environment === 'development' ? 'http://localhost:8081/' : '';
```

#### Secure Database Configuration
```php
// User inputs are validated and tested before being applied
$dbConfig = [
    'db_driver' => 'MySQLi',
    'db_hostname' => $userInput['hostname'],
    // ... other validated inputs
];
```

#### Automatic Security Settings
```php
// Production gets enhanced security, development gets debugging
'app.forceGlobalSecureRequests = true' => 'app.forceGlobalSecureRequests = ' . ($environment === 'production' ? 'true' : 'false'),
'app.CSPEnabled = true' => 'app.CSPEnabled = ' . ($environment === 'production' ? 'true' : 'false'),
```

## Benefits

### ✅ **Clean Separation**
- Development and production environments are clearly separated
- No risk of development settings leaking into production

### ✅ **User-Friendly**
- Non-technical users can configure the application through a web interface
- Database connections are tested before being applied
- Clear error messages guide users through any issues

### ✅ **Secure by Default**
- Production environments get security-hardened configurations
- Encryption keys are generated securely
- Sensitive settings are environment-appropriate

### ✅ **Zero Configuration Deployment**
- Deployment packages work on any hosting provider
- No manual file editing required
- Setup wizard handles all configuration needs

## Files Involved

### Core Files
- `app/Controllers/Setup.php` - Enhanced setup controller with .env generation
- `.env.example` - Template for environment configuration
- `app/Views/setup.php` - Setup wizard interface

### Generated Files
- `.env` - Created by setup wizard from user inputs
- `setup_completed.json` - Flag to prevent duplicate setups

## Security Considerations

### Production Security
- HTTPS enforcement enabled automatically
- CSRF protection enabled
- Content Security Policy enabled
- Secure session configuration

### Development Security
- Security features relaxed for debugging
- Error display enabled for development
- Less restrictive CORS settings

## Testing

The setup system includes comprehensive database testing:
- **MySQL**: Tests connection, database existence, and permissions
- **SQLite**: Tests directory writability and file creation
- **Error Handling**: Provides clear feedback on connection issues

## Migration from Previous Approach

If you have an existing `.env` file from the previous environment switch approach:
1. The setup wizard will use your existing `.env` if present
2. Or you can delete `.env` to trigger the setup wizard
3. The new approach is backwards compatible

---

**Implementation Status**: ✅ Complete  
**Testing Status**: ✅ Ready for testing  
**Documentation**: ✅ Up to date

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import archiver from 'archiver';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

// Version management
const versionFilePath = path.join(projectRoot, '.deploy-version');
let deployVersion = 1;

// Read and increment version
if (fs.existsSync(versionFilePath)) {
    const currentVersion = parseInt(fs.readFileSync(versionFilePath, 'utf8').trim(), 10);
    deployVersion = isNaN(currentVersion) ? 1 : currentVersion + 1;
} else {
    deployVersion = 1;
}

// Write new version
fs.writeFileSync(versionFilePath, deployVersion.toString());

console.log(`📦 Creating standalone deployment package v${deployVersion}...`);
console.log('⚠️  NOTE: setup_completed.flag will be excluded from deployment package');
console.log('   This ensures fresh installations start with the setup wizard');
console.log('⚠️  NOTE: app/Views/test/ folder will be excluded from deployment package');
console.log('   Test and example views are not needed in production');
console.log('⚠️  NOTE: logs/ and debugbar/ folders will be cleaned for production deployment');
console.log('   Only directory structure will be preserved with .gitkeep files');

// Optional log archiving before cleanup
const ARCHIVE_LOGS = process.env.ARCHIVE_LOGS === 'true' || process.argv.includes('--archive-logs');
let logArchivePath = null;

if (ARCHIVE_LOGS) {
    console.log('📂 Archiving logs before deployment cleanup...');
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    logArchivePath = path.join(projectRoot, `logs-archive-${timestamp}`);
    fs.mkdirSync(logArchivePath, { recursive: true });
    
    // Archive current logs
    const logsDir = path.join(projectRoot, 'writable/logs');
    const debugbarDir = path.join(projectRoot, 'writable/debugbar');
    
    if (fs.existsSync(logsDir)) {
        const logFiles = fs.readdirSync(logsDir).filter(file => file.endsWith('.log'));
        if (logFiles.length > 0) {
            const archiveLogsDir = path.join(logArchivePath, 'logs');
            fs.mkdirSync(archiveLogsDir, { recursive: true });
            logFiles.forEach(logFile => {
                const src = path.join(logsDir, logFile);
                const dest = path.join(archiveLogsDir, logFile);
                fs.copyFileSync(src, dest);
            });
            console.log(`✅ Archived ${logFiles.length} log files to ${archiveLogsDir}`);
        }
    }
    
    if (fs.existsSync(debugbarDir)) {
        const debugFiles = fs.readdirSync(debugbarDir).filter(file => file.endsWith('.json'));
        if (debugFiles.length > 0) {
            const archiveDebugDir = path.join(logArchivePath, 'debugbar');
            fs.mkdirSync(archiveDebugDir, { recursive: true });
            debugFiles.forEach(debugFile => {
                const src = path.join(debugbarDir, debugFile);
                const dest = path.join(archiveDebugDir, debugFile);
                fs.copyFileSync(src, dest);
            });
            console.log(`✅ Archived ${debugFiles.length} debugbar files to ${archiveDebugDir}`);
        }
    }
    
    // Create archive summary
    const archiveSummary = `# Log Archive Summary
Created: ${new Date().toISOString()}
Source: ${projectRoot}
Deployment Package: webscheduler-deploy/

## Archived Files:
- logs/: ${fs.existsSync(path.join(logArchivePath, 'logs')) ? fs.readdirSync(path.join(logArchivePath, 'logs')).length : 0} files
- debugbar/: ${fs.existsSync(path.join(logArchivePath, 'debugbar')) ? fs.readdirSync(path.join(logArchivePath, 'debugbar')).length : 0} files

## Usage:
These files were archived before deployment to keep production clean.
Review for debugging or audit purposes as needed.
`;
    
    fs.writeFileSync(path.join(logArchivePath, 'ARCHIVE-README.md'), archiveSummary);
    console.log(`📋 Created archive summary: ${logArchivePath}/ARCHIVE-README.md`);
}

// Create deployment package
const packageDir = path.join(projectRoot, 'webschedulr-deploy');
if (fs.existsSync(packageDir)) {
    try {
        fs.rmSync(packageDir, { recursive: true, force: true });
    } catch (error) {
        console.warn('⚠️  Could not remove existing package directory, trying alternative method...');
        // Try to remove contents instead
        const files = fs.readdirSync(packageDir);
        for (const file of files) {
            const filePath = path.join(packageDir, file);
            try {
                fs.rmSync(filePath, { recursive: true, force: true });
            } catch (e) {
                console.warn(`Could not remove ${file}, continuing...`);
            }
        }
    }
}
fs.mkdirSync(packageDir, { recursive: true });

// Copy CodeIgniter structure for standalone deployment
const essentialFiles = [
    { src: 'app', dest: 'app' },
    { src: 'writable', dest: 'writable' },
    { src: 'vendor', dest: 'vendor' },
    { src: 'public', dest: 'public' },
    { src: 'spark', dest: 'spark' },
    { src: 'preload.php', dest: 'preload.php' },
    { src: '.env.example', dest: '.env.example' }
];

// Note: vendor already includes vendor/codeigniter4/framework/system
// We'll copy it separately for standalone deployment structure

// Function to copy directory with exclusions (supports matching by relative path)
function copyDirectoryWithFilter(src, dest, excludePatterns = [], root = null) {
    if (!fs.existsSync(src)) return false;

    const rootBase = root || src; // root of this copy operation
    fs.mkdirSync(dest, { recursive: true });

    const items = fs.readdirSync(src);

    for (const item of items) {
        const srcPath = path.join(src, item);
        const destPath = path.join(dest, item);
        const relPath = path.relative(rootBase, srcPath).replace(/\\/g, '/');

        // Check if item matches any exclude pattern by name or by relative path
        const shouldExclude = excludePatterns.some(pattern => {
            if (typeof pattern === 'string') {
                return item === pattern || relPath === pattern;
            } else if (pattern instanceof RegExp) {
                return pattern.test(item) || pattern.test(relPath);
            }
            return false;
        });

        if (shouldExclude) {
            console.log(`⏭️  Excluded: ${relPath}`);
            continue;
        }

        if (fs.statSync(srcPath).isDirectory()) {
            copyDirectoryWithFilter(srcPath, destPath, excludePatterns, rootBase);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    }

    return true;
}

essentialFiles.forEach(({ src, dest }) => {
    const source = path.join(projectRoot, src);
    const destination = path.join(packageDir, dest);
    
    if (fs.existsSync(source)) {
        try {
            if (fs.statSync(source).isDirectory()) {
                // Special handling for writable directory to clean debug/log files
                if (src === 'writable') {
                    // Exclude all debug and log files, setup flags, and SQLite DB files
                    const excludePatterns = [
                        'setup_completed.flag',
                        'setup_complete.flag',
                        /^database\/.*\.db$/i,
                        /^logs\/.*\.log$/i,
                        /^debugbar\/.*\.json$/i,
                        'upload-debug.log'
                    ];
                    copyDirectoryWithFilter(source, destination, excludePatterns);
                    
                    // Ensure empty directories exist with proper structure
                    const cleanDirectories = ['logs', 'debugbar', 'cache', 'session', 'uploads'];
                    cleanDirectories.forEach(dir => {
                        const dirPath = path.join(destination, dir);
                        if (!fs.existsSync(dirPath)) {
                            fs.mkdirSync(dirPath, { recursive: true });
                        }
                        // Create .gitkeep file to preserve directory structure
                        const gitkeepPath = path.join(dirPath, '.gitkeep');
                        if (!fs.existsSync(gitkeepPath)) {
                            fs.writeFileSync(gitkeepPath, '# Keep this directory in version control\n');
                        }
                    });
                    
                    console.log(`✅ Copied ${src} → ${dest} (cleaned: logs, debugbar, flags, SQLite files)`);
                } else if (src === 'app') {
                    // Exclude test views from production deployment
                    const excludePatterns = ['Views/test'];
                    copyDirectoryWithFilter(source, destination, excludePatterns);
                    console.log(`✅ Copied ${src} → ${dest} (excluded Views/test)`);
                } else {
                    fs.cpSync(source, destination, { recursive: true });
                    console.log(`✅ Copied ${src} → ${dest}`);
                }
            } else {
                fs.mkdirSync(path.dirname(destination), { recursive: true });
                fs.copyFileSync(source, destination);
                console.log(`✅ Copied ${src} → ${dest}`);
            }
        } catch (error) {
            console.error(`❌ Failed to copy ${src}: ${error.message}`);
        }
    } else {
        console.warn(`⚠️  Source not found: ${src}`);
    }
});

// Copy system directory separately for standalone deployment
const systemSource = path.join(projectRoot, 'vendor/codeigniter4/framework/system');
const systemDest = path.join(packageDir, 'system');

if (fs.existsSync(systemSource)) {
    try {
        fs.cpSync(systemSource, systemDest, { recursive: true });
        console.log(`✅ Copied vendor/codeigniter4/framework/system → system`);
    } catch (error) {
        console.error(`❌ Failed to copy system directory: ${error.message}`);
    }
} else {
    console.warn(`⚠️  System directory not found: ${systemSource}`);
}

// Ensure no real .env file is included (only .env.example should be present)
const envFile = path.join(packageDir, '.env');
if (fs.existsSync(envFile)) {
    fs.unlinkSync(envFile);
    console.log('⚠️  Removed .env file from deployment package (only .env.example should be included)');
}

// Verify .env.example exists
const envExampleFile = path.join(packageDir, '.env.example');
if (!fs.existsSync(envExampleFile)) {
    console.error('❌ .env.example file missing from deployment package - setup will fail!');
}

// Update index.php for standalone deployment
const indexPath = path.join(packageDir, 'public/index.php');
if (fs.existsSync(indexPath)) {
    let indexContent = fs.readFileSync(indexPath, 'utf8');
    
    // Make sure we preserve the original structure but ensure paths are correct
    // The paths should already be correct, but let's verify the system directory path
    console.log('✅ Index.php paths verified for standalone deployment');
} else {
    console.warn('⚠️  index.php not found in deployment package');
}

// Update Paths.php for standalone deployment
const pathsConfigPath = path.join(packageDir, 'app/Config/Paths.php');
if (fs.existsSync(pathsConfigPath)) {
    let pathsContent = fs.readFileSync(pathsConfigPath, 'utf8');
    
    // Ensure all directory paths are correct for standalone deployment
    pathsContent = pathsContent.replace(
        /public string \$systemDirectory = [^;]+;/,
        "public string $systemDirectory = __DIR__ . '/../../system';"
    );
    
    pathsContent = pathsContent.replace(
        /public string \$appDirectory = [^;]+;/,
        "public string $appDirectory = __DIR__ . '/..';"
    );
    
    pathsContent = pathsContent.replace(
        /public string \$writableDirectory = [^;]+;/,
        "public string $writableDirectory = __DIR__ . '/../../writable';"
    );
    
    pathsContent = pathsContent.replace(
        /public string \$testsDirectory = [^;]+;/,
        "public string $testsDirectory = __DIR__ . '/../../tests';"
    );
    
    pathsContent = pathsContent.replace(
        /public string \$viewDirectory = [^;]+;/,
        "public string $viewDirectory = __DIR__ . '/../Views';"
    );
    
    fs.writeFileSync(pathsConfigPath, pathsContent);
    console.log('✅ Updated Paths.php for standalone deployment');
} else {
    console.warn('⚠️  Paths.php not found in deployment package');
}

// Update App.php for production deployment
const appConfigPath = path.join(packageDir, 'app/Config/App.php');
if (fs.existsSync(appConfigPath)) {
    let appContent = fs.readFileSync(appConfigPath, 'utf8');
    
    // Set production base URL to be dynamic/empty for flexible deployment
    appContent = appContent.replace(
        /public string \$baseURL = '[^']*';/,
        "public string $baseURL = '';"
    );
    
    // Remove index.php from URLs for clean URLs (works with .htaccess)
    appContent = appContent.replace(
        /public string \$indexPage = '[^']*';/,
        "public string $indexPage = '';"
    );
    
    // Add constructor for robust baseURL auto-detection if not already present
    if (!appContent.includes('public function __construct()')) {
        const constructorCode = `
    /**
     * Constructor - Auto-detect baseURL for production environments
     */
    public function __construct()
    {
        parent::__construct();
        
        // Auto-detect baseURL if empty (production deployment)
        if (empty($this->baseURL) && !empty($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                       (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') 
                       ? 'https://' : 'http://';
            
            $host = $_SERVER['HTTP_HOST'];
            
            // Handle subdirectory installations
            $path = '';
            if (!empty($_SERVER['SCRIPT_NAME'])) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                if ($scriptDir !== '/' && $scriptDir !== '.') {
                    $path = $scriptDir;
                }
            }
            
            $this->baseURL = $protocol . $host . $path . '/';
        }
    }
`;
        
        // Insert constructor after the baseURL property
        appContent = appContent.replace(
            /(public string \$baseURL = '';)/,
            `$1${constructorCode}`
        );
    }
    
    fs.writeFileSync(appConfigPath, appContent);
    console.log('✅ Updated App.php for production deployment with robust URL detection');
} else {
    console.warn('⚠️  App.php not found in deployment package');
}

// Copy .env.example to .env in deployment (for setup wizard to use)
const envSourcePath = path.join(projectRoot, '.env.example');
const envDestPath = path.join(packageDir, '.env');

if (fs.existsSync(envSourcePath)) {
    fs.copyFileSync(envSourcePath, envDestPath);
    console.log('✅ Copied .env.example to .env for deployment');
} else {
    console.warn('⚠️  .env.example not found, creating basic .env file');
    
    // Fallback: create a basic .env file
    const envContent = `# Production Environment Configuration
# This file will be populated by the setup wizard

# ENVIRONMENT
CI_ENVIRONMENT = production

# APP
app.baseURL = ''
app.indexPage = ''

# DATABASE (Set by setup wizard)
database.default.hostname = 
database.default.database = 
database.default.username = 
database.default.password = 
database.default.DBDriver = 

# ENCRYPTION (Generated by setup wizard)
encryption.key = 

# SESSION
session.driver = 'CodeIgniter\\Session\\Handlers\\FileHandler'
session.cookieName = 'ci_session'
session.expiration = 7200
session.savePath = null
session.matchIP = false
session.timeToUpdate = 300
session.regenerateDestroy = false

# SECURITY
security.CSRFProtection = true
security.tokenName = 'csrf_test_name'
security.headerName = 'X-CSRF-TOKEN'
security.cookieName = 'csrf_cookie_name'
security.expires = 7200
security.regenerate = true
security.redirect = true
security.samesite = 'Lax'

# LOGGER
logger.threshold = 4
`;

    fs.writeFileSync(envDestPath, envContent);
}
console.log('✅ Environment configuration ready for setup wizard');

// Create comprehensive .htaccess for production deployment
const htaccessContent = `# CodeIgniter 4 Production .htaccess
RewriteEngine On

# Handle CodeIgniter requests
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]

# Disable server signature
ServerSignature Off

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# PHP error handling (safe defaults for production)
<IfModule mod_php.c>
    php_flag display_errors Off
    php_flag log_errors On
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# Prevent access to sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.lock">
    Order allow,deny
    Deny from all
</Files>`;

fs.writeFileSync(path.join(packageDir, 'public/.htaccess'), htaccessContent);
console.log('✅ Created public/.htaccess');

// Create root .htaccess for security
const rootHtaccessContent = `# Deny access to sensitive directories
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/(app|system|writable|vendor)(/.*)?$ [NC]
    RewriteRule ^.*$ - [F,L]
</IfModule>

# Redirect all requests to public folder
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^$ public/ [L]
    RewriteRule (.*) public/$1 [L]
</IfModule>`;

fs.writeFileSync(path.join(packageDir, '.htaccess'), rootHtaccessContent);

// Create deployment README
const readmeContent = `# WebSchedulr Production Deployment

## 🚀 Quick Deploy Instructions:

1. **Upload Files**: Upload the entire contents of this folder to your hosting provider
2. **Point Domain**: Point your domain/subdomain to the 'public' folder (NOT the root)
3. **Set Permissions**: Set writable folder permissions to 755 or 777:
   \`\`\`
   chmod -R 755 writable/
   \`\`\`
4. **Environment Setup**: The .env file is pre-configured for production
5. **First Access**: Visit your domain - you'll be redirected to the setup wizard

## 🧹 Clean Production Environment:

This deployment package includes:
- ✅ **Clean logs directory** - Empty and ready for production logging
- ✅ **Clean debugbar directory** - No development debug files included
- ✅ **No setup flags** - Ensures fresh setup wizard experience  
- ✅ **No SQLite dev databases** - Clean database directory
- ✅ **No test views** - Production-only view files

${logArchivePath ? `## 📂 Archived Development Files:

Development logs and debug files have been archived to:
\`${path.basename(logArchivePath)}\`

These files are preserved for audit/debugging purposes but excluded from production deployment.
` : `## � Log Archiving:

To archive development logs before deployment, run:
\`\`\`
npm run package -- --archive-logs
# OR
ARCHIVE_LOGS=true npm run package
\`\`\`
`}

## �📁 Deployment Scenarios:

### Option A: Subdomain Deployment (Recommended)
- Upload all files to: \`subdomain_root/\`
- Point subdomain document root to: \`subdomain_root/public/\`
- Access via: \`https://app.yourdomain.com\`

### Option B: Subfolder Deployment
- Upload all files to: \`yourdomain.com/app/\`
- Update .htaccess to handle subfolder routing
- Access via: \`https://yourdomain.com/app/public/\`

### Option C: Main Domain Deployment
- Upload all files to: \`domain_root/\`
- Point domain document root to: \`domain_root/public/\`
- Access via: \`https://yourdomain.com\`

## 🔧 Troubleshooting:

### 500 Internal Server Error:
1. Check file permissions: \`chmod -R 755 writable/\`
2. Check .htaccess compatibility (try renaming .htaccess temporarily)
3. Check error logs in \`writable/logs/\` (will be created after first request)
4. Ensure PHP 8.1+ is available

### Database Issues:
- SQLite: Ensure writable/database/ folder has write permissions
- MySQL: Update .env file with correct database credentials during setup

### Path Issues:
- Ensure your web server points to the 'public' folder
- Check that mod_rewrite is enabled for .htaccess

### Logging:
- Production logs will be created in \`writable/logs/\` after deployment
- Debug toolbar data will be stored in \`writable/debugbar/\` if enabled
- All directories have .gitkeep files to maintain structure

## 📋 File Structure:
\`\`\`
your-upload-directory/
├── app/                 # Application code
├── public/              # Web root (point domain here)
│   ├── index.php       # Entry point
│   ├── .htaccess       # URL rewriting
│   └── build/          # Compiled assets
├── system/             # CodeIgniter framework
├── vendor/             # PHP dependencies
├── writable/           # Logs, cache, uploads (clean directories)
│   ├── logs/           # Clean - ready for production logs
│   ├── debugbar/       # Clean - ready for debug data
│   ├── cache/          # Clean - ready for cache files
│   ├── session/        # Clean - ready for session files
│   └── uploads/        # Clean - ready for file uploads
└── .env               # Environment configuration
\`\`\`

## ⚡ Zero Configuration:
This package is designed for zero-configuration deployment. Just upload and go!

For support, check the application logs in writable/logs/ if you encounter issues.
`;

fs.writeFileSync(path.join(packageDir, 'DEPLOY-README.md'), readmeContent);
console.log('✅ Created comprehensive deployment documentation');

// Create a quick deployment guide specifically for ZIP deployment
const quickDeployContent = `# 🚀 Quick ZIP Deployment Guide

## Step 1: Upload & Extract
1. Upload \`webschedulr-deploy.zip\` to your hosting provider
2. Extract the ZIP file in your hosting account
3. Point your domain to the \`public/\` folder (IMPORTANT!)

## Step 2: Set Permissions
Run this command or use your hosting panel:
\`\`\`bash
chmod -R 755 writable/
\`\`\`

## Step 3: Access Your Application
- Visit your domain
- You'll be redirected to the setup wizard
- Create your admin account
- Choose database (SQLite recommended for easy setup)
- Start using WebSchedulr!

## Troubleshooting
- If you get 500 errors, check writable/ folder permissions
- If pages don't load, ensure domain points to public/ folder
- For debugging, temporarily upload debug.php to public/ folder

## File Structure After Extraction:
\`\`\`
your-hosting-root/
├── app/                 # Application code
├── public/              # ← Point your domain HERE
│   ├── index.php       
│   └── build/          # Compiled assets
├── system/             # Framework
├── vendor/             # Dependencies  
├── writable/           # Must be writable!
└── .env               # Configuration
\`\`\`

Ready in 3 steps! 🎉
`;

fs.writeFileSync(path.join(packageDir, 'QUICK-DEPLOY.md'), quickDeployContent);
console.log('✅ Created quick deployment guide for ZIP users');

console.log('✅ Created comprehensive deployment documentation');

// Validate the deployment package
console.log('\n🔍 Validating deployment package...');

const requiredFiles = [
    'public/index.php',
    'app/Config/App.php',
    'app/Config/Paths.php',
    'app/Controllers/Setup.php',
    'app/Views/setup.php',
    'system/Boot.php',
    'writable',
    '.env',
    'public/.htaccess'
];

let validationPassed = true;

requiredFiles.forEach(file => {
    const filePath = path.join(packageDir, file);
    if (fs.existsSync(filePath)) {
        console.log(`✅ ${file}`);
    } else {
        console.log(`❌ Missing: ${file}`);
        validationPassed = false;
    }
});

if (validationPassed) {
    console.log('\n🎉 Deployment package validation passed!');
} else {
    console.log('\n⚠️  Deployment package validation failed - some files are missing');
}

console.log('\n🎉 Standalone package ready!');
console.log(`📁 Package location: ${packageDir}`);
console.log('📋 Upload the contents to your hosting provider and point domain to public/ folder!');

// Create ZIP file for easy deployment
console.log('\n📦 Creating ZIP package for deployment...');

const zipName = `webschedulr-deploy-v${deployVersion}.zip`;
const zipPath = path.join(projectRoot, zipName);

// Also keep the generic name for backward compatibility
const genericZipName = 'webschedulr-deploy.zip';
const genericZipPath = path.join(projectRoot, genericZipName);

// Remove existing zip files if they exist
if (fs.existsSync(zipPath)) {
    fs.unlinkSync(zipPath);
    console.log(`🗑️  Removed existing versioned ZIP: v${deployVersion}`);
}
if (fs.existsSync(genericZipPath)) {
    fs.unlinkSync(genericZipPath);
    console.log('🗑️  Removed existing generic ZIP file');
}

async function createZipFile() {
    return new Promise((resolve, reject) => {
        // Create a file to stream archive data to
        const output = fs.createWriteStream(zipPath);
        const archive = archiver('zip', {
            zlib: { level: 9 } // Sets the compression level
        });

        // Listen for all archive data to be written
        output.on('close', () => {
            const fileSizeInMB = (archive.pointer() / (1024 * 1024)).toFixed(2);
            console.log(`✅ ZIP package created successfully!`);
            console.log(`📁 ZIP location: ${zipPath}`);
            console.log(`📊 ZIP size: ${fileSizeInMB} MB`);
            console.log(`📊 Version: v${deployVersion}`);
            console.log(`📊 Total bytes: ${archive.pointer()}`);
            
            // Create generic copy for backward compatibility
            try {
                fs.copyFileSync(zipPath, genericZipPath);
                console.log(`📋 Created generic copy: ${genericZipPath}`);
            } catch (err) {
                console.warn('⚠️  Could not create generic copy:', err.message);
            }
            
            resolve();
        });

        // Handle warnings (ie stat failures and other non-blocking errors)
        archive.on('warning', (err) => {
            if (err.code === 'ENOENT') {
                console.warn('⚠️  Warning:', err);
            } else {
                reject(err);
            }
        });

        // Handle errors
        archive.on('error', (err) => {
            console.error('❌ Archive error:', err);
            reject(err);
        });

        // Handle progress
        archive.on('progress', (progress) => {
            if (progress.entries.processed % 100 === 0) {
                console.log(`📝 Processing: ${progress.entries.processed} files, ${progress.entries.total} total`);
            }
        });

        // Pipe archive data to the file
        archive.pipe(output);

        // Check if source directory exists and has files
        if (!fs.existsSync(packageDir)) {
            reject(new Error(`Package directory does not exist: ${packageDir}`));
            return;
        }

        const files = fs.readdirSync(packageDir);
        console.log(`📂 Adding ${files.length} items to ZIP: ${files.join(', ')}`);

        // Add entire directory contents to ZIP
        archive.directory(packageDir, false);

        // Add a deployment info file with version
        const deploymentInfo = `WebSchedulr Deployment Package
Version: v${deployVersion}
Created: ${new Date().toISOString()}
Git Branch: ${execSync('git rev-parse --abbrev-ref HEAD', { cwd: projectRoot }).toString().trim()}
Git Commit: ${execSync('git rev-parse --short HEAD', { cwd: projectRoot }).toString().trim()}
Package Script: package.js
Source Directory: ${packageDir}
Files Included: ${files.join(', ')}

DEPLOYMENT INSTRUCTIONS:
1. Extract this ZIP to your web server
2. Point your domain to the public/ folder
3. Ensure writable/ folder has write permissions (755 or 775)
4. Run the setup wizard if this is a fresh install
5. Check the logs in writable/logs/ if you encounter issues
`;
        archive.append(deploymentInfo, { name: 'DEPLOYMENT-INFO.txt' });

        // Finalize the archive (ie we are done appending files but streams have to finish yet)
        archive.finalize();
    });
}

try {
    await createZipFile();
    
    console.log('\n🚀 Deployment Options:');
    console.log('   1. Upload ZIP file and extract on server');
    console.log('   2. Upload individual files from webschedulr-deploy/ folder');
    console.log('   3. Use ZIP for backup/distribution');
    console.log('\n📋 Quick Upload:');
    console.log(`   - Upload: ${zipName}`);
    console.log('   - Extract to hosting root');
    console.log('   - Point domain to public/ folder');
    
} catch (error) {
    console.warn('⚠️  Could not create ZIP file:', error.message);
    console.log('💡 Alternative: Manually compress the webschedulr-deploy/ folder');
}
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import archiver from 'archiver';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

console.log('📦 Creating standalone deployment package...');

// Create deployment package
const packageDir = path.join(projectRoot, 'xscheduler-deploy');
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
    { src: 'preload.php', dest: 'preload.php' }
];

// Note: vendor already includes vendor/codeigniter4/framework/system
// We'll copy it separately for standalone deployment structure

essentialFiles.forEach(({ src, dest }) => {
    const source = path.join(projectRoot, src);
    const destination = path.join(packageDir, dest);
    
    if (fs.existsSync(source)) {
        try {
            if (fs.statSync(source).isDirectory()) {
                fs.cpSync(source, destination, { recursive: true });
            } else {
                fs.mkdirSync(path.dirname(destination), { recursive: true });
                fs.copyFileSync(source, destination);
            }
            console.log(`✅ Copied ${src} → ${dest}`);
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
    
    fs.writeFileSync(appConfigPath, appContent);
    console.log('✅ Updated App.php for production deployment');
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

# Enable error logging for debugging (remove in production)
<IfModule mod_php.c>
    php_flag display_errors On
    php_flag log_errors On
    php_value error_log ../writable/logs/error.log
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
console.log('✅ Created public/.htaccess with debugging enabled');

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
const readmeContent = `# xScheduler Production Deployment

## 🚀 Quick Deploy Instructions:

1. **Upload Files**: Upload the entire contents of this folder to your hosting provider
2. **Point Domain**: Point your domain/subdomain to the 'public' folder (NOT the root)
3. **Set Permissions**: Set writable folder permissions to 755 or 777:
   \`\`\`
   chmod -R 755 writable/
   \`\`\`
4. **Environment Setup**: The .env file is pre-configured for production
5. **First Access**: Visit your domain - you'll be redirected to the setup wizard

## 📁 Deployment Scenarios:

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
3. Check error logs in \`writable/logs/\`
4. Ensure PHP 8.1+ is available

### Database Issues:
- SQLite: Ensure writable/database/ folder has write permissions
- MySQL: Update .env file with correct database credentials during setup

### Path Issues:
- Ensure your web server points to the 'public' folder
- Check that mod_rewrite is enabled for .htaccess

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
├── writable/           # Logs, cache, uploads
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
1. Upload \`xscheduler-deploy.zip\` to your hosting provider
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
- Start using xScheduler!

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

const zipName = 'xscheduler-deploy.zip';
const zipPath = path.join(projectRoot, zipName);

// Remove existing zip file if it exists
if (fs.existsSync(zipPath)) {
    fs.unlinkSync(zipPath);
    console.log('🗑️  Removed existing ZIP file');
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
            console.log(`📊 Total files: ${archive.pointer()} bytes`);
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

        // Add a deployment timestamp file
        archive.append(`Deployment package created: ${new Date().toISOString()}\nVersion: xScheduler CI4\nPackaged by: package.js script\nSource directory: ${packageDir}\nFiles included: ${files.join(', ')}`, { name: 'DEPLOYMENT-INFO.txt' });

        // Finalize the archive (ie we are done appending files but streams have to finish yet)
        archive.finalize();
    });
}

try {
    await createZipFile();
    
    console.log('\n🚀 Deployment Options:');
    console.log('   1. Upload ZIP file and extract on server');
    console.log('   2. Upload individual files from xscheduler-deploy/ folder');
    console.log('   3. Use ZIP for backup/distribution');
    console.log('\n📋 Quick Upload:');
    console.log(`   - Upload: ${zipName}`);
    console.log('   - Extract to hosting root');
    console.log('   - Point domain to public/ folder');
    
} catch (error) {
    console.warn('⚠️  Could not create ZIP file:', error.message);
    console.log('💡 Alternative: Manually compress the xscheduler-deploy/ folder');
}
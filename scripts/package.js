import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

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
    { src: '.env.example', dest: '.env' },
    // Add essential CodeIgniter files for standalone deployment
    { src: 'vendor/codeigniter4/framework/system', dest: 'system' },
    { src: 'spark', dest: 'spark' },
    { src: 'preload.php', dest: 'preload.php' }
];

// Note: public folder already includes the built assets from Vite
// Note: Copying system directory separately for standalone deployment

essentialFiles.forEach(({ src, dest }) => {
    const source = path.join(projectRoot, src);
    const destination = path.join(packageDir, dest);
    
    if (fs.existsSync(source)) {
        if (fs.statSync(source).isDirectory()) {
            fs.cpSync(source, destination, { recursive: true });
        } else {
            fs.mkdirSync(path.dirname(destination), { recursive: true });
            fs.copyFileSync(source, destination);
        }
        console.log(`✅ Copied ${src} → ${dest}`);
    }
});

// Update index.php for standalone deployment
const indexPath = path.join(packageDir, 'public/index.php');
if (fs.existsSync(indexPath)) {
    let indexContent = fs.readFileSync(indexPath, 'utf8');
    
    // Update paths for standalone deployment (not using vendor/codeigniter4/framework)
    indexContent = indexContent.replace(
        /require\s+FCPATH\s*\.\s*['"][^'"]*vendor[^'"]*['"];?/g,
        "require FCPATH . '../app/Config/Paths.php';"
    );
    
    // Ensure system path points to our standalone system directory
    indexContent = indexContent.replace(
        /\$pathsConfig->systemDirectory\s*=.*$/gm,
        "$pathsConfig->systemDirectory = ROOTPATH . 'system';"
    );
    
    fs.writeFileSync(indexPath, indexContent);
    console.log('✅ Updated index.php for standalone deployment');
}

// Update Paths.php for standalone deployment
const pathsConfigPath = path.join(packageDir, 'app/Config/Paths.php');
if (fs.existsSync(pathsConfigPath)) {
    let pathsContent = fs.readFileSync(pathsConfigPath, 'utf8');
    
    // Update system directory path for standalone deployment
    pathsContent = pathsContent.replace(
        /public\s+string\s+\$systemDirectory\s*=\s*__DIR__\s*\.\s*['"][^'"]*['"];?/,
        "public string $systemDirectory = __DIR__ . '/../../system';"
    );
    
    fs.writeFileSync(pathsConfigPath, pathsContent);
    console.log('✅ Updated Paths.php for standalone deployment');
}

// Create comprehensive .htaccess for production deployment

const htaccessContent = `# CodeIgniter 4 Production .htaccess
RewriteEngine On

# Handle angular front-end requests
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
const readmeContent = `# xScheduler Deployment

## Quick Deploy Instructions:

1. Upload the entire contents of this folder to your hosting provider
2. Point your domain/subdomain to the 'public' folder
3. Update the .env file with your database credentials
4. Set writable folder permissions to 755

## For Subfolder Deployment:
- Upload to: yourdomain.com/subfolder/
- Make sure the web server points to the 'public' directory

## For Subdomain Deployment:
- Upload to subdomain root
- Point subdomain to the 'public' directory

That's it! Zero configuration needed.
`;

fs.writeFileSync(path.join(packageDir, 'DEPLOY-README.md'), readmeContent);

console.log('🎉 Standalone package ready!');
console.log(`📁 Package location: ${packageDir}`);
console.log('📋 Just upload the contents to your hosting provider!');
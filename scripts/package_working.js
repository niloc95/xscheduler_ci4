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
    fs.rmSync(packageDir, { recursive: true });
}
fs.mkdirSync(packageDir, { recursive: true });

// Copy CodeIgniter structure
const essentialFiles = [
    { src: 'app', dest: 'app' },
    { src: 'system', dest: 'system' },
    { src: 'writable', dest: 'writable' },
    { src: 'vendor', dest: 'vendor' },
    { src: 'public', dest: 'public' },
    { src: 'dist', dest: 'public/assets' }, // Vite build output
    { src: '.env.example', dest: '.env' }
];

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
    
    // Ensure proper paths for any subfolder deployment
    indexContent = indexContent.replace(
        "require FCPATH . '../app/Config/Paths.php';",
        "require FCPATH . '../app/Config/Paths.php';"
    );
    
    fs.writeFileSync(indexPath, indexContent);
    console.log('✅ Updated index.php for standalone deployment');
}

// Create simple .htaccess for subfolders
const htaccessContent = `RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]

# Remove index.php from URLs
RewriteCond %{THE_REQUEST} \\s/+[^/\\s]*\\.php[?/\\s] [NC]
RewriteRule ^(.*)$ %{REQUEST_URI} [R=301,L]`;

fs.writeFileSync(path.join(packageDir, 'public/.htaccess'), htaccessContent);

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
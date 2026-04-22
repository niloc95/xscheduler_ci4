# xScheduler Production Deployment

## 🚀 Quick Deploy Instructions:

1. **Upload Files**: Upload the entire contents of this folder to your hosting provider
2. **Point Domain**: Point your domain/subdomain to the 'public' folder (NOT the root)
3. **Set Permissions**: Set writable folder permissions to 755 or 777:
   ```
   chmod -R 755 writable/
   ```
4. **Environment Setup**: The .env file is pre-configured for production
5. **First Access**: Visit your domain - you'll be redirected to the setup wizard

## 📁 Deployment Scenarios:

### Option A: Subdomain Deployment (Recommended)
- Upload all files to: `subdomain_root/`
- Point subdomain document root to: `subdomain_root/public/`
- Access via: `https://app.yourdomain.com`

### Option B: Subfolder Deployment
- Upload all files to: `yourdomain.com/app/`
- Update .htaccess to handle subfolder routing
- Access via: `https://yourdomain.com/app/public/`

### Option C: Main Domain Deployment
- Upload all files to: `domain_root/`
- Point domain document root to: `domain_root/public/`
- Access via: `https://yourdomain.com`

## 🔧 Troubleshooting:

### 500 Internal Server Error:
1. Check file permissions: `chmod -R 755 writable/`
2. Check .htaccess compatibility (try renaming .htaccess temporarily)
3. Check error logs in `writable/logs/`
4. Ensure PHP 8.1+ is available

### Database Issues:
- SQLite: Ensure writable/database/ folder has write permissions
- MySQL: Update .env file with correct database credentials during setup

### Path Issues:
- Ensure your web server points to the 'public' folder
- Check that mod_rewrite is enabled for .htaccess

## 📋 File Structure:
```
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
```

## ⚡ Zero Configuration:
This package is designed for zero-configuration deployment. Just upload and go!

For support, check the application logs in writable/logs/ if you encounter issues.

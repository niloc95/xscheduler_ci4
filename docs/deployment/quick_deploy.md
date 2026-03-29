# 🚀 Quick ZIP Deployment Guide

## Step 1: Upload & Extract
1. Upload `webschedulr-deploy.zip` to your hosting provider
2. Extract the ZIP file in your hosting account
3. Point your domain to the `public/` folder (IMPORTANT!)

## Step 2: Set Permissions
Run this command or use your hosting panel:
```bash
chmod -R 755 writable/
```

## Step 3: Access Your Application
- Visit your domain
- You'll be redirected to the setup wizard
- Create your admin account
- Choose database (SQLite recommended for easy setup)
- Start using WebScheduler!

## Troubleshooting
- If you get 500 errors, check writable/ folder permissions
- If pages don't load, ensure domain points to public/ folder
- For debugging, temporarily upload debug.php to public/ folder

## File Structure After Extraction:
```
your-hosting-root/
├── app/                 # Application code
├── public/              # ← Point your domain HERE
│   ├── index.php       
│   └── build/          # Compiled assets
├── system/             # Framework
├── vendor/             # Dependencies  
├── writable/           # Must be writable!
└── .env               # Configuration
```

Ready in 3 steps! 🎉

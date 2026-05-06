# AWS Lightsail — Bitnami LAMP Stack Reference Guide

---

## Table of Contents
1. [Connecting to Your Instance](#connecting)
2. [Key File Locations](#file-locations)
3. [Apache Configuration](#apache)
4. [phpMyAdmin](#phpmyadmin)
5. [File Transfers (FileZilla)](#filezilla)
6. [Multi-App Subdomain Deployment](#deployment)
7. [Automated Deploy Script](#deploy-script)
8. [SSL & HTTPS](#ssl)
9. [DNS Configuration](#dns)
10. [Permissions Reference](#permissions)
11. [Common Commands](#commands)
12. [Troubleshooting](#troubleshooting)

---

## 1. Connecting to Your Instance {#connecting}

### SSH via Terminal
```bash
ssh -i /path/to/your-key.pem bitnami@YOUR_LIGHTSAIL_IP
```

### SSH Tunnel (for secure local access)
```bash
ssh -i your-key.pem -N -L 8888:127.0.0.1:80 bitnami@YOUR_LIGHTSAIL_IP
```
Then browse to `http://localhost:8888`

### Default Credentials
```bash
cat /home/bitnami/bitnami_credentials
```

---

## 2. Key File Locations {#file-locations}

> **Important:** On Bitnami Lightsail, `apache2` is a symlink to `apache`. They are the same directory.
> ```bash
> ls -la /opt/bitnami/ | grep apache
> # lrwxrwxrwx apache2 -> apache
> ```

| Resource | Path |
|---|---|
| Web root | `/opt/bitnami/apache/htdocs/` |
| Apache config | `/opt/bitnami/apache/conf/bitnami/bitnami.conf` |
| SSL config | `/opt/bitnami/apache/conf/bitnami/bitnami-ssl.conf` |
| Apache error log | `/opt/bitnami/apache/logs/error_log` |
| phpMyAdmin config | `/opt/bitnami/apache/conf/bitnami/phpmyadmin.conf` |
| Bitnami credentials | `/home/bitnami/bitnami_credentials` |
| PHP config | `/opt/bitnami/apache/conf/bitnami/php-fpm.conf` |

---

## 3. Apache Configuration {#apache}

### Main VirtualHost Config
```bash
sudo nano /opt/bitnami/apache/conf/bitnami/bitnami.conf
```

### Single App Example
```apache
SetEnvIf X-Forwarded-Proto https HTTPS=on
<VirtualHost _default_:80>
  DocumentRoot "/opt/bitnami/apache/htdocs/myapp/public"
  <Directory "/opt/bitnami/apache/htdocs/myapp/public">
    Options -Indexes +FollowSymLinks -MultiViews
    AllowOverride All
    Require all granted
  </Directory>
  ErrorDocument 503 /503.html
</VirtualHost>
Include "/opt/bitnami/apache/conf/bitnami/bitnami-ssl.conf"
```

### Multi-App VirtualHost (SSL config)
```bash
sudo nano /opt/bitnami/apache/conf/bitnami/bitnami-ssl.conf
```
```apache
<VirtualHost *:443>
  ServerName client1.app.yourdomain.com
  DocumentRoot "/opt/bitnami/apache/htdocs/client1/public"
  <Directory "/opt/bitnami/apache/htdocs/client1/public">
    AllowOverride All
    Require all granted
  </Directory>
</VirtualHost>

<VirtualHost *:443>
  ServerName client2.app.yourdomain.com
  DocumentRoot "/opt/bitnami/apache/htdocs/client2/public"
  <Directory "/opt/bitnami/apache/htdocs/client2/public">
    AllowOverride All
    Require all granted
  </Directory>
</VirtualHost>
```

### Restart Apache
```bash
sudo /opt/bitnami/ctlscript.sh restart apache
```

### Check Apache is Running
```bash
sudo /opt/bitnami/ctlscript.sh status apache
```

### Verify mod_rewrite is Enabled
```bash
/opt/bitnami/apache/bin/httpd -M | grep rewrite
```

---

## 4. phpMyAdmin {#phpmyadmin}

### Enable External Access
```bash
sudo nano /opt/bitnami/apache/conf/bitnami/phpmyadmin.conf
```
Change `Require local` to:
```apache
Require all granted        # Open to everyone (not recommended for production)
Require ip YOUR.IP.ADDRESS # Restrict to your IP (recommended)
```

### Restart and Access
```bash
sudo /opt/bitnami/ctlscript.sh restart apache
# Visit: http://YOUR_LIGHTSAIL_IP/phpmyadmin
# Username: root
# Password: from /home/bitnami/bitnami_credentials
```

### Secure Access via SSH Tunnel (Recommended)
```bash
ssh -i your-key.pem -N -L 8888:127.0.0.1:80 bitnami@YOUR_LIGHTSAIL_IP
# Visit: http://localhost:8888/phpmyadmin
```

---

## 5. File Transfers — FileZilla {#filezilla}

### Connection Settings
| Setting | Value |
|---|---|
| Protocol | SFTP – SSH File Transfer Protocol |
| Host | Your Lightsail public IP |
| Port | 22 |
| Logon Type | Key file |
| User | `bitnami` |
| Key file | Your `.pem` key (convert to `.ppk` with PuTTYgen if needed) |

### Fix Permission Denied Errors
```bash
sudo chown -R bitnami:bitnami /opt/bitnami/apache/htdocs/
sudo chmod -R 755 /opt/bitnami/apache/htdocs/
```

### Upload and Unzip
```bash
cd /opt/bitnami/apache/htdocs
unzip yourfile.zip              # Extract
unzip -o yourfile.zip           # Extract and overwrite existing files
```

---

## 6. Multi-App Subdomain Deployment {#deployment}

### Structure
Each client app lives in its own folder with its own subdomain:
```
htdocs/
├── client1/          → client1.app.yourdomain.com
│   └── public/       ← Apache DocumentRoot
├── client2/          → client2.app.yourdomain.com
│   └── public/
└── webschedulr-deploy-v191.zip
```

### CodeIgniter App Requirements
Each app's `.env` must have:
```env
app.baseURL   = 'https://clientname.app.yourdomain.com'
app.CSPEnabled = false
```

Each app's `public/.htaccess` needs:
```apache
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

---

## 7. Automated Deploy Script {#deploy-script}

### Location
```bash
/opt/bitnami/deploy.sh
```

### Full Script
```bash
#!/bin/bash
# Usage: sudo /opt/bitnami/deploy.sh clientname
NAME=$1
HTDOCS=/opt/bitnami/apache/htdocs

# Validate input
if [ -z "$NAME" ]; then
  echo "Usage: sudo ./deploy.sh clientname"
  exit 1
fi

# Find latest zip
ZIP=$(ls -t $HTDOCS/*.zip 2>/dev/null | head -1)
if [ -z "$ZIP" ]; then
  echo "Error: No zip file found in $HTDOCS"
  exit 1
fi

echo "Deploying $ZIP as '$NAME'..."

# Extract app
mkdir -p $HTDOCS/$NAME
unzip "$ZIP" -d $HTDOCS/$NAME

# Configure .env
sed -i 's/app.CSPEnabled = true/app.CSPEnabled = false/' $HTDOCS/$NAME/.env
# sed -i "s|app.baseURL = ''|app.baseURL = 'https://${NAME}.app.yourdomain.com'|" $HTDOCS/$NAME/.env

# Generate encryption key
# cd $HTDOCS/$NAME && php spark key:generate --force

# Set permissions
chown -R daemon:daemon $HTDOCS/$NAME
chmod -R 777 $HTDOCS/$NAME/writable

# Add VirtualHost
cat >> /opt/bitnami/apache/conf/bitnami/bitnami-ssl.conf << EOF

<VirtualHost *:443>
  ServerName ${NAME}.app.yourdomain.com
  DocumentRoot "/opt/bitnami/apache/htdocs/${NAME}/public"
  <Directory "/opt/bitnami/apache/htdocs/${NAME}/public">
    AllowOverride All
    Require all granted
  </Directory>
</VirtualHost>
EOF

# Restart Apache
/opt/bitnami/ctlscript.sh restart apache

echo "✅ Deployed: https://${NAME}.app.yourdomain.com"
```

### Install & Use
```bash
sudo chmod +x /opt/bitnami/deploy.sh
sudo /opt/bitnami/deploy.sh clientname
```

---

## 8. SSL & HTTPS {#ssl}

### Enable SSL with Let's Encrypt (Bitnami Tool)
```bash
sudo /opt/bitnami/bncert-tool
```
Follow the prompts — handles certificate generation, renewal, and Apache config automatically.

### SSL Config File
```bash
sudo nano /opt/bitnami/apache/conf/bitnami/bitnami-ssl.conf
```

### Force HTTPS in CodeIgniter .env
```env
app.forceGlobalSecureRequests = true
```

---

## 9. DNS Configuration {#dns}

### Wildcard Subdomain Record
Add this in your DNS provider (Cloudflare, Hostinger, etc.):

| Type | Name | Value | TTL |
|---|---|---|---|
| A | `*.app` | `YOUR_LIGHTSAIL_IP` | Auto |

This allows `anything.app.yourdomain.com` to resolve automatically — no new DNS record needed per client.

### Get Your Lightsail IP
```bash
curl -s http://checkip.amazonaws.com
```

### Verify DNS Propagation
```bash
nslookup clientname.app.yourdomain.com
```

### Open Firewall Ports (Lightsail Console)
Navigate to: Instance → **Networking** tab → **IPv4 Firewall**

| Port | Protocol | Purpose |
|---|---|---|
| 22 | TCP | SSH |
| 80 | TCP | HTTP |
| 443 | TCP | HTTPS |

---

## 10. Permissions Reference {#permissions}

| Command | Purpose |
|---|---|
| `sudo chown -R bitnami:bitnami /path` | Allow FileZilla uploads |
| `sudo chown -R daemon:daemon /path` | Allow Apache to write files |
| `sudo chmod -R 755 /path` | Standard folder permissions |
| `sudo chmod -R 777 /path/writable` | Allow app to write logs/cache |

### Apache runs as:
```bash
ps aux | grep httpd | head -3
# Look for the user in the first column
```

---

## 11. Common Commands {#commands}

### Apache Control
```bash
sudo /opt/bitnami/ctlscript.sh start apache
sudo /opt/bitnami/ctlscript.sh stop apache
sudo /opt/bitnami/ctlscript.sh restart apache
sudo /opt/bitnami/ctlscript.sh status apache
```

### All Services
```bash
sudo /opt/bitnami/ctlscript.sh restart all
sudo /opt/bitnami/ctlscript.sh status all
```

### PHP Version
```bash
php -v
```

### Find Config Files Referencing a Path
```bash
grep -r "phpmyadmin" /opt/bitnami/apache/conf/ --include="*.conf" -l
```

### Check Real Path of Symlink
```bash
realpath /opt/bitnami/apache2/htdocs/
```

### View Live Apache Error Log
```bash
sudo tail -f /opt/bitnami/apache/logs/error_log
```

### CodeIgniter Spark Commands
```bash
cd /opt/bitnami/apache/htdocs/appname
php spark key:generate --force   # Generate encryption key
php spark cache:clear            # Clear cache
php spark migrate                # Run database migrations
```

---

## 12. Troubleshooting {#troubleshooting}

### Still Seeing Bitnami Splash Screen
```bash
# Disable banner
sudo /opt/bitnami/apps/bitnami/bnconfig --disable_banner 1

# Check for stale index.html
ls /opt/bitnami/apache/htdocs/index*
sudo mv /opt/bitnami/apache/htdocs/index.html /opt/bitnami/apache/htdocs/index.html.bak

sudo /opt/bitnami/ctlscript.sh restart apache
```

### 500 Internal Server Error
```bash
# Check Apache error log
sudo tail -30 /opt/bitnami/apache/logs/error_log

# Check app logs (CodeIgniter)
cat /opt/bitnami/apache/htdocs/appname/writable/logs/log-$(date +%Y-%m-%d).php

# Fix writable permissions
sudo chmod -R 777 /opt/bitnami/apache/htdocs/appname/writable/
```

### 404 Not Found
- Verify `DocumentRoot` points to the `public/` subfolder
- Confirm `mod_rewrite` is enabled: `/opt/bitnami/apache/bin/httpd -M | grep rewrite`
- Check `.htaccess` exists in `public/` folder
- Test locally: `curl -I http://localhost/index.php`

### CSS/JS Broken (CSP Errors)
```bash
# Disable Content Security Policy in app .env
sed -i 's/app.CSPEnabled = true/app.CSPEnabled = false/' /opt/bitnami/apache/htdocs/appname/.env
sudo /opt/bitnami/ctlscript.sh restart apache
```

### ERR_NAME_NOT_RESOLVED
DNS wildcard record is missing or not propagated yet.
```bash
nslookup clientname.app.yourdomain.com   # Should return your Lightsail IP
```

### FileZilla Permission Denied
```bash
sudo chown -R bitnami:bitnami /opt/bitnami/apache/htdocs/
sudo chmod -R 755 /opt/bitnami/apache/htdocs/
```

### Find Which Apache Config is Active
```bash
# Check which httpd process is running and what config it uses
ps aux | grep httpd | head -3
# Look for: -f /opt/bitnami/apache/conf/httpd.conf
```

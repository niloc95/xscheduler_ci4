# webScheduler — Ubuntu 24.04 Deployment Guide

> End-to-end deployment guide for Ubuntu 24.04. Covers two scenarios:
> - **Scenario A — Dedicated domain/subdomain** (fresh server, e.g. `https://app.example.com/`)
> - **Scenario B — Sub-folder on existing server** (e.g. `https://example.com/webscheduler/`)
>
> The setup wizard handles all app configuration on first visit — no manual `.env` editing required.

---

## Prerequisites

- Ubuntu 24.04 VPS (AWS Lightsail or equivalent)
- SSH access as a user with `sudo`
- **Scenario A:** A domain/subdomain DNS A-record pointing at the server IP
- **Scenario B:** Apache 2.4 already running with an existing vhost

---

## Phase 1 — System Setup

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install unzip curl git -y
```

---

## Phase 2 — Apache

> Skip if Apache is already installed (Scenario B).

```bash
sudo apt install apache2 -y
sudo systemctl enable apache2
sudo systemctl start apache2
```

Open the firewall:
```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
```

Enable required modules:
```bash
sudo a2enmod rewrite headers ssl
sudo systemctl restart apache2
```

---

## Phase 3 — PHP

Install PHP and all extensions required by CodeIgniter 4:
```bash
sudo apt install php libapache2-mod-php php-cli \
  php-mbstring php-xml php-curl php-mysql \
  php-intl php-zip php-gd php-json -y
sudo systemctl restart apache2
```

Verify `intl` is loaded (CodeIgniter requires it):
```bash
php -m | grep intl
```

Expected output: `intl`

If missing:
```bash
sudo apt install php-intl -y && sudo systemctl restart apache2
```

---

## Phase 4 — MySQL

> Skip the install if MySQL is already running (Scenario B). Still create the DB and users.

```bash
sudo apt install mysql-server -y
sudo mysql_secure_installation
```

### Create the application database

```bash
sudo mysql
```

```sql
CREATE DATABASE webscheduler;
CREATE USER 'ws_user'@'localhost' IDENTIFIED BY 'ChangeMe_Strong1!';
GRANT ALL PRIVILEGES ON webscheduler.* TO 'ws_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Keep these credentials for the setup wizard.

### Create a phpMyAdmin admin user

MySQL root uses socket authentication and **cannot log in via phpMyAdmin**.
Create a password-auth admin user now:

```bash
sudo mysql
```

```sql
CREATE USER 'dbadmin'@'localhost' IDENTIFIED BY 'ChangeMe_DbAdmin1!';
GRANT ALL PRIVILEGES ON *.* TO 'dbadmin'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EXIT;
```

Use `dbadmin` to log into phpMyAdmin.

---

## Phase 5 — Deploy the Application

### Scenario A — Dedicated domain

Create the web root:
```bash
sudo mkdir -p /var/www/webscheduler
```

Upload the `webschedulr-deploy` package and extract it into `/var/www/webscheduler`.

Set ownership and permissions:
```bash
sudo chown -R www-data:www-data /var/www/webscheduler
sudo chmod -R 775 /var/www/webscheduler
```

### Scenario B — Sub-folder on existing server

Create the sub-folder:
```bash
sudo mkdir -p /var/www/html/webscheduler
```

Upload the `webschedulr-deploy` package and extract it into `/var/www/html/webscheduler`.

Set ownership and permissions:
```bash
sudo chown -R www-data:www-data /var/www/html/webscheduler
sudo chmod -R 775 /var/www/html/webscheduler
```

> **Important (both scenarios):** `www-data` must be the **owner**, not just the group.
> PHP calls `chmod()` on `.env` during the setup wizard — this fails if `www-data` is only group-member.

Expected directory layout (both scenarios):
```
<app-root>/
  app/
  public/
  system/
  vendor/
  writable/
  .env
  .htaccess
```

---

## Phase 6 — Apache Configuration

### Scenario A — Create a dedicated virtual host

Disable the default site:
```bash
sudo a2dissite 000-default.conf
```

Create the site config:
```bash
sudo nano /etc/apache2/sites-available/webscheduler.conf
```

Paste the following (replace `app.example.com` with your domain):
```apache
<VirtualHost *:80>
    ServerName app.example.com
    Redirect permanent / https://app.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName app.example.com

    DocumentRoot /var/www/webscheduler/public

    <Directory /var/www/webscheduler/public>
        Options +FollowSymLinks -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
    </IfModule>

    ErrorLog ${APACHE_LOG_DIR}/webscheduler_error.log
    CustomLog ${APACHE_LOG_DIR}/webscheduler_access.log combined

    # Certbot adds SSL certificate lines here (Phase 7)
</VirtualHost>
```

Enable and test:
```bash
sudo a2ensite webscheduler.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

---

### Scenario B — Add a Directory block to the existing vhost

Find the active vhost config:
```bash
ls /etc/apache2/sites-enabled/
```

Open it:
```bash
sudo nano /etc/apache2/sites-available/000-default.conf
```

Add this block **inside** the existing `<VirtualHost>`:
```apache
<Directory /var/www/html/webscheduler>
    Options +FollowSymLinks -Indexes
    AllowOverride All
    Require all granted
</Directory>
```

Test and reload:
```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

> **Scenario B users:** skip Phase 7 (SSL is already set up on your existing server)
> and skip Phase 8 if phpMyAdmin is already installed. Continue from Phase 9.

---

## Phase 7 — SSL Certificate *(Scenario A only)*

If using Cloudflare, set the DNS record to **DNS only** (grey cloud) before running Certbot.
Switch back to Proxied after the certificate is issued.

```bash
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d app.example.com
```

Choose the redirect option when prompted.

Test automatic renewal:
```bash
sudo certbot renew --dry-run
```

**If using Cloudflare:** set SSL/TLS mode to **Full (Strict)** in the Cloudflare dashboard.
Do not use Flexible — it causes an infinite redirect loop.

---

## Phase 8 — phpMyAdmin *(Scenario A only — skip if already installed)*

```bash
sudo apt install phpmyadmin -y
```

When prompted:
- Select **apache2** (press Space to select, then Enter)
- Choose **Yes** to configure with dbconfig-common
- Enter a strong password for the control user — must meet MySQL policy:
  uppercase + lowercase + digit + special character (e.g. `Pma@Admin2026!`)

If the install fails with a password policy error, fix it with:
```bash
sudo dpkg-reconfigure phpmyadmin
```
Select **apache2**, answer **Yes** to dbconfig-common, and re-enter a strong password.

Enable the config:
```bash
sudo a2enconf phpmyadmin
sudo systemctl reload apache2
```

### Restrict phpMyAdmin to your IP

```bash
sudo nano /etc/apache2/conf-available/phpmyadmin.conf
```

In the `<Directory /usr/share/phpmyadmin>` block, replace or add:
```apache
Require ip YOUR.PUBLIC.IP.HERE
```

Find your public IP:
```bash
curl -s ifconfig.me
```

To allow multiple IPs, add one `Require ip` line per IP:
```apache
Require ip 1.2.3.4
Require ip 5.6.7.8
```

Reload Apache:
```bash
sudo apache2ctl configtest && sudo systemctl reload apache2
```

Access phpMyAdmin at `https://app.example.com/phpmyadmin/` and log in with `dbadmin`.

### Fix control user warning (if it persists after install)

Use a heredoc to avoid bash `!` expansion issues:
```bash
sudo mysql << 'SQL'
ALTER USER 'phpmyadmin'@'localhost' IDENTIFIED BY 'Pma@Admin2026!';
FLUSH PRIVILEGES;
SQL
```

Update the config file to match:
```bash
sudo grep dbpass /etc/phpmyadmin/config-db.php
sudo nano /etc/phpmyadmin/config-db.php
# Update the line: $dbconfig['dbpass'] = 'Pma@Admin2026!';
```

Then reload Apache.

---

## Phase 9 — Run the Setup Wizard

**Scenario A:** visit `https://app.example.com/`

**Scenario B:** visit `https://YOUR_DOMAIN/webscheduler/`

The app redirects to the setup wizard. Complete all steps:
1. Business name and timezone
2. Database credentials (`ws_user` + password from Phase 4)
3. Admin account (name, email, password)

The wizard writes `.env`, generates the encryption key, runs migrations, and redirects to the login page.

---

## Phase 10 — Verify

```bash
# Scenario A
curl -I https://app.example.com/
sudo tail -f /var/log/apache2/webscheduler_error.log

# Scenario B
curl -I https://YOUR_DOMAIN/webscheduler/
sudo tail -f /var/log/apache2/error.log
```

---

## Adding More Tenants — deploy.sh

Once the server is running, use `deploy.sh` to spin up additional tenants automatically.
Each tenant gets its own subdomain (e.g. `dental.webscheduler.co.za`), directory, Apache vhost, and SSL certificate.

### One-time setup — copy the script to the server

```bash
scp docs/aws-lightsail/deploy.sh ubuntu@YOUR_SERVER_IP:/var/www/deploy.sh
```

On the server:
```bash
sudo chmod +x /var/www/deploy.sh
```

### To add a tenant

**Step 1** — Upload your release zip to `/var/www/webscheduler/` (via FileZilla, scp, etc.)

**Step 2** — Run the script:
```bash
sudo bash /var/www/deploy.sh dental
```

The script will:
1. Find the latest zip in `/var/www/webscheduler/`
2. Extract and flatten into `/var/www/dental/`
3. Set `www-data:www-data` ownership and correct permissions
4. Write a minimal `.env` (the setup wizard fills in the rest)
5. Create an Apache vhost for `dental.webscheduler.co.za`
6. Prompt you to disable the Cloudflare proxy, then run Certbot for SSL
7. Print the setup wizard URL when done

**Step 3** — Complete the setup wizard at `https://dental.webscheduler.co.za/`

> **DNS:** Add an A-record for `dental.webscheduler.co.za` pointing at the server IP
> before running the script, or Certbot will fail.

---

## Troubleshooting

| Problem | Cause | Fix |
|---|---|---|
| `Syntax error … Options` | Mixed `+/-` prefix | Use `Options +FollowSymLinks -Indexes` |
| 404 on all routes | `mod_rewrite` off or `AllowOverride` missing | `sudo a2enmod rewrite` + `AllowOverride All` |
| 403 Forbidden on sub-folder | `<Directory>` path doesn't match upload path | Verify the path in the Directory block exactly matches the upload location |
| `chmod(): Operation not permitted` | App dir not owned by `www-data` | `sudo chown -R www-data:www-data <app-root>` |
| Setup wizard loops | `setup_complete.flag` left from a failed attempt | `sudo rm -f <app-root>/writable/setup_complete.flag` |
| 500 after setup | PHP error | Check `<app-root>/writable/logs/log-YYYY-MM-DD.php` |
| DB connection error in wizard | Wrong credentials | Must exactly match what was created in Phase 4 |
| phpMyAdmin 403 Forbidden | IP not in `Require ip` list | Add your IP to `/etc/apache2/conf-available/phpmyadmin.conf` |
| phpMyAdmin `Access denied for root` | Root uses `auth_socket` | Log in with `dbadmin` instead |
| phpMyAdmin control user warning | Password mismatch | `sudo dpkg-reconfigure phpmyadmin` or use the heredoc fix in Phase 8 |
| `ERR_TOO_MANY_REDIRECTS` | Cloudflare SSL not Full (Strict) | Set Cloudflare SSL/TLS → Full (Strict) |
| Bash `!': event not found` | Bash history expansion | Use heredoc `<< 'SQL'` (quoted delimiter) |
| Sub-folder links broken after setup | Wrong `app.baseURL` | Set `app.baseURL = https://DOMAIN/webscheduler/` (trailing slash) in `.env` |

---

## Key Rules

| Rule | Detail |
|---|---|
| DocumentRoot | Always point to `/public` — never the app root |
| Permissions | `www-data:www-data` owner + `chmod 775` on the entire app tree |
| Options syntax | All options must have `+`/`-` prefix: `Options +FollowSymLinks -Indexes` |
| App config | Setup wizard writes `.env` — never pre-create or hand-edit it |
| phpMyAdmin login | Use `dbadmin`, not `root` — root uses socket auth |
| SSL | Cloudflare must be Full (Strict) — never Flexible |
| Passwords with `!` | Use heredoc `<< 'SQL'` in bash to avoid history expansion |

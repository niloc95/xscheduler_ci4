# Environment Configuration Guide

The application uses a `.env` file for environment-specific settings. The setup wizard generates a production-ready `.env` from `.env.example` during first-time installation. For development, copy and edit the file manually.

---

## Quick Setup

```bash
cp .env.example .env
php spark key:generate
```

Then either run the setup wizard at `/setup` (recommended) or configure the database manually in `.env`.

---

## Environment

```env
CI_ENVIRONMENT = production   # or 'development' for local work
```

**Development:** enables debug output, relaxed security, Mailpit for email.  
**Production:** disables debug, enforces HTTPS and CSP, strict session settings.

---

## Database

Runtime database is **MySQL/MariaDB only**. The setup wizard sets these automatically.

```env
database.default.hostname = localhost
database.default.database = webschedulr_prod
database.default.username = your_db_user
database.default.password = your_secure_password
database.default.DBDriver = MySQLi
database.default.DBPrefix = xs_
database.default.port = 3306
```

---

## Encryption

```env
encryption.key = your_32_character_encryption_key_here
```

Generate with `php spark key:generate`. Required for sessions and cookies. Never commit to version control.

---

## Session

```env
session.driver = 'CodeIgniter\Session\Handlers\FileHandler'
session.matchIP = false
session.timeToUpdate = 900
session.regenerateDestroy = false
```

Do not set `session.savePath` unless your host requires a specific absolute path. The default (`WRITEPATH . 'session'`) works for standard installs.

---

## Security

```env
security.CSRFProtection = true
security.CSRFTokenName = 'csrf_token'
security.CSRFCookieName = 'csrf_cookie'
security.CSRFExpire = 7200
security.CSRFRegenerate = true
security.CSRFSameSite = 'Strict'

app.CSPEnabled = true
# Uncomment after SSL is configured:
# app.forceGlobalSecureRequests = true

cookie.httponly = true
cookie.samesite = 'Strict'
```

---

## Email

Email transport is configured through **Settings → Integrations** in the admin UI (stored in `xs_business_integrations`), not via `.env`. The `.env` email keys below are a **development fallback only** — used when no active DB integration exists and `ENVIRONMENT === 'development'`.

```env
# Development Mailpit (local email testing):
# email.protocol = smtp
# email.SMTPHost = 127.0.0.1
# email.SMTPPort = 1025
# email.fromEmail = dev@webschedulr.local
# email.fromName = WebSchedulr Dev

# Production SMTP (fallback only — prefer DB integration):
# email.protocol = smtp
# email.SMTPHost = your-smtp-server.com
# email.SMTPUser = your-email@domain.com
# email.SMTPPass = your-email-password
# email.SMTPPort = 587
# email.SMTPCrypto = tls
# email.fromEmail = noreply@yourdomain.com
# email.fromName = WebSchedulr
```

See `Agent_Context_v2.md §7.3` for the full email transport resolution priority.

---

## Application Locale

```env
app.timezone = 'UTC'
app.defaultLocale = 'en'
app.negotiateLocale = false
app.supportedLocales = ['en']
```

Display timezone and date format are configured in **Settings → Localization** (stored in `xs_settings`), not here.

---

## Setup Wizard

```env
setup.enabled = true
setup.allowMultipleRuns = false
```

After setup completes, `setup.enabled` is set to `false` in `.env` automatically. The completion state is also tracked via `writable/setup_complete.flag`.

---

## Feature Flags

```env
features.emailNotifications = true
features.smsNotifications = false
features.multiLanguage = false
features.apiAccess = false
```

---

## Logger

```env
logger.threshold = 4   # 4=Error (production). Use 7 for development verbosity.
```

---

## Deployment Checklist

- [ ] `cp .env.example .env`
- [ ] Run setup wizard at `/setup` — configures DB, admin account, generates encryption key
- [ ] Confirm `CI_ENVIRONMENT = production`
- [ ] Enable HTTPS: uncomment `app.forceGlobalSecureRequests = true` after SSL is active
- [ ] Set file permissions on `writable/` to `755`
- [ ] Verify error logs at `writable/logs/`

---

## Common Issues

| Error | Cause | Fix |
|---|---|---|
| "Unable to connect to database" | Wrong credentials or host | Verify DB settings; confirm DB server is running |
| "The encryption key is not set" | Missing `encryption.key` | Run `php spark key:generate` |
| "Session directory not writable" | Permissions | `chmod 755 writable/session/` |
| 404 / routing problems | mod_rewrite disabled or wrong baseURL | Check `.htaccess`; set `app.baseURL` explicitly |
| Email not sending | No active SMTP integration | Configure via Settings → Integrations or add dev `.env` keys |

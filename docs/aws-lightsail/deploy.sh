#!/bin/bash
# ══════════════════════════════════════════════════════════════
#  webScheduler — Deploy New Tenant
#  Usage:   sudo bash /var/www/deploy.sh <tenant-name>
#  Example: sudo bash /var/www/deploy.sh dental
#
#  Before running:
#  1. Upload your webscheduler release zip to /var/www/webscheduler/
#  2. Run this script — it picks up the latest zip automatically
# ══════════════════════════════════════════════════════════════

NAME=$1
DOMAIN_SUFFIX="webscheduler.co.za"
ZIP_SOURCE="/var/www/webscheduler"
WEB_ROOT="/var/www"
TENANT_DIR="${WEB_ROOT}/${NAME}"
TENANT_DOMAIN="${NAME}.${DOMAIN_SUFFIX}"
APACHE_CONF="/etc/apache2/sites-available/${NAME}.conf"

# ── Colours ───────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok()   { echo -e "${GREEN}✓${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC}  $1"; }
fail() { echo -e "${RED}✗  ERROR: $1${NC}"; exit 1; }

# ── Validate ──────────────────────────────────────────────────
[[ -z "$NAME" ]]        && fail "Usage: sudo bash /var/www/deploy.sh <tenant-name>"
[[ "$EUID" -ne 0 ]]     && fail "Run as root: sudo bash /var/www/deploy.sh $NAME"
[[ -d "$TENANT_DIR" ]]  && fail "${TENANT_DIR} already exists. Aborting."
[[ -f "$APACHE_CONF" ]] && fail "Apache config ${APACHE_CONF} already exists. Aborting."

# ── Find latest zip ───────────────────────────────────────────
ZIP=$(ls -t "${ZIP_SOURCE}"/*.zip 2>/dev/null | head -1)
[[ -z "$ZIP" ]] && fail "No zip file found in ${ZIP_SOURCE}/. Upload your release zip first."

echo ""
echo "══════════════════════════════════════════"
echo "  Tenant:  ${NAME}"
echo "  Domain:  ${TENANT_DOMAIN}"
echo "  Zip:     $(basename $ZIP)"
echo "  Target:  ${TENANT_DIR}"
echo "══════════════════════════════════════════"
echo ""

# ── 1. Extract app ────────────────────────────────────────────
echo "[1/5] Extracting app..."
mkdir -p "$TENANT_DIR"
unzip -q "$ZIP" -d "$TENANT_DIR"

# Flatten if zip extracted into a single subdirectory
SUBDIR_COUNT=$(find "$TENANT_DIR" -maxdepth 1 -mindepth 1 -type d | wc -l)
if [[ "$SUBDIR_COUNT" -eq 1 ]] && [[ ! -d "$TENANT_DIR/public" ]]; then
    SUBDIR=$(find "$TENANT_DIR" -maxdepth 1 -mindepth 1 -type d)
    shopt -s dotglob
    mv "$SUBDIR"/* "$TENANT_DIR/"
    shopt -u dotglob
    rmdir "$SUBDIR"
fi

[[ ! -d "$TENANT_DIR/public" ]] && fail "Extraction failed — public/ not found. Check your zip structure."

mkdir -p "$TENANT_DIR/writable"/{cache,logs,session,uploads}
ok "App extracted to ${TENANT_DIR}"

# ── 2. Permissions ────────────────────────────────────────────
echo "[2/5] Setting permissions..."
chown -R www-data:www-data "$TENANT_DIR"
chmod -R 755 "$TENANT_DIR"
chmod -R 775 "$TENANT_DIR/writable"
# App root needs 775 so the setup wizard can write .env
chmod 775 "$TENANT_DIR"
ok "Permissions set"

# ── 3. .env ───────────────────────────────────────────────────
echo "[3/5] Preparing .env..."
cat > "$TENANT_DIR/.env" <<EOF
CI_ENVIRONMENT = production
app.baseURL = ''
app.indexPage = ''
EOF
chown www-data:www-data "$TENANT_DIR/.env"
chmod 664 "$TENANT_DIR/.env"
ok ".env ready (setup wizard will write DB credentials and encryption key)"

# ── 4. Apache VirtualHost ─────────────────────────────────────
echo "[4/5] Configuring Apache..."
cat > "$APACHE_CONF" <<EOF
<VirtualHost *:80>
    ServerName ${TENANT_DOMAIN}
    Redirect permanent / https://${TENANT_DOMAIN}/
</VirtualHost>

<VirtualHost *:443>
    ServerName ${TENANT_DOMAIN}

    DocumentRoot ${TENANT_DIR}/public

    <Directory ${TENANT_DIR}/public>
        Options +FollowSymLinks -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
    </IfModule>

    ErrorLog \${APACHE_LOG_DIR}/${NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${NAME}_access.log combined

    # SSL — certbot injects certificate lines here
</VirtualHost>
EOF

a2ensite "${NAME}.conf" > /dev/null
apache2ctl configtest 2>&1 | grep -E "Syntax|Error"
systemctl reload apache2
ok "Apache configured and reloaded"

# ── 5. SSL ────────────────────────────────────────────────────
echo ""
echo "[5/5] SSL certificate"
warn "In Cloudflare, set '${TENANT_DOMAIN}' to DNS only (grey cloud) before continuing."
echo ""
read -rp "  Press ENTER when Cloudflare proxy is off (or Ctrl+C to skip SSL): "
echo ""

certbot --apache -d "$TENANT_DOMAIN"

echo ""
warn "Switch Cloudflare back to Proxied (orange cloud) now."

# ── Done ──────────────────────────────────────────────────────
echo ""
echo "══════════════════════════════════════════"
ok "Tenant '${NAME}' is live!"
echo ""
echo "  Run the setup wizard at:"
echo "  https://${TENANT_DOMAIN}/"
echo ""
echo "  Apache error log:"
echo "  /var/log/apache2/${NAME}_error.log"
echo "══════════════════════════════════════════"
echo ""

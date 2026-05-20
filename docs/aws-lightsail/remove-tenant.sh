#!/bin/bash
# ══════════════════════════════════════════════════════════════
#  webScheduler — Remove Tenant
#  Usage:   sudo bash /var/www/remove-tenant.sh <tenant-name>
#  Example: sudo bash /var/www/remove-tenant.sh dental
#
#  Removes:
#  - Apache vhost config + site
#  - SSL certificate (via certbot)
#  - Tenant web directory (/var/www/<tenant-name>)
# ══════════════════════════════════════════════════════════════

NAME=$1
DOMAIN_SUFFIX="webscheduler.co.za"
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
[[ -z "$NAME" ]]    && fail "Usage: sudo bash /var/www/remove-tenant.sh <tenant-name>"
[[ "$EUID" -ne 0 ]] && fail "Run as root: sudo bash /var/www/remove-tenant.sh $NAME"

# Guard against accidentally nuking top-level dirs
[[ "$NAME" == "webscheduler" ]] && fail "Cannot remove the shared upload directory."
[[ "$NAME" == "html" ]]         && fail "Cannot remove /var/www/html."

if [[ ! -d "$TENANT_DIR" ]] && [[ ! -f "$APACHE_CONF" ]]; then
    fail "No tenant '${NAME}' found (no directory or Apache config exists)."
fi

echo ""
echo "══════════════════════════════════════════"
echo "  Tenant:  ${NAME}"
echo "  Domain:  ${TENANT_DOMAIN}"
echo "  Dir:     ${TENANT_DIR}"
echo "  Conf:    ${APACHE_CONF}"
echo "══════════════════════════════════════════"
warn "This will permanently delete all files and configuration for '${NAME}'."
echo ""
read -rp "  Type the tenant name to confirm: " CONFIRM
echo ""

[[ "$CONFIRM" != "$NAME" ]] && fail "Confirmation did not match. Aborting."

# ── 1. Disable Apache site ────────────────────────────────────
echo "[1/4] Disabling Apache site..."
if [[ -f "$APACHE_CONF" ]]; then
    a2dissite "${NAME}.conf" > /dev/null 2>&1
    rm -f "$APACHE_CONF"
    ok "Apache config removed"
else
    warn "Apache config not found — skipping"
fi

# ── 2. Reload Apache ──────────────────────────────────────────
echo "[2/4] Reloading Apache..."
apache2ctl configtest 2>&1 | grep -E "Syntax|Error"
systemctl reload apache2
ok "Apache reloaded"

# ── 3. Remove SSL certificate ─────────────────────────────────
echo "[3/4] Removing SSL certificate..."
if certbot certificates 2>/dev/null | grep -q "$TENANT_DOMAIN"; then
    certbot delete --cert-name "$TENANT_DOMAIN" --non-interactive
    ok "SSL certificate removed"
else
    warn "No certbot certificate found for ${TENANT_DOMAIN} — skipping"
fi

# ── 4. Remove tenant directory ────────────────────────────────
echo "[4/4] Removing tenant directory..."
if [[ -d "$TENANT_DIR" ]]; then
    rm -rf "$TENANT_DIR"
    ok "Directory ${TENANT_DIR} removed"
else
    warn "Directory ${TENANT_DIR} not found — skipping"
fi

# ── Done ──────────────────────────────────────────────────────
echo ""
echo "══════════════════════════════════════════"
ok "Tenant '${NAME}' removed."
echo ""
echo "  Remember to:"
echo "  - Delete the DNS A-record for ${TENANT_DOMAIN}"
echo "  - Drop the database if no longer needed:"
echo "    sudo mysql -e \"DROP DATABASE IF EXISTS <db_name>;\""
echo "══════════════════════════════════════════"
echo ""

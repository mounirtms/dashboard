#!/bin/bash
##########################################################
# CRITICAL FIX #1: Enable Port 80 Listening (cPanel/EasyApache)
# Adapted for /etc/apache2/conf/httpd.conf configuration
##########################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/fix-port80.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
HTTPD_CONF="/etc/apache2/conf/httpd.conf"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Port 80 Configuration Fix" | tee -a "$LOG_FILE"

# 1. Backup httpd.conf
echo -e "\n${YELLOW}[1/5] Backing up httpd.conf${NC}" | tee -a "$LOG_FILE"
cp "$HTTPD_CONF" "${HTTPD_CONF}.backup.$TIMESTAMP"
echo "✓ Backup created: ${HTTPD_CONF}.backup.$TIMESTAMP" | tee -a "$LOG_FILE"

# 2. Show current Listen directives
echo -e "\n${YELLOW}[2/5] Current Listen directives:${NC}" | tee -a "$LOG_FILE"
grep -E "^Listen" "$HTTPD_CONF" | tee -a "$LOG_FILE"

# 3. Add port 80 Listen directives
echo -e "\n${YELLOW}[3/5] Adding port 80 Listen directives${NC}" | tee -a "$LOG_FILE"

# Add Listen 80 directives after the existing Listen 81 directives
sed -i "/^Listen 0.0.0.0:81/a Listen 0.0.0.0:80" "$HTTPD_CONF"
sed -i "/^Listen \[::\]:81/a Listen [::]:80" "$HTTPD_CONF"

echo "✓ Added Listen 0.0.0.0:80 and Listen [::]:80" | tee -a "$LOG_FILE"

# 4. Create port 80 redirect configuration
echo -e "\n${YELLOW}[4/5] Creating port 80 HTTP redirect configuration${NC}" | tee -a "$LOG_FILE"

mkdir -p /etc/apache2/conf.d

cat > /etc/apache2/conf.d/port80-redirect.conf << 'REDIRECT_EOF'
# Port 80 HTTP to HTTPS Redirect Configuration
# Created: 2026-05-07

<VirtualHost *:80>
    ServerName _default_
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
    </IfModule>
</VirtualHost>

# Per-domain redirects
<VirtualHost *:80>
    ServerName technostationery.com
    ServerAlias www.technostationery.com
    Redirect 301 / https://technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName beta.technostationery.com
    Redirect 301 / https://beta.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName dev.technostationery.com
    Redirect 301 / https://dev.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName dashboard.technostationery.com
    Redirect 301 / https://dashboard.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName lms.technostationery.com
    Redirect 301 / https://lms.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName pim.technostationery.com
    Redirect 301 / https://pim.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName ded701.inmotionhosting.com
    Redirect 301 / https://ded701.inmotionhosting.com/
</VirtualHost>
REDIRECT_EOF

echo "✓ Port 80 redirect configuration created" | tee -a "$LOG_FILE"

# 5. Test Apache configuration
echo -e "\n${YELLOW}[5/5] Testing Apache configuration syntax${NC}" | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE" | grep -q "Syntax OK"; then
    echo -e "${GREEN}✓ Apache configuration syntax is valid${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${RED}✗ Apache configuration syntax error${NC}" | tee -a "$LOG_FILE"
    echo "Restoring backup..." | tee -a "$LOG_FILE"
    cp "${HTTPD_CONF}.backup.$TIMESTAMP" "$HTTPD_CONF"
    exit 1
fi

# 6. Rebuild Apache configuration (cPanel)
echo -e "\n${YELLOW}[6/7] Rebuilding Apache configuration${NC}" | tee -a "$LOG_FILE"
if command -v /scripts/rebuildhttpdconf &> /dev/null; then
    /scripts/rebuildhttpdconf 2>&1 | tee -a "$LOG_FILE"
    echo "✓ Apache configuration rebuilt" | tee -a "$LOG_FILE"
else
    echo "⚠ rebuildhttpdconf not found, skipping" | tee -a "$LOG_FILE"
fi

# 7. Reload Apache
echo -e "\n${YELLOW}[7/7] Reloading Apache${NC}" | tee -a "$LOG_FILE"
systemctl reload httpd 2>&1 | tee -a "$LOG_FILE"
echo -e "${GREEN}✓ Apache reloaded${NC}" | tee -a "$LOG_FILE"

# 8. Verify port 80 is listening
echo -e "\n${YELLOW}Verifying port 80 is now listening...${NC}" | tee -a "$LOG_FILE"
sleep 2

if netstat -tlnp 2>/dev/null | grep ":80 "; then
    echo -e "${GREEN}✓ Port 80 is now listening${NC}" | tee -a "$LOG_FILE"
    netstat -tlnp | grep ":80" | tee -a "$LOG_FILE"
else
    echo -e "${RED}⚠ Port 80 may not be listening yet${NC}" | tee -a "$LOG_FILE"
fi

# 9. Test HTTP redirect
echo -e "\n${YELLOW}Testing HTTP redirect...${NC}" | tee -a "$LOG_FILE"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:80/ 2>&1)
if [[ "$HTTP_CODE" == "301" || "$HTTP_CODE" == "302" ]]; then
    echo -e "${GREEN}✓ Port 80 responding with $HTTP_CODE redirect${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${YELLOW}⚠ Port 80 response: $HTTP_CODE${NC}" | tee -a "$LOG_FILE"
fi

# Summary
echo -e "\n${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}Port 80 Configuration Fix Completed${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Changes made:" | tee -a "$LOG_FILE"
echo "  ✓ Added Listen 80 to $HTTPD_CONF" | tee -a "$LOG_FILE"
echo "  ✓ Created /etc/apache2/conf.d/port80-redirect.conf" | tee -a "$LOG_FILE"
echo "  ✓ Reloaded Apache" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Next Steps:" | tee -a "$LOG_FILE"
echo "  1. Run: bash fix-varnish-backend.sh" | tee -a "$LOG_FILE"
echo "  2. Verify: curl -I http://technostationery.com/" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"

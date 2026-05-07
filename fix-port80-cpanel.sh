#!/bin/bash
##########################################################
# CRITICAL FIX #1: Enable Port 80 on cPanel System
# Uses cPanel pre/post include files to add port 80
##########################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/fix-port80-cpanel.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Port 80 Configuration Fix (cPanel Method)" | tee -a "$LOG_FILE"

# Create pre_virtualhost_global.conf for port 80
echo -e "\n${YELLOW}[1/4] Creating cPanel include for port 80${NC}" | tee -a "$LOG_FILE"

mkdir -p /etc/apache2/conf.d/includes

cat > /etc/apache2/conf.d/includes/pre_virtualhost_global.conf << 'INCLUDE_EOF'
# Port 80 Listen Directive
# This file persists through rebuildhttpdconf
Listen 0.0.0.0:80
Listen [::]:80

# Port 80 HTTP to HTTPS Redirect
<VirtualHost *:80>
    ServerName _default_
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
    </IfModule>
</VirtualHost>
INCLUDE_EOF

echo "✓ Created /etc/apache2/conf.d/includes/pre_virtualhost_global.conf" | tee -a "$LOG_FILE"

# Create per-domain port 80 redirects
echo -e "\n${YELLOW}[2/4] Creating per-domain port 80 redirects${NC}" | tee -a "$LOG_FILE"

cat > /etc/apache2/conf.d/includes/port80-redirects.conf << 'REDIRECTS_EOF'
# Per-domain Port 80 Redirects
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
REDIRECTS_EOF

echo "✓ Created /etc/apache2/conf.d/includes/port80-redirects.conf" | tee -a "$LOG_FILE"

# Test configuration
echo -e "\n${YELLOW}[3/4] Testing Apache configuration${NC}" | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE" | grep -q "Syntax OK"; then
    echo -e "${GREEN}✓ Configuration syntax valid${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${RED}✗ Configuration syntax error${NC}" | tee -a "$LOG_FILE"
    exit 1
fi

# Restart Apache (full restart needed for Listen changes)
echo -e "\n${YELLOW}[4/4] Restarting Apache (full restart for Listen changes)${NC}" | tee -a "$LOG_FILE"
systemctl restart httpd 2>&1 | tee -a "$LOG_FILE"
echo -e "${GREEN}✓ Apache restarted${NC}" | tee -a "$LOG_FILE"

# Wait for Apache to fully start
sleep 3

# Verify port 80
echo -e "\n${YELLOW}Verifying port 80...${NC}" | tee -a "$LOG_FILE"
if netstat -tlnp 2>/dev/null | grep ":80 " | grep httpd; then
    echo -e "${GREEN}✓ Port 80 is listening!${NC}" | tee -a "$LOG_FILE"
    netstat -tlnp | grep ":80 " | grep httpd | tee -a "$LOG_FILE"
else
    echo -e "${RED}✗ Port 80 not listening${NC}" | tee -a "$LOG_FILE"
    echo "Checking what's using port 80..." | tee -a "$LOG_FILE"
    netstat -tlnp | grep ":80 " | tee -a "$LOG_FILE"
fi

# Test HTTP redirect
echo -e "\n${YELLOW}Testing HTTP redirect...${NC}" | tee -a "$LOG_FILE"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:80/ 2>&1 || echo "000")
if [[ "$HTTP_CODE" == "301" || "$HTTP_CODE" == "302" ]]; then
    echo -e "${GREEN}✓ Port 80 redirecting ($HTTP_CODE)${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${YELLOW}⚠ HTTP response code: $HTTP_CODE${NC}" | tee -a "$LOG_FILE"
fi

# Summary
echo -e "\n${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}Port 80 Fix Completed${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo "Files created:" | tee -a "$LOG_FILE"
echo "  ✓ /etc/apache2/conf.d/includes/pre_virtualhost_global.conf" | tee -a "$LOG_FILE"
echo "  ✓ /etc/apache2/conf.d/includes/port80-redirects.conf" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Next: Run bash fix-varnish-backend.sh" | tee -a "$LOG_FILE"
echo "Log: $LOG_FILE" | tee -a "$LOG_FILE"

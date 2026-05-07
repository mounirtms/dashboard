#!/bin/bash

##########################################################
# CRITICAL FIX #1: Enable Port 80 Listening
# This script adds HTTP port 80 to Apache configuration
# REQUIRED: Without this, Cloudflare cannot route traffic
##########################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/fix-port80.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Port 80 Configuration Fix" | tee -a "$LOG_FILE"

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}" | tee -a "$LOG_FILE"
    else
        echo -e "${RED}✗ $2${NC}" | tee -a "$LOG_FILE"
        exit 1
    fi
}

# 1. Backup current ports.conf
echo -e "\n${YELLOW}[1/5] Backing up current ports.conf${NC}" | tee -a "$LOG_FILE"
cp /etc/apache2/ports.conf "/etc/apache2/ports.conf.backup.$TIMESTAMP"
print_status $? "Backup created: /etc/apache2/ports.conf.backup.$TIMESTAMP"

# 2. Check current ports.conf
echo -e "\n${YELLOW}[2/5] Current ports.conf contents:${NC}" | tee -a "$LOG_FILE"
cat /etc/apache2/ports.conf | tee -a "$LOG_FILE"

# 3. Update ports.conf to include port 80
echo -e "\n${YELLOW}[3/5] Updating ports.conf to include port 80${NC}" | tee -a "$LOG_FILE"
cat > /etc/apache2/ports.conf << 'PORTS_EOF'
# If you just change the port or add more ports here, you will likely also
# have to change the VirtualHost statement in
# /etc/apache2/sites-enabled/000-default-ssl.conf

Listen 80
Listen 81
Listen 443 https

# vim: syntax=apache ts=4 sw=4 sts=4 sr noet
PORTS_EOF

print_status $? "ports.conf updated with port 80"

# 4. Create port 80 redirect configuration
echo -e "\n${YELLOW}[4/5] Creating port 80 HTTP redirect vhost${NC}" | tee -a "$LOG_FILE"

# Backup any existing redirect config
if [ -f /etc/apache2/conf.d/port80-redirect.conf ]; then
    cp /etc/apache2/conf.d/port80-redirect.conf "/etc/apache2/conf.d/port80-redirect.conf.backup.$TIMESTAMP"
    echo "Existing config backed up: /etc/apache2/conf.d/port80-redirect.conf.backup.$TIMESTAMP" | tee -a "$LOG_FILE"
fi

cat > /etc/apache2/conf.d/port80-redirect.conf << 'REDIRECT_EOF'
# ========================================================
# Port 80 HTTP to HTTPS Redirect Configuration
# Created: 2026-05-07
# Purpose: Redirect all HTTP traffic to HTTPS
# ========================================================

# Global HTTP VirtualHost on port 80
<VirtualHost *:80>
    # Match any domain
    ServerName _default_
    
    # Enable mod_rewrite
    <IfModule mod_rewrite.c>
        RewriteEngine On
        
        # Skip health check endpoint (no redirect)
        RewriteRule ^\.health_check$ - [L]
        
        # Redirect all HTTP to HTTPS with 301 (permanent)
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
    </IfModule>
    
    # Fallback redirect for requests that don't use mod_rewrite
    Redirect 301 / https://%{HTTP_HOST}/
    
    # Health check endpoint (returns 200 OK without redirect)
    Alias /.health_check /var/www/html/.health_check
    <Directory /var/www/html>
        Require all granted
    </Directory>
</VirtualHost>

# Per-domain explicit redirects (optional, for clarity)
<VirtualHost *:80>
    ServerName technostationery.com
    ServerAlias www.technostationery.com mail.technostationery.com
    Redirect 301 / https://technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName beta.technostationery.com
    ServerAlias www.beta.technostationery.com mail.beta.technostationery.com
    Redirect 301 / https://beta.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName dev.technostationery.com
    ServerAlias www.dev.technostationery.com mail.dev.technostationery.com
    Redirect 301 / https://dev.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName dashboard.technostationery.com
    ServerAlias www.dashboard.technostationery.com mail.dashboard.technostationery.com
    Redirect 301 / https://dashboard.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName lms.technostationery.com
    ServerAlias www.lms.technostationery.com mail.lms.technostationery.com
    Redirect 301 / https://lms.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName pim.technostationery.com
    ServerAlias www.pim.technostationery.com mail.pim.technostationery.com
    Redirect 301 / https://pim.technostationery.com/
</VirtualHost>

<VirtualHost *:80>
    ServerName ded701.inmotionhosting.com
    Redirect 301 / https://ded701.inmotionhosting.com/
</VirtualHost>
REDIRECT_EOF

print_status $? "Port 80 redirect configuration created"

# 5. Test Apache configuration
echo -e "\n${YELLOW}[5/5] Testing Apache configuration syntax${NC}" | tee -a "$LOG_FILE"
httpd -t 2>&1 | tee -a "$LOG_FILE"
TEST_RESULT=${PIPESTATUS[0]}

if [ $TEST_RESULT -eq 0 ]; then
    print_status 0 "Apache configuration syntax is valid"
else
    print_status 1 "Apache configuration syntax error - not reloading"
fi

# 6. Reload Apache to apply changes
echo -e "\n${YELLOW}[6/6] Reloading Apache to apply configuration${NC}" | tee -a "$LOG_FILE"
service httpd reload 2>&1 | tee -a "$LOG_FILE"
print_status $? "Apache reloaded successfully"

# 7. Verify port 80 is listening
echo -e "\n${YELLOW}Verifying port 80 is now listening...${NC}" | tee -a "$LOG_FILE"
sleep 2

if ss -tlnp 2>/dev/null | grep -q ":80 "; then
    print_status 0 "Port 80 is now listening"
    ss -tlnp | grep ":80"
else
    echo -e "${YELLOW}Checking with netstat...${NC}" | tee -a "$LOG_FILE"
    netstat -tlnp 2>/dev/null | grep ":80" || echo "Port 80 check inconclusive" | tee -a "$LOG_FILE"
fi

# 8. Test connectivity
echo -e "\n${YELLOW}[Testing port 80 HTTP connectivity]${NC}" | tee -a "$LOG_FILE"
if curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:80/ | grep -q "301"; then
    echo -e "${GREEN}✓ Port 80 responding with 301 redirect${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${YELLOW}⚠ Port 80 response check may need verification${NC}" | tee -a "$LOG_FILE"
fi

# 9. Summary
echo -e "\n${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}Port 80 Configuration Fix Completed${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "\nConfiguration Changes:" | tee -a "$LOG_FILE"
echo "  ✓ Added 'Listen 80' to /etc/apache2/ports.conf" | tee -a "$LOG_FILE"
echo "  ✓ Created /etc/apache2/conf.d/port80-redirect.conf" | tee -a "$LOG_FILE"
echo "  ✓ Verified Apache configuration syntax" | tee -a "$LOG_FILE"
echo "  ✓ Reloaded Apache web server" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Next Steps:" | tee -a "$LOG_FILE"
echo "  1. Run: fix-varnish-backend.sh" | tee -a "$LOG_FILE"
echo "  2. Test: curl http://127.0.0.1:80/" | tee -a "$LOG_FILE"
echo "  3. Verify: curl -v https://technostationery.com/" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"

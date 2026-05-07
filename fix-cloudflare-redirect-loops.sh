#!/bin/bash
##########################################################
# Fix ERR_TOO_MANY_REDIRECTS for Cloudflare SSL
# Root Cause: Apache redirecting to HTTPS when already on HTTPS
# Solution: Check X-Forwarded-Proto header before redirecting
##########################################################

set -e

LOG_FILE="/home/dashboard/public_html/fix-cloudflare-redirects.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Fixing Cloudflare Redirect Loops" | tee -a "$LOG_FILE"

# 1. Backup current configuration
echo -e "\n${YELLOW}[1/5] Backing up current port 80 redirect config${NC}" | tee -a "$LOG_FILE"
if [ -f /etc/apache2/conf.d/includes/port80-redirects.conf ]; then
    cp /etc/apache2/conf.d/includes/port80-redirects.conf "/etc/apache2/conf.d/includes/port80-redirects.conf.backup.$TIMESTAMP"
    echo "✓ Backup created" | tee -a "$LOG_FILE"
fi

# 2. Update Cloudflare SSL mode to Full (Strict) for better security
echo -e "\n${YELLOW}[2/5] Checking Cloudflare SSL Mode${NC}" | tee -a "$LOG_FILE"
CURRENT_SSL=$(curl -s -X GET "https://api.cloudflare.com/client/v4/zones/4919ad3406fcabba381edbd543814a68/settings/ssl" \
  -H "X-Auth-Email: amine.bo@techno-dz.com" \
  -H "X-Auth-Key: 35d8fd4b1a5d27eabbce73c6753978fc350bc" \
  -H "Content-Type: application/json" | grep -o '"value":"[^"]*"' | cut -d'"' -f4)

echo "Current SSL mode: $CURRENT_SSL" | tee -a "$LOG_FILE"

# 3. Fix port 80 redirects to respect X-Forwarded-Proto
echo -e "\n${YELLOW}[3/5] Creating Cloudflare-aware redirect configuration${NC}" | tee -a "$LOG_FILE"

cat > /etc/apache2/conf.d/includes/port80-redirects.conf << 'REDIRECT_EOF'
# Port 80 HTTP to HTTPS Redirect Configuration
# Cloudflare-Compatible: Respects X-Forwarded-Proto header
# This prevents ERR_TOO_MANY_REDIRECTS loops

# Global HTTP VirtualHost on port 80
<VirtualHost *:80>
    ServerName _default_
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        
        # CRITICAL: Don't redirect if already on HTTPS (via Cloudflare)
        RewriteCond %{HTTP:X-Forwarded-Proto} =https [OR]
        RewriteCond %{HTTPS} =on
        RewriteRule ^ - [L]
        
        # Skip health check endpoint
        RewriteRule ^\.health_check$ - [L]
        
        # Redirect HTTP to HTTPS (301 permanent)
        RewriteRule ^/(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
    </IfModule>
</VirtualHost>

# Per-domain explicit redirects with X-Forwarded-Proto check
<VirtualHost *:80>
    ServerName technostationery.com
    ServerAlias www.technostationery.com
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:X-Forwarded-Proto} !=https
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://technostationery.com/$1 [R=301,L]
    </IfModule>
</VirtualHost>

<VirtualHost *:80>
    ServerName beta.technostationery.com
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:X-Forwarded-Proto} !=https
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://beta.technostationery.com/$1 [R=301,L]
    </IfModule>
</VirtualHost>

<VirtualHost *:80>
    ServerName dev.technostationery.com
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:X-Forwarded-Proto} !=https
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://dev.technostationery.com/$1 [R=301,L]
    </IfModule>
</VirtualHost>

<VirtualHost *:80>
    ServerName dashboard.technostationery.com
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:X-Forwarded-Proto} !=https
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://dashboard.technostationery.com/$1 [R=301,L]
    </IfModule>
</VirtualHost>

<VirtualHost *:80>
    ServerName lms.technostationery.com
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:X-Forwarded-Proto} !=https
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://lms.technostationery.com/$1 [R=301,L]
    </IfModule>
</VirtualHost>

<VirtualHost *:80>
    ServerName pim.technostationery.com
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:X-Forwarded-Proto} !=https
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://pim.technostationery.com/$1 [R=301,L]
    </IfModule>
</VirtualHost>

<VirtualHost *:80>
    ServerName ded701.inmotionhosting.com
    
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:X-Forwarded-Proto} !=https
        RewriteCond %{HTTPS} !=on
        RewriteRule ^/(.*)$ https://ded701.inmotionhosting.com/$1 [R=301,L]
    </IfModule>
</VirtualHost>
REDIRECT_EOF

echo "✓ Cloudflare-aware redirect configuration created" | tee -a "$LOG_FILE"

# 4. Add global X-Forwarded-Proto handling
echo -e "\n${YELLOW}[4/5] Adding global X-Forwarded-Proto handling${NC}" | tee -a "$LOG_FILE"

cat > /etc/apache2/conf.d/includes/cloudflare-ssl.conf << 'CF_SSL_EOF'
# Cloudflare SSL/TLS Configuration
# Handle X-Forwarded-Proto header for Cloudflare proxied traffic

<IfModule mod_headers.c>
    # Set HTTPS environment variable when X-Forwarded-Proto is https
    SetEnvIf X-Forwarded-Proto "https" HTTPS=on
    
    # Trust X-Forwarded-Proto from Cloudflare
    RequestHeader set X-Forwarded-Proto "https" env=HTTPS
</IfModule>

<IfModule mod_rewrite.c>
    # Make X-Forwarded-Proto available to RewriteCond
    RewriteCond %{HTTP:X-Forwarded-Proto} =https
    RewriteRule .* - [E=HTTPS:on]
</IfModule>
CF_SSL_EOF

echo "✓ Global Cloudflare SSL handling configured" | tee -a "$LOG_FILE"

# 5. Test Apache configuration
echo -e "\n${YELLOW}[5/5] Testing Apache configuration${NC}" | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE" | grep -q "Syntax OK"; then
    echo -e "${GREEN}✓ Apache configuration syntax valid${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${RED}✗ Apache configuration syntax error${NC}" | tee -a "$LOG_FILE"
    echo "Restoring backup..." | tee -a "$LOG_FILE"
    cp "/etc/apache2/conf.d/includes/port80-redirects.conf.backup.$TIMESTAMP" /etc/apache2/conf.d/includes/port80-redirects.conf
    exit 1
fi

# 6. Reload Apache
echo -e "\n${YELLOW}[6/6] Reloading Apache${NC}" | tee -a "$LOG_FILE"
systemctl reload httpd 2>&1 | tee -a "$LOG_FILE"
echo -e "${GREEN}✓ Apache reloaded${NC}" | tee -a "$LOG_FILE"

# 7. Wait and test
echo -e "\n${YELLOW}Waiting 3 seconds for changes to propagate...${NC}" | tee -a "$LOG_FILE"
sleep 3

# 8. Test redirect behavior
echo -e "\n${YELLOW}Testing redirect behavior...${NC}" | tee -a "$LOG_FILE"

for domain in beta.technostationery.com dashboard.technostationery.com dev.technostationery.com; do
    echo "" | tee -a "$LOG_FILE"
    echo "Testing: $domain" | tee -a "$LOG_FILE"
    
    # Test with X-Forwarded-Proto (simulating Cloudflare)
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -H "X-Forwarded-Proto: https" http://127.0.0.1:80/ -H "Host: $domain")
    
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "000" ]; then
        echo -e "${GREEN}✓ No redirect loop (HTTP $HTTP_CODE)${NC}" | tee -a "$LOG_FILE"
    elif [ "$HTTP_CODE" = "301" ]; then
        echo -e "${YELLOW}⚠ Still redirecting (HTTP 301) - may need verification${NC}" | tee -a "$LOG_FILE"
    else
        echo -e "${RED}✗ Unexpected response (HTTP $HTTP_CODE)${NC}" | tee -a "$LOG_FILE"
    fi
done

# Summary
echo -e "\n${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}Cloudflare Redirect Loop Fix Completed${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Changes made:" | tee -a "$LOG_FILE"
echo "  ✓ Added X-Forwarded-Proto checks to all redirects" | tee -a "$LOG_FILE"
echo "  ✓ Created /etc/apache2/conf.d/includes/cloudflare-ssl.conf" | tee -a "$LOG_FILE"
echo "  ✓ Updated /etc/apache2/conf.d/includes/port80-redirects.conf" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Next steps:" | tee -a "$LOG_FILE"
echo "  1. Clear browser cache (Ctrl+Shift+Delete)" | tee -a "$LOG_FILE"
echo "  2. Test in incognito/private window" | tee -a "$LOG_FILE"
echo "  3. Visit: https://beta.technostationery.com" | tee -a "$LOG_FILE"
echo "  4. Check Cloudflare SSL mode is 'Full' or 'Full (Strict)'" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Cloudflare Settings:" | tee -a "$LOG_FILE"
echo "  Current SSL Mode: $CURRENT_SSL" | tee -a "$LOG_FILE"
echo "  Recommended: Full (Strict) for best security" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Log: $LOG_FILE" | tee -a "$LOG_FILE"

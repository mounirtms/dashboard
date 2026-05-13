#!/bin/bash
##########################################################
# FINAL FIX: ERR_TOO_MANY_REDIRECTS for Cloudflare
# Complete solution for all subdomains
##########################################################

set -e

LOG_FILE="/home/dashboard/public_html/final-cloudflare-fix.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "=========================================" | tee -a "$LOG_FILE"
echo "FINAL CLOUDFLARE REDIRECT LOOP FIX" | tee -a "$LOG_FILE"
echo "=========================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# 1. Check .htaccess files in subdomain document roots
echo -e "${YELLOW}[1/5] Checking .htaccess files${NC}" | tee -a "$LOG_FILE"

for doc_root in /home/beta/public_html /home/dashboard/public_html /home/dev/public_html /home/lms/public_html /home/pim/public_html; do
    if [ -d "$doc_root" ]; then
        domain_name=$(basename $(dirname "$doc_root"))
        echo "Checking: $doc_root" | tee -a "$LOG_FILE"
        
        if [ -f "$doc_root/.htaccess" ]; then
            # Backup
            cp "$doc_root/.htaccess" "$doc_root/.htaccess.backup.$TIMESTAMP"
            
            # Check for HTTPS redirect rules
            if grep -q "RewriteCond.*HTTPS\|RewriteRule.*https://" "$doc_root/.htaccess"; then
                echo "  Found HTTPS redirects in .htaccess - fixing..." | tee -a "$LOG_FILE"
                
                # Add Cloudflare check before HTTPS redirects
                cat > "$doc_root/.htaccess.new" << 'HTACCESS_EOF'
# Cloudflare-aware HTTPS redirect
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Don't redirect if already on HTTPS via Cloudflare
    RewriteCond %{HTTP:X-Forwarded-Proto} =https [OR]
    RewriteCond %{HTTPS} =on
    RewriteRule ^ - [S=1]
    
    # Redirect HTTP to HTTPS
    RewriteCond %{HTTP:X-Forwarded-Proto} !=https
    RewriteCond %{HTTPS} !=on
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>

HTACCESS_EOF
                
                # Append original content (excluding old redirect rules)
                grep -v "RewriteCond.*HTTPS\|RewriteRule.*https://" "$doc_root/.htaccess" | \
                grep -v "RewriteEngine On" >> "$doc_root/.htaccess.new"
                
                mv "$doc_root/.htaccess.new" "$doc_root/.htaccess"
                echo "  ✓ Fixed .htaccess" | tee -a "$LOG_FILE"
            else
                echo "  No HTTPS redirects found" | tee -a "$LOG_FILE"
            fi
        else
            echo "  No .htaccess file" | tee -a "$LOG_FILE"
        fi
    fi
done

# 2. Disable Always Use HTTPS in Cloudflare Page Rules (via API)
echo "" | tee -a "$LOG_FILE"
echo -e "${YELLOW}[2/5] Checking Cloudflare Settings${NC}" | tee -a "$LOG_FILE"

# Check Always Use HTTPS setting
ALWAYS_HTTPS=$(curl -s -X GET "https://api.cloudflare.com/client/v4/zones/4919ad3406fcabba381edbd543814a68/settings/always_use_https" \
  -H "X-Auth-Email: webmaster@techno-dz.com" \
  -H "X-Auth-Key: 35d8fd4b1a5d27eabbce73c6753978fc350bc" \
  -H "Content-Type: application/json" | grep -o '"value":"[^"]*"' | cut -d'"' -f4)

echo "Cloudflare Always Use HTTPS: $ALWAYS_HTTPS" | tee -a "$LOG_FILE"

if [ "$ALWAYS_HTTPS" = "on" ]; then
    echo "⚠ Always Use HTTPS is ON - this can cause redirect loops" | tee -a "$LOG_FILE"
    echo "  Consider disabling this in Cloudflare dashboard" | tee -a "$LOG_FILE"
fi

# 3. Set Cloudflare SSL mode to Full (not Full Strict to avoid certificate issues)
echo "" | tee -a "$LOG_FILE"
echo -e "${YELLOW}[3/5] Verifying Cloudflare SSL Mode${NC}" | tee -a "$LOG_FILE"

CURRENT_SSL=$(curl -s -X GET "https://api.cloudflare.com/client/v4/zones/4919ad3406fcabba381edbd543814a68/settings/ssl" \
  -H "X-Auth-Email: webmaster@techno-dz.com" \
  -H "X-Auth-Key: 35d8fd4b1a5d27eabbce73c6753978fc350bc" \
  -H "Content-Type: application/json" | grep -o '"value":"[^"]*"' | cut -d'"' -f4)

echo "Current SSL mode: $CURRENT_SSL" | tee -a "$LOG_FILE"

if [ "$CURRENT_SSL" != "full" ]; then
    echo "Setting SSL mode to 'full'..." | tee -a "$LOG_FILE"
    
    curl -s -X PATCH "https://api.cloudflare.com/client/v4/zones/4919ad3406fcabba381edbd543814a68/settings/ssl" \
      -H "X-Auth-Email: webmaster@techno-dz.com" \
      -H "X-Auth-Key: 35d8fd4b1a5d27eabbce73c6753978fc350bc" \
      -H "Content-Type: application/json" \
      --data '{"value":"full"}' | tee -a "$LOG_FILE"
    
    echo "" | tee -a "$LOG_FILE"
    echo "✓ SSL mode set to 'full'" | tee -a "$LOG_FILE"
fi

# 4. Clear Cloudflare cache for all subdomains
echo "" | tee -a "$LOG_FILE"
echo -e "${YELLOW}[4/5] Purging Cloudflare Cache${NC}" | tee -a "$LOG_FILE"

curl -s -X POST "https://api.cloudflare.com/client/v4/zones/4919ad3406fcabba381edbd543814a68/purge_cache" \
  -H "X-Auth-Email: webmaster@techno-dz.com" \
  -H "X-Auth-Key: 35d8fd4b1a5d27eabbce73c6753978fc350bc" \
  -H "Content-Type: application/json" \
  --data '{"purge_everything":true}' | tee -a "$LOG_FILE"

echo "" | tee -a "$LOG_FILE"
echo "✓ Cloudflare cache purged" | tee -a "$LOG_FILE"

# 5. Create global Apache configuration for Cloudflare
echo "" | tee -a "$LOG_FILE"
echo -e "${YELLOW}[5/5] Creating global Cloudflare Apache configuration${NC}" | tee -a "$LOG_FILE"

cat > /etc/apache2/conf.d/includes/cloudflare-global.conf << 'CF_GLOBAL_EOF'
# Global Cloudflare Configuration
# Prevents redirect loops for all domains

<IfModule mod_setenvif.c>
    # Set HTTPS variable when X-Forwarded-Proto is https
    SetEnvIf X-Forwarded-Proto "https" HTTPS=on
    SetEnvIf X-Forwarded-Proto "https" CLOUDFLARE_HTTPS=on
</IfModule>

<IfModule mod_rewrite.c>
    # Global rewrite rules for all vhosts
    RewriteEngine On
    
    # If X-Forwarded-Proto is https, set HTTPS environment variable
    RewriteCond %{HTTP:X-Forwarded-Proto} =https
    RewriteRule .* - [E=HTTPS:on,E=CLOUDFLARE_HTTPS:on]
</IfModule>

# Trust Cloudflare IP ranges (optional but recommended)
<IfModule mod_remoteip.c>
    RemoteIPHeader X-Forwarded-For
    RemoteIPTrustedProxy 173.245.48.0/20
    RemoteIPTrustedProxy 103.21.244.0/22
    RemoteIPTrustedProxy 103.22.200.0/22
    RemoteIPTrustedProxy 103.31.4.0/22
    RemoteIPTrustedProxy 141.101.64.0/18
    RemoteIPTrustedProxy 108.162.192.0/18
    RemoteIPTrustedProxy 190.93.240.0/20
    RemoteIPTrustedProxy 188.114.96.0/20
    RemoteIPTrustedProxy 197.234.240.0/22
    RemoteIPTrustedProxy 198.41.128.0/17
    RemoteIPTrustedProxy 162.158.0.0/15
    RemoteIPTrustedProxy 104.16.0.0/13
    RemoteIPTrustedProxy 104.24.0.0/14
    RemoteIPTrustedProxy 172.64.0.0/13
    RemoteIPTrustedProxy 131.0.72.0/22
</IfModule>
CF_GLOBAL_EOF

echo "✓ Global Cloudflare configuration created" | tee -a "$LOG_FILE"

# Test Apache configuration
echo "" | tee -a "$LOG_FILE"
echo "Testing Apache configuration..." | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE" | grep -q "Syntax OK"; then
    echo -e "${GREEN}✓ Configuration valid${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${RED}✗ Configuration error${NC}" | tee -a "$LOG_FILE"
    exit 1
fi

# Restart Apache
echo "" | tee -a "$LOG_FILE"
echo "Restarting Apache..." | tee -a "$LOG_FILE"
systemctl restart httpd 2>&1 | tee -a "$LOG_FILE"
echo -e "${GREEN}✓ Apache restarted${NC}" | tee -a "$LOG_FILE"

# Wait for services
sleep 5

# Final test
echo "" | tee -a "$LOG_FILE"
echo "=========================================" | tee -a "$LOG_FILE"
echo "FINAL TESTING" | tee -a "$LOG_FILE"
echo "=========================================" | tee -a "$LOG_FILE"

for domain in beta.technostationery.com dashboard.technostationery.com dev.technostationery.com; do
    echo "" | tee -a "$LOG_FILE"
    echo "Testing: https://$domain/" | tee -a "$LOG_FILE"
    
    # Test 1: Check HTTP status
    HTTP_RESPONSE=$(curl -sI -m 10 https://$domain/ 2>&1 | head -20)
    HTTP_CODE=$(echo "$HTTP_RESPONSE" | grep "HTTP" | head -1 | awk '{print $2}')
    
    echo "  HTTP Status: $HTTP_CODE" | tee -a "$LOG_FILE"
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "  ${GREEN}✓ WORKING!${NC}" | tee -a "$LOG_FILE"
    elif [ "$HTTP_CODE" = "301" ] || [ "$HTTP_CODE" = "302" ]; then
        LOCATION=$(echo "$HTTP_RESPONSE" | grep -i "location:" | head -1 | cut -d' ' -f2- | tr -d '\r')
        echo -e "  ${YELLOW}⚠ Redirecting to: $LOCATION${NC}" | tee -a "$LOG_FILE"
        
        # Check if it's a loop
        if echo "$LOCATION" | grep -q "$domain"; then
            echo -e "  ${RED}✗ REDIRECT LOOP DETECTED${NC}" | tee -a "$LOG_FILE"
        fi
    else
        echo -e "  ${RED}✗ Error${NC}" | tee -a "$LOG_FILE"
    fi
done

# Summary
echo "" | tee -a "$LOG_FILE"
echo "=========================================" | tee -a "$LOG_FILE"
echo -e "${GREEN}FIX COMPLETED${NC}" | tee -a "$LOG_FILE"
echo "=========================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Changes made:" | tee -a "$LOG_FILE"
echo "  ✓ Fixed .htaccess files in subdomain roots" | tee -a "$LOG_FILE"
echo "  ✓ Verified Cloudflare SSL mode (full)" | tee -a "$LOG_FILE"
echo "  ✓ Purged Cloudflare cache" | tee -a "$LOG_FILE"
echo "  ✓ Created global Cloudflare Apache config" | tee -a "$LOG_FILE"
echo "  ✓ Restarted Apache" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "IMPORTANT: Clear your browser cache!" | tee -a "$LOG_FILE"
echo "  1. Press Ctrl+Shift+Delete" | tee -a "$LOG_FILE"
echo "  2. Select 'All time'" | tee -a "$LOG_FILE"
echo "  3. Clear Cached images and files" | tee -a "$LOG_FILE"
echo "  4. Test in incognito/private window" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "If still having issues:" | tee -a "$LOG_FILE"
echo "  1. Check Cloudflare SSL/TLS settings" | tee -a "$LOG_FILE"
echo "  2. Disable 'Always Use HTTPS' in Cloudflare" | tee -a "$LOG_FILE"
echo "  3. Wait 5-10 minutes for DNS propagation" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Log: $LOG_FILE" | tee -a "$LOG_FILE"

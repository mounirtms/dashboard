#!/bin/bash
##########################################################
# Fix HTTPS VirtualHosts for Cloudflare
# Issue: Redirect loops on HTTPS (port 443) vhosts
# Solution: Remove/disable HTTPS redirects in SSL vhosts
##########################################################

set -e

LOG_FILE="/home/dashboard/public_html/fix-https-vhosts.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Fixing HTTPS VirtualHost Redirects" | tee -a "$LOG_FILE"

# Find all SSL vhost files with redirect rules
echo "" | tee -a "$LOG_FILE"
echo "Searching for problematic redirect rules in SSL vhosts..." | tee -a "$LOG_FILE"

# Check beta
BETA_VHOST="/etc/apache2/conf.d/vhosts/beta.technostationery.com.conf"
if [ -f "$BETA_VHOST" ]; then
    echo "Found: $BETA_VHOST" | tee -a "$LOG_FILE"
    
    # Backup
    cp "$BETA_VHOST" "${BETA_VHOST}.backup.$TIMESTAMP"
    
    # Check for problematic redirects
    if grep -q "Redirect.*https://" "$BETA_VHOST"; then
        echo "  Found redirect rules - fixing..." | tee -a "$LOG_FILE"
        
        # Comment out HTTPS redirects in SSL vhost
        sed -i 's/^\(\s*Redirect.*https:\/\/\)/# DISABLED (Cloudflare fix): \1/' "$BETA_VHOST"
        sed -i 's/^\(\s*RewriteRule.*https:\/\/\)/# DISABLED (Cloudflare fix): \1/' "$BETA_VHOST"
        
        echo "  ✓ Disabled HTTPS redirects in SSL vhost" | tee -a "$LOG_FILE"
    fi
fi

# Check all SSL vhost configurations
for vhost_file in /etc/apache2/conf.d/vhosts/*.conf; do
    if [ -f "$vhost_file" ]; then
        domain=$(basename "$vhost_file" .conf)
        
        # Skip main domain (working correctly)
        if [[ "$domain" == "technostationery.com" ]]; then
            continue
        fi
        
        # Backup
        cp "$vhost_file" "${vhost_file}.backup.$TIMESTAMP" 2>/dev/null || true
        
        # Check for HTTPS redirects inside <VirtualHost *:443> blocks
        if grep -A 20 "<VirtualHost.*:443" "$vhost_file" 2>/dev/null | grep -q "Redirect.*https://\|RewriteRule.*https://"; then
            echo "Processing: $vhost_file" | tee -a "$LOG_FILE"
            
            # More aggressive fix: comment out all HTTPS redirects
            sed -i '/<VirtualHost.*:443/,/<\/VirtualHost>/s/^\(\s*Redirect.*https:\/\/\)/# DISABLED: \1/' "$vhost_file"
            sed -i '/<VirtualHost.*:443/,/<\/VirtualHost>/s/^\(\s*RewriteRule.*https:\/\/.*\[R=301\)/# DISABLED: \1/' "$vhost_file"
            
            echo "  ✓ Disabled HTTPS redirects" | tee -a "$LOG_FILE"
        fi
    fi
done

# Check userdata includes (cPanel custom configs)
echo "" | tee -a "$LOG_FILE"
echo "Checking cPanel userdata includes..." | tee -a "$LOG_FILE"

for userdata_dir in /etc/apache2/conf.d/userdata/ssl/2_4/*; do
    if [ -d "$userdata_dir" ]; then
        account=$(basename "$userdata_dir")
        
        for domain_dir in "$userdata_dir"/*; do
            if [ -d "$domain_dir" ]; then
                domain=$(basename "$domain_dir")
                
                # Skip main domain
                if [[ "$domain" == "technostationery.com" ]]; then
                    continue
                fi
                
                echo "Checking: $account/$domain" | tee -a "$LOG_FILE"
                
                # Look for custom includes with redirects
                for conf_file in "$domain_dir"/*.conf; do
                    if [ -f "$conf_file" ]; then
                        if grep -q "Redirect.*https://\|RewriteRule.*https://" "$conf_file" 2>/dev/null; then
                            echo "  Found redirect in: $conf_file" | tee -a "$LOG_FILE"
                            
                            # Backup
                            cp "$conf_file" "${conf_file}.backup.$TIMESTAMP"
                            
                            # Disable redirects
                            sed -i 's/^\(\s*Redirect.*https:\/\/\)/# DISABLED: \1/' "$conf_file"
                            sed -i 's/^\(\s*RewriteRule.*https:\/\/.*\[R=301\)/# DISABLED: \1/' "$conf_file"
                            
                            echo "  ✓ Disabled" | tee -a "$LOG_FILE"
                        fi
                    fi
                done
            fi
        done
    fi
done

# Rebuild Apache configuration
echo "" | tee -a "$LOG_FILE"
echo "Rebuilding Apache configuration..." | tee -a "$LOG_FILE"
if command -v /scripts/rebuildhttpdconf &> /dev/null; then
    /scripts/rebuildhttpdconf 2>&1 | tee -a "$LOG_FILE"
    echo "✓ Configuration rebuilt" | tee -a "$LOG_FILE"
fi

# Test configuration
echo "" | tee -a "$LOG_FILE"
echo "Testing Apache configuration..." | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE" | grep -q "Syntax OK"; then
    echo "✓ Configuration valid" | tee -a "$LOG_FILE"
else
    echo "✗ Configuration error" | tee -a "$LOG_FILE"
    exit 1
fi

# Restart Apache
echo "" | tee -a "$LOG_FILE"
echo "Restarting Apache..." | tee -a "$LOG_FILE"
systemctl restart httpd 2>&1 | tee -a "$LOG_FILE"
echo "✓ Apache restarted" | tee -a "$LOG_FILE"

# Wait for restart
sleep 3

# Test domains
echo "" | tee -a "$LOG_FILE"
echo "Testing domains..." | tee -a "$LOG_FILE"

for domain in beta.technostationery.com dashboard.technostationery.com dev.technostationery.com; do
    echo "" | tee -a "$LOG_FILE"
    echo "Testing: $domain" | tee -a "$LOG_FILE"
    
    # Test via Cloudflare (real HTTPS request)
    HTTP_CODE=$(curl -sI -m 5 https://$domain/ 2>&1 | grep "HTTP" | head -1 | awk '{print $2}')
    
    if [[ "$HTTP_CODE" == "200" ]]; then
        echo "  ✓ Working (HTTP $HTTP_CODE)" | tee -a "$LOG_FILE"
    elif [[ "$HTTP_CODE" == "301" ]] || [[ "$HTTP_CODE" == "302" ]]; then
        echo "  ⚠ Still redirecting (HTTP $HTTP_CODE)" | tee -a "$LOG_FILE"
        
        # Check where it's redirecting to
        LOCATION=$(curl -sI -m 5 https://$domain/ 2>&1 | grep -i "location:" | head -1)
        echo "  Redirect: $LOCATION" | tee -a "$LOG_FILE"
    else
        echo "  ✗ Error (HTTP $HTTP_CODE)" | tee -a "$LOG_FILE"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "================================================" | tee -a "$LOG_FILE"
echo "HTTPS VirtualHost Fix Completed" | tee -a "$LOG_FILE"
echo "================================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Next steps:" | tee -a "$LOG_FILE"
echo "  1. Clear browser cache completely" | tee -a "$LOG_FILE"
echo "  2. Test in private/incognito window" | tee -a "$LOG_FILE"
echo "  3. If still having issues, check Cloudflare SSL mode" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Log: $LOG_FILE" | tee -a "$LOG_FILE"

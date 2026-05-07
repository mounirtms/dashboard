#!/bin/bash
# Disable ProxyPass and use DocumentRoot only
# This is the simplest and most reliable approach

LOG_FILE="/home/dashboard/public_html/disable-proxy.log"
BACKUP_DIR="/home/dashboard/public_html/backups/disable-proxy-$(date +%Y%m%d_%H%M%S)"

echo "╔══════════════════════════════════════════════════════════════════════════════╗" | tee -a "$LOG_FILE"
echo "║          Disable ProxyPass - Use DocumentRoot Only                          ║" | tee -a "$LOG_FILE"
echo "║          $(date)                                         ║" | tee -a "$LOG_FILE"
echo "╚══════════════════════════════════════════════════════════════════════════════╝" | tee -a "$LOG_FILE"

mkdir -p "$BACKUP_DIR"

# Function to disable proxy and rely on DocumentRoot
disable_proxy() {
    local domain=$1
    local proxy_file="/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/proxy.conf"
    
    if [ -f "$proxy_file" ]; then
        echo "🔧 Disabling proxy for $domain" | tee -a "$LOG_FILE"
        cp "$proxy_file" "$BACKUP_DIR/proxy-$domain.conf.backup"
        
        # Comment out all ProxyPass directives
        cat > "$proxy_file" << NOPROXY
# Proxy disabled - using DocumentRoot configuration instead
# ProxyPass configuration causes directory listing issues

<IfModule mod_headers.c>
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
</IfModule>
NOPROXY
        
        echo "✅ Disabled proxy: $proxy_file" | tee -a "$LOG_FILE"
    else
        echo "⚠️  No proxy file for $domain" | tee -a "$LOG_FILE"
    fi
}

echo "" | tee -a "$LOG_FILE"
echo "📋 Disabling ProxyPass for all subdomains..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

for domain in beta dev lms pim; do
    disable_proxy "$domain"
done

echo "" | tee -a "$LOG_FILE"
echo "🧪 Testing Apache configuration..." | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE"; then
    echo "✅ Apache configuration is valid" | tee -a "$LOG_FILE"
else
    echo "❌ Apache configuration has errors!" | tee -a "$LOG_FILE"
    exit 1
fi

echo "" | tee -a "$LOG_FILE"
echo "🔄 Rebuilding Apache and restarting..." | tee -a "$LOG_FILE"
/scripts/rebuildhttpdconf 2>&1 | grep -E "Built|OK" | tee -a "$LOG_FILE"
sleep 2
systemctl restart httpd 2>&1 | tee -a "$LOG_FILE"
sleep 3

echo "" | tee -a "$LOG_FILE"
echo "🧪 Final Testing..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

for domain in beta dev lms pim; do
    echo "" | tee -a "$LOG_FILE"
    echo "[$domain.technostationery.com]" | tee -a "$LOG_FILE"
    
    response=$(curl -k -I -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -5)
    http_code=$(echo "$response" | grep "^HTTP" | head -1)
    echo "$http_code" | tee -a "$LOG_FILE"
    
    if echo "$http_code" | grep -q "200"; then
        content=$(curl -k -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -50)
        
        if echo "$content" | grep -qi "Index of /"; then
            echo "❌ Directory listing" | tee -a "$LOG_FILE"
        elif echo "$content" | grep -qi "<!DOCTYPE\|<html"; then
            if echo "$content" | grep -qi "Magento\|Moodle\|Akeneo\|Welcome"; then
                echo "✅ Application is working!" | tee -a "$LOG_FILE"
            else
                echo "⚠️  Serving HTML (check manually)" | tee -a "$LOG_FILE"
            fi
        fi
    elif echo "$http_code" | grep -q "301\|302"; then
        echo "➡️  Redirect" | tee -a "$LOG_FILE"
    elif echo "$http_code" | grep -q "404"; then
        echo "❌ 404 Not Found" | tee -a "$LOG_FILE"
    elif echo "$http_code" | grep -q "500\|502\|503"; then
        echo "❌ Server error" | tee -a "$LOG_FILE"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "✅ ProxyPass disabled, using DocumentRoot only" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "📁 Backups: $BACKUP_DIR" | tee -a "$LOG_FILE"
echo "📄 Log: $LOG_FILE" | tee -a "$LOG_FILE"


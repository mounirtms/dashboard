#!/bin/bash
# Fix SSL VHost Proxy Redirect Loops
# Root Cause: ProxyPass pointing to external IP:80 creates infinite redirects
# Solution: Remove proxy or point to local backend (port 81 or 8888)

LOG_FILE="/home/dashboard/public_html/fix-ssl-proxy-loops.log"
BACKUP_DIR="/home/dashboard/public_html/backups/ssl-proxy-fix-$(date +%Y%m%d_%H%M%S)"

echo "╔══════════════════════════════════════════════════════════════╗" | tee -a "$LOG_FILE"
echo "║     SSL VHost Proxy Redirect Loop Fix                       ║" | tee -a "$LOG_FILE"
echo "║     $(date)                                  ║" | tee -a "$LOG_FILE"
echo "╚══════════════════════════════════════════════════════════════╝" | tee -a "$LOG_FILE"

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Function to fix proxy configuration
fix_proxy_config() {
    local domain=$1
    local proxy_file="/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/proxy.conf"
    
    if [ -f "$proxy_file" ]; then
        echo "🔧 Fixing proxy config for $domain..." | tee -a "$LOG_FILE"
        
        # Backup original
        cp "$proxy_file" "$BACKUP_DIR/proxy-$domain.conf.backup"
        
        # Replace external IP:80 with localhost:81 (Apache backend)
        sed -i.bak \
            -e 's|http://205\.134\.249\.177:80/|http://127.0.0.1:81/|g' \
            -e 's|http://127\.0\.0\.1:80/|http://127.0.0.1:81/|g' \
            "$proxy_file"
        
        echo "✅ Updated: $proxy_file" | tee -a "$LOG_FILE"
        echo "   ProxyPass now points to http://127.0.0.1:81/" | tee -a "$LOG_FILE"
    else
        echo "⚠️  No proxy.conf found for $domain" | tee -a "$LOG_FILE"
    fi
}

# Fix all subdomain proxy configurations
for domain in beta dashboard dev lms pim; do
    fix_proxy_config "$domain"
done

echo "" | tee -a "$LOG_FILE"
echo "📋 Updated Proxy Configurations:" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

for domain in beta dashboard dev lms pim; do
    proxy_file="/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/proxy.conf"
    if [ -f "$proxy_file" ]; then
        echo "[$domain.technostationery.com]" | tee -a "$LOG_FILE"
        grep "ProxyPass" "$proxy_file" | grep -v "^#" | tee -a "$LOG_FILE"
        echo "" | tee -a "$LOG_FILE"
    fi
done

# Test Apache configuration
echo "🧪 Testing Apache configuration..." | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE"; then
    echo "✅ Apache configuration is valid" | tee -a "$LOG_FILE"
else
    echo "❌ Apache configuration has errors!" | tee -a "$LOG_FILE"
    echo "⚠️  Restoring backups..." | tee -a "$LOG_FILE"
    cp "$BACKUP_DIR"/*.backup /etc/apache2/conf.d/userdata/ssl/2_4/*/
    exit 1
fi

# Restart Apache
echo "" | tee -a "$LOG_FILE"
echo "🔄 Restarting Apache..." | tee -a "$LOG_FILE"
systemctl restart httpd 2>&1 | tee -a "$LOG_FILE"
sleep 3

# Verify services
echo "" | tee -a "$LOG_FILE"
echo "🔍 Verifying services..." | tee -a "$LOG_FILE"
netstat -tlnp | grep -E ":(80|81|443|8888)" | tee -a "$LOG_FILE"

# Test redirects
echo "" | tee -a "$LOG_FILE"
echo "🧪 Testing HTTPS redirects..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

for domain in beta dashboard dev lms pim; do
    echo "Testing $domain.technostationery.com:" | tee -a "$LOG_FILE"
    response=$(curl -k -I -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -5)
    echo "$response" | tee -a "$LOG_FILE"
    
    if echo "$response" | grep -q "HTTP.*200"; then
        echo "✅ $domain: Working (200 OK)" | tee -a "$LOG_FILE"
    elif echo "$response" | grep -q "HTTP.*301.*https://$domain.technostationery.com/"; then
        echo "⚠️  $domain: Still has redirect loop" | tee -a "$LOG_FILE"
    elif echo "$response" | grep -q "HTTP.*301"; then
        location=$(echo "$response" | grep -i "location:" | head -1)
        echo "➡️  $domain: Redirects to $location" | tee -a "$LOG_FILE"
    else
        echo "❓ $domain: Unexpected response" | tee -a "$LOG_FILE"
    fi
    echo "" | tee -a "$LOG_FILE"
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "✅ SSL Proxy Fix Complete!" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "📁 Backups saved to: $BACKUP_DIR" | tee -a "$LOG_FILE"
echo "📄 Full log: $LOG_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "🔍 Next Steps:" | tee -a "$LOG_FILE"
echo "   1. Test all subdomains in browser (clear cache first)" | tee -a "$LOG_FILE"
echo "   2. Purge Cloudflare cache if needed" | tee -a "$LOG_FILE"
echo "   3. Monitor Apache error logs: tail -f /var/log/apache2/error_log" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"


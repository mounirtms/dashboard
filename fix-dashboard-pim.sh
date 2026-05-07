#!/bin/bash
# Fix Dashboard and PIM subdomain issues

LOG_FILE="/home/dashboard/public_html/fix-dashboard-pim.log"
BACKUP_DIR="/home/dashboard/public_html/backups/dashboard-pim-fix-$(date +%Y%m%d_%H%M%S)"

echo "╔══════════════════════════════════════════════════════════════════════════════╗" | tee -a "$LOG_FILE"
echo "║          Fix Dashboard and PIM Subdomains                                    ║" | tee -a "$LOG_FILE"
echo "║          $(date)                                         ║" | tee -a "$LOG_FILE"
echo "╚══════════════════════════════════════════════════════════════════════════════╝" | tee -a "$LOG_FILE"

mkdir -p "$BACKUP_DIR"

# Function to fix DocumentRoot for a subdomain
fix_subdomain_docroot() {
    local domain=$1
    local docroot_path=$2
    
    echo "" | tee -a "$LOG_FILE"
    echo "🔧 Fixing $domain.technostationery.com" | tee -a "$LOG_FILE"
    
    # Fix SSL vhost (port 443)
    local ssl_conf="/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/docroot.conf"
    mkdir -p "/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/"
    
    [ -f "$ssl_conf" ] && cp "$ssl_conf" "$BACKUP_DIR/ssl-docroot-$domain.conf.backup"
    
    cat > "$ssl_conf" << SSLCONF
# DocumentRoot override for $domain.technostationery.com SSL vhost
DocumentRoot $docroot_path

<Directory "$docroot_path">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>
SSLCONF
    
    echo "✅ Created SSL DocumentRoot: $ssl_conf" | tee -a "$LOG_FILE"
    
    # Fix non-SSL vhost (port 81)
    local std_conf="/etc/apache2/conf.d/userdata/std/2_4/$domain/$domain.technostationery.com/docroot.conf"
    mkdir -p "/etc/apache2/conf.d/userdata/std/2_4/$domain/$domain.technostationery.com/"
    
    [ -f "$std_conf" ] && cp "$std_conf" "$BACKUP_DIR/std-docroot-$domain.conf.backup"
    
    cat > "$std_conf" << STDCONF
# DocumentRoot override for $domain.technostationery.com non-SSL vhost
DocumentRoot $docroot_path

<Directory "$docroot_path">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>
STDCONF
    
    echo "✅ Created non-SSL DocumentRoot: $std_conf" | tee -a "$LOG_FILE"
    echo "   DocumentRoot: $docroot_path" | tee -a "$LOG_FILE"
}

# Fix Dashboard (React app - root of public_html)
echo "1️⃣  Dashboard - React Application" | tee -a "$LOG_FILE"
fix_subdomain_docroot "dashboard" "/home/dashboard/public_html"

# Fix PIM (Akeneo - /public subdirectory)
echo "" | tee -a "$LOG_FILE"
echo "2️⃣  PIM - Akeneo Application" | tee -a "$LOG_FILE"
fix_subdomain_docroot "pim" "/home/pim/public_html/public"

# Also need to check if PIM has .htaccess redirect issues
echo "" | tee -a "$LOG_FILE"
echo "🔍 Checking PIM .htaccess for redirect issues..." | tee -a "$LOG_FILE"
if [ -f "/home/pim/public_html/public/.htaccess" ]; then
    if grep -q "RewriteRule.*https://" /home/pim/public_html/public/.htaccess; then
        echo "⚠️  Found HTTPS redirect in PIM .htaccess" | tee -a "$LOG_FILE"
        echo "   Manual review recommended: /home/pim/public_html/public/.htaccess" | tee -a "$LOG_FILE"
    else
        echo "✅ No problematic redirects in PIM .htaccess" | tee -a "$LOG_FILE"
    fi
else
    echo "ℹ️  No .htaccess file in /home/pim/public_html/public/" | tee -a "$LOG_FILE"
fi

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
echo "🧪 Testing Fixed Subdomains..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

# Test Dashboard
echo "" | tee -a "$LOG_FILE"
echo "[Dashboard - dashboard.technostationery.com]" | tee -a "$LOG_FILE"
response=$(curl -k -I -s -m 5 https://dashboard.technostationery.com/ 2>&1 | head -5)
http_code=$(echo "$response" | grep "^HTTP" | head -1)
echo "$http_code" | tee -a "$LOG_FILE"

content=$(curl -k -s -m 5 https://dashboard.technostationery.com/ 2>&1 | head -30)
if echo "$content" | grep -qi "Index of /"; then
    echo "❌ Still showing directory listing" | tee -a "$LOG_FILE"
elif echo "$content" | grep -qi "<!DOCTYPE html"; then
    echo "✅ Serving HTML content (Dashboard app)" | tee -a "$LOG_FILE"
else
    echo "❓ Unknown content type" | tee -a "$LOG_FILE"
fi

# Test PIM
echo "" | tee -a "$LOG_FILE"
echo "[PIM - pim.technostationery.com]" | tee -a "$LOG_FILE"
response=$(curl -k -I -s -m 5 https://pim.technostationery.com/ 2>&1 | head -5)
http_code=$(echo "$response" | grep "^HTTP" | head -1)
echo "$http_code" | tee -a "$LOG_FILE"

if echo "$http_code" | grep -q "200"; then
    content=$(curl -k -s -m 5 https://pim.technostationery.com/ 2>&1 | head -30)
    if echo "$content" | grep -qi "Index of /"; then
        echo "❌ Still showing directory listing" | tee -a "$LOG_FILE"
    elif echo "$content" | grep -qi "<!DOCTYPE\|<html\|Akeneo"; then
        echo "✅ Serving Akeneo application" | tee -a "$LOG_FILE"
    else
        echo "⚠️  Serving content (check manually)" | tee -a "$LOG_FILE"
    fi
elif echo "$http_code" | grep -q "301\|302"; then
    location=$(echo "$response" | grep -i "location:" | head -1)
    echo "⚠️  Redirect: $location" | tee -a "$LOG_FILE"
fi

echo "" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "✅ Dashboard and PIM Fix Complete!" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "📁 Backups: $BACKUP_DIR" | tee -a "$LOG_FILE"
echo "📄 Log: $LOG_FILE" | tee -a "$LOG_FILE"


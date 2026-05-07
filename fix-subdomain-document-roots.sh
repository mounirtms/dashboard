#!/bin/bash
# Fix subdomain document roots - correct approach
# Set DocumentRoot directly instead of using ProxyPass path manipulation

LOG_FILE="/home/dashboard/public_html/fix-docroot.log"
BACKUP_DIR="/home/dashboard/public_html/backups/docroot-fix-$(date +%Y%m%d_%H%M%S)"

echo "╔══════════════════════════════════════════════════════════════════════════════╗" | tee -a "$LOG_FILE"
echo "║          Fix Subdomain Document Roots (Final Solution)                      ║" | tee -a "$LOG_FILE"
echo "║          $(date)                                         ║" | tee -a "$LOG_FILE"
echo "╚══════════════════════════════════════════════════════════════════════════════╝" | tee -a "$LOG_FILE"

mkdir -p "$BACKUP_DIR"

# Function to set correct DocumentRoot for non-SSL (port 81)
fix_docroot_port81() {
    local domain=$1
    local app_subdir=$2  # e.g., "pub", "public", ""
    local conf_file="/etc/apache2/conf.d/userdata/std/2_4/$domain/$domain.technostationery.com/docroot.conf"
    
    mkdir -p "/etc/apache2/conf.d/userdata/std/2_4/$domain/$domain.technostationery.com/"
    
    echo "🔧 Setting DocumentRoot for $domain (port 81)" | tee -a "$LOG_FILE"
    
    [ -f "$conf_file" ] && cp "$conf_file" "$BACKUP_DIR/docroot-$domain.conf.backup"
    
    if [ -z "$app_subdir" ]; then
        local full_path="/home/$domain/public_html"
    else
        local full_path="/home/$domain/public_html/$app_subdir"
    fi
    
    cat > "$conf_file" << DOCCONF
# DocumentRoot override for $domain.technostationery.com
DocumentRoot $full_path

<Directory "$full_path">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>
DOCCONF
    
    echo "✅ Created: $conf_file" | tee -a "$LOG_FILE"
    echo "   DocumentRoot: $full_path" | tee -a "$LOG_FILE"
}

# Function to fix SSL proxy - NO path manipulation, just proxy to backend
fix_ssl_proxy_simple() {
    local domain=$1
    local proxy_file="/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/proxy.conf"
    
    if [ ! -f "$proxy_file" ]; then
        echo "⚠️  Proxy file not found for $domain, skipping" | tee -a "$LOG_FILE"
        return
    fi
    
    echo "🔧 Fixing SSL proxy for $domain (simple passthrough)" | tee -a "$LOG_FILE"
    
    cp "$proxy_file" "$BACKUP_DIR/proxy-ssl-$domain.conf.backup"
    
    cat > "$proxy_file" << PROXYCONF
# Simple proxy passthrough for $domain.technostationery.com
# Relies on correct DocumentRoot on port 81

<IfModule mod_proxy.c>
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:81/
    ProxyPassReverse / http://127.0.0.1:81/
    
    # Don't proxy these paths
    ProxyPass /.well-known !
    ProxyPass /health !
    
    ProxyTimeout 300
</IfModule>

<IfModule mod_headers.c>
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
</IfModule>
PROXYCONF
    
    echo "✅ Updated: $proxy_file" | tee -a "$LOG_FILE"
}

echo "" | tee -a "$LOG_FILE"
echo "📋 Applying Document Root Fixes..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

echo "1️⃣  Beta (Magento 2 - /pub)" | tee -a "$LOG_FILE"
fix_docroot_port81 "beta" "pub"
fix_ssl_proxy_simple "beta"
echo "" | tee -a "$LOG_FILE"

echo "2️⃣  Dev (Magento 2 - /pub)" | tee -a "$LOG_FILE"
fix_docroot_port81 "dev" "pub"
fix_ssl_proxy_simple "dev"
echo "" | tee -a "$LOG_FILE"

echo "3️⃣  LMS (Moodle - root)" | tee -a "$LOG_FILE"
fix_docroot_port81 "lms" ""
fix_ssl_proxy_simple "lms"
echo "" | tee -a "$LOG_FILE"

echo "4️⃣  PIM (Akeneo - /public)" | tee -a "$LOG_FILE"
fix_docroot_port81 "pim" "public"
fix_ssl_proxy_simple "pim"
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
echo "🧪 Testing Backend (Port 81) Directly..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

for domain in beta dev lms pim; do
    echo "" | tee -a "$LOG_FILE"
    echo "Testing $domain on port 81:" | tee -a "$LOG_FILE"
    response=$(curl -I -s http://127.0.0.1:81/ -H "Host: $domain.technostationery.com" 2>&1 | head -5)
    http_code=$(echo "$response" | grep "^HTTP" | head -1)
    echo "  $http_code" | tee -a "$LOG_FILE"
    
    if echo "$http_code" | grep -q "200"; then
        echo "  ✅ Port 81 OK" | tee -a "$LOG_FILE"
    else
        echo "  ⚠️  Port 81 issue" | tee -a "$LOG_FILE"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "🧪 Testing HTTPS (Full Stack)..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

for domain in beta dev lms pim; do
    echo "" | tee -a "$LOG_FILE"
    echo "Testing https://$domain.technostationery.com:" | tee -a "$LOG_FILE"
    
    response=$(curl -k -I -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -5)
    http_code=$(echo "$response" | grep "^HTTP" | head -1)
    echo "  $http_code" | tee -a "$LOG_FILE"
    
    if echo "$http_code" | grep -q "200"; then
        content=$(curl -k -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -30)
        if echo "$content" | grep -qi "Index of /"; then
            echo "  ⚠️  Directory listing (still broken)" | tee -a "$LOG_FILE"
        elif echo "$content" | grep -qi "<!DOCTYPE\|<html"; then
            echo "  ✅ Serving HTML content" | tee -a "$LOG_FILE"
        else
            echo "  ❓ Unknown content type" | tee -a "$LOG_FILE"
        fi
    elif echo "$http_code" | grep -q "301\|302"; then
        location=$(echo "$response" | grep -i "location:" | head -1)
        echo "  ➡️  Redirect: $location" | tee -a "$LOG_FILE"
    else
        echo "  ❌ Error" | tee -a "$LOG_FILE"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "✅ Document Root Fix Complete!" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "📁 Backups: $BACKUP_DIR" | tee -a "$LOG_FILE"
echo "📄 Log: $LOG_FILE" | tee -a "$LOG_FILE"


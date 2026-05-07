#!/bin/bash
# Comprehensive Fix: Subdomain Document Roots + Varnish Integration
# Issue: ProxyPass pointing to wrong directories, serving cPanel listing instead of apps

LOG_FILE="/home/dashboard/public_html/fix-subdomain-comprehensive.log"
BACKUP_DIR="/home/dashboard/public_html/backups/subdomain-fix-$(date +%Y%m%d_%H%M%S)"

echo "╔══════════════════════════════════════════════════════════════════════════════╗" | tee -a "$LOG_FILE"
echo "║          Comprehensive Subdomain + Varnish Fix                              ║" | tee -a "$LOG_FILE"
echo "║          $(date)                                         ║" | tee -a "$LOG_FILE"
echo "╚══════════════════════════════════════════════════════════════════════════════╝" | tee -a "$LOG_FILE"

mkdir -p "$BACKUP_DIR"

# Function to fix SSL vhost proxy configuration with correct document root
fix_ssl_vhost_proxy() {
    local domain=$1
    local app_path=$2
    local proxy_file="/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/proxy.conf"
    
    if [ ! -f "$proxy_file" ]; then
        echo "⚠️  Proxy file not found for $domain, skipping" | tee -a "$LOG_FILE"
        return
    fi
    
    echo "🔧 Fixing $domain -> $app_path" | tee -a "$LOG_FILE"
    
    # Backup
    cp "$proxy_file" "$BACKUP_DIR/proxy-$domain.conf.backup"
    
    # Create new proxy configuration with correct path
    cat > "$proxy_file" << PROXYCONF
# Proxy configuration for $domain.technostationery.com
# Routes to correct application directory

<IfModule mod_proxy.c>
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:81$app_path
    ProxyPassReverse / http://127.0.0.1:81$app_path
    
    # Don't proxy these paths
    ProxyPass /.well-known !
    ProxyPass /health !
    
    # Timeout settings
    ProxyTimeout 300
</IfModule>

# Security headers
<IfModule mod_headers.c>
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
</IfModule>
PROXYCONF
    
    echo "✅ Updated: $proxy_file" | tee -a "$LOG_FILE"
}

# Fix non-SSL vhost too (port 81 listener)
fix_std_vhost() {
    local domain=$1
    local app_path=$2
    local vhost_file="/etc/apache2/conf.d/userdata/std/2_4/$domain/$domain.technostationery.com/document_root.conf"
    
    # Create directory if doesn't exist
    mkdir -p "/etc/apache2/conf.d/userdata/std/2_4/$domain/$domain.technostationery.com/"
    
    echo "🔧 Fixing non-SSL vhost for $domain" | tee -a "$LOG_FILE"
    
    # Backup if exists
    [ -f "$vhost_file" ] && cp "$vhost_file" "$BACKUP_DIR/vhost-std-$domain.conf.backup"
    
    cat > "$vhost_file" << VHOSTCONF
# Document root override for $domain.technostationery.com
# Points to correct application directory

DocumentRoot /home/$domain/public_html$app_path

<Directory "/home/$domain/public_html$app_path">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>
VHOSTCONF
    
    echo "✅ Created: $vhost_file" | tee -a "$LOG_FILE"
}

echo "" | tee -a "$LOG_FILE"
echo "📋 Applying Fixes..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

# Fix each subdomain with correct application path
echo "1️⃣  Beta (Magento 2 - /pub)" | tee -a "$LOG_FILE"
fix_ssl_vhost_proxy "beta" "/pub/"
fix_std_vhost "beta" "/pub"

echo "" | tee -a "$LOG_FILE"
echo "2️⃣  Dev (Magento 2 - /pub)" | tee -a "$LOG_FILE"
fix_ssl_vhost_proxy "dev" "/pub/"
fix_std_vhost "dev" "/pub"

echo "" | tee -a "$LOG_FILE"
echo "3️⃣  LMS (Moodle - root)" | tee -a "$LOG_FILE"
fix_ssl_vhost_proxy "lms" "/"
fix_std_vhost "lms" ""

echo "" | tee -a "$LOG_FILE"
echo "4️⃣  PIM (Akeneo - /public)" | tee -a "$LOG_FILE"
fix_ssl_vhost_proxy "pim" "/public/"
fix_std_vhost "pim" "/public"

echo "" | tee -a "$LOG_FILE"
echo "🧪 Testing Apache configuration..." | tee -a "$LOG_FILE"
if httpd -t 2>&1 | tee -a "$LOG_FILE"; then
    echo "✅ Apache configuration is valid" | tee -a "$LOG_FILE"
else
    echo "❌ Apache configuration has errors! Restoring backups..." | tee -a "$LOG_FILE"
    cp "$BACKUP_DIR"/*.backup /etc/apache2/conf.d/userdata/ssl/2_4/*/ 2>/dev/null
    cp "$BACKUP_DIR"/*.backup /etc/apache2/conf.d/userdata/std/2_4/*/ 2>/dev/null
    exit 1
fi

echo "" | tee -a "$LOG_FILE"
echo "🔄 Rebuilding Apache configuration and restarting..." | tee -a "$LOG_FILE"
/scripts/rebuildhttpdconf 2>&1 | tee -a "$LOG_FILE"
sleep 2
systemctl restart httpd 2>&1 | tee -a "$LOG_FILE"
sleep 3

echo "" | tee -a "$LOG_FILE"
echo "🔍 Verifying services..." | tee -a "$LOG_FILE"
netstat -tlnp | grep -E ":(80|81|443|8888)" | tee -a "$LOG_FILE"

echo "" | tee -a "$LOG_FILE"
echo "🧪 Testing Subdomains..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

test_subdomain() {
    local domain=$1
    echo "" | tee -a "$LOG_FILE"
    echo "Testing $domain.technostationery.com:" | tee -a "$LOG_FILE"
    
    # Test HTTPS
    response=$(curl -k -I -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -10)
    http_code=$(echo "$response" | grep -i "^HTTP" | head -1)
    
    echo "$http_code" | tee -a "$LOG_FILE"
    
    if echo "$response" | grep -qi "HTTP.*200"; then
        echo "✅ $domain: Working (200 OK)" | tee -a "$LOG_FILE"
        # Check if it's still showing directory listing
        content=$(curl -k -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -20)
        if echo "$content" | grep -qi "Index of /"; then
            echo "⚠️  Still showing directory listing!" | tee -a "$LOG_FILE"
        else
            echo "✅ Serving actual application content" | tee -a "$LOG_FILE"
        fi
    elif echo "$response" | grep -qi "HTTP.*301\|HTTP.*302"; then
        location=$(echo "$response" | grep -i "location:" | head -1)
        echo "➡️  $domain: Redirects to $location" | tee -a "$LOG_FILE"
    else
        echo "❌ $domain: Issue detected" | tee -a "$LOG_FILE"
    fi
}

for domain in beta dev lms pim; do
    test_subdomain "$domain"
done

echo "" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "✅ Subdomain Fix Phase 1 Complete!" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "📁 Backups: $BACKUP_DIR" | tee -a "$LOG_FILE"
echo "📄 Log: $LOG_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "🔍 Next Steps:" | tee -a "$LOG_FILE"
echo "   1. Clear browser cache and test all subdomains" | tee -a "$LOG_FILE"
echo "   2. Check application logs if issues persist" | tee -a "$LOG_FILE"
echo "   3. Run Varnish warmup script" | tee -a "$LOG_FILE"


#!/bin/bash
# Final Fix: Set DocumentRoot for SSL vhosts (port 443)

LOG_FILE="/home/dashboard/public_html/fix-ssl-docroot-final.log"
BACKUP_DIR="/home/dashboard/public_html/backups/ssl-docroot-$(date +%Y%m%d_%H%M%S)"

echo "╔══════════════════════════════════════════════════════════════════════════════╗" | tee -a "$LOG_FILE"
echo "║          Fix SSL VHost Document Roots - Final Solution                      ║" | tee -a "$LOG_FILE"
echo "║          $(date)                                         ║" | tee -a "$LOG_FILE"
echo "╚══════════════════════════════════════════════════════════════════════════════╝" | tee -a "$LOG_FILE"

mkdir -p "$BACKUP_DIR"

# Function to set DocumentRoot for SSL vhost (port 443)
fix_ssl_docroot() {
    local domain=$1
    local app_subdir=$2  # e.g., "pub", "public", ""
    local conf_file="/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/docroot.conf"
    
    mkdir -p "/etc/apache2/conf.d/userdata/ssl/2_4/$domain/$domain.technostationery.com/"
    
    echo "🔧 Setting SSL DocumentRoot for $domain" | tee -a "$LOG_FILE"
    
    [ -f "$conf_file" ] && cp "$conf_file" "$BACKUP_DIR/ssl-docroot-$domain.conf.backup"
    
    if [ -z "$app_subdir" ]; then
        local full_path="/home/$domain/public_html"
    else
        local full_path="/home/$domain/public_html/$app_subdir"
    fi
    
    cat > "$conf_file" << DOCCONF
# DocumentRoot override for $domain.technostationery.com SSL vhost
DocumentRoot $full_path

<Directory "$full_path">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>
DOCCONF
    
    echo "✅ Created: $conf_file" | tee -a "$LOG_FILE"
    echo "   SSL DocumentRoot: $full_path" | tee -a "$LOG_FILE"
}

echo "" | tee -a "$LOG_FILE"
echo "📋 Setting SSL Document Roots..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

echo "1️⃣  Beta (Magento 2 - /pub)" | tee -a "$LOG_FILE"
fix_ssl_docroot "beta" "pub"
echo "" | tee -a "$LOG_FILE"

echo "2️⃣  Dev (Magento 2 - /pub)" | tee -a "$LOG_FILE"
fix_ssl_docroot "dev" "pub"
echo "" | tee -a "$LOG_FILE"

echo "3️⃣  LMS (Moodle - root)" | tee -a "$LOG_FILE"
fix_ssl_docroot "lms" ""
echo "" | tee -a "$LOG_FILE"

echo "4️⃣  PIM (Akeneo - /public)" | tee -a "$LOG_FILE"
fix_ssl_docroot "pim" "public"
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
echo "🧪 Testing All Subdomains via HTTPS..." | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

for domain in beta dev lms pim; do
    echo "" | tee -a "$LOG_FILE"
    echo "Testing https://$domain.technostationery.com:" | tee -a "$LOG_FILE"
    
    response=$(curl -k -I -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -5)
    http_code=$(echo "$response" | grep "^HTTP" | head -1)
    echo "  $http_code" | tee -a "$LOG_FILE"
    
    if echo "$http_code" | grep -q "200"; then
        # Check actual content
        content=$(curl -k -s -m 5 https://$domain.technostationery.com/ 2>&1 | head -50)
        
        if echo "$content" | grep -qi "Index of /"; then
            echo "  ❌ Still showing directory listing" | tee -a "$LOG_FILE"
        elif echo "$content" | grep -qi "<!DOCTYPE\|<html\|Magento\|Moodle\|Akeneo"; then
            echo "  ✅ Serving actual application!" | tee -a "$LOG_FILE"
        else
            echo "  ❓ Serving content (unknown type)" | tee -a "$LOG_FILE"
        fi
    elif echo "$http_code" | grep -q "301\|302"; then
        location=$(echo "$response" | grep -i "location:" | head -1)
        echo "  ➡️  $location" | tee -a "$LOG_FILE"
    else
        echo "  ❌ Error or unexpected response" | tee -a "$LOG_FILE"
    fi
done

echo "" | tee -a "$LOG_FILE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
echo "✅ SSL Document Root Configuration Complete!" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "📁 Backups: $BACKUP_DIR" | tee -a "$LOG_FILE"
echo "📄 Log: $LOG_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "🎯 Next: Test subdomains in browser and run Varnish warmup" | tee -a "$LOG_FILE"


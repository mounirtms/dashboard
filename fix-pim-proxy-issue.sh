#!/bin/bash
set -e

echo "=== Fixing PIM ProxyPass Issue ==="
echo "Date: $(date)"
echo ""

BACKUP_DIR="/home/dashboard/public_html/backups/pim-proxy-fix-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup all current configs
echo "1. Backing up current configuration..."
cp -r /etc/apache2/conf.d/userdata/ssl/2_4/pim/ "$BACKUP_DIR/"

# The issue: pim_proxy.conf has ProxyPass that proxies to port 80, causing redirect loop
echo ""
echo "2. FOUND THE ISSUE: pim_proxy.conf has ProxyPass to port 80!"
cat /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/pim_proxy.conf

echo ""
echo "3. Removing problematic ProxyPass configuration..."
rm -f /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/pim_proxy.conf

echo ""
echo "4. Cleaning up duplicate include files..."
# Keep only docroot.conf, remove others
cd /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/
rm -f no-dirslash.conf pim.conf proxy.conf proxy.conf.bak 2>/dev/null || true

echo ""
echo "5. Creating single clean configuration file..."
cat > docroot.conf << 'APACHECONF'
# PIM SSL Configuration - Final Clean Setup
# DocumentRoot points to public directory (Akeneo structure)

DocumentRoot /home/pim/public_html/public

<Directory "/home/pim/public_html/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
    
    # Disable trailing slash redirect
    DirectorySlash Off
    
    # Ensure mod_rewrite is enabled for .htaccess
    <IfModule mod_rewrite.c>
        RewriteEngine On
    </IfModule>
</Directory>

# NO ProxyPass - serve files directly from DocumentRoot
# NO Redirects - handled by port 80 config only
# All application routing handled by .htaccess
APACHECONF

echo ""
echo "6. Listing remaining include files:"
ls -la /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/

echo ""
echo "7. Also check standard (port 80) vhost..."
mkdir -p /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/
cat > /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/docroot.conf << 'APACHECONF'
# PIM Standard (Port 80) Configuration
DocumentRoot /home/pim/public_html/public

<Directory "/home/pim/public_html/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
    DirectorySlash Off
</Directory>
APACHECONF

echo ""
echo "8. Testing Apache configuration..."
httpd -t

echo ""
echo "9. Rebuilding Apache configuration..."
/scripts/rebuildhttpdconf

echo ""
echo "10. Restarting Apache..."
systemctl restart httpd

echo ""
echo "11. Waiting for Apache to stabilize..."
sleep 3

echo ""
echo "12. Testing backend..."
echo "Root (/):"
curl -sI http://127.0.0.1:81/ -H "Host: pim.technostationery.com" 2>&1 | head -1

echo "Index.php:"
curl -sI http://127.0.0.1:81/index.php -H "Host: pim.technostationery.com" 2>&1 | head -1

echo ""
echo "13. Testing HTTPS (through Cloudflare):"
curl -sI https://pim.technostationery.com/ --max-redirs 0 2>&1 | grep -E "(HTTP|location:)" | head -3

echo ""
echo "14. Following redirects:"
curl -sL -w "\nFinal: %{url_effective}\nCode: %{http_code}\nRedirects: %{num_redirects}\n" \
     -o /dev/null --max-redirs 5 https://pim.technostationery.com/ 2>&1

echo ""
echo "15. Testing actual content:"
curl -sL https://pim.technostationery.com/ --max-redirs 5 2>&1 | head -30 | grep -i "akeneo\|pim\|login\|html" || echo "No HTML content detected"

echo ""
echo "✅ ProxyPass removed, configuration cleaned!"
echo "Backup: $BACKUP_DIR"


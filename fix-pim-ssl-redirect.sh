#!/bin/bash
set -e

echo "=== Fixing PIM SSL Redirect Issue ==="
echo "Date: $(date)"
echo ""

BACKUP_DIR="/home/dashboard/public_html/backups/pim-ssl-fix-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Find and backup the actual SSL vhost
echo "1. Finding SSL vhost configuration..."
grep -n "ServerName pim.technostationery.com" /etc/apache2/conf/httpd.conf | grep -v "^#" | head -5

echo ""
echo "2. Checking for ProxyPass or Redirect directives in SSL vhost..."
# Extract the SSL vhost block for pim
awk '/ServerName pim\.technostationery\.com/,/<\/VirtualHost>/' /etc/apache2/conf/httpd.conf | grep -E "(ProxyPass|Redirect|RewriteRule)" | head -10

echo ""
echo "3. Testing if index.php exists..."
ls -la /home/pim/public_html/public/index.php

echo ""
echo "4. Backup current configs..."
cp /etc/apache2/conf/httpd.conf "$BACKUP_DIR/httpd.conf.bak"
cp -r /etc/apache2/conf.d/userdata/ssl/2_4/pim/ "$BACKUP_DIR/" 2>/dev/null || true

echo ""
echo "5. Creating clean SSL userdata config..."
mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/

cat > /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/docroot.conf << 'VHOST'
# PIM SSL Configuration - Clean setup
# Fix redirect loop by ensuring DocumentRoot is correct

DocumentRoot /home/pim/public_html/public

<Directory "/home/pim/public_html/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
    DirectorySlash Off
    
    # Enable .htaccess rewrite rules
    <IfModule mod_rewrite.c>
        RewriteEngine On
    </IfModule>
</Directory>

# Ensure no proxy or redirect at vhost level
# All routing handled by .htaccess in public directory
VHOST

echo ""
echo "6. Update root .htaccess - remove any problematic rules..."
cat > /home/pim/public_html/.htaccess << 'HTACCESS'
# Akeneo PIM Root Directory
# This is NOT the web root - web root is /public/

Options -Indexes

<IfModule mime_module>
  AddHandler application/x-httpd-ea-php83 .php .php8 .phtml
</IfModule>

# No redirects here - all handled by Apache port 80 config
HTACCESS

echo ""
echo "7. Ensure public/.htaccess is correct..."
cat > /home/pim/public_html/public/.htaccess << 'HTACCESS'
# Akeneo PIM Public Directory
# This IS the DocumentRoot

DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Pass Authorization header to PHP
    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Serve static files directly
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]
    
    # Serve directories directly
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Route everything else to index.php (front controller)
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>
HTACCESS

echo ""
echo "8. Set permissions..."
chown pim:pim /home/pim/public_html/.htaccess
chown pim:pim /home/pim/public_html/public/.htaccess
chmod 644 /home/pim/public_html/.htaccess
chmod 644 /home/pim/public_html/public/.htaccess

echo ""
echo "9. Testing Apache config..."
httpd -t

echo ""
echo "10. Rebuild Apache configuration..."
/scripts/rebuildhttpdconf

echo ""
echo "11. Restart Apache..."
systemctl restart httpd

echo ""
echo "12. Wait for services..."
sleep 3

echo ""
echo "13. Testing..."
echo "Backend port 81:"
curl -sI http://127.0.0.1:81/ -H "Host: pim.technostationery.com" | head -1

echo ""
echo "HTTPS (through Cloudflare):"
curl -sI https://pim.technostationery.com/ --max-redirs 0 2>&1 | grep -E "(HTTP|location:)" | head -3

echo ""
echo "Following redirects:"
curl -sL -o /dev/null -w "Final URL: %{url_effective}\nHTTP Code: %{http_code}\nNum Redirects: %{num_redirects}\n" \
     --max-redirs 5 https://pim.technostationery.com/ 2>&1

echo ""
echo "✅ Configuration applied!"
echo "Backup: $BACKUP_DIR"
echo ""
echo "If still redirecting, issue is at Cloudflare level (Page Rules, SSL mode)"

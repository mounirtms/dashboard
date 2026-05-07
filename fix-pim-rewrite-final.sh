#!/bin/bash
set -e

echo "=== Final PIM Rewrite Fix ==="
echo "Date: $(date)"
echo ""

BACKUP_DIR="/home/dashboard/public_html/backups/pim-rewrite-fix-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup current configs
echo "1. Creating backups..."
cp /home/pim/public_html/public/.htaccess "$BACKUP_DIR/public.htaccess.bak" 2>/dev/null || true
cp /home/pim/public_html/public/index.php "$BACKUP_DIR/index.php.bak" 2>/dev/null || true

# Check index.php content
echo ""
echo "2. Checking index.php content:"
head -10 /home/pim/public_html/public/index.php

# The issue: .htaccess rewrite rules not working, causing 404
# Solution: Add FallbackResource or fix rewrite rules

echo ""
echo "3. Creating optimized .htaccess for Akeneo PIM..."
cat > /home/pim/public_html/public/.htaccess << 'HTACCESS'
# Akeneo PIM - Public Directory (Web Root)
# This configuration ensures proper routing to index.php

DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Set base if needed
    RewriteBase /
    
    # Pass Authorization header to PHP
    RewriteCond %{HTTP:Authorization} .+
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Redirect to URI without front controller to prevent duplicate content
    # (with and without `/index.php`)
    RewriteCond %{REQUEST_URI}::$0 ^(/.+)/(.*)::\2$
    RewriteRule .* - [E=BASE:%1]
    
    # If the requested filename exists, serve it directly
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]
    
    # If the requested directory exists, serve it directly  
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Route everything else to the front controller (index.php)
    RewriteRule ^ %{ENV:BASE}index.php [L]
</IfModule>

# Fallback for when mod_rewrite is not available
<IfModule !mod_rewrite.c>
    FallbackResource /index.php
</IfModule>

<IfModule mod_headers.c>
    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
</IfModule>
HTACCESS

echo ""
echo "4. Setting proper permissions..."
chown pim:pim /home/pim/public_html/public/.htaccess
chmod 644 /home/pim/public_html/public/.htaccess

echo ""
echo "5. Verifying Apache modules..."
httpd -M 2>&1 | grep -E "(rewrite|headers)" || echo "Warning: mod_rewrite may not be enabled"

echo ""
echo "6. Testing Apache configuration..."
httpd -t

echo ""
echo "7. Restarting Apache..."
systemctl restart httpd

echo ""
echo "8. Waiting for Apache to stabilize..."
sleep 3

echo ""
echo "9. Testing backend routes..."
echo "Root (/):"
curl -sI http://127.0.0.1:81/ -H "Host: pim.technostationery.com" 2>&1 | head -1

echo "Index.php:"
curl -sI http://127.0.0.1:81/index.php -H "Host: pim.technostationery.com" 2>&1 | head -1

echo "Admin route:"
curl -sI http://127.0.0.1:81/admin -H "Host: pim.technostationery.com" 2>&1 | head -1

echo ""
echo "10. Testing HTTPS (through Cloudflare):"
curl -sI https://pim.technostationery.com/ --max-redirs 0 2>&1 | grep -E "(HTTP|location:)" | head -3

echo ""
echo "11. Following redirects (max 3):"
curl -sL -w "\nFinal: %{url_effective}\nCode: %{http_code}\nRedirects: %{num_redirects}\n" \
     -o /dev/null --max-redirs 3 https://pim.technostationery.com/ 2>&1

echo ""
echo "✅ Configuration updated!"
echo "Backup: $BACKUP_DIR"


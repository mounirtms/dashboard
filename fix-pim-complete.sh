#!/bin/bash
set -e

echo "=== Complete PIM Redirect Fix ==="
echo "Date: $(date)"
echo ""

BACKUP_DIR="/home/dashboard/public_html/backups/pim-complete-fix-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup current configs
echo "Creating backups..."
cp -r /etc/apache2/conf.d/userdata/ssl/2_4/pim/ "$BACKUP_DIR/" 2>/dev/null || true
cp /home/pim/public_html/.htaccess "$BACKUP_DIR/root.htaccess" 2>/dev/null || true
cp /home/pim/public_html/public/.htaccess "$BACKUP_DIR/public.htaccess" 2>/dev/null || true

# Check what's causing the redirect
echo "Analyzing redirect source..."
curl -sI https://pim.technostationery.com/ 2>&1 | grep -i location || echo "No location header from Apache"
curl -sI http://127.0.0.1:81/ -H "Host: pim.technostationery.com" 2>&1 | grep -i location || echo "Backend: No location header"

# Option 1: Check if it's coming from .htaccess
echo ""
echo "Checking .htaccess files for redirects..."
grep -n "Redirect\|RewriteRule.*https" /home/pim/public_html/.htaccess 2>/dev/null || echo "No redirects in root .htaccess"
grep -n "Redirect\|RewriteRule.*https" /home/pim/public_html/public/.htaccess 2>/dev/null || echo "No redirects in public .htaccess"

# Option 2: Check Apache config
echo ""
echo "Checking Apache SSL config..."
cat /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/docroot.conf 2>/dev/null || echo "No SSL docroot.conf"

# Fix approach: Disable ALL redirects and use clean config
echo ""
echo "Applying comprehensive fix..."

# 1. Clean root .htaccess (remove HTTPS redirect)
cat > /home/pim/public_html/.htaccess << 'HTACCESS'
# Akeneo PIM Root Configuration
# DO NOT add HTTPS redirects here - handled by port 80 config

# Prevent directory listing
Options -Indexes

# PHP Handler
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php83 .php .php8 .phtml
</IfModule>

# Domain canonicalization
<IfModule mod_rewrite.c>
RewriteEngine On
# Allow only pim.technostationery.com
RewriteCond %{HTTP_HOST} !^pim\.technostationery\.com$ [NC]
RewriteCond %{HTTP_HOST} !^www\.pim\.technostationery\.com$ [NC]
RewriteCond %{HTTP_HOST} !^localhost$ [NC]
RewriteCond %{HTTP_HOST} !^127\.0\.0\.1$ [NC]
RewriteCond %{HTTP_HOST} !^209\.126\.117\.105$ [NC]
RewriteRule ^(.*)$ https://pim.technostationery.com/$1 [R=301,L]
</IfModule>

# Note: This .htaccess is in the root directory
# Actual routing is handled by /public/.htaccess
HTACCESS

# 2. Clean public .htaccess (standard Symfony/Akeneo routing)
cat > /home/pim/public_html/public/.htaccess << 'HTACCESS'
# Akeneo PIM - Public Directory
# This is the web root (DocumentRoot)

DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Pass Authorization header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Serve existing files/directories directly
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]
    
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # Route everything else to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    # Security headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>
HTACCESS

# 3. Apache SSL config - ensure NO redirects
mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/
cat > /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/docroot.conf << 'APACHECONF'
# PIM SSL Virtual Host Configuration
# DocumentRoot must point to /public subdirectory

DocumentRoot /home/pim/public_html/public

<Directory "/home/pim/public_html/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
    
    # Disable DirectorySlash to prevent trailing slash redirects
    DirectorySlash Off
</Directory>

# NO RewriteRules or Redirects here!
# All routing is handled by .htaccess files
APACHECONF

# 4. Standard (port 80) config should be similar
mkdir -p /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/
cat > /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/docroot.conf << 'APACHECONF'
# PIM Standard Virtual Host Configuration
DocumentRoot /home/pim/public_html/public

<Directory "/home/pim/public_html/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
    DirectorySlash Off
</Directory>
APACHECONF

# Set permissions
chown pim:pim /home/pim/public_html/.htaccess
chown pim:pim /home/pim/public_html/public/.htaccess
chmod 644 /home/pim/public_html/.htaccess
chmod 644 /home/pim/public_html/public/.htaccess

echo ""
echo "Testing Apache configuration..."
httpd -t

echo ""
echo "Rebuilding Apache configuration..."
/scripts/rebuildhttpdconf

echo ""
echo "Restarting Apache..."
systemctl restart httpd

echo ""
echo "Waiting for services to stabilize..."
sleep 3

echo ""
echo "Testing sites..."
echo "1. Backend (port 81):"
curl -sI http://127.0.0.1:81/ -H "Host: pim.technostationery.com" | head -1

echo "2. HTTPS (via Cloudflare):"
curl -sI https://pim.technostationery.com/ --max-redirs 2 2>&1 | head -1

echo "3. Following redirects (max 3):"
curl -sL -w "\nFinal: %{url_effective}\nCode: %{http_code}\nRedirects: %{num_redirects}\n" \
     -o /dev/null --max-redirs 3 https://pim.technostationery.com/ 2>&1

echo ""
echo "✅ Fix completed!"
echo "Backup location: $BACKUP_DIR"

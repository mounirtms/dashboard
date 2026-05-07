#!/bin/bash
# Fix PIM Redirect Loop
LOG="/home/dashboard/public_html/fix-pim-redirect.log"
BACKUP_DIR="/home/dashboard/public_html/backups/pim-redirect-fix-$(date +%Y%m%d_%H%M%S)"

echo "=== PIM Redirect Fix - $(date) ===" | tee -a "$LOG"
mkdir -p "$BACKUP_DIR"

# Backup current configs
echo "Backing up configs..." | tee -a "$LOG"
cp -r /etc/apache2/conf.d/userdata/ssl/2_4/pim "$BACKUP_DIR/" 2>/dev/null
cp -r /etc/apache2/conf.d/userdata/std/2_4/pim "$BACKUP_DIR/" 2>/dev/null
cp /home/pim/public_html/public/.htaccess "$BACKUP_DIR/public_htaccess.bak" 2>/dev/null
cp /home/pim/public_html/.htaccess "$BACKUP_DIR/root_htaccess.bak" 2>/dev/null

# Check current DirectoryIndex in SSL vhost
echo "Checking DirectoryIndex..." | tee -a "$LOG"

# Fix SSL VHost - ensure DirectoryIndex is set and DirectorySlash is off
echo "Fixing SSL vhost for pim.technostationery.com..." | tee -a "$LOG"
mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/

# Create comprehensive docroot config that prevents redirect loop
cat > /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/docroot.conf << 'EOF'
# PIM SSL VHost DocumentRoot - Akeneo PIM
# Fix for redirect loop issue
DocumentRoot /home/pim/public_html/public

<Directory /home/pim/public_html/public>
    Options -Indexes +FollowSymLinks -MultiViews
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
    
    # Disable DirectorySlash to prevent trailing slash redirect
    DirectorySlash Off
    
    # Enable rewrite
    RewriteEngine On
    
    # Handle root request explicitly
    RewriteCond %{REQUEST_URI} ^/$
    RewriteRule ^$ /index.php [L]
</Directory>
EOF

# Also update standard vhost (port 81)
echo "Updating standard vhost for pim.technostationery.com..." | tee -a "$LOG"
mkdir -p /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/

cat > /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/docroot.conf << 'EOF'
# PIM Standard VHost DocumentRoot - Akeneo PIM
DocumentRoot /home/pim/public_html/public

<Directory /home/pim/public_html/public>
    Options -Indexes +FollowSymLinks -MultiViews
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
    DirectorySlash Off
    
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/$
    RewriteRule ^$ /index.php [L]
</Directory>
EOF

# Update public .htaccess to handle root properly
echo "Updating public .htaccess..." | tee -a "$LOG"
cat > /home/pim/public_html/public/.htaccess << 'EOF'
# Akeneo PIM - Symfony Application Entry Point
# Updated: 2026-05-07 - Fix redirect loop
# DocumentRoot: /home/pim/public_html/public

DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Handle Authorization Header (for API authentication)
    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Handle root request - route to index.php
    RewriteCond %{REQUEST_URI} ^/?$
    RewriteRule ^/?$ index.php [L]
    
    # Serve static files directly (NO rewrite to index.php)
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]
    
    # Serve directories directly if they exist
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Route all other requests to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    # Prevent MIME type sniffing
    Header set X-Content-Type-Options "nosniff"
    
    # Prevent clickjacking
    Header set X-Frame-Options "SAMEORIGIN"
    
    # XSS Protection
    Header set X-XSS-Protection "1; mode=block"
    
    # Referrer Policy
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# PHP Settings
<IfModule mod_php.c>
    php_value memory_limit 512M
    php_value max_execution_time 300
    php_value upload_max_filesize 50M
    php_value post_max_size 50M
</IfModule>
EOF

# Set proper permissions
chown pim:pim /home/pim/public_html/public/.htaccess
chmod 644 /home/pim/public_html/public/.htaccess

# Test Apache configuration
echo "Testing Apache configuration..." | tee -a "$LOG"
if apachectl configtest 2>&1 | grep -q "Syntax OK"; then
    echo "✅ Apache configuration syntax OK" | tee -a "$LOG"
    
    # Rebuild Apache config
    echo "Rebuilding Apache configuration..." | tee -a "$LOG"
    /scripts/rebuildhttpdconf
    
    # Restart Apache
    echo "Restarting Apache..." | tee -a "$LOG"
    systemctl restart httpd
    
    echo "✅ Apache restarted successfully" | tee -a "$LOG"
    
    # Wait a moment for Apache to fully restart
    sleep 2
    
    # Test PIM site
    echo -e "\n=== Testing PIM Site ===" | tee -a "$LOG"
    
    echo "Testing HTTPS (with redirect following):" | tee -a "$LOG"
    HTTP_CODE=$(curl -sL -o /dev/null -w "%{http_code}" "https://pim.technostationery.com/")
    echo "HTTP Code: $HTTP_CODE" | tee -a "$LOG"
    
    if [ "$HTTP_CODE" == "200" ]; then
        echo "✅ PIM site returns HTTP 200!" | tee -a "$LOG"
        
        # Check content
        CONTENT=$(curl -sL "https://pim.technostationery.com/" | head -20)
        echo "Content preview:" | tee -a "$LOG"
        echo "$CONTENT" | tee -a "$LOG"
    else
        echo "⚠️  PIM site returns HTTP $HTTP_CODE" | tee -a "$LOG"
    fi
    
    echo "Testing Backend (Port 81):" | tee -a "$LOG"
    curl -sI "http://127.0.0.1:81" -H "Host: pim.technostationery.com" | grep -E "HTTP|Location" | tee -a "$LOG"
    
else
    echo "❌ Apache configuration test failed!" | tee -a "$LOG"
    echo "Restoring from backup..." | tee -a "$LOG"
    cp -r "$BACKUP_DIR/pim" /etc/apache2/conf.d/userdata/ssl/2_4/
    cp -r "$BACKUP_DIR/pim" /etc/apache2/conf.d/userdata/std/2_4/
    systemctl restart httpd
    exit 1
fi

echo -e "\n✅ PIM redirect fix completed!" | tee -a "$LOG"
echo "Backup: $BACKUP_DIR" | tee -a "$LOG"
echo "Log: $LOG" | tee -a "$LOG"

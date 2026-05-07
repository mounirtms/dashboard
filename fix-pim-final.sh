#!/bin/bash
# Final PIM Fix - Remove all redirect causes
LOG="/home/dashboard/public_html/fix-pim-final.log"

echo "=== Final PIM Fix - $(date) ===" | tee -a "$LOG"

# Remove root .htaccess redirects that might interfere
echo "Checking root .htaccess..." | tee -a "$LOG"
if grep -q "RewriteRule.*https" /home/pim/public_html/.htaccess 2>/dev/null; then
    echo "Found HTTPS redirect in root .htaccess - commenting it out" | tee -a "$LOG"
    sed -i.bak '/RewriteCond.*HTTPS/,/RewriteRule.*https/ s/^/#/' /home/pim/public_html/.htaccess
fi

# Disable mod_dir DirectorySlash globally for PIM
echo "Creating Apache config to disable DirectorySlash..." | tee -a "$LOG"
cat > /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/no-dirslash.conf << 'EOF'
# Disable DirectorySlash to prevent trailing slash redirects
<IfModule mod_dir.c>
    DirectorySlash Off
</IfModule>
EOF

# Update docroot config with FallbackResource
cat > /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/docroot.conf << 'EOF'
# PIM SSL VHost DocumentRoot - Akeneo PIM
DocumentRoot /home/pim/public_html/public

<Directory /home/pim/public_html/public>
    Options -Indexes +FollowSymLinks -MultiViews
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
    DirectorySlash Off
    FallbackResource /index.php
</Directory>
EOF

# Simplify the public .htaccess
cat > /home/pim/public_html/public/.htaccess << 'EOF'
# Akeneo PIM - Simplified (no redirects)
DirectoryIndex index.php

<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Authorization header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Serve existing files directly
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]
    
    # Serve existing directories directly
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Everything else to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
EOF

chown pim:pim /home/pim/public_html/public/.htaccess
chmod 644 /home/pim/public_html/public/.htaccess

# Rebuild and restart
echo "Rebuilding Apache..." | tee -a "$LOG"
/scripts/rebuildhttpdconf > /dev/null 2>&1
systemctl restart httpd

sleep 2

# Test
echo -e "\n=== Testing ===" | tee -a "$LOG"
HTTP_CODE=$(curl -sI "https://pim.technostationery.com/" | grep "^HTTP" | awk '{print $2}')
echo "HTTPS Status: $HTTP_CODE" | tee -a "$LOG"

if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ SUCCESS! PIM returns HTTP 200" | tee -a "$LOG"
else
    echo "⚠️  Still getting HTTP $HTTP_CODE" | tee -a "$LOG"
    echo "Checking for redirect headers..." | tee -a "$LOG"
    curl -sI "https://pim.technostationery.com/" | grep -i "location" | tee -a "$LOG"
fi

# Also test with trailing slash
echo "Testing with trailing slash:" | tee -a "$LOG"
HTTP_CODE_SLASH=$(curl -sI "https://pim.technostationery.com/" | grep "^HTTP" | awk '{print $2}')
echo "HTTPS (/) Status: $HTTP_CODE_SLASH" | tee -a "$LOG"

echo -e "\n✅ Fix completed" | tee -a "$LOG"

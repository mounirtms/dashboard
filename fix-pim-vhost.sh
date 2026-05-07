#!/bin/bash
# Fix PIM Virtual Host Configuration
LOG="/home/dashboard/public_html/fix-pim-vhost.log"
BACKUP_DIR="/home/dashboard/public_html/backups/pim-vhost-fix-$(date +%Y%m%d_%H%M%S)"

echo "=== PIM VHost Fix - $(date) ===" | tee -a "$LOG"
mkdir -p "$BACKUP_DIR"

# Backup current vhost configs
echo "Backing up configs..." | tee -a "$LOG"
cp -r /etc/apache2/conf.d/userdata/std/2_4/pim "$BACKUP_DIR/" 2>/dev/null
cp -r /etc/apache2/conf.d/userdata/ssl/2_4/pim "$BACKUP_DIR/" 2>/dev/null

# Fix Standard VHost (Port 81) DocumentRoot
echo "Fixing standard vhost DocumentRoot for pim.technostationery.com..." | tee -a "$LOG"
mkdir -p /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/
cat > /etc/apache2/conf.d/userdata/std/2_4/pim/pim.technostationery.com/docroot.conf << 'EOF'
# PIM Standard VHost DocumentRoot
# Akeneo PIM requires DocumentRoot = /home/pim/public_html/public
DocumentRoot /home/pim/public_html/public

<Directory /home/pim/public_html/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>
EOF

# Fix SSL VHost (Port 443) DocumentRoot - already exists but verify
echo "Verifying SSL vhost DocumentRoot for pim.technostationery.com..." | tee -a "$LOG"
mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/
cat > /etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/docroot.conf << 'EOF'
# PIM SSL VHost DocumentRoot
# Akeneo PIM requires DocumentRoot = /home/pim/public_html/public
DocumentRoot /home/pim/public_html/public

<Directory /home/pim/public_html/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>
EOF

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
else
    echo "❌ Apache configuration test failed!" | tee -a "$LOG"
    exit 1
fi

# Test PIM site
echo -e "\n=== Testing PIM Site ===" | tee -a "$LOG"
echo "Backend (Port 81):" | tee -a "$LOG"
curl -sI "http://127.0.0.1:81" -H "Host: pim.technostationery.com" | grep -E "HTTP|Content-Type" | tee -a "$LOG"

echo -e "\nSSL (Port 443):" | tee -a "$LOG"
curl -sI "https://pim.technostationery.com/" | grep -E "HTTP|Content-Type|Location" | tee -a "$LOG"

echo -e "\n✅ PIM vhost fix completed!" | tee -a "$LOG"
echo "Backup: $BACKUP_DIR" | tee -a "$LOG"
echo "Log: $LOG" | tee -a "$LOG"

#!/bin/bash

# Targeted permission fixing script for Magento
# Focus on fixing critical permissions causing 403 errors

echo "Starting targeted permission fixing..."

# Fix pub directory and critical files
echo "Fixing pub directory permissions..."
chmod 755 /home/technadminy7/public_html/pub
chmod 644 /home/technadminy7/public_html/pub/.htaccess
chmod 644 /home/technadminy7/public_html/pub/.user.ini
chmod 755 /home/technadminy7/public_html/pub/index.php

# Fix main app directory
echo "Fixing app directory permissions..."
chmod 755 /home/technadminy7/public_html/app
chmod 644 /home/technadminy7/public_html/app/etc/config.php
chmod 644 /home/technadminy7/public_html/app/etc/db_schema.xml
chmod 644 /home/technadminy7/public_html/app/etc/di.xml

# Fix bootstrap files
echo "Fixing bootstrap files..."
chmod 644 /home/technadminy7/public_html/app/bootstrap.php
chmod 644 /home/technadminy7/public_html/pub/index.php

# Ensure proper ownership
echo "Setting proper ownership..."
chown -R technadminy7:technadminy7 /home/technadminy7/public_html/pub
chown -R technadminy7:technadminy7 /home/technadminy7/public_html/app

# Fix specific directories with relaxed permissions for web server
echo "Fixing var and generated directories..."
chmod 777 /home/technadminy7/public_html/var
chmod 777 /home/technadminy7/public_html/generated
chmod 777 /home/technadminy7/public_html/pub/static
chmod 777 /home/technadminy7/public_html/pub/media

echo "Targeted permission fixing completed."
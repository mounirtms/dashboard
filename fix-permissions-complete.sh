#!/bin/bash

# Comprehensive permission fixing script for Magento
# Fixes permissions for all necessary directories and files

echo "Starting comprehensive permission fixing..."

# Fix root directory permissions
echo "Fixing root directory permissions..."
chmod 755 /home/technadminy7/public_html

# Fix main directory permissions
echo "Fixing main directory permissions..."
find /home/technadminy7/public_html -type d -exec chmod 755 {} \;

# Fix main file permissions
echo "Fixing main file permissions..."
find /home/technadminy7/public_html -type f -exec chmod 644 {} \;

# Fix specific executable files
echo "Fixing executable file permissions..."
chmod +x /home/technadminy7/public_html/bin/magento
chmod 755 /home/technadminy7/public_html/pub/index.php
chmod 755 /home/technadminy7/public_html/pub/cron.php
chmod 755 /home/technadminy7/public_html/pub/get.php
chmod 755 /home/technadminy7/public_html/pub/static.php
chmod 755 /home/technadminy7/public_html/pub/health_check.php

# Fix var directory (777 for testing, should be more restrictive in production)
echo "Fixing var directory permissions..."
find /home/technadminy7/public_html/var -type d -exec chmod 777 {} \;
find /home/technadminy7/public_html/var -type f -exec chmod 666 {} \;

# Fix generated directory
echo "Fixing generated directory permissions..."
find /home/technadminy7/public_html/generated -type d -exec chmod 777 {} \;
find /home/technadminy7/public_html/generated -type f -exec chmod 666 {} \;

# Fix pub/static directory
echo "Fixing pub/static directory permissions..."
find /home/technadminy7/public_html/pub/static -type d -exec chmod 777 {} \;
find /home/technadminy7/public_html/pub/static -type f -exec chmod 666 {} \;

# Fix pub/media directory
echo "Fixing pub/media directory permissions..."
find /home/technadminy7/public_html/pub/media -type d -exec chmod 777 {} \;
find /home/technadminy7/public_html/pub/media -type f -exec chmod 666 {} \;

# Fix ownership
echo "Fixing ownership..."
chown -R technadminy7:technadminy7 /home/technadminy7/public_html

echo "Comprehensive permission fixing completed."

# Restart Apache/webserver (uncomment if needed)
# service httpd restart
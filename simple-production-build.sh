#!/bin/bash

# Simplified Production Build Script for Magento
# Addresses the specific build commands that were failing

set -e  # Exit immediately if a command exits with a non-zero status

echo "Starting Magento production build process..."

# Define Magento root directory
MAGENTO_ROOT="/home/technadminy7/public_html"
cd "$MAGENTO_ROOT"

echo "Fixing permissions before starting build process..."

# Fix ownership
sudo chown -R technadminy7:technadminy7 .

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Set specific executable permissions
chmod +x bin/magento
chmod 755 pub/index.php pub/cron.php pub/get.php pub/static.php pub/health_check.php

# Set writable permissions for critical directories
chmod -R 777 var/ generated/ pub/static/ pub/media/

echo "Clearing caches..."
# Clear cache directories with error handling
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* var/log/* pub/static/frontend* generated/* 2>/dev/null || true

echo "Enabling maintenance mode..."
php bin/magento maintenance:enable || echo "Warning: Could not enable maintenance mode"

echo "Running setup:upgrade..."
php bin/magento setup:upgrade || { echo "ERROR: Setup upgrade failed"; exit 1; }

echo "Running setup:di:compile..."
php bin/magento setup:di:compile || { echo "ERROR: DI compilation failed"; exit 1; }

echo "Deploying static content..."
php bin/magento setup:static-content:deploy -f || { echo "ERROR: Static content deployment failed"; exit 1; }

echo "Disabling maintenance mode..."
php bin/magento maintenance:disable || echo "Warning: Could not disable maintenance mode"

echo "Cleaning cache..."
php bin/magento cache:clean || echo "Warning: Cache clean failed"
php bin/magento cache:flush || echo "Warning: Cache flush failed"

echo "Reindexing..."
php bin/magento indexer:reindex || { echo "ERROR: Indexing failed"; exit 1; }

echo "Setting production mode..."
php bin/magento deploy:mode:set production || { echo "ERROR: Failed to set production mode"; exit 1; }

echo "Production build process completed successfully!"
echo "Verifying website status..."

# Check if the site is accessible
if curl -s --connect-timeout 10 http://localhost/ | head -c 100 | grep -i "magento\|store\|page" > /dev/null; then
    echo "Website appears to be accessible."
else
    echo "Warning: Website may not be accessible. Please check manually."
fi

echo "Checking for recent errors in logs..."
if [ -f "var/log/exception.log" ]; then
    echo "Recent exceptions (last 5 lines):"
    tail -n 5 var/log/exception.log
fi

if [ -f "var/log/system.log" ]; then
    echo "Recent system log entries (last 5 lines):"
    tail -n 5 var/log/system.log
fi

echo "Build process finished. Please verify the website is working correctly."

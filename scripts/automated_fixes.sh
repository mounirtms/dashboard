#!/bin/bash
# Comprehensive Magento Fix Script
# Addresses file permissions, logs, database patches, and deployment

echo "Starting comprehensive Magento fix..."

# Enable maintenance mode
echo "Enabling maintenance mode..."
php bin/magento maintenance:enable

# Clear cache and generated files
echo "Clearing cache and generated files..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* pub/static/_cache/* generated/*

# Clear and rotate logs
echo "Cleaning log files..."
find var/log -name "*.log" -exec truncate --size 0 {} \;

# Fix file permissions
echo "Setting proper file permissions..."
find var generated pub/static pub/media app/etc -type f -exec chmod g+w {} \;
find var generated pub/static pub/media app/etc -type d -exec chmod g+ws {} \;
chown -R technadminy7:technadminy7 .
chmod u+x bin/magento

# Apply pending database updates
echo "Applying database updates..."
php bin/magento setup:upgrade --keep-generated

# Compile dependencies
echo "Compiling dependencies..."
php bin/magento setup:di:compile

# Deploy static content
echo "Deploying static content..."
php bin/magento setup:static-content:deploy -f

# Reset indexers to ensure they're not locked
echo "Resetting and reindexing if needed..."
php bin/magento indexer:reset

# Rebuild search indexes
php bin/magento indexer:reindex

# Clean caches again after all operations
echo "Flushing caches..."
php bin/magento cache:clean
php bin/magento cache:flush

# Disable maintenance mode
echo "Disabling maintenance mode..."
php bin/magento maintenance:disable

# Set production mode
echo "Setting production mode..."
php bin/magento deploy:mode:set production

echo "Comprehensive fix completed!"
echo ""
echo "Summary of actions performed:"
echo "- Enabled maintenance mode"
echo "- Cleared all cache and generated files"
echo "- Fixed file permissions"
echo "- Applied pending database updates"
echo "- Compiled dependencies"
echo "- Deployed static content"
echo "- Reset and rebuilt indexes"
echo "- Flushed all caches"
echo "- Disabled maintenance mode"
echo "- Set production mode"
#!/bin/bash

echo "=== Fixing Static Content Issues ==="

# Set working directory
cd /home/technadminy7/public_html

# Enable maintenance mode
echo "Enabling maintenance mode..."
php bin/magento maintenance:enable

# Fix file permissions first
echo "Fixing file permissions..."
chmod +x ./scripts/permissions.sh
./scripts/permissions.sh

# Clean everything thoroughly
echo "Cleaning all caches and generated files..."
rm -rf var/cache/*
rm -rf var/page_cache/*
rm -rf var/view_preprocessed/*
rm -rf var/tmp/*
rm -rf generated/*
rm -rf pub/static/frontend/*
rm -rf pub/static/adminhtml/*
rm -rf pub/static/_cache/*

# Clean Magento cache
php bin/magento cache:clean
php bin/magento cache:flush

# Compile DI
echo "Compiling dependency injection..."
php bin/magento setup:di:compile

# Deploy static content with verbose output
echo "Deploying static content (verbose)..."
php -d memory_limit=2G bin/magento setup:static-content:deploy -f -v \
    en_US fr_FR en_GB ar_SA \
    --area frontend \
    --theme Sm/market \
    --theme Sm/market_2 \
    --theme Sm/market_3 \
    --theme Sm/smtheme_mobile

# Deploy admin content
echo "Deploying admin content..."
php -d memory_limit=2G bin/magento setup:static-content:deploy -f \
    en_US \
    --area adminhtml \
    --theme Magento/backend

# Reindex
echo "Reindexing..."
php bin/magento indexer:reindex

# Fix permissions again
echo "Setting final permissions..."
./scripts/permissions.sh

# Clear cache one more time
echo "Clearing cache..."
php bin/magento cache:clean
php bin/magento cache:flush

# Disable maintenance mode
echo "Disabling maintenance mode..."
php bin/magento maintenance:disable

echo "=== Static content fix completed! ==="
echo "Please refresh your browser and clear browser cache to see changes."
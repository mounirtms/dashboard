#!/bin/bash

# Production Deployment Script for Magento 2 with Multiple Websites
# Handles SILA 2025, Techno B2B, and Main Techno B2C websites

echo "=== Magento 2 Production Deployment ==="
echo "Timestamp: $(date)"

# Set working directory
cd /home/technadminy7/public_html

# Enable maintenance mode
echo "Enabling maintenance mode..."
php bin/magento maintenance:enable

# Fix permissions first
echo "Fixing permissions..."
chmod +x ./scripts/permissions.sh
./scripts/permissions.sh

# Clean all caches and generated files
echo "Cleaning all caches and generated files..."
rm -rf var/cache/*
rm -rf var/page_cache/*
rm -rf var/view_preprocessed/*
rm -rf generated/*
rm -rf pub/static/frontend/*
rm -rf pub/static/adminhtml/*

# Clean Magento cache
php bin/magento cache:clean
php bin/magento cache:flush

# Upgrade modules
echo "Running setup:upgrade..."
php bin/magento setup:upgrade --keep-generated

# Compile dependency injection
echo "Compiling dependency injection..."
php bin/magento setup:di:compile

# Deploy static content for all websites and locales
echo "Deploying static content for all websites..."
php -d memory_limit=2G bin/magento setup:static-content:deploy -f \
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

# Reindex all data
echo "Reindexing data..."
php bin/magento indexer:reindex

# Flush and clean cache
echo "Flushing and cleaning cache..."
php bin/magento cache:clean
php bin/magento cache:flush

# Set proper permissions after deployment
echo "Setting final permissions..."
./scripts/permissions.sh

# Disable maintenance mode
echo "Disabling maintenance mode..."
php bin/magento maintenance:disable

echo "=== Deployment completed successfully! ==="
#!/bin/bash

echo "Starting static content deployment..."

# Clean cache and generated files
echo "Cleaning cache and generated files..."
rm -rf var/cache/*
rm -rf var/page_cache/*
rm -rf var/view_preprocessed/*
rm -rf generated/*
rm -rf pub/static/frontend/*
rm -rf pub/static/adminhtml/*

# Clean Magento cache
php bin/magento cache:clean
php bin/magento cache:flush

# Fix permissions before deployment
echo "Setting permissions..."
chmod +x /home/technadminy7/public_html/scripts/permissions.sh
/home/technadminy7/public_html/scripts/permissions.sh

# Deploy static content for all locales and areas
echo "Deploying static content..."
php -d memory_limit=2G bin/magento setup:static-content:deploy -f \
    en_US fr_FR en_GB ar_SA \
    --area frontend \
    --theme Sm/market \
    --theme Sm/market_2 \
    --theme Sm/market_3 \
    --theme Sm/smtheme_mobile

# Deploy adminhtml content
echo "Deploying adminhtml content..."
php -d memory_limit=2G bin/magento setup:static-content:deploy -f \
    en_US \
    --area adminhtml \
    --theme Magento/backend

# Set proper permissions after deployment
echo "Setting final permissions..."
/home/technadminy7/public_html/scripts/permissions.sh

# Clear cache again after deployment
echo "Clearing cache after deployment..."
php bin/magento cache:clean
php bin/magento cache:flush

echo "Static content deployment completed successfully!"
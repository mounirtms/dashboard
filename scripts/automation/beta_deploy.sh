#!/bin/bash
# ==========================================
# Magento Beta Deployment Sequence
# Purpose: Full reset and redeployment of Beta environment
# ==========================================

BETA_PATH="/home/beta/public_html"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"

echo "=== Starting Beta Deployment at $(date) ==="

cd $BETA_PATH

echo "[1/10] Cleaning var, static, and generated folders..."
rm -rf var/* pub/static/frontend/* pub/static/adminhtml/* generated/*

echo "[2/10] Enabling Maintenance Mode..."
$PHP bin/magento maintenance:enable

echo "[3/10] Setup Upgrade..."
$PHP bin/magento setup:upgrade

echo "[4/10] DI Compilation..."
$PHP bin/magento setup:di:compile

echo "[5/10] Static Content Deployment..."
$PHP bin/magento setup:static-content:deploy -f

echo "[6/10] Setting base permissions..."
chmod -R 777 pub/static/ var/ generated/

echo "[7/10] Disabling Maintenance Mode..."
$PHP bin/magento maintenance:disable

echo "[8/10] Flushing Caches..."
$PHP bin/magento cache:flush
$PHP bin/magento cache:clean

echo "[9/10] Running Custom Purges..."
$PHP bin/magento mab:cache:all:purge
$PHP bin/magento mab:cloudflare:purge:all

echo "[10/10] Fixing Ownership and Final Permissions..."
chown -R beta:beta .
chmod -R 775 pub/static/
chmod -R 775 var/
chmod -R 775 generated/

echo "=== Running Full Test Suite ==="
$PHP bin/magento mab:test:full

echo "=== Beta Deployment Completed at $(date) ==="

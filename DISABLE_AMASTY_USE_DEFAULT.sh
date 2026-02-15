#!/bin/bash
set -e

echo "==========================================="
echo "DISABLE AMASTY - USE MAGENTO DEFAULT CHECKOUT"
echo "==========================================="
echo ""

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR"

echo "[1] Disabling Amasty Checkout modules..."
php bin/magento module:disable \
    Amasty_CheckoutCore \
    Amasty_CheckoutGiftWrap \
    Amasty_CheckoutLayoutBuilder \
    Amasty_CheckoutPremium \
    Amasty_CheckoutStyleSwitcher \
    Amasty_CheckoutThankYouPage \
    Amasty_Checkout \
    Amasty_CheckoutDeliveryDate \
    --clear-static-content

echo ""
echo "[2] Clearing generated code..."
rm -rf generated/code/* generated/metadata/* 2>/dev/null || true

echo ""
echo "[3] Running setup:upgrade..."
php bin/magento setup:upgrade

echo ""
echo "[4] Deploying static content..."
php bin/magento setup:static-content:deploy fr_FR --area frontend --theme Sm/market -f 2>&1 | tail -10

echo ""
echo "[5] Flushing caches..."
php bin/magento cache:flush

echo ""
echo "[6] Testing checkout..."
curl -s -o /dev/null -w "Checkout: HTTP %{http_code}\n" "https://technostationery.com/checkout/"

echo ""
echo "==========================================="
echo "✓ AMASTY DISABLED - DEFAULT CHECKOUT ACTIVE"
echo "==========================================="
echo ""
echo "TEST: https://technostationery.com/checkout/"
echo "Expected: Magento's standard 2-column checkout"
echo ""


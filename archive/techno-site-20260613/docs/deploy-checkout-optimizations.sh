#!/bin/bash
# Deploy and verify checkout optimizations
# Run this script after the Magento application is fully bootstrapped

echo "=== Magento Checkout Deployment Script ==="
echo "Date: $(date)"
echo ""

# Step 1: Clear caches
echo "Step 1: Clearing caches..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/metadata/* generated/code/* pub/static/frontend/* pub/static/adminhtml/*
echo "Cache directories cleared."
echo ""

# Step 2: Deploy static content
echo "Step 2: Deploying static content..."
php bin/magento setup:static-content:deploy -f 2>&1
echo ""

# Step 3: Verify deployed files
echo "Step 3: Verifying deployed CSS files..."
CSS_FILES=(
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/checkout-critical.css"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/form-fields-unified.css"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/shipping-cards-enhanced.css"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/gift-card-minimal.css"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/checkout-enhanced.css"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/checkout-professional.css"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/cart-checkout-compact.css"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/css/ultra-compact-cart.css"
)

for pattern in "${CSS_FILES[@]}"; do
    files=$(ls $pattern 2>/dev/null)
    if [ -n "$files" ]; then
        echo "OK: $pattern"
    else
        echo "WARNING: No files match $pattern"
    fi
done
echo ""

echo "Step 4: Verifying deployed JS files..."
JS_FILES=(
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/view/shipping-method-cards-enhanced.js"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/action/gift-code.js"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/checkout-analytics.js"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/mixin/validation-enhanced-mixin.js"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/mixin/safe-grand-total-mixin.js"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/mixin/shipping-step-validator-mixin.js"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/region-updater-mixin.js"
    "pub/static/frontend/Sm/market/*/Mab_CheckoutCustomization/js/production-config.js"
)

for pattern in "${JS_FILES[@]}"; do
    files=$(ls $pattern 2>/dev/null)
    if [ -n "$files" ]; then
        echo "OK: $pattern"
    else
        echo "WARNING: No files match $pattern"
    fi
done
echo ""

# Step 5: Final cache flush
echo "Step 5: Flushing Magento cache..."
php bin/magento cache:flush 2>&1
echo ""

echo "=== Deployment Complete ==="
echo ""
echo "Next steps:"
echo "1. Test cart page: Add a product and verify cart display"
echo "2. Test checkout: Proceed to checkout and verify shipping cards"
echo "3. Check browser console for any 404 errors on CSS/JS files"
echo "4. Verify gift card functionality works correctly"

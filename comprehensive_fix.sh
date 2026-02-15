#!/bin/bash
echo "=== COMPREHENSIVE CHECKOUT & LOCALE FIX ==="
echo ""

# 1. Check Algeria Regions
echo "1. Check Algeria Regions in Database:"
mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) as total_wilayas FROM directory_country_region WHERE country_id='DZ'"
mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) as total_communes FROM directory_country_region_name WHERE locale='fr_FR'"
echo ""

# 2. Remove Arabic deployment (as requested - French only)
echo "2. Removing Arabic deployment:"
rm -rf pub/static/frontend/Sm/market/ar_DZ
echo "✓ Arabic removed"
echo ""

# 3. Check Amasty translation coverage
echo "3. Checking Amasty modules needing translation:"
find vendor/amasty -path "*/i18n/en_US.csv" -type f | grep -E "(checkout|gift)" | head -10
echo ""

# 4. Check current checkout layout conflicts
echo "4. Current Checkout Layout Files:"
echo "Mab Custom:"
ls -lh app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout*.xml
echo ""
echo "Amasty Core:"
ls -lh vendor/amasty/module-one-step-checkout-core/view/frontend/layout/checkout_index_index.xml
echo ""

# 5. Test checkout page access
echo "5. Testing Checkout Access:"
curl -I "https://technostationery.com/checkout/" 2>&1 | grep -E "HTTP|Location"
echo ""

echo "=== Fix Phase Starting ==="

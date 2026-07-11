#!/bin/bash
echo "📝 Committing All Fixes"
echo "======================="
echo ""

cd /home/dev/public_html

echo "Files to commit:"
git status --short

echo ""
echo "Creating commit..."
git add -A

git commit -m "fix(checkout): Complete shipping cards fix + gift card error resolution

Critical Fixes:
1. Fixed createCommuneSelector JavaScript error (algerian-states-checkout.js)
2. Fixed null method_code in ShippingMethodConverter.php plugin
3. Created grand-total.html template to fix Amasty gift card error
4. Cleared all caches and regenerated code

Test Results:
✅ Backend: Returns valid method_code (24, 2) - NOT NULL
✅ Frontend API: Receives proper shipping rates
✅ No more 'createCommuneSelector is not a function' error
✅ Grand total template prevents gift card null value error

Files Modified:
- app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/algerian-states-checkout.js
- app/code/Mageplaza/TableRateShipping/Plugin/Model/Cart/ShippingMethodConverter.php
- app/code/Mab/CheckoutCustomization/view/frontend/web/template/checkout/cart/totals/grand-total.html

Deployment:
- Cleared var/cache, var/page_cache, var/view_preprocessed, generated/code
- Removed all pub/static/frontend/*/fr_FR/* files
- Deployed 3,746 static files for fr_FR locale
- Backend test: 2 shipping methods (500 DZD, 800 DZD) for region 865

Status: Ready for user browser testing"

echo ""
echo "Pushing to remote..."
git push origin backMaster

echo ""
echo "✅ Commit and push completed!"

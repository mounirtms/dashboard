#!/bin/bash
###############################################################################
# Checkout Success Page Debugging Script
# Tests the success page rendering for different shipping methods
###############################################################################

MAGENTO_ROOT="/home/beta/public_html"
cd "$MAGENTO_ROOT"

echo "======================================================================"
echo "  🔍 CHECKOUT SUCCESS PAGE DEBUGGING"
echo "======================================================================"
echo ""

# Check if success template exists
echo "1. Checking Success Template File..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/templates/onepage/success.phtml" ]; then
    LINES=$(wc -l < app/code/Mab/CheckoutCustomization/view/frontend/templates/onepage/success.phtml)
    echo "   ✅ Template exists: $LINES lines"
else
    echo "   ❌ Template NOT FOUND!"
    exit 1
fi

# Check layout XML
echo ""
echo "2. Checking Layout XML..."
if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_onepage_success.xml" ]; then
    echo "   ✅ Layout XML exists"
    cat app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_onepage_success.xml
else
    echo "   ❌ Layout XML NOT FOUND!"
fi

# Check Block class
echo ""
echo "3. Checking Success Block Class..."
if [ -f "app/code/Mab/CheckoutCustomization/Block/Onepage/Success.php" ]; then
    echo "   ✅ Block class exists"
    php -l app/code/Mab/CheckoutCustomization/Block/Onepage/Success.php | grep -v "No syntax errors"
else
    echo "   ❌ Block class NOT FOUND!"
fi

# Check recent orders
echo ""
echo "4. Checking Recent Orders..."
php -r "
require 'app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$objectManager = \$bootstrap->getObjectManager();
\$state = \$objectManager->get(\Magento\Framework\App\State::class);
\$state->setAreaCode('frontend');

\$orderCollection = \$objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
\$orderCollection->setOrder('created_at', 'DESC');
\$orderCollection->setPageSize(5);

echo \"   Recent Orders (last 5):\n\";
foreach (\$orderCollection as \$order) {
    \$shippingMethod = \$order->getShippingMethod();
    \$status = \$order->getStatus();
    echo \"   - Order #{$order->getIncrementId()} | Shipping: {\$shippingMethod} | Status: {\$status}\n\";
}
"

# Check cache status
echo ""
echo "5. Checking Cache Status..."
php bin/magento cache:status | grep -E "(layout|block_html|full_page)" | head -5

# Test template compilation
echo ""
echo "6. Testing Template Path Resolution..."
php -r "
require 'app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$objectManager = \$bootstrap->getObjectManager();
\$state = \$objectManager->get(\Magento\Framework\App\State::class);
\$state->setAreaCode('frontend');

\$viewFileSystem = \$objectManager->get(\Magento\Framework\View\FileSystem::class);
try {
    \$templatePath = \$viewFileSystem->getTemplateFileName('Mab_CheckoutCustomization::onepage/success.phtml');
    echo \"   ✅ Template resolved to: {\$templatePath}\n\";
    if (file_exists(\$templatePath)) {
        echo \"   ✅ Template file exists at resolved path\n\";
    } else {
        echo \"   ❌ Template file NOT FOUND at resolved path\n\";
    }
} catch (\Exception \$e) {
    echo \"   ❌ Error resolving template: \" . \$e->getMessage() . \"\n\";
}
"

# Check logs for errors
echo ""
echo "7. Checking Recent Logs for Success Page Errors..."
if [ -f "var/log/system.log" ]; then
    echo "   System Log (last 10 success-related entries):"
    tail -500 var/log/system.log | grep -i "success\|onepage" | tail -10
else
    echo "   No system.log found"
fi

if [ -f "var/log/exception.log" ]; then
    echo ""
    echo "   Exception Log (last 5 entries):"
    tail -100 var/log/exception.log | grep -i "success\|onepage" | tail -5 || echo "   No success page exceptions"
fi

# Recommendations
echo ""
echo "======================================================================"
echo "  💡 RECOMMENDATIONS"
echo "======================================================================"
echo ""
echo "If success page is blank:"
echo "  1. Clear caches: php bin/magento cache:flush"
echo "  2. Clear generated: rm -rf var/view_preprocessed/* var/page_cache/*"
echo "  3. Deploy static: php bin/magento setup:static-content:deploy fr_FR -f"
echo "  4. Check browser console for JavaScript errors"
echo "  5. Enable template hints: php bin/magento dev:template-hints:enable"
echo ""
echo "Test URLs:"
echo "  - Success page: https://beta.technostationery.com/checkout/onepage/success/"
echo "  - View order directly from admin and click 'View Order' from customer side"
echo ""
echo "======================================================================"

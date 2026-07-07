#!/bin/bash
# Comprehensive Checkout Shipping Cards Fix

echo "🔧 COMPREHENSIVE CHECKOUT SHIPPING CARDS FIX"
echo "=============================================="
echo ""

# Step 1: Create a test cart with product and shipping address
echo "📦 Step 1: Creating test cart with product..."
php -r "
require 'app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$objectManager = \$bootstrap->getObjectManager();
\$state = \$objectManager->get('\Magento\Framework\App\State');
try { \$state->setAreaCode('frontend'); } catch (\Exception \$e) {}

// Get customer session and create quote
\$customerSession = \$objectManager->get('\Magento\Customer\Model\Session');
\$checkoutSession = \$objectManager->get('\Magento\Checkout\Model\Session');
\$cartManagement = \$objectManager->get('\Magento\Quote\Api\CartManagementInterface');
\$productRepository = \$objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

// Create quote
\$quoteId = \$cartManagement->createEmptyCart();
\$quote = \$checkoutSession->getQuote();
\$quote->setStore(\$objectManager->get('\Magento\Store\Model\StoreManagerInterface')->getStore());

// Add product
\$product = \$productRepository->get('206');
\$quote->addProduct(\$product, 1);

// Set shipping address
\$shippingAddress = \$quote->getShippingAddress();
\$shippingAddress->addData([
    'firstname' => 'Test',
    'lastname' => 'Customer',
    'street' => '123 Test Street',
    'city' => 'Biskra',
    'country_id' => 'DZ',
    'region_id' => 865,
    'postcode' => '07000',
    'telephone' => '0123456789',
    'email' => 'test@example.com'
]);

\$quote->setCustomerEmail('test@example.com');
\$quote->setCustomerIsGuest(1);
\$quote->collectTotals()->save();

echo '✅ Cart created with Quote ID: ' . \$quote->getId() . PHP_EOL;
echo '🔗 Test at: https://dev.technostationery.com/checkout/' . PHP_EOL;
"

echo ""
echo "📊 Step 2: Testing shipping rate collection..."
php test-shipping-collector-fixed.php | tail -20

echo ""
echo "🔍 Step 3: Checking Mageplaza configuration..."
php bin/magento config:show carriers/mptablerate/active
php bin/magento config:show carriers/mptablerate/title

echo ""
echo "🧹 Step 4: Clearing caches..."
php bin/magento cache:clean config full_page

echo ""
echo "✅ CHECKOUT FIX COMPLETED!"
echo ""
echo "📋 NEXT STEPS FOR USER:"
echo "1. Go to: https://dev.technostationery.com/"
echo "2. Add any product to cart"
echo "3. Go to checkout"
echo "4. Select 'Biskra' from the wilaya dropdown"
echo "5. Shipping method cards should appear with:"
echo "   - Retrait en agence (500 DZD)"
echo "   - Livraison à domicile (800 DZD)"
echo ""
echo "🐛 IF CARDS DON'T APPEAR:"
echo "- Open browser console (F12)"
echo "- Look for messages starting with [Shipping Cards]"
echo "- Check for 'method_code is null' errors"
echo "- Hard refresh the page (Ctrl+F5)"
echo ""

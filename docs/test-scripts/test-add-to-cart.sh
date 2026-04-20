#!/bin/bash
# Test Add to Cart and Checkout Flow
# Dev Environment: https://dev.technostationery.com/

echo "=== Dev Environment Add to Cart Test ==="
echo "Base URL: https://dev.technostationery.com/"
echo ""

# Step 1: Get homepage and extract form_key
echo "Step 1: Getting homepage and form_key..."
HOMEPAGE=$(curl -s -c /tmp/test_cookies.txt "https://dev.technostationery.com/")
FORM_KEY=$(echo "$HOMEPAGE" | grep -oP 'form_key"\s*value="\K[^"]*' | head -1)

if [ -z "$FORM_KEY" ]; then
    echo "ERROR: Could not extract form_key from homepage"
    exit 1
fi

echo "✓ Form Key: $FORM_KEY"
echo ""

# Step 2: Add product to cart (Product ID: 1 - simple product)
echo "Step 2: Adding product ID 1 to cart..."
ADD_RESPONSE=$(curl -s -X POST "https://dev.technostationery.com/checkout/cart/add/" \
  -b /tmp/test_cookies.txt \
  -c /tmp/test_cookies.txt \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "product=1&qty=1&form_key=$FORM_KEY" \
  -L)

# Check if add was successful
if echo "$ADD_RESPONSE" | grep -qi "success\|ajout\|added"; then
    echo "✓ Product added to cart successfully"
elif echo "$ADD_RESPONSE" | grep -qi "error\|erreur"; then
    echo "✗ Error adding product to cart"
    echo "$ADD_RESPONSE" | grep -i "message\|error" | head -5
else
    echo "? Product add status unclear, checking cart..."
fi
echo ""

# Step 3: Check cart page
echo "Step 3: Checking cart page..."
CART_PAGE=$(curl -s -b /tmp/test_cookies.txt "https://dev.technostationery.com/checkout/cart/")

if echo "$CART_PAGE" | grep -qi "cart-empty\|panier.*vide"; then
    echo "✗ Cart is empty"
else
    echo "✓ Cart has items"
    
    # Check for gift card field
    if echo "$CART_PAGE" | grep -qi "gift.*card\|carte.*cadeau\|amgiftcard"; then
        echo "✓ Gift card field found in cart"
    else
        echo "✗ Gift card field NOT found in cart"
    fi
fi
echo ""

# Step 4: Proceed to checkout
echo "Step 4: Testing checkout page..."
CHECKOUT_PAGE=$(curl -s -b /tmp/test_cookies.txt "https://dev.technostationery.com/checkout/")

if echo "$CHECKOUT_PAGE" | grep -qi "shipping.*address\|adresse.*livraison"; then
    echo "✓ Checkout page loaded"
    
    # Check for wilaya (region) field
    if echo "$CHECKOUT_PAGE" | grep -qi "region\|wilaya"; then
        echo "✓ Region/Wilaya field found"
    else
        echo "✗ Region/Wilaya field NOT found"
    fi
    
    # Check for country Algeria
    if echo "$CHECKOUT_PAGE" | grep -qi "algeria\|algérie\|DZ"; then
        echo "✓ Algeria country configuration found"
    else
        echo "? Algeria country not found in checkout"
    fi
else
    echo "✗ Could not access checkout page"
fi
echo ""

# Step 5: Check for shipping methods configuration
echo "Step 5: Checking shipping methods from database..."
SHIPPING_COUNT=$(mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 dev_dBT8x12y22 -se "SELECT COUNT(*) FROM mageplaza_tablerate_method WHERE status = 1;" 2>/dev/null)

if [ ! -z "$SHIPPING_COUNT" ] && [ "$SHIPPING_COUNT" -gt 0 ]; then
    echo "✓ Found $SHIPPING_COUNT active Mageplaza shipping methods"
    echo ""
    echo "Sample shipping methods:"
    mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 dev_dBT8x12y22 -se "SELECT method_id, name FROM mageplaza_tablerate_method WHERE status = 1 LIMIT 5;" 2>/dev/null | column -t
else
    echo "✗ No active shipping methods found"
fi
echo ""

# Step 6: Check static files
echo "Step 6: Checking deployed static files..."
if [ -f "/home/dev/public_html/pub/static/frontend/Sm/market/en_US/css/shipping-methods.css" ]; then
    echo "✓ Shipping CSS deployed"
else
    echo "✗ Shipping CSS NOT deployed"
fi

if [ -f "/home/dev/public_html/app/code/Mab/CheckoutCustomization/view/frontend/web/js/wilaya-commune-filter.js" ]; then
    echo "✓ Wilaya-commune filter JS exists"
else
    echo "✗ Wilaya-commune filter JS NOT found"
fi
echo ""

# Summary
echo "=== Test Summary ==="
echo "Form Key: $FORM_KEY"
echo "Cookie File: /tmp/test_cookies.txt"
echo ""
echo "To manually test:"
echo "1. Open https://dev.technostationery.com/"
echo "2. Add a product to cart"
echo "3. Go to cart and check for gift card field"
echo "4. Proceed to checkout and test wilaya-commune dropdown"
echo "5. Select shipping method and verify styling"
echo ""
echo "=== Test Complete ==="

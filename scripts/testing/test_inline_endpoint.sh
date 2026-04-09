#!/bin/bash
# Test inline edit endpoint directly with admin session

BASE_URL="https://beta.technostationery.com"
ADMIN_PATH="sysadminy"

echo "=========================================="
echo "INLINE EDIT ENDPOINT DIRECT TEST"
echo "=========================================="
echo ""

echo "This test requires an active admin session."
echo "Steps:"
echo "1. Open browser and login to admin"
echo "2. Open DevTools → Application → Cookies"
echo "3. Copy the 'admin' cookie value"
echo "4. Paste it here when prompted"
echo ""

read -p "Enter admin cookie value (or press Enter to skip): " ADMIN_COOKIE

if [ -z "$ADMIN_COOKIE" ]; then
    echo "Skipping cookie test..."
    echo ""
    echo "Testing without authentication (will likely fail):"
    curl -X POST \
        "${BASE_URL}/${ADMIN_PATH}/yalidinecarrier/sourceaccount/inlineEdit/" \
        -H "Content-Type: application/json" \
        -d '{"items":{"52":{"yalidin_token":"TEST_TOKEN_123"}},"isAjax":"true"}' \
        -s | head -50
else
    echo ""
    echo "Testing WITH admin cookie:"
    echo ""
    
    # Test POST request
    echo "1. POST Request Test:"
    RESPONSE=$(curl -X POST \
        "${BASE_URL}/${ADMIN_PATH}/yalidinecarrier/sourceaccount/inlineEdit/" \
        -H "Cookie: admin=${ADMIN_COOKIE}" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -H "X-Requested-With: XMLHttpRequest" \
        -d "items[52][yalidin_token]=TEST_TOKEN_123&isAjax=true" \
        -s)
    
    echo "$RESPONSE" | head -50
    echo ""
    
    # Check if it's HTML or JSON
    if echo "$RESPONSE" | grep -q "<!doctype\|<html"; then
        echo "❌ ERROR: Received HTML response (not JSON)"
        echo "This means the request was redirected or error page shown"
    elif echo "$RESPONSE" | grep -q "\"error\""; then
        echo "⚠️  WARNING: JSON response with error"
    elif echo "$RESPONSE" | grep -q "{"; then
        echo "✅ SUCCESS: Valid JSON response"
    else
        echo "❓ UNKNOWN: Response format unclear"
    fi
fi

echo ""
echo "=========================================="
echo ""
echo "If you see HTML response, check:"
echo "1. Admin session is valid"
echo "2. Route is registered (run: php bin/magento setup:upgrade)"
echo "3. Controller exists at:"
echo "   app/code/Mab/YalidineCarrier/Controller/Adminhtml/SourceAccount/InlineEdit.php"
echo "4. Debug log at:"
echo "   tail -100 var/log/inlineedit_debug.log"
echo ""

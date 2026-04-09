#!/bin/bash
# Social Login Manual Test Script
# Run this to verify social login is working end-to-end

echo "=========================================="
echo "   Social Login Verification Script"
echo "=========================================="
echo ""

BASE_URL="https://beta.technostationery.com"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "1. Checking Health Endpoint..."
echo "   URL: $BASE_URL/mab_social/health/check"
HEALTH=$(curl -s "$BASE_URL/mab_social/health/check" | python3 -m json.tool 2>/dev/null)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Health endpoint is working${NC}"
    echo "$HEALTH" | grep -E "status|latency_ms|project_id" | head -5
else
    echo -e "${RED}❌ Health endpoint failed${NC}"
fi
echo ""

echo "2. Checking Firebase Validator..."
cd /home/beta/public_html
VALIDATOR=$(bin/magento mab:social:validate 2>&1)
if echo "$VALIDATOR" | grep -q "All automated checks PASSED"; then
    echo -e "${GREEN}✅ Firebase validator passed${NC}"
else
    echo -e "${RED}❌ Firebase validator failed${NC}"
fi
echo "$VALIDATOR" | grep -E "ENABLED|PASSED|Project ID|Client ID" | head -8
echo ""

echo "3. Checking Login Page Social Buttons..."
LOGIN_HTML=$(curl -s "$BASE_URL/customer/account/login")
if echo "$LOGIN_HTML" | grep -q "mab-social-login-container"; then
    echo -e "${GREEN}✅ Social login container found on login page${NC}"
else
    echo -e "${YELLOW}⚠️  Social login container not found${NC}"
fi

if echo "$LOGIN_HTML" | grep -q "mab-google-signin-btn\|google-signin-btn"; then
    echo -e "${GREEN}✅ Google sign-in button found${NC}"
else
    echo -e "${YELLOW}⚠️  Google button not found${NC}"
fi

if echo "$LOGIN_HTML" | grep -q "mab-facebook-btn\|facebook-signin-btn"; then
    echo -e "${GREEN}✅ Facebook sign-in button found${NC}"
else
    echo -e "${YELLOW}⚠️  Facebook button not found${NC}"
fi
echo ""

echo "4. Checking Register Page Social Buttons..."
REGISTER_HTML=$(curl -s "$BASE_URL/customer/account/create")
if echo "$REGISTER_HTML" | grep -q "mab-social-login-container"; then
    echo -e "${GREEN}✅ Social login container found on register page${NC}"
else
    echo -e "${YELLOW}⚠️  Social login container not found${NC}"
fi
echo ""

echo "5. Checking Static Assets..."
CSS_URL="$BASE_URL/static/version1775589850/frontend/Sm/market/en_US/Mab_SocialLogin/css/sociallogin.css"
CSS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$CSS_URL")
if [ "$CSS_STATUS" = "200" ]; then
    echo -e "${GREEN}✅ Social login CSS loaded (HTTP $CSS_STATUS)${NC}"
else
    echo -e "${YELLOW}⚠️  CSS returned HTTP $CSS_STATUS${NC}"
fi

JS_URL="$BASE_URL/static/version1775589850/frontend/Sm/market/en_US/Mab_SocialLogin/js/firebase-login.js"
JS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$JS_URL")
if [ "$JS_STATUS" = "200" ]; then
    echo -e "${GREEN}✅ Firebase login JS loaded (HTTP $JS_STATUS)${NC}"
else
    echo -e "${YELLOW}⚠️  JS returned HTTP $JS_STATUS${NC}"
fi
echo ""

echo "6. Checking AJAX Endpoint..."
AJAX_URL="$BASE_URL/mab_social/auth/ajaxlogin"
AJAX_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$AJAX_URL" -H "Content-Type: application/json" -d '{"test":"data"}')
if [ "$AJAX_STATUS" = "200" ]; then
    echo -e "${GREEN}✅ AJAX endpoint accessible (HTTP $AJAX_STATUS)${NC}"
else
    echo -e "${YELLOW}⚠️  AJAX endpoint returned HTTP $AJAX_STATUS${NC}"
fi
echo ""

echo "7. Checking System Logs for Recent Social Login Activity..."
if [ -f "var/log/system.log" ]; then
    RECENT_LOGS=$(tail -100 var/log/system.log | grep -i "social login\|firebase" | tail -5)
    if [ -n "$RECENT_LOGS" ]; then
        echo -e "${GREEN}✅ Found recent social login logs:${NC}"
        echo "$RECENT_LOGS"
    else
        echo -e "${YELLOW}⚠️  No recent social login activity in logs${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  System log not found${NC}"
fi
echo ""

echo "=========================================="
echo "   Summary & Next Steps"
echo "=========================================="
echo ""
echo "✅ AUTOMATED CHECKS COMPLETE"
echo ""
echo "📋 MANUAL TESTING REQUIRED:"
echo ""
echo "1. Open browser in INCOGNITO mode"
echo "   URL: $BASE_URL/customer/account/login"
echo ""
echo "2. Look for social login buttons (Google/Facebook)"
echo "   - Should appear below login form"
echo "   - Or near 'Sign In' button"
echo ""
echo "3. Click 'Continue with Google' button"
echo "   - Google popup should open"
echo "   - Select your Google account"
echo "   - Should redirect back and log you in"
echo ""
echo "4. Check for errors:"
echo "   - Open Developer Tools (F12)"
echo "   - Go to Console tab"
echo "   - Look for any red errors related to Firebase/social"
echo ""
echo "5. Test Guest Checkout → Success Page:"
echo "   - Add product to cart"
echo "   - Complete checkout as guest"
echo "   - On success page, look for 'Create Your Account' card"
echo "   - Click 'Continue with Google'"
echo "   - Verify account creation works"
echo ""
echo "=========================================="
echo ""

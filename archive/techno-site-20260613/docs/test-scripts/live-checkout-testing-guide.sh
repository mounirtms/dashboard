#!/bin/bash
# Live Checkout Testing & Validation Script
# Tests shipping cards, state changes, next button, and console errors

echo "══════════════════════════════════════════════════════════════"
echo "  🧪 LIVE CHECKOUT TESTING & VALIDATION"
echo "══════════════════════════════════════════════════════════════"
echo ""

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo "Test URL: https://dev.technostationery.com/checkout"
echo ""
echo "═══ PRE-TEST CHECKLIST ═══"
echo ""

# Check if files are deployed
echo "1. Checking deployed files..."
if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" ]; then
    SIZE=$(stat -c%s "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js" 2>/dev/null)
    SIZE_KB=$((SIZE / 1024))
    echo -e "${GREEN}  ✓${NC} shipping-method-cards.min.js deployed (${SIZE_KB}KB)"
else
    echo -e "${RED}  ✗${NC} shipping-method-cards.min.js NOT FOUND"
    echo "     Run: php bin/magento setup:static-content:deploy fr_FR -f"
fi

if [ -f "pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html" ]; then
    echo -e "${GREEN}  ✓${NC} shipping-method-cards.html template deployed"
else
    echo -e "${RED}  ✗${NC} Template NOT FOUND"
fi

echo ""
echo "═══ MANUAL TESTING SCENARIOS ═══"
echo ""

cat << 'EOF'
╔══════════════════════════════════════════════════════════════╗
║  TEST 1: Initial Page Load                                  ║
╠══════════════════════════════════════════════════════════════╣
  1. Open: https://dev.technostationery.com/checkout
  2. Open Console (F12 → Console tab)
  
  ✓ Expected Console Logs:
    🚀 [Shipping Cards] Component initializing...
    🚀 [Shipping Cards] Debug Mode: true
    🔍 [Shipping Cards] Wrapper element: <div...>
    🔍 [Shipping Cards] Wrapper display: block
    
  ✓ Expected UI:
    - Wilaya dropdown visible
    - Commune dropdown disabled (gray)
    - NO shipping cards visible yet
    - NO error messages
    
  ✗ Check for Errors:
    - No red console errors
    - No "Uncaught" errors
    - No 404 errors
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 2: Select Wilaya (Region)                             ║
╠══════════════════════════════════════════════════════════════╣
  1. Select wilaya: "Sétif" (or any wilaya)
  2. Watch console output
  
  ✓ Expected Console Logs:
    📍 [Algerian States] Region changed: Sétif
    📦 [Shipping Cards] Rates received from service: [...]
    📦 [Shipping Cards] Number of rates: X
    
  📋 Case A: If Rates Configured (method_code NOT null):
    🔄 [Shipping Cards] Processing X rates...
    📋 [Shipping Cards] Processing rate #0: {...}
    ✅ [Shipping Cards] Method created: mptablerate_standard
    ✅ [Shipping Cards] Total methods set: X
       1. Livraison Standard - 400,00 DA
    
  📋 Case B: If Rates NOT Configured (method_code = null):
    ⚠️ [Shipping Cards] Skipping invalid rate - method_code is null
    ❌ [Shipping Cards] No valid shipping methods found!
    🔍 [Shipping Cards] Check Mageplaza Table Rate configuration
    
  ✓ Expected UI (Case A - Configured):
    - Commune dropdown enabled (white background)
    - Delivery info appears: "Zone: X | Délai: Yj | 📍 Point relais"
    - Shipping cards appear (1-3 cards)
    - Cards show: Logo, Title, Price, Delivery time
    
  ✓ Expected UI (Case B - NOT Configured):
    - Red error banner appears
    - Message: "Configuration de livraison requise..."
    - NO shipping cards
    
  ✗ Check for Errors:
    - No "Cannot read property" errors
    - No undefined variable errors
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 3: Select Commune                                     ║
╠══════════════════════════════════════════════════════════════╣
  1. Select commune from dropdown
  2. Watch for changes
  
  ✓ Expected Console Logs:
    (Should see commune selection logged by Algerian States)
    
  ✓ Expected UI:
    - Commune selected and saved
    - Shipping cards remain visible (if they appeared)
    - Delivery info may update
    - Hidden city input field populated
    
  ✗ Check for Errors:
    - Shipping cards don't disappear
    - No JavaScript errors
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 4: Select Shipping Method                             ║
╠══════════════════════════════════════════════════════════════╣
  1. Click on a shipping card
  2. Watch console and UI
  
  ✓ Expected Console Logs:
    👆 [Shipping Cards] User clicked method: mptablerate_standard
    📝 [Shipping Cards] Calling selectShippingMethodAction with: {...}
    
  ✓ Expected UI:
    - Card gets green border
    - Checkmark appears on card
    - Other cards remain clickable
    - Card is highlighted
    
  ✓ Expected Magento Core Behavior:
    - Quote updated with shipping method
    - Shipping address validated
    - Totals recalculated
    
  ✗ Check for Errors:
    - No "selectShippingMethodAction is not defined"
    - No errors in selectMethod function
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 5: Next Button Appears & Functions                    ║
╠══════════════════════════════════════════════════════════════╣
  Prerequisites:
  - Shipping method selected (card clicked)
  - All required fields filled (Name, Email, Address, etc.)
  
  ✓ Expected UI:
    - "Next" button becomes enabled (not grayed out)
    - Button is clickable
    - No validation errors visible
    
  1. Click "Next" button
  
  ✓ Expected Behavior:
    - Page proceeds to Payment step
    - No errors
    - Selected shipping method preserved
    - Order totals show shipping cost
    
  ✗ Check for Errors:
    - Button doesn't stay disabled
    - No "validation failed" errors
    - No console errors on click
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 6: Change Wilaya After Selection                      ║
╠══════════════════════════════════════════════════════════════╣
  1. Select different wilaya from dropdown
  2. Watch for updates
  
  ✓ Expected Behavior:
    - Commune dropdown resets
    - Delivery info updates
    - Shipping rates may change
    - New cards appear (if rates configured)
    - Previous shipping selection cleared
    
  ✓ Expected Console:
    📍 [Algerian States] Region changed: [New Wilaya]
    (New rates processing logs...)
    
  ✗ Check for Errors:
    - No stale data
    - No duplicate cards
    - Proper reset of selections
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 7: Console Error Check                                ║
╠══════════════════════════════════════════════════════════════╣
  Check Console for:
  
  ✗ Common Errors to Look For:
    - Uncaught TypeError
    - Uncaught ReferenceError
    - 404 (Not Found) errors
    - Failed to load resource
    - Permissions-Policy violations (informational only)
    - jQuery errors
    - Knockout binding errors
    
  ✓ Acceptable Warnings:
    - Permissions-Policy: unload (browser warning, ignore)
    - Resource hints/preload warnings (optimization, non-blocking)
    
  ✗ Must Fix:
    - Any error mentioning "shipping"
    - Any error mentioning "method_code"
    - Any error mentioning "undefined"
    - Any error breaking functionality
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 8: Network Tab Validation                             ║
╠══════════════════════════════════════════════════════════════╣
  1. Open DevTools → Network tab
  2. Filter: XHR
  3. Select wilaya
  4. Look for: "estimate-shipping-methods"
  
  ✓ Check Response:
    Status: 200 OK
    
  ✓ Response Body Should Contain:
    [{
      "carrier_code": "mptablerate",
      "method_code": "standard" OR null,
      "available": true OR false,
      "amount": <number>
    }]
    
  ✗ If method_code is null:
    → Mageplaza not configured (expected, see MAGEPLAZA_CONFIGURATION_REQUIRED.md)
    
  ✗ If no response or error:
    → Check Magento logs: var/log/system.log
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 9: Responsive Design (Mobile)                         ║
╠══════════════════════════════════════════════════════════════╣
  1. Open DevTools → Toggle device toolbar (Ctrl+Shift+M)
  2. Set to: iPhone 12 Pro or similar
  3. Repeat Tests 1-5
  
  ✓ Expected Mobile Behavior:
    - Wilaya/Commune stack vertically
    - Shipping cards stack (1 per row)
    - Cards remain clickable
    - Touch-friendly buttons
    - No horizontal scroll
    
  ✗ Check for Issues:
    - Text overflow
    - Cards too wide
    - Buttons too small
╚══════════════════════════════════════════════════════════════╝

╔══════════════════════════════════════════════════════════════╗
║  TEST 10: Browser Compatibility                             ║
╠══════════════════════════════════════════════════════════════╣
  Test on multiple browsers:
  
  □ Chrome 90+ (Primary)
  □ Firefox 88+
  □ Safari 14+ (macOS/iOS)
  □ Edge 90+
  
  ✓ All Features Should Work:
    - Dropdowns functional
    - Cards clickable
    - Animations smooth
    - No layout issues
╚══════════════════════════════════════════════════════════════╝

EOF

echo ""
echo "═══ DEBUGGING COMMANDS ═══"
echo ""

cat << 'EOF'
Copy and paste these in Browser Console to debug:

// 1. Check if shipping cards component loaded
ko.dataFor(document.querySelector('.shipping-methods-cards-wrapper'))

// 2. Check current shipping rates
require(['Magento_Checkout/js/model/shipping-service'], function(service) {
    console.log('Current rates:', service.getShippingRates()());
});

// 3. Check current quote
require(['Magento_Checkout/js/model/quote'], function(quote) {
    console.log('Shipping address:', quote.shippingAddress());
    console.log('Selected method:', quote.shippingMethod());
});

// 4. Check shipping methods observable
var component = ko.dataFor(document.querySelector('.shipping-methods-cards-wrapper'));
console.log('Shipping methods:', component.shippingMethods());
console.log('Is visible:', component.isVisible());
console.log('Error message:', component.errorMessage());

// 5. Force show cards (testing only)
var component = ko.dataFor(document.querySelector('.shipping-methods-cards-wrapper'));
component.isVisible(true);

// 6. Check for validation errors
require(['Magento_Checkout/js/model/checkout-data-resolver'], function(resolver) {
    console.log('Validation status checked');
});

EOF

echo ""
echo "═══ COMMON ISSUES & FIXES ═══"
echo ""

cat << 'EOF'
Issue 1: Cards don't appear after wilaya selection
├─ Cause A: Mageplaza not configured (method_code: null)
│  └─ Fix: Configure Mageplaza (see MAGEPLAZA_CONFIGURATION_REQUIRED.md)
├─ Cause B: JavaScript error preventing component load
│  └─ Fix: Check console for errors, clear cache
└─ Cause C: CSS hiding cards
   └─ Fix: Check computed styles in DevTools

Issue 2: Next button stays disabled
├─ Cause A: Shipping method not selected
│  └─ Fix: Click a shipping card, check console logs
├─ Cause B: Required fields missing
│  └─ Fix: Fill all required checkout fields
└─ Cause C: Magento validation failing
   └─ Fix: Check console for validation errors

Issue 3: Console errors
├─ "method_code is null" → Expected if Mageplaza not configured
├─ "Cannot read property of undefined" → Check component initialization
├─ "selectShippingMethodAction not defined" → RequireJS loading issue
└─ 404 errors → Check file paths, redeploy static content

Issue 4: Shipping rates not updating on wilaya change
├─ Cause: Cache issue or event not firing
└─ Fix: Clear browser cache, check address update event

EOF

echo ""
echo "═══ VALIDATION CHECKLIST ═══"
echo ""

cat << 'EOF'
After completing all tests, verify:

□ Component initializes without errors
□ Cards appear when valid rates available
□ Error message shown when no valid rates
□ Cards clickable and selectable
□ Selection updates quote properly
□ Next button enables after selection
□ Can proceed to payment step
□ Console has no red errors (warnings OK)
□ Network requests succeed (200 OK)
□ Mobile responsive works
□ Multiple browsers tested

EOF

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  📝 TEST RESULTS"
echo "══════════════════════════════════════════════════════════════"
echo ""
echo "Please perform the manual tests above and document results:"
echo ""
echo "Test 1 (Page Load):       □ Pass  □ Fail"
echo "Test 2 (Select Wilaya):   □ Pass  □ Fail"
echo "Test 3 (Select Commune):  □ Pass  □ Fail"
echo "Test 4 (Select Method):   □ Pass  □ Fail"
echo "Test 5 (Next Button):     □ Pass  □ Fail"
echo "Test 6 (Change Wilaya):   □ Pass  □ Fail"
echo "Test 7 (Console Errors):  □ Pass  □ Fail"
echo "Test 8 (Network):         □ Pass  □ Fail"
echo "Test 9 (Mobile):          □ Pass  □ Fail"
echo "Test 10 (Browsers):       □ Pass  □ Fail"
echo ""
echo "══════════════════════════════════════════════════════════════"
echo ""
echo "For automated testing, run: ./comprehensive-checkout-test.sh"
echo "For configuration help: cat MAGEPLAZA_CONFIGURATION_REQUIRED.md"
echo ""

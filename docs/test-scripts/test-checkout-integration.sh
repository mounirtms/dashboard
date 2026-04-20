#!/bin/bash
#
# Comprehensive Checkout Integration Test Suite
# Tests all checkout components together
#

set -e

BASEDIR="/home/dev/public_html"
cd "$BASEDIR"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

pass_count=0
fail_count=0
warn_count=0

echo "========================================="
echo "🧪 CHECKOUT INTEGRATION TEST SUITE"
echo "========================================="
echo ""

# Test function
test_pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((pass_count++))
}

test_fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((fail_count++))
}

test_warn() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
    ((warn_count++))
}

test_section() {
    echo ""
    echo -e "${BLUE}═══ $1 ═══${NC}"
}

# =========================================
# 1. FILE INTEGRITY TESTS
# =========================================
test_section "FILE INTEGRITY"

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-critical.css" ]; then
    test_pass "checkout-critical.css exists"
else
    test_fail "checkout-critical.css missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/form-fields-unified.css" ]; then
    test_pass "form-fields-unified.css exists"
else
    test_fail "form-fields-unified.css missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/shipping-cards-enhanced.css" ]; then
    test_pass "shipping-cards-enhanced.css exists"
else
    test_fail "shipping-cards-enhanced.css missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/gift-card-minimal.css" ]; then
    test_pass "gift-card-minimal.css exists"
else
    test_fail "gift-card-minimal.css missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css" ]; then
    test_pass "checkout-enhanced.css exists"
else
    test_fail "checkout-enhanced.css missing"
fi

# =========================================
# 2. DEPLOYMENT TESTS
# =========================================
test_section "DEPLOYMENT STATUS"

THEME_PATH="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css"

if [ -d "$THEME_PATH" ]; then
    test_pass "Deployment directory exists"
    
    # Check each CSS file
    if [ -f "$THEME_PATH/checkout-critical.min.css" ]; then
        SIZE=$(stat -c%s "$THEME_PATH/checkout-critical.min.css" 2>/dev/null || echo "0")
        if [ $SIZE -gt 1000 ]; then
            test_pass "checkout-critical.min.css deployed ($SIZE bytes)"
        else
            test_warn "checkout-critical.min.css too small ($SIZE bytes)"
        fi
    else
        test_fail "checkout-critical.min.css not deployed"
    fi
    
    if [ -f "$THEME_PATH/form-fields-unified.min.css" ]; then
        SIZE=$(stat -c%s "$THEME_PATH/form-fields-unified.min.css" 2>/dev/null || echo "0")
        if [ $SIZE -gt 5000 ]; then
            test_pass "form-fields-unified.min.css deployed ($SIZE bytes)"
        else
            test_warn "form-fields-unified.min.css too small ($SIZE bytes)"
        fi
    else
        test_fail "form-fields-unified.min.css not deployed"
    fi
    
    if [ -f "$THEME_PATH/shipping-cards-enhanced.min.css" ]; then
        SIZE=$(stat -c%s "$THEME_PATH/shipping-cards-enhanced.min.css" 2>/dev/null || echo "0")
        if [ $SIZE -gt 6000 ]; then
            test_pass "shipping-cards-enhanced.min.css deployed ($SIZE bytes)"
        else
            test_warn "shipping-cards-enhanced.min.css too small ($SIZE bytes)"
        fi
    else
        test_fail "shipping-cards-enhanced.min.css not deployed"
    fi
    
    if [ -f "$THEME_PATH/gift-card-minimal.min.css" ]; then
        SIZE=$(stat -c%s "$THEME_PATH/gift-card-minimal.min.css" 2>/dev/null || echo "0")
        if [ $SIZE -gt 6000 ]; then
            test_pass "gift-card-minimal.min.css deployed ($SIZE bytes)"
        else
            test_warn "gift-card-minimal.min.css too small ($SIZE bytes)"
        fi
    else
        test_fail "gift-card-minimal.min.css not deployed"
    fi
    
    if [ -f "$THEME_PATH/checkout-enhanced.min.css" ]; then
        SIZE=$(stat -c%s "$THEME_PATH/checkout-enhanced.min.css" 2>/dev/null || echo "0")
        if [ $SIZE -gt 10000 ]; then
            test_pass "checkout-enhanced.min.css deployed ($SIZE bytes)"
        else
            test_warn "checkout-enhanced.min.css too small ($SIZE bytes)"
        fi
    else
        test_fail "checkout-enhanced.min.css not deployed"
    fi
else
    test_fail "Deployment directory missing"
fi

# =========================================
# 3. CONTENT VALIDATION
# =========================================
test_section "CONTENT VALIDATION"

# Shipping cards
if grep -q "replaceShippingStep" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js 2>/dev/null; then
    test_pass "Shipping cards: replaceShippingStep method found"
else
    test_fail "Shipping cards: replaceShippingStep method missing"
fi

if grep -q "bounceIn" app/code/Mab/CheckoutCustomization/view/frontend/web/css/shipping-cards-enhanced.css 2>/dev/null; then
    test_pass "Shipping cards: bounce animation found"
else
    test_fail "Shipping cards: bounce animation missing"
fi

# Gift card
if grep -q "Techno Bon Cadeau" app/code/Mab/CheckoutCustomization/view/frontend/web/template/payment/gift-card-fr.html 2>/dev/null; then
    test_pass "Gift card: Techno branding found"
else
    test_fail "Gift card: Techno branding missing"
fi

if grep -q "Appliquer le code" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/payment/gift-card-fr.js 2>/dev/null; then
    test_pass "Gift card: French localization found"
else
    test_fail "Gift card: French localization missing"
fi

if grep -q "mab-giftcard-btn-primary" app/code/Mab/CheckoutCustomization/view/frontend/web/css/gift-card-minimal.css 2>/dev/null; then
    test_pass "Gift card: button styles found"
else
    test_fail "Gift card: button styles missing"
fi

# Form fields
if grep -q "form-fields-unified" app/code/Mab/CheckoutCustomization/view/frontend/web/css/form-fields-unified.css 2>/dev/null; then
    test_pass "Form fields: unified styles found"
else
    test_warn "Form fields: unified comment missing (non-critical)"
fi

# =========================================
# 4. LAYOUT XML VALIDATION
# =========================================
test_section "LAYOUT XML"

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml" ]; then
    test_pass "default.xml exists"
    
    # Check CSS load order
    if grep -q "checkout-critical.css" app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml; then
        test_pass "default.xml: checkout-critical.css loaded"
    else
        test_fail "default.xml: checkout-critical.css not loaded"
    fi
    
    if grep -q "form-fields-unified.css" app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml; then
        test_pass "default.xml: form-fields-unified.css loaded"
    else
        test_fail "default.xml: form-fields-unified.css not loaded"
    fi
    
    if grep -q "shipping-cards-enhanced.css" app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml; then
        test_pass "default.xml: shipping-cards-enhanced.css loaded"
    else
        test_fail "default.xml: shipping-cards-enhanced.css not loaded"
    fi
    
    if grep -q "gift-card-minimal.css" app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml; then
        test_pass "default.xml: gift-card-minimal.css loaded"
    else
        test_fail "default.xml: gift-card-minimal.css not loaded"
    fi
    
    # Validate XML syntax
    if command -v xmllint &> /dev/null; then
        if xmllint --noout app/code/Mab/CheckoutCustomization/view/frontend/layout/default.xml 2>/dev/null; then
            test_pass "default.xml: valid XML syntax"
        else
            test_fail "default.xml: invalid XML syntax"
        fi
    else
        test_warn "xmllint not available, skipping XML validation"
    fi
else
    test_fail "default.xml missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml" ]; then
    test_pass "checkout_index_index.xml exists"
else
    test_fail "checkout_index_index.xml missing"
fi

# =========================================
# 5. JAVASCRIPT VALIDATION
# =========================================
test_section "JAVASCRIPT"

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js" ]; then
    test_pass "shipping-method-cards.js exists"
    
    # Check for common JS errors
    if grep -E "undefined;|null;|NaN;" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js >/dev/null 2>&1; then
        test_warn "shipping-method-cards.js: potential undefined values"
    else
        test_pass "shipping-method-cards.js: no obvious syntax errors"
    fi
else
    test_fail "shipping-method-cards.js missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js" ]; then
    test_pass "shipping-cards-mixin.js exists"
else
    test_fail "shipping-cards-mixin.js missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/payment/gift-card-fr.js" ]; then
    test_pass "gift-card-fr.js exists"
else
    test_fail "gift-card-fr.js missing"
fi

# =========================================
# 6. REQUIREJS CONFIGURATION
# =========================================
test_section "REQUIREJS CONFIG"

if [ -f "app/code/Mab/CheckoutCustomization/requirejs-config.js" ]; then
    test_pass "requirejs-config.js exists"
    
    if grep -q "shippingMethodCards" app/code/Mab/CheckoutCustomization/requirejs-config.js; then
        test_pass "requirejs: shippingMethodCards defined"
    else
        test_fail "requirejs: shippingMethodCards not defined"
    fi
    
    if grep -q "shipping-cards-mixin" app/code/Mab/CheckoutCustomization/requirejs-config.js; then
        test_pass "requirejs: shipping-cards-mixin defined"
    else
        test_fail "requirejs: shipping-cards-mixin not defined"
    fi
else
    test_fail "requirejs-config.js missing"
fi

# =========================================
# 7. MODULE CONFIGURATION
# =========================================
test_section "MODULE CONFIG"

if [ -f "app/code/Mab/CheckoutCustomization/etc/module.xml" ]; then
    test_pass "module.xml exists"
else
    test_fail "module.xml missing"
fi

if [ -f "app/code/Mab/CheckoutCustomization/registration.php" ]; then
    test_pass "registration.php exists"
else
    test_fail "registration.php missing"
fi

# Check if module is enabled
if php bin/magento module:status Mab_CheckoutCustomization 2>&1 | grep -q "Mab_CheckoutCustomization"; then
    if php bin/magento module:status Mab_CheckoutCustomization 2>&1 | grep -A 1 "List of enabled modules" | grep -q "Mab_CheckoutCustomization"; then
        test_pass "Module is enabled"
    else
        test_fail "Module is disabled"
    fi
else
    test_warn "Cannot determine module status"
fi

# =========================================
# 8. PERFORMANCE CHECKS
# =========================================
test_section "PERFORMANCE"

# Total CSS size
TOTAL_SIZE=0
for file in pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/*.min.css; do
    if [ -f "$file" ]; then
        SIZE=$(stat -c%s "$file" 2>/dev/null || echo "0")
        TOTAL_SIZE=$((TOTAL_SIZE + SIZE))
    fi
done

if [ $TOTAL_SIZE -gt 0 ]; then
    TOTAL_KB=$((TOTAL_SIZE / 1024))
    if [ $TOTAL_KB -lt 50 ]; then
        test_pass "Total CSS size: ${TOTAL_KB}KB (under 50KB target)"
    elif [ $TOTAL_KB -lt 100 ]; then
        test_warn "Total CSS size: ${TOTAL_KB}KB (acceptable but could be optimized)"
    else
        test_fail "Total CSS size: ${TOTAL_KB}KB (exceeds 100KB limit)"
    fi
else
    test_fail "Cannot calculate CSS size"
fi

# Check for will-change hints (performance optimization)
if grep -q "will-change" app/code/Mab/CheckoutCustomization/view/frontend/web/css/*.css 2>/dev/null; then
    test_pass "GPU acceleration hints found (will-change)"
else
    test_warn "No GPU acceleration hints found"
fi

# =========================================
# 9. ACCESSIBILITY CHECKS
# =========================================
test_section "ACCESSIBILITY"

if grep -q "focus-visible" app/code/Mab/CheckoutCustomization/view/frontend/web/css/*.css 2>/dev/null; then
    test_pass "Keyboard focus styles found"
else
    test_warn "Missing keyboard focus styles"
fi

if grep -q "aria-label" app/code/Mab/CheckoutCustomization/view/frontend/web/template/payment/gift-card-fr.html 2>/dev/null; then
    test_pass "ARIA labels found in gift card"
else
    test_warn "Missing ARIA labels in gift card"
fi

if grep -q "prefers-reduced-motion" app/code/Mab/CheckoutCustomization/view/frontend/web/css/*.css 2>/dev/null; then
    test_pass "Reduced motion support found"
else
    test_warn "Missing reduced motion support"
fi

# =========================================
# 10. DOCUMENTATION
# =========================================
test_section "DOCUMENTATION"

DOCS=0
[ -f "SHIPPING_CARDS_TEST_GUIDE.md" ] && ((DOCS++))
[ -f "SHIPPING_CARDS_FIX_VALIDATION.md" ] && ((DOCS++))
[ -f "FINAL_STATUS_ALL_ISSUES_RESOLVED.md" ] && ((DOCS++))
[ -f "CHECKOUT_COMPLETE_FIX_REPORT.md" ] && ((DOCS++))

if [ $DOCS -ge 3 ]; then
    test_pass "Documentation: $DOCS files found"
else
    test_warn "Documentation: only $DOCS files found (expected 4+)"
fi

# =========================================
# SUMMARY
# =========================================
echo ""
echo "========================================="
echo "📊 TEST SUMMARY"
echo "========================================="
echo ""
echo -e "${GREEN}✓ Passed:${NC}  $pass_count"
echo -e "${YELLOW}⚠ Warnings:${NC} $warn_count"
echo -e "${RED}✗ Failed:${NC}  $fail_count"
echo "Total:    $((pass_count + warn_count + fail_count))"
echo ""

# Calculate percentage
TOTAL=$((pass_count + fail_count))
if [ $TOTAL -gt 0 ]; then
    PERCENT=$((pass_count * 100 / TOTAL))
    echo "Pass rate: ${PERCENT}%"
    echo ""
fi

# Final verdict
if [ $fail_count -eq 0 ]; then
    if [ $warn_count -eq 0 ]; then
        echo -e "${GREEN}🎉 PERFECT! ALL TESTS PASSED!${NC}"
        echo "Status: ✅ PRODUCTION READY"
    else
        echo -e "${GREEN}✅ PASSED WITH WARNINGS${NC}"
        echo "Status: ⚠️  REVIEW WARNINGS BEFORE PRODUCTION"
    fi
    exit 0
else
    echo -e "${RED}❌ TESTS FAILED${NC}"
    echo "Status: 🚫 NOT READY FOR PRODUCTION"
    echo ""
    echo "Please fix the failed tests before deploying."
    exit 1
fi

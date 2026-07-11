#!/bin/bash

echo "==================================="
echo "CHECKOUT DESKTOP FIELDS TEST"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "==================================="
echo ""

# Check if CSS is deployed
echo "1. CSS Deployment Status:"
CSS_FILE="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-professional.min.css"
if [ -f "$CSS_FILE" ]; then
    SIZE=$(du -h "$CSS_FILE" | cut -f1)
    echo "   ✓ checkout-professional.min.css deployed ($SIZE)"
    
    # Check for critical CSS rules
    echo ""
    echo "2. Critical CSS Rules Check:"
    
    if grep -q "width: 100%" "$CSS_FILE"; then
        echo "   ✓ Field width: 100% found"
    else
        echo "   ✗ Field width: 100% NOT FOUND"
    fi
    
    if grep -q "min-height: 40px" "$CSS_FILE"; then
        echo "   ✓ Min-height: 40px found"
    else
        echo "   ✗ Min-height: 40px NOT FOUND"
    fi
    
    if grep -q "region_id" "$CSS_FILE" || grep -q "region" "$CSS_FILE"; then
        echo "   ✓ Region/state field rules found"
    else
        echo "   ✗ Region/state field rules NOT FOUND"
    fi
    
    if grep -q "grid-template-columns" "$CSS_FILE"; then
        echo "   ✓ Two-column grid found"
    else
        echo "   ✗ Two-column grid NOT FOUND"
    fi
else
    echo "   ✗ CSS file not found!"
fi

echo ""
echo "3. Layout XML Status:"
XML_FILE="app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml"
if [ -f "$XML_FILE" ]; then
    CSS_COUNT=$(grep -c "checkout-professional.css" "$XML_FILE")
    if [ "$CSS_COUNT" -gt 0 ]; then
        echo "   ✓ Layout loads checkout-professional.css"
    else
        echo "   ✗ Layout does NOT load checkout-professional.css"
    fi
    
    # Check for other CSS files that might conflict
    echo "   CSS files loaded:"
    grep '<css src=' "$XML_FILE" | sed 's/.*src="//; s/".*$//' | while read line; do
        echo "     - $line"
    done
else
    echo "   ✗ Layout XML not found!"
fi

echo ""
echo "4. Theme Configuration:"
THEME_LESS="app/design/frontend/Sm/market/web/css/source/pages/checkout/_styles.less"
if [ -f "$THEME_LESS" ]; then
    LINES=$(wc -l < "$THEME_LESS")
    echo "   ✓ Theme checkout LESS exists ($LINES lines)"
    
    if grep -q "max-width: unset" "$THEME_LESS"; then
        echo "   ✓ Theme allows full-width forms"
    fi
else
    echo "   - Theme LESS not found (using defaults)"
fi

echo ""
echo "5. Cache Status:"
php bin/magento cache:status | grep -E "(layout|block_html|full_page)" | while read line; do
    echo "   $line"
done

echo ""
echo "==================================="
echo "Test Complete!"
echo "==================================="
echo ""
echo "Dev Checkout URL:"
echo "https://dev.technostationery.com/checkout"
echo ""

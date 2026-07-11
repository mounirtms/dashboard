#!/bin/bash
CSS_FILE="pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-professional.min.css"

echo "Checking CSS Rules in Minified File..."
echo ""

# Check for input styling
if grep -q "\.checkout-index-index input\[type" "$CSS_FILE"; then
    echo "✓ Input type selectors found"
fi

# Check for select styling  
if grep -q "\.checkout-index-index select" "$CSS_FILE"; then
    echo "✓ Select selectors found"
fi

# Check for width 100%
if grep -q "width:100%" "$CSS_FILE"; then
    echo "✓ width:100% rules found"
    COUNT=$(grep -o "width:100%" "$CSS_FILE" | wc -l)
    echo "  → Count: $COUNT occurrences"
fi

# Check for min-height
if grep -q "min-height:40px" "$CSS_FILE"; then
    echo "✓ min-height:40px rules found"
fi

# Check for padding
if grep -q "padding:10px 12px" "$CSS_FILE"; then
    echo "✓ padding:10px 12px rules found"
fi

# Check for border-radius
if grep -q "border-radius:3px" "$CSS_FILE"; then
    echo "✓ border-radius:3px rules found"
fi

# Check for grid
if grep -q "grid-template-columns:1fr 1fr" "$CSS_FILE"; then
    echo "✓ Two-column grid (1fr 1fr) found"
fi

echo ""
echo "File size: $(du -h "$CSS_FILE" | cut -f1)"

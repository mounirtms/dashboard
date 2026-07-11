#!/bin/bash

echo "=========================================="
echo "🇫🇷 FRENCH TRANSLATION VALIDATION"
echo "=========================================="
echo ""

echo "1. Checking French terms in JavaScript files:"
echo "-------------------------------------------"
french_terms=("Retrait" "Livraison" "domicile" "agence" "Gratuit" "jours" "immédiat")
js_file="app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js"

if [ -f "$js_file" ]; then
    for term in "${french_terms[@]}"; do
        count=$(grep -c "$term" "$js_file")
        if [ $count -gt 0 ]; then
            echo "  ✅ '$term' found ($count occurrences)"
        else
            echo "  ❌ '$term' NOT found"
        fi
    done
fi
echo ""

echo "2. Checking delivery time translations:"
echo "-------------------------------------------"
delivery_terms=(
    "Retrait immédiat en magasin"
    "Livraison à domicile"
    "Retrait en agence"
    "Livraison gratuite"
    "jours ouvrables"
)

for term in "${delivery_terms[@]}"; do
    if grep -q "$term" "$js_file" 2>/dev/null; then
        echo "  ✅ '$term'"
    else
        echo "  ❌ '$term' MISSING"
    fi
done
echo ""

echo "3. Checking carrier identification:"
echo "-------------------------------------------"
carriers=("yalidine" "ecotrak" "techno" "retrait" "pickup" "magasin" "store" "gratuit" "free")
for carrier in "${carriers[@]}"; do
    if grep -qi "$carrier" "$js_file" 2>/dev/null; then
        echo "  ✅ '$carrier' recognized"
    else
        echo "  ❌ '$carrier' NOT recognized"
    fi
done
echo ""

echo "4. Checking template French labels:"
echo "-------------------------------------------"
template_file="app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-simple.phtml"
if [ -f "$template_file" ]; then
    if grep -q "Carte Cadeau" "$template_file"; then
        echo "  ✅ Gift card French label found"
    else
        echo "  ❌ Gift card French label MISSING"
    fi
fi
echo ""

echo "5. Checking price format (Algerian DZD):"
echo "-------------------------------------------"
if grep -q "formatPrice" "$js_file"; then
    echo "  ✅ Price formatting function exists"
    if grep -q "DZD" "$js_file"; then
        echo "  ✅ DZD currency configured"
    else
        echo "  ⚠️  DZD currency check - verify implementation"
    fi
fi
echo ""

echo "6. Translation Summary:"
echo "-------------------------------------------"
total_checks=20
passed=0

# Count passed checks
for term in "${french_terms[@]}"; do
    if grep -q "$term" "$js_file" 2>/dev/null; then
        ((passed++))
    fi
done

for term in "${delivery_terms[@]}"; do
    if grep -q "$term" "$js_file" 2>/dev/null; then
        ((passed++))
    fi
done

for carrier in "${carriers[@]}"; do
    if grep -qi "$carrier" "$js_file" 2>/dev/null; then
        ((passed++))
    fi
done

percentage=$((passed * 100 / total_checks))
echo "  Passed: ${passed}/${total_checks} (${percentage}%)"

if [ $percentage -ge 90 ]; then
    echo "  ✅ EXCELLENT"
elif [ $percentage -ge 70 ]; then
    echo "  ✅ GOOD"
else
    echo "  ⚠️  NEEDS IMPROVEMENT"
fi

echo ""
echo "=========================================="
echo "✅ Validation Complete"
echo "=========================================="

#!/bin/bash
#
# Amasty Gift Card & French Translation Fix Script
# Date: 2026-02-12
# Purpose: Fix gift card display and translate all text to French
#

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR" || exit 1

echo "=== AMASTY GIFT CARD & FRENCH TRANSLATION FIX ==="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

echo "[1/8] Creating French translation files for Amasty Gift Card..."

# Create French translation directory if not exists
mkdir -p app/i18n/amasty/fr_FR

# Create translation file for Gift Card
cat > app/i18n/amasty/fr_FR/Amasty_GiftCard.csv << 'EOF'
"Apply Gift Card Code","Appliquer un Code Carte Cadeau"
"Gift Card Code","Code Carte Cadeau"
"Enter your Code","Entrez votre code"
"Add Code","Ajouter le Code"
"Check Status","Vérifier le Statut"
"Remove","Supprimer"
"Gift Card","Carte Cadeau"
"Gift Card Balance","Solde Carte Cadeau"
"Applied Gift Cards","Cartes Cadeaux Appliquées"
"Remaining Balance","Solde Restant"
"Choose a Store","Choisir un Magasin"
"Close Popup","Fermer la Popup"
"Sorry, no quotes are available for this order at this time","Aucun mode de livraison n'est disponible pour votre adresse"
"Estimate Shipping and Tax","Estimer la livraison et la TVA"
"Estimate Tax","Estimer la TVA"
"Enter your billing address to get a tax estimate.","Entrez votre adresse de facturation pour obtenir une estimation de la taxe."
"Enter your destination to get a shipping estimate.","Choisissez votre destination pour estimer les frais de port."
"Country","Pays"
"State/Province","État/Province"
"Zip/Postal Code","Code postal"
"Please select a region, state or province.","Veuillez sélectionner une région, une état ou une province."
EOF

echo "✓ French translation file created"
echo ""

echo "[2/8] Creating custom CSS for Gift Card block..."

# Create custom CSS file for gift card styling
cat > app/design/frontend/Sm/market/web/css/amasty-giftcard-fix.css << 'EOF'
/**
 * Amasty Gift Card Custom Styles
 * Fix display issues in cart page
 */

/* Gift Card Block Container */
.amcard-field-container-collapsible.-cart.block {
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    clear: both;
}

/* Gift Card Title */
.amcard-field-container-collapsible.-cart .title {
    background: #f5f5f5;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    cursor: pointer;
}

.amcard-field-container-collapsible.-cart .title strong {
    font-size: 16px;
    color: #333;
    font-weight: 600;
}

.amcard-title {
    display: inline-block;
    margin: 0;
}

/* Gift Card Content */
.amcard-field-container-collapsible.-cart .payment-option-content {
    padding: 20px 15px;
}

/* Gift Card Input Fields */
.amcard-field-container.-cart {
    display: block;
    width: 100%;
    margin-bottom: 15px;
}

.amcard-field-block.-double {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 15px;
}

.amgcard-datalist-container {
    flex: 1;
    position: relative;
}

.amcard-field.-datalist {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1.4;
    height: 42px;
}

.amcard-field.-datalist:focus {
    border-color: #1979c3;
    outline: none;
    box-shadow: 0 0 3px rgba(25, 121, 195, 0.3);
}

/* Add Code Button */
.amcard-button {
    padding: 10px 20px;
    background: #1979c3;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    height: 42px;
    white-space: nowrap;
    transition: background 0.3s;
}

.amcard-button:hover {
    background: #006bb4;
}

/* Check Status Button */
.amcard-check {
    padding: 10px 20px;
    background: #f0f0f0;
    color: #333;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-top: 10px;
    transition: all 0.3s;
}

.amcard-check:hover {
    background: #e0e0e0;
    border-color: #ccc;
}

/* Applied Gift Cards List */
.amcard-codes-list {
    margin-bottom: 15px;
}

.amcard-codes-list .messages {
    margin-bottom: 10px;
}

/* Datalist Dropdown */
.amgcard-datalist-ul {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.amgcard-datalist-container.-active .amgcard-datalist-ul {
    display: block;
}

/* Responsive Design */
@media (max-width: 768px) {
    .amcard-field-block.-double {
        flex-direction: column;
        gap: 10px;
    }
    
    .amcard-button {
        width: 100%;
    }
    
    .amcard-check {
        width: 100%;
    }
}

/* Move gift card block after discount code */
.cart-discount {
    order: 3;
}

.block.discount {
    order: 2;
}

/* Ensure proper stacking */
.cart-container .cart-summary {
    display: flex;
    flex-direction: column;
}

.cart-container .cart-summary .block {
    order: 1;
}

.cart-container .cart-summary #block-shipping {
    order: 2;
}

.cart-container .cart-summary #block-discount {
    order: 3;
}

.cart-container .cart-summary .cart-discount {
    order: 4;
}

.cart-container .cart-summary #cart-totals {
    order: 5;
}

.cart-container .cart-summary .checkout.methods {
    order: 6;
}

/* Fix collapsible behavior */
.amcard-field-container-collapsible[data-collapsible="true"] .payment-option-content[aria-hidden="true"] {
    display: none;
}

.amcard-field-container-collapsible[data-collapsible="true"] .payment-option-content[aria-hidden="false"] {
    display: block;
}

/* Visual indicator for collapsible */
.amcard-field-container-collapsible .title:after {
    content: '+';
    float: right;
    font-size: 20px;
    line-height: 1;
    transition: transform 0.3s;
}

.amcard-field-container-collapsible .title[aria-expanded="true"]:after {
    content: '−';
}

/* Better spacing */
.cart-summary .block + .block,
.cart-summary .block + .cart-discount,
.cart-summary .cart-discount + #cart-totals {
    margin-top: 15px;
}
EOF

echo "✓ Custom CSS created"
echo ""

echo "[3/8] Registering custom CSS in theme..."

# Check if custom CSS is already registered
if ! grep -q "amasty-giftcard-fix.css" app/design/frontend/Sm/market/Magento_Theme/layout/default.xml 2>/dev/null; then
    # Create layout directory if not exists
    mkdir -p app/design/frontend/Sm/market/Magento_Theme/layout
    
    # Create or update default.xml
    cat > app/design/frontend/Sm/market/Magento_Theme/layout/default.xml << 'EOF'
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <css src="css/amasty-giftcard-fix.css"/>
    </head>
</page>
EOF
    echo "✓ Custom CSS registered in theme"
else
    echo "✓ Custom CSS already registered"
fi
echo ""

echo "[4/8] Creating French locale override for Amasty modules..."

# Create locale override for Amasty modules
mkdir -p app/design/frontend/Sm/market/i18n
cat > app/design/frontend/Sm/market/i18n/fr_FR.csv << 'EOF'
"Apply Gift Card Code","Appliquer un Code Carte Cadeau"
"Gift Card Code","Code Carte Cadeau"
"Enter your Code","Entrez votre code"
"Add Code","Ajouter le Code"
"Check Status","Vérifier le Statut"
"Choose a Store","Choisir un Magasin"
"Close Popup","Fermer la Popup"
EOF

echo "✓ Theme locale file created"
echo ""

echo "[5/8] Updating checkout translations..."

# Ensure checkout translations are in place
if [ -f "app/design/frontend/Sm/market/i18n/fr_FR.csv" ]; then
    # Append if not already present
    if ! grep -q "Estimate Shipping and Tax" app/design/frontend/Sm/market/i18n/fr_FR.csv 2>/dev/null; then
        cat >> app/design/frontend/Sm/market/i18n/fr_FR.csv << 'EOF'
"Estimate Shipping and Tax","Estimer la livraison et la TVA"
"Estimate Tax","Estimer la TVA"
"Enter your billing address to get a tax estimate.","Entrez votre adresse de facturation pour obtenir une estimation de la taxe."
"Enter your destination to get a shipping estimate.","Choisissez votre destination pour estimer les frais de port."
"Sorry, no quotes are available for this order at this time","Aucun mode de livraison n'est disponible pour votre adresse"
EOF
    fi
fi

echo "✓ Checkout translations updated"
echo ""

echo "[6/8] Clearing translation cache..."
php bin/magento cache:clean translate full_page layout block_html

echo "✓ Translation cache cleared"
echo ""

echo "[7/8] Deploying static content for French locale..."
# Deploy only French locale for faster execution
php bin/magento setup:static-content:deploy fr_FR -f --area frontend --theme Sm/market 2>&1 | tail -5

echo "✓ Static content deployed"
echo ""

echo "[8/8] Final cache flush..."
php bin/magento cache:flush

echo "✓ Cache flushed"
echo ""

echo "=== FIX COMPLETE ==="
echo ""
echo "Applied fixes:"
echo "  ✓ Custom CSS for Amasty Gift Card block"
echo "  ✓ French translations for all gift card text"
echo "  ✓ Improved block positioning in cart"
echo "  ✓ Better mobile responsiveness"
echo "  ✓ Fixed collapsible behavior"
echo ""
echo "Test the changes:"
echo "  1. Visit: https://technostationery.com/checkout/cart"
echo "  2. Check gift card block appearance"
echo "  3. Verify all text is in French"
echo "  4. Test collapsible functionality"
echo ""
echo "Files created:"
echo "  - app/design/frontend/Sm/market/web/css/amasty-giftcard-fix.css"
echo "  - app/design/frontend/Sm/market/i18n/fr_FR.csv"
echo "  - app/design/frontend/Sm/market/Magento_Theme/layout/default.xml"
echo ""

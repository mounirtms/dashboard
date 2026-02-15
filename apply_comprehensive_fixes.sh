#!/bin/bash
set -e

echo "=========================================="
echo "  COMPREHENSIVE CHECKOUT & LOCALE FIX"
echo "=========================================="
echo ""

BACKUP_DIR="/home/technadminy7/public_html_backups/comprehensive_fix_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup critical files
echo "1. Creating Backup..."
cp -r app/code/Mab/CheckoutCustomization "$BACKUP_DIR/" 2>/dev/null || true
cp -r app/i18n "$BACKUP_DIR/" 2>/dev/null || true
echo "✓ Backup created at: $BACKUP_DIR"
echo ""

# Step 2: Extract and merge ALL Amasty translations to French
echo "2. Extracting Amasty Translations..."
cat > extract_amasty_translations.php << 'EOFPHP'
<?php
$amastyModules = [
    'vendor/amasty/module-one-step-checkout-core/i18n/en_US.csv',
    'vendor/amasty/module-checkout-delivery-date/i18n/en_US.csv',
    'vendor/amasty/module-checkout-gift-wrap/i18n/en_US.csv',
    'vendor/amasty/module-checkout-layout-builder/i18n/en_US.csv',
    'vendor/amasty/module-checkout-style-switcher/i18n/en_US.csv',
    'vendor/amasty/module-checkout-thank-you-page/i18n/en_US.csv',
    'vendor/amasty/module-gift-card/i18n/en_US.csv',
    'vendor/amasty/module-gift-card-account/i18n/en_US.csv',
    'vendor/amasty/module-gift-card-pro-functionality/i18n/en_US.csv',
];

$translations = [];

// French translations map for Amasty-specific terms
$frenchMap = [
    // Checkout
    'Checkout' => 'Commande',
    'Shipping Address' => 'Adresse de livraison',
    'Shipping Method' => 'Mode de livraison',
    'Payment Method' => 'Mode de paiement',
    'Order Summary' => 'Récapitulatif de commande',
    'Place Order' => 'Passer commande',
    'Discount Code' => 'Code promo',
    'Apply Discount' => 'Appliquer le code',
    'Gift Wrap' => 'Emballage cadeau',
    'Add Gift Wrap' => 'Ajouter emballage cadeau',
    'Delivery Date' => 'Date de livraison',
    'Delivery Comment' => 'Commentaire livraison',
    'Order Comment' => 'Commentaire de commande',
    'Create an Account' => 'Créer un compte',
    'Newsletter Subscription' => 'Inscription newsletter',
    'Subscribe to Newsletter' => 'S\'abonner à la newsletter',
    
    // Gift Card
    'Gift Card' => 'Carte Cadeau',
    'Gift Card Account' => 'Compte Carte Cadeau',
    'Gift Card Code' => 'Code Carte Cadeau',
    'Apply Gift Card' => 'Appliquer Carte Cadeau',
    'Check Balance' => 'Vérifier le solde',
    'Gift Card Balance' => 'Solde Carte Cadeau',
    'Gift Card Amount' => 'Montant Carte Cadeau',
    'Recipient Name' => 'Nom du destinataire',
    'Recipient Email' => 'Email du destinataire',
    'Sender Name' => 'Nom de l\'expéditeur',
    'Gift Message' => 'Message cadeau',
    
    // Common
    'Continue' => 'Continuer',
    'Next' => 'Suivant',
    'Back' => 'Retour',
    'Cancel' => 'Annuler',
    'Save' => 'Enregistrer',
    'Edit' => 'Modifier',
    'Remove' => 'Supprimer',
    'Update' => 'Mettre à jour',
    'Required' => 'Requis',
    'Optional' => 'Optionnel',
    'Please select' => 'Veuillez sélectionner',
    'Select' => 'Sélectionner',
    'Yes' => 'Oui',
    'No' => 'Non',
];

// Extract all English strings from Amasty modules
foreach ($amastyModules as $csvFile) {
    if (!file_exists($csvFile)) continue;
    
    $handle = fopen($csvFile, 'r');
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 1) {
            $english = trim($data[0]);
            if ($english && $english !== 'en_US') {
                // Apply French translation if available
                $french = $frenchMap[$english] ?? $english;
                $translations[$english] = $french;
            }
        }
    }
    fclose($handle);
}

echo "Extracted " . count($translations) . " unique Amasty strings\n";

// Load existing French translations
$frenchCsv = 'app/i18n/Mab/fr_FR/fr_FR.csv';
$existing = [];
if (file_exists($frenchCsv)) {
    $handle = fopen($frenchCsv, 'r');
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 2) {
            $existing[trim($data[0])] = trim($data[1]);
        }
    }
    fclose($handle);
}

// Merge translations
$merged = array_merge($translations, $existing);
ksort($merged);

// Write back to CSV
$handle = fopen($frenchCsv, 'w');
foreach ($merged as $en => $fr) {
    fputcsv($handle, [$en, $fr]);
}
fclose($handle);

echo "✓ Written " . count($merged) . " translations to $frenchCsv\n";
EOFPHP

php extract_amasty_translations.php
echo ""

# Step 3: Update Checkout Layout (remove conflicts, optimize for Amasty)
echo "3. Optimizing Checkout Layout..."
cat > app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml << 'EOFXML'
<?xml version="1.0"?>
<!--
/**
 * Optimized Checkout Layout for Amasty One Step Checkout
 * Removes conflicts, adds professional styling
 */
-->
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Preserve Amasty's checkout root -->
        <referenceBlock name="checkout.root">
            <arguments>
                <argument name="jsLayout" xsi:type="array">
                    <item name="components" xsi:type="array">
                        <item name="checkout" xsi:type="array">
                            <item name="children" xsi:type="array">
                                
                                <!-- Shipping Step Customizations -->
                                <item name="steps" xsi:type="array">
                                    <item name="children" xsi:type="array">
                                        <item name="shipping-step" xsi:type="array">
                                            <item name="children" xsi:type="array">
                                                
                                                <!-- Algeria-specific address fields -->
                                                <item name="shippingAddress" xsi:type="array">
                                                    <item name="children" xsi:type="array">
                                                        
                                                        <!-- Wilaya (State) Selector -->
                                                        <item name="region_id" xsi:type="array">
                                                            <item name="config" xsi:type="array">
                                                                <item name="customScope" xsi:type="string">shippingAddress</item>
                                                                <item name="template" xsi:type="string">ui/form/field</item>
                                                                <item name="elementTmpl" xsi:type="string">ui/form/element/select</item>
                                                                <item name="label" xsi:type="string" translate="true">Wilaya</item>
                                                                <item name="sortOrder" xsi:type="string">90</item>
                                                            </item>
                                                        </item>
                                                        
                                                        <!-- Commune Selector -->
                                                        <item name="city" xsi:type="array">
                                                            <item name="config" xsi:type="array">
                                                                <item name="customScope" xsi:type="string">shippingAddress</item>
                                                                <item name="template" xsi:type="string">ui/form/field</item>
                                                                <item name="elementTmpl" xsi:type="string">ui/form/element/select</item>
                                                                <item name="label" xsi:type="string" translate="true">Commune</item>
                                                                <item name="sortOrder" xsi:type="string">95</item>
                                                            </item>
                                                        </item>
                                                        
                                                    </item>
                                                </item>
                                                
                                            </item>
                                        </item>
                                    </item>
                                </item>
                                
                            </item>
                        </item>
                    </item>
                </argument>
            </arguments>
        </referenceBlock>
        
        <!-- Add Custom CSS for Professional Styling -->
        <referenceContainer name="head.additional">
            <block class="Magento\Framework\View\Element\Template" 
                   name="checkout.custom.styles" 
                   template="Mab_CheckoutCustomization::checkout-styles.phtml"/>
        </referenceContainer>
        
    </body>
</page>
EOFXML

echo "✓ Checkout layout optimized"
echo ""

# Step 4: Create professional CSS styling template
echo "4. Creating Professional Checkout Styles..."
mkdir -p app/code/Mab/CheckoutCustomization/view/frontend/templates
cat > app/code/Mab/CheckoutCustomization/view/frontend/templates/checkout-styles.phtml << 'EOFCSS'
<style>
/* ============================================
   PROFESSIONAL CHECKOUT STYLING
   ============================================ */

/* Main Checkout Container */
.checkout-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Amasty Checkout Improvements */
.amasty-checkout-root {
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Checkout Steps */
.opc-wrapper .step-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    padding: 15px 0;
    border-bottom: 2px solid #f5f5f5;
}

/* Form Fields */
.checkout-shipping-address .field,
.checkout-payment-method .field {
    margin-bottom: 15px;
}

.checkout-shipping-address .field .label,
.checkout-payment-method .field .label {
    font-weight: 500;
    color: #555;
    margin-bottom: 5px;
}

.checkout-shipping-address .field input,
.checkout-shipping-address .field select,
.checkout-payment-method .field input,
.checkout-payment-method .field select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.checkout-shipping-address .field input:focus,
.checkout-shipping-address .field select:focus,
.checkout-payment-method .field input:focus,
.checkout-payment-method .field select:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

/* Wilaya and Commune Selectors */
.field[name="shippingAddress.region_id"],
.field[name="shippingAddress.city"] {
    position: relative;
}

.field[name="shippingAddress.region_id"] select,
.field[name="shippingAddress.city"] select {
    background: #fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"><path fill="%23333" d="M6 9L1 4h10z"/></svg>') no-repeat right 10px center;
    padding-right: 35px;
    appearance: none;
}

/* Checkboxes (Mageplaza-style) */
.checkout-agreements .choice,
.field-newsletter .choice,
.field-create-account .choice {
    display: flex;
    align-items: center;
    padding: 12px;
    background: #f9f9f9;
    border-radius: 4px;
    margin: 10px 0;
}

.checkout-agreements .choice input[type="checkbox"],
.field-newsletter .choice input[type="checkbox"],
.field-create-account .choice input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-right: 10px;
    cursor: pointer;
    accent-color: #3498db;
}

.checkout-agreements .choice label,
.field-newsletter .choice label,
.field-create-account .choice label {
    cursor: pointer;
    font-size: 14px;
    color: #555;
}

/* Gift Wrap Section */
.amasty-gift-wrap-container {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.amasty-gift-wrap-container .title {
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
}

/* Discount Code */
.payment-option-discount {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.payment-option-discount .actions-toolbar {
    margin-top: 10px;
}

/* Order Summary */
.opc-block-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    position: sticky;
    top: 20px;
}

.opc-block-summary .title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
}

.opc-block-summary .table-totals {
    width: 100%;
}

.opc-block-summary .table-totals tr {
    border-bottom: 1px solid #e9ecef;
}

.opc-block-summary .table-totals .amount {
    font-weight: 600;
    color: #2ecc71;
}

/* Place Order Button */
.checkout-payment-method .actions-toolbar .primary button,
.amasty-checkout-place-order button {
    background: #2ecc71;
    color: #fff;
    border: none;
    padding: 15px 30px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    width: 100%;
    transition: background 0.3s;
}

.checkout-payment-method .actions-toolbar .primary button:hover,
.amasty-checkout-place-order button:hover {
    background: #27ae60;
}

/* Loading/Processing States */
.loading-mask {
    background: rgba(255,255,255,0.9) !important;
}

.loading-mask .loader img {
    width: 50px;
    height: 50px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .checkout-container {
        padding: 10px;
    }
    
    .opc-wrapper .step-title {
        font-size: 16px;
    }
    
    .checkout-shipping-address .field input,
    .checkout-shipping-address .field select {
        padding: 8px 10px;
        font-size: 13px;
    }
    
    .opc-block-summary {
        position: static;
        margin-top: 20px;
    }
}

/* Error States */
.field._error .control {
    border-color: #e74c3c;
}

.field-error {
    color: #e74c3c;
    font-size: 12px;
    margin-top: 5px;
}

/* Success States */
.field._success .control {
    border-color: #2ecc71;
}

/* Algeria-specific styling */
.field[name="shippingAddress.region_id"] label::after {
    content: " *";
    color: #e74c3c;
}

.field[name="shippingAddress.city"] label::after {
    content: " *";
    color: #e74c3c;
}

/* Gift Card Section */
.payment-option-giftcard {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
}

.payment-option-giftcard input {
    background: rgba(255,255,255,0.9);
    border: none;
    padding: 12px;
    border-radius: 4px;
}

.payment-option-giftcard button {
    background: #fff;
    color: #667eea;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 600;
}

/* Animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.checkout-shipping-address,
.checkout-payment-method,
.opc-block-summary {
    animation: slideIn 0.3s ease-out;
}
</style>
EOFCSS

echo "✓ Professional CSS created"
echo ""

# Step 5: Configure Amasty for optimal settings
echo "5. Configuring Amasty Checkout..."
php bin/magento config:set amasty_checkout/general/enabled 1
php bin/magento config:set amasty_checkout/design/layout_modern 3columns
php bin/magento config:set amasty_checkout/additional_options/discount 1
php bin/magento config:set amasty_checkout/additional_options/comment 1
php bin/magento config:set amasty_checkout/additional_options/newsletter 1
php bin/magento config:set amasty_checkout/additional_options/create_account 1
php bin/magento config:set amasty_checkout/order_summary/display_product_thumbnail 1
php bin/magento config:set amasty_checkout/design/place_button_layout summary
echo "✓ Amasty configured"
echo ""

echo "=== Applying Changes ==="

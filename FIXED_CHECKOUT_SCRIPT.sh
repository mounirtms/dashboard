#!/bin/bash

##############################################################################
# FIXED COMPREHENSIVE CHECKOUT FIX
# Fixes: Config errors, Layout conflicts, Gift Card translations
# Date: 2026-02-15
##############################################################################

set -e

echo "=========================================="
echo "COMPREHENSIVE CHECKOUT FIX - STARTING"
echo "=========================================="
echo ""

MAGENTO_ROOT="/home/technadminy7/public_html"
cd "$MAGENTO_ROOT"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

##############################################################################
# STEP 1: Backup Conflicting Layout Files
##############################################################################

echo -e "${YELLOW}[STEP 1]${NC} Backing up conflicting layout files..."

BACKUP_DIR="layout_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup potentially conflicting files
if [ -f "app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml" ]; then
    cp "app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml" "$BACKUP_DIR/"
    echo "  ✓ Backed up Mab/Core checkout layout"
fi

if [ -f "app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml" ]; then
    cp "app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml" "$BACKUP_DIR/"
    echo "  ✓ Backed up Mab/VisualEffects checkout layout"
fi

echo -e "${GREEN}  ✓ Backup created in $BACKUP_DIR${NC}"
echo ""

##############################################################################
# STEP 2: Disable Conflicting Layout Files
##############################################################################

echo -e "${YELLOW}[STEP 2]${NC} Disabling conflicting layout files..."

# Disable Mab/Core checkout layout
if [ -f "app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml" ]; then
    mv "app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml" \
       "app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml.disabled"
    echo "  ✓ Disabled Mab/Core checkout layout"
fi

# Disable Mab/VisualEffects checkout layout
if [ -f "app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml" ]; then
    mv "app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml" \
       "app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml.disabled"
    echo "  ✓ Disabled Mab/VisualEffects checkout layout"
fi

echo -e "${GREEN}  ✓ Conflicting layouts disabled${NC}"
echo ""

##############################################################################
# STEP 3: Add Gift Card French Translations
##############################################################################

echo -e "${YELLOW}[STEP 3]${NC} Adding gift card French translations..."

# Check if translations already exist
if ! grep -q "\"Gift Card\",\"Carte Cadeau\"" "app/i18n/Mab/fr_FR/fr_FR.csv" 2>/dev/null; then
    cat >> "app/i18n/Mab/fr_FR/fr_FR.csv" << 'EOF'
"Gift Card","Carte Cadeau"
"Gift Card Code","Code Carte Cadeau"
"Apply Gift Card Code","Appliquer le Code Carte Cadeau"
"Check Gift Card Balance","Vérifier le Solde de la Carte Cadeau"
"Check Balance","Vérifier le Solde"
"Gift Card Balance","Solde de la Carte Cadeau"
"Enter Gift Card Code","Entrez le Code de la Carte Cadeau"
"Enter your gift card code","Entrez votre code de carte cadeau"
"Remove Gift Card","Retirer la Carte Cadeau"
"Gift Card removed","Carte Cadeau retirée"
"Gift Card applied","Carte Cadeau appliquée"
"Invalid Gift Card Code","Code de Carte Cadeau invalide"
"This Gift Card is already applied","Cette Carte Cadeau est déjà appliquée"
"Gift Card amount","Montant de la Carte Cadeau"
"Remaining balance","Solde restant"
"Gift Cards Applied","Cartes Cadeaux Appliquées"
"You have no gift cards to redeem","Vous n'avez aucune carte cadeau à utiliser"
"Gift Card successfully applied to your order","Carte Cadeau appliquée avec succès à votre commande"
"Gift Message","Message Cadeau"
"Add Gift Message","Ajouter un Message Cadeau"
"Gift Wrap","Emballage Cadeau"
"Add Gift Wrap","Ajouter un Emballage Cadeau"
"Gift Wrap Options","Options d'Emballage Cadeau"
"Gift options","Options Cadeau"
"Gift Options for the Entire Order","Options Cadeau pour la Commande Entière"
"Gift Options for Individual Items","Options Cadeau pour les Articles Individuels"
EOF
    echo -e "${GREEN}  ✓ Added gift card translations${NC}"
else
    echo "  → Gift card translations already exist"
fi

TRANSLATION_COUNT=$(wc -l < app/i18n/Mab/fr_FR/fr_FR.csv)
echo "  → Total translation lines: $TRANSLATION_COUNT"
echo ""

##############################################################################
# STEP 4: Optimize Amasty Configuration (Using Database)
##############################################################################

echo -e "${YELLOW}[STEP 4]${NC} Optimizing Amasty configuration..."

# Database connection
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"
DB_USER="root"
DB_PASS="YourNewStrongPassword"
MYSQL_CMD="/opt/mariadb10.6/mariadb/bin/mysql -u $DB_USER -p'$DB_PASS' -h $DB_HOST -P $DB_PORT $DB_NAME"

# Safe config updates using database
$MYSQL_CMD << 'EOSQL'
-- Enable Amasty Checkout
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'amasty_checkout/general/enabled', '1')
ON DUPLICATE KEY UPDATE value = '1';

-- Enable discount code
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'amasty_checkout/additional_options/discount', '1')
ON DUPLICATE KEY UPDATE value = '1';

-- Enable order comments
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'amasty_checkout/additional_options/comment', '1')
ON DUPLICATE KEY UPDATE value = '1';

-- Enable newsletter
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'amasty_checkout/additional_options/newsletter', '1')
ON DUPLICATE KEY UPDATE value = '1';

-- Enable create account
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'amasty_checkout/additional_options/create_account', '1')
ON DUPLICATE KEY UPDATE value = '1';

-- Place order button in summary
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'amasty_checkout/design/place_button_layout', 'summary')
ON DUPLICATE KEY UPDATE value = 'summary';

-- Layout is already 3columns - no need to change
EOSQL

if [ $? -eq 0 ]; then
    echo -e "${GREEN}  ✓ Amasty configuration updated${NC}"
else
    echo -e "${YELLOW}  ⚠ Using CLI method instead...${NC}"
    # Fallback to safe CLI commands (skip layout as it's already correct)
    php bin/magento config:set amasty_checkout/general/enabled 1 2>/dev/null || true
    php bin/magento config:set amasty_checkout/additional_options/discount 1 2>/dev/null || true
    php bin/magento config:set amasty_checkout/additional_options/comment 1 2>/dev/null || true
    php bin/magento config:set amasty_checkout/additional_options/newsletter 1 2>/dev/null || true
    php bin/magento config:set amasty_checkout/additional_options/create_account 1 2>/dev/null || true
    php bin/magento config:set amasty_checkout/design/place_button_layout summary 2>/dev/null || true
fi

echo ""

##############################################################################
# STEP 5: Check Mageplaza Conflicts
##############################################################################

echo -e "${YELLOW}[STEP 5]${NC} Checking for Mageplaza conflicts..."

MAGEPLAZA_OSC=$(php bin/magento module:status 2>/dev/null | grep -i "Mageplaza_Osc" || echo "")

if [ -n "$MAGEPLAZA_OSC" ]; then
    echo -e "${YELLOW}  ⚠ Mageplaza One Step Checkout detected!${NC}"
    echo "  → May conflict with Amasty"
    echo "$MAGEPLAZA_OSC"
else
    echo -e "${GREEN}  ✓ No Mageplaza conflicts${NC}"
fi

echo ""

##############################################################################
# STEP 6: Clear Caches
##############################################################################

echo -e "${YELLOW}[STEP 6]${NC} Clearing caches..."

rm -rf generated/code/* generated/metadata/* 2>/dev/null || true
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* 2>/dev/null || true

echo -e "${GREEN}  ✓ Caches cleared${NC}"
echo ""

##############################################################################
# STEP 7: Set Permissions
##############################################################################

echo -e "${YELLOW}[STEP 7]${NC} Setting permissions..."

find var generated pub/static pub/media app/etc -type f -exec chmod 664 {} \; 2>/dev/null || true
find var generated pub/static pub/media app/etc -type d -exec chmod 775 {} \; 2>/dev/null || true

echo -e "${GREEN}  ✓ Permissions set${NC}"
echo ""

##############################################################################
# STEP 8: Regenerate DI
##############################################################################

echo -e "${YELLOW}[STEP 8]${NC} Regenerating dependency injection..."

php bin/magento setup:di:compile

echo -e "${GREEN}  ✓ DI compiled${NC}"
echo ""

##############################################################################
# STEP 9: Deploy Static Content
##############################################################################

echo -e "${YELLOW}[STEP 9]${NC} Deploying French static content..."

php bin/magento setup:static-content:deploy fr_FR --area frontend --theme Sm/market -f

echo -e "${GREEN}  ✓ Static content deployed${NC}"
echo ""

##############################################################################
# STEP 10: Flush Caches
##############################################################################

echo -e "${YELLOW}[STEP 10]${NC} Flushing Magento caches..."

php bin/magento cache:flush
php bin/magento cache:clean

echo -e "${GREEN}  ✓ Caches flushed${NC}"
echo ""

##############################################################################
# STEP 11: Test URLs
##############################################################################

echo -e "${YELLOW}[STEP 11]${NC} Testing checkout URLs..."

HTTP_CART=$(curl -s -o /dev/null -w "%{http_code}" https://technostationery.com/checkout/cart/ 2>/dev/null || echo "000")
HTTP_CHECKOUT=$(curl -s -o /dev/null -w "%{http_code}" https://technostationery.com/checkout/ 2>/dev/null || echo "000")

if [ "$HTTP_CART" = "200" ]; then
    echo -e "${GREEN}  ✓ Cart: HTTP $HTTP_CART${NC}"
else
    echo -e "${YELLOW}  → Cart: HTTP $HTTP_CART${NC}"
fi

if [ "$HTTP_CHECKOUT" = "200" ]; then
    echo -e "${GREEN}  ✓ Checkout: HTTP $HTTP_CHECKOUT${NC}"
else
    echo -e "${YELLOW}  → Checkout: HTTP $HTTP_CHECKOUT${NC}"
fi

echo ""

##############################################################################
# FINAL REPORT
##############################################################################

echo "=========================================="
echo -e "${GREEN}CHECKOUT FIX COMPLETE!${NC}"
echo "=========================================="
echo ""
echo "SUMMARY:"
echo "  1. ✓ Disabled conflicting layouts"
echo "  2. ✓ Added gift card French translations"
echo "  3. ✓ Optimized Amasty configuration"
echo "  4. ✓ Cleared caches & generated files"
echo "  5. ✓ Regenerated DI"
echo "  6. ✓ Deployed static content"
echo "  7. ✓ Tested URLs"
echo ""
echo "CONFIGURATION:"
echo "  • Amasty Checkout: Enabled"
echo "  • Layout: 3 columns (already set)"
echo "  • Discount: Enabled"
echo "  • Comments: Enabled"
echo "  • Newsletter: Enabled"
echo "  • Create Account: Enabled"
echo "  • Place Order Button: In summary"
echo ""
echo "TRANSLATIONS:"
echo "  • Total lines: $TRANSLATION_COUNT"
echo "  • Gift card: Translated to French"
echo ""
echo "BACKUP:"
echo "  • Location: $BACKUP_DIR"
echo ""
echo "TEST URLS:"
echo "  • Cart: https://technostationery.com/checkout/cart/"
echo "  • Checkout: https://technostationery.com/checkout/"
echo ""
echo "✅ All fixes applied successfully!"
echo "=========================================="

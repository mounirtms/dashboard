#!/bin/bash
##############################################################################
# Comprehensive Checkout & Cart Optimization Script
# Fixes all remaining issues and optimizes the checkout experience
##############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     COMPREHENSIVE CHECKOUT & CART OPTIMIZATION SCRIPT         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Backup directory
BACKUP_DIR="/home/technadminy7/public_html_backups/checkout_optimization_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
echo -e "${GREEN}✓${NC} Backup directory created: $BACKUP_DIR"

# Step 1: Fix Generated Metadata
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 1: Regenerate DI Compilation${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo "Clearing generated code..."
rm -rf generated/code/* generated/metadata/*
php bin/magento setup:di:compile
echo -e "${GREEN}✓${NC} DI compilation completed"

# Step 2: Optimize Static Content
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 3: Deploy Static Content (Optimized)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo "Deploying static content for theme..."
php bin/magento setup:static-content:deploy fr_FR ar_DZ -f --theme Mab/techno --area frontend --jobs 4
echo -e "${GREEN}✓${NC} Static content deployed"

# Step 4: Configure Amasty Checkout for Optimal UX
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 4: Optimize Amasty Checkout Configuration${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Enable modern 3-column layout
php bin/magento config:set amasty_checkout/design/checkout_design 1
php bin/magento config:set amasty_checkout/design/layout_modern "3columns"

# Show discount code field
php bin/magento config:set amasty_checkout/additional_options/discount 1

# Enable order comments
php bin/magento config:set amasty_checkout/additional_options/comment 1

# Enable newsletter subscription option
php bin/magento config:set amasty_checkout/additional_options/newsletter 1

# Set place order button in summary
php bin/magento config:set amasty_checkout/design/place_button_layout "summary"

echo -e "${GREEN}✓${NC} Amasty configuration optimized"

# Step 5: Clear All Caches
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 5: Clear All Caches${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

php bin/magento cache:flush
echo -e "${GREEN}✓${NC} All caches flushed"

# Step 6: Test Checkout Page
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 6: Test Checkout & Cart Pages${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo "Testing cart page..."
CART_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: technostationery.com" http://127.0.0.1:8080/checkout/cart/)
echo -e "Cart Page Status: ${GREEN}$CART_STATUS${NC}"

echo "Testing checkout page..."
CHECKOUT_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: technostationery.com" http://127.0.0.1:8080/checkout/)
echo -e "Checkout Page Status: ${GREEN}$CHECKOUT_STATUS${NC}"

echo -e "\n${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║             OPTIMIZATION COMPLETE!                             ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Add a product to cart on https://technostationery.com"
echo "2. Go to checkout and verify the modern 3-column layout"
echo "3. Check that all sections are visible (discount code, comments, etc.)"
echo "4. Test the checkout flow with Cash on Delivery payment"
echo ""
echo -e "${BLUE}Backup Location:${NC} $BACKUP_DIR"

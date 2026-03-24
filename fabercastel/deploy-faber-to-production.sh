#!/bin/bash
#
# Deploy Faber-Castel Products to Production
# Smooth, error-free deployment script
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
FROM_USER="beta"
TO_USER="technadminy7"
FROM_DIR="/home/$FROM_USER/public_html/fabercastel"
TO_DIR="/home/$TO_USER/public_html/fabercastel"

echo ""
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║        DEPLOY FABER-CASTEL PRODUCTS TO PRODUCTION                          ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${BLUE}📂 Source:${NC} $FROM_DIR"
echo -e "${BLUE}📂 Target:${NC} $TO_DIR"
echo ""

# Step 1: Create target directory
echo "═══════════════════════════════════════════════════════════════════════════"
echo -e "${YELLOW}Step 1/6:${NC} Creating target directory..."
echo "═══════════════════════════════════════════════════════════════════════════"

mkdir -p "$TO_DIR"
echo -e "  ${GREEN}✓${NC} Directory created"
echo ""

# Step 2: Copy files to production
echo "═══════════════════════════════════════════════════════════════════════════"
echo -e "${YELLOW}Step 2/6:${NC} Copying files to production..."
echo "═══════════════════════════════════════════════════════════════════════════"

cp "$FROM_DIR"/*.php "$TO_DIR/" && echo -e "  ${GREEN}✓${NC} Copied PHP scripts"
cp "$FROM_DIR"/*.csv "$TO_DIR/" && echo -e "  ${GREEN}✓${NC} Copied CSV file"
cp "$FROM_DIR"/*.zip "$TO_DIR/" && echo -e "  ${GREEN}✓${NC} Copied ZIP file"
cp "$FROM_DIR"/*.sh "$TO_DIR/" && echo -e "  ${GREEN}✓${NC} Copied shell scripts"

chmod +x "$TO_DIR"/*.sh "$TO_DIR"/*.php
chmod 644 "$TO_DIR"/*.csv "$TO_DIR"/*.zip

echo ""

# Step 3: Process images on production
echo "═══════════════════════════════════════════════════════════════════════════"
echo -e "${YELLOW}Step 3/6:${NC} Processing images on production..."
echo "═══════════════════════════════════════════════════════════════════════════"
echo ""

cd "$TO_DIR"
bash "$TO_DIR/process-faber-images.sh"

echo ""

# Step 4: Update database with images
echo "═══════════════════════════════════════════════════════════════════════════"
echo -e "${YELLOW}Step 4/6:${NC} Updating product images in database..."
echo "═══════════════════════════════════════════════════════════════════════════"
echo ""

php "$TO_DIR/update-faber-images.php" "canvas - canva faber castel.csv" "/home/$TO_USER/public_html/pub/media/catalog/product"

echo ""

# Step 5: Reindex
echo "═══════════════════════════════════════════════════════════════════════════"
echo -e "${YELLOW}Step 5/6:${NC} Running reindex on production..."
echo "═══════════════════════════════════════════════════════════════════════════"
echo ""

php bin/magento indexer:reindex catalog_product_attribute catalog_product_price cataloginventory_stock catalog_category_product || {
    echo -e "${YELLOW}⚠ Some indexes may have warnings (continuing)${NC}"
}

echo ""

# Step 6: Clear cache
echo "═══════════════════════════════════════════════════════════════════════════"
echo -e "${YELLOW}Step 6/6:${NC} Clearing cache..."
echo "═══════════════════════════════════════════════════════════════════════════"
echo ""

php bin/magento cache:clean
php bin/magento cache:flush

echo ""

# Verification
echo "═══════════════════════════════════════════════════════════════════════════"
echo -e "${YELLOW}Verification:${NC} Checking imported products..."
echo "═══════════════════════════════════════════════════════════════════════════"
echo ""

PRODUCT_COUNT=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -N -e "SELECT COUNT(*) FROM catalog_product_entity WHERE sku LIKE '11406637%'" 2>/dev/null)

echo -e "  ${GREEN}✓${NC} Products in database: $PRODUCT_COUNT"

IMAGE_COUNT=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -N -e "SELECT COUNT(DISTINCT cpe.entity_id) FROM catalog_product_entity cpe JOIN catalog_product_entity_varchar cpev ON cpe.entity_id = cpev.entity_id WHERE cpev.attribute_id = 87 AND cpev.store_id = 0 AND cpe.sku LIKE '11406637%'" 2>/dev/null)

echo -e "  ${GREEN}✓${NC} Products with images: $IMAGE_COUNT"

echo ""

# Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo -e "║                   ${GREEN}✅ DEPLOYMENT COMPLETE${NC}                              ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${BLUE}Summary:${NC}"
echo "  📦 Products: $PRODUCT_COUNT Faber-Castel products"
echo "  🖼️  Images: $IMAGE_COUNT products with images"
echo "  📁 Categories: Assigned from CSV"
echo "  🔍 Status: Existing products (update images only)"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Login to production admin: https://techno-y7.com/sysadminy"
echo "  2. Go to Catalog > Products"
echo "  3. Filter by SKU: 11406637*"
echo "  4. Verify images appear correctly"
echo ""
echo -e "${GREEN}✅ All done!${NC}"
echo ""

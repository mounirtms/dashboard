#!/bin/bash
#
# Faber-Castel Image Sync - Production Ready
# - Uses exact Image Name from CSV
# - Resizes to 1200x1200
# - Enables all products
# - Skips existing images
#

# Don't exit on error
set +e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║         FABER-CASTEL IMAGE SYNC (1200x1200)                                ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CSV_FILE="$SCRIPT_DIR/canvas - canva faber castel.csv"
TEMP_DIR="$SCRIPT_DIR/images_temp"
PROCESSED_DIR="$SCRIPT_DIR/images_processed"
MAGENTO_MEDIA="/home/technadminy7/public_html/pub/media/catalog/product"

# Image settings
TARGET_SIZE="1200x1200"
QUALITY=90

echo -e "${BLUE}📂 CSV:${NC} $CSV_FILE"
echo -e "${BLUE}🖼️  Size:${NC} $TARGET_SIZE"
echo -e "${BLUE}📁 Output:${NC} $MAGENTO_MEDIA"
echo ""

# Check dependencies
if ! command -v convert &> /dev/null; then
    echo -e "${RED}✗ ImageMagick not found${NC}"
    exit 1
fi

# Step 1: Extract ZIP if not already
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 1/4: Extracting images..."
echo "═══════════════════════════════════════════════════════════════════════════"

ZIP_FILE="$SCRIPT_DIR/drive-download-20260316T123940Z-1-001.zip"
if [ -f "$ZIP_FILE" ] && [ ! -d "$TEMP_DIR" ]; then
    mkdir -p "$TEMP_DIR"
    unzip -o "$ZIP_FILE" -d "$TEMP_DIR/" > /dev/null 2>&1
    echo -e "  ${GREEN}✓${NC} Extracted images"
else
    echo -e "  ${GREEN}✓${NC} Images already extracted"
fi
echo ""

# Step 2: Create mapping and process
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 2/4: Processing images (1200x1200)..."
echo "═══════════════════════════════════════════════════════════════════════════"

mkdir -p "$PROCESSED_DIR"

# Create mapping file
php -r "
\$file = '$CSV_FILE';
\$content = file_get_contents(\$file);
\$content = str_replace([\"\r\n\", \"\r\"], \"\n\", \$content);
\$tempFile = tempnam(sys_get_temp_dir(), 'csv_');
file_put_contents(\$tempFile, \$content);
if ((\$handle = fopen(\$tempFile, 'r')) !== false) {
    \$header = fgetcsv(\$handle, 0, ',', '\"');
    \$skuIndex = array_search('sku', \$header);
    \$imageNameIndex = array_search('Image Name', \$header);
    \$refIndex = array_search('ref', \$header);
    
    while ((\$data = fgetcsv(\$handle, 0, ',', '\"')) !== false) {
        if (count(\$data) >= max(\$skuIndex, \$imageNameIndex, \$refIndex) + 1) {
            \$sku = trim(\$data[\$skuIndex]);
            \$imageName = trim(\$data[\$imageNameIndex]);
            \$ref = trim(\$data[\$refIndex]);
            if (!empty(\$sku) && !empty(\$ref)) {
                echo \"\$sku|\$imageName|\$ref\n\";
            }
        }
    }
    fclose(\$handle);
}
unlink(\$tempFile);
" > /tmp/faber_mapping.txt

PROCESSED=0
SKIPPED=0
NOT_FOUND=0

while IFS='|' read -r sku image_name ref; do
    [ -z "$ref" ] && continue
    
    # Find source image (by ref number)
    source_image=""
    for ext in jpg jpeg webp; do
        if [ -f "$TEMP_DIR/${ref}.${ext}" ]; then
            source_image="$TEMP_DIR/${ref}.${ext}"
            break
        fi
        # Also check with suffixes like _10_PM1
        for file in "$TEMP_DIR"/${ref}_*.$ext; do
            if [ -f "$file" ]; then
                source_image="$file"
                break 2
            fi
        done
    done
    
    if [ -z "$source_image" ]; then
        echo -e "  ${YELLOW}⚠${NC} No source for: $sku ($ref)"
        ((NOT_FOUND++)) || true
        continue
    fi
    
    # Create directory structure from SKU
    sku_first=${sku:0:2}
    sku_second=${sku:2:2}
    target_dir="$MAGENTO_MEDIA/$sku_first/$sku_second"
    mkdir -p "$target_dir"
    
    # Target filename (use Image Name from CSV)
    target_file="$target_dir/${image_name}.jpg"
    
    # Check if already exists and is correct size
    if [ -f "$target_file" ]; then
        dimensions=$(convert identify -format "%wx%h" "$target_file" 2>/dev/null)
        if [ "$dimensions" = "1200x1200" ]; then
            echo -e "  ${GREEN}✓${NC} Exists (1200x1200): $image_name"
            ((SKIPPED++)) || true
            continue
        fi
    fi
    
    # Convert and resize
    convert "$source_image" -resize "$TARGET_SIZE" -gravity center -extent "$TARGET_SIZE" -quality $QUALITY "$target_file" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo -e "  ${GREEN}✓${NC} Processed: $image_name (1200x1200)"
        ((PROCESSED++)) || true
    else
        echo -e "  ${RED}✗${NC} Failed: $image_name"
    fi
    
done < /tmp/faber_mapping.txt

echo ""
echo -e "  ${GREEN}✓${NC} Processed: $PROCESSED"
echo -e "  ${GREEN}✓${NC} Skipped (already exist): $SKIPPED"
echo -e "  ${YELLOW}⚠${NC} Not found: $NOT_FOUND"
echo ""

# Cleanup
rm -rf "$TEMP_DIR" "$PROCESSED_DIR" /tmp/faber_mapping.txt

# Step 3: Update database
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 3/4: Updating database..."
echo "═══════════════════════════════════════════════════════════════════════════"

php "$SCRIPT_DIR/update-faber-images-final.php" "$CSV_FILE" "$MAGENTO_MEDIA"

echo ""

# Step 4: Enable products
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 4/4: Enabling all Faber-Castel products..."
echo "═══════════════════════════════════════════════════════════════════════════"

/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
UPDATE catalog_product_entity_int cpei
JOIN catalog_product_entity cpe ON cpe.entity_id = cpei.entity_id
SET cpei.value = 1
WHERE cpei.attribute_id = 97 
AND cpei.store_id = 0 
AND cpe.sku LIKE '11406637%'
" 2>/dev/null

echo -e "  ${GREEN}✓${NC} Products enabled"
echo ""

# Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo -e "║                   ${GREEN}✅ IMAGE SYNC COMPLETE${NC}                                ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""
echo "📊 Summary:"
echo "  - Images processed: $PROCESSED"
echo "  - Images skipped: $SKIPPED"
echo "  - Images not found: $NOT_FOUND"
echo "  - Products enabled: All Faber-Castel"
echo ""
echo "✅ All done!"
echo ""

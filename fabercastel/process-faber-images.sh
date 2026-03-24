#!/bin/bash
#
# Faber-Castel Image Processing Script
# - Extracts images from ZIP
# - Converts WebP to JPG
# - Resizes to standard size (800x800)
# - Renames based on SKU/Ref
# - Moves to Magento media directory
#

# Don't exit on error - we handle errors manually
set +e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║         FABER-CASTEL IMAGE PROCESSING                                      ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CSV_FILE="$SCRIPT_DIR/canvas - canva faber castel.csv"
ZIP_FILE="$SCRIPT_DIR/drive-download-20260316T123940Z-1-001.zip"
TEMP_DIR="$SCRIPT_DIR/images_temp"
PROCESSED_DIR="$SCRIPT_DIR/images_processed"
MAGENTO_MEDIA="/home/beta/public_html/pub/media/catalog/product"

# Image settings
MAX_WIDTH=800
MAX_HEIGHT=800
QUALITY=85

echo -e "${BLUE}📂 Source CSV:${NC} $CSV_FILE"
echo -e "${BLUE}📦 ZIP File:${NC} $ZIP_FILE"
echo -e "${BLUE}🖼️  Output:${NC} $MAGENTO_MEDIA"
echo ""

# Check dependencies
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Checking dependencies..."
echo "═══════════════════════════════════════════════════════════════════════════"

if ! command -v convert &> /dev/null; then
    echo -e "${RED}✗ ImageMagick 'convert' not found. Installing...${NC}"
    # Try to install or use alternative
    if command -v magick &> /dev/null; then
        CONVERT_CMD="magick"
        echo -e "${GREEN}✓ Using 'magick' command${NC}"
    else
        echo -e "${RED}✗ ImageMagick not available. Please install: apt-get install imagemagick${NC}"
        exit 1
    fi
else
    CONVERT_CMD="convert"
    echo -e "${GREEN}✓ ImageMagick convert found${NC}"
fi

if ! command -v unzip &> /dev/null; then
    echo -e "${RED}✗ unzip not found${NC}"
    exit 1
else
    echo -e "${GREEN}✓ unzip found${NC}"
fi

echo ""

# Step 1: Extract ZIP
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 1/5: Extracting images from ZIP..."
echo "═══════════════════════════════════════════════════════════════════════════"

mkdir -p "$TEMP_DIR"
unzip -o "$ZIP_FILE" -d "$TEMP_DIR/" > /dev/null 2>&1

IMAGE_COUNT=$(ls "$TEMP_DIR"/*.jpg "$TEMP_DIR"/*.webp 2>/dev/null | wc -l)
echo -e "  ${GREEN}✓${NC} Extracted $IMAGE_COUNT images"
echo ""

# Step 2: Create mapping from CSV
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 2/5: Creating SKU to Image mapping..."
echo "═══════════════════════════════════════════════════════════════════════════"

declare -A SKU_TO_REF
declare -A REF_TO_IMAGE

# Parse CSV to get SKU -> Ref mapping
php -r "
\$file = '$CSV_FILE';
\$content = file_get_contents(\$file);
\$content = str_replace([\"\r\n\", \"\r\"], \"\n\", \$content);
\$tempFile = tempnam(sys_get_temp_dir(), 'csv_');
file_put_contents(\$tempFile, \$content);
if ((\$handle = fopen(\$tempFile, 'r')) !== false) {
    \$header = fgetcsv(\$handle, 0, ',', '\"');
    \$skuIndex = array_search('sku', \$header);
    \$refIndex = array_search('ref', \$header);
    \$imageNameIndex = array_search('Image Name', \$header);
    
    \$mappings = [];
    while ((\$data = fgetcsv(\$handle, 0, ',', '\"')) !== false) {
        if (count(\$data) >= max(\$skuIndex, \$refIndex, \$imageNameIndex) + 1) {
            \$sku = trim(\$data[\$skuIndex]);
            \$ref = trim(\$data[\$refIndex]);
            \$imageName = trim(\$data[\$imageNameIndex]);
            if (!empty(\$sku) && !empty(\$ref)) {
                echo \"\$sku|\$ref|\$imageName\n\";
            }
        }
    }
    fclose(\$handle);
}
unlink(\$tempFile);
" > /tmp/faber_mapping.txt

MAPPING_COUNT=$(wc -l < /tmp/faber_mapping.txt)
echo -e "  ${GREEN}✓${NC} Found $MAPPING_COUNT product mappings"
echo ""

# Step 3: Process images
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 3/5: Processing images (convert WebP, resize)..."
echo "═══════════════════════════════════════════════════════════════════════════"

mkdir -p "$PROCESSED_DIR"

PROCESSED=0
CONVERTED=0
RESIZED=0
ERRORS=0

for image in "$TEMP_DIR"/*; do
    [ -f "$image" ] || continue
    
    filename=$(basename "$image")
    extension="${filename##*.}"
    basename_no_ext="${filename%.*}"
    
    # Extract base ref (remove suffixes like _10_PM1, _0_PM99)
    base_ref=$(echo "$basename_no_ext" | sed 's/_.*//')
    
    # Output filename
    output_name="${base_ref}.jpg"
    output_path="$PROCESSED_DIR/$output_name"
    
    # Convert WebP to JPG or copy JPG
    if [ "$extension" = "webp" ]; then
        temp_file="$PROCESSED_DIR/temp_${basename_no_ext}.jpg"
        if $CONVERT_CMD "$image" -quality 100 "$temp_file" 2>&1; then
            mv "$temp_file" "$output_path"
            ((CONVERTED++))
        else
            echo -e "  ${YELLOW}⚠${NC} Failed to convert: $filename"
            rm -f "$temp_file"
            ((ERRORS++))
            continue
        fi
    else
        cp "$image" "$output_path"
    fi
    
    # Resize if needed
    dimensions=$($CONVERT_CMD identify -format "%wx%h" "$output_path" 2>/dev/null)
    width=$(echo "$dimensions" | cut -d'x' -f1)
    height=$(echo "$dimensions" | cut -d'x' -f2)
    
    if [ "$width" -gt "$MAX_WIDTH" ] || [ "$height" -gt "$MAX_HEIGHT" ]; then
        $CONVERT_CMD "$output_path" -resize "${MAX_WIDTH}x${MAX_HEIGHT}>" -quality $QUALITY "$output_path.tmp" 2>/dev/null && {
            mv "$output_path.tmp" "$output_path"
            ((RESIZED++))
        }
    fi
    
    ((PROCESSED++))
    echo -e "  ${GREEN}✓${NC} Processed: $filename → $output_name (${dimensions})"
done

echo ""
echo -e "  ${GREEN}✓${NC} Processed: $PROCESSED images"
echo -e "  ${GREEN}✓${NC} Converted from WebP: $CONVERTED"
echo -e "  ${GREEN}✓${NC} Resized: $RESIZED"
if [ $ERRORS -gt 0 ]; then
    echo -e "  ${RED}✗${NC} Errors: $ERRORS"
fi
echo ""

# Step 4: Copy to Magento media directory
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 4/5: Copying images to Magento media directory..."
echo "═══════════════════════════════════════════════════════════════════════════"

COPIED=0

while IFS='|' read -r sku ref image_name; do
    [ -z "$ref" ] && continue
    
    # Find matching image
    image_file="$PROCESSED_DIR/${ref}.jpg"
    
    if [ -f "$image_file" ]; then
        # Create directory structure (first 2 chars / next 2 chars)
        sku_first=${sku:0:2}
        sku_second=${sku:2:2}
        target_dir="$MAGENTO_MEDIA/$sku_first/$sku_second"
        mkdir -p "$target_dir"
        
        # Copy image
        cp "$image_file" "$target_dir/${ref}.jpg"
        ((COPIED++))
        echo -e "  ${GREEN}✓${NC} $sku → $sku_first/$sku_second/${ref}.jpg"
    else
        echo -e "  ${YELLOW}⚠${NC} No image for SKU: $sku (Ref: $ref)"
    fi
done < /tmp/faber_mapping.txt

echo ""
echo -e "  ${GREEN}✓${NC} Copied $COPIED images to Magento media"
echo ""

# Step 5: Update database
echo "═══════════════════════════════════════════════════════════════════════════"
echo "Step 5/5: Updating product images in database..."
echo "═══════════════════════════════════════════════════════════════════════════"

php "$SCRIPT_DIR/update-faber-images.php" "$CSV_FILE" "$MAGENTO_MEDIA"

echo ""

# Cleanup
rm -rf "$TEMP_DIR" "$PROCESSED_DIR" /tmp/faber_mapping.txt

# Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo -e "║                   ${GREEN}✅ IMAGE PROCESSING COMPLETE${NC}                          ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""
echo "📊 Summary:"
echo "  - Images extracted: $IMAGE_COUNT"
echo "  - Images processed: $PROCESSED"
echo "  - WebP converted: $CONVERTED"
echo "  - Images resized: $RESIZED"
echo "  - Images copied: $COPIED"
echo ""
echo "📁 Images location: $MAGENTO_MEDIA"
echo ""

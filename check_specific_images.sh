#!/bin/bash
echo "=== CHECKING SPECIFIC PRODUCT IMAGES ==="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Define images to check
declare -a images=(
    "/m/i/mini-pinces-en-bois-48x7mm-paquet-de-24-pieces-couleurs-naturel-techno-ref-5007-0.jpg"
    "/a/c/acrylic-studio-tube-de-100ml-bleu-phtalocyanine-pebeo-ref-831017.jpg"
)

declare -a entity_ids=(495 606)
declare -a skus=("1140618142" "107688301")

echo "Product ID | SKU | Image Path | Status | Size"
echo "-----------|-----|------------|--------|------"

for i in "${!images[@]}"; do
    img="${images[$i]}"
    entity_id="${entity_ids[$i]}"
    sku="${skus[$i]}"
    
    full_path="pub/media/catalog/product${img}"
    
    if [ -f "$full_path" ]; then
        size=$(du -h "$full_path" | cut -f1)
        echo "$entity_id | $sku | $img | ✓ EXISTS | $size"
    else
        echo "$entity_id | $sku | $img | ✗ MISSING | N/A"
    fi
done

echo ""
echo "=== CHECKING FOR SIMILAR/ALTERNATE IMAGES ==="
for i in "${!skus[@]}"; do
    sku="${skus[$i]}"
    echo ""
    echo "SKU: $sku (Entity ID: ${entity_ids[$i]})"
    find pub/media/catalog/product -type f -name "*${sku}*" 2>/dev/null | head -5
done

echo ""
echo "=== CACHE IMAGE CHECK ==="
for img in "${images[@]}"; do
    cache_pattern="pub/media/catalog/product/cache/**${img}"
    count=$(find pub/media/catalog/product/cache -type f -path "*${img}" 2>/dev/null | wc -l)
    echo "Cache images for ${img}: $count"
done


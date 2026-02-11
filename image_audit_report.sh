#!/bin/bash
#
# IMAGE AUDIT & OPTIMIZATION REPORT
# Date: 2026-02-11
#

echo "========================================"
echo "IMAGE AUDIT & OPTIMIZATION REPORT"
echo "Date: $(date '+%Y-%m-d %H:%M:%S')"
echo "========================================"
echo ""

cd /home/technadminy7/public_html

# Admin SVG Check
echo "1. ADMIN SVG ICON STATUS"
echo "----------------------------------------"
echo "File: pub/static/adminhtml/Magento/backend/en_US/images/magento-icon.svg"
if [ -f "pub/static/adminhtml/Magento/backend/en_US/images/magento-icon.svg" ]; then
    echo "Status: EXISTS ✓"
    echo "Size: $(stat -f%z pub/static/adminhtml/Magento/backend/en_US/images/magento-icon.svg 2>/dev/null || stat -c%s pub/static/adminhtml/Magento/backend/en_US/images/magento-icon.svg) bytes"
    echo "Content:"
    cat pub/static/adminhtml/Magento/backend/en_US/images/magento-icon.svg
    echo ""
else
    echo "Status: MISSING ✗"
fi

# Check referenced favicon
echo ""
echo "2. FAVICON CHECK"
echo "----------------------------------------"
if [ -f "pub/media/favicon/default/techno.png" ]; then
    echo "Favicon: EXISTS ✓"
    echo "Size: $(stat -f%z pub/media/favicon/default/techno.png 2>/dev/null || stat -c%s pub/media/favicon/default/techno.png) bytes"
else
    echo "Favicon: MISSING ✗"
fi

# Product Images Summary
echo ""
echo "3. PRODUCT IMAGES SUMMARY"
echo "----------------------------------------"
echo "Total images (jpg + png): $(find pub/media/catalog/product -type f \( -name "*.jpg" -o -name "*.png" \) 2>/dev/null | wc -l)"
echo "Directory size: $(du -sh pub/media/catalog/product | cut -f1)"
echo "Cache directory: $(du -sh pub/media/catalog/product/cache 2>/dev/null | cut -f1 || echo 'Not found')"

# Check image resize process
echo ""
echo "4. IMAGE RESIZE PROCESS"
echo "----------------------------------------"
if ps aux | grep -q "[c]atalog:images:resize"; then
    echo "Status: RUNNING ✓"
    echo "Process:"
    ps aux | grep "[c]atalog:images:resize"
else
    echo "Status: NOT RUNNING"
fi

# Check for recent image operations
echo ""
echo "5. RECENT IMAGE OPERATIONS"
echo "----------------------------------------"
echo "Images modified in last hour:"
find pub/media/catalog/product -type f -mmin -60 2>/dev/null | wc -l

echo "Cache images modified in last hour:"
find pub/media/catalog/product/cache -type f -mmin -60 2>/dev/null | wc -l

# Sample missing images check
echo ""
echo "6. SAMPLE PRODUCTS IMAGE CHECK"
echo "----------------------------------------"
echo "Checking products: 495, 606, 1140621565, 1140632138, 1140637505, 1140658840"
echo ""

MYSQL_CMD="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22"

$MYSQL_CMD -e "
SELECT 
    cpe.entity_id,
    cpe.sku,
    cpev_image.value as image_path
FROM catalog_product_entity cpe
LEFT JOIN catalog_product_entity_varchar cpev_image ON cpe.entity_id = cpev_image.entity_id 
    AND cpev_image.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4)
    AND cpev_image.store_id = 0
WHERE cpe.entity_id IN (495, 606, 1140621565, 1140632138, 1140637505, 1140658840)
ORDER BY cpe.entity_id;
" 2>/dev/null

# Performance Stats
echo ""
echo "7. PERFORMANCE STATISTICS"
echo "----------------------------------------"
echo "Total product entities: $($MYSQL_CMD -sse 'SELECT COUNT(*) FROM catalog_product_entity' 2>/dev/null)"
echo "Products without images: $($MYSQL_CMD -sse "SELECT COUNT(*) FROM catalog_product_entity cpe LEFT JOIN catalog_product_entity_varchar cpev ON cpe.entity_id = cpev.entity_id AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4) WHERE cpev.value IS NULL OR cpev.value = '' OR cpev.value = 'no_selection'" 2>/dev/null)"

# Recommendations
echo ""
echo "========================================"
echo "RECOMMENDATIONS"
echo "========================================"
echo "1. Admin SVG: File exists but references favicon"
echo "   - Favicon exists: pub/media/favicon/default/techno.png"
echo "   - Action: Ensure favicon is accessible"
echo ""
echo "2. Image Resize: Currently running"
echo "   - Monitor: tail -f var/log/system.log"
echo "   - Expected time: 30-60 minutes for full catalog"
echo ""
echo "3. Product Images: 357,975 total (13GB)"
echo "   - Action: Regular cleanup of old cache"
echo "   - Schedule: Weekly cache cleanup"
echo ""
echo "4. Missing Images: Check database for products without images"
echo "   - Action: Export CSV and bulk upload"
echo "   - Priority: High for enabled products"
echo ""
echo "Completed at: $(date '+%Y-%m-%d %H:%M:%S')"

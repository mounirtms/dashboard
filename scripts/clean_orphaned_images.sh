#!/bin/bash
# ============================================================================
# Clean orphaned image references in Magento database
# Removes database entries that point to non-existent physical files
# ============================================================================

MAGENTO_ROOT="/home/technadminy7/public_html"
LOG="/home/dashboard/public_html/logs/clean_orphaned_images_$(date +%Y%m%d_%H%M%S).log"

log() {
    echo "$1" | tee -a "$LOG"
}

log "================================================================"
log "CLEANING ORPHANED IMAGE REFERENCES"
log "================================================================"
log ""

cd "$MAGENTO_ROOT"

# Step 1: Run Magento's built-in media gallery sync
log "Step 1: Syncing media gallery with database..."
log "This will remove references to deleted files from catalog_product_entity_media_gallery"
php bin/magento catalog:media:sync 2>&1 | tee -a "$LOG"
log ""

# Step 2: Clean up orphaned varchar image attributes
log "Step 2: Cleaning orphaned varchar image attributes..."
php -r "
require 'app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$obj = \$bootstrap->getObjectManager();
\$state = \$obj->get('Magento\Framework\App\State');
\$state->setAreaCode('adminhtml');

\$resource = \$obj->get('Magento\Framework\App\ResourceConnection');
\$conn = \$resource->getConnection();

\$mediaPath = '/home/technadminy7/public_html/pub/media/catalog/product';

// Get attribute IDs for image, small_image, thumbnail
\$attrIds = \$conn->fetchAll('
    SELECT attribute_id, attribute_code FROM eav_attribute 
    WHERE attribute_code IN (\"image\", \"small_image\", \"thumbnail\") 
    AND entity_type_id = 4
');

\$cleaned = 0;

foreach (\$attrIds as \$attr) {
    \$attrId = \$attr['attribute_id'];
    \$code = \$attr['attribute_code'];
    
    // Get all values for this attribute
    \$values = \$conn->fetchAll('
        SELECT value_id, entity_id, value FROM catalog_product_entity_varchar 
        WHERE attribute_id = ?
        AND value IS NOT NULL AND value != \"\" AND value != \"no_selection\"
    ', [\$attrId]);
    
    foreach (\$values as \$row) {
        \$filePath = \$mediaPath . \$row['value'];
        if (!file_exists(\$filePath)) {
            // Remove orphaned reference
            \$conn->delete('catalog_product_entity_varchar', ['value_id = ?' => \$row['value_id']]);
            \$cleaned++;
            echo \"Cleaned orphaned $code for product \" . \$row['entity_id'] . \": \" . \$row['value'] . PHP_EOL;
        }
    }
}

echo \"Total orphaned varchar references cleaned: \$cleaned\" . PHP_EOL;
" 2>&1 | tee -a "$LOG"
log ""

# Step 3: Reindex catalog
log "Step 3: Reindexing catalog..."
php bin/magento indexer:reindex catalog_product_attribute 2>&1 | tee -a "$LOG"
log ""

# Step 4: Clear cache
log "Step 4: Clearing cache..."
php bin/magento cache:clean 2>&1 | tee -a "$LOG"
log ""

log "================================================================"
log "CLEANUP COMPLETE"
log "================================================================"
log "Log: $LOG"
log "Next: Run 'php bin/magento catalog:images:resize' to resize remaining images"

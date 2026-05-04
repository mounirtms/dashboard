#!/bin/bash
echo "=== MAGENTO PRODUCTION FIX ==="
echo "Date: $(date)"
echo ""

# Step 1: Reset stuck inventory indexer
echo "Step 1: Resetting stuck inventory indexer"
php bin/magento indexer:reset inventory
echo "✅ Inventory indexer reset"
echo ""

# Step 2: Reindex all
echo "Step 2: Reindexing all indexers"
php bin/magento indexer:reindex
echo "✅ Reindex complete"
echo ""

# Step 3: Check indexer status
echo "Step 3: Verifying indexer status"
php bin/magento indexer:status | grep -E "inventory|catalog"
echo ""

# Step 4: Flush all caches
echo "Step 4: Flushing caches"
php bin/magento cache:flush
echo "✅ Caches flushed"
echo ""

# Step 5: Check Varnish configuration
echo "Step 5: Checking Varnish configuration"
php bin/magento config:show system/full_page_cache/caching_application
echo ""

# Step 6: Check if Varnish is configured
echo "Step 6: Checking Varnish backend configuration"
php bin/magento config:show system/full_page_cache/varnish
echo ""

# Step 7: Clear Varnish cache (ban all)
echo "Step 7: Clearing Varnish cache"
if command -v varnishadm &> /dev/null; then
    varnishadm "ban req.url ~ /" 2>&1 || echo "Varnishadm requires permissions"
else
    echo "Varnishadm not available, using curl"
    curl -X PURGE http://localhost:80/ 2>&1 || echo "Curl purge not configured"
fi
echo ""

# Step 8: Test product count
echo "Step 8: Testing product visibility"
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SELECT 
    (SELECT COUNT(*) FROM catalog_product_entity) as total_products,
    (SELECT COUNT(*) FROM catalog_category_product WHERE category_id=3) as category_3_products,
    (SELECT COUNT(*) FROM catalogsearch_fulltext_scope1) as indexed_products;
" 2>&1 | grep -v "Warning"
echo ""

echo "=== Fix Complete ==="
echo "Test URL: https://technostationery.com/tous-les-produits.html"

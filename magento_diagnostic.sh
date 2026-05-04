#!/bin/bash
echo "=== MAGENTO PRODUCTION DIAGNOSTIC ==="
echo "Date: $(date)"
echo "Site: https://technostationery.com/tous-les-produits.html"
echo ""

# Step 1: Check indexer status
echo "Step 1: Checking Indexer Status"
php bin/magento indexer:status
echo ""

# Step 2: Check cache status
echo "Step 2: Checking Cache Status"
php bin/magento cache:status
echo ""

# Step 3: Check if products exist in database
echo "Step 3: Checking Products in Database"
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) as total_products FROM catalog_product_entity;" 2>&1 | grep -v "Warning"
echo ""

# Step 4: Check category products
echo "Step 4: Checking Products in 'Tous les produits' Category"
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SELECT c.entity_id, c.value as category_name, COUNT(cp.product_id) as product_count 
FROM catalog_category_entity_varchar c
LEFT JOIN catalog_category_product cp ON c.entity_id = cp.category_id
WHERE c.value LIKE '%Tous%les%produits%' OR c.value LIKE '%tous%les%produits%'
GROUP BY c.entity_id, c.value;
" 2>&1 | grep -v "Warning"
echo ""

# Step 5: Check Varnish configuration
echo "Step 5: Checking Varnish Status"
systemctl status varnish 2>&1 | head -10 || service varnish status 2>&1 | head -10
echo ""

# Step 6: Check Varnish backend health
echo "Step 6: Checking Varnish Backend"
varnishadm backend.list 2>&1 || echo "Varnishadm not available"
echo ""

# Step 7: Check store configuration
echo "Step 7: Checking Store Configuration"
php bin/magento store:list
echo ""

# Step 8: Recent error logs
echo "Step 8: Checking Recent Errors"
tail -50 var/log/system.log 2>/dev/null | grep -i "error\|exception" | tail -10 || echo "No system.log found"
echo ""

echo "=== Diagnostic Complete ==="

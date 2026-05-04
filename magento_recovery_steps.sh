#!/bin/bash
echo "=== MAGENTO RECOVERY STEPS ==="
echo "Date: $(date)"
echo ""

# Step 1: Check if reindex completed
echo "Step 1: Check indexer status"
php bin/magento indexer:status | grep -E "Ready|Processing|Working"
echo ""

# Step 2: Check for errors in logs
echo "Step 2: Recent errors in system.log"
tail -30 var/log/system.log 2>/dev/null | grep -i "error\|exception\|fatal" | tail -10
echo ""

# Step 3: Check exception.log
echo "Step 3: Recent exceptions"
tail -30 var/log/exception.log 2>/dev/null | tail -10
echo ""

# Step 4: Disable maintenance mode if enabled
echo "Step 4: Check maintenance mode"
if [ -f var/.maintenance.flag ]; then
    rm var/.maintenance.flag
    echo "✅ Maintenance mode disabled"
else
    echo "Maintenance mode not active"
fi
echo ""

# Step 5: Check Varnish cache
echo "Step 5: Clear Varnish cache"
curl -X PURGE https://technostationery.com/tous-les-produits.html 2>&1 | head -5
echo ""

# Step 6: Test database connection
echo "Step 6: Test database query"
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) as products FROM catalog_category_product WHERE category_id=3;" 2>&1 | grep -v "Warning"
echo ""

echo "=== Recovery Steps Complete ==="
echo ""
echo "MANUAL ACTIONS REQUIRED:"
echo "1. Wait for inventory indexer to complete (check: php bin/magento indexer:status)"
echo "2. If indexer stuck, kill and restart: php bin/magento indexer:reset inventory && php bin/magento indexer:reindex inventory"
echo "3. Check PHP error log: tail -f /var/log/ea-php83/error.log"
echo "4. Test page: curl https://technostationery.com/tous-les-produits.html"
echo "5. If still 500 error, check: var/log/system.log and var/log/exception.log"

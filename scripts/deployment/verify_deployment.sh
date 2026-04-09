#!/bin/bash
echo "=========================================="
echo "Magento Deployment Verification"
echo "=========================================="
echo ""

# Check mode
echo "1. Deployment Mode:"
php bin/magento deploy:mode:show
echo ""

# Check compilation
echo "2. Generated Code:"
if [ -f "generated/code/Magento/Framework/App/FrontController/Interceptor.php" ]; then
    echo "✅ DI Compilation: SUCCESS"
else
    echo "❌ DI Compilation: FAILED"
fi
echo ""

# Check static content
echo "3. Static Content:"
if [ -d "pub/static/adminhtml" ] && [ -d "pub/static/frontend" ]; then
    STATIC_SIZE=$(du -sh pub/static | cut -f1)
    echo "✅ Static Content Deployed: $STATIC_SIZE"
else
    echo "❌ Static Content: MISSING"
fi
echo ""

# Check permissions
echo "4. Permissions:"
ls -ld var/ generated/ pub/static/ | awk '{print $1, $3, $4, $9}'
echo ""

# Check indexes
echo "5. Indexer Status:"
php bin/magento indexer:status | grep -E "Ready|Processing|Reindex" | head -5
echo "..."
echo ""

# Check caches
echo "6. Cache Status:"
php bin/magento cache:status | grep -E "Enabled|Disabled" | head -5
echo "..."
echo ""

# Check modules
echo "7. Enabled Modules:"
ENABLED_COUNT=$(php bin/magento module:status | grep -A 200 "List of enabled modules" | grep -E "^[A-Z]" | wc -l)
echo "Total Enabled: $ENABLED_COUNT modules"
echo ""

# Check logs for recent errors
echo "8. Recent Errors (last 10):"
if [ -f "var/log/exception.log" ]; then
    tail -100 var/log/exception.log | grep -i "CRITICAL\|ERROR" | tail -10 | cut -c1-100
else
    echo "No exception log found"
fi
echo ""

# Database connection
echo "9. Database Connection:"
php bin/magento setup:db:status
echo ""

echo "=========================================="
echo "Verification Complete!"
echo "=========================================="

#!/bin/bash
#
# Amasty Modules Performance Optimization Script
# Comprehensive indexer management and optimization
#

echo "=== AMASTY PERFORMANCE OPTIMIZATION ==="
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Part 1: Amasty Indexer Status
echo "=== PART 1: AMASTY INDEXER STATUS ==="
echo ""

AMASTY_INDEXERS=$(php bin/magento indexer:status | grep -i amasty | wc -l)
echo "Total Amasty indexers: $AMASTY_INDEXERS"

echo ""
echo "Checking for issues..."
INVALID_COUNT=$(php bin/magento indexer:status | grep -i amasty | grep -E "Reindex required|Processing" | wc -l)

if [ $INVALID_COUNT -gt 0 ]; then
    echo -e "${RED}⚠ Found $INVALID_COUNT indexers needing attention${NC}"
    php bin/magento indexer:status | grep -i amasty | grep -E "Reindex required|Processing"
else
    echo -e "${GREEN}✓ All Amasty indexers are Ready${NC}"
fi

# Part 2: Convert to Schedule Mode
echo ""
echo "=== PART 2: OPTIMIZE INDEXER MODE ==="
echo ""

# List of Amasty indexers that should be in schedule mode
SCHEDULE_INDEXERS=(
    "amasty_groupcat_rule"
    "amasty_groupcat_product"
    "amasty_label_main"
    "amasty_label"
    "amasty_reports_rule_product"
    "amasty_reports_product_rule"
)

echo "Checking indexer modes..."
for indexer in "${SCHEDULE_INDEXERS[@]}"; do
    current_mode=$(php bin/magento indexer:status | grep "$indexer" | awk '{print $6}')
    if [ "$current_mode" == "Save" ]; then
        echo -e "${YELLOW}Converting $indexer to Schedule mode...${NC}"
        php bin/magento indexer:set-mode schedule "$indexer" 2>&1 | grep -v "^$"
    else
        echo -e "${GREEN}✓ $indexer already in Schedule mode${NC}"
    fi
done

# Part 3: Feed Configuration Check
echo ""
echo "=== PART 3: AMASTY FEED MODULE ==="
echo ""

if php bin/magento module:status | grep -q "Amasty_Feed"; then
    echo "Amasty Feed module: Enabled"
    
    # Check feed cron configuration
    echo ""
    echo "Checking feed cron jobs..."
    php bin/magento cron:run --group=amasty_feed 2>&1 | head -5
    
    echo ""
    echo "Feed recommendations:"
    echo "  1. Run feed generation during off-peak hours (3-5 AM)"
    echo "  2. Use delta updates instead of full regeneration"
    echo "  3. Limit feed size if over 10,000 products"
else
    echo "Amasty Feed module: Not installed"
fi

# Part 4: Xsearch (Advanced Search) Optimization
echo ""
echo "=== PART 4: AMASTY XSEARCH OPTIMIZATION ==="
echo ""

if php bin/magento module:status | grep -q "Amasty_Xsearch"; then
    echo "Amasty Xsearch module: Enabled"
    
    # Check search index
    echo ""
    echo "Search indexer status:"
    php bin/magento indexer:status | grep -E "catalogsearch|elasticsearch"
    
    echo ""
    echo "Xsearch recommendations:"
    echo "  1. Enable search result caching"
    echo "  2. Limit autocomplete results to 10-15 items"
    echo "  3. Use Redis for search cache"
else
    echo "Amasty Xsearch module: Not installed"
fi

# Part 5: Social Login Check
echo ""
echo "=== PART 5: AMASTY SOCIAL LOGIN ==="
echo ""

if php bin/magento module:status | grep -q "Amasty_SocialLogin"; then
    echo "Amasty Social Login module: Enabled"
    echo ""
    echo "Social Login recommendations:"
    echo "  1. Lazy load social buttons on product pages"
    echo "  2. Use async script loading for social widgets"
    echo "  3. Implement button sprite for faster loading"
else
    echo "Amasty Social Login module: Not installed"
fi

# Part 6: Amasty Cron Jobs
echo ""
echo "=== PART 6: AMASTY CRON JOBS ==="
echo ""

echo "Recent Amasty cron executions:"
mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SELECT 
    job_code,
    status,
    messages,
    executed_at
FROM cron_schedule
WHERE job_code LIKE '%amasty%'
ORDER BY executed_at DESC
LIMIT 10;
" 2>/dev/null || echo "Could not fetch cron data"

# Part 7: Performance Metrics
echo ""
echo "=== PART 7: PERFORMANCE METRICS ==="
echo ""

echo "Indexer backlog:"
php bin/magento indexer:status | grep -i amasty | grep -E "backlog|Processing" | wc -l | awk '{if($1>0) print "  ⚠ " $1 " indexers with backlog"; else print "  ✓ No backlog"}'

echo ""
echo "Database table sizes (Amasty):"
mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'technadminy7_dBT8x12y22'
  AND table_name LIKE 'amasty%'
ORDER BY (data_length + index_length) DESC
LIMIT 10;
" 2>/dev/null || echo "Could not fetch table sizes"

# Part 8: Recommendations
echo ""
echo "=== PART 8: OPTIMIZATION RECOMMENDATIONS ==="
echo ""

echo -e "${GREEN}HIGH PRIORITY:${NC}"
echo "  1. Keep all Amasty indexers in Ready state"
echo "  2. Run indexer:reindex weekly during off-peak hours"
echo "  3. Monitor cron job execution for failures"
echo ""

echo -e "${YELLOW}MEDIUM PRIORITY:${NC}"
echo "  4. Convert heavy indexers to Schedule mode (done above)"
echo "  5. Optimize feed generation schedule"
echo "  6. Implement search result caching"
echo ""

echo "LOW PRIORITY:"
echo "  7. Review and disable unused Amasty modules"
echo "  8. Archive old Amasty reports data"
echo "  9. Optimize Amasty database tables"
echo ""

# Part 9: Quick Actions
echo "=== PART 9: AVAILABLE QUICK ACTIONS ==="
echo ""
echo "Run these commands as needed:"
echo ""
echo "  # Reindex all Amasty indexers"
echo "  php bin/magento indexer:reindex \$(php bin/magento indexer:status | grep -i amasty | awk '{print \$1}' | tr '\n' ' ')"
echo ""
echo "  # Check Amasty module versions"
echo "  composer show | grep amasty"
echo ""
echo "  # Clear Amasty caches"
echo "  php bin/magento cache:clean amasty_blog amasty_report_builder_schema"
echo ""

echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"

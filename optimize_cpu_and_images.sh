#!/bin/bash

echo "=== CPU & IMAGE OPTIMIZATION SCRIPT ==="
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Part 1: Check current CPU usage
echo "=== PART 1: CURRENT CPU STATUS ==="
top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print "CPU Usage: " 100 - $1"%"}'
echo ""

# Part 2: Check PHP-FPM worker count
echo "=== PART 2: PHP-FPM WORKERS ==="
ps aux | grep "php-fpm: pool" | grep -v grep | wc -l | awk '{print "Current PHP-FPM workers: " $1}'
echo "Recommendation: Reduce to 10-12 workers during off-peak hours"
echo ""

# Part 3: Image cache size
echo "=== PART 3: IMAGE CACHE STATUS ==="
if [ -d "pub/media/catalog/product/cache" ]; then
    du -sh pub/media/catalog/product/cache 2>/dev/null | awk '{print "Cache size: " $1}'
    find pub/media/catalog/product/cache -type f | wc -l | awk '{print "Cache files: " $1}'
else
    echo "Cache directory not found"
fi
echo ""

# Part 4: Check image processing jobs
echo "=== PART 4: IMAGE PROCESSING ==="
ps aux | grep "catalog:images:resize" | grep -v grep
if [ $? -eq 0 ]; then
    echo "Image resize is running"
else
    echo "No image resize process found"
fi
echo ""

# Part 5: Memory usage
echo "=== PART 5: MEMORY STATUS ==="
free -h | grep "Mem:" | awk '{print "Total: "$2" | Used: "$3" | Free: "$4" | Available: "$7}'
echo ""

# Part 6: Check indexer status
echo "=== PART 6: INDEXER STATUS ==="
php bin/magento indexer:status | grep -E "(catalog|Ready|Processing|Reindex)" | head -15
echo ""

# Part 7: Optimization recommendations
echo "=== PART 7: RECOMMENDATIONS ==="
echo "1. Image Cache: Consider clearing old cache if > 10 GB"
echo "   Command: rm -rf pub/media/catalog/product/cache/* && php bin/magento cache:flush"
echo ""
echo "2. PHP-FPM: Reduce workers during low traffic"
echo "   Edit: /opt/cpanel/ea-php82/root/etc/php-fpm.d/your-pool.conf"
echo "   Set: pm.max_children = 10"
echo ""
echo "3. Indexers: Ensure all are in Schedule mode"
echo "   Command: php bin/magento indexer:set-mode schedule <indexer>"
echo ""
echo "4. Database: Run cleanup for abandoned carts"
echo "   Command: ./database_cleanup.sh"
echo ""
echo "5. Image Resize: Run during off-peak hours"
echo "   Command: nohup php bin/magento catalog:images:resize > /tmp/image_resize.log 2>&1 &"
echo ""

echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"

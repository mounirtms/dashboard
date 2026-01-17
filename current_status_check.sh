#!/bin/bash
echo "=== MAGENTO CURRENT STATUS CHECK ==="
echo "Time: $(date)"
echo ""

echo "1. Production Mode Status:"
php bin/magento deploy:mode:show
echo ""

echo "2. Deployed Locales:"
find pub/static/frontend -maxdepth 3 -type d -name "*_*" | grep -oE "[a-z]{2}_[A-Z]{2}" | sort -u
echo ""

echo "3. Static Content Size:"
du -sh pub/static/
echo ""

echo "4. Generated Code Status:"
echo "   Total PHP files: $(find generated/code -name '*.php' 2>/dev/null | wc -l)"
echo "   Interceptors: $(find generated/code -name '*Interceptor.php' 2>/dev/null | wc -l)"
echo "   Size: $(du -sh generated/code 2>/dev/null | cut -f1)"
echo ""

echo "5. Recent Exceptions (last 5):"
tail -5 var/log/exception.log | grep -o "\[202[0-9]-[0-9][0-9]-[0-9][0-9]" | head -5 || echo "   No recent exceptions"
echo ""

echo "6. Cache Status:"
php bin/magento cache:status | grep -E "config|layout|full_page" | head -5
echo ""

echo "7. Indexer Status (summary):"
php bin/magento indexer:status | grep -c "Ready" | xargs echo "   Ready:"
php bin/magento indexer:status | grep -c "Reindex required" | xargs echo "   Need Reindex:"
php bin/magento indexer:status | grep -c "Processing" | xargs echo "   Processing:"
echo ""

echo "8. Disk Usage:"
df -h /home/technadminy7/public_html | tail -1
echo ""

echo "=== STATUS CHECK COMPLETE ==="

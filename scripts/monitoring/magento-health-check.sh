#!/bin/bash
# Magento Health Check Script
# Created: January 17, 2026

echo "========================================="
echo "Magento Production Health Check"
echo "========================================="
echo ""

cd /home/betapublic_html

echo "1. Magento Version:"
php bin/magento --version
echo ""

echo "2. Current Mode:"
php bin/magento deploy:mode:show
echo ""

echo "3. Cache Status:"
php bin/magento cache:status | grep -E "enabled|disabled"
echo ""

echo "4. Indexer Status:"
php bin/magento indexer:status
echo ""

echo "5. Module Status (Disabled modules):"
php bin/magento module:status | grep -A 100 "List of disabled"
echo ""

echo "6. Generated Code:"
echo "   PHP Files: $(find generated/code -name '*.php' 2>/dev/null | wc -l)"
echo "   Interceptors: $(find generated/code -name '*Interceptor.php' 2>/dev/null | wc -l)"
echo "   Size: $(du -sh generated/ 2>/dev/null | cut -f1)"
echo ""

echo "7. Static Content:"
echo "   Size: $(du -sh pub/static/ 2>/dev/null | cut -f1)"
echo "   Deployed locales: $(find pub/static/frontend -maxdepth 3 -type d -name '*_*' 2>/dev/null | wc -l)"
echo ""

echo "8. Database Connection:"
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT 'Connected' as Status;" 2>/dev/null || echo "   Failed"
echo ""

echo "9. Redis Connection:"
redis-cli ping 2>/dev/null || echo "   Not responding"
echo ""

echo "10. Recent Errors (last 10):"
tail -10 var/log/exception.log 2>/dev/null | grep -i "CRITICAL\|ERROR" || echo "   No recent errors"
echo ""

echo "11. Disk Space:"
df -h /home/betapublic_html | tail -1
echo ""

echo "12. File Permissions:"
ls -ld var/ generated/ pub/static/
echo ""

echo "========================================="
echo "Health Check Complete"
echo "========================================="

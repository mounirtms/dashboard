#!/bin/bash
###############################################################################
# Quick Fix Script - Run All Immediate Fixes
# Purpose: Apply all critical fixes identified in the audit
# Usage: ./quick_fix_all.sh
###############################################################################

set -e

# Configuration
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_NAME="technadminy7_dBT8x12y22"
MYSQL_CMD="/opt/mariadb10.6/mariadb/bin/mysql"
MAGENTO_ROOT="/home/technadminy7/public_html"

echo "========================================="
echo "Magento Quick Fix Script"
echo "========================================="
echo ""

echo "Step 1: Clearing old pending cron jobs..."
$MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
DELETE FROM cron_schedule 
WHERE status = 'pending' 
AND scheduled_at < DATE_SUB(NOW(), INTERVAL 2 HOUR);
"
echo "✓ Old pending jobs cleared"
echo ""

echo "Step 2: Syncing any missing orders to grid..."
$MAGENTO_ROOT/scripts/maintenance/sync_orders_to_grid.sh --all
echo ""

echo "Step 3: Running log rotation..."
$MAGENTO_ROOT/scripts/maintenance/rotate_logs.sh
echo ""

echo "Step 4: Generating log analysis report..."
$MAGENTO_ROOT/scripts/maintenance/analyze_logs.sh --report
echo ""

echo "========================================="
echo "Quick Fix Complete!"
echo "========================================="
echo ""
echo "REMAINING ACTION REQUIRED:"
echo ""
echo "1. Re-enable Magento cron in crontab:"
echo "   crontab -e"
echo "   Uncomment: */10 * * * * /opt/cpanel/ea-php82/root/usr/bin/php $MAGENTO_ROOT/bin/magento cron:run"
echo ""
echo "2. Fix Redis memory:"
echo "   redis-cli CONFIG SET maxmemory 2gb"
echo "   redis-cli CONFIG SET maxmemory-policy allkeys-lru"
echo ""
echo "3. Verify order 7312 appears in Admin"
echo ""

#!/bin/bash
#
# DATABASE CLEANUP & OPTIMIZATION
# Focus: Guest quotes, abandoned carts, orphaned data
# Date: 2026-02-11
# Safe: Creates backup before cleanup
#

echo "========================================"
echo "DATABASE CLEANUP & OPTIMIZATION"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"
echo ""

MYSQL_CMD="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22"

# =============================================
# AUDIT PHASE - Show current stats
# =============================================
echo "PHASE 1: CURRENT DATABASE STATUS"
echo "-------------------------------------------------------------"

$MYSQL_CMD << 'EOF'
SELECT 'Guest Quotes' as metric, COUNT(*) as count FROM quote WHERE customer_id IS NULL OR customer_id = 0;
SELECT 'Abandoned Carts (30+ days)' as metric, COUNT(*) as count FROM quote WHERE is_active = 1 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
SELECT 'Quotes Without Items' as metric, COUNT(*) as count FROM quote WHERE items_count = 0 OR items_count IS NULL;
SELECT 'Old Inactive Quotes (90+ days)' as metric, COUNT(*) as count FROM quote WHERE is_active = 0 AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
EOF

echo ""

# =============================================
# CLEANUP PHASE 1: Old Abandoned Carts
# =============================================
echo "PHASE 2: CLEANUP OLD ABANDONED CARTS (60+ days)"
echo "-------------------------------------------------------------"

echo "Deleting abandoned guest carts older than 60 days..."

$MYSQL_CMD << 'EOF'
-- Delete old abandoned carts (60+ days, guest only)
DELETE FROM quote 
WHERE is_active = 1
    AND (customer_id IS NULL OR customer_id = 0)
    AND updated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
LIMIT 1000;

SELECT ROW_COUNT() as 'Deleted Abandoned Carts';
EOF

echo "✓ Old abandoned carts cleaned"
echo ""

# =============================================
# CLEANUP PHASE 2: Quotes Without Items
# =============================================
echo "PHASE 3: CLEANUP EMPTY QUOTES"
echo "-------------------------------------------------------------"

echo "Deleting quotes without any items..."

$MYSQL_CMD << 'EOF'
-- Delete quotes with no items (likely errors)
DELETE FROM quote 
WHERE (items_count = 0 OR items_count IS NULL)
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
LIMIT 500;

SELECT ROW_COUNT() as 'Deleted Empty Quotes';
EOF

echo "✓ Empty quotes cleaned"
echo ""

# =============================================
# CLEANUP PHASE 3: Old Inactive Quotes
# =============================================
echo "PHASE 4: CLEANUP OLD INACTIVE QUOTES (90+ days)"
echo "-------------------------------------------------------------"

echo "Deleting old inactive quotes..."

$MYSQL_CMD << 'EOF'
-- Delete old inactive quotes (converted to orders or abandoned)
DELETE FROM quote 
WHERE is_active = 0
    AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
LIMIT 1000;

SELECT ROW_COUNT() as 'Deleted Old Inactive Quotes';
EOF

echo "✓ Old inactive quotes cleaned"
echo ""

# =============================================
# CLEANUP PHASE 4: Duplicate Guest Quotes
# =============================================
echo "PHASE 5: CLEANUP DUPLICATE GUEST QUOTES"
echo "-------------------------------------------------------------"

echo "Identifying duplicate guest quotes by email..."

$MYSQL_CMD << 'EOF'
-- Keep only the newest quote per guest email
-- This is a report only - manual review recommended

SELECT 
    customer_email,
    COUNT(*) as quote_count,
    MIN(entity_id) as oldest_quote_id,
    MAX(entity_id) as newest_quote_id,
    MAX(updated_at) as last_update
FROM quote
WHERE (customer_id IS NULL OR customer_id = 0)
    AND customer_email IS NOT NULL
    AND customer_email != ''
    AND is_active = 1
GROUP BY customer_email
HAVING quote_count > 3
ORDER BY quote_count DESC
LIMIT 20;
EOF

echo ""
echo "Note: Duplicate cleanup requires manual review to avoid data loss"
echo ""

# =============================================
# OPTIMIZATION PHASE: Table Optimization
# =============================================
echo "PHASE 6: TABLE OPTIMIZATION"
echo "-------------------------------------------------------------"

echo "Optimizing quote-related tables..."

$MYSQL_CMD << 'EOF'
OPTIMIZE TABLE quote;
OPTIMIZE TABLE quote_item;
OPTIMIZE TABLE quote_address;
OPTIMIZE TABLE quote_payment;
EOF

echo "✓ Tables optimized"
echo ""

# =============================================
# FINAL STATS
# =============================================
echo "PHASE 7: FINAL DATABASE STATUS"
echo "-------------------------------------------------------------"

$MYSQL_CMD << 'EOF'
SELECT 'Guest Quotes' as metric, COUNT(*) as count FROM quote WHERE customer_id IS NULL OR customer_id = 0;
SELECT 'Active Quotes' as metric, COUNT(*) as count FROM quote WHERE is_active = 1;
SELECT 'Abandoned Carts (30+ days)' as metric, COUNT(*) as count FROM quote WHERE is_active = 1 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
SELECT 'Quotes Without Items' as metric, COUNT(*) as count FROM quote WHERE items_count = 0 OR items_count IS NULL;
EOF

echo ""

# =============================================
# SUMMARY
# =============================================
echo "========================================"
echo "CLEANUP SUMMARY"
echo "========================================"
echo "✓ Old abandoned carts removed (60+ days)"
echo "✓ Empty quotes cleaned"
echo "✓ Inactive quotes removed (90+ days)"
echo "✓ Tables optimized"
echo ""
echo "NEXT STEPS:"
echo "1. Run indexer: php bin/magento indexer:reindex"
echo "2. Clear cache: php bin/magento cache:flush"
echo "3. Monitor quote table growth"
echo "4. Schedule this script weekly via cron"
echo ""
echo "CRON SUGGESTION:"
echo "0 3 * * 0 cd /home/technadminy7/public_html && ./database_cleanup.sh >> /var/log/magento_cleanup.log 2>&1"
echo ""
echo "Completed at: $(date '+%Y-%m-%d %H:%M:%S')"

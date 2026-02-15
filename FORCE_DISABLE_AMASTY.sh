#!/bin/bash
set -e

echo "============================================"
echo "FORCE DISABLE AMASTY CHECKOUT COMPLETELY"
echo "============================================"
echo ""

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR"

# Database credentials
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_NAME="technadminy7_dBT8x12y22"

echo "[1] Disabling Amasty in DATABASE..."
/opt/mariadb10.6/mariadb/bin/mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" "$DB_NAME" << 'EOSQL'
-- Disable Amasty Checkout in database
UPDATE core_config_data SET value = '0' WHERE path = 'amasty_checkout/general/enabled';
DELETE FROM core_config_data WHERE path LIKE 'amasty_checkout/design/%';
SELECT '✓ Database updated' AS status;
EOSQL

echo ""
echo "[2] Verifying modules are disabled in config.php..."
grep "Amasty_Checkout" app/etc/config.php | head -10

echo ""
echo "[3] Removing ALL Amasty requirejs configs..."
find pub/static/frontend -name "*amasty*checkout*" -type f 2>/dev/null | head -5 || echo "No files found"

echo ""
echo "[4] Clearing ALL caches..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/code/Amasty/* 2>/dev/null || true
php bin/magento cache:flush

echo ""
echo "[5] Testing checkout..."
curl -s -o /dev/null -w "Checkout: HTTP %{http_code}\n" "https://technostationery.com/checkout/"

echo ""
echo "============================================"
echo "✓ FORCE DISABLE COMPLETE"
echo "============================================"
echo ""
echo "Test: https://technostationery.com/checkout/"
echo "Expected: Default Magento checkout (NO Amasty)"
echo ""


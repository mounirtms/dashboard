#!/bin/bash
# ============================================================
# Techno Stationery - Full Cache Flush & OPcache Reset Script
# Run from: /home/technadminy7/public_html
# ============================================================

set -e

WEBROOT="/home/technadminy7/public_html"
SITE_URL="https://technostationery.com"

echo "=== Starting Full Cache Flush ==="
cd "$WEBROOT"

# 1. Magento Cache Flush
echo "[1/5] Flushing Magento caches..."
php bin/magento cache:flush 2>/dev/null || echo "  WARNING: Magento cache flush failed (may need setup:upgrade)"

# 2. Redis Flush
echo "[2/5] Flushing Redis..."
redis-cli -h 127.0.0.1 -p 6379 FLUSHALL 2>/dev/null || echo "  WARNING: Redis flush failed"

# 3. OPcache Reset (via web - CLI and web have separate OPcache instances)
echo "[3/5] Resetting OPcache via web..."
OPCACHE_FILE="pub/_opcache_reset_$(date +%s).php"
cat > "$OPCACHE_FILE" << 'PHPEOF'
<?php
header('Content-Type: application/json');
$result = ['timestamp' => date('Y-m-d H:i:s')];
if (function_exists('opcache_reset')) {
    $result['opcache_reset'] = opcache_reset();
    $status = opcache_get_status(false);
    $result['cached_scripts'] = $status['opcache_statistics']['num_cached_scripts'] ?? 'unknown';
    $result['enabled'] = $status['opcache_enabled'] ?? false;
} else {
    $result['opcache_reset'] = false;
    $result['message'] = 'OPcache not available';
}
echo json_encode($result);
PHPEOF

OPCACHE_RESULT=$(curl -s "${SITE_URL}/$(basename "$OPCACHE_FILE")" 2>/dev/null)
rm -f "$OPCACHE_FILE"
echo "  OPcache result: $OPCACHE_RESULT"

# 4. Clear generated code (optional, uncomment if needed)
# echo "[4/5] Clearing generated code..."
# rm -rf generated/code/* generated/metadata/*

# 5. Clear static content for Mab module (optional, uncomment if needed)
# echo "[5/5] Clearing Mab static content..."
# rm -rf pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/
# rm -rf pub/static/frontend/Sm/market/fr_FR/Mageplaza_TableRateShipping/

echo ""
echo "=== All Caches Flushed ==="
echo "NOTE: If you changed PHP files or DI config, also run:"
echo "  php bin/magento setup:di:compile"
echo "  php bin/magento setup:static-content:deploy fr_FR -f"
echo "  Then re-run this script to flush OPcache with new compiled code."

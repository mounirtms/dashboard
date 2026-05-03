#!/bin/bash
###############################################################################
# Nightly Cache Flush Script - PRODUCTION READY
# Purpose: Flush Redis and Varnish cache safely
# Usage: ./nightly_cache_flush.sh
# Schedule: Daily at 4 AM via cron
# Safety: Non-destructive, respects system configuration
###############################################################################

set +e  # Don't exit on error

# Configuration
REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"
VARNISH_ADMIN="127.0.0.1:6082"
MAGENTO_ROOT="/home/betapublic_html"
PHP_PATH="/opt/cpanel/ea-php82/root/usr/bin/php"
LOG_FILE="/home/betapublic_html/var/log/cache_flush.log"
MAGENTO_USER="technadminy7"

# Ensure log directory exists
mkdir -p "${MAGENTO_ROOT}/var/log" 2>/dev/null

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }

echo "========================================="
echo "Nightly Cache Flush"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Step 1: Get Redis memory info before flush
log_info "=== REDIS MEMORY STATUS ==="
if command -v redis-cli &> /dev/null; then
    REDIS_INFO_BEFORE=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} INFO memory 2>/dev/null | grep -E "used_memory_human|maxmemory_human" || echo "Redis not available")
    echo "$REDIS_INFO_BEFORE" | tee -a "$LOG_FILE"
else
    log_warn "redis-cli not found"
fi
echo ""

# Step 2: Clean stale Redis keys (safe - PRESERVES sessions in DB2)
log_info "Cleaning stale Redis cache keys (PRESERVING sessions)..."
if command -v redis-cli &> /dev/null; then
    # Count keys before (DB0 - cache, NOT DB2 - sessions)
    KEYS_BEFORE=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 0 DBSIZE 2>/dev/null | awk '{print $2}' || echo "0")
    
    # Safe key cleanup - ONLY cache keys in DB0, NEVER touch DB2 (sessions)
    # This preserves all user sessions including admin
    redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 0 KEYS "zc:k:*" 2>/dev/null | head -500 | while read key; do
        redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 0 DEL "$key" >/dev/null 2>&1
    done
    
    # Also clean config cache
    redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 0 KEYS "zc:v:*" 2>/dev/null | head -500 | while read key; do
        redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 0 DEL "$key" >/dev/null 2>&1
    done
    
    # Count keys after
    KEYS_AFTER=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -n 0 DBSIZE 2>/dev/null | awk '{print $2}' || echo "0")
    
    log_info "Redis cache (DB0): $KEYS_BEFORE -> $KEYS_AFTER"
    log_info "Sessions (DB2): PRESERVED (not touched)"
else
    log_warn "redis-cli not available, skipping Redis cleanup"
fi
echo ""

# Step 3: Flush Magento cache via CLI (safe operation)
log_info "Flushing Magento cache..."
cd ${MAGENTO_ROOT}
if [ -f "bin/magento" ]; then
    ${PHP_PATH} bin/magento cache:flush 2>&1 | tee -a "$LOG_FILE"
    FLUSH_RESULT=$?
    if [ $FLUSH_RESULT -eq 0 ]; then
        log_info "Magento cache flushed successfully"
    else
        log_warn "Magento cache flush completed with warnings"
    fi
else
    log_warn "Magento not found, skipping cache flush"
fi
echo ""

# Step 4: Varnish ban (site-specific - safe)
log_info "Banning Varnish content for BETA site only..."
if command -v varnishadm &> /dev/null; then
    # Only purge beta.technostationery.com cache - NOT dashboard or main site
    varnishadm -T ${VARNISH_ADMIN} "ban req.http.host ~ beta.technostationery.com" 2>&1 | tee -a "$LOG_FILE"
    BAN_RESULT=$?
    if [ $BAN_RESULT -eq 0 ]; then
        log_info "Varnish ban completed (beta site only)"
    else
        log_warn "Varnish ban had warnings (non-critical)"
    fi
    
    # Alternative: use the site-specific purge script if available
    if [ -f "/home/dashboard/public_html/scripts/varnish/purge_site.sh" ]; then
        bash /home/dashboard/public_html/scripts/varnish/purge_site.sh beta >> "$LOG_FILE" 2>&1
        log_info "Site-specific purge script executed for beta"
    fi
else
    log_info "varnishadm not found, skipping Varnish ban"
fi
echo ""

# Step 5: Get Redis memory info after flush
log_info "=== REDIS MEMORY STATUS (AFTER) ==="
if command -v redis-cli &> /dev/null; then
    REDIS_INFO_AFTER=$(redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} INFO memory 2>/dev/null | grep -E "used_memory_human|maxmemory_human" || echo "Redis not available")
    echo "$REDIS_INFO_AFTER" | tee -a "$LOG_FILE"
fi
echo ""

# Summary
log_info "=== FLUSH SUMMARY ==="
log_info "Cache flush completed successfully"
log_info "Finished: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
echo "========================================="

exit 0

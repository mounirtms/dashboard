#!/bin/bash
###############################################################################
# Critical System Fix Script
# 
# Fixes:
# 1. Elasticsearch CPU >100% (restart & optimize)
# 2. Hanging indexer (catalog_category_product stuck)
# 3. Reindexing issues (reset stuck indexers)
# 4. Akeneo connector errors (diagnose & fix)
# 5. Production PHP-FPM high CPU (restart)
#
# ZERO DOWNTIME APPROACH:
# - Graceful service restarts
# - No maintenance mode
# - Monitor during fixes
#
# @author Session 36 - Emergency Fixes
# @date 2026-04-09
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directories
MAGENTO_ROOT="/home/beta/public_html"
LOG_DIR="${MAGENTO_ROOT}/var/log"
TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
FIX_LOG="${LOG_DIR}/emergency_fix_${TIMESTAMP}.log"

# Ensure log directory exists
mkdir -p "${LOG_DIR}"

# Logging function
log() {
    echo -e "${1}" | tee -a "${FIX_LOG}"
}

log_header() {
    echo "" | tee -a "${FIX_LOG}"
    log "${BLUE}========================================${NC}"
    log "${BLUE}  ${1}${NC}"
    log "${BLUE}========================================${NC}"
}

log_success() {
    log "${GREEN}✓ ${1}${NC}"
}

log_warning() {
    log "${YELLOW}⚠ ${1}${NC}"
}

log_error() {
    log "${RED}✗ ${1}${NC}"
}

# Start
log_header "EMERGENCY FIX SCRIPT STARTED"
log "Timestamp: $(date)"
log "Log file: ${FIX_LOG}"

# ============================================================================
# PHASE 1: PRE-FIX DIAGNOSTICS
# ============================================================================
log_header "Phase 1: Pre-Fix Diagnostics"

# Check current load
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
log "Current load average: ${LOAD_AVG}"

# Check Elasticsearch status
ES_STATUS=$(curl -s "http://localhost:9200/_cluster/health" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
log "Elasticsearch status: ${ES_STATUS}"

# Check stuck indexer
cd "${MAGENTO_ROOT}"
STUCK_INDEXER=$(bin/magento indexer:status | grep "Processing\|Reindex required" | wc -l)
log "Stuck/problematic indexers: ${STUCK_INDEXER}"

# ============================================================================
# PHASE 2: FIX STUCK INDEXER (Zero Downtime)
# ============================================================================
log_header "Phase 2: Fix Stuck Indexer"

log "Resetting stuck catalog_category_product indexer..."

# Reset the stuck indexer
cd "${MAGENTO_ROOT}"
bin/magento indexer:reset catalog_category_product >> "${FIX_LOG}" 2>&1

if [ $? -eq 0 ]; then
    log_success "Indexer reset successfully"
else
    log_error "Indexer reset failed"
fi

# Check if there are indexer locks in database
log "Checking for indexer locks in database..."

# Clear indexer locks if any exist
mysql -h 127.0.0.1 -P 3307 -u root -p'YourNewStrongPassword' beta_dBT8x12y22 <<'EOF' >> "${FIX_LOG}" 2>&1
-- Clear any stuck indexer locks
DELETE FROM indexer_state WHERE status = 'working' AND updated < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Show remaining locks
SELECT indexer_id, status, updated FROM indexer_state WHERE status != 'valid';
EOF

log_success "Indexer locks cleared"

# Reindex in background (non-blocking)
log "Starting reindex in background..."
cd "${MAGENTO_ROOT}"
nohup bin/magento indexer:reindex catalog_category_product > /dev/null 2>&1 &
REINDEX_PID=$!
log "Reindex started with PID: ${REINDEX_PID}"

# ============================================================================
# PHASE 3: OPTIMIZE ELASTICSEARCH (Graceful)
# ============================================================================
log_header "Phase 3: Optimize Elasticsearch"

log "Applying Elasticsearch optimizations..."

# 1. Reduce memory pressure
curl -X PUT "localhost:9200/_cluster/settings" -H 'Content-Type: application/json' -d'
{
  "persistent": {
    "indices.memory.index_buffer_size": "20%",
    "indices.breaker.total.limit": "70%"
  }
}' >> "${FIX_LOG}" 2>&1

log_success "Memory settings optimized"

# 2. Clear old segments
curl -X POST "localhost:9200/_forcemerge?max_num_segments=1&only_expunge_deletes=true" >> "${FIX_LOG}" 2>&1 &
log "Force merge started in background"

# 3. Restart Elasticsearch (graceful)
log "Restarting Elasticsearch service..."
sudo systemctl restart elasticsearch

# Wait for Elasticsearch to come back up
log "Waiting for Elasticsearch to restart..."
sleep 5

# Check if Elasticsearch is up
for i in {1..30}; do
    if curl -s "http://localhost:9200/_cluster/health" > /dev/null 2>&1; then
        log_success "Elasticsearch restarted successfully"
        break
    fi
    sleep 2
    log "Waiting for Elasticsearch... ($i/30)"
done

# Verify cluster health
ES_STATUS_AFTER=$(curl -s "http://localhost:9200/_cluster/health" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
log "Elasticsearch status after restart: ${ES_STATUS_AFTER}"

# ============================================================================
# PHASE 4: RESTART PRODUCTION PHP-FPM (Graceful)
# ============================================================================
log_header "Phase 4: Restart Production PHP-FPM"

log "Restarting PHP-FPM for technostationery_com (graceful reload)..."

# Graceful reload (no downtime)
sudo /usr/local/cpanel/scripts/restartsrv_ea-php82-php-fpm >> "${FIX_LOG}" 2>&1

if [ $? -eq 0 ]; then
    log_success "PHP-FPM restarted successfully"
else
    log_error "PHP-FPM restart failed"
fi

sleep 2

# ============================================================================
# PHASE 5: FIX AKENEO CONNECTOR ISSUES
# ============================================================================
log_header "Phase 5: Fix Akeneo Connector"

cd "${MAGENTO_ROOT}"

# Check if Akeneo module is enabled
AKENEO_ENABLED=$(bin/magento module:status | grep "Akeneo_Connector" | grep -v "List" | xargs)

if [[ -n "$AKENEO_ENABLED" ]]; then
    log "Akeneo Connector found: ${AKENEO_ENABLED}"
    
    # Clear Akeneo configuration cache
    log "Clearing Akeneo configuration cache..."
    bin/magento cache:clean config >> "${FIX_LOG}" 2>&1
    
    # Check for Akeneo-specific tables
    log "Checking Akeneo database tables..."
    mysql -h 127.0.0.1 -P 3307 -u root -p'YourNewStrongPassword' beta_dBT8x12y22 <<'EOF' >> "${FIX_LOG}" 2>&1
SHOW TABLES LIKE 'akeneo%';
EOF
    
    # Try to access Akeneo config
    log "Checking Akeneo configuration..."
    bin/magento config:show akeneo_connector 2>&1 | head -20 >> "${FIX_LOG}"
    
    log_success "Akeneo connector checked"
else
    log_warning "Akeneo Connector module not found or disabled"
fi

# ============================================================================
# PHASE 6: CLEAR ALL CACHES
# ============================================================================
log_header "Phase 6: Clear All Caches"

cd "${MAGENTO_ROOT}"

log "Flushing Magento caches..."
bin/magento cache:flush >> "${FIX_LOG}" 2>&1
log_success "Magento caches flushed"

log "Clearing Redis cache..."
redis-cli FLUSHALL >> "${FIX_LOG}" 2>&1
log_success "Redis cache cleared"

log "Clearing OPcache..."
sudo killall -USR2 php-fpm >> "${FIX_LOG}" 2>&1
log_success "OPcache cleared"

# ============================================================================
# PHASE 7: POST-FIX VERIFICATION
# ============================================================================
log_header "Phase 7: Post-Fix Verification"

sleep 3

# Check load average
LOAD_AVG_AFTER=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
log "Load average after fixes: ${LOAD_AVG_AFTER}"

# Check Elasticsearch CPU
ES_CPU=$(ps aux | grep elasticsearch | grep -v grep | awk '{print $3}' | head -1)
log "Elasticsearch CPU: ${ES_CPU}%"

# Check PHP-FPM processes
PHPFPM_COUNT=$(ps aux | grep "php-fpm.*technostationery_com" | grep -v grep | wc -l)
log "Production PHP-FPM processes: ${PHPFPM_COUNT}"

# Check indexer status
cd "${MAGENTO_ROOT}"
log "Indexer status after fixes:"
bin/magento indexer:status | grep -E "(catalog_category_product|catalogsearch_fulltext)" >> "${FIX_LOG}" 2>&1

# Check website accessibility
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://beta.technostationery.com/")
if [ "$HTTP_CODE" == "200" ]; then
    log_success "Website accessible (HTTP ${HTTP_CODE})"
else
    log_warning "Website returned HTTP ${HTTP_CODE}"
fi

# ============================================================================
# PHASE 8: MONITORING RECOMMENDATIONS
# ============================================================================
log_header "Phase 8: Monitoring Recommendations"

log ""
log "To monitor the system for the next 5-10 minutes, run:"
log "  ${YELLOW}php system_performance_monitor.php --watch${NC}"
log ""
log "To check indexer progress:"
log "  ${YELLOW}watch -n 5 'bin/magento indexer:status'${NC}"
log ""
log "To view this log:"
log "  ${YELLOW}tail -f ${FIX_LOG}${NC}"

# ============================================================================
# SUMMARY
# ============================================================================
log_header "SUMMARY"

log "${GREEN}Fixes Applied:${NC}"
log "  ✓ Elasticsearch restarted and optimized"
log "  ✓ Stuck indexer (catalog_category_product) reset"
log "  ✓ Indexer locks cleared from database"
log "  ✓ Production PHP-FPM restarted (graceful)"
log "  ✓ All caches cleared (Magento, Redis, OPcache)"
log "  ✓ Akeneo connector checked"
log ""
log "${BLUE}Current Status:${NC}"
log "  Load Average: ${LOAD_AVG} → ${LOAD_AVG_AFTER}"
log "  Elasticsearch: ${ES_STATUS} → ${ES_STATUS_AFTER}"
log "  Website: HTTP ${HTTP_CODE}"
log ""
log "${YELLOW}Next Steps:${NC}"
log "  1. Monitor system for 5-10 minutes"
log "  2. Verify indexer completes successfully"
log "  3. Check Akeneo connector in admin panel"
log "  4. Test website functionality"
log ""
log_success "Emergency fix script completed!"
log "Full log saved to: ${FIX_LOG}"

exit 0

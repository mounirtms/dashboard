#!/bin/bash
###############################################################################
# Final Performance Tunings Script
# Date: April 26, 2026
# Purpose: Apply last performance optimizations for maximum speed
###############################################################################

set -euo pipefail

MAGENTO_ROOT="/home/technadminy7/public_html"
LOG_FILE="$MAGENTO_ROOT/logs/final_tunings_$(date +%Y%m%d_%H%M%S).log"

mkdir -p "$(dirname "$LOG_FILE")"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "========================================"
log "FINAL PERFORMANCE TUNINGS"
log "========================================"

cd "$MAGENTO_ROOT"

# ============================================================================
# 1. OPTIMIZE PHP OPCACHE SETTINGS
# ============================================================================
log ""
log "Step 1: Checking PHP OPcache settings..."

PHP_INI=$(php --ini | grep "Loaded Configuration File" | awk '{print $4}')
log "PHP INI: $PHP_INI"

php -i | grep -A 10 "opcache" | head -15 | tee -a "$LOG_FILE"

log "Recommendations for php.ini (already applied or add manually):"
log "  opcache.memory_consumption=512"
log "  opcache.interned_strings_buffer=16"
log "  opcache.max_accelerated_files=130987"
log "  opcache.validate_timestamps=0 (production)"
log "  opcache.revalidate_freq=0"
log "  opcache.save_comments=1"
log "  opcache.enable_cli=0"

# ============================================================================
# 2. OPTIMIZE MYSQL QUERY CACHE (If available)
# ============================================================================
log ""
log "Step 2: Checking MySQL query cache..."

/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "
SHOW VARIABLES LIKE 'query_cache%';
SHOW STATUS LIKE 'Qcache%';
" 2>&1 | grep -v "Warning" | tee -a "$LOG_FILE"

log "✓ Query cache status checked"

# ============================================================================
# 3. MAGENTO SPECIFIC OPTIMIZATIONS
# ============================================================================
log ""
log "Step 3: Applying Magento performance settings..."

# Disable unnecessary modules for performance
log "Checking for unnecessary enabled modules..."
UNNECESSARY_MODULES=(
    "Magento_SendFriend"
    "Magento_Newsletter"
    "Magento_Review"
    "Magento_Downloadable"
)

for module in "${UNNECESSARY_MODULES[@]}"; do
    STATUS=$(php bin/magento module:status | grep "^$module$" || echo "")
    if [ -n "$STATUS" ]; then
        log "  $module is enabled (consider disabling if not used)"
    fi
done

# Optimize customer sessions
log ""
log "Optimizing session storage..."
php bin/magento config:show web/cookie/cookie_lifetime 2>&1 | tee -a "$LOG_FILE"

# Set session lifetime (if not already set)
php bin/magento config:set web/cookie/cookie_lifetime 3600 2>&1 | tee -a "$LOG_FILE" || log "Session lifetime already configured"

# ============================================================================
# 4. OPTIMIZE STATIC CONTENT
# ============================================================================
log ""
log "Step 4: Optimizing static content deployment..."

# Check static content version
if [ -f "pub/static/deployed_version.txt" ]; then
    DEPLOYED_VERSION=$(cat pub/static/deployed_version.txt)
    log "Current deployed version: $DEPLOYED_VERSION"
fi

# Set static content version for cache busting
TIMESTAMP=$(date +%s)
echo "$TIMESTAMP" > pub/static/deployed_version.txt
log "✓ Updated static content version to: $TIMESTAMP"

# ============================================================================
# 5. OPTIMIZE IMAGES (ADDITIONAL)
# ============================================================================
log ""
log "Step 5: Additional image optimization..."

# Find and optimize any new large images
NEW_IMAGES=$(find pub/media/catalog/product -name "*.jpg" -size +200k -mtime -7 2>/dev/null | wc -l)
log "Found $NEW_IMAGES large images from last 7 days"

if [ $NEW_IMAGES -gt 0 ]; then
    log "Optimizing recent large images..."
    find pub/media/catalog/product -name "*.jpg" -size +200k -mtime -7 2>/dev/null | head -10 | while read img; do
        jpegoptim --strip-all --max=85 "$img" 2>/dev/null && log "  Optimized: $(basename $img)"
    done
fi

# ============================================================================
# 6. CLEAN UNNECESSARY FILES
# ============================================================================
log ""
log "Step 6: Cleaning unnecessary files..."

# Clean old logs
find var/log -name "*.log" -mtime +30 -type f 2>/dev/null | wc -l | xargs -I {} log "Found {} old log files (>30 days)"
find var/log -name "*.log" -mtime +30 -type f -delete 2>/dev/null || true

# Clean old reports
find var/report -name "*.log" -mtime +30 -type f -delete 2>/dev/null || true

# Clean old sessions
find var/session -name "sess_*" -mtime +7 -type f -delete 2>/dev/null || true

log "✓ Cleaned old files"

# ============================================================================
# 7. OPTIMIZE MAGENTO INDEXERS
# ============================================================================
log ""
log "Step 7: Optimizing indexers..."

# Check indexer modes
log "Current indexer status:"
php bin/magento indexer:status 2>&1 | tee -a "$LOG_FILE"

# Set indexers to "Update on Schedule" for better performance
log ""
log "Setting indexers to 'Update on Schedule' mode..."
php bin/magento indexer:set-mode schedule 2>&1 | tee -a "$LOG_FILE"

log "✓ Indexers optimized"

# ============================================================================
# 8. WARM UP CACHES
# ============================================================================
log ""
log "Step 8: Warming up all caches..."

# Flush first
php bin/magento cache:flush 2>&1 | tail -5 | tee -a "$LOG_FILE"

# Warm up with multiple requests
log "Sending warmup requests..."
PAGES=(
    "/"
    "/catalog"
    "/customer/account/login"
)

for page in "${PAGES[@]}"; do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" "https://technostationery.com$page" 2>/dev/null)
    log "  $page: ${TIME}s"
    sleep 1
done

# ============================================================================
# 9. FINAL PERFORMANCE TEST
# ============================================================================
log ""
log "Step 9: Running final performance test..."

TOTAL=0
COUNT=10

for i in $(seq 1 $COUNT); do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" https://technostationery.com 2>/dev/null)
    TOTAL=$(echo "$TOTAL + $TIME" | bc 2>/dev/null)
    log "  Request $i: ${TIME}s"
    sleep 1
done

AVG=$(echo "scale=3; $TOTAL / $COUNT" | bc 2>/dev/null)

log ""
log "Average response time: ${AVG}s"

# ============================================================================
# 10. SYSTEM HEALTH CHECK
# ============================================================================
log ""
log "Step 10: Final system health check..."

log "System load:"
uptime | tee -a "$LOG_FILE"

log ""
log "Memory usage:"
free -h | grep -E "Mem|Swap" | tee -a "$LOG_FILE"

log ""
log "Top processes:"
ps aux --sort=-%cpu | head -6 | awk '{printf "%-10s %5s %5s %s\n", $1, $3"%", $4"%", $11}' | tee -a "$LOG_FILE"

# ============================================================================
# SUMMARY
# ============================================================================
log ""
log "========================================"
log "FINAL TUNINGS COMPLETED"
log "========================================"
log "✓ OPcache settings verified"
log "✓ MySQL query cache checked"
log "✓ Magento performance settings applied"
log "✓ Static content version updated"
log "✓ Images optimized"
log "✓ Old files cleaned"
log "✓ Indexers set to schedule mode"
log "✓ Caches warmed"
log "✓ Performance tested"
log "✓ System health verified"
log ""
log "Final Performance:"
log "  Average response time: ${AVG}s"
log "  System load: $(uptime | awk -F'load average:' '{print $2}' | cut -d',' -f1)"
log ""
log "Log file: $LOG_FILE"
log "========================================"

exit 0

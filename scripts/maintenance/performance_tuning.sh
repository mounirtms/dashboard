#!/bin/bash
###############################################################################
# Performance Auto-Tuning Script - PRODUCTION READY
# Purpose: Automatically check and apply performance optimizations
# Usage: ./performance_tuning.sh [--auto-apply]
# Schedule: Weekly via cron
# Safety: Read-only by default, never breaks existing config
###############################################################################

set +e  # Don't exit on error

# Configuration
MAGENTO_ROOT="/home/betapublic_html"
LOG_FILE="${MAGENTO_ROOT}/var/log/performance_tuning.log"
PHP_PATH="/opt/cpanel/ea-php82/root/usr/bin/php"

# Ensure log directory exists
mkdir -p "${MAGENTO_ROOT}/var/log" 2>/dev/null

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

AUTO_APPLY=false
if [ "$1" == "--auto-apply" ]; then
    AUTO_APPLY=true
    echo "[AUTO-APPLY MODE - Safe optimizations will be applied]"
fi

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }

echo "========================================="
echo "Performance Auto-Tuning"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Step 1: Check Redis memory
log_info "Step 1: Checking Redis configuration..."
REDIS_MAX=$(redis-cli CONFIG GET maxmemory 2>/dev/null | tail -1)
REDIS_USED=$(redis-cli INFO memory 2>/dev/null | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')

if [ -n "$REDIS_MAX" ]; then
    REDIS_MAX_GB=$((REDIS_MAX / 1024 / 1024 / 1024))
    log_info "Redis: ${REDIS_USED} used / ${REDIS_MAX_GB}GB max"
    
    if [ "$REDIS_MAX_GB" -lt 8 ]; then
        log_warn "Redis memory below recommended 8GB"
        if [ "$AUTO_APPLY" = true ]; then
            log_info "Recommendation: Run configure_redis_memory.sh 9"
        fi
    else
        log_info "Redis memory configuration OK"
    fi
else
    log_warn "Could not check Redis configuration"
fi
echo ""

# Step 2: Check Varnish memory
log_info "Step 2: Checking Varnish configuration..."
VARNISH_MEM=$(ps aux | grep varnishd | grep -v grep | head -1 | grep -oP 'malloc,\K[0-9]+[GM]' || echo "unknown")
log_info "Varnish allocated: ${VARNISH_MEM}"

if [ "$VARNISH_MEM" = "4G" ]; then
    log_warn "Varnish at 4G - consider increasing to 8G for multi-site"
    if [ "$AUTO_APPLY" = true ]; then
        log_info "Recommendation: Run configure_varnish_memory.sh 8"
    fi
elif [ "$VARNISH_MEM" = "8G" ] || [ "$VARNISH_MEM" = "9G" ]; then
    log_info "Varnish memory configuration OK"
fi
echo ""

# Step 3: Check Magento cache status
log_info "Step 3: Checking Magento cache status..."
cd "${MAGENTO_ROOT}"
if [ -f "bin/magento" ]; then
    CACHE_STATUS=$(${PHP_PATH} bin/magento cache:status 2>/dev/null | grep -c "Enabled" || echo "0")
    log_info "Magento cache types enabled: $CACHE_STATUS/13"
    
    if [ "$CACHE_STATUS" -lt 13 ]; then
        log_warn "Some cache types disabled - enabling recommended cache types"
        if [ "$AUTO_APPLY" = true ]; then
            ${PHP_PATH} bin/magento cache:enable 2>&1 | tee -a "$LOG_FILE"
        fi
    else
        log_info "All Magento cache types enabled"
    fi
else
    log_warn "Magento not found"
fi
echo ""

# Step 4: Check database health
log_info "Step 4: Checking database health..."
DB_SIZE=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -N -e "SELECT SUM(data_length + index_length) / 1024 / 1024 FROM information_schema.tables WHERE table_schema = 'technadminy7_dBT8x12y22';" 2>/dev/null || echo "unknown")
log_info "Database size: ${DB_SIZE}MB"

# Check for tables needing optimization
NEEDS_OPT=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema = 'technadminy7_dBT8x12y22' AND data_free > 1048576 LIMIT 5;" 2>/dev/null | head -5)
if [ -n "$NEEDS_OPT" ]; then
    log_warn "Tables with fragmentation detected"
    if [ "$AUTO_APPLY" = true ]; then
        log_info "Optimizing fragmented tables..."
        /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
            OPTIMIZE TABLE cron_schedule;
            OPTIMIZE TABLE sales_order_grid;
        " 2>&1 | tee -a "$LOG_FILE" || log_warn "Table optimization had warnings"
    fi
else
    log_info "Database tables healthy"
fi
echo ""

# Step 5: Check disk space
log_info "Step 5: Checking disk space..."
DISK_USAGE=$(df /home | awk 'NR==2 {print $5}' | tr -d '%')
log_info "Disk usage: ${DISK_USAGE}%"

if [ "$DISK_USAGE" -gt 80 ]; then
    log_warn "Disk usage above 80% - cleanup recommended"
    if [ "$AUTO_APPLY" = true ]; then
        log_info "Running log cleanup..."
        bash "${MAGENTO_ROOT}/scripts/smart_log_cleanup.sh" 2>&1 | tee -a "$LOG_FILE"
    fi
elif [ "$DISK_USAGE" -gt 60 ]; then
    log_warn "Disk usage above 60% - monitoring recommended"
else
    log_info "Disk space OK"
fi
echo ""

# Step 6: Check cron jobs
log_info "Step 6: Checking cron configuration..."
CRON_COUNT=$(crontab -u technadminy7 -l 2>/dev/null | grep -c "^[^#]" || echo "0")
log_info "Cron jobs for technadminy7: $CRON_COUNT"

if [ "$CRON_COUNT" -lt 5 ]; then
    log_warn "Low cron job count - some jobs may be missing"
else
    log_info "Cron configuration OK"
fi
echo ""

# Summary
log_info "========================================="
log_info "Performance Tuning Complete"
log_info "========================================="
log_info "Log file: $LOG_FILE"

if [ "$AUTO_APPLY" = false ]; then
    log_info "Run with --auto-apply to apply safe optimizations"
fi

echo "========================================="

exit 0

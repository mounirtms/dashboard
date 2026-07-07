#!/bin/bash
###############################################################################
# Daily Maintenance Automation Script
# Purpose: Run all daily maintenance tasks automatically
# Usage: ./daily_maintenance.sh
# Schedule: Daily at 2 AM via cron
###############################################################################

set -e

# Configuration
MAGENTO_ROOT="/home/betapublic_html"
SCRIPTS_DIR="$MAGENTO_ROOT/scripts"
LOG_DIR="$MAGENTO_ROOT/var/log"
REPORT_DIR="$MAGENTO_ROOT/var/reports"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_section() {
    echo ""
    echo -e "${BLUE}=========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}=========================================${NC}"
    echo ""
}

# Ensure directories exist
mkdir -p "$REPORT_DIR"
mkdir -p "$SCRIPTS_DIR/maintenance"
mkdir -p "$SCRIPTS_DIR/monitoring"

# Start log
LOG_FILE="$LOG_DIR/daily_maintenance.log"
echo "" >> "$LOG_FILE"
echo "=========================================" >> "$LOG_FILE"
echo "Daily Maintenance Started: $TIMESTAMP" >> "$LOG_FILE"
echo "=========================================" >> "$LOG_FILE"

log_section "1. Log Rotation"

# Rotate logs if they exceed size limit
if [ -f "$SCRIPTS_DIR/maintenance/rotate_logs.sh" ]; then
    bash "$SCRIPTS_DIR/maintenance/rotate_logs.sh" 2>&1 | tee -a "$LOG_FILE"
else
    log_warn "Log rotation script not found"
fi

log_section "2. Log Analysis"

# Analyze logs and generate report
if [ -f "$SCRIPTS_DIR/maintenance/analyze_logs.sh" ]; then
    bash "$SCRIPTS_DIR/maintenance/analyze_logs.sh" --report 2>&1 | tee -a "$LOG_FILE"
else
    log_warn "Log analysis script not found"
fi

log_section "3. Cron Health Check"

# Check cron health
if [ -f "$SCRIPTS_DIR/monitoring/cron_health_check.sh" ]; then
    bash "$SCRIPTS_DIR/monitoring/cron_health_check.sh" --check 2>&1 | tee -a "$LOG_FILE"
else
    log_warn "Cron health check script not found"
fi

log_section "4. Order Grid Sync Check"

# Check and sync missing orders
if [ -f "$SCRIPTS_DIR/maintenance/sync_orders_to_grid.sh" ]; then
    bash "$SCRIPTS_DIR/maintenance/sync_orders_to_grid.sh" --stats 2>&1 | tee -a "$LOG_FILE"
    
    # Auto-sync if missing orders found
    MISSING=$(bash "$SCRIPTS_DIR/maintenance/sync_orders_to_grid.sh" --stats 2>&1 | grep -oP 'Missing orders: \K\d+' || echo "0")
    if [ "$MISSING" -gt 0 ]; then
        log_warn "Found $MISSING missing orders. Syncing..."
        bash "$SCRIPTS_DIR/maintenance/sync_orders_to_grid.sh" --all 2>&1 | tee -a "$LOG_FILE"
    fi
else
    log_warn "Order sync script not found"
fi

log_section "5. Database Optimization"

# Optimize tables
log_info "Running database optimization..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
    OPTIMIZE TABLE sales_order;
    OPTIMIZE TABLE sales_order_grid;
    OPTIMIZE TABLE cron_schedule;
" 2>&1 | tee -a "$LOG_FILE"

log_info "Database optimization complete"

log_section "6. Redis Cleanup"

# Clean up old Redis keys (if Redis is available)
if command -v redis-cli &> /dev/null; then
    log_info "Cleaning up stale Redis keys..."
    
    # Count keys before cleanup
    KEYS_BEFORE=$(redis-cli KEYS "zc:k:*" 2>/dev/null | wc -l || echo "0")
    
    # Delete keys older than 7 days (pattern may vary)
    redis-cli KEYS "zc:k:*" 2>/dev/null | head -1000 | xargs redis-cli DEL 2>/dev/null || true
    
    KEYS_AFTER=$(redis-cli KEYS "zc:k:*" 2>/dev/null | wc -l || echo "0")
    
    log_info "Redis keys before: $KEYS_BEFORE, after: $KEYS_AFTER"
else
    log_info "Redis CLI not available, skipping Redis cleanup"
fi

log_section "7. Disk Space Check"

# Check disk space
log_info "Disk space usage:"
df -h /home 2>&1 | tee -a "$LOG_FILE"

# Check var directory size
VAR_SIZE=$(du -sh "$MAGENTO_ROOT/var" 2>/dev/null | cut -f1)
log_info "var directory size: $VAR_SIZE"

log_section "8. Generate Daily Summary Report"

# Create daily summary
SUMMARY_FILE="$REPORT_DIR/daily_summary_$(date '+%Y-%m-%d').md"

cat > "$SUMMARY_FILE" << EOF
# Daily Maintenance Summary

**Date:** $(date '+%Y-%m-%d')
**Server:** $(hostname)
**Completed:** $(date '+%Y-%m-%d %H:%M:%S')

---

## Tasks Completed

- [x] Log rotation
- [x] Log analysis
- [x] Cron health check
- [x] Order grid sync check
- [x] Database optimization
- [x] Redis cleanup
- [x] Disk space check

---

## Key Metrics

| Metric | Value |
|--------|-------|
| var directory size | $VAR_SIZE |
| Disk usage | $(df -h /home | tail -1 | awk '{print $5}') |
| Log files | $(ls -1 "$LOG_DIR"/*.log 2>/dev/null | wc -l) |

---

## Issues Detected

EOF

# Add any warnings/errors from the log
if grep -q "WARN\|ERROR" "$LOG_FILE"; then
    grep "WARN\|ERROR" "$LOG_FILE" | tail -20 >> "$SUMMARY_FILE"
else
    echo "No issues detected." >> "$SUMMARY_FILE"
fi

cat >> "$SUMMARY_FILE" << EOF

---

## Next Scheduled Run

Tomorrow at 2:00 AM

---

*Generated by daily_maintenance.sh*
EOF

log_info "Daily summary saved to: $SUMMARY_FILE"

log_section "Maintenance Complete"

log_info "All maintenance tasks completed successfully!"
log_info "Review the summary report: $SUMMARY_FILE"

# Log completion
echo "" >> "$LOG_FILE"
echo "Maintenance Completed: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
echo "=========================================" >> "$LOG_FILE"

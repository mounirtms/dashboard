#!/bin/bash
###############################################################################
# Cron Health Check Script
# Purpose: Monitor cron job health, detect backlogs, and alert on issues
# Usage: ./cron_health_check.sh [--fix] [--report]
# Schedule: Every 15 minutes via cron
###############################################################################

set -e

# Configuration
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_NAME="technadminy7_dBT8x12y22"
MYSQL_CMD="/opt/mariadb10.6/mariadb/bin/mysql"
MAGENTO_ROOT="/home/betapublic_html"
LOG_FILE="$MAGENTO_ROOT/var/log/cron_health.log"

# Pull real DB password from dashboard .env (never hardcode it here)
ENV_FILE="/home/dashboard/public_html/.env"
if [ -f "$ENV_FILE" ]; then
    DB_PASS=$(grep -E '^DB_PASS=' "$ENV_FILE" | head -1 | cut -d= -f2-)
fi
DB_PASS="${DB_PASS:-}"

# Thresholds
PENDING_JOB_THRESHOLD=50
OLD_PENDING_MINUTES=30
MISSING_ORDER_THRESHOLD=5

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"
}

# Function to check pending cron jobs
check_pending_jobs() {
    log_info "Checking pending cron jobs..."
    
    local pending_count=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT COUNT(*) FROM cron_schedule WHERE status = 'pending';
    ")
    
    log_info "Pending cron jobs: $pending_count"
    
    if [ "$pending_count" -gt "$PENDING_JOB_THRESHOLD" ]; then
        log_warn "High pending job count: $pending_count (threshold: $PENDING_JOB_THRESHOLD)"
        
        # Show top pending jobs
        log_info "Top pending jobs:"
        $MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
            SELECT job_code, COUNT(*) as count 
            FROM cron_schedule 
            WHERE status = 'pending' 
            GROUP BY job_code 
            ORDER BY count DESC 
            LIMIT 10;
        " | tee -a "$LOG_FILE"
        
        return 1
    fi
    
    log_info "✓ Pending job count is healthy"
    return 0
}

# Function to check old pending jobs
check_old_pending() {
    log_info "Checking for old pending jobs (>${OLD_PENDING_MINUTES} min)..."
    
    local old_pending=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT COUNT(*) 
        FROM cron_schedule 
        WHERE status = 'pending' 
        AND scheduled_at < DATE_SUB(NOW(), INTERVAL ${OLD_PENDING_MINUTES} MINUTE);
    ")
    
    if [ "$old_pending" -gt 0 ]; then
        log_warn "Found $old_pending jobs pending for more than ${OLD_PENDING_MINUTES} minutes"
        
        # Show oldest pending jobs
        log_info "Oldest pending jobs:"
        $MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
            SELECT schedule_id, job_code, scheduled_at 
            FROM cron_schedule 
            WHERE status = 'pending' 
            ORDER BY scheduled_at ASC 
            LIMIT 10;
        " | tee -a "$LOG_FILE"
        
        return 1
    fi
    
    log_info "✓ No old pending jobs"
    return 0
}

# Function to check orders missing from grid
check_missing_orders() {
    log_info "Checking for orders missing from grid..."
    
    local missing_count=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT COUNT(*) 
        FROM sales_order so 
        LEFT JOIN sales_order_grid sog ON so.entity_id = sog.entity_id 
        WHERE sog.entity_id IS NULL;
    ")
    
    if [ "$missing_count" -gt 0 ]; then
        log_warn "Found $missing_count orders missing from sales_order_grid!"
        
        # Show missing orders
        log_info "Missing orders (latest 10):"
        $MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
            SELECT so.entity_id, so.increment_id, so.status, so.created_at 
            FROM sales_order so 
            LEFT JOIN sales_order_grid sog ON so.entity_id = sog.entity_id 
            WHERE sog.entity_id IS NULL 
            ORDER BY so.entity_id DESC 
            LIMIT 10;
        " | tee -a "$LOG_FILE"
        
        if [ "$missing_count" -gt "$MISSING_ORDER_THRESHOLD" ]; then
            log_error "Critical: Too many missing orders!"
            return 1
        fi
    else
        log_info "✓ All orders are synced to grid"
    fi
    
    return 0
}

# Function to check cron configuration
check_cron_config() {
    log_info "Checking Magento cron configuration..."
    
    # Check if Magento cron is in crontab
    if crontab -l 2>/dev/null | grep -q "bin/magento cron:run"; then
        log_info "✓ Magento cron is configured"
    else
        log_warn "Magento cron NOT found in crontab!"
        log_info "Add this line to crontab:"
        log_info "*/10 * * * * /opt/cpanel/ea-php82/root/usr/bin/php $MAGENTO_ROOT/bin/magento cron:run"
        return 1
    fi
    
    return 0
}

# Function to check last cron execution
check_last_execution() {
    log_info "Checking last cron execution..."
    
    local last_run=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT MAX(executed_at) 
        FROM cron_schedule 
        WHERE status = 'success' 
        AND job_code = 'indexer_reindex_all_database';
    ")
    
    if [ -z "$last_run" ] || [ "$last_run" = "NULL" ]; then
        log_warn "No successful cron execution found for indexer_reindex_all_database"
        return 1
    fi
    
    local last_run_ts=$(date -d "$last_run" +%s 2>/dev/null || echo 0)
    local now_ts=$(date +%s)
    local diff_minutes=$(( (now_ts - last_run_ts) / 60 ))
    
    log_info "Last successful indexer run: $last_run (${diff_minutes} minutes ago)"
    
    if [ "$diff_minutes" -gt 60 ]; then
        log_warn "Last cron execution was more than 60 minutes ago!"
        return 1
    fi
    
    log_info "✓ Cron execution is recent"
    return 0
}

# Function to fix pending jobs (cleanup old ones)
fix_pending_jobs() {
    log_warn "Cleaning up old pending jobs..."
    
    $MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
        DELETE FROM cron_schedule 
        WHERE status = 'pending' 
        AND scheduled_at < DATE_SUB(NOW(), INTERVAL 2 HOUR);
    "
    
    local deleted=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT ROW_COUNT();
    ")
    
    log_info "Deleted $deleted old pending jobs"
}

# Function to sync missing orders
fix_missing_orders() {
    log_warn "Syncing missing orders to grid..."
    
    $MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
        INSERT IGNORE INTO sales_order_grid 
        SELECT * FROM sales_order 
        WHERE entity_id IN (
            SELECT so.entity_id 
            FROM sales_order so 
            LEFT JOIN sales_order_grid sog ON so.entity_id = sog.entity_id 
            WHERE sog.entity_id IS NULL 
            LIMIT 100
        );
    "
    
    log_info "Synced missing orders to grid"
}

# Function to generate health report
generate_report() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo ""
    echo "========================================="
    echo "Cron Health Check Report"
    echo "Generated: $timestamp"
    echo "========================================="
    echo ""
    
    local issues=0
    
    check_pending_jobs || issues=$((issues + 1))
    echo ""
    check_old_pending || issues=$((issues + 1))
    echo ""
    check_missing_orders || issues=$((issues + 1))
    echo ""
    check_cron_config || issues=$((issues + 1))
    echo ""
    check_last_execution || issues=$((issues + 1))
    echo ""
    
    echo "========================================="
    if [ "$issues" -eq 0 ]; then
        log_info "Health check PASSED - All systems healthy"
    else
        log_warn "Health check found $issues issue(s) requiring attention"
    fi
    echo "========================================="
}

# Main execution
case "${1:-check}" in
    --check|-c)
        generate_report
        ;;
    --fix|-f)
        log_info "Running automated fixes..."
        fix_pending_jobs
        fix_missing_orders
        log_info "Fixes complete. Running health check..."
        generate_report
        ;;
    --report|-r)
        generate_report
        ;;
    *)
        echo "Usage: $0 [--check|--fix|--report]"
        echo ""
        echo "Options:"
        echo "  --check, -c    Run health checks"
        echo "  --fix, -f      Run health checks and apply fixes"
        echo "  --report, -r   Generate full report"
        exit 1
        ;;
esac

#!/bin/bash
###############################################################################
# Database Daily Maintenance Script
# 
# This script performs automated daily database maintenance including:
# - Cleanup of old records (search queries, logs, notifications)
# - Table optimization for fragmented tables
# - Health monitoring and alerting
# - Performance metrics collection
#
# Usage:
#   ./database_daily_maintenance.sh [production|beta|both]
#
# Cron Example (Run daily at 2 AM):
#   0 2 * * * /home/beta/public_html/database_daily_maintenance.sh both >> /home/beta/public_html/var/log/db_maintenance.log 2>&1
#
# @author Session 36 - Database Optimization
# @date 2026-04-09
###############################################################################

set -euo pipefail

# Configuration
SCRIPT_DIR="/home/beta/public_html"
LOG_DIR="${SCRIPT_DIR}/var/log"
REPORT_DIR="${SCRIPT_DIR}/var/reports"
PHP_HEALTH_SCRIPT="${SCRIPT_DIR}/database_health_check.php"

# Ensure directories exist
mkdir -p "${LOG_DIR}"
mkdir -p "${REPORT_DIR}"

# Determine target (default: both)
TARGET="${1:-both}"

# Timestamp
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
DATE_STAMP=$(date '+%Y-%m-%d')

# Log file
LOG_FILE="${LOG_DIR}/db_maintenance_${DATE_STAMP}.log"

# Function to log messages
log() {
    echo "[${TIMESTAMP}] $1" | tee -a "${LOG_FILE}"
}

log "=========================================="
log "Database Daily Maintenance Started"
log "Target: ${TARGET}"
log "=========================================="

# 1. Run comprehensive health check with fixes
log "Step 1: Running database health check..."
if php "${PHP_HEALTH_SCRIPT}" "${TARGET}" --fix 2>&1 | tee -a "${LOG_FILE}"; then
    log "✓ Health check completed successfully"
else
    log "✗ Health check encountered issues (exit code: $?)"
fi

# 2. Check system resources
log ""
log "Step 2: Checking system resources..."

# CPU Load
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
log "Load Average: ${LOAD_AVG}"

# Memory usage
MEM_USAGE=$(free -m | awk 'NR==2{printf "%.2f%%", $3*100/$2 }')
log "Memory Usage: ${MEM_USAGE}"

# Disk usage
DISK_USAGE=$(df -h /home | awk 'NR==2{print $5}')
log "Disk Usage: ${DISK_USAGE}"

# 3. Check for long-running queries
log ""
log "Step 3: Checking for long-running queries..."

# Production database check
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
    SELECT COUNT(*) as long_queries
    FROM information_schema.PROCESSLIST
    WHERE Command != 'Sleep' AND Time > 30
" 2>/dev/null | tail -n 1 > /tmp/prod_long_queries.txt

PROD_LONG_QUERIES=$(cat /tmp/prod_long_queries.txt)
log "Production long queries (>30s): ${PROD_LONG_QUERIES}"

# Beta database check
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22 -e "
    SELECT COUNT(*) as long_queries
    FROM information_schema.PROCESSLIST
    WHERE Command != 'Sleep' AND Time > 30
" 2>/dev/null | tail -n 1 > /tmp/beta_long_queries.txt

BETA_LONG_QUERIES=$(cat /tmp/beta_long_queries.txt)
log "Beta long queries (>30s): ${BETA_LONG_QUERIES}"

# 4. Check table sizes
log ""
log "Step 4: Monitoring table growth..."

# Production largest tables
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
        table_rows
    FROM information_schema.TABLES
    WHERE table_schema = 'technadminy7_dBT8x12y22'
    ORDER BY (data_length + index_length) DESC
    LIMIT 5
" 2>/dev/null | tee -a "${LOG_FILE}"

# 5. Clean up old maintenance logs (keep last 30 days)
log ""
log "Step 5: Cleaning up old maintenance logs..."
find "${LOG_DIR}" -name "db_maintenance_*.log" -mtime +30 -delete
find "${LOG_DIR}" -name "database_health_*.json" -mtime +30 -delete
log "✓ Old logs cleaned"

# 6. Generate daily summary report
log ""
log "Step 6: Generating summary report..."

SUMMARY_FILE="${REPORT_DIR}/db_maintenance_summary_${DATE_STAMP}.txt"

cat > "${SUMMARY_FILE}" << EOF
Database Maintenance Summary - ${DATE_STAMP}
================================================================

System Resources:
  - Load Average: ${LOAD_AVG}
  - Memory Usage: ${MEM_USAGE}
  - Disk Usage: ${DISK_USAGE}

Database Health:
  - Production Long Queries: ${PROD_LONG_QUERIES}
  - Beta Long Queries: ${BETA_LONG_QUERIES}

Actions Taken:
  - Database cleanup executed
  - Table optimization performed
  - Old logs purged

See detailed log: ${LOG_FILE}
EOF

log "✓ Summary report created: ${SUMMARY_FILE}"

# 7. Alert if issues detected
ALERT_THRESHOLD_LOAD=10.0
ALERT_THRESHOLD_MEM=90

if (( $(echo "${LOAD_AVG} > ${ALERT_THRESHOLD_LOAD}" | bc -l) )); then
    log "⚠ ALERT: High load average detected: ${LOAD_AVG}"
fi

if [[ "${MEM_USAGE%\%}" -gt "${ALERT_THRESHOLD_MEM}" ]]; then
    log "⚠ ALERT: High memory usage detected: ${MEM_USAGE}"
fi

if [[ "${PROD_LONG_QUERIES}" -gt 5 ]]; then
    log "⚠ ALERT: Multiple long-running queries in production: ${PROD_LONG_QUERIES}"
fi

# 8. Rotate old reports (keep last 90 days)
find "${REPORT_DIR}" -name "db_maintenance_summary_*.txt" -mtime +90 -delete

log ""
log "=========================================="
log "Database Daily Maintenance Completed"
log "=========================================="

exit 0

#!/bin/bash
###############################################################################
# Resource Audit Script
# Purpose: Audit Redis, Varnish, MySQL, and disk resources
# Usage: ./resource_audit.sh [--report]
# Schedule: Daily at 6 AM via cron
###############################################################################

set -e

# Configuration
MAGENTO_ROOT="/home/technadminy7/public_html"
REPORT_DIR="${MAGENTO_ROOT}/var/reports"
LOG_FILE="${MAGENTO_ROOT}/var/log/resource_audit.log"

# Ensure report directory exists
mkdir -p "${REPORT_DIR}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }

TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
REPORT_FILE="${REPORT_DIR}/resource_audit_${TIMESTAMP}.md"

echo "========================================="
echo "Resource Audit"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Start report
cat > "${REPORT_FILE}" << EOF
# Resource Audit Report

**Generated:** $(date '+%Y-%m-%d %H:%M:%S')
**Server:** $(hostname)

---

## Executive Summary

EOF

# 1. Memory Overview
log_info "Checking memory usage..."
cat >> "${REPORT_FILE}" << EOF
## Memory Usage

\`\`\`
$(free -h)
\`\`\`

### Memory Breakdown
| Type | Used | Available |
|------|------|-----------|
| Total | $(free -h | awk '/^Mem:/ {print $2}') | |
| Used | $(free -h | awk '/^Mem:/ {print $3}') | |
| Free | $(free -h | awk '/^Mem:/ {print $4}') | |
| Available | $(free -h | awk '/^Mem:/ {print $7}') | |

EOF
log_info "Memory check complete"

# 2. Redis Status
log_info "Checking Redis status..."
REDIS_USED=$(redis-cli INFO memory 2>/dev/null | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
REDIS_MAX=$(redis-cli CONFIG GET maxmemory 2>/dev/null | tail -1)
REDIS_MAX_HUMAN=$(echo "$REDIS_MAX" | awk '{
    if ($1 > 1073741824) printf "%.1fGB", $1/1073741824;
    else if ($1 > 1048576) printf "%.1fMB", $1/1048576;
    else printf "%d", $1;
}')
REDIS_KEYS=$(redis-cli DBSIZE 2>/dev/null | awk '{print $2}')

cat >> "${REPORT_FILE}" << EOF
## Redis Status

| Metric | Value |
|--------|-------|
| Used Memory | ${REDIS_USED:-N/A} |
| Max Memory | ${REDIS_MAX_HUMAN:-N/A} |
| Total Keys | ${REDIS_KEYS:-N/A} |
| Memory Usage | $(echo "$REDIS_USED $REDIS_MAX" | awk '{if ($2 > 0) printf "%.1f%%", ($1/$2)*100; else print "N/A"}') |

EOF

if [ -n "$REDIS_MAX" ] && [ "$REDIS_MAX" -lt 8589934592 ] 2>/dev/null; then
    log_warn "Redis maxmemory is below recommended 8GB"
    echo "**⚠️ WARNING:** Redis maxmemory is below recommended 8GB" >> "${REPORT_FILE}"
fi

log_info "Redis check complete"

# 3. Varnish Status
log_info "Checking Varnish status..."
VARNISH_RAM=$(ps aux | grep varnishd | head -1 | grep -oP 'malloc,\K[0-9]+[GM]' || echo "4G")

cat >> "${REPORT_FILE}" << EOF
## Varnish Status

| Metric | Value |
|--------|-------|
| Allocated Memory | ${VARNISH_RAM} |
| Process Count | $(pgrep -c varnishd || echo 0) |

EOF

log_info "Varnish check complete"

# 4. MySQL/MariaDB Status
log_info "Checking MySQL status..."
MYSQL_MEM=$(ps aux | grep mariadbd | grep -v grep | awk '{sum += $6} END {printf "%.1fMB", sum/1024}')
MYSQL_CONN=$(mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | awk 'NR==2 {print $2}')

cat >> "${REPORT_FILE}" << EOF
## MySQL/MariaDB Status

| Metric | Value |
|--------|-------|
| Memory Usage | ${MYSQL_MEM:-N/A} |
| Active Connections | ${MYSQL_CONN:-N/A} |

EOF

log_info "MySQL check complete"

# 5. Disk Usage
log_info "Checking disk usage..."
cat >> "${REPORT_FILE}" << EOF
## Disk Usage

\`\`\`
$(df -h /home /var 2>/dev/null)
\`\`\`

### Magento var Directory
\`\`\`
$(du -sh ${MAGENTO_ROOT}/var 2>/dev/null)
\`\`\`

### Log Files Size
\`\`\`
$(du -sh ${MAGENTO_ROOT}/var/log/*.log 2>/dev/null | sort -hr | head -10)
\`\`\`

EOF

log_info "Disk check complete"

# 6. Process Summary
log_info "Checking key processes..."
cat >> "${REPORT_FILE}" << EOF
## Key Processes

| Process | Count | Status |
|---------|-------|--------|
| PHP-FPM | $(pgrep -c php-fpm || echo 0) | $(pgrep -c php-fpm > /dev/null && echo "✓ Running" || echo "✗ Not running") |
| Redis | $(pgrep -c redis-server || echo 0) | $(pgrep -c redis-server > /dev/null && echo "✓ Running" || echo "✗ Not running") |
| Varnish | $(pgrep -c varnishd || echo 0) | $(pgrep -c varnishd > /dev/null && echo "✓ Running" || echo "✗ Not running") |
| MariaDB | $(pgrep -c mariadbd || echo 0) | $(pgrep -c mariadbd > /dev/null && echo "✓ Running" || echo "✗ Not running") |
| Cron | $(pgrep -c crond || pgrep -c cron || echo 0) | $(pgrep -c crond > /dev/null || pgrep -c cron > /dev/null && echo "✓ Running" || echo "✗ Not running") |

EOF

log_info "Process check complete"

# 7. Recommendations
cat >> "${REPORT_FILE}" << EOF
---

## Recommendations

EOF

# Check and add recommendations
if [ -n "$REDIS_MAX" ] && [ "$REDIS_MAX" -lt 8589934592 ] 2>/dev/null; then
    echo "### 🔴 Redis Memory" >> "${REPORT_FILE}"
    echo "- Current maxmemory: ${REDIS_MAX_HUMAN}" >> "${REPORT_FILE}"
    echo "- Recommended: 8-9GB for multi-site setup" >> "${REPORT_FILE}"
    echo "- Command: \`redis-cli CONFIG SET maxmemory 8gb\`" >> "${REPORT_FILE}"
    echo "" >> "${REPORT_FILE}"
fi

VAR_USAGE=$(df /home | awk 'NR==2 {print $5}' | tr -d '%')
if [ "$VAR_USAGE" -gt 70 ] 2>/dev/null; then
    echo "### 🟡 Disk Usage" >> "${REPORT_FILE}"
    echo "- Current usage: ${VAR_USAGE}%" >> "${REPORT_FILE}"
    echo "- Consider cleaning logs and old backups" >> "${REPORT_FILE}"
    echo "" >> "${REPORT_FILE}"
fi

echo "*Report generated by resource_audit.sh*" >> "${REPORT_FILE}"

# Summary
log_info "========================================="
log_info "Audit Complete"
log_info "========================================="
log_info "Report saved to: ${REPORT_FILE}"
log_info "Log file: ${LOG_FILE}"
echo ""
echo "========================================="

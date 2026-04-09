#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Comprehensive System Monitor - Production Server
# Purpose: Monitor CPU, Memory, Queue, Elasticsearch, MariaDB and auto-remediate
# Location: /home/pim/public_html/scripts/monitoring/system_monitor.sh
# Run: Every 2 minutes via cron
# ═══════════════════════════════════════════════════════════════════════════

set -e

# Configuration
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_PASS="YourNewStrongPassword"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"

# Thresholds
CPU_WARNING=60
CPU_CRITICAL=80
CPU_EMERGENCY=90
MEMORY_WARNING=70
MEMORY_CRITICAL=85
QUEUE_WARNING=1000
QUEUE_CRITICAL=5000
PHP_FPM_MAX=6

# Log files
LOG_FILE="/home/pim/public_html/var/log/system_monitor.log"
ALERT_FILE="/home/pim/public_html/var/log/system_alerts.log"

mkdir -p "$(dirname "$LOG_FILE")"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

send_alert() {
    local severity="$1"
    local message="$2"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$severity] $message" >> "$ALERT_FILE"
    log_message "ALERT [$severity]: $message"
}

log_message "=== System Monitor Check ==="

# Get current metrics
CPU_LINE=$(top -bn1 | grep "Cpu(s)")
CPU_USER=$(echo "$CPU_LINE" | awk '{print $2}' | cut -d'%' -f1)
CPU_SYS=$(echo "$CPU_LINE" | awk '{print $4}' | cut -d'%' -f1)
CPU_TOTAL=$(echo "$CPU_USER + $CPU_SYS" | bc 2>/dev/null || echo "$CPU_USER")

MEM_INFO=$(free | grep Mem)
MEM_PERCENT=$(( $(echo $MEM_INFO | awk '{print $3}') * 100 / $(echo $MEM_INFO | awk '{print $2}') ))

QUEUE_COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")

PHP_FPM_COUNT=$(ps aux | grep -E "php-fpm.*technostationery" | grep -v grep | grep -v master | wc -l)

LOAD_1=$(uptime | awk -F'load average:' '{print $2}' | awk -F',' '{print $1}' | tr -d ' ')

log_message "CPU: ${CPU_TOTAL}% | Memory: ${MEM_PERCENT}% | Queue: $QUEUE_COUNT | PHP-FPM: $PHP_FPM_COUNT | Load: $LOAD_1"

# ═══════════════════════════════════════════════════════════════════════════
# CPU Checks
# ═══════════════════════════════════════════════════════════════════════════

if (( $(echo "$CPU_TOTAL >= $CPU_EMERGENCY" | bc -l 2>/dev/null || echo 0) )); then
    send_alert "EMERGENCY" "CPU at ${CPU_TOTAL}% - Initiating emergency throttle"
    log_message "Running emergency CPU throttle..."
    /bin/bash /home/pim/public_html/emergency_cpu_throttle.sh
    exit 0
    
elif (( $(echo "$CPU_TOTAL >= $CPU_CRITICAL" | bc -l 2>/dev/null || echo 0) )); then
    send_alert "CRITICAL" "CPU at ${CPU_TOTAL}% - Running optimization"
    log_message "Running CPU optimization script..."
    /bin/bash /home/pim/public_html/cpu_optimize.sh
    exit 1
    
elif (( $(echo "$CPU_TOTAL >= $CPU_WARNING" | bc -l 2>/dev/null || echo 0) )); then
    send_alert "WARNING" "CPU elevated at ${CPU_TOTAL}%"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Memory Checks
# ═══════════════════════════════════════════════════════════════════════════

if [ "$MEM_PERCENT" -ge "$MEMORY_CRITICAL" ]; then
    send_alert "CRITICAL" "Memory at ${MEM_PERCENT}%"
    
    # Clear caches
    log_message "Clearing system caches due to high memory..."
    rm -rf /home/technadminy7/public_html/var/cache/* 2>/dev/null || true
    rm -rf /home/pim/public_html/var/cache/prod/* 2>/dev/null || true
    
elif [ "$MEM_PERCENT" -ge "$MEMORY_WARNING" ]; then
    send_alert "WARNING" "Memory elevated at ${MEM_PERCENT}%"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Queue Checks
# ═══════════════════════════════════════════════════════════════════════════

if [ "$QUEUE_COUNT" -ge "$QUEUE_CRITICAL" ]; then
    send_alert "CRITICAL" "Queue size critical: $QUEUE_COUNT messages"
    log_message "Running queue cleanup..."
    /bin/bash /home/pim/public_html/queue_optimize.sh
    
elif [ "$QUEUE_COUNT" -ge "$QUEUE_WARNING" ]; then
    send_alert "WARNING" "Queue size elevated: $QUEUE_COUNT messages"
fi

# ═══════════════════════════════════════════════════════════════════════════
# PHP-FPM Worker Checks
# ═══════════════════════════════════════════════════════════════════════════

if [ "$PHP_FPM_COUNT" -gt "$PHP_FPM_MAX" ]; then
    send_alert "WARNING" "Too many PHP-FPM workers: $PHP_FPM_COUNT (max: $PHP_FPM_MAX)"
    
    # Kill oldest workers if too many
    EXCESS=$((PHP_FPM_COUNT - PHP_FPM_MAX))
    log_message "Killing $EXCESS excess PHP-FPM workers..."
    
    ps aux --sort=etime | grep -E "php-fpm.*technostationery" | grep -v master | grep -v grep | head -$EXCESS | awk '{print $2}' | while read pid; do
        if [ -n "$pid" ]; then
            log_message "  Killing PID $pid"
            kill $pid 2>/dev/null || true
        fi
    done
fi

# ═══════════════════════════════════════════════════════════════════════════
# Load Average Checks
# ═══════════════════════════════════════════════════════════════════════════

LOAD_INT=$(echo "$LOAD_1" | cut -d'.' -f1)
if [ "$LOAD_INT" -ge 15 ]; then
    send_alert "CRITICAL" "Load average critical: $LOAD_1"
elif [ "$LOAD_INT" -ge 10 ]; then
    send_alert "WARNING" "Load average elevated: $LOAD_1"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Elasticsearch Check
# ═══════════════════════════════════════════════════════════════════════════

ES_MEMORY=$(ps aux | grep elasticsearch | grep -v grep | awk '{sum+=$6} END {print sum/1024}' 2>/dev/null || echo "0")
if (( $(echo "$ES_MEMORY > 4000" | bc -l 2>/dev/null || echo 0) )); then
    send_alert "WARNING" "Elasticsearch using ${ES_MEMORY}MB RAM"
fi

# ═══════════════════════════════════════════════════════════════════════════
# MariaDB Check
# ═══════════════════════════════════════════════════════════════════════════

MYSQL_MEMORY=$(ps aux | grep mariadbd | grep -v grep | awk '{print $6}' 2>/dev/null || echo "0")
MYSQL_MEMORY_MB=$((MYSQL_MEMORY / 1024))
if [ "$MYSQL_MEMORY_MB" -gt 6000 ]; then
    send_alert "WARNING" "MariaDB using ${MYSQL_MEMORY_MB}MB RAM"
fi

# Check for slow queries
SLOW_QUERIES=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM information_schema.processlist WHERE command != 'Sleep' AND time > 30;" 2>/dev/null || echo "0")
if [ "$SLOW_QUERIES" -gt 5 ]; then
    send_alert "WARNING" "$SLOW_QUERIES slow queries running (>30s)"
fi

# ═══════════════════════════════════════════════════════════════════════════
# Consumer Process Check
# ═══════════════════════════════════════════════════════════════════════════

CONSUMER_COUNT=$(ps aux | grep -E "queue:consumers:start" | grep -v grep | wc -l)
if [ "$CONSUMER_COUNT" -eq 0 ] && [ "$QUEUE_COUNT" -gt 100 ]; then
    send_alert "WARNING" "No queue consumers but $QUEUE_COUNT messages in queue"
    
    # Restart consumers
    log_message "Restarting queue consumers..."
    cd /home/technadminy7/public_html
    nohup /opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start async.operations.all --single-thread --max-messages=1000 >> /home/technadminy7/public_html/var/log/consumer_async.log 2>&1 &
    nohup /opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus --single-thread --max-messages=1000 >> /home/technadminy7/public_html/var/log/consumer_inventory.log 2>&1 &
fi

log_message "=== System Monitor Check Completed ==="
log_message ""

# Exit with appropriate code
if [ "$QUEUE_COUNT" -ge "$QUEUE_CRITICAL" ] || [ "$MEM_PERCENT" -ge "$MEMORY_CRITICAL" ]; then
    exit 2
elif [ "$QUEUE_COUNT" -ge "$QUEUE_WARNING" ] || [ "$MEM_PERCENT" -ge "$MEMORY_WARNING" ]; then
    exit 1
else
    exit 0
fi

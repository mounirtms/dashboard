#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# CPU Optimization Script - Production Server
# Purpose: Optimize CPU usage by tuning services and cleaning resources
# Location: /home/pim/public_html/cpu_optimize.sh
# Run: As root or via cron
# ═══════════════════════════════════════════════════════════════════════════

set -e

# Configuration
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"

# Pull real DB password from dashboard .env (never hardcode it here)
ENV_FILE="/home/dashboard/public_html/.env"
if [ -f "$ENV_FILE" ]; then
    MYSQL_PASS=$(grep -E '^DB_PASS=' "$ENV_FILE" | head -1 | cut -d= -f2-)
fi
MYSQL_PASS="${MYSQL_PASS:-}"

LOG_FILE="/home/pim/public_html/var/log/cpu_optimize.log"
LOG_DIR="/home/pim/public_html/var/log"

# Ensure log directory exists
mkdir -p "$LOG_DIR"
chown pim:pim "$LOG_DIR" 2>/dev/null || true

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to execute MySQL command
mysql_cmd() {
    $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" -e "$1" 2>/dev/null
}

log_message "=== CPU Optimization Started ==="

# ═══════════════════════════════════════════════════════════════════════════
# 1. Check and Kill Stuck PHP-FPM Processes
# ═══════════════════════════════════════════════════════════════════════════

log_message "Checking for stuck PHP-FPM processes..."

# Find PHP-FPM processes running longer than 30 minutes or using too much CPU
STUCK_PIDS=$(ps aux | awk '$8 ~ /R/ && $3 > 50 && /php-fpm/ && !/master/ {print $2}' || true)

if [ -n "$STUCK_PIDS" ]; then
    log_message "WARNING: Found stuck/high-CPU PHP-FPM processes:"
    echo "$STUCK_PIDS" | while read pid; do
        if [ -n "$pid" ]; then
            CPU=$(ps -p $pid -o %cpu= 2>/dev/null || echo "0")
            TIME=$(ps -p $pid -o etime= 2>/dev/null || echo "unknown")
            log_message "  PID $pid - CPU: ${CPU}% - Time: $TIME"
            
            # Only kill if CPU > 80% for extended time
            if (( $(echo "$CPU > 80" | bc -l 2>/dev/null || echo 0) )); then
                log_message "  Killing high-CPU process $pid"
                kill $pid 2>/dev/null || true
            fi
        fi
    done
fi

# ═══════════════════════════════════════════════════════════════════════════
# 2. Clean Magento Queue (if too large)
# ═══════════════════════════════════════════════════════════════════════════

QUEUE_COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")
log_message "Magento queue_message count: $QUEUE_COUNT"

if [ "$QUEUE_COUNT" -gt 5000 ]; then
    log_message "WARNING: Queue size critically high ($QUEUE_COUNT), cleaning..."
    mysql_cmd "SET FOREIGN_KEY_CHECKS=0;"
    mysql_cmd "DELETE FROM technadminy7_dBT8x12y22.queue_message_status WHERE message_id IN (SELECT id FROM technadminy7_dBT8x12y22.queue_message);"
    mysql_cmd "DELETE FROM technadminy7_dBT8x12y22.queue_message;"
    mysql_cmd "SET FOREIGN_KEY_CHECKS=1;"
    log_message "Queue cleaned"
fi

# ═══════════════════════════════════════════════════════════════════════════
# 3. Optimize MariaDB Performance
# ═══════════════════════════════════════════════════════════════════════════

log_message "Optimizing MariaDB..."

# Kill long-running queries (> 60 seconds)
log_message "Checking for long-running queries..."
mysql_cmd "SET @old_time = @@long_query_time; SET GLOBAL long_query_time = 60;"
SLOW_QUERIES=$(mysql_cmd "SELECT id, time, state, info FROM information_schema.processlist WHERE command != 'Sleep' AND time > 60 AND user NOT IN ('root', 'system_user');" 2>/dev/null || true)

if [ -n "$SLOW_QUERIES" ]; then
    log_message "Found slow queries:"
    echo "$SLOW_QUERIES" | while read line; do
        log_message "  $line"
    done
fi

# Clean expired sessions and temporary tables
mysql_cmd "FLUSH TABLES;" 2>/dev/null || true
log_message "MariaDB tables flushed"

# Optimize key buffer
mysql_cmd "FLUSH STATUS;" 2>/dev/null || true

# ═══════════════════════════════════════════════════════════════════════════
# 4. Clear PHP OPcache
# ═══════════════════════════════════════════════════════════════════════════

log_message "Clearing PHP OPcache..."

# Create PHP script to clear OPcache
OPCACHE_SCRIPT="/tmp/clear_opcache.php"
cat > "$OPCACHE_SCRIPT" << 'PHPEOF'
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully\n";
} else {
    echo "OPcache not enabled\n";
}
PHPEOF

# Run for both PHP versions
if [ -f /opt/cpanel/ea-php82/root/usr/bin/php ]; then
    /opt/cpanel/ea-php82/root/usr/bin/php "$OPCACHE_SCRIPT" 2>/dev/null | while read line; do
        log_message "  PHP 8.2: $line"
    done
fi

if [ -f /opt/cpanel/ea-php83/root/usr/bin/php ]; then
    /opt/cpanel/ea-php83/root/usr/bin/php "$OPCACHE_SCRIPT" 2>/dev/null | while read line; do
        log_message "  PHP 8.3: $line"
    done
fi

rm -f "$OPCACHE_SCRIPT"

# ═══════════════════════════════════════════════════════════════════════════
# 5. Clear System Cache
# ═══════════════════════════════════════════════════════════════════════════

log_message "Clearing system caches..."

# Clear Magento cache
if [ -d /home/technadminy7/public_html/var/cache ]; then
    MAGENTO_CACHE_SIZE=$(du -sm /home/technadminy7/public_html/var/cache 2>/dev/null | awk '{print $1}' || echo "0")
    log_message "Magento cache size: ${MAGENTO_CACHE_SIZE}MB"
    
    if [ "$MAGENTO_CACHE_SIZE" -gt 500 ]; then
        log_message "Clearing old Magento cache..."
        find /home/technadminy7/public_html/var/cache -type f -mmin +60 -delete 2>/dev/null || true
        find /home/technadminy7/public_html/var/page_cache -type f -mmin +60 -delete 2>/dev/null || true
    fi
fi

# Clear PIM cache
if [ -d /home/pim/public_html/var/cache ]; then
    PIM_CACHE_SIZE=$(du -sm /home/pim/public_html/var/cache 2>/dev/null | awk '{print $1}' || echo "0")
    log_message "PIM cache size: ${PIM_CACHE_SIZE}MB"
    
    if [ "$PIM_CACHE_SIZE" -gt 500 ]; then
        log_message "Clearing old PIM cache..."
        find /home/pim/public_html/var/cache/prod -type f -mmin +60 -delete 2>/dev/null || true
    fi
fi

# Clear /tmp
TMP_SIZE=$(du -sm /tmp 2>/dev/null | awk '{print $1}' || echo "0")
if [ "$TMP_SIZE" -gt 1000 ]; then
    log_message "Cleaning /tmp directory (${TMP_SIZE}MB)..."
    find /tmp -type f -atime +1 -delete 2>/dev/null || true
fi

# ═══════════════════════════════════════════════════════════════════════════
# 6. Restart Stuck Queue Consumers
# ═══════════════════════════════════════════════════════════════════════════

log_message "Checking queue consumers..."

# Find consumers running longer than 2 hours
CONSUMER_PIDS=$(ps aux | grep -E "queue:consumers:start" | grep -v grep | awk '$10 ~ /^[0-9]+:[0-9]+:[0-9]+$/ || $10 ~ /^[0-9]+:[0-9]+$/ {
    split($10, t, ":");
    if (length(t) == 3) {
        seconds = t[1]*3600 + t[2]*60 + t[3];
    } else {
        seconds = t[1]*60 + t[2];
    }
    if (seconds > 7200) {
        print $2;
    }
}' || true)

if [ -n "$CONSUMER_PIDS" ]; then
    log_message "Restarting long-running consumers..."
    echo "$CONSUMER_PIDS" | while read pid; do
        if [ -n "$pid" ]; then
            log_message "  Killing consumer PID $pid"
            kill $pid 2>/dev/null || true
        fi
    done
    
    # Restart consumers
    sleep 2
    log_message "Restarting Magento queue consumers..."
    cd /home/technadminy7/public_html
    
    # Start async operations consumer
    nohup /opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start async.operations.all --single-thread --max-messages=1000 >> /home/technadminy7/public_html/var/log/consumer_async.log 2>&1 &
    log_message "  Started async.operations.all consumer"
    
    # Start inventory consumer
    nohup /opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus --single-thread --max-messages=1000 >> /home/technadminy7/public_html/var/log/consumer_inventory.log 2>&1 &
    log_message "  Started inventory.reservations consumer"
fi

# ═══════════════════════════════════════════════════════════════════════════
# 7. Elasticsearch Optimization (if running)
# ═══════════════════════════════════════════════════════════════════════════

if pgrep -x "java" | xargs ps -p 2>/dev/null | grep -q elasticsearch; then
    log_message "Elasticsearch is running, optimizing..."
    
    # Force segment merge to reduce memory
    curl -X POST "localhost:9200/_forcemerge?max_num_segments=1&only_expunge_deletes=true" 2>/dev/null || true
    log_message "  Forced segment merge"
    
    # Clear field data cache
    curl -X POST "localhost:9200/_cache/clear?field_data=true" 2>/dev/null || true
    log_message "  Cleared field data cache"
fi

# ═══════════════════════════════════════════════════════════════════════════
# 8. Final Status
# ═══════════════════════════════════════════════════════════════════════════

log_message "=== Final Status ==="

# CPU Usage
CPU_LINE=$(top -bn1 | grep "Cpu(s)")
CPU_USER=$(echo "$CPU_LINE" | awk '{print $2}' | cut -d'%' -f1)
CPU_SYS=$(echo "$CPU_LINE" | awk '{print $4}' | cut -d'%' -f1)
CPU_IDLE=$(echo "$CPU_LINE" | awk '{print $8}' | cut -d'%' -f1)
log_message "CPU: User=${CPU_USER}%, System=${CPU_SYS}%, Idle=${CPU_IDLE}%"

# Memory Usage
MEM_INFO=$(free | grep Mem)
MEM_PERCENT=$(( $(echo $MEM_INFO | awk '{print $3}') * 100 / $(echo $MEM_INFO | awk '{print $2}') ))
log_message "Memory: ${MEM_PERCENT}% used"

# Queue Size
FINAL_QUEUE=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")
log_message "Queue messages: $FINAL_QUEUE"

# Active PHP-FPM workers
PHP_FPM_COUNT=$(ps aux | grep -E "php-fpm.*technostationery" | grep -v grep | wc -l)
log_message "Active PHP-FPM workers: $PHP_FPM_COUNT"

log_message "=== CPU Optimization Completed ==="
log_message ""

exit 0

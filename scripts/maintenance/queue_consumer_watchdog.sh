#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Queue Consumer Watchdog - Magento 2
# Purpose: Monitor and restart stuck queue consumers
# Location: /home/technadminy7/public_html/scripts/queue_consumer_watchdog.sh
# ═══════════════════════════════════════════════════════════════════════════

set -e

MAGENTO_ROOT="/home/technadminy7/public_html"
PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"
LOG_FILE="/home/technadminy7/public_html/var/log/queue_watchdog.log"

# Thresholds
MAX_CONSUMER_AGE_HOURS=2    # Restart consumers older than this
MAX_CONSUMERS_PER_QUEUE=3   # Maximum consumers per queue type

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_message "=== Queue Consumer Watchdog Check ==="

# Check for stuck consumers (running too long)
log_message "Checking for long-running consumers..."
ps aux | grep -E "queue:consumers:start" | grep -v grep | while read line; do
    PID=$(echo $line | awk '{print $2}')
    TIME=$(echo $line | awk '{print $10}')
    
    # Convert TIME to seconds (format: HH:MM:SS or MM:SS)
    if [[ "$TIME" =~ ^([0-9]+):([0-9]+):([0-9]+)$ ]]; then
        SECONDS=$((${BASH_REMATCH[1]} * 3600 + ${BASH_REMATCH[2]} * 60 + ${BASH_REMATCH[3]}))
    elif [[ "$TIME" =~ ^([0-9]+):([0-9]+)$ ]]; then
        SECONDS=$((${BASH_REMATCH[1]} * 60 + ${BASH_REMATCH[2]}))
    else
        SECONDS=0
    fi
    
    HOURS=$((SECONDS / 3600))
    
    if [ "$HOURS" -ge "$MAX_CONSUMER_AGE_HOURS" ]; then
        log_message "WARNING: Consumer PID $PID running for ${HOURS}h - restarting..."
        kill $PID 2>/dev/null || true
        log_message "Killed consumer PID $PID"
    fi
done

# Count consumers per queue type
log_message "Consumer count by queue:"
ps aux | grep -E "queue:consumers:start" | grep -v grep | awk '{print $NF}' | sort | uniq -c | while read count queue; do
    log_message "  $queue: $count processes"
    
    if [ "$count" -gt "$MAX_CONSUMERS_PER_QUEUE" ]; then
        log_message "WARNING: Too many consumers for $queue ($count > $MAX_CONSUMERS_PER_QUEUE)"
        # Kill excess consumers (keep only MAX_CONSUMERS_PER_QUEUE)
        EXCESS=$((count - MAX_CONSUMERS_PER_QUEUE))
        ps aux | grep "$queue" | grep -v grep | head -n $EXCESS | awk '{print $2}' | while read pid; do
            log_message "Killing excess consumer PID $pid"
            kill $pid 2>/dev/null || true
        done
    fi
done

# Check for zombie processes
ZOMBIES=$(ps aux | grep -E "defunct|zombie" | grep -v grep | wc -l)
if [ "$ZOMBIES" -gt 0 ]; then
    log_message "WARNING: Found $ZOMBIE zombie processes"
fi

# Check if consumers are making progress (queue size should decrease)
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_PASS="YourNewStrongPassword"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"

QUEUE_COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")

if [ "$QUEUE_COUNT" -gt 1000 ]; then
    log_message "WARNING: Queue size is $QUEUE_COUNT - consumers may be stuck"
    # Consider restarting all consumers
    # log_message "Restarting all consumers..."
    # pkill -f "queue:consumers:start" || true
fi

log_message "=== Queue Consumer Watchdog Completed ==="
log_message ""

exit 0

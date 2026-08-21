#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Queue Monitor Script - Magento 2
# Purpose: Monitor queue sizes and alert before they become critical
# Location: /home/dashboard/public_html/scripts/monitoring/queue_monitor.sh
# ═══════════════════════════════════════════════════════════════════════════

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

# Thresholds (unified with other monitoring scripts)
QUEUE_WARNING_THRESHOLD=1000    # Warn when queue exceeds this
QUEUE_CRITICAL_THRESHOLD=5000   # Critical alert when exceeds this
CPU_WARNING_THRESHOLD=60        # CPU usage warning (%)
CPU_CRITICAL_THRESHOLD=80       # CPU usage critical (%)
MEMORY_WARNING_THRESHOLD=70     # Memory usage warning (%)

# Log file
LOG_FILE="/home/dashboard/public_html/logs/queue_monitor.log"
ALERT_FILE="/home/dashboard/public_html/logs/queue_alerts.log"

# Telegram alert integration
PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"
ALERT_CRON_PHP="/home/dashboard/public_html/api/telegram/alert_cron.php"

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to send alert
send_alert() {
    local severity="$1"
    local message="$2"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$severity] $message" >> "$ALERT_FILE"
    log_message "ALERT [$severity]: $message"

    # Send Telegram notification for all alerts (with dedup handled by PHP)
    send_telegram_alert "$severity" "$message"
}

# Function to send Telegram alert
send_telegram_alert() {
    local severity="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    local alert_key="queuemon_$(echo "$message" | md5sum | cut -d' ' -f1)"

    # Run PHP in background to avoid blocking
    $PHP_BIN "$ALERT_CRON_PHP" --direct-alert --key="$alert_key" --severity="$severity" --message="$message" --time="$timestamp" >> /dev/null 2>&1 &
}

# Function to execute MySQL query
mysql_query() {
    $MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "$1" 2>/dev/null
}

# ═══════════════════════════════════════════════════════════════════════════
# Main Monitoring Logic
# ═══════════════════════════════════════════════════════════════════════════

log_message "=== Queue Monitor Check Started ==="

# Check queue_message table
QUEUE_COUNT=$(mysql_query "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")
log_message "Queue messages: $QUEUE_COUNT"

# Check queue_message_status
STATUS_COUNT=$(mysql_query "SELECT COUNT(*) FROM queue_message_status;" 2>/dev/null || echo "0")
log_message "Queue status records: $STATUS_COUNT"

# Check queue distribution by topic
log_message "Queue distribution by topic:"
mysql_query "SELECT topic_name, COUNT(*) as count FROM queue_message GROUP BY topic_name ORDER BY count DESC LIMIT 5;" 2>/dev/null | while read line; do
    log_message "  $line"
done

# Check oldest queue message age (if any exist)
if [ "$QUEUE_COUNT" -gt 0 ]; then
    OLDEST_MESSAGE=$(mysql_query "SELECT MIN(id) FROM queue_message;" 2>/dev/null || echo "N/A")
    log_message "Oldest message ID: $OLDEST_MESSAGE"
fi

# Check consumer processes
CONSUMER_COUNT=$(ps aux | grep -E "queue:consumers:start" | grep -v grep | wc -l)
log_message "Active consumer processes: $CONSUMER_COUNT"

# Check CPU usage
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1 | cut -d'.' -f1)
if [ -n "$CPU_USAGE" ]; then
    log_message "CPU Usage: ${CPU_USAGE}%"
fi

# Check Memory usage
MEMORY_INFO=$(free | grep Mem)
MEMORY_TOTAL=$(echo $MEMORY_INFO | awk '{print $2}')
MEMORY_USED=$(echo $MEMORY_INFO | awk '{print $3}')
MEMORY_PERCENT=$((MEMORY_USED * 100 / MEMORY_TOTAL))
log_message "Memory Usage: ${MEMORY_PERCENT}%"

# ═══════════════════════════════════════════════════════════════════════════
# Threshold Checks and Alerts
# ═══════════════════════════════════════════════════════════════════════════

# Queue size checks
if [ "$QUEUE_COUNT" -ge "$QUEUE_CRITICAL_THRESHOLD" ]; then
    send_alert "CRITICAL" "Queue size is critically high: $QUEUE_COUNT messages (threshold: $QUEUE_CRITICAL_THRESHOLD)"
elif [ "$QUEUE_COUNT" -ge "$QUEUE_WARNING_THRESHOLD" ]; then
    send_alert "WARNING" "Queue size is elevated: $QUEUE_COUNT messages (threshold: $QUEUE_WARNING_THRESHOLD)"
fi

# CPU checks
if [ -n "$CPU_USAGE" ] && [ "$CPU_USAGE" -ge "$CPU_CRITICAL_THRESHOLD" ]; then
    send_alert "CRITICAL" "CPU usage is critically high: ${CPU_USAGE}% (threshold: ${CPU_CRITICAL_THRESHOLD}%)"
elif [ -n "$CPU_USAGE" ] && [ "$CPU_USAGE" -ge "$CPU_WARNING_THRESHOLD" ]; then
    send_alert "WARNING" "CPU usage is elevated: ${CPU_USAGE}% (threshold: ${CPU_WARNING_THRESHOLD}%)"
fi

# Memory checks
MEMORY_CRITICAL_THRESHOLD=90
if [ "$MEMORY_PERCENT" -ge "$MEMORY_CRITICAL_THRESHOLD" ]; then
    send_alert "CRITICAL" "Memory usage is critically high: ${MEMORY_PERCENT}% (threshold: ${MEMORY_CRITICAL_THRESHOLD}%)"
elif [ "$MEMORY_PERCENT" -ge "$MEMORY_WARNING_THRESHOLD" ]; then
    send_alert "WARNING" "Memory usage is elevated: ${MEMORY_PERCENT}% (threshold: ${MEMORY_WARNING_THRESHOLD}%)"
fi

# Consumer process check
if [ "$CONSUMER_COUNT" -eq 0 ] && [ "$QUEUE_COUNT" -gt 0 ]; then
    send_alert "WARNING" "No active consumer processes but queue has $QUEUE_COUNT messages"
fi

log_message "=== Queue Monitor Check Completed ==="
log_message ""

# Exit with appropriate code
# Note: Use { } for proper grouping since && binds tighter than ||
if [ "$QUEUE_COUNT" -ge "$QUEUE_CRITICAL_THRESHOLD" ] || { [ -n "$CPU_USAGE" ] && [ "$CPU_USAGE" -ge "$CPU_CRITICAL_THRESHOLD" ]; } || { [ "$MEMORY_PERCENT" -ge "$MEMORY_CRITICAL_THRESHOLD" ]; }; then
    exit 2  # Critical
elif [ "$QUEUE_COUNT" -ge "$QUEUE_WARNING_THRESHOLD" ] || { [ -n "$CPU_USAGE" ] && [ "$CPU_USAGE" -ge "$CPU_WARNING_THRESHOLD" ]; } || { [ "$MEMORY_PERCENT" -ge "$MEMORY_WARNING_THRESHOLD" ]; }; then
    exit 1  # Warning
else
    exit 0  # OK
fi

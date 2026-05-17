#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# CPU & Resource Monitor - Production Server
# Purpose: Monitor CPU, Memory, Disk and alert on high usage
# Location: /home/dashboard/public_html/scripts/monitoring/cpu_monitor.sh
# ═══════════════════════════════════════════════════════════════════════════

# Configuration
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_PASS="YourNewStrongPassword"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"

# Thresholds (unified with other monitoring scripts)
CPU_WARNING=60
CPU_CRITICAL=80
MEMORY_WARNING=70
MEMORY_CRITICAL=90
DISK_WARNING=80
DISK_CRITICAL=90
LOAD_WARNING=8
LOAD_CRITICAL=12

# Log files
LOG_FILE="/home/dashboard/public_html/logs/cpu_monitor.log"
ALERT_FILE="/home/dashboard/public_html/logs/resource_alerts.log"

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
    local alert_key="cpumon_$(echo "$message" | md5sum | cut -d' ' -f1)"

    # Run PHP in background to avoid blocking
    $PHP_BIN "$ALERT_CRON_PHP" --direct-alert --key="$alert_key" --severity="$severity" --message="$message" --time="$timestamp" >> /dev/null 2>&1 &
}

log_message "=== Resource Monitor Check ==="

# CPU Usage
CPU_LINE=$(top -bn1 | grep "Cpu(s)")
CPU_USER=$(echo "$CPU_LINE" | awk '{print $2}' | cut -d'%' -f1)
CPU_SYS=$(echo "$CPU_LINE" | awk '{print $4}' | cut -d'%' -f1)
CPU_IDLE=$(echo "$CPU_LINE" | awk '{print $8}' | cut -d'%' -f1)
CPU_WAIT=$(echo "$CPU_LINE" | awk '{print $10}' | cut -d'%' -f1)

CPU_TOTAL=$(echo "$CPU_USER + $CPU_SYS" | bc 2>/dev/null || echo "$CPU_USER")
log_message "CPU: User=${CPU_USER}%, System=${CPU_SYS}%, Idle=${CPU_IDLE}%, I/O Wait=${CPU_WAIT}%"

# CPU Check
if (( $(echo "$CPU_TOTAL >= $CPU_CRITICAL" | bc -l 2>/dev/null || echo 0) )); then
    send_alert "CRITICAL" "CPU usage critical: ${CPU_TOTAL}% (threshold: ${CPU_CRITICAL}%)"
elif (( $(echo "$CPU_TOTAL >= $CPU_WARNING" | bc -l 2>/dev/null || echo 0) )); then
    send_alert "WARNING" "CPU usage elevated: ${CPU_TOTAL}% (threshold: ${CPU_WARNING}%)"
fi

# Memory Usage
MEM_INFO=$(free | grep Mem)
MEM_TOTAL=$(echo $MEM_INFO | awk '{print $2}')
MEM_USED=$(echo $MEM_INFO | awk '{print $3}')
MEM_FREE=$(echo $MEM_INFO | awk '{print $4}')
MEM_BUFF=$(echo $MEM_INFO | awk '{print $6}')
MEM_PERCENT=$((MEM_USED * 100 / MEM_TOTAL))

log_message "Memory: Used=${MEM_USED}MB, Free=${MEM_FREE}MB, Buff/Cache=${MEM_BUFF}MB (${MEM_PERCENT}%)"

# Memory Check
if [ "$MEM_PERCENT" -ge "$MEMORY_CRITICAL" ]; then
    send_alert "CRITICAL" "Memory usage critical: ${MEM_PERCENT}% (threshold: ${MEMORY_CRITICAL}%)"
elif [ "$MEM_PERCENT" -ge "$MEMORY_WARNING" ]; then
    send_alert "WARNING" "Memory usage elevated: ${MEM_PERCENT}% (threshold: ${MEMORY_WARNING}%)"
fi

# Disk Usage
log_message "Disk Usage:"
df -h | grep -E "^/dev|Filesystem" | while read line; do
    DISK_PERCENT=$(echo "$line" | awk '{print $5}' | tr -d '%')
    MOUNT=$(echo "$line" | awk '{print $6}')
    
    if [[ "$DISK_PERCENT" =~ ^[0-9]+$ ]]; then
        if [ "$DISK_PERCENT" -ge "$DISK_CRITICAL" ]; then
            send_alert "CRITICAL" "Disk usage critical on $MOUNT: ${DISK_PERCENT}%"
        elif [ "$DISK_PERCENT" -ge "$DISK_WARNING" ]; then
            send_alert "WARNING" "Disk usage elevated on $MOUNT: ${DISK_PERCENT}%"
        fi
        log_message "  $line"
    fi
done

# Load Average
LOAD_INFO=$(uptime)
LOAD_1=$(echo "$LOAD_INFO" | awk -F'load average:' '{print $2}' | awk -F',' '{print $1}' | tr -d ' ')
LOAD_5=$(echo "$LOAD_INFO" | awk -F'load average:' '{print $2}' | awk -F',' '{print $2}' | tr -d ' ')
LOAD_15=$(echo "$LOAD_INFO" | awk -F'load average:' '{print $2}' | awk -F',' '{print $3}' | tr -d ' ')

log_message "Load Average: 1m=${LOAD_1}, 5m=${LOAD_5}, 15m=${LOAD_15}"

# Load Check (compare as integers after multiplying by 100)
LOAD_1_INT=$(echo "$LOAD_1" | cut -d'.' -f1)
if [ "$LOAD_1_INT" -ge "$LOAD_CRITICAL" ]; then
    send_alert "CRITICAL" "System load critical: ${LOAD_1} (threshold: ${LOAD_CRITICAL})"
elif [ "$LOAD_1_INT" -ge "$LOAD_WARNING" ]; then
    send_alert "WARNING" "System load elevated: ${LOAD_1} (threshold: ${LOAD_WARNING})"
fi

# Top CPU Processes
log_message "Top CPU Processes:"
ps aux --sort=-%cpu | head -6 | tail -5 | while read line; do
    PID=$(echo $line | awk '{print $2}')
    CPU=$(echo $line | awk '{print $3}')
    MEM=$(echo $line | awk '{print $4}')
    CMD=$(echo $line | awk '{for(i=11;i<=NF;i++) printf $i" "; print ""}')
    log_message "  PID=$PID CPU=${CPU}% MEM=${MEM}% $CMD"
done

# Top Memory Processes
log_message "Top Memory Processes:"
ps aux --sort=-%mem | head -6 | tail -5 | while read line; do
    PID=$(echo $line | awk '{print $2}')
    CPU=$(echo $line | awk '{print $3}')
    MEM=$(echo $line | awk '{print $4}')
    CMD=$(echo $line | awk '{for(i=11;i<=NF;i++) printf $i" "; print ""}')
    log_message "  PID=$PID CPU=${CPU}% MEM=${MEM}% $CMD"
done

# MySQL/MariaDB Connections
if command -v $MYSQL_BIN &> /dev/null; then
    MYSQL_CONNS=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | awk '{print $2}' || echo "N/A")
    log_message "MySQL Connections: $MYSQL_CONNS"
fi

# Queue Size (quick check)
QUEUE_COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")
log_message "Magento Queue Size: $QUEUE_COUNT messages"

if [ "$QUEUE_COUNT" -ge 5000 ]; then
    send_alert "CRITICAL" "Queue size critical: $QUEUE_COUNT messages"
elif [ "$QUEUE_COUNT" -ge 1000 ]; then
    send_alert "WARNING" "Queue size elevated: $QUEUE_COUNT messages"
fi

log_message "=== Resource Monitor Check Completed ==="
log_message ""

# Exit with appropriate code
exit 0

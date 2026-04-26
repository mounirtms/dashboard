#!/bin/bash

###############################################################################
# Server Health Check Script
# Purpose: Monitor system health and alert on critical issues
# Location: /home/technadminy7/public_html/scripts/health_check.sh
# Schedule: Every 5 minutes via cron
###############################################################################

# Configuration
MAX_LOAD=8.0
MAX_CPU=75
MAX_MEM=80
ALERT_EMAIL="webmaster@techno-dz.com"
LOG_FILE="/home/technadminy7/public_html/var/log/health_check.log"
ALERT_FILE="/tmp/health_alert_sent"

# Get current values
LOAD=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
CPU=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1 | cut -d. -f1)
MEM=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Log current status
echo "[$TIMESTAMP] Load: $LOAD | CPU: $CPU% | Memory: $MEM%" >> "$LOG_FILE"

# Check load average
LOAD_INT=$(echo "$LOAD" | cut -d. -f1)
if [ "$LOAD_INT" -gt "$(echo $MAX_LOAD | cut -d. -f1)" ]; then
    if [ ! -f "${ALERT_FILE}_load" ] || [ $(find "${ALERT_FILE}_load" -mmin +60 2>/dev/null) ]; then
        echo "[$TIMESTAMP] ALERT: Load average is $LOAD (threshold: $MAX_LOAD)" | tee -a "$LOG_FILE" | \
            mail -s "⚠️ Load Alert - $(hostname)" "$ALERT_EMAIL"
        touch "${ALERT_FILE}_load"
    fi
else
    rm -f "${ALERT_FILE}_load" 2>/dev/null
fi

# Check CPU usage
if [ "$CPU" -gt "$MAX_CPU" ]; then
    if [ ! -f "${ALERT_FILE}_cpu" ] || [ $(find "${ALERT_FILE}_cpu" -mmin +60 2>/dev/null) ]; then
        echo "[$TIMESTAMP] ALERT: CPU usage is $CPU% (threshold: $MAX_CPU%)" | tee -a "$LOG_FILE" | \
            mail -s "⚠️ CPU Alert - $(hostname)" "$ALERT_EMAIL"
        touch "${ALERT_FILE}_cpu"
    fi
else
    rm -f "${ALERT_FILE}_cpu" 2>/dev/null
fi

# Check memory usage
if [ "$MEM" -gt "$MAX_MEM" ]; then
    if [ ! -f "${ALERT_FILE}_mem" ] || [ $(find "${ALERT_FILE}_mem" -mmin +60 2>/dev/null) ]; then
        echo "[$TIMESTAMP] ALERT: Memory usage is $MEM% (threshold: $MAX_MEM%)" | tee -a "$LOG_FILE" | \
            mail -s "⚠️ Memory Alert - $(hostname)" "$ALERT_EMAIL"
        touch "${ALERT_FILE}_mem"
    fi
else
    rm -f "${ALERT_FILE}_mem" 2>/dev/null
fi

# Check MariaDB status
if ! systemctl is-active --quiet mariadb; then
    echo "[$TIMESTAMP] CRITICAL: MariaDB is not running!" | tee -a "$LOG_FILE" | \
        mail -s "🔴 CRITICAL: MariaDB Down - $(hostname)" "$ALERT_EMAIL"
fi

# Check PHP-FPM status
if ! systemctl is-active --quiet ea-php82-php-fpm; then
    echo "[$TIMESTAMP] CRITICAL: PHP-FPM is not running!" | tee -a "$LOG_FILE" | \
        mail -s "🔴 CRITICAL: PHP-FPM Down - $(hostname)" "$ALERT_EMAIL"
fi

# Check Elasticsearch status
if ! systemctl is-active --quiet elasticsearch; then
    echo "[$TIMESTAMP] WARNING: Elasticsearch is not running" | tee -a "$LOG_FILE" | \
        mail -s "⚠️ WARNING: Elasticsearch Down - $(hostname)" "$ALERT_EMAIL"
fi

# Check disk space (alert if any partition > 85%)
df -h | awk '$5 > 85 {print $0}' | while read line; do
    USAGE=$(echo $line | awk '{print $5}' | sed 's/%//')
    MOUNT=$(echo $line | awk '{print $6}')
    echo "[$TIMESTAMP] WARNING: Disk usage on $MOUNT is ${USAGE}%" | tee -a "$LOG_FILE" | \
        mail -s "⚠️ Disk Space Alert - $(hostname)" "$ALERT_EMAIL"
done

# Rotate log file if > 10MB
if [ -f "$LOG_FILE" ] && [ $(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE") -gt 10485760 ]; then
    mv "$LOG_FILE" "${LOG_FILE}.old"
    gzip "${LOG_FILE}.old" &
fi

exit 0

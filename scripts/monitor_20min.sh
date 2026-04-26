#!/bin/bash
###############################################################################
# 20-Minute Performance Monitoring Script
# Date: April 26, 2026
# Purpose: Monitor system performance, load, and response times
###############################################################################

set -euo pipefail

MONITOR_DURATION=1200  # 20 minutes in seconds
LOG_INTERVAL=60        # Log every 60 seconds
SITE_URL="https://technostationery.com"
LOG_FILE="/home/technadminy7/public_html/logs/monitor_20min_$(date +%Y%m%d_%H%M%S).log"

mkdir -p "$(dirname "$LOG_FILE")"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "========================================="
log "20-MINUTE PERFORMANCE MONITORING START"
log "Duration: $MONITOR_DURATION seconds"
log "Interval: $LOG_INTERVAL seconds"
log "========================================="

START_TIME=$(date +%s)
ITERATION=0

while true; do
    CURRENT_TIME=$(date +%s)
    ELAPSED=$((CURRENT_TIME - START_TIME))
    
    if [ $ELAPSED -ge $MONITOR_DURATION ]; then
        break
    fi
    
    ITERATION=$((ITERATION + 1))
    REMAINING=$((MONITOR_DURATION - ELAPSED))
    MINUTES=$((REMAINING / 60))
    SECONDS=$((REMAINING % 60))
    
    log ""
    log "========================================="
    log "Iteration #$ITERATION | Remaining: ${MINUTES}m ${SECONDS}s"
    log "========================================="
    
    # 1. System Load
    log "1. SYSTEM LOAD:"
    uptime | tee -a "$LOG_FILE"
    
    # 2. Memory Usage
    log ""
    log "2. MEMORY USAGE:"
    free -h | grep -E "Mem|Swap" | tee -a "$LOG_FILE"
    
    # 3. Top CPU Processes
    log ""
    log "3. TOP 5 CPU PROCESSES:"
    ps aux --sort=-%cpu | head -6 | awk '{printf "%-10s %5s %5s %s\n", $1, $3"%", $4"%", $11}' | tee -a "$LOG_FILE"
    
    # 4. PHP-FPM Workers
    log ""
    log "4. PHP-FPM WORKERS:"
    ps aux | grep -E "php-fpm.*technostationery" | grep -v grep | wc -l | xargs -I {} log "Active workers: {}"
    
    # 5. MariaDB Connections
    log ""
    log "5. MARIADB CONNECTIONS:"
    /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | tail -1 | awk '{print "Connected threads: " $2}' | tee -a "$LOG_FILE" || log "MySQL query failed"
    
    # 6. Website Response Time (3 requests)
    log ""
    log "6. WEBSITE RESPONSE TIME:"
    TOTAL=0
    for i in {1..3}; do
        TIME=$(curl -s -o /dev/null -w "%{time_total}" "$SITE_URL" 2>/dev/null)
        TOTAL=$(echo "$TOTAL + $TIME" | bc 2>/dev/null)
        log "  Request $i: ${TIME}s"
    done
    AVG=$(echo "scale=3; $TOTAL / 3" | bc 2>/dev/null)
    log "  Average: ${AVG}s"
    
    # 7. Redis Memory
    log ""
    log "7. REDIS MEMORY:"
    redis-cli info memory 2>/dev/null | grep "used_memory_human" | tee -a "$LOG_FILE" || log "Redis query failed"
    
    # 8. Disk I/O
    log ""
    log "8. DISK I/O:"
    iostat -x 1 2 | tail -1 | awk '{printf "Device: %s | Read: %.2f MB/s | Write: %.2f MB/s\n", $1, $6/1024, $7/1024}' | tee -a "$LOG_FILE" 2>/dev/null || log "iostat not available"
    
    # 9. Error Log Check (last 5 errors)
    log ""
    log "9. RECENT ERRORS (last 5):"
    tail -5 /home/technadminy7/public_html/var/log/system.log 2>/dev/null | grep -i "error\|exception\|fatal" | tail -5 | tee -a "$LOG_FILE" || log "No recent errors"
    
    # Wait for next iteration
    if [ $ELAPSED -lt $MONITOR_DURATION ]; then
        sleep $LOG_INTERVAL
    fi
done

log ""
log "========================================="
log "20-MINUTE MONITORING COMPLETED"
log "========================================="
log "Log file: $LOG_FILE"

# Generate summary
log ""
log "========================================="
log "SUMMARY STATISTICS"
log "========================================="

log "Calculating average metrics..."

# Average response time
AVG_RESPONSE=$(grep "Average:" "$LOG_FILE" | awk '{print $3}' | sed 's/s//' | awk '{sum+=$1; count++} END {if(count>0) printf "%.3f", sum/count; else print "N/A"}')
log "Average Response Time: ${AVG_RESPONSE}s"

# Average load (1-min)
AVG_LOAD=$(grep "load average" "$LOG_FILE" | awk -F'load average:' '{print $2}' | awk -F',' '{print $1}' | awk '{sum+=$1; count++} END {if(count>0) printf "%.2f", sum/count; else print "N/A"}')
log "Average Load (1-min): $AVG_LOAD"

# Peak load
PEAK_LOAD=$(grep "load average" "$LOG_FILE" | awk -F'load average:' '{print $2}' | awk -F',' '{print $1}' | sort -rn | head -1 | tr -d ' ')
log "Peak Load (1-min): $PEAK_LOAD"

log "========================================="
log "Report saved to: $LOG_FILE"
log "========================================="

exit 0

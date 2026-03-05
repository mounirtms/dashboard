#!/bin/bash
# Magento Production Monitoring Script
# File: /usr/local/bin/magento-monitor.sh

# Configuration
MAGENTO_PATH="/home/technadminy7/public_html"
LOG_FILE="/var/log/magento-monitor.log"
ALERT_EMAIL="admin@technostationery.com"

# Thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=85
DISK_THRESHOLD=90
RESPONSE_TIME_THRESHOLD=5

# Functions
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

send_alert() {
    local subject="$1"
    local message="$2"
    echo "$message" | mail -s "$subject" "$ALERT_EMAIL"
    log_message "ALERT SENT: $subject"
}

check_system_resources() {
    # CPU Usage
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    if (( $(echo "$cpu_usage > $CPU_THRESHOLD" | bc -l) )); then
        send_alert "HIGH CPU USAGE ALERT" "CPU usage is at ${cpu_usage}%"
    fi
    
    # Memory Usage
    local memory_usage=$(free | grep Mem | awk '{printf("%.2f"), $3/$2 * 100.0}')
    if (( $(echo "$memory_usage > $MEMORY_THRESHOLD" | bc -l) )); then
        send_alert "HIGH MEMORY USAGE ALERT" "Memory usage is at ${memory_usage}%"
    fi
    
    # Disk Usage
    local disk_usage=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$disk_usage" -gt "$DISK_THRESHOLD" ]; then
        send_alert "LOW DISK SPACE ALERT" "Disk usage is at ${disk_usage}%"
    fi
}

check_magento_health() {
    # Check Magento health endpoint
    local response_time=$(curl -o /dev/null -s -w "%{time_total}\n" https://technostationery.com/pub/health_check.php 2>/dev/null)
    
    if [ $? -ne 0 ]; then
        send_alert "MAGENTO SITE DOWN" "Unable to reach Magento health check endpoint"
    elif (( $(echo "$response_time > $RESPONSE_TIME_THRESHOLD" | bc -l) )); then
        send_alert "SLOW RESPONSE TIME" "Magento response time is ${response_time} seconds"
    fi
}

check_php_processes() {
    # Check PHP-FPM processes
    local php_processes=$(pgrep -f "php-fpm: pool" | wc -l)
    local max_children=24  # From php-fpm config
    
    if [ "$php_processes" -gt $((max_children * 2)) ]; then
        send_alert "HIGH PHP PROCESSES" "Too many PHP processes running: $php_processes"
    fi
}

check_redis_status() {
    # Check Redis connectivity
    if ! redis-cli ping >/dev/null 2>&1; then
        send_alert "REDIS UNAVAILABLE" "Redis server is not responding"
    fi
}

check_mysql_status() {
    # Check MySQL connectivity
    if ! mysqladmin -h localhost -P 3307 ping >/dev/null 2>&1; then
        send_alert "MYSQL UNAVAILABLE" "MySQL server is not responding"
    fi
}

# Main execution
log_message "Starting Magento monitoring check"

check_system_resources
check_magento_health
check_php_processes
check_redis_status
check_mysql_status

log_message "Monitoring check completed"

# Cleanup old log entries (keep last 1000 lines)
if [ -f "$LOG_FILE" ] && [ $(wc -l < "$LOG_FILE") -gt 1000 ]; then
    tail -n 1000 "$LOG_FILE" > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "$LOG_FILE"
fi
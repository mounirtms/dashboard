#!/bin/bash

# CPU Manager Script for Magento
# Monitors and optimizes CPU usage by managing processes and priorities

LOG_FILE="/home/technadminy7/public_html/var/log/cpu_manager.log"
CPU_THRESHOLD=70  # Alert when CPU usage exceeds 70%
CRITICAL_CPU_THRESHOLD=85  # Take action when CPU usage exceeds 85%

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Function to get current CPU usage
get_cpu_usage() {
    # Get overall CPU usage percentage
    top -bn1 | grep "Cpu(s)" | awk '{print int($2)}'
}

# Function to identify high CPU processes
identify_high_cpu_processes() {
    log_message "Identifying high CPU processes..."
    ps aux --sort=-%cpu | head -15 | while read line; do
        log_message "High CPU: $line"
    done
}

# Function to renice high CPU processes
optimize_process_priorities() {
    log_message "Optimizing process priorities..."
    
    # Lower priority of non-critical high CPU processes
    HIGH_CPU_PIDS=$(ps aux | awk '$3 > 30 && $11 ~ /(php-fpm|java|mysqld)/ {print $2}')
    
    for pid in $HIGH_CPU_PIDS; do
        if [ -n "$pid" ] && [ "$pid" != "$$" ]; then
            # Lower priority (increase nice value) for high CPU processes
            renice +5 "$pid" 2>/dev/null
            if [ $? -eq 0 ]; then
                log_message "Lowered priority for process PID: $pid"
            fi
        fi
    done
}

# Function to limit concurrent PHP processes
limit_php_processes() {
    log_message "Managing PHP-FPM process count..."
    
    # Count current PHP-FPM processes
    PHP_COUNT=$(pgrep -f php-fpm | wc -l)
    MAX_PHP_PROCESSES=25
    
    if [ "$PHP_COUNT" -gt "$MAX_PHP_PROCESSES" ]; then
        log_message "Too many PHP processes ($PHP_COUNT), limiting to $MAX_PHP_PROCESSES"
        
        # Kill excess PHP processes (keep the newer ones)
        pgrep -f php-fpm | tail -n +$((MAX_PHP_PROCESSES + 1)) | xargs kill -TERM 2>/dev/null
        
        # Give processes time to terminate gracefully
        sleep 5
        
        # Force kill if still running
        pgrep -f php-fpm | tail -n +$((MAX_PHP_PROCESSES + 1)) | xargs kill -9 2>/dev/null
    fi
}

# Function to optimize MySQL queries
optimize_mysql_cpu() {
    log_message "Optimizing MySQL CPU usage..."
    
    # Kill long-running queries that consume excessive CPU
    mysql -e "SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO FROM INFORMATION_SCHEMA.PROCESSLIST WHERE TIME > 60 AND COMMAND != 'Sleep' ORDER BY TIME DESC LIMIT 5" 2>/dev/null | while read line; do
        log_message "Long running query: $line"
    done
    
    # This would require MySQL root access - logging for now
}

# Function to restart services if CPU is critically high
emergency_cpu_management() {
    log_message "EMERGENCY: Critical CPU usage detected"
    
    # Restart PHP-FPM to clear accumulated overhead
    log_message "Restarting PHP-FPM for emergency CPU relief..."
    /usr/local/cpanel/scripts/restartsrv_apache_php_fpm >> "$LOG_FILE" 2>&1
    
    # Clear system caches
    sync
    echo 3 > /proc/sys/vm/drop_caches
    
    log_message "Emergency CPU management completed"
}

# Main execution
log_message "=== CPU Manager Script Started ==="

CURRENT_CPU=$(get_cpu_usage)
log_message "Current CPU usage: ${CURRENT_CPU}%"

identify_high_cpu_processes

if [ "$CURRENT_CPU" -ge "$CRITICAL_CPU_THRESHOLD" ]; then
    log_message "CRITICAL: CPU usage (${CURRENT_CPU}%) exceeds critical threshold (${CRITICAL_CPU_THRESHOLD}%)"
    emergency_cpu_management
elif [ "$CURRENT_CPU" -ge "$CPU_THRESHOLD" ]; then
    log_message "WARNING: CPU usage (${CURRENT_CPU}%) exceeds threshold (${CPU_THRESHOLD}%)"
    optimize_process_priorities
    limit_php_processes
    optimize_mysql_cpu
else
    log_message "CPU usage (${CURRENT_CPU}%) is within acceptable limits"
    # Still do light optimization
    optimize_process_priorities
fi

log_message "=== CPU Manager Script Completed ==="
log_message ""
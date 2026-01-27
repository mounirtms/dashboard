#!/bin/bash

# Optimized CPU Manager Script for Magento
# More efficient version that reduces resource consumption

LOG_FILE="/home/technadminy7/public_html/var/log/cpu_manager_optimized.log"
CPU_THRESHOLD=60  # Alert when CPU usage exceeds 60%
CRITICAL_CPU_THRESHOLD=75  # Take action when CPU usage exceeds 75%

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Lightweight function to get current CPU usage
get_cpu_usage() {
    # More efficient way to get CPU usage
    awk '/^cpu / {usage=($2+$4)*100/($2+$3+$4+$5)} END {print int(usage)}' /proc/stat
}

# Efficient process identification
identify_problematic_processes() {
    log_message "Scanning for problematic processes..."
    
    # Use ps with specific fields to reduce overhead
    ps -eo pid,ppid,cmd,%cpu,%mem,etime --sort=-%cpu | head -10 | while read line; do
        log_message "High resource: $line"
    done
}

# Smart process management
manage_processes() {
    log_message "Managing system processes..."
    
    # Count current PHP processes efficiently
    PHP_FPM_COUNT=$(pgrep -fc "php-fpm:")
    PHP_CGI_COUNT=$(pgrep -fc "php-cgi")
    TOTAL_PHP=$((PHP_FPM_COUNT + PHP_CGI_COUNT))
    
    log_message "PHP processes - FPM: $PHP_FPM_COUNT, CGI: $PHP_CGI_COUNT, Total: $TOTAL_PHP"
    
    # Only take action if we have too many processes
    if [ "$TOTAL_PHP" -gt 30 ]; then
        log_message "High PHP process count detected ($TOTAL_PHP), taking corrective action"
        
        # Kill the oldest high-CPU PHP processes (more than 40% CPU for >60 seconds)
        ps -eo pid,comm,%cpu,etimes | awk '$3 > 40 && $4 > 60 && $2 ~ /php/ {print $1}' | head -5 | while read pid; do
            if [ -n "$pid" ] && [ "$pid" != "$$" ]; then
                log_message "Terminating high-CPU process PID: $pid"
                kill -TERM "$pid" 2>/dev/null
                
                # Wait and force kill if needed
                sleep 3
                if ps -p "$pid" > /dev/null 2>&1; then
                    kill -9 "$pid" 2>/dev/null
                    log_message "Force killed process PID: $pid"
                fi
            fi
        done
    fi
}

# Gentle system optimization
optimize_system() {
    log_message "Performing gentle system optimization..."
    
    # Clear caches only if memory pressure is high
    MEM_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    if [ "$MEM_USAGE" -gt 75 ]; then
        log_message "Clearing system caches due to high memory usage (${MEM_USAGE}%)"
        sync
        echo 1 > /proc/sys/vm/drop_caches 2>/dev/null
    fi
    
    # Adjust process priorities gently
    HIGH_CPU_PIDS=$(ps -eo pid,comm,%cpu | awk '$3 > 35 && $2 ~ /(php|java|mysql)/ {print $1}' | head -3)
    
    for pid in $HIGH_CPU_PIDS; do
        if [ -n "$pid" ] && [ "$pid" != "$$" ]; then
            # Only adjust if not already adjusted
            CURRENT_NICE=$(ps -o nice= -p "$pid" 2>/dev/null)
            if [ "$CURRENT_NICE" != "5" ] && [ "$CURRENT_NICE" != "10" ]; then
                renice +3 "$pid" 2>/dev/null
                if [ $? -eq 0 ]; then
                    log_message "Adjusted priority for process PID: $pid"
                fi
            fi
        fi
    done
}

# Emergency response (only for severe cases)
emergency_response() {
    log_message "EMERGENCY: Critical system load detected!"
    
    # Log current state before intervention
    log_message "System state before emergency response:"
    uptime >> "$LOG_FILE"
    free -h >> "$LOG_FILE"
    
    # Restart only the most problematic service
    PHP_HIGH_COUNT=$(pgrep -fc "php.*pool")
    if [ "$PHP_HIGH_COUNT" -gt 25 ]; then
        log_message "Restarting PHP-FPM due to excessive worker count ($PHP_HIGH_COUNT)"
        /usr/local/cpanel/scripts/restartsrv_apache_php_fpm >> "$LOG_FILE" 2>&1
    fi
    
    # Wait and reassess
    sleep 10
    
    # Final assessment
    FINAL_CPU=$(get_cpu_usage)
    log_message "Post-emergency CPU usage: ${FINAL_CPU}%"
}

# Main execution
log_message "=== Optimized CPU Manager Started ==="

CURRENT_CPU=$(get_cpu_usage)
log_message "Current CPU usage: ${CURRENT_CPU}%"

identify_problematic_processes

if [ "$CURRENT_CPU" -ge "$CRITICAL_CPU_THRESHOLD" ]; then
    log_message "CRITICAL: CPU usage (${CURRENT_CPU}%) exceeds critical threshold (${CRITICAL_CPU_THRESHOLD}%)"
    emergency_response
elif [ "$CURRENT_CPU" -ge "$CPU_THRESHOLD" ]; then
    log_message "WARNING: CPU usage (${CURRENT_CPU}%) exceeds threshold (${CPU_THRESHOLD}%)"
    manage_processes
    optimize_system
else
    log_message "CPU usage (${CURRENT_CPU}%) is within acceptable limits"
    # Light optimization only
    optimize_system
fi

log_message "=== Optimized CPU Manager Completed ==="
log_message ""
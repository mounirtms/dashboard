#!/bin/bash

# PHP-FPM CPU Optimization Script
# Optimizes PHP-FPM for better CPU efficiency

LOG_FILE="/home/technadminy7/public_html/var/log/php_fpm_cpu_optimizer.log"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "=== PHP-FPM CPU Optimization Started ==="

# Function to optimize running PHP processes
optimize_running_php_processes() {
    log_message "Optimizing running PHP processes..."
    
    # Find PHP processes consuming high CPU
    HIGH_CPU_PHP=$(ps aux | grep php-fpm | awk '$3 > 25 {print $2}')
    
    for pid in $HIGH_CPU_PHP; do
        if [ -n "$pid" ]; then
            # Lower priority of high CPU PHP processes
            renice +10 "$pid" 2>/dev/null
            if [ $? -eq 0 ]; then
                log_message "Lowered priority for high CPU PHP process: $pid"
            fi
            
            # If process is using extreme CPU for too long, consider restarting
            CPU_TIME=$(ps -p "$pid" -o time= | tr -d ' ')
            if [ -n "$CPU_TIME" ]; then
                # Convert time to seconds for comparison
                MINUTES=$(echo "$CPU_TIME" | cut -d: -f1)
                if [ "$MINUTES" -gt 10 ]; then  # If running more than 10 minutes
                    log_message "Terminating long-running high CPU process: $pid"
                    kill -TERM "$pid" 2>/dev/null
                fi
            fi
        fi
    done
}

# Function to optimize PHP-FPM configuration for CPU
optimize_php_fpm_config() {
    log_message "Optimizing PHP-FPM configuration for CPU efficiency..."
    
    # These are suggestions that would need to be applied to the actual PHP-FPM config
    log_message "Recommended PHP-FPM CPU optimizations:"
    log_message "- Set pm.max_children to appropriate level (15-20 for current load)"
    log_message "- Enable opcache with proper settings"
    log_message "- Use APCu for user cache instead of file-based cache"
    log_message "- Optimize realpath cache settings"
    
    # Apply runtime optimizations
    # Clear opcache
    echo "<?php opcache_reset(); ?>" > /tmp/clear_opcache_cpu.php
    curl -s http://localhost/tmp/clear_opcache_cpu.php > /dev/null 2>&1
    rm -f /tmp/clear_opcache_cpu.php
}

# Function to implement CPU quotas
implement_cpu_quotas() {
    log_message "Implementing CPU quotas for web processes..."
    
    # Set CPU affinity for web processes (if multiple cores available)
    WEB_PIDS=$(pgrep -f "php-fpm|apache")
    
    CORE_COUNT=$(nproc)
    if [ "$CORE_COUNT" -gt 4 ]; then
        # On multi-core systems, distribute processes across cores
        CORE_INDEX=0
        for pid in $WEB_PIDS; do
            if [ -n "$pid" ]; then
                taskset -pc $((CORE_INDEX % CORE_COUNT)) "$pid" 2>/dev/null
                CORE_INDEX=$((CORE_INDEX + 1))
            fi
        done
        log_message "Distributed web processes across $CORE_COUNT CPU cores"
    fi
}

# Function to monitor and log CPU-intensive operations
monitor_cpu_intensive_operations() {
    log_message "Monitoring CPU-intensive operations..."
    
    # Log top 5 CPU-consuming processes
    ps aux --sort=-%cpu | head -6 | while read line; do
        log_message "CPU Top Process: $line"
    done
    
    # Log system interrupt activity
    INTERRUPTS=$(cat /proc/interrupts | head -5)
    log_message "System Interrupts:"
    echo "$INTERRUPTS" >> "$LOG_FILE"
}

# Main execution
optimize_running_php_processes
optimize_php_fpm_config
implement_cpu_quotas
monitor_cpu_intensive_operations

log_message "=== PHP-FPM CPU Optimization Completed ==="
log_message ""
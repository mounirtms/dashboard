#!/bin/bash

# Memory Manager Script for Magento
# Monitors system memory and performs cleanup when thresholds are exceeded

LOG_FILE="/home/technadminy7/public_html/var/log/memory_manager.log"
MEMORY_THRESHOLD_GB=25  # Trigger cleanup when memory usage exceeds 25GB
CRITICAL_THRESHOLD_GB=30  # Critical cleanup when memory usage exceeds 30GB

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Function to get current memory usage in GB
get_memory_usage() {
    free -g | awk 'NR==2{print $3}'
}

# Function to flush Redis cache
flush_redis() {
    log_message "Flushing Redis cache..."
    redis-cli FLUSHALL >> "$LOG_FILE" 2>&1
    if [ $? -eq 0 ]; then
        log_message "Redis cache flushed successfully"
    else
        log_message "ERROR: Failed to flush Redis cache"
    fi
}

# Function to restart PHP-FPM
restart_php_fpm() {
    log_message "Restarting PHP-FPM processes..."
    # Kill high memory PHP processes (>1.5GB RSS)
    ps aux | grep php-fpm | awk '$6 > 1500000 {print $2}' | xargs kill -9 2>/dev/null
    
    # Clear OPCache
    log_message "Clearing OPCache..."
    echo "<?php opcache_reset(); ?>" > /tmp/clear_opcache_$(date +%s).php
    curl -s http://localhost/tmp/clear_opcache_$(date +%s).php > /dev/null 2>&1
    rm -f /tmp/clear_opcache_*.php
    
    # Restart PHP-FPM service
    /usr/local/cpanel/scripts/restartsrv_apache_php_fpm >> "$LOG_FILE" 2>&1
    if [ $? -eq 0 ]; then
        log_message "PHP-FPM restarted successfully"
    else
        log_message "ERROR: Failed to restart PHP-FPM"
    fi
}

# Function to clean Magento cache
clean_magento_cache() {
    log_message "Cleaning Magento cache..."
    cd /home/technadminy7/public_html
    php bin/magento cache:flush >> "$LOG_FILE" 2>&1
    if [ $? -eq 0 ]; then
        log_message "Magento cache cleaned successfully"
    else
        log_message "ERROR: Failed to clean Magento cache"
    fi
}

# Function to clear system cache
clear_system_cache() {
    log_message "Clearing system caches..."
    sync
    echo 3 > /proc/sys/vm/drop_caches
    log_message "System caches cleared"
}

# Main execution
log_message "=== Memory Manager Script Started ==="

CURRENT_MEMORY=$(get_memory_usage)
log_message "Current memory usage: ${CURRENT_MEMORY}GB"

if [ "$CURRENT_MEMORY" -ge "$CRITICAL_THRESHOLD_GB" ]; then
    log_message "CRITICAL: Memory usage (${CURRENT_MEMORY}GB) exceeds critical threshold (${CRITICAL_THRESHOLD_GB}GB)"
    
    # Perform all cleanup actions
    flush_redis
    clean_magento_cache
    restart_php_fpm
    clear_system_cache
    
    # Wait and check again
    sleep 30
    NEW_MEMORY=$(get_memory_usage)
    log_message "Memory after critical cleanup: ${NEW_MEMORY}GB"
    
elif [ "$CURRENT_MEMORY" -ge "$MEMORY_THRESHOLD_GB" ]; then
    log_message "WARNING: Memory usage (${CURRENT_MEMORY}GB) exceeds threshold (${MEMORY_THRESHOLD_GB}GB)"
    
    # Perform moderate cleanup
    flush_redis
    clean_magento_cache
    
    # Wait and check again
    sleep 15
    NEW_MEMORY=$(get_memory_usage)
    log_message "Memory after cleanup: ${NEW_MEMORY}GB"
else
    log_message "Memory usage (${CURRENT_MEMORY}GB) is within acceptable limits"
fi

log_message "=== Memory Manager Script Completed ==="
log_message ""
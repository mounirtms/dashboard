#!/bin/bash
# High CPU Optimization Script for Magento
# This script addresses the immediate high CPU usage issues

LOG_FILE="/home/technadminy7/public_html/scripts/cpu_optimization.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "=== CPU Optimization Started - $DATE ===" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Function to kill high CPU processes safely
kill_high_cpu_processes() {
    echo "--- Killing High CPU Processes ---" | tee -a "$LOG_FILE"
    
    # Kill VS Code Server (running as root and consuming massive CPU)
    VS_CODE_PIDS=$(ps aux | grep "vscode-server.*node" | grep -v grep | awk '{if($3 > 50) print $2}')
    if [ ! -z "$VS_CODE_PIDS" ]; then
        echo "Found high CPU VS Code processes: $VS_CODE_PIDS" | tee -a "$LOG_FILE"
        for pid in $VS_CODE_PIDS; do
            echo "Killing VS Code process PID: $pid" | tee -a "$LOG_FILE"
            kill -9 $pid 2>/dev/null && echo "✓ Killed VS Code process $pid" | tee -a "$LOG_FILE" || echo "✗ Failed to kill VS Code process $pid" | tee -a "$LOG_FILE"
        done
    else
        echo "No high CPU VS Code processes found" | tee -a "$LOG_FILE"
    fi
    
    # Kill excessively high CPU PHP processes (>80%)
    HIGH_PHP_PIDS=$(ps aux | grep "php-fpm" | grep -v grep | awk '{if($3 > 80) print $2}')
    if [ ! -z "$HIGH_PHP_PIDS" ]; then
        echo "Found high CPU PHP processes: $HIGH_PHP_PIDS" | tee -a "$LOG_FILE"
        for pid in $HIGH_PHP_PIDS; do
            echo "Killing high CPU PHP process PID: $pid" | tee -a "$LOG_FILE"
            kill -9 $pid 2>/dev/null && echo "✓ Killed PHP process $pid" | tee -a "$LOG_FILE" || echo "✗ Failed to kill PHP process $pid" | tee -a "$LOG_FILE"
        done
    fi
    
    echo "" | tee -a "$LOG_FILE"
}

# Function to restart PHP-FPM pools to clear memory
restart_php_fpm() {
    echo "--- Restarting PHP-FPM Services ---" | tee -a "$LOG_FILE"
    
    # Restart PHP 8.2 FPM (most active)
    if systemctl is-active ea-php82-php-fpm >/dev/null 2>&1; then
        echo "Restarting ea-php82-php-fpm..." | tee -a "$LOG_FILE"
        systemctl restart ea-php82-php-fpm && echo "✓ ea-php82-php-fpm restarted successfully" | tee -a "$LOG_FILE" || echo "✗ Failed to restart ea-php82-php-fpm" | tee -a "$LOG_FILE"
    fi
    
    # Restart PHP 8.3 FPM if running
    if systemctl is-active ea-php83-php-fpm >/dev/null 2>&1; then
        echo "Restarting ea-php83-php-fpm..." | tee -a "$LOG_FILE"
        systemctl restart ea-php83-php-fpm && echo "✓ ea-php83-php-fpm restarted successfully" | tee -a "$LOG_FILE" || echo "✗ Failed to restart ea-php83-php-fpm" | tee -a "$LOG_FILE"
    fi
    
    echo "" | tee -a "$LOG_FILE"
}

# Function to clear Magento cache and sessions
clear_magento_cache() {
    echo "--- Clearing Magento Cache ---" | tee -a "$LOG_FILE"
    
    cd /home/technadminy7/public_html
    
    # Clear Magento cache
    if [ -f "bin/magento" ]; then
        echo "Clearing Magento cache..." | tee -a "$LOG_FILE"
        php bin/magento cache:flush >> "$LOG_FILE" 2>&1 && echo "✓ Magento cache cleared" | tee -a "$LOG_FILE" || echo "✗ Failed to clear Magento cache" | tee -a "$LOG_FILE"
        
        # Clear static content
        echo "Cleaning static content..." | tee -a "$LOG_FILE"
        rm -rf pub/static/* var/view_preprocessed/* >> "$LOG_FILE" 2>&1
        echo "✓ Static content cleaned" | tee -a "$LOG_FILE"
    fi
    
    # Clear PHP sessions
    echo "Clearing PHP sessions..." | tee -a "$LOG_FILE"
    rm -rf var/session/* >> "$LOG_FILE" 2>&1
    echo "✓ PHP sessions cleared" | tee -a "$LOG_FILE"
    
    echo "" | tee -a "$LOG_FILE"
}

# Function to optimize Redis
optimize_redis() {
    echo "--- Optimizing Redis ---" | tee -a "$LOG_FILE"
    
    # Check if Redis is running
    if redis-cli ping >/dev/null 2>&1; then
        echo "Redis is running, optimizing..." | tee -a "$LOG_FILE"
        
        # Clear expired keys
        redis-cli EVAL "local expired_keys = redis.call('SCAN', 0, 'MATCH', 'zc:*', 'COUNT', 1000); return #expired_keys" 0 >> "$LOG_FILE" 2>&1
        
        # Optimize memory
        redis-cli CONFIG SET maxmemory-policy allkeys-lru >> "$LOG_FILE" 2>&1
        echo "✓ Redis memory policy optimized" | tee -a "$LOG_FILE"
        
        # Get current memory usage
        MEMORY_USED=$(redis-cli info memory | grep used_memory_human | cut -d: -f2)
        echo "Redis memory usage: $MEMORY_USED" | tee -a "$LOG_FILE"
    else
        echo "✗ Redis is not running" | tee -a "$LOG_FILE"
    fi
    
    echo "" | tee -a "$LOG_FILE"
}

# Function to check and optimize MySQL
optimize_mysql() {
    echo "--- Checking MySQL Performance ---" | tee -a "$LOG_FILE"
    
    # Check for long running queries
    LONG_QUERIES=$(mysql -e "SHOW PROCESSLIST" 2>/dev/null | grep -v Sleep | grep -v "SHOW PROCESSLIST" | wc -l)
    echo "Active MySQL queries: $LONG_QUERIES" | tee -a "$LOG_FILE"
    
    # Kill queries running longer than 30 seconds if too many
    if [ "$LONG_QUERIES" -gt 10 ]; then
        echo "Too many active queries, killing long-running ones..." | tee -a "$LOG_FILE"
        mysql -e "SELECT ID FROM INFORMATION_SCHEMA.PROCESSLIST WHERE COMMAND != 'Sleep' AND TIME > 30" 2>/dev/null | while read id; do
            if [ ! -z "$id" ] && [ "$id" != "ID" ]; then
                mysql -e "KILL $id" 2>/dev/null && echo "Killed query ID: $id" | tee -a "$LOG_FILE"
            fi
        done
    fi
    
    echo "" | tee -a "$LOG_FILE"
}

# Function to check system resources after optimization
check_post_optimization() {
    echo "--- Post-Optimization Status ---" | tee -a "$LOG_FILE"
    
    # CPU usage
    CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    echo "CPU Usage: ${CPU_USAGE}%" | tee -a "$LOG_FILE"
    
    # Load average
    LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}')
    echo "Load Average:$LOAD_AVG" | tee -a "$LOG_FILE"
    
    # Memory usage
    FREE_MEM=$(free -h | awk '/^Mem:/ {print $7}')
    echo "Available Memory: $FREE_MEM" | tee -a "$LOG_FILE"
    
    # Active processes
    PHP_COUNT=$(pgrep php-fpm | wc -l)
    echo "PHP-FPM Processes: $PHP_COUNT" | tee -a "$LOG_FILE"
    
    echo "" | tee -a "$LOG_FILE"
}

# Execute optimization steps
kill_high_cpu_processes
sleep 5
restart_php_fpm
sleep 10
clear_magento_cache
optimize_redis
optimize_mysql
check_post_optimization

echo "=== CPU Optimization Complete - $(date '+%Y-%m-%d %H:%M:%S') ===" | tee -a "$LOG_FILE"
echo "Recommended: Monitor system for next 30 minutes to ensure stability" | tee -a "$LOG_FILE"
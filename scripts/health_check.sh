#!/bin/bash
# Redis and Varnish Health & Status Check Script
# Location: /home/technadminy7/public_html/scripts/health_check.sh

LOG_FILE="/home/technadminy7/public_html/scripts/health_check.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "=== System Health Check - $DATE ===" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# Function to check Redis status
check_redis() {
    echo "--- Redis Status ---" >> "$LOG_FILE"
    
    # Check if Redis is running
    if redis-cli ping > /dev/null 2>&1; then
        echo "✓ Redis is running" >> "$LOG_FILE"
        
        # Get memory info
        MEMORY_INFO=$(redis-cli info memory | grep -E "used_memory_human|maxmemory_human|mem_fragmentation_ratio")
        echo "Memory Usage:" >> "$LOG_FILE"
        echo "$MEMORY_INFO" >> "$LOG_FILE"
        
        # Get key statistics
        KEY_STATS=$(redis-cli dbsize)
        echo "Total Keys: $KEY_STATS" >> "$LOG_FILE"
        
        # Check cache keys specifically
        CACHE_KEYS=$(redis-cli KEYS "zc:*" | wc -l)
        echo "Cache Keys (zc:*): $CACHE_KEYS" >> "$LOG_FILE"
        
        # Get uptime
        UPTIME=$(redis-cli info server | grep uptime_in_seconds | cut -d: -f2)
        echo "Uptime: $UPTIME seconds" >> "$LOG_FILE"
        
    else
        echo "✗ Redis is NOT running!" >> "$LOG_FILE"
        echo "Attempting to restart Redis..." >> "$LOG_FILE"
        sudo systemctl restart redis 2>>"$LOG_FILE" && echo "Redis restarted successfully" >> "$LOG_FILE" || echo "Failed to restart Redis" >> "$LOG_FILE"
    fi
    
    echo "" >> "$LOG_FILE"
}

# Function to check Varnish status
check_varnish() {
    echo "--- Varnish Status ---" >> "$LOG_FILE"
    
    # Check if Varnish is running
    if pgrep varnishd > /dev/null 2>&1; then
        echo "✓ Varnish is running" >> "$LOG_FILE"
        
        # Get basic stats
        VARNISH_STATS=$(varnishstat -1 2>/dev/null | head -10)
        if [ $? -eq 0 ]; then
            echo "Varnish Statistics (top 10):" >> "$LOG_FILE"
            echo "$VARNISH_STATS" >> "$LOG_FILE"
        else
            echo "Unable to retrieve Varnish statistics" >> "$LOG_FILE"
        fi
        
        # Check cache hit ratio if available
        HIT_RATE=$(varnishstat -1 -f MAIN.cache_hit -f MAIN.cache_miss 2>/dev/null | awk '{sum+=$2} END {if(sum>0) print ($1/sum)*100}')
        if [ ! -z "$HIT_RATE" ]; then
            echo "Cache Hit Rate: ${HIT_RATE}% (approximate)" >> "$LOG_FILE"
        fi
        
        # Get Varnish version
        VARNISH_VERSION=$(varnishd -V 2>&1 | head -1)
        echo "Version: $VARNISH_VERSION" >> "$LOG_FILE"
        
    else
        echo "✗ Varnish is NOT running!" >> "$LOG_FILE"
        echo "Attempting to restart Varnish..." >> "$LOG_FILE"
        sudo systemctl restart varnish 2>>"$LOG_FILE" && echo "Varnish restarted successfully" >> "$LOG_FILE" || echo "Failed to restart Varnish" >> "$LOG_FILE"
    fi
    
    echo "" >> "$LOG_FILE"
}

# Function to check system resources
check_system_resources() {
    echo "--- System Resources ---" >> "$LOG_FILE"
    
    # CPU usage
    CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    echo "CPU Usage: ${CPU_USAGE}%" >> "$LOG_FILE"
    
    # Memory usage
    MEM_INFO=$(free -h | grep Mem)
    echo "Memory: $MEM_INFO" >> "$LOG_FILE"
    
    # Disk usage
    DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}')
    echo "Root Disk Usage: $DISK_USAGE" >> "$LOG_FILE"
    
    # Load average
    LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}')
    echo "Load Average:$LOAD_AVG" >> "$LOG_FILE"
    
    echo "" >> "$LOG_FILE"
}

# Function to check web server status
check_web_server() {
    echo "--- Web Server Status ---" >> "$LOG_FILE"
    
    # Check Apache/Nginx
    if pgrep apache2 > /dev/null 2>&1; then
        echo "✓ Apache is running" >> "$LOG_FILE"
        APACHE_STATUS=$(systemctl is-active apache2 2>/dev/null)
        echo "Apache Status: $APACHE_STATUS" >> "$LOG_FILE"
    elif pgrep nginx > /dev/null 2>&1; then
        echo "✓ Nginx is running" >> "$LOG_FILE"
        NGINX_STATUS=$(systemctl is-active nginx 2>/dev/null)
        echo "Nginx Status: $NGINX_STATUS" >> "$LOG_FILE"
    else
        echo "✗ No web server detected running" >> "$LOG_FILE"
    fi
    
    echo "" >> "$LOG_FILE"
}

# Function to check Magento-specific services
check_magento_services() {
    echo "--- Magento Services ---" >> "$LOG_FILE"
    
    # Check PHP-FPM if running
    if pgrep php-fpm > /dev/null 2>&1; then
        echo "✓ PHP-FPM is running" >> "$LOG_FILE"
    fi
    
    # Check MySQL/MariaDB
    if mysqladmin ping > /dev/null 2>&1; then
        echo "✓ Database is accessible" >> "$LOG_FILE"
    else
        echo "✗ Database connection issue" >> "$LOG_FILE"
    fi
    
    echo "" >> "$LOG_FILE"
}

# Run all checks
check_redis
check_varnish
check_system_resources
check_web_server
check_magento_services

echo "=== Health Check Complete ===" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"
#!/bin/bash

# Emergency CPU Optimization Script
# Date: January 27, 2026
# Purpose: Reduce server load and optimize resource usage

LOG_FILE="/home/technadminy7/public_html/var/log/cpu_emergency_optimization.log"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "=== EMERGENCY CPU OPTIMIZATION STARTED ==="

# 1. Kill runaway PHP processes
log_message "Step 1: Terminating high CPU PHP processes"
pkill -f "php-fpm.*technostationery" 2>/dev/null
sleep 3

# 2. Optimize PHP-FPM configuration
log_message "Step 2: Optimizing PHP-FPM configuration"
PHP_FPM_CONF="/opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf"

# Backup current config
cp "$PHP_FPM_CONF" "${PHP_FPM_CONF}.emergency_backup.$(date +%Y%m%d_%H%M%S)"

# Apply emergency limits
sed -i 's/pm.max_children = [0-9]*/pm.max_children = 10/' "$PHP_FPM_CONF"
sed -i 's/pm.start_servers = [0-9]*/pm.start_servers = 2/' "$PHP_FPM_CONF"
sed -i 's/pm.min_spare_servers = [0-9]*/pm.min_spare_servers = 1/' "$PHP_FPM_CONF"
sed -i 's/pm.max_spare_servers = [0-9]*/pm.max_spare_servers = 3/' "$PHP_FPM_CONF"
sed -i 's/pm.max_requests = [0-9]*/pm.max_requests = 200/' "$PHP_FPM_CONF"
sed -i 's/pm.process_idle_timeout = [0-9]*/pm.process_idle_timeout = 30/' "$PHP_FPM_CONF"

# 3. Restart services
log_message "Step 3: Restarting PHP-FPM and Apache"
/usr/local/cpanel/scripts/restartsrv_apache_php_fpm >> "$LOG_FILE" 2>&1
systemctl restart httpd >> "$LOG_FILE" 2>&1

# 4. Temporarily disable resource-heavy services
log_message "Step 4: Managing resource-heavy services"
systemctl stop elasticsearch 2>/dev/null

# 5. Clear caches and temporary files
log_message "Step 5: Clearing caches and temporary files"
find /home/technadminy7/public_html/var/cache/ -type f -mtime +1 -delete 2>/dev/null
find /home/technadminy7/public_html/var/page_cache/ -type f -mtime +1 -delete 2>/dev/null
find /tmp -name "sess_*" -mtime +1 -delete 2>/dev/null

# 6. Optimize database connections
log_message "Step 6: Optimizing database connections"
mysql -e "SHOW PROCESSLIST;" 2>/dev/null | grep -E "(Sleep|Query)" | awk '{print $1}' | xargs -I {} mysql -e "KILL {};" 2>/dev/null

log_message "=== EMERGENCY CPU OPTIMIZATION COMPLETED ==="
log_message ""

# Show final status
echo "Emergency optimization completed. Current status:"
top -b -n 1 | head -10
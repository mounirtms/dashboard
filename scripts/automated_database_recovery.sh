#!/bin/bash

# Automated Database Recovery Script
# Automatically recovers from database connection issues

LOG_FILE="/home/technadminy7/public_html/var/log/database_recovery.log"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Function to check if database is responsive
is_database_responsive() {
    /usr/bin/mariadb -u technadminy7_ntdbusr24 -p -h localhost -P 3307 technadminy7_dBT8x12y22 -e "SELECT 1 LIMIT 1;" > /dev/null 2>&1
    return $?
}

# Function to restart database services
restart_database_services() {
    log_message "Attempting to restart database services..."
    
    # Stop database gracefully
    sudo systemctl stop mariadb
    sleep 5
    
    # Start database
    sudo systemctl start mariadb
    
    # Wait for startup
    sleep 10
    
    # Check if running
    if systemctl is-active --quiet mariadb; then
        log_message "✓ Database service restarted successfully"
        return 0
    else
        log_message "✗ Database service failed to start"
        return 1
    fi
}

# Function to optimize database after recovery
optimize_database() {
    log_message "Optimizing database after recovery..."
    
    # Run basic optimization queries
    /usr/bin/mariadb -u technadminy7_ntdbusr24 -p -h localhost -P 3307 technadminy7_dBT8x12y22 -e "
        OPTIMIZE TABLE cron_schedule;
        ANALYZE TABLE cron_schedule;
        FLUSH QUERY CACHE;
    " > /dev/null 2>&1
    
    if [ $? -eq 0 ]; then
        log_message "✓ Database optimization completed"
    else
        log_message "⚠ Database optimization failed"
    fi
}

# Function to clear Magento cache
clear_magento_cache() {
    log_message "Clearing Magento cache..."
    
    cd /home/technadminy7/public_html
    php bin/magento cache:flush > /dev/null 2>&1
    
    if [ $? -eq 0 ]; then
        log_message "✓ Magento cache cleared"
    else
        log_message "⚠ Magento cache clearing failed"
    fi
}

# Function to restart web services
restart_web_services() {
    log_message "Restarting web services..."
    
    # Restart PHP-FPM pools
    sudo systemctl restart ea-php82-php-fpm
    sudo systemctl restart ea-php83-php-fpm
    
    # Restart Apache
    sudo systemctl restart httpd
    
    log_message "✓ Web services restarted"
}

# Main recovery process
perform_recovery() {
    log_message "=== AUTOMATED DATABASE RECOVERY STARTED ==="
    
    # Step 1: Check current database status
    log_message "Step 1: Checking database status..."
    if is_database_responsive; then
        log_message "Database is responsive, no recovery needed"
        return 0
    else
        log_message "Database is not responsive, proceeding with recovery"
    fi
    
    # Step 2: Restart database services
    log_message "Step 2: Restarting database services..."
    if ! restart_database_services; then
        log_message "FATAL: Unable to restart database services"
        return 1
    fi
    
    # Step 3: Wait for database to stabilize
    log_message "Step 3: Waiting for database stabilization..."
    sleep 30
    
    # Step 4: Verify database is now responsive
    log_message "Step 4: Verifying database connectivity..."
    for i in {1..10}; do
        if is_database_responsive; then
            log_message "✓ Database is now responsive"
            break
        else
            log_message "Waiting for database... (attempt $i/10)"
            sleep 10
        fi
    done
    
    if ! is_database_responsive; then
        log_message "FATAL: Database remains unresponsive after recovery attempts"
        return 1
    fi
    
    # Step 5: Optimize database
    log_message "Step 5: Optimizing database..."
    optimize_database
    
    # Step 6: Clear caches
    log_message "Step 6: Clearing caches..."
    clear_magento_cache
    
    # Step 7: Restart web services
    log_message "Step 7: Restarting web services..."
    restart_web_services
    
    log_message "=== AUTOMATED DATABASE RECOVERY COMPLETED SUCCESSFULLY ==="
    return 0
}

# Run the recovery
perform_recovery
exit $?
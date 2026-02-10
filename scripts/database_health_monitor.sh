#!/bin/bash

# Database Health Monitor Script
# Monitors database connectivity and performance

LOG_FILE="/home/technadminy7/public_html/var/log/database_health.log"
DB_HOST="localhost"
DB_PORT="3307"
DB_USER="technadminy7_ntdbusr24"
DB_NAME="technadminy7_dBT8x12y22"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Test database connectivity
test_database_connection() {
    log_message "Testing database connectivity..."
    
    # Simple connection test
    CONNECTION_TEST=$(/usr/bin/mariadb -u "$DB_USER" -p"$DB_PASSWORD" -h "$DB_HOST" -P "$DB_PORT" -e "SELECT 1;" 2>&1)
    
    if [ $? -eq 0 ]; then
        log_message "✓ Database connection successful"
        return 0
    else
        log_message "✗ Database connection failed: $CONNECTION_TEST"
        return 1
    fi
}

# Check database performance metrics
check_database_performance() {
    log_message "Checking database performance metrics..."
    
    PERFORMANCE_METRICS=$(/usr/bin/mariadb -u "$DB_USER" -p"$DB_PASSWORD" -h "$DB_HOST" -P "$DB_PORT" -e "
        SHOW STATUS LIKE 'Threads_connected';
        SHOW STATUS LIKE 'Threads_running';
        SHOW STATUS LIKE 'Aborted_connects';
        SHOW STATUS LIKE 'Connection_errors_internal';
        SHOW ENGINE INNODB STATUS\G;
    " 2>&1)
    
    echo "$PERFORMANCE_METRICS" >> "$LOG_FILE"
}

# Check for connection issues
check_connection_issues() {
    log_message "Checking for connection issues..."
    
    # Check aborted connections
    ABORTED_CONNECTIONS=$(/usr/bin/mariadb -u "$DB_USER" -p"$DB_PASSWORD" -h "$DB_HOST" -P "$DB_PORT" -e "
        SELECT VARIABLE_VALUE as aborted_connections 
        FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
        WHERE VARIABLE_NAME = 'Aborted_connects';
    " 2>&1 | tail -1)
    
    log_message "Aborted connections: $ABORTED_CONNECTIONS"
    
    # Check current connections
    CURRENT_CONNECTIONS=$(/usr/bin/mariadb -u "$DB_USER" -p"$DB_PASSWORD" -h "$DB_HOST" -P "$DB_PORT" -e "
        SELECT VARIABLE_VALUE as current_connections 
        FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
        WHERE VARIABLE_NAME = 'Threads_connected';
    " 2>&1 | tail -1)
    
    log_message "Current connections: $CURRENT_CONNECTIONS"
}

# Restart database if critical issues detected
restart_database_if_needed() {
    local connection_failures=$(grep -c "Connection refused" "$LOG_FILE" | tail -1)
    
    if [ "$connection_failures" -gt 5 ]; then
        log_message "CRITICAL: Multiple connection failures detected, restarting database..."
        
        # Restart MariaDB service
        sudo systemctl restart mariadb
        
        if [ $? -eq 0 ]; then
            log_message "✓ Database restarted successfully"
        else
            log_message "✗ Failed to restart database"
        fi
    fi
}

# Main execution
log_message "=== Database Health Check Started ==="

test_database_connection
if [ $? -eq 0 ]; then
    check_database_performance
    check_connection_issues
    restart_database_if_needed
else
    log_message "EMERGENCY: Database is unreachable!"
    # Send alert notification
    echo "CRITICAL: Database connection failed at $(date)" | mail -s "Database Alert" admin@technostationery.com
fi

log_message "=== Database Health Check Completed ==="
log_message ""
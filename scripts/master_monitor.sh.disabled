#!/bin/bash

# Master System Monitor Script
# Coordinates all monitoring activities to prevent resource conflicts

BASE_DIR="/home/technadminy7/public_html"
SCRIPTS_DIR="$BASE_DIR/scripts"
LOG_DIR="$BASE_DIR/var/log"

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR"

MASTER_LOG="$LOG_DIR/master_monitor.log"
LAST_RUN_FILE="$LOG_DIR/.master_monitor_last_run"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$MASTER_LOG"
}

# Check if we should run based on time intervals
should_run_check() {
    local check_name=$1
    local interval_minutes=$2
    
    local last_run_file="$LOG_DIR/.${check_name}_last_run"
    
    if [ ! -f "$last_run_file" ]; then
        echo "true"
        return
    fi
    
    local last_run=$(cat "$last_run_file")
    local current_time=$(date +%s)
    local time_diff=$((current_time - last_run))
    local interval_seconds=$((interval_minutes * 60))
    
    if [ "$time_diff" -ge "$interval_seconds" ]; then
        echo "true"
    else
        echo "false"
    fi
}

# Update last run timestamp
update_last_run() {
    local check_name=$1
    local last_run_file="$LOG_DIR/.${check_name}_last_run"
    date +%s > "$last_run_file"
}

# Quick system health assessment
quick_health_check() {
    log_message "Performing quick health assessment..."
    
    # Very lightweight checks
    CPU_USAGE=$(awk '/^cpu / {usage=($2+$4)*100/($2+$3+$4+$5)} END {print int(usage)}' /proc/stat)
    LOAD_AVERAGE=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
    MEM_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    
    log_message "Quick check - CPU: ${CPU_USAGE}%, Load: ${LOAD_AVERAGE}, Memory: ${MEM_USAGE}%"
    
    # Only trigger intensive checks if thresholds exceeded
    if [ "$CPU_USAGE" -gt 70 ] || (( $(echo "$LOAD_AVERAGE > 10.0" | bc -l) )) || [ "$MEM_USAGE" -gt 80 ]; then
        log_message "Thresholds exceeded, triggering detailed checks"
        return 0  # true
    else
        return 1  # false
    fi
}

# Coordinate all monitoring activities
main() {
    log_message "=== Master Monitor Started ==="
    
    # Always run quick check
    if quick_health_check; then
        # Run intensive checks only when needed
        
        # Run enhanced health check (every 30 minutes max)
        if [ "$(should_run_check "health" 30)" = "true" ]; then
            log_message "Running enhanced health check"
            "$SCRIPTS_DIR/enhanced_health_check.sh"
            update_last_run "health"
        fi
        
        # Run optimized CPU manager (every 15 minutes max)
        if [ "$(should_run_check "cpu_manage" 15)" = "true" ]; then
            log_message "Running CPU manager"
            "$SCRIPTS_DIR/cpu_manager_optimized.sh"
            update_last_run "cpu_manage"
        fi
        
        # Run kill script if critical (every 5 minutes max)
        if [ "$(should_run_check "kill_php" 5)" = "true" ]; then
            log_message "Running PHP killer script"
            "$BASE_DIR/kill_high_cpu_php.sh"
            update_last_run "kill_php"
        fi
    else
        log_message "System appears healthy, skipping intensive checks"
    fi
    
    log_message "=== Master Monitor Completed ==="
    log_message ""
}

# Run the monitor
main
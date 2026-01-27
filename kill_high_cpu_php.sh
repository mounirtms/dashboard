#!/bin/bash

# Kill High CPU PHP Processes Script
# Location: /home/technadminy7/public_html/kill_high_cpu_php.sh

LOG_FILE="/home/technadminy7/public_html/var/log/kill_high_cpu_php.log"
CPU_THRESHOLD=40  # Kill processes using more than 40% CPU
MIN_RUNTIME=30    # Only kill processes running longer than 30 seconds

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "=== Kill High CPU PHP Script Started ==="

# Find high CPU PHP processes
HIGH_CPU_PROCESSES=$(ps aux | awk -v threshold="$CPU_THRESHOLD" -v min_time="$MIN_RUNTIME" '
    $11 ~ /php/ && $3 > threshold && $10 ~ /:/ {
        # Convert runtime to seconds for comparison
        split($10, time_parts, ":");
        if (length(time_parts) == 3) {
            # Format: HH:MM:SS
            runtime_sec = time_parts[1]*3600 + time_parts[2]*60 + time_parts[3];
        } else if (length(time_parts) == 2) {
            # Format: MM:SS
            runtime_sec = time_parts[1]*60 + time_parts[2];
        } else {
            runtime_sec = 0;
        }
        if (runtime_sec > min_time) {
            print $2 " " $3 " " $10 " " $11;
        }
    }
')

if [ -n "$HIGH_CPU_PROCESSES" ]; then
    log_message "Found $(echo "$HIGH_CPU_PROCESSES" | wc -l) high CPU PHP processes:"
    echo "$HIGH_CPU_PROCESSES" | while read pid cpu_time runtime command; do
        log_message "Killing PID: $pid (CPU: ${cpu_time}%, Runtime: $runtime, Command: $command)"
        kill -TERM "$pid" 2>/dev/null
        
        # Wait 5 seconds and check if process is still running
        sleep 5
        if ps -p "$pid" > /dev/null 2>&1; then
            log_message "Process $pid still running, sending SIGKILL"
            kill -9 "$pid" 2>/dev/null
        fi
    done
    
    # Restart PHP-FPM to clear any stuck workers
    log_message "Restarting PHP-FPM to refresh worker pool..."
    /usr/local/cpanel/scripts/restartsrv_apache_php_fpm >> "$LOG_FILE" 2>&1
else
    log_message "No high CPU PHP processes found above threshold"
fi

log_message "=== Kill High CPU PHP Script Completed ==="
log_message ""
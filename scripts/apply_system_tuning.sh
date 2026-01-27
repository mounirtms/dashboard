#!/bin/bash

# System Memory Tuning Script
# Applies kernel-level memory optimizations for Magento

LOG_FILE="/home/technadminy7/public_html/var/log/system_tuning.log"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "=== System Memory Tuning Started ==="

# Apply kernel parameters
while read -r line; do
    if [[ $line == *=* ]] && [[ ! $line == \#* ]]; then
        param=$(echo "$line" | cut -d'=' -f1 | tr -d ' ')
        value=$(echo "$line" | cut -d'=' -f2 | tr -d ' ')
        
        if [ -n "$param" ] && [ -n "$value" ]; then
            echo "$value" > "/proc/sys/$param" 2>/dev/null
            if [ $? -eq 0 ]; then
                log_message "Applied: $param = $value"
            else
                log_message "Failed to apply: $param = $value"
            fi
        fi
    fi
done < "/home/technadminy7/public_html/config/system_memory_tuning.conf"

# Increase limits for the user
echo "technadminy7 soft nofile 100000" >> /etc/security/limits.conf
echo "technadminy7 hard nofile 100000" >> /etc/security/limits.conf

log_message "=== System Memory Tuning Completed ==="
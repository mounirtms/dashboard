#!/bin/bash

# CPU System Tuning Script
# Applies kernel-level CPU optimizations

LOG_FILE="/home/betapublic_html/var/log/cpu_system_tuning.log"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "=== CPU System Tuning Started ==="

# Apply CPU scheduler parameters
while read -r line; do
    if [[ $line == *=* ]] && [[ ! $line == \#* ]]; then
        param=$(echo "$line" | cut -d'=' -f1 | tr -d ' ')
        value=$(echo "$line" | cut -d'=' -f2 | tr -d ' ')
        
        if [ -n "$param" ] && [ -n "$value" ]; then
            echo "$value" > "/proc/sys/$param" 2>/dev/null
            if [ $? -eq 0 ]; then
                log_message "Applied CPU parameter: $param = $value"
            else
                log_message "Failed to apply CPU parameter: $param = $value"
            fi
        fi
    fi
done < "/home/betapublic_html/config/cpu_tuning.conf"

# Disable CPU frequency scaling throttling
echo performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor 2>/dev/null
if [ $? -eq 0 ]; then
    log_message "Set CPU governor to performance mode"
fi

# Optimize network processing for CPU efficiency
echo 1 > /proc/sys/net/core/busy_poll 2>/dev/null
echo 50 > /proc/sys/net/core/busy_read 2>/dev/null

log_message "=== CPU System Tuning Completed ==="
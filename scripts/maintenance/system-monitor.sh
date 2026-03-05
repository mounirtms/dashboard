#!/bin/bash

# System monitoring script for Magento server
LOG_DIR="/home/technadminy7/public_html/monitoring"
DURATION=600  # 10 minutes in seconds
INTERVAL=30   # Check every 30 seconds

mkdir -p $LOG_DIR

echo "Starting system monitoring for 10 minutes..."
echo "Logging to $LOG_DIR"

# Function to log system stats
log_stats() {
    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
    echo "=== $TIMESTAMP ===" >> $LOG_DIR/system-stats.log
    
    # CPU and memory
    echo "CPU/Memory:" >> $LOG_DIR/system-stats.log
    vmstat 1 1 >> $LOG_DIR/system-stats.log
    
    # Disk I/O
    echo "Disk I/O:" >> $LOG_DIR/system-stats.log
    iostat -x 1 1 >> $LOG_DIR/system-stats.log
    
    # Network
    echo "Network:" >> $LOG_DIR/system-stats.log
    sar -n DEV 1 1 >> $LOG_DIR/system-stats.log
    
    # Redis stats
    echo "Redis:" >> $LOG_DIR/system-stats.log
    redis-cli -h 127.0.0.1 -p 6379 info | grep -E "used_memory_human|connected_clients|total_commands_processed" >> $LOG_DIR/system-stats.log
    
    # Elasticsearch stats
    echo "Elasticsearch:" >> $LOG_DIR/system-stats.log
    curl -s -X GET "localhost:9200/_nodes/stats/os,jvm?pretty" | grep -E "mem|cpu|heap_used_percent" >> $LOG_DIR/system-stats.log
    
    echo "" >> $LOG_DIR/system-stats.log
}

# Run monitoring for specified duration
END_TIME=$(($(date +%s) + $DURATION))
while [ $(date +%s) -lt $END_TIME ]; do
    log_stats
    sleep $INTERVAL
done

echo "Monitoring completed. Logs saved to $LOG_DIR/system-stats.log"
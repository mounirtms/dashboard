#!/bin/bash
LOG="/home/technadminy7/public_html/20min_monitor_$(date +%Y%m%d_%H%M%S).log"
echo "=== 20-MINUTE SYSTEM MONITORING ===" > $LOG
echo "Start Time: $(date)" >> $LOG
echo "" >> $LOG

for i in {1..240}; do  # 240 iterations * 5 seconds = 20 minutes
    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
    LOAD=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    
    # Get top 5 CPU processes
    echo "[$TIMESTAMP] Load: $LOAD" >> $LOG
    ps aux --sort=-%cpu | head -6 | tail -5 | awk '{printf "  %-10s %5s%% CPU %5s%% MEM %s\n", $1, $3, $4, $11}' >> $LOG
    
    # Count PHP-FPM workers
    PHP_WORKERS=$(ps aux | grep "php-fpm: pool" | grep -v grep | wc -l)
    echo "  PHP-FPM Workers: $PHP_WORKERS" >> $LOG
    
    # Check MariaDB connections
    MYSQL_CONN=$(/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -N -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | awk '{print $2}')
    echo "  MariaDB Connections: $MYSQL_CONN" >> $LOG
    echo "" >> $LOG
    
    sleep 5
done

echo "" >> $LOG
echo "End Time: $(date)" >> $LOG
echo "=== MONITORING COMPLETE ===" >> $LOG
echo "Log saved to: $LOG"

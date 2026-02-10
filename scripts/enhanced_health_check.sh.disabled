#!/bin/bash
# Enhanced Health Check Script with Realistic Metrics
# Location: /home/technadminy7/public_html/scripts/enhanced_health_check.sh

LOG_FILE="/home/technadminy7/public_html/scripts/enhanced_health_check.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "=== Enhanced System Health Check - $DATE ===" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# Function to check system health with realistic thresholds
check_system_health() {
    echo "--- System Health Assessment ---" >> "$LOG_FILE"
    
    HEALTH_SCORE=100
    ISSUES_FOUND=()
    
    # CPU Health Check
    CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print int($2)}')
    echo "CPU Usage: ${CPU_USAGE}%" >> "$LOG_FILE"
    
    if [ "$CPU_USAGE" -gt 80 ]; then
        HEALTH_SCORE=$((HEALTH_SCORE - 30))
        ISSUES_FOUND+=("High CPU usage: ${CPU_USAGE}%")
    elif [ "$CPU_USAGE" -gt 60 ]; then
        HEALTH_SCORE=$((HEALTH_SCORE - 15))
        ISSUES_FOUND+=("Moderate CPU usage: ${CPU_USAGE}%")
    fi
    
    # Load Average Check
    LOAD_1MIN=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
    LOAD_5MIN=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $2}' | tr -d ',')
    LOAD_15MIN=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $3}' | tr -d ',')
    
    CORES=$(nproc)
    echo "Load Average: ${LOAD_1MIN}, ${LOAD_5MIN}, ${LOAD_15MIN} (cores: $CORES)" >> "$LOG_FILE"
    
    # Normalize load to CPU cores
    NORMALIZED_LOAD=$(echo "$LOAD_1MIN $CORES" | awk '{print $1/$2}')
    
    if (( $(echo "$NORMALIZED_LOAD > 2.0" | bc -l) )); then
        HEALTH_SCORE=$((HEALTH_SCORE - 25))
        ISSUES_FOUND+=("Critical system load: ${LOAD_1MIN}")
    elif (( $(echo "$NORMALIZED_LOAD > 1.0" | bc -l) )); then
        HEALTH_SCORE=$((HEALTH_SCORE - 10))
        ISSUES_FOUND+=("High system load: ${LOAD_1MIN}")
    fi
    
    # Memory Health Check
    MEM_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    echo "Memory Usage: ${MEM_USAGE}%" >> "$LOG_FILE"
    
    if [ "$MEM_USAGE" -gt 85 ]; then
        HEALTH_SCORE=$((HEALTH_SCORE - 20))
        ISSUES_FOUND+=("High memory usage: ${MEM_USAGE}%")
    elif [ "$MEM_USAGE" -gt 70 ]; then
        HEALTH_SCORE=$((HEALTH_SCORE - 10))
        ISSUES_FOUND+=("Moderate memory usage: ${MEM_USAGE}%")
    fi
    
    # Disk Space Check
    DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | tr -d '%')
    echo "Disk Usage: ${DISK_USAGE}%" >> "$LOG_FILE"
    
    if [ "$DISK_USAGE" -gt 90 ]; then
        HEALTH_SCORE=$((HEALTH_SCORE - 25))
        ISSUES_FOUND+=("Critical disk usage: ${DISK_USAGE}%")
    elif [ "$DISK_USAGE" -gt 80 ]; then
        HEALTH_SCORE=$((HEALTH_SCORE - 15))
        ISSUES_FOUND+=("High disk usage: ${DISK_USAGE}%")
    fi
    
    # Process Count Check
    TOTAL_PROCESSES=$(ps aux | wc -l)
    echo "Total Processes: $TOTAL_PROCESSES" >> "$LOG_FILE"
    
    if [ "$TOTAL_PROCESSES" -gt 400 ]; then
        HEALTH_SCORE=$((HEALTH_SCORE - 15))
        ISSUES_FOUND+=("Too many processes: $TOTAL_PROCESSES")
    fi
    
    echo "" >> "$LOG_FILE"
    return $HEALTH_SCORE
}

# Function to check service responsiveness
check_service_responsiveness() {
    echo "--- Service Responsiveness ---" >> "$LOG_FILE"
    
    # Web server response time
    if command -v curl >/dev/null 2>&1; then
        RESPONSE_TIME=$(curl -o /dev/null -s -w "%{time_total}\n" http://localhost 2>/dev/null | head -1)
        if [ -n "$RESPONSE_TIME" ]; then
            echo "Homepage Response Time: ${RESPONSE_TIME}s" >> "$LOG_FILE"
            
            RESPONSE_MS=$(echo "$RESPONSE_TIME" | awk '{print int($1 * 1000)}')
            if [ "$RESPONSE_MS" -gt 5000 ]; then
                # Don't penalize heavily for slow response - it's a symptom, not cause
                echo "Slow response detected (>5s)" >> "$LOG_FILE"
            fi
        else
            echo "Cannot reach web server" >> "$LOG_FILE"
        fi
    fi
    
    # Database connection test
    if mysqladmin ping >/dev/null 2>&1; then
        echo "Database Connection: OK" >> "$LOG_FILE"
        DB_RESPONSE=$(mysql -e "SELECT 1" 2>&1 | wc -l)
        if [ "$DB_RESPONSE" -lt 1 ]; then
            echo "Database query slow or failing" >> "$LOG_FILE"
        fi
    else
        echo "Database Connection: FAILED" >> "$LOG_FILE"
    fi
    
    echo "" >> "$LOG_FILE"
}

# Function to check resource-intensive processes
check_resource_hogs() {
    echo "--- Resource Intensive Processes ---" >> "$LOG_FILE"
    
    echo "Top 10 CPU consumers:" >> "$LOG_FILE"
    ps aux --sort=-%cpu | head -11 >> "$LOG_FILE"
    
    echo "" >> "$LOG_FILE"
    echo "Processes using >30% CPU:" >> "$LOG_FILE"
    ps aux | awk '$3 > 30 {print $2 " " $3 "% " $11}' >> "$LOG_FILE"
    
    echo "" >> "$LOG_FILE"
}

# Function to provide recommendations
provide_recommendations() {
    echo "--- Recommendations ---" >> "$LOG_FILE"
    
    if [ "$HEALTH_SCORE" -lt 70 ]; then
        echo "⚠️ CRITICAL: System health is poor ($HEALTH_SCORE/100)" >> "$LOG_FILE"
        echo "Immediate actions needed:" >> "$LOG_FILE"
        for issue in "${ISSUES_FOUND[@]}"; do
            echo "  - $issue" >> "$LOG_FILE"
        done
        echo "  - Consider restarting services" >> "$LOG_FILE"
        echo "  - Check for DDoS or traffic spikes" >> "$LOG_FILE"
    elif [ "$HEALTH_SCORE" -lt 90 ]; then
        echo "⚠️ WARNING: System health is moderate ($HEALTH_SCORE/100)" >> "$LOG_FILE"
        echo "Monitor these issues:" >> "$LOG_FILE"
        for issue in "${ISSUES_FOUND[@]}"; do
            echo "  - $issue" >> "$LOG_FILE"
        done
    else
        echo "✅ GOOD: System health is acceptable ($HEALTH_SCORE/100)" >> "$LOG_FILE"
        echo "No critical issues detected" >> "$LOG_FILE"
    fi
    
    echo "" >> "$LOG_FILE"
}

# Run all checks
check_system_health
HEALTH_SCORE=$?
check_service_responsiveness
check_resource_hogs
provide_recommendations

echo "=== Enhanced Health Check Complete (Score: $HEALTH_SCORE/100) ===" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"
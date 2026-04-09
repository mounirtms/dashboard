#!/bin/bash
################################################################################
# Automated Alert System
# Version: 1.0.0
# Date: 2026-04-10
# Description: Monitors system and sends alerts for critical conditions
################################################################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
ALERT_LOG="/home/dashboard/public_html/logs/alerts_$(date +%Y-%m-%d).log"
ALERT_HISTORY="/home/dashboard/public_html/logs/alert_history.txt"
STATE_FILE="/tmp/alert_system_state.txt"

# Thresholds
LOAD_CRITICAL=12.0
LOAD_WARNING=8.0
CPU_CRITICAL=95
CPU_WARNING=80
MEM_CRITICAL=90
MEM_WARNING=80
DISK_CRITICAL=95
DISK_WARNING=85

# Alert cooldown (seconds) - prevent alert spam
COOLDOWN_PERIOD=1800  # 30 minutes

# Notification methods
ENABLE_LOG=true
ENABLE_EMAIL=false  # Set to true to enable email alerts
ADMIN_EMAIL="admin@technostationery.com"

# Initialize state file
if [[ ! -f "$STATE_FILE" ]]; then
    echo "last_critical_alert=0" > "$STATE_FILE"
    echo "last_warning_alert=0" >> "$STATE_FILE"
    echo "last_load_alert=0" >> "$STATE_FILE"
    echo "last_cpu_alert=0" >> "$STATE_FILE"
    echo "last_mem_alert=0" >> "$STATE_FILE"
fi

# Load state
source "$STATE_FILE"

# Get current timestamp
now=$(date +%s)

# Logging function
log_alert() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo "[$timestamp] [$level] $message" | tee -a "$ALERT_LOG"
    echo "[$timestamp] [$level] $message" >> "$ALERT_HISTORY"
}

# Check if alert should be sent (respect cooldown)
should_send_alert() {
    local alert_type="$1"
    local last_alert_var="last_${alert_type}_alert"
    local last_alert="${!last_alert_var}"
    
    if [[ -z "$last_alert" ]]; then
        last_alert=0
    fi
    
    local time_since=$((now - last_alert))
    
    if [[ $time_since -gt $COOLDOWN_PERIOD ]]; then
        return 0  # Should send
    else
        return 1  # In cooldown
    fi
}

# Update alert timestamp
update_alert_time() {
    local alert_type="$1"
    sed -i "s/^last_${alert_type}_alert=.*/last_${alert_type}_alert=$now/" "$STATE_FILE"
}

# Send notification
send_notification() {
    local subject="$1"
    local body="$2"
    local level="$3"
    
    # Log to file
    if [[ "$ENABLE_LOG" == "true" ]]; then
        log_alert "$level" "$subject: $body"
    fi
    
    # Send email (if enabled)
    if [[ "$ENABLE_EMAIL" == "true" ]]; then
        echo "$body" | mail -s "[$level] $subject" "$ADMIN_EMAIL"
    fi
}

# Check load average
check_load_average() {
    local load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    local load_int=$(echo "$load" | awk '{print int($1)}')
    
    if (( $(echo "$load > $LOAD_CRITICAL" | bc -l) )); then
        if should_send_alert "critical"; then
            send_notification \
                "CRITICAL: High Load Average" \
                "Load average is $load (critical threshold: $LOAD_CRITICAL)\nImmediate action required!" \
                "CRITICAL"
            update_alert_time "critical"
            return 2
        fi
    elif (( $(echo "$load > $LOAD_WARNING" | bc -l) )); then
        if should_send_alert "warning"; then
            send_notification \
                "WARNING: Elevated Load Average" \
                "Load average is $load (warning threshold: $LOAD_WARNING)\nMonitor closely." \
                "WARNING"
            update_alert_time "warning"
            return 1
        fi
    fi
    
    return 0
}

# Check CPU usage
check_cpu_usage() {
    local cpu_idle=$(top -bn1 | grep "Cpu(s)" | awk '{print $8}' | sed 's/%id,//')
    local cpu_busy=$(echo "100 - $cpu_idle" | bc)
    local cpu_busy_int=$(echo "$cpu_busy" | awk '{print int($1)}')
    
    if [[ $cpu_busy_int -gt $CPU_CRITICAL ]]; then
        if should_send_alert "cpu"; then
            send_notification \
                "CRITICAL: High CPU Usage" \
                "CPU usage is ${cpu_busy}% (critical threshold: ${CPU_CRITICAL}%)\nSystem may be unresponsive!" \
                "CRITICAL"
            update_alert_time "cpu"
            return 2
        fi
    elif [[ $cpu_busy_int -gt $CPU_WARNING ]]; then
        if should_send_alert "cpu"; then
            send_notification \
                "WARNING: Elevated CPU Usage" \
                "CPU usage is ${cpu_busy}% (warning threshold: ${CPU_WARNING}%)\nConsider optimization." \
                "WARNING"
            update_alert_time "cpu"
            return 1
        fi
    fi
    
    return 0
}

# Check memory usage
check_memory_usage() {
    local mem_used=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100}')
    
    if [[ $mem_used -gt $MEM_CRITICAL ]]; then
        if should_send_alert "mem"; then
            send_notification \
                "CRITICAL: High Memory Usage" \
                "Memory usage is ${mem_used}% (critical threshold: ${MEM_CRITICAL}%)\nRisk of OOM!" \
                "CRITICAL"
            update_alert_time "mem"
            return 2
        fi
    elif [[ $mem_used -gt $MEM_WARNING ]]; then
        if should_send_alert "mem"; then
            send_notification \
                "WARNING: Elevated Memory Usage" \
                "Memory usage is ${mem_used}% (warning threshold: ${MEM_WARNING}%)\nMonitor memory consumption." \
                "WARNING"
            update_alert_time "mem"
            return 1
        fi
    fi
    
    return 0
}

# Check disk space
check_disk_space() {
    local disk_used=$(df -h /home | tail -1 | awk '{print $5}' | sed 's/%//')
    
    if [[ $disk_used -gt $DISK_CRITICAL ]]; then
        if should_send_alert "disk"; then
            send_notification \
                "CRITICAL: Low Disk Space" \
                "Disk usage is ${disk_used}% (critical threshold: ${DISK_CRITICAL}%)\nCleanup required immediately!" \
                "CRITICAL"
            update_alert_time "disk"
            return 2
        fi
    elif [[ $disk_used -gt $DISK_WARNING ]]; then
        if should_send_alert "disk"; then
            send_notification \
                "WARNING: Disk Space Running Low" \
                "Disk usage is ${disk_used}% (warning threshold: ${DISK_WARNING}%)\nPlan cleanup soon." \
                "WARNING"
            update_alert_time "disk"
            return 1
        fi
    fi
    
    return 0
}

# Check PHP-FPM status
check_php_fpm() {
    if ! systemctl is-active --quiet ea-php82-php-fpm; then
        send_notification \
            "CRITICAL: PHP-FPM Service Down" \
            "PHP-FPM service is not running!\nWebsites may be offline!" \
            "CRITICAL"
        return 2
    fi
    
    # Check PHP-FPM CPU usage
    local prod_cpu=$(ps aux | grep "php-fpm: pool technostationery_com" | grep -v grep | awk '{sum+=$3} END {print sum}')
    
    if (( $(echo "$prod_cpu > 400" | bc -l) )); then
        if should_send_alert "php_fpm"; then
            send_notification \
                "WARNING: High PHP-FPM CPU Usage" \
                "Production PHP-FPM is using ${prod_cpu}% CPU\nConsider reload or optimization." \
                "WARNING"
            update_alert_time "php_fpm"
            return 1
        fi
    fi
    
    return 0
}

# Check Elasticsearch status
check_elasticsearch() {
    if ! curl -s http://localhost:9200/_cluster/health >/dev/null 2>&1; then
        send_notification \
            "CRITICAL: Elasticsearch Not Responding" \
            "Elasticsearch is not responding!\nSearch functionality may be broken!" \
            "CRITICAL"
        return 2
    fi
    
    local status=$(curl -s http://localhost:9200/_cluster/health | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    
    if [[ "$status" == "red" ]]; then
        send_notification \
            "CRITICAL: Elasticsearch Cluster Red" \
            "Elasticsearch cluster status is RED!\nData loss possible!" \
            "CRITICAL"
        return 2
    fi
    
    return 0
}

# Check database connections
check_databases() {
    if ! /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT 1;" >/dev/null 2>&1; then
        send_notification \
            "CRITICAL: Production Database Unreachable" \
            "Cannot connect to production database!\nWebsite may be down!" \
            "CRITICAL"
        return 2
    fi
    
    return 0
}

# Main monitoring loop
main() {
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}        Alert System Check - $(date)${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    local alert_count=0
    local critical_count=0
    local warning_count=0
    
    # Run all checks
    echo -n "Checking load average... "
    check_load_average
    local result=$?
    if [[ $result -eq 2 ]]; then
        echo -e "${RED}CRITICAL${NC}"
        ((critical_count++))
        ((alert_count++))
    elif [[ $result -eq 1 ]]; then
        echo -e "${YELLOW}WARNING${NC}"
        ((warning_count++))
        ((alert_count++))
    else
        echo -e "${GREEN}OK${NC}"
    fi
    
    echo -n "Checking CPU usage... "
    check_cpu_usage
    result=$?
    if [[ $result -eq 2 ]]; then
        echo -e "${RED}CRITICAL${NC}"
        ((critical_count++))
        ((alert_count++))
    elif [[ $result -eq 1 ]]; then
        echo -e "${YELLOW}WARNING${NC}"
        ((warning_count++))
        ((alert_count++))
    else
        echo -e "${GREEN}OK${NC}"
    fi
    
    echo -n "Checking memory usage... "
    check_memory_usage
    result=$?
    if [[ $result -eq 2 ]]; then
        echo -e "${RED}CRITICAL${NC}"
        ((critical_count++))
        ((alert_count++))
    elif [[ $result -eq 1 ]]; then
        echo -e "${YELLOW}WARNING${NC}"
        ((warning_count++))
        ((alert_count++))
    else
        echo -e "${GREEN}OK${NC}"
    fi
    
    echo -n "Checking disk space... "
    check_disk_space
    result=$?
    if [[ $result -eq 2 ]]; then
        echo -e "${RED}CRITICAL${NC}"
        ((critical_count++))
        ((alert_count++))
    elif [[ $result -eq 1 ]]; then
        echo -e "${YELLOW}WARNING${NC}"
        ((warning_count++))
        ((alert_count++))
    else
        echo -e "${GREEN}OK${NC}"
    fi
    
    echo -n "Checking PHP-FPM... "
    check_php_fpm
    result=$?
    if [[ $result -eq 2 ]]; then
        echo -e "${RED}CRITICAL${NC}"
        ((critical_count++))
        ((alert_count++))
    elif [[ $result -eq 1 ]]; then
        echo -e "${YELLOW}WARNING${NC}"
        ((warning_count++))
        ((alert_count++))
    else
        echo -e "${GREEN}OK${NC}"
    fi
    
    echo -n "Checking Elasticsearch... "
    check_elasticsearch
    result=$?
    if [[ $result -eq 2 ]]; then
        echo -e "${RED}CRITICAL${NC}"
        ((critical_count++))
        ((alert_count++))
    elif [[ $result -eq 1 ]]; then
        echo -e "${YELLOW}WARNING${NC}"
        ((warning_count++))
        ((alert_count++))
    else
        echo -e "${GREEN}OK${NC}"
    fi
    
    echo -n "Checking databases... "
    check_databases
    result=$?
    if [[ $result -eq 2 ]]; then
        echo -e "${RED}CRITICAL${NC}"
        ((critical_count++))
        ((alert_count++))
    elif [[ $result -eq 1 ]]; then
        echo -e "${YELLOW}WARNING${NC}"
        ((warning_count++))
        ((alert_count++))
    else
        echo -e "${GREEN}OK${NC}"
    fi
    
    echo ""
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Summary:${NC} Total Alerts: $alert_count | ${RED}Critical: $critical_count${NC} | ${YELLOW}Warning: $warning_count${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    if [[ $alert_count -eq 0 ]]; then
        echo -e "${GREEN}✓ All systems healthy!${NC}"
    else
        echo -e "${YELLOW}⚠ Review alerts: $ALERT_LOG${NC}"
    fi
    
    echo ""
}

# Run main function
main "$@"

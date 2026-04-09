#!/bin/bash
################################################################################
# Dashboard Health Monitor
# Version: 1.0.0
# Date: 2026-04-09
# Description: Monitors dashboard and system health continuously
################################################################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
DASHBOARD_ROOT="/home/dashboard/public_html"
BETA_ROOT="/home/beta/public_html"
PROD_ROOT="/home/technadminy7/public_html"
LOG_FILE="$DASHBOARD_ROOT/logs/health_monitor_$(date +%Y-%m-%d).log"

# Health check functions
check_dashboard_accessibility() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking dashboard accessibility..."
    
    if curl -s -o /dev/null -w "%{http_code}" https://dashboard.technostationery.com/ | grep -q "200"; then
        echo -e "${GREEN}✓${NC} Dashboard is accessible (HTTP 200)"
        return 0
    else
        echo -e "${RED}✗${NC} Dashboard is not accessible"
        return 1
    fi
}

check_api_health() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking API health..."
    
    # Test API from server side
    if cd "$DASHBOARD_ROOT/api" && php -r "
        \$_GET = ['action' => 'status', 'env' => 'prod'];
        ob_start();
        include 'dashboard.php';
        \$output = ob_get_clean();
        \$data = json_decode(\$output, true);
        exit(\$data['success'] ? 0 : 1);
    " 2>/dev/null; then
        echo -e "${GREEN}✓${NC} API is functional"
        return 0
    else
        echo -e "${RED}✗${NC} API has errors"
        return 1
    fi
}

check_system_performance() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking system performance..."
    
    # Load average
    local load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    local load_int=$(echo "$load" | awk '{print int($1)}')
    
    if [[ $load_int -lt 8 ]]; then
        echo -e "${GREEN}✓${NC} Load average: $load (healthy)"
    elif [[ $load_int -lt 12 ]]; then
        echo -e "${YELLOW}⚠${NC} Load average: $load (warning)"
    else
        echo -e "${RED}✗${NC} Load average: $load (critical)"
    fi
    
    # Memory usage
    local mem_used=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100}')
    
    if [[ $mem_used -lt 80 ]]; then
        echo -e "${GREEN}✓${NC} Memory usage: ${mem_used}% (healthy)"
    elif [[ $mem_used -lt 90 ]]; then
        echo -e "${YELLOW}⚠${NC} Memory usage: ${mem_used}% (warning)"
    else
        echo -e "${RED}✗${NC} Memory usage: ${mem_used}% (critical)"
    fi
}

check_databases() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking database connections..."
    
    # Test production database
    if /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT 1;" >/dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Production database is accessible"
    else
        echo -e "${RED}✗${NC} Production database connection failed"
    fi
    
    # Test beta database
    if /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22 -e "SELECT 1;" >/dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Beta database is accessible"
    else
        echo -e "${RED}✗${NC} Beta database connection failed"
    fi
}

check_scripts_directory() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking scripts directory..."
    
    local script_count=$(find "$DASHBOARD_ROOT/scripts" -type f \( -name "*.php" -o -name "*.sh" \) | wc -l)
    
    if [[ $script_count -gt 40 ]]; then
        echo -e "${GREEN}✓${NC} Scripts directory has $script_count files (healthy)"
    else
        echo -e "${YELLOW}⚠${NC} Scripts directory has only $script_count files (expected 40+)"
    fi
}

check_disk_space() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking disk space..."
    
    local disk_usage=$(df -h /home | tail -1 | awk '{print $5}' | sed 's/%//')
    
    if [[ $disk_usage -lt 80 ]]; then
        echo -e "${GREEN}✓${NC} Disk usage: ${disk_usage}% (healthy)"
    elif [[ $disk_usage -lt 90 ]]; then
        echo -e "${YELLOW}⚠${NC} Disk usage: ${disk_usage}% (warning)"
    else
        echo -e "${RED}✗${NC} Disk usage: ${disk_usage}% (critical)"
    fi
}

check_php_fpm() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking PHP-FPM status..."
    
    if systemctl is-active --quiet ea-php82-php-fpm; then
        echo -e "${GREEN}✓${NC} PHP-FPM is running"
    else
        echo -e "${RED}✗${NC} PHP-FPM is not running"
    fi
}

check_elasticsearch() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} Checking Elasticsearch..."
    
    if curl -s http://localhost:9200/_cluster/health | grep -q "yellow\|green"; then
        local status=$(curl -s http://localhost:9200/_cluster/health | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
        
        if [[ "$status" == "green" ]]; then
            echo -e "${GREEN}✓${NC} Elasticsearch: $status (healthy)"
        elif [[ "$status" == "yellow" ]]; then
            echo -e "${YELLOW}⚠${NC} Elasticsearch: $status (warning)"
        else
            echo -e "${RED}✗${NC} Elasticsearch: $status"
        fi
    else
        echo -e "${RED}✗${NC} Elasticsearch is not responding"
    fi
}

# Main monitoring loop
monitor() {
    local interval="${1:-60}"  # Default 60 seconds
    local iterations="${2:-0}"  # 0 = infinite
    local count=0
    
    echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║         Dashboard Health Monitor Started                      ║${NC}"
    echo -e "${BLUE}║         Interval: ${interval}s | Press Ctrl+C to stop               ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    while true; do
        count=$((count + 1))
        
        echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
        echo -e "${BLUE}Health Check #$count - $(date)${NC}"
        echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
        
        check_dashboard_accessibility
        check_api_health
        check_system_performance
        check_databases
        check_elasticsearch
        check_php_fpm
        check_scripts_directory
        check_disk_space
        
        echo ""
        
        # Log to file
        {
            echo "=== Health Check #$count - $(date) ==="
            echo "Dashboard: $(curl -s -o /dev/null -w '%{http_code}' https://dashboard.technostationery.com/)"
            echo "Load: $(uptime | awk -F'load average:' '{print $2}')"
            echo "Memory: $(free | grep Mem | awk '{printf "%.0f%%", $3/$2 * 100}')"
            echo ""
        } >> "$LOG_FILE"
        
        # Check if should stop
        if [[ $iterations -gt 0 && $count -ge $iterations ]]; then
            break
        fi
        
        # Wait for next iteration
        if [[ $iterations -eq 0 || $count -lt $iterations ]]; then
            echo -e "${CYAN}Next check in ${interval} seconds...${NC}"
            sleep "$interval"
        fi
    done
    
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║         Health Monitor Stopped                                 ║${NC}"
    echo -e "${BLUE}║         Total checks: $count                                        ║${NC}"
    echo -e "${BLUE}║         Log file: $LOG_FILE${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
}

# Parse arguments
case "${1:-monitor}" in
    monitor)
        monitor "${2:-60}" "${3:-0}"
        ;;
    once)
        echo -e "${BLUE}Running single health check...${NC}"
        echo ""
        check_dashboard_accessibility
        check_api_health
        check_system_performance
        check_databases
        check_elasticsearch
        check_php_fpm
        check_scripts_directory
        check_disk_space
        ;;
    *)
        echo "Usage: $0 [monitor|once] [interval] [iterations]"
        echo ""
        echo "Examples:"
        echo "  $0 once                  # Run once"
        echo "  $0 monitor               # Monitor continuously (60s interval)"
        echo "  $0 monitor 30            # Monitor every 30 seconds"
        echo "  $0 monitor 60 10         # Monitor 10 times at 60s interval"
        exit 1
        ;;
esac

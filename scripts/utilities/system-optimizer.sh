#!/bin/bash
################################################################################
# System Optimization Script
# Version: 1.0.0
# Date: 2026-04-09
# Description: Optimizes system performance by addressing high CPU/load issues
################################################################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
LOG_FILE="/home/dashboard/public_html/logs/optimization_$(date +%Y-%m-%d_%H-%M-%S).log"
BACKUP_DIR="/home/dashboard/public_html/backups"

# Create necessary directories
mkdir -p "$(dirname "$LOG_FILE")"
mkdir -p "$BACKUP_DIR"

# Logging function
log() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

print_header() {
    log "${CYAN}============================================================================${NC}"
    log "${CYAN}$1${NC}"
    log "${CYAN}============================================================================${NC}"
}

print_section() {
    log "\n${BLUE}────────────────────────────────────────────────────────────────────────${NC}"
    log "${BLUE}$1${NC}"
    log "${BLUE}────────────────────────────────────────────────────────────────────────${NC}"
}

print_success() {
    log "${GREEN}✓${NC} $1"
}

print_error() {
    log "${RED}✗${NC} $1"
}

print_info() {
    log "${CYAN}ℹ${NC} $1"
}

# Check if running as appropriate user
check_permissions() {
    if [[ $EUID -eq 0 ]]; then
        print_info "Running as root - full system access available"
        return 0
    else
        print_info "Running as regular user - some optimizations may be skipped"
        return 1
    fi
}

# Get current system stats
get_current_stats() {
    print_section "Current System Status"
    
    local load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    local mem_used=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100}')
    local cpu_busy=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | sed 's/%us,//')
    
    log "Load Average: $load"
    log "Memory Usage: ${mem_used}%"
    log "CPU Busy: ${cpu_busy}%"
    
    # Store for comparison
    echo "$load" > /tmp/load_before.txt
    echo "$mem_used" > /tmp/mem_before.txt
    echo "$cpu_busy" > /tmp/cpu_before.txt
}

# Optimize Elasticsearch
optimize_elasticsearch() {
    print_section "Optimizing Elasticsearch"
    
    # Check if Elasticsearch is running
    if ! curl -s http://localhost:9200/_cluster/health >/dev/null 2>&1; then
        print_error "Elasticsearch is not responding"
        return 1
    fi
    
    local status=$(curl -s http://localhost:9200/_cluster/health | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    print_info "Current Elasticsearch status: $status"
    
    # Clear cache
    print_info "Clearing Elasticsearch cache..."
    if curl -s -XPOST "http://localhost:9200/_cache/clear" >/dev/null 2>&1; then
        print_success "Elasticsearch cache cleared"
    fi
    
    # Force merge indices (reduce fragmentation)
    print_info "Optimizing indices (this may take a few minutes)..."
    if curl -s -XPOST "http://localhost:9200/_forcemerge?max_num_segments=1" >/dev/null 2>&1; then
        print_success "Elasticsearch indices optimized"
    fi
    
    # Check status after optimization
    local new_status=$(curl -s http://localhost:9200/_cluster/health | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    print_info "Elasticsearch status after optimization: $new_status"
}

# Optimize PHP-FPM
optimize_php_fpm() {
    print_section "Optimizing PHP-FPM"
    
    # Check PHP-FPM status
    if ! systemctl is-active --quiet ea-php82-php-fpm; then
        print_error "PHP-FPM is not running"
        return 1
    fi
    
    print_info "Current PHP-FPM status: Running"
    
    # Get current pool stats
    local prod_cpu=$(ps aux | grep "php-fpm: pool technostationery_com" | grep -v grep | awk '{sum+=$3} END {print sum}')
    print_info "Production PHP-FPM CPU usage: ${prod_cpu}%"
    
    # Reload PHP-FPM gracefully
    print_info "Reloading PHP-FPM configuration..."
    if systemctl reload ea-php82-php-fpm; then
        print_success "PHP-FPM reloaded successfully"
        sleep 3
        
        # Check new stats
        local new_cpu=$(ps aux | grep "php-fpm: pool technostationery_com" | grep -v grep | awk '{sum+=$3} END {print sum}')
        print_info "PHP-FPM CPU usage after reload: ${new_cpu}%"
    else
        print_error "Failed to reload PHP-FPM"
    fi
}

# Optimize Redis
optimize_redis() {
    print_section "Optimizing Redis"
    
    # Check if Redis is running
    if ! redis-cli ping >/dev/null 2>&1; then
        print_error "Redis is not responding"
        return 1
    fi
    
    print_success "Redis is responding"
    
    # Get Redis info
    local mem=$(redis-cli info memory | grep "used_memory_human" | cut -d':' -f2 | tr -d '\r')
    print_info "Redis memory usage: $mem"
    
    # Get key count
    local keys=$(redis-cli dbsize | awk '{print $2}')
    print_info "Redis key count: $keys"
    
    # Optimize if too many keys
    if [[ $keys -gt 100000 ]]; then
        print_info "High key count detected, consider running cache cleanup"
    fi
}

# Clean Magento caches
clean_magento_caches() {
    print_section "Cleaning Magento Caches"
    
    local environments=("prod" "beta")
    
    for env in "${environments[@]}"; do
        if [[ "$env" == "prod" ]]; then
            local magento_root="/home/technadminy7/public_html"
        else
            local magento_root="/home/beta/public_html"
        fi
        
        if [[ ! -d "$magento_root" ]]; then
            print_error "$env environment not found at $magento_root"
            continue
        fi
        
        print_info "Cleaning $env Magento cache..."
        
        # Flush cache
        if cd "$magento_root" && php bin/magento cache:flush >/dev/null 2>&1; then
            print_success "$env cache flushed"
        else
            print_error "Failed to flush $env cache"
        fi
        
        # Clean generated files (if safe)
        if [[ -d "$magento_root/var/cache" ]]; then
            local cache_size=$(du -sh "$magento_root/var/cache" | awk '{print $1}')
            print_info "$env cache directory size: $cache_size"
        fi
    done
}

# Kill hung processes
kill_hung_processes() {
    print_section "Checking for Hung Processes"
    
    # Find PHP processes running > 10 minutes
    local hung_processes=$(ps aux | grep php | awk '{if ($10 > 600) print $2}')
    
    if [[ -n "$hung_processes" ]]; then
        print_info "Found hung processes (running > 10 minutes)"
        
        for pid in $hung_processes; do
            local cmd=$(ps -p $pid -o comm=)
            local time=$(ps -p $pid -o etime=)
            print_info "PID $pid ($cmd) running for $time"
            
            # Optionally kill (uncomment to enable)
            # kill -15 $pid
            # print_info "Sent SIGTERM to PID $pid"
        done
    else
        print_success "No hung processes detected"
    fi
}

# Optimize system settings
optimize_system_settings() {
    print_section "Optimizing System Settings"
    
    # Check current swappiness
    local swappiness=$(cat /proc/sys/vm/swappiness)
    print_info "Current swappiness: $swappiness"
    
    if [[ $EUID -eq 0 ]]; then
        # Optimize swappiness if root
        if [[ $swappiness -gt 10 ]]; then
            print_info "Setting swappiness to 10..."
            echo 10 > /proc/sys/vm/swappiness
            print_success "Swappiness optimized"
        fi
    else
        print_info "Skipping swappiness optimization (requires root)"
    fi
    
    # Check file descriptors
    local open_files=$(lsof | wc -l)
    local max_files=$(ulimit -n)
    print_info "Open files: $open_files / $max_files"
    
    if [[ $open_files -gt $((max_files * 80 / 100)) ]]; then
        print_info "High file descriptor usage detected"
    fi
}

# Database optimization
optimize_databases() {
    print_section "Optimizing Databases"
    
    local db_script="/home/dashboard/public_html/scripts/database/database_health_check.php"
    
    if [[ -f "$db_script" ]]; then
        print_info "Running database optimization..."
        
        if cd "$(dirname "$db_script")" && php database_health_check.php both --fix >/dev/null 2>&1; then
            print_success "Database optimization completed"
        else
            print_error "Database optimization encountered errors (check logs)"
        fi
    else
        print_error "Database health check script not found"
    fi
}

# Generate optimization report
generate_report() {
    print_section "Optimization Summary"
    
    # Get new stats
    sleep 5  # Wait for changes to take effect
    
    local load_before=$(cat /tmp/load_before.txt 2>/dev/null || echo "N/A")
    local load_after=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    
    local mem_before=$(cat /tmp/mem_before.txt 2>/dev/null || echo "N/A")
    local mem_after=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100}')
    
    local cpu_before=$(cat /tmp/cpu_before.txt 2>/dev/null || echo "N/A")
    local cpu_after=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | sed 's/%us,//')
    
    log "\n${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    log "${CYAN}                    OPTIMIZATION RESULTS                              ${NC}"
    log "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    log "\n${BLUE}Metric              Before          After           Change${NC}"
    log "────────────────────────────────────────────────────────────────────────"
    log "Load Average        $load_before            $load_after"
    log "Memory Usage        ${mem_before}%          ${mem_after}%"
    log "CPU Usage           ${cpu_before}%          ${cpu_after}%"
    
    log "\n${GREEN}Optimization completed at $(date)${NC}"
    log "${GREEN}Full log: $LOG_FILE${NC}\n"
    
    # Cleanup temp files
    rm -f /tmp/load_before.txt /tmp/mem_before.txt /tmp/cpu_before.txt
}

# Main execution
main() {
    clear
    print_header "TechnoStationery System Optimization"
    log ""
    print_info "Started at: $(date)"
    print_info "Log file: $LOG_FILE"
    log ""
    
    # Check permissions
    check_permissions
    
    # Get baseline stats
    get_current_stats
    
    # Run optimizations
    optimize_elasticsearch
    optimize_php_fpm
    optimize_redis
    clean_magento_caches
    kill_hung_processes
    optimize_system_settings
    optimize_databases
    
    # Generate report
    generate_report
    
    print_header "Optimization Complete"
    
    log "\n${CYAN}Next Steps:${NC}"
    log "1. Monitor system for 15-30 minutes"
    log "2. Run health check: bash /home/dashboard/public_html/scripts/utilities/health-monitor.sh once"
    log "3. Check performance: php /home/dashboard/public_html/scripts/performance/system_performance_monitor.php"
    log "4. Review logs: tail -f $LOG_FILE"
    log ""
}

# Run main function
main "$@"

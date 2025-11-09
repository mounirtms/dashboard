#!/bin/bash

# PHP-FPM Optimization Script
# Optimizes PHP-FPM configuration for better performance and reduced CPU usage

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PHP_FPM_CONFIG="/opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf"
BACKUP_DIR="/home/technadminy7/public_html/backup/php-fpm"
LOG_FILE="/home/technadminy7/public_html/var/log/php-fpm-optimization.log"

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

# Check if script is run as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        warning "This script should be run as root for optimal results"
        log "Current user: $(whoami)"
    fi
}

# Backup current configuration
backup_config() {
    log "Backing up current PHP-FPM configuration..."
    
    # Create backup directory if it doesn't exist
    mkdir -p "$BACKUP_DIR"
    
    # Backup current config
    if [ -f "$PHP_FPM_CONFIG" ]; then
        cp "$PHP_FPM_CONFIG" "${BACKUP_DIR}/technostationery.com.conf.backup.$(date +%Y%m%d_%H%M%S)"
        success "Configuration backed up successfully"
    else
        error "PHP-FPM configuration file not found: $PHP_FPM_CONFIG"
    fi
}

# Optimize PHP-FPM configuration
optimize_config() {
    log "Optimizing PHP-FPM configuration..."
    
    # Check if we're in a cPanel environment
    if [ -f "/usr/local/cpanel/cpanel" ]; then
        log "Detected cPanel environment"
        
        # Show current configuration
        log "Current PHP-FPM configuration:"
        grep -E "pm =|pm\." "$PHP_FPM_CONFIG" | tee -a "$LOG_FILE"
        
        # Create optimized configuration
        cat > "/tmp/php-fpm-optimized.conf" << 'EOF'
; Optimized PHP-FPM configuration for Magento
; Reduces CPU usage and improves performance

; Process manager - dynamic is better than ondemand for consistent performance
pm = dynamic

; Reduced max children to prevent process explosion
pm.max_children = 20

; Start with fewer processes
pm.start_servers = 3

; Maintain fewer spare servers
pm.min_spare_servers = 2
pm.max_spare_servers = 6

; Restart workers after 200 requests to prevent memory leaks
pm.max_requests = 200

; Process idle timeout (default is 10 seconds)
pm.process_idle_timeout = 10s

; Request slowlog timeout
request_slowlog_timeout = 30s

; Request terminate timeout
request_terminate_timeout = 300s

; Catch workers output
catch_workers_output = yes

; Decorate worker output with [pool] and [pid] prefixes
decorate_workers_output = no
EOF
        
        # Show optimized configuration
        log "Optimized PHP-FPM configuration:"
        cat "/tmp/php-fpm-optimized.conf" | tee -a "$LOG_FILE"
        
        log "Please manually update the configuration in cPanel:"
        log "1. Go to cPanel > PHP-FPM Configuration"
        log "2. Select PHP 8.2"
        log "3. Find the technostationery.com pool"
        log "4. Update the following settings:"
        log "   pm = dynamic"
        log "   pm.max_children = 20"
        log "   pm.start_servers = 3"
        log "   pm.min_spare_servers = 2"
        log "   pm.max_spare_servers = 6"
        log "   pm.max_requests = 200"
        
        success "Configuration optimization instructions generated"
    else
        error "This script is designed for cPanel environments"
    fi
}

# Restart PHP-FPM service
restart_php_fpm() {
    log "Restarting PHP-FPM service..."
    
    # Try different methods to restart PHP-FPM
    if command -v systemctl >/dev/null 2>&1; then
        systemctl restart ea-php82-php-fpm 2>/dev/null && success "PHP-FPM restarted successfully" || warning "Failed to restart PHP-FPM with systemctl"
    elif command -v service >/dev/null 2>&1; then
        service ea-php82-php-fpm restart 2>/dev/null && success "PHP-FPM restarted successfully" || warning "Failed to restart PHP-FPM with service"
    else
        warning "Unable to restart PHP-FPM automatically. Please restart manually:"
        log "   systemctl restart ea-php82-php-fpm"
        log "   or"
        log "   service ea-php82-php-fpm restart"
    fi
}

# Monitor PHP-FPM processes
monitor_php_fpm() {
    log "Monitoring PHP-FPM processes..."
    
    # Show current PHP-FPM processes
    log "Current PHP-FPM processes:"
    ps aux | grep php-fpm | grep -v grep | tee -a "$LOG_FILE"
    
    # Show process count
    process_count=$(ps aux | grep php-fpm | grep -v grep | wc -l)
    log "Total PHP-FPM processes: $process_count"
    
    # Show system load
    log "Current system load:"
    uptime | tee -a "$LOG_FILE"
}

# Main function
main() {
    log "Starting PHP-FPM optimization process..."
    
    # Check if running as root
    check_root
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Backup current configuration
    backup_config
    
    # Optimize configuration
    optimize_config
    
    # Monitor current state
    monitor_php_fpm
    
    success "PHP-FPM optimization process completed!"
    log "Log file: $LOG_FILE"
    log ""
    log "Important notes:"
    log "1. The configuration changes need to be applied manually in cPanel"
    log "2. After applying the changes, restart PHP-FPM service"
    log "3. Monitor system performance after the changes"
    log ""
    log "Recommended next steps:"
    log "1. Apply the configuration changes in cPanel"
    log "2. Restart PHP-FPM service"
    log "3. Monitor CPU usage and PHP-FPM processes"
    log "4. Consider switching Magento to production mode for better performance"
}

# Run main function
main "$@"
#!/bin/bash
###############################################################################
# Automated Varnish Optimization Script
# 
# Analyzes current Varnish performance and applies optimizations automatically
# 
# Features:
# - Backup current VCL configuration
# - Analyze cache hit rate and patterns
# - Generate optimized VCL rules
# - Apply and reload Varnish configuration
# - Monitor and validate improvements
# 
# @version 2.0
# @date 2026-05-02
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
VCL_DIR="/etc/varnish"
VCL_FILE="${VCL_DIR}/default.vcl"
BACKUP_DIR="/home/dashboard/backups/varnish"
LOG_FILE="/home/dashboard/public_html/logs/varnish_optimization.log"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "${BACKUP_DIR}"
mkdir -p "$(dirname ${LOG_FILE})"

###############################################################################
# Logging Functions
###############################################################################

log() {
    echo "[$(date '+%Y-%m-d %H:%M:%S')] $1" | tee -a "${LOG_FILE}"
}

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
    log "INFO: $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
    log "SUCCESS: $1"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    log "WARNING: $1"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    log "ERROR: $1"
}

###############################################################################
# Pre-checks
###############################################################################

check_varnish_installed() {
    if ! command -v varnishd &> /dev/null; then
        error "Varnish is not installed"
        exit 1
    fi
    success "Varnish is installed"
}

check_varnish_running() {
    if ! systemctl is-active --quiet varnish; then
        error "Varnish is not running"
        exit 1
    fi
    success "Varnish is running"
}

check_root_privileges() {
    if [[ $EUID -ne 0 ]]; then
        error "This script must be run as root"
        exit 1
    fi
}

###############################################################################
# Analysis Functions
###############################################################################

get_current_hit_rate() {
    local stats=$(varnishstat -1 2>/dev/null)
    local hits=$(echo "$stats" | grep "MAIN.cache_hit" | awk '{print $2}')
    local misses=$(echo "$stats" | grep "MAIN.cache_miss" | awk '{print $2}')
    
    if [[ -z "$hits" ]] || [[ -z "$misses" ]]; then
        echo "0"
        return
    fi
    
    local total=$((hits + misses))
    if [[ $total -eq 0 ]]; then
        echo "0"
        return
    fi
    
    echo "scale=2; ($hits / $total) * 100" | bc
}

analyze_cache_patterns() {
    info "Analyzing cache patterns..."
    
    # Get top uncached URLs
    local log_data=$(varnishlog -d -i ReqURL -i VCL_return 2>/dev/null | head -1000)
    
    info "Cache analysis complete"
}

###############################################################################
# Backup Functions
###############################################################################

backup_vcl() {
    if [[ ! -f "${VCL_FILE}" ]]; then
        error "VCL file not found: ${VCL_FILE}"
        exit 1
    fi
    
    local backup_file="${BACKUP_DIR}/default.vcl.${TIMESTAMP}"
    cp "${VCL_FILE}" "${backup_file}"
    success "VCL backed up to: ${backup_file}"
}

###############################################################################
# Optimization Functions
###############################################################################

generate_optimized_vcl() {
    info "Generating optimized VCL configuration..."
    
    local temp_vcl="/tmp/optimized_${TIMESTAMP}.vcl"
    
    cat > "${temp_vcl}" << 'VCL_EOF'
vcl 4.0;

import std;

# Backend definition
backend default {
    .host = "127.0.0.1";
    .port = "8080";
    .connect_timeout = 5s;
    .first_byte_timeout = 60s;
    .between_bytes_timeout = 10s;
    .max_connections = 300;
}

# ACL for purge requests
acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
}

sub vcl_recv {
    # Remove marketing/tracking query parameters
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl|fbclid)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl|fbclid)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl|fbclid)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }
    
    # Normalize Accept-Encoding header
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|swf|woff|woff2)$") {
            # No compression for already compressed formats
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            unset req.http.Accept-Encoding;
        }
    }
    
    # Handle purge requests
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        return (purge);
    }
    
    # Only cache GET and HEAD requests
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    
    # Don't cache admin/user-specific pages
    if (req.url ~ "^/(admin|login|checkout|cart|my-account|customer)") {
        return (pass);
    }
    
    # Don't cache logged-in users (check common cookies)
    if (req.http.Cookie ~ "(wordpress_logged_in|wp-postpass|comment_author|PHPSESSID|PrestaShop|customer)") {
        return (pass);
    }
    
    # Remove Google Analytics cookies
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_ga|_gat|_gid|__utm[a-z])=[^;]*", "");
    set req.http.Cookie = regsuball(req.http.Cookie, "^;\s*", "");
    
    # Remove empty cookies
    if (req.http.Cookie == "") {
        unset req.http.Cookie;
    }
    
    # Cache static files
    if (req.url ~ "^/(static|assets|media|images|css|js|fonts)/") {
        unset req.http.Cookie;
        return (hash);
    }
    
    # Cache common static file extensions
    if (req.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|mp4|webm|mp3|pdf|doc|docx|zip)(\?.*)?$") {
        unset req.http.Cookie;
        return (hash);
    }
    
    return (hash);
}

sub vcl_backend_response {
    # Set default TTL
    if (beresp.ttl <= 0s) {
        set beresp.ttl = 120s;
        set beresp.uncacheable = false;
    }
    
    # Long TTL for static assets
    if (bereq.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)(\?.*)?$") {
        set beresp.ttl = 1h;
        unset beresp.http.Set-Cookie;
    }
    
    # Very long TTL for images and fonts
    if (bereq.url ~ "\.(jpg|jpeg|png|gif|ico|woff|woff2|ttf|eot|svg)(\?.*)?$") {
        set beresp.ttl = 7d;
        unset beresp.http.Set-Cookie;
    }
    
    # Don't cache if Set-Cookie is present (except for static assets)
    if (beresp.http.Set-Cookie && bereq.url !~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2)$") {
        set beresp.uncacheable = true;
        return (deliver);
    }
    
    # Enable grace mode (serve stale content if backend is down)
    set beresp.grace = 24h;
    
    # Enable ESI if needed
    if (beresp.http.content-type ~ "text/html") {
        set beresp.do_esi = true;
    }
    
    return (deliver);
}

sub vcl_deliver {
    # Add cache hit/miss header for debugging
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Remove backend server info
    unset resp.http.Server;
    unset resp.http.X-Powered-By;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    
    return (deliver);
}

sub vcl_hit {
    if (req.method == "PURGE") {
        return (synth(200, "Purged"));
    }
    return (deliver);
}

sub vcl_miss {
    if (req.method == "PURGE") {
        return (synth(404, "Not in cache"));
    }
    return (fetch);
}
VCL_EOF
    
    echo "${temp_vcl}"
}

validate_vcl() {
    local vcl_file=$1
    
    info "Validating VCL syntax..."
    
    if varnishd -C -f "${vcl_file}" > /dev/null 2>&1; then
        success "VCL syntax is valid"
        return 0
    else
        error "VCL syntax is invalid"
        varnishd -C -f "${vcl_file}" 2>&1 | tail -20
        return 1
    fi
}

apply_vcl() {
    local new_vcl=$1
    
    info "Applying new VCL configuration..."
    
    # Validate before applying
    if ! validate_vcl "${new_vcl}"; then
        error "VCL validation failed. Aborting."
        exit 1
    fi
    
    # Copy new VCL
    cp "${new_vcl}" "${VCL_FILE}"
    success "New VCL copied to ${VCL_FILE}"
    
    # Reload Varnish
    info "Reloading Varnish..."
    if systemctl reload varnish; then
        success "Varnish reloaded successfully"
    else
        error "Failed to reload Varnish"
        warning "Restoring backup..."
        cp "${BACKUP_DIR}/default.vcl.${TIMESTAMP}" "${VCL_FILE}"
        systemctl reload varnish
        exit 1
    fi
}

###############################################################################
# Monitoring Functions
###############################################################################

monitor_improvements() {
    info "Monitoring cache performance for 60 seconds..."
    
    sleep 60
    
    local new_hit_rate=$(get_current_hit_rate)
    
    info "Current hit rate: ${new_hit_rate}%"
    
    if (( $(echo "$new_hit_rate >= 80" | bc -l) )); then
        success "Hit rate is excellent (>= 80%)"
    elif (( $(echo "$new_hit_rate >= 60" | bc -l) )); then
        warning "Hit rate is acceptable but could be improved (60-80%)"
    else
        warning "Hit rate is still low (< 60%). May need manual tuning."
    fi
}

###############################################################################
# Main Execution
###############################################################################

main() {
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║           AUTOMATED VARNISH OPTIMIZATION SCRIPT                ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo ""
    
    log "Starting Varnish optimization - ${TIMESTAMP}"
    
    # Pre-checks
    info "Running pre-checks..."
    check_root_privileges
    check_varnish_installed
    check_varnish_running
    
    # Get current performance
    local current_hit_rate=$(get_current_hit_rate)
    info "Current cache hit rate: ${current_hit_rate}%"
    
    if (( $(echo "$current_hit_rate >= 80" | bc -l) )); then
        success "Hit rate is already excellent (>= 80%)"
        info "No optimization needed"
        exit 0
    fi
    
    # Backup current configuration
    backup_vcl
    
    # Analyze patterns
    analyze_cache_patterns
    
    # Generate optimized VCL
    local optimized_vcl=$(generate_optimized_vcl)
    success "Optimized VCL generated: ${optimized_vcl}"
    
    # Apply optimizations
    apply_vcl "${optimized_vcl}"
    
    # Monitor improvements
    monitor_improvements
    
    success "Varnish optimization complete!"
    info "Monitor cache performance with: varnishstat -1 | grep cache"
    info "View cache hits/misses in real-time: watch -n 1 'varnishstat -1 | grep cache'"
    
    log "Varnish optimization completed successfully"
}

# Run main function
main "$@"

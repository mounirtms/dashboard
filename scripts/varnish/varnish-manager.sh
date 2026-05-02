#!/bin/bash
# Varnish Cache Management Toolkit
# Provides various cache management operations

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

show_help() {
    cat << EOF
╔════════════════════════════════════════════════════════════════╗
║        VARNISH CACHE MANAGEMENT TOOLKIT                        ║
╚════════════════════════════════════════════════════════════════╝

Usage: $0 <command> [options]

Commands:
  warmup         Warm up all site caches
  warmup-prod    Warm up production site only
  warmup-beta    Warm up beta site only
  
  monitor        Show current hit rate and statistics
  stats          Show detailed Varnish statistics
  
  clear          Clear all cache (ban all)
  clear-prod     Clear production cache
  clear-beta     Clear beta cache
  clear-url      Clear specific URL pattern
  
  reload         Reload Varnish VCL configuration
  restart        Restart Varnish service
  
  test-vcl       Test VCL syntax
  apply-optimized Apply optimized Magento VCL
  
  top            Show top cached URLs (live)
  log            Show Varnish log (last 100 lines)
  
  health         Check backend health
  status         Show Varnish service status

Examples:
  $0 warmup                    # Warm up all sites
  $0 monitor                   # Check hit rate
  $0 clear-url /products/*     # Clear products cache
  $0 apply-optimized           # Apply optimized VCL

EOF
}

# ============================================================================
# WARMUP COMMANDS
# ============================================================================
cmd_warmup() {
    bash "$SCRIPT_DIR/warmup_all.sh"
}

cmd_warmup_prod() {
    bash "$SCRIPT_DIR/warmup_production.sh"
}

cmd_warmup_beta() {
    bash "$SCRIPT_DIR/warmup_beta.sh"
}

# ============================================================================
# MONITORING COMMANDS
# ============================================================================
cmd_monitor() {
    bash "$SCRIPT_DIR/monitor_hitrate.sh"
}

cmd_stats() {
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║              VARNISH DETAILED STATISTICS                       ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo ""
    varnishstat -1
}

cmd_top() {
    echo "Top cached URLs (press Ctrl+C to stop):"
    varnishtop -i ReqURL
}

cmd_log() {
    echo "Recent Varnish log:"
    varnishlog -n 100
}

# ============================================================================
# CACHE CLEARING COMMANDS
# ============================================================================
cmd_clear() {
    echo "Clearing ALL cache..."
    varnishadm 'ban req.url ~ .'
    echo "✓ All cache cleared"
}

cmd_clear_prod() {
    echo "Clearing production cache..."
    varnishadm 'ban req.http.host == "technostationery.com"'
    echo "✓ Production cache cleared"
}

cmd_clear_beta() {
    echo "Clearing beta cache..."
    varnishadm 'ban req.http.host == "beta.technostationery.com"'
    echo "✓ Beta cache cleared"
}

cmd_clear_url() {
    if [ -z "$1" ]; then
        echo "Error: URL pattern required"
        echo "Usage: $0 clear-url <pattern>"
        echo "Example: $0 clear-url '/products/*'"
        exit 1
    fi
    
    echo "Clearing cache for pattern: $1"
    varnishadm "ban req.url ~ $1"
    echo "✓ Cache cleared for pattern: $1"
}

# ============================================================================
# VCL MANAGEMENT
# ============================================================================
cmd_reload() {
    echo "Reloading Varnish VCL configuration..."
    systemctl reload varnish
    echo "✓ VCL reloaded"
}

cmd_restart() {
    echo "Restarting Varnish service..."
    systemctl restart varnish
    echo "✓ Varnish restarted"
}

cmd_test_vcl() {
    if [ -f "$SCRIPT_DIR/optimized_magento.vcl" ]; then
        echo "Testing optimized Magento VCL..."
        varnishd -C -f "$SCRIPT_DIR/optimized_magento.vcl" > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo "✓ VCL syntax is valid"
        else
            echo "✗ VCL syntax error"
            varnishd -C -f "$SCRIPT_DIR/optimized_magento.vcl"
        fi
    else
        echo "Testing current VCL..."
        varnishd -C -f /etc/varnish/default.vcl > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo "✓ VCL syntax is valid"
        else
            echo "✗ VCL syntax error"
            varnishd -C -f /etc/varnish/default.vcl
        fi
    fi
}

cmd_apply_optimized() {
    if [ ! -f "$SCRIPT_DIR/optimized_magento.vcl" ]; then
        echo "✗ Error: optimized_magento.vcl not found"
        exit 1
    fi
    
    echo "Applying optimized Magento VCL..."
    echo ""
    echo "This will:"
    echo "  1. Backup current VCL"
    echo "  2. Copy optimized VCL to /etc/varnish/default.vcl"
    echo "  3. Test VCL syntax"
    echo "  4. Reload Varnish"
    echo ""
    read -p "Continue? (y/N): " -n 1 -r
    echo ""
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Aborted"
        exit 1
    fi
    
    # Backup
    BACKUP="/etc/varnish/default.vcl.backup.$(date +%Y%m%d_%H%M%S)"
    cp /etc/varnish/default.vcl "$BACKUP"
    echo "✓ Backed up to: $BACKUP"
    
    # Test new VCL
    varnishd -C -f "$SCRIPT_DIR/optimized_magento.vcl" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "✗ VCL syntax error, aborting"
        varnishd -C -f "$SCRIPT_DIR/optimized_magento.vcl"
        exit 1
    fi
    
    # Copy and reload
    cp "$SCRIPT_DIR/optimized_magento.vcl" /etc/varnish/default.vcl
    echo "✓ Copied optimized VCL"
    
    systemctl reload varnish
    echo "✓ Varnish reloaded"
    echo ""
    echo "Optimized VCL applied successfully!"
    echo "Monitor hit rate with: $0 monitor"
}

# ============================================================================
# HEALTH COMMANDS
# ============================================================================
cmd_health() {
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║              VARNISH BACKEND HEALTH                            ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo ""
    varnishadm 'backend.list -p'
}

cmd_status() {
    systemctl status varnish
}

# ============================================================================
# MAIN
# ============================================================================

if [ $# -eq 0 ]; then
    show_help
    exit 0
fi

COMMAND=$1
shift

case "$COMMAND" in
    warmup)         cmd_warmup "$@" ;;
    warmup-prod)    cmd_warmup_prod "$@" ;;
    warmup-beta)    cmd_warmup_beta "$@" ;;
    monitor)        cmd_monitor "$@" ;;
    stats)          cmd_stats "$@" ;;
    top)            cmd_top "$@" ;;
    log)            cmd_log "$@" ;;
    clear)          cmd_clear "$@" ;;
    clear-prod)     cmd_clear_prod "$@" ;;
    clear-beta)     cmd_clear_beta "$@" ;;
    clear-url)      cmd_clear_url "$@" ;;
    reload)         cmd_reload "$@" ;;
    restart)        cmd_restart "$@" ;;
    test-vcl)       cmd_test_vcl "$@" ;;
    apply-optimized) cmd_apply_optimized "$@" ;;
    health)         cmd_health "$@" ;;
    status)         cmd_status "$@" ;;
    help|-h|--help) show_help ;;
    *)
        echo "Unknown command: $COMMAND"
        echo ""
        show_help
        exit 1
        ;;
esac

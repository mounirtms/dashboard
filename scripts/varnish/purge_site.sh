#!/bin/bash
###############################################################################
# Site-Specific Varnish Cache Purge
# Purpose: Clear Varnish cache for a specific site only
# Usage: ./purge_site.sh [dashboard|beta|technostationery|all]
###############################################################################

set -e

# Configuration
VARNISH_ADMIN="127.0.0.1:6082"
VARNISH_HOST="127.0.0.1"
VARNISH_PORT="6081"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Usage
if [ -z "$1" ]; then
    echo "Usage: $0 [dashboard|beta|technostationery|all]"
    echo ""
    echo "Examples:"
    echo "  $0 dashboard          # Clear only dashboard.technostationery.com cache"
    echo "  $0 beta               # Clear only beta.technostationery.com cache"
    echo "  $0 technostationery   # Clear only technostationery.com cache"
    echo "  $0 all                # Clear ALL site caches (use with caution)"
    exit 1
fi

SITE="$1"

echo "========================================="
echo "Varnish Cache Purge - Site: $SITE"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Function to purge site-specific cache
purge_site() {
    local site_name=$1
    local site_domain=$2
    
    log_info "Purging cache for: $site_name ($site_domain)"
    
    # Use varnishadm to ban URLs matching the host
    if command -v varnishadm &> /dev/null; then
        # Ban specific to this site's host header
        varnishadm -T ${VARNISH_ADMIN} "ban req.http.host ~ ${site_domain}" 2>&1
        if [ $? -eq 0 ]; then
            log_info "✓ Cache purged for $site_name"
        else
            log_error "✗ Failed to purge cache for $site_name"
            return 1
        fi
    else
        log_error "varnishadm not found"
        return 1
    fi
    
    # Alternative: HTTP PURGE request with specific host
    if command -v curl &> /dev/null; then
        curl -s -X PURGE -H "Host: ${site_domain}" http://${VARNISH_HOST}:${VARNISH_PORT}/ > /dev/null 2>&1
        log_info "HTTP PURGE request sent"
    fi
}

# Purge based on site
case "$SITE" in
    dashboard)
        purge_site "Dashboard" "dashboard.technostationery.com"
        ;;
    beta)
        purge_site "Beta" "beta.technostationery.com"
        ;;
    technostationery)
        purge_site "Technostationery" "technostationery.com"
        ;;
    all)
        log_warn "Purging ALL site caches..."
        purge_site "Dashboard" "dashboard.technostationery.com"
        purge_site "Beta" "beta.technostationery.com"
        purge_site "Technostationery" "technostationery.com"
        ;;
    *)
        log_error "Unknown site: $SITE"
        echo "Valid options: dashboard, beta, technostationery, all"
        exit 1
        ;;
esac

echo ""

# Show cache statistics
log_info "=== VARNISH STATISTICS ==="
if command -v varnishstat &> /dev/null; then
    varnishstat -1 | grep -E "cache_hit|cache_miss|n_object" | while read line; do
        echo "  $line"
    done
fi

echo ""
echo "========================================="
log_info "Purge completed at $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="

exit 0

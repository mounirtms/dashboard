#!/bin/bash
###############################################################################
# PIM Akeneo Maintenance and Fix Script
# Run: sudo ./pim_maintenance.sh
###############################################################################

set -e

echo "=========================================="
echo "PIM AKENEO MAINTENANCE SCRIPT"
echo "Started: $(date)"
echo "=========================================="

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    log_error "Please run as root (sudo)"
    exit 1
fi

PIM_HOME="/home/pim/public_html"
cd "$PIM_HOME"

###############################################################################
# STEP 1: Clear Cache
###############################################################################
echo ""
echo "=== STEP 1: Clearing Cache ==="
log_info "Clearing Symfony cache..."
rm -rf var/cache/*
rm -rf var/logs/*
rm -rf public/cache/*
log_info "Cache cleared"

###############################################################################
# STEP 2: Fix Permissions
###############################################################################
echo ""
echo "=== STEP 2: Fixing Permissions ==="
log_info "Fixing file permissions..."
chown -R pim:pim "$PIM_HOME/public"
chown -R pim:pim "$PIM_HOME/var"
chmod -R 755 bin/
chmod -R 644 vendor/
find var -type d -exec chmod 775 {} \;
find var -type f -exec chmod 664 {} \;
log_info "Permissions fixed"

###############################################################################
# STEP 3: Warmup Varnish (Optional)
###############################################################################
echo ""
echo "=== STEP 3: Varnish Warmup ==="
# Warmup PIM URLs
URLS=(
    "/"
    "/css/pim.css"
    "/login"
    "/api/rest/v1/products?limit=1"
)

for url in "${URLS[@]}"; do
    curl -s -k "https://pim.technostationery.com$url" -o /dev/null -w "  $url: %{http_code}\n" || true
done

###############################################################################
# STEP 4: Database Health Check
###############################################################################
echo ""
echo "=== STEP 4: Database Health ==="
log_info "Testing database connection..."
/opt/mariadb10.6/mariadb/bin/mysql -u akeneo_pim -pakeneo_pim -h 127.0.0.1 -P 3307 akeneo_pim -e "SELECT 1;" 2>/dev/null && log_info "Database: OK" || log_error "Database: FAILED"

###############################################################################
# STEP 5: Elasticsearch Health
###############################################################################
echo ""
echo "=== STEP 5: Elasticsearch Health ==="
ES_STATUS=$(curl -s localhost:9200/_cluster/health | grep -o '"status":"[^"]*"' | cut -d'"' -f3)
log_info "Elasticsearch Status: $ES_STATUS"
if [ "$ES_STATUS" = "green" ]; then
    log_info "Elasticsearch: OK"
else
    log_warn "Elasticsearch: $ES_STATUS (not green)"
fi

###############################################################################
# STEP 6: Test PIM Endpoints
###############################################################################
echo ""
echo "=== STEP 6: Testing Endpoints ==="
log_info "Testing PIM endpoints..."

# Test homepage
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://pim.technostationery.com/" 2>/dev/null)
log_info "Homepage: $HTTP_CODE"

# Test CSS
CSS_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://pim.technostationery.com/css/pim.css" 2>/dev/null)
log_info "CSS: $CSS_CODE"

# Test API (if accessible)
# API_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://pim.technostationery.com/api/rest/v1/products?limit=1" 2>/dev/null)
# log_info "API: $API_CODE"

###############################################################################
# SUMMARY
###############################################################################
echo ""
echo "=========================================="
echo "MAINTENANCE COMPLETE"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Test login: https://pim.technostationery.com/login"
echo "2. Check browser console for JS/CSS errors"
echo "3. Test product catalog"
echo "=========================================="

exit 0
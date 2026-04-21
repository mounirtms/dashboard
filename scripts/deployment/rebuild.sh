#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# Rebuild / Reindex Script
# Full rebuild: DI compile, static content deploy, reindex, cache flush
# Usage:
#   bash rebuild.sh prod           # Full rebuild
#   bash rebuild.sh prod --index   # Only reindex
#   bash rebuild.sh prod --compile # Only compile
#   bash rebuild.sh prod --static  # Only static content
#   bash rebuild.sh prod --cache   # Only flush cache
# ═══════════════════════════════════════════════════════════════════════

set -e
ENV="${1:-prod}"
MODE="${2:-full}"

declare -A PATHS=(
    [prod]="/home/technadminy7/public_html"
    [beta]="/home/beta/public_html"
    [dev]="/home/dev/public_html"
    [pim]="/home/pim/public_html"
)
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
SITE_PATH="${PATHS[$ENV]}"

if [ -z "$SITE_PATH" ]; then
    echo "Unknown env: $ENV. Use: prod, beta, dev, pim"
    exit 1
fi

log() { echo "[$(date '+%H:%M:%S')] $1"; }
cd "$SITE_PATH"

log "=== REBUILD: $ENV ($MODE) ==="
log "Path: $SITE_PATH"
log "Load before: $(uptime)"

FULL=false
[ "$MODE" = "full" ] && FULL=true
[ "$MODE" = "--all" ] && FULL=true

# Enable maintenance for full rebuild
if $FULL; then
    log "[1/6] Enabling maintenance mode..."
    $PHP bin/magento maintenance:enable 2>/dev/null || true
fi

# DI Compile
if $FULL || [ "$MODE" = "--compile" ]; then
    log "[2/6] Running DI compile..."
    $PHP bin/magento setup:di:compile 2>&1 | tail -3
    log "  Done"
fi

# Static content
if $FULL || [ "$MODE" = "--static" ]; then
    log "[3/6] Deploying static content..."
    $PHP bin/magento setup:static-content:deploy -f 2>&1 | tail -3
    log "  Done"
fi

# Reindex
if $FULL || [ "$MODE" = "--index" ]; then
    log "[4/6] Reindexing..."
    $PHP bin/magento indexer:reindex 2>&1 | tail -5
    log "  Done"
fi

# Cache flush
if $FULL || [ "$MODE" = "--cache" ]; then
    log "[5/6] Flushing cache..."
    $PHP bin/magento cache:clean 2>&1 | tail -2
    $PHP bin/magento cache:flush 2>&1 | tail -2
    log "  Done"
fi

# Disable maintenance
if $FULL; then
    log "[6/6] Disabling maintenance mode..."
    $PHP bin/magento maintenance:disable 2>/dev/null || true
fi

# Fix permissions
log "Fixing permissions..."
chown -R "$(stat -c '%U' "$SITE_PATH")" "$SITE_PATH/pub/static" "$SITE_PATH/var" "$SITE_PATH/generated" 2>/dev/null || true

log "=== REBUILD COMPLETE ==="
log "Load after: $(uptime)"

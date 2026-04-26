#!/bin/bash
# Nightly Cache Flush Script
# Clears and warms up Magento cache during low-traffic hours
# Scheduled: Daily at 5:00 AM

MAGENTO_ROOT="/home/technadminy7/public_html"
LOGFILE="$MAGENTO_ROOT/var/log/cache_flush.log"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

log "=== Nightly Cache Flush Starting ==="

# Change to Magento root
cd "$MAGENTO_ROOT" || exit 1

# 1. Check current cache status
log "Checking cache status..."
CACHE_STATUS=$($PHP bin/magento cache:status 2>&1)
log "Current cache status captured"

# 2. Flush Redis cache
log "Flushing Redis cache..."
redis-cli FLUSHDB 2>&1 | while read line; do log "Redis: $line"; done
log "Redis cache flushed"

# 3. Clean Magento file cache
log "Cleaning Magento file cache..."
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* 2>/dev/null
log "File cache cleaned"

# 4. Flush Magento application cache
log "Flushing Magento cache..."
$PHP bin/magento cache:flush 2>&1 | while read line; do
    log "$line"
done

# 5. Clean Magento cache (more thorough)
log "Deep cleaning Magento cache..."
$PHP bin/magento cache:clean 2>&1 | while read line; do
    log "$line"
done

# 6. Warm up critical cache types
log "Warming up cache..."
$PHP bin/magento cache:enable 2>&1 | while read line; do
    log "$line"
done

# 7. Clean generated static files (pub/static/_cache)
log "Cleaning static cache..."
if [ -d "$MAGENTO_ROOT/pub/static/_cache" ]; then
    rm -rf "$MAGENTO_ROOT/pub/static/_cache/*" 2>/dev/null
    log "Static cache cleaned"
fi

# 8. Flush Varnish cache (if configured)
log "Flushing Varnish cache..."
if command -v varnishadm >/dev/null 2>&1; then
    varnishadm "ban req.url ~ ." 2>&1 | while read line; do
        log "Varnish: $line"
    done
else
    log "Varnish not available or not configured"
fi

# 9. Fix permissions on cache directories
log "Fixing cache permissions..."
chmod -R 775 var/cache var/page_cache var/view_preprocessed 2>/dev/null
chown -R technadminy7:technadminy7 var/cache var/page_cache var/view_preprocessed 2>/dev/null
log "Cache permissions fixed"

# 10. Report cache status after flush
log "Reporting new cache status..."
$PHP bin/magento cache:status 2>&1 | while read line; do
    log "$line"
done

# 11. Memory report
REDIS_MEM=$(redis-cli INFO memory 2>/dev/null | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
log "Redis memory usage: $REDIS_MEM"

log "=== Nightly Cache Flush Completed ==="

# Keep only last 1000 lines of this log
tail -1000 "$LOGFILE" > "$LOGFILE.tmp" 2>/dev/null && mv "$LOGFILE.tmp" "$LOGFILE" 2>/dev/null

exit 0

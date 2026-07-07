#!/bin/bash
# Server Tuning Verification Script
# Checks all applied configurations and reports status

echo "=================================================="
echo "  Server Infrastructure Tuning Verification"
echo "  Date: $(date)"
echo "=================================================="
echo ""

# 1. Redis
echo "--- REDIS ---"
echo "Port: $(redis-cli INFO server 2>/dev/null | grep tcp_port | head -1)"
echo "Unix Socket: $(redis-cli INFO server 2>/dev/null | grep unix_socket)"
echo "Max Memory: $(redis-cli INFO memory 2>/dev/null | grep maxmemory_human)"
echo "Used Memory: $(redis-cli INFO memory 2>/dev/null | grep used_memory_human)"
echo "Eviction Policy: $(redis-cli INFO memory 2>/dev/null | grep maxmemory_policy)"
echo "Hz: $(redis-cli INFO server 2>/dev/null | grep ^hz:)"
echo "RDB Saves: $(redis-cli INFO persistence 2>/dev/null | grep 'rdb_changes_since_last_save')"
echo "Keyspace:"
redis-cli INFO keyspace 2>/dev/null | grep -E "^db[0-9]"
echo ""

# Calculate hit rate
HITS=$(redis-cli INFO stats 2>/dev/null | grep keyspace_hits | awk -F: '{print $2}')
MISSES=$(redis-cli INFO stats 2>/dev/null | grep keyspace_misses | awk -F: '{print $2}')
if [ -n "$HITS" ] && [ -n "$MISSES" ]; then
    TOTAL=$((HITS + MISSES))
    if [ $TOTAL -gt 0 ]; then
        RATE=$(echo "scale=2; $HITS * 100 / $TOTAL" | bc)
        echo "Cache Hit Rate: ${RATE}%"
    fi
fi
echo ""

# 2. OPcache
echo "--- OPCACHE (PHP 8.2) ---"
php -r '
$status = opcache_get_status();
if ($status) {
    echo "Memory Used: " . round($status["memory_usage"]["used_memory"] / 1024 / 1024, 1) . "MB / " . round($status["memory_usage"]["memory_consumption"] / 1024 / 1024, 1) . "MB\n";
    echo "Hit Rate: " . $status["opcache_statistics"]["hit_rate"] . "%\n";
    echo "Cached Files: " . $status["opcache_statistics"]["num_cached_files"] . "\n";
    echo "JIT Enabled: " . ($status["jit"]["on"] ? "YES" : "NO") . "\n";
    if ($status["jit"]["on"]) {
        echo "JIT Type: " . $status["jit"]["kind"] . "\n";
        echo "JIT Buffer: " . round($status["jit"]["buffer_size"] / 1024 / 1024, 1) . "MB\n";
    }
} else {
    echo "OPcache not available in CLI (normal if not restarted yet)\n";
}
'
echo ""

# 3. Varnish
echo "--- VARNISH ---"
echo "Status: $(systemctl is-active varnish 2>/dev/null || echo 'not running')"
echo "Listen Port: $(grep VARNISH_LISTEN_PORT /etc/systemd/system/varnish.service 2>/dev/null | head -1)"
echo "Storage: $(grep VARNISH_STORAGE /etc/systemd/system/varnish.service 2>/dev/null | head -1)"
echo "Threads: min=$(grep VARNISH_MIN_THREADS /etc/systemd/system/varnish.service 2>/dev/null | head -1), max=$(grep VARNISH_MAX_THREADS /etc/systemd/system/varnish.service 2>/dev/null | head -1)"
echo ""

# Varnish stats
if systemctl is-active varnish > /dev/null 2>&1; then
    HIT=$(varnishstat -1 -f cache_hit 2>/dev/null | awk '{print $2}')
    MISS=$(varnishstat -1 -f cache_miss 2>/dev/null | awk '{print $2}')
    if [ -n "$HIT" ] && [ -n "$MISS" ]; then
        TOTAL=$((HIT + MISS))
        if [ $TOTAL -gt 0 ]; then
            RATE=$((HIT * 100 / TOTAL))
            echo "Cache Hit Rate: ${RATE}% (hits: $HIT, misses: $MISS)"
        fi
    fi
fi
echo ""

# 4. System Memory
echo "--- SYSTEM MEMORY ---"
free -h | grep -E "Mem|Swap"
echo ""

# 5. Apache
echo "--- APACHE ---"
echo "MPM: $(apachectl -M 2>/dev/null | grep -i mpm)"
echo "Status: $(systemctl is-active httpd 2>/dev/null || echo 'not running')"
echo ""

# 6. PHP-FPM
echo "--- PHP-FPM ---"
echo "Status: $(systemctl is-active ea-php82-php-fpm 2>/dev/null || echo 'not running')"
if [ -f /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf ]; then
    grep -E "^pm\s*=|^pm\.max_children" /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf 2>/dev/null
fi
echo ""

echo "=================================================="
echo "  Verification Complete"
echo "=================================================="

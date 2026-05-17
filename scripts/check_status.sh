#!/bin/bash
# ============================================================================
# Technostationery System Status Check
# Quick overview of all critical services and Varnish performance
# ============================================================================

MAGENTO="/home/technadminy7/public_html"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"

echo "================================================================"
echo "TECHNOSTATIONERY STATUS CHECK - $(date '+%Y-%m-%d %H:%M:%S')"
echo "================================================================"

# ─── Server Load ──────────────────────────────────────────────────────
echo ""
echo "=== Server Load ==="
uptime

# ─── Services Status ──────────────────────────────────────────────────
echo ""
echo "=== Services ==="
for svc in httpd varnish elasticsearch mariadb10.6 redis ea-php82-php-fpm; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
        echo "  [OK]     $svc"
    else
        echo "  [DOWN]   $svc"
    fi
done

# ─── Varnish Stats ────────────────────────────────────────────────────
echo ""
echo "=== Varnish Cache ==="
varnishstat -1 2>/dev/null | grep -E "MAIN\.(cache_hit|cache_miss|client_req|n_object)" | while read key val rest; do
    name=$(echo "$key" | sed 's/MAIN\.//')
    printf "  %-20s %s\n" "$name:" "$val"
done

HIT=$(varnishstat -1 2>/dev/null | grep "MAIN.cache_hit " | awk '{print $2}')
MISS=$(varnishstat -1 2>/dev/null | grep "MAIN.cache_miss " | awk '{print $2}')
TOTAL=$((HIT + MISS))
if [ "$TOTAL" -gt 0 ]; then
    RATE=$((HIT * 100 / TOTAL))
    echo "  Hit Rate:            ${RATE}%"
fi

# ─── Backend Health ───────────────────────────────────────────────────
echo ""
echo "=== Backend Health ==="
UNHEALTHY=$(varnishstat -1 2>/dev/null | grep "VBE.*default.unhealthy" | tail -1 | awk '{print $2}')
echo "  Backend unhealthy:   ${UNHEALTHY:-0}"

# ─── Website Test ─────────────────────────────────────────────────────
echo ""
echo "=== Website ==="
for url in "/" "/tous-les-produits.html" "/promos.html"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: technostationery.com" --max-time 5 "http://127.0.0.1:80$url")
    if [ "$code" = "200" ]; then
        echo "  [OK]     $url -> $code"
    else
        echo "  [!!]     $url -> $code"
    fi
done

# ─── Elasticsearch ────────────────────────────────────────────────────
echo ""
echo "=== Elasticsearch ==="
ES_STATUS=$(curl -s "http://localhost:9200/_cluster/health" 2>/dev/null | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
if [ -n "$ES_STATUS" ]; then
    echo "  Cluster status:      $ES_STATUS"
    curl -s "http://localhost:9200/_cat/indices?v&h=health,index,docs.count,store.size&s=health" 2>/dev/null | head -15
else
    echo "  [DOWN]   Cannot reach Elasticsearch"
fi

# ─── Magento Cron ─────────────────────────────────────────────────────
echo ""
echo "=== Magento Cron ==="
if [ -f "$MAGENTO/var/log/cron.log" ]; then
    LAST_CRON=$(tail -1 "$MAGENTO/var/log/cron.log" 2>/dev/null | grep -o '^\[[^]]*\]' | tr -d '[]')
    echo "  Last cron run:       ${LAST_CRON:-unknown}"
fi

# ─── Disk Usage ───────────────────────────────────────────────────────
echo ""
echo "=== Disk Usage ==="
du -sh "$MAGENTO/var/log/" 2>/dev/null | awk '{print "  Magento logs:        " $1}'
du -sh "/home/dashboard/public_html/logs/" 2>/dev/null | awk '{print "  Dashboard logs:    " $1}'
df -h / | tail -1 | awk '{print "  Disk usage:          " $5 " used (" $4 " available)"}'

echo ""
echo "================================================================"

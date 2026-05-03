#!/bin/bash
# Warm up Varnish cache by requesting key pages

echo "🔥 Warming up Varnish cache..."

DOMAIN="http://localhost"
URLS=(
    "/"
    "/index.html"
    "/monitoring-dashboard.html"
    "/api/system/metrics"
    "/api/elasticsearch/status"
    "/api/redis/status"
)

for url in "${URLS[@]}"; do
    echo -n "  Requesting $url ... "
    curl -s -o /dev/null -w "%{http_code}" "$DOMAIN$url"
    echo " ✓"
    sleep 0.2
done

echo ""
echo "✅ Cache warmup complete"
echo ""
varnishstat -1 | grep -E "MAIN\.(cache_hit|cache_miss|client_req)" | awk '{print "  " $1 " = " $2}'

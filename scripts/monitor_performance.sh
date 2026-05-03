#!/bin/bash
# Real-time performance monitoring

echo "=== INFRASTRUCTURE PERFORMANCE MONITOR ==="
echo "Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Varnish Stats
if command -v varnishstat &> /dev/null; then
    echo "📊 VARNISH CACHE:"
    stats=$(varnishstat -1 2>/dev/null)
    hits=$(echo "$stats" | grep "MAIN.cache_hit " | awk '{print $2}')
    misses=$(echo "$stats" | grep "MAIN.cache_miss " | awk '{print $2}')
    total=$((hits + misses))
    if [[ $total -gt 0 ]]; then
        hit_rate=$(echo "scale=2; ($hits / $total) * 100" | bc)
        echo "  Hit Rate: ${hit_rate}% (${hits} hits, ${misses} misses)"
    fi
fi

# Redis Stats
if redis-cli ping > /dev/null 2>&1; then
    echo ""
    echo "🔴 REDIS:"
    stats=$(redis-cli info stats 2>/dev/null)
    hits=$(echo "$stats" | grep "keyspace_hits:" | cut -d: -f2 | tr -d '\r')
    misses=$(echo "$stats" | grep "keyspace_misses:" | cut -d: -f2 | tr -d '\r')
    if [[ -n "$hits" ]] && [[ -n "$misses" ]]; then
        total=$((hits + misses))
        if [[ $total -gt 0 ]]; then
            hit_rate=$(echo "scale=2; ($hits / $total) * 100" | bc)
            echo "  Hit Rate: ${hit_rate}% (${hits} hits, ${misses} misses)"
        fi
    fi
fi

# Elasticsearch
if curl -s localhost:9200/_cluster/health > /dev/null 2>&1; then
    echo ""
    echo "🔍 ELASTICSEARCH:"
    health=$(curl -s localhost:9200/_cluster/health)
    status=$(echo "$health" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    echo "  Cluster Status: ${status}"
fi

# System Resources
echo ""
echo "💻 SYSTEM RESOURCES:"
echo "  CPU Load: $(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}')"
echo "  Memory: $(free | grep Mem | awk '{printf "%.1f%%", $3/$2 * 100}')"
echo "  Disk: $(df / | tail -1 | awk '{printf "%s (%s)", $5, $4}')"

echo ""
echo "=========================================="

#!/bin/bash
# Complete Infrastructure Health Report
# Comprehensive status check and recommendations

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║    COMPLETE INFRASTRUCTURE HEALTH REPORT - $(date +%Y-%m-%d)    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    local service=$1
    local status=$2
    local details=$3
    
    if [ "$status" = "OK" ]; then
        echo -e "${GREEN}✅ $service${NC}: $details"
    elif [ "$status" = "WARN" ]; then
        echo -e "${YELLOW}⚠️  $service${NC}: $details"
    else
        echo -e "${RED}❌ $service${NC}: $details"
    fi
}

echo "═══════════════════════════════════════════════════════════════"
echo "📊 SERVICE STATUS CHECKS"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# 1. VARNISH
echo "🟢 VARNISH CACHE:"
if systemctl is-active varnish > /dev/null 2>&1; then
    hits=$(varnishstat -1 | grep "MAIN.cache_hit " | awk '{print $2}')
    misses=$(varnishstat -1 | grep "MAIN.cache_miss " | awk '{print $2}')
    total=$((hits + misses))
    if [ $total -gt 0 ]; then
        rate=$(echo "scale=2; ($hits * 100) / $total" | bc)
        if (( $(echo "$rate >= 80" | bc -l) )); then
            print_status "Varnish" "OK" "Running, Hit Rate: ${rate}% (Target: ≥80%)"
        elif (( $(echo "$rate >= 50" | bc -l) )); then
            print_status "Varnish" "WARN" "Running, Hit Rate: ${rate}% (Target: ≥80%)"
        else
            print_status "Varnish" "FAIL" "Running, Hit Rate: ${rate}% (Critical low)"
        fi
    else
        print_status "Varnish" "WARN" "Running, no traffic yet"
    fi
    
    # Check backend health
    backend_status=$(varnishadm backend.list | grep -o "Healthy\|Sick" | head -1)
    if [ "$backend_status" = "Healthy" ]; then
        print_status "Backend" "OK" "Healthy"
    else
        print_status "Backend" "FAIL" "Unhealthy"
    fi
else
    print_status "Varnish" "FAIL" "Not running"
fi
echo ""

# 2. CLOUDFLARE
echo "☁️  CLOUDFLARE:"
if php /home/dashboard/public_html/scripts/test_cloudflare_graphql.php 2>&1 | grep -q "Total Zones: 5"; then
    print_status "Cloudflare" "OK" "5 zones active, API working"
else
    print_status "Cloudflare" "WARN" "API connection issues"
fi
echo ""

# 3. REDIS
echo "🔴 REDIS:"
if redis-cli ping > /dev/null 2>&1; then
    hits=$(redis-cli info stats | grep "keyspace_hits" | cut -d: -f2 | tr -d '\r')
    misses=$(redis-cli info stats | grep "keyspace_misses" | cut -d: -f2 | tr -d '\r')
    total=$((hits + misses))
    if [ $total -gt 0 ]; then
        rate=$(echo "scale=2; ($hits * 100) / $total" | bc)
        if (( $(echo "$rate >= 90" | bc -l) )); then
            print_status "Redis" "OK" "Running, Hit Rate: ${rate}%"
        elif (( $(echo "$rate >= 80" | bc -l) )); then
            print_status "Redis" "WARN" "Running, Hit Rate: ${rate}%"
        else
            print_status "Redis" "FAIL" "Running, Hit Rate: ${rate}% (Low)"
        fi
    fi
else
    print_status "Redis" "FAIL" "Not running"
fi
echo ""

# 4. ELASTICSEARCH
echo "🔍 ELASTICSEARCH:"
status=$(curl -s http://localhost:9200/_cluster/health | python3 -c "import json,sys; print(json.load(sys.stdin).get('status','unknown'))" 2>/dev/null)
if [ "$status" = "green" ]; then
    print_status "Elasticsearch" "OK" "Cluster GREEN"
elif [ "$status" = "yellow" ]; then
    print_status "Elasticsearch" "WARN" "Cluster YELLOW"
elif [ "$status" = "red" ]; then
    print_status "Elasticsearch" "FAIL" "Cluster RED"
else
    print_status "Elasticsearch" "FAIL" "Not responding"
fi
echo ""

# 5. SYSTEM RESOURCES
echo "💻 SYSTEM RESOURCES:"
cpu_load=$(uptime | awk -F'load average:' '{print $2}' | cut -d',' -f1 | xargs)
mem_pct=$(free | grep Mem | awk '{printf "%.0f", ($3/$2)*100}')
disk_pct=$(df -h / | tail -1 | awk '{print $5}' | tr -d '%')

if (( $(echo "$cpu_load < 4" | bc -l) )); then
    print_status "CPU Load" "OK" "${cpu_load}"
elif (( $(echo "$cpu_load < 8" | bc -l) )); then
    print_status "CPU Load" "WARN" "${cpu_load}"
else
    print_status "CPU Load" "FAIL" "${cpu_load} (High)"
fi

if [ $mem_pct -lt 80 ]; then
    print_status "Memory" "OK" "${mem_pct}% used"
elif [ $mem_pct -lt 90 ]; then
    print_status "Memory" "WARN" "${mem_pct}% used"
else
    print_status "Memory" "FAIL" "${mem_pct}% used (Critical)"
fi

if [ $disk_pct -lt 80 ]; then
    print_status "Disk" "OK" "${disk_pct}% used"
elif [ $disk_pct -lt 90 ]; then
    print_status "Disk" "WARN" "${disk_pct}% used"
else
    print_status "Disk" "FAIL" "${disk_pct}% used (Critical)"
fi
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo "📈 PERFORMANCE METRICS"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Varnish detailed stats
if systemctl is-active varnish > /dev/null 2>&1; then
    echo "🟢 Varnish Cache Statistics:"
    varnishstat -1 | grep -E "MAIN\.(cache_hit|cache_miss|client_req)" | awk '{printf "  %-35s: %10s\n", $1, $2}'
    echo ""
fi

# Redis detailed stats
if redis-cli ping > /dev/null 2>&1; then
    echo "🔴 Redis Statistics:"
    echo "  Keyspace Hits:                      $(redis-cli info stats | grep keyspace_hits | cut -d: -f2 | tr -d '\r' | xargs printf "%'d")"
    echo "  Keyspace Misses:                    $(redis-cli info stats | grep keyspace_misses | cut -d: -f2 | tr -d '\r' | xargs printf "%'d")"
    echo "  Connected Clients:                  $(redis-cli info clients | grep connected_clients | cut -d: -f2 | tr -d '\r')"
    echo ""
fi

echo "═══════════════════════════════════════════════════════════════"
echo "💡 RECOMMENDATIONS"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Generate recommendations
recommendations=0

# Check Varnish hit rate
if systemctl is-active varnish > /dev/null 2>&1; then
    hits=$(varnishstat -1 | grep "MAIN.cache_hit " | awk '{print $2}')
    misses=$(varnishstat -1 | grep "MAIN.cache_miss " | awk '{print $2}')
    total=$((hits + misses))
    if [ $total -gt 0 ]; then
        rate=$(echo "scale=2; ($hits * 100) / $total" | bc)
        if (( $(echo "$rate < 80" | bc -l) )); then
            echo "🔧 Varnish Hit Rate (${rate}%):"
            echo "   → Run: bash /home/dashboard/public_html/scripts/varnish_advanced_tuning.sh"
            echo "   → Target: ≥80% hit rate"
            recommendations=$((recommendations + 1))
            echo ""
        fi
    fi
fi

# Check Cloudflare settings
echo "☁️  Cloudflare Optimization:"
echo "   → Run: bash /home/dashboard/public_html/scripts/optimize_cloudflare.sh"
echo "   → View Page Rules: bash /home/dashboard/public_html/scripts/cloudflare_cache_rules.sh"
recommendations=$((recommendations + 1))
echo ""

# Check for automated monitoring
if ! crontab -l 2>/dev/null | grep -q "infrastructure_audit.php"; then
    echo "⏰ Automated Monitoring:"
    echo "   → Add to crontab:"
    echo "     0 */6 * * * /usr/bin/php /home/dashboard/public_html/scripts/infrastructure_audit.php"
    recommendations=$((recommendations + 1))
    echo ""
fi

if [ $recommendations -eq 0 ]; then
    echo "✅ No critical recommendations at this time"
    echo ""
fi

echo "═══════════════════════════════════════════════════════════════"
echo "📊 QUICK STATS SUMMARY"
echo "═══════════════════════════════════════════════════════════════"
echo ""

if [ -f /home/dashboard/public_html/logs/audit_reports/audit_*.json ]; then
    latest_audit=$(ls -t /home/dashboard/public_html/logs/audit_reports/audit_*.json | head -1)
    if [ -f "$latest_audit" ]; then
        echo "Latest Audit: $(basename $latest_audit)"
        echo "Overall Score: $(cat $latest_audit | python3 -c "import json,sys; print(json.load(sys.stdin).get('overall_score', 'N/A'))" 2>/dev/null)/100"
        echo ""
    fi
fi

echo "═══════════════════════════════════════════════════════════════"
echo "✅ REPORT COMPLETE"
echo "═══════════════════════════════════════════════════════════════"
echo ""

#!/bin/bash
# Quick Redis and Varnish Status Summary
# Location: /home/technadminy7/public_html/scripts/status_summary.sh

echo "=== QUICK SYSTEM STATUS ==="
echo ""

# Redis Status
echo "REDIS:"
if redis-cli ping > /dev/null 2>&1; then
    echo "  ✓ Running"
    MEMORY=$(redis-cli info memory | grep used_memory_human | cut -d: -f2)
    KEYS=$(redis-cli dbsize)
    echo "  Memory: $MEMORY"
    echo "  Keys: $KEYS"
else
    echo "  ✗ NOT RUNNING"
fi

echo ""

# Varnish Status
echo "VARNISH:"
if pgrep varnishd > /dev/null 2>&1; then
    echo "  ✓ Running"
    # Try to get basic stats
    if command -v varnishstat > /dev/null 2>&1; then
        UPTIME=$(varnishstat -1 -f MAIN.uptime 2>/dev/null | awk '{print int($2/3600)"h "int(($2%3600)/60)"m"}')
        if [ ! -z "$UPTIME" ]; then
            echo "  Uptime: $UPTIME"
        fi
    fi
else
    echo "  ✗ NOT RUNNING"
fi

echo ""

# System Resources
echo "SYSTEM:"
CPU=$(top -bn1 | grep "Cpu(s)" | awk '{print int($2)}')
MEM=$(free | grep Mem | awk '{printf("%.1f%%", $3/$2 * 100.0)}')
DISK=$(df -h / | tail -1 | awk '{print $5}')

echo "  CPU: ${CPU}%"
echo "  Memory: $MEM"
echo "  Disk: $DISK"

echo ""
echo "Last checked: $(date)"
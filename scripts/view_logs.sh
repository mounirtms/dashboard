#!/bin/bash
# ============================================================================
# Technostationery Log Viewer
# View recent logs from Magento and Dashboard
# Usage: ./view_logs.sh [magento|dashboard|varnish|cron|all] [lines]
# ============================================================================

MAGENTO="/home/technadminy7/public_html"
DASHBOARD="/home/dashboard/public_html"
LINES=${2:-50}

case "${1:-all}" in
    magento)
        echo "=== Magento System Log (last $LINES lines) ==="
        tail -$LINES "$MAGENTO/var/log/system.log" 2>/dev/null
        echo ""
        echo "=== Magento Exception Log (last $LINES lines) ==="
        tail -$LINES "$MAGENTO/var/log/exception.log" 2>/dev/null
        ;;
    cron)
        echo "=== Magento Cron Log (last $LINES lines) ==="
        tail -$LINES "$MAGENTO/var/log/cron.log" 2>/dev/null
        echo ""
        echo "=== Cron Health (last 20 lines) ==="
        tail -20 "$MAGENTO/var/log/cron_health.log" 2>/dev/null
        ;;
    varnish)
        echo "=== Varnish Warmup Log (last $LINES lines) ==="
        LATEST_WARMUP=$(ls -t "$DASHBOARD/logs/warmup_per_device_"*.log 2>/dev/null | head -1)
        if [ -n "$LATEST_WARMUP" ]; then
            tail -$LINES "$LATEST_WARMUP"
        else
            echo "No warmup logs found"
        fi
        ;;
    dashboard)
        echo "=== Dashboard Alert Log (last $LINES lines) ==="
        tail -$LINES "$DASHBOARD/logs/alert_system.log" 2>/dev/null
        echo ""
        echo "=== Dashboard Load Alerts (last $LINES lines) ==="
        tail -$LINES "$DASHBOARD/logs/load_alerts.log" 2>/dev/null
        ;;
    all)
        echo "=== Magento System Log (last $LINES lines) ==="
        tail -$LINES "$MAGENTO/var/log/system.log" 2>/dev/null
        echo ""
        echo "=== Magento Cron Log (last 20 lines) ==="
        tail -20 "$MAGENTO/var/log/cron.log" 2>/dev/null
        echo ""
        echo "=== Latest Varnish Warmup (last $LINES lines) ==="
        LATEST_WARMUP=$(ls -t "$DASHBOARD/logs/warmup_per_device_"*.log 2>/dev/null | head -1)
        [ -n "$LATEST_WARMUP" ] && tail -$LINES "$LATEST_WARMUP"
        ;;
    *)
        echo "Usage: $0 [magento|dashboard|varnish|cron|all] [lines]"
        echo ""
        echo "  magento   - Magento system and exception logs"
        echo "  cron      - Magento cron execution logs"
        echo "  varnish   - Latest Varnish warmup log"
        echo "  dashboard - Dashboard alert and load logs"
        echo "  all       - All of the above (default)"
        ;;
esac

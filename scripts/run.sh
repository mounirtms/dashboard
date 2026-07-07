#!/bin/bash
# ============================================================================
# Technostationery Script Runner
# Lists and runs available maintenance scripts from the dashboard
# ============================================================================

SCRIPTS_DIR="/home/dashboard/public_html/scripts"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"

case "${1:-list}" in
    list)
        echo "================================================================"
        echo "Available Scripts"
        echo "================================================================"
        echo ""
        echo "STATUS & MONITORING:"
        echo "  ./run.sh status              - Full system status check"
        echo "  ./run.sh quick-status        - Quick service status"
        echo "  ./run.sh logs [type] [lines] - View logs (magento|cron|varnish|dashboard|all)"
        echo ""
        echo "CACHE & PERFORMANCE:"
        echo "  ./run.sh warmup [urls] [parallel] - Warm Varnish cache (default: 500 URLs, 6 parallel)"
        echo "  ./run.sh cache-flush         - Flush Magento cache"
        echo ""
        echo "MAINTENANCE:"
        echo "  ./run.sh cleanup             - Run master cleanup (logs, reports, sessions)"
        echo "  ./run.sh backup              - Run streamlined backup"
        echo "  ./run.sh health-check        - Run health check"
        echo ""
        echo "DEPLOYMENT:"
        echo "  ./run.sh deploy              - Run full deployment pipeline"
        echo ""
        echo "DATABASE:"
        echo "  ./run.sh db-health           - Database health check"
        echo "  ./run.sh db-cleanup          - Clean up database tables"
        echo "  ./run.sh db-backup           - Database backup"
        echo ""
        ;;
    status)
        bash "$SCRIPTS_DIR/check_status.sh"
        ;;
    quick-status)
        bash "$SCRIPTS_DIR/quick_status.sh"
        ;;
    logs)
        bash "$SCRIPTS_DIR/view_logs.sh" "${2:-all}" "${3:-50}"
        ;;
    warmup)
        URLs=${2:-500}
        PARALLEL=${3:-6}
        echo "Starting cache warmup: $URLs URLs, $PARALLEL parallel..."
        $PHP "$SCRIPTS_DIR/warmup_per_device.php" --urls=$URLS --parallel=$PARALLEL
        ;;
    cache-flush)
        cd /home/technadminy7/public_html
        php bin/magento cache:flush
        ;;
    cleanup)
        bash "$SCRIPTS_DIR/maintenance/fix_permissions.sh"
        ;;
    backup)
        bash "$SCRIPTS_DIR/backup/streamlined-backup.sh"
        ;;
    health-check)
        bash "$SCRIPTS_DIR/deployment/health-check.sh"
        ;;
    deploy)
        bash "$SCRIPTS_DIR/deploy-production.sh"
        ;;
    db-health)
        $PHP "$SCRIPTS_DIR/database/database_health_check.php"
        ;;
    db-cleanup)
        $PHP "$SCRIPTS_DIR/database/cleanup_database.php"
        ;;
    db-backup)
        $PHP "$SCRIPTS_DIR/database/database_backup_manager.php"
        ;;
    *)
        echo "Unknown command: $1"
        echo "Run './run.sh list' to see available commands"
        ;;
esac

#!/bin/bash
# ──────────────────────────────────────────────────────────────────────
# Magento Queue Consumer Daemon
# Purpose: Run Magento queue consumers as persistent daemon processes
# This replaces the cron-based consumers_runner which runs every minute
# ──────────────────────────────────────────────────────────────────────

LOCK_DIR="/tmp/magento_consumers"
LOGFILE="/home/technadminy7/public_html/var/log/consumer_daemon.log"
MAGENTO_DIR="/home/technadminy7/public_html"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
MAX_CONSUMERS=3
MEMORY_LIMIT="512M"

mkdir -p "$LOCK_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

log "=== Consumer Daemon Starting ==="

# List of Magento queue consumers to run
CONSUMERS=(
    "product_action_attribute.update"
    "product_action_attribute_frontend.update"
    "import_processor"
    "exportProcessor"
    "code.generator.processor"
)

# Start consumer processes
for i in "${!CONSUMERS[@]}"; do
    CONSUMER="${CONSUMERS[$i]}"
    LOCK_FILE="$LOCK_DIR/${CONSUMER//./_}.lock"

    # Check if already running
    if flock -n "$LOCK_FILE" true 2>/dev/null; then
        log "Starting consumer: $CONSUMER"
        (
            flock -n 200
            cd "$MAGENTO_DIR"
            $PHP bin/magento queue:consumers:start "$CONSUMER" --max-messages=5000 --max-runtime=3600 >> "$LOGFILE" 2>&1
        ) 200>"$LOCK_FILE" &
        sleep 2
    else
        log "Consumer already running: $CONSUMER"
    fi
done

log "Consumer daemon started with ${#CONSUMERS[@]} consumers"

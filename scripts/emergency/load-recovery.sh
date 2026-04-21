#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# Emergency Load Recovery Script
# Use when CPU load > 10 — kills stuck processes, restarts services
# Usage: bash /home/dashboard/public_html/scripts/emergency/load-recovery.sh
# ═══════════════════════════════════════════════════════════════════════

set -e
LOG="/home/dashboard/public_html/logs/emergency_recovery.log"
PROD="/home/technadminy7/public_html"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG"; }

log "=== EMERGENCY LOAD RECOVERY STARTED ==="
log "Current load: $(uptime)"

# Step 1: Kill stuck messenger consumers
log "[1/6] Killing stuck messenger consumers..."
ps aux | grep 'messenger:consume' | grep -v grep | awk '{print $2}' | xargs -r kill -9 2>/dev/null || true
log "  Done"

# Step 2: Kill overlapping Magento cron processes
log "[2/6] Killing overlapping Magento crons..."
ps aux | grep 'magento cron:run' | grep -v grep | awk '{print $2}' | xargs -r kill -9 2>/dev/null || true
log "  Done"

# Step 3: Kill PHP-FPM workers running > 3 minutes
log "[3/6] Killing long-running PHP-FPM workers (>3 min)..."
KILLED=0
for line in $(ps -eo pid,etime,cmd --sort=-etime | grep 'php-fpm: pool' | grep -v master | grep -v grep | head -20); do
    PID=$(echo "$line" | awk '{print $1}')
    ETIME=$(echo "$line" | awk '{print $2}')
    if [[ "$ETIME" == *-* ]]; then
        kill -9 "$PID" 2>/dev/null && KILLED=$((KILLED+1))
    fi
done
log "  Killed $KILLED workers"

# Step 4: Restart PHP-FPM
log "[4/6] Restarting PHP-FPM..."
systemctl restart ea-php82-php-fpm
log "  Done"

# Step 5: Flush Magento cache
log "[5/6] Flushing Magento cache..."
cd "$PROD" && $PHP bin/magento cache:flush 2>&1 | tail -3
log "  Done"

# Step 6: Restart services if load still > 10
LOAD=$(uptime | awk -F'load average: ' '{print $2}' | cut -d, -f1 | tr -d ' ')
LOAD_INT=${LOAD%%.*}
if [ "${LOAD_INT:-0}" -gt 10 ]; then
    log "[6/6] Load still high ($LOAD), restarting key services..."
    systemctl restart elasticsearch 2>/dev/null || true
    systemctl restart varnish 2>/dev/null || true
    sleep 3
    NEW_LOAD=$(uptime | awk -F'load average: ' '{print $2}' | cut -d, -f1 | tr -d ' ')
    log "  Load after: $NEW_LOAD"
else
    log "[6/6] Load is $LOAD — no full restart needed"
fi

FINAL_LOAD=$(uptime)
log "=== RECOVERY COMPLETE ==="
log "Final status: $FINAL_LOAD"

#!/bin/bash
# ============================================================================
# Technostationery Varnish Cache Warmup Orchestrator
# ============================================================================
# Manages the complete cache warmup lifecycle:
# 1. Checks server load before starting
# 2. Runs per-device warmup (desktop, mobile, tablet)
# 3. Monitors system health during execution
# 4. Generates comprehensive report
# 5. Emails report to webmaster@techno-dz.com
#
# Usage: ./warmup_orchestrator.sh [--mode=full|quick|test]
#   full  - Warm all 3 devices with 3000 URLs (default, ~15-20 min)
#   quick - Warm with 1000 URLs (~5-8 min)
#   test  - Warm with 100 URLs for testing (~1 min)
# ============================================================================

set +e  # Continue on errors

# ─── Configuration ───────────────────────────────────────────────────────────
MAGENTO_DIR="/home/technadminy7/public_html"
DASHBOARD_DIR="/home/dashboard/public_html"
SCRIPT="$DASHBOARD_DIR/scripts/warmup_per_device.php"
LOG_DIR="$DASHBOARD_DIR/logs"
REPORT_EMAIL="webmaster@techno-dz.com"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
LOCK_FILE="/tmp/warmup_orchestrator.lock"
MAX_WAIT=600  # Max seconds to wait for low load (10 min)

# Parse mode
MODE="full"
for arg in "$@"; do
    case $arg in
        --mode=*) MODE="${arg#*=}" ;;
    esac
done

case $MODE in
    full)  MAX_URLS=3000; PARALLEL=8;  MAX_LOAD=12.0; BATCH_DELAY=200000 ;;
    quick) MAX_URLS=1000; PARALLEL=6;  MAX_LOAD=10.0; BATCH_DELAY=150000 ;;
    test)  MAX_URLS=100;  PARALLEL=3;  MAX_LOAD=8.0;  BATCH_DELAY=100000 ;;
    *)     echo "Invalid mode: $MODE. Use: full, quick, or test"; exit 1 ;;
esac

mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/warmup_orchestrator_$(date +%Y%m%d_%H%M%S).log"

# ─── Helpers ─────────────────────────────────────────────────────────────────
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

get_load() {
    uptime | awk -F'load average:' '{print $2}' | awk -F',' '{print $1}' | tr -d ' '
}

send_email() {
    local subject="$1"
    local body_file="$2"
    
    if [ -f "$PHP" ]; then
        php -r "
            \$to = '$REPORT_EMAIL';
            \$subject = '$subject';
            \$body = file_get_contents('$body_file');
            \$headers = 'From: cache-warmup@technostationery.com\r\n' .
                       'Reply-To: $REPORT_EMAIL\r\n' .
                       'X-Mailer: Technostationery Warmup Orchestrator\r\n' .
                       'Content-Type: text/plain; charset=UTF-8\r\n';
            if (mail(\$to, \$subject, \$body, \$headers)) {
                echo 'Email sent to $REPORT_EMAIL';
            } else {
                echo 'Failed to send email';
            }
        " 2>&1 | tee -a "$LOG_FILE"
    else
        # Fallback to mail command
        mail -s "$subject" "$REPORT_EMAIL" < "$body_file" 2>&1 | tee -a "$LOG_FILE"
    fi
}

# ─── Pre-flight Checks ──────────────────────────────────────────────────────
log "================================================================"
log "TECHNOSTATIONERY VARNISH WARMUP ORCHESTRATOR"
log "================================================================"
log "Mode: $MODE (URLs=$MAX_URLS, Parallel=$PARALLEL, MaxLoad=$MAX_LOAD)"
log ""

# Check if already running
if [ -f "$LOCK_FILE" ]; then
    pid=$(cat "$LOCK_FILE" 2>/dev/null)
    if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
        log "ERROR: Another warmup is already running (PID: $pid)"
        log "Aborting. Remove $LOCK_FILE if stale."
        exit 1
    else
        log "WARNING: Stale lock file found (PID: $pid). Removing."
        rm -f "$LOCK_FILE"
    fi
fi

# Check script exists
if [ ! -f "$SCRIPT" ]; then
    log "ERROR: Warmup script not found: $SCRIPT"
    exit 1
fi

# Check sitemap
if [ ! -f "$MAGENTO_DIR/pub/sitemap.xml" ]; then
    log "ERROR: Sitemap not found: $MAGENTO_DIR/pub/sitemap.xml"
    exit 1
fi

# ─── Wait for Low Load ───────────────────────────────────────────────────────
log "Checking server load..."
waited=0
while [ $waited -lt $MAX_WAIT ]; do
    load=$(get_load)
    log "Current load: $load (threshold: $MAX_LOAD, waited: ${waited}s)"
    
    if (( $(echo "$load < $MAX_LOAD" | bc -l 2>/dev/null || echo 1) )); then
        log "Load is acceptable. Starting warmup."
        break
    fi
    
    log "Load too high. Waiting 30s..."
    sleep 30
    waited=$((waited + 30))
done

if [ $waited -ge $MAX_WAIT ]; then
    log "WARNING: Max wait time reached ($MAX_WAIT s). Starting anyway."
fi

# ─── Create Lock File ────────────────────────────────────────────────────────
echo $$ > "$LOCK_FILE"

# ─── Run Warmup ─────────────────────────────────────────────────────────────
log ""
log "Starting warmup..."
START_TIME=$(date +%s)

if [ -f "$PHP" ]; then
    $PHP "$SCRIPT" \
        --urls=$MAX_URLS \
        --parallel=$PARALLEL \
        --max-load=$MAX_LOAD \
        --batch-delay=$BATCH_DELAY 2>&1 | tee -a "$LOG_FILE"
    WARMUP_EXIT=$?
else
    log "ERROR: PHP not found at $PHP"
    WARMUP_EXIT=1
fi

END_TIME=$(date +%s)
TOTAL_TIME=$((END_TIME - START_TIME))

# ─── Cleanup Lock ────────────────────────────────────────────────────────────
rm -f "$LOCK_FILE"

# ─── Post-Warmup Summary ─────────────────────────────────────────────────────
log ""
log "================================================================"
log "ORCHESTRATOR SUMMARY"
log "================================================================"
log "Mode: $MODE"
log "Total Time: ${TOTAL_TIME}s ($((TOTAL_TIME / 60))m $((TOTAL_TIME % 60))s)"
log "Exit Code: $WARMUP_EXIT"
log ""

if [ $WARMUP_EXIT -eq 0 ]; then
    log "Warmup completed successfully."
else
    log "WARNING: Warmup exited with code $WARMUP_EXIT. Check logs for details."
fi

# ─── Find Latest Warmup Log ──────────────────────────────────────────────────
LATEST_WARMUP_LOG=$(ls -t "$LOG_DIR"/warmup_per_device_*.log 2>/dev/null | head -1)
if [ -n "$LATEST_WARMUP_LOG" ]; then
    log "Warmup Log: $LATEST_WARMUP_LOG"
    
    # Send email report
    log ""
    log "Sending email report to $REPORT_EMAIL..."
    send_email \
        "Varnish Cache Warmup Report - $(date '+%Y-%m-%d %H:%M') [Mode: $MODE]" \
        "$LATEST_WARMUP_LOG"
fi

# ─── Varnish Final Stats ────────────────────────────────────────────────────
log ""
log "Final Varnish Statistics:"
varnishstat -1 2>/dev/null | grep -E "MAIN\.(cache_hit|cache_miss|client_req) " | \
    awk '{printf "  %-35s %s\n", $1, $2}' | tee -a "$LOG_FILE"

# ─── Server Load After ───────────────────────────────────────────────────────
final_load=$(get_load)
log ""
log "Server Load (after): $final_load"
log ""
log "Orchestrator Log: $LOG_FILE"
log "Completed: $(date)"

exit $WARMUP_EXIT

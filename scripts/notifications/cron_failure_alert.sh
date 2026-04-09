#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Cron Failure Alert Script
# Purpose: Check for cron job failures and send alerts
# Location: /home/dashboard/public_html/scripts/notifications/cron_failure_alert.sh
# Usage: bash cron_failure_alert.sh --check-period=1h --alert-email=admin@example.com
# ═══════════════════════════════════════════════════════════════════════════

set -e

# Configuration
LOG_DIRS=(
    "/home/technadminy7/public_html/var/log"
    "/home/pim/public_html/var/log"
    "/home/beta/public_html/var/log"
    "/home/dashboard/public_html/var/log"
)

ALERT_EMAIL="admin@technostationery.com"
CHECK_PERIOD="1h"  # Default: check last hour
FAILURE_PATTERNS="ERROR|CRITICAL|FATAL|failed|Failed|FAILURE"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "/home/dashboard/public_html/var/log/cron_failures.log"
}

usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --check-period=PERIOD   Check period (e.g., 1h, 30m, 2h)"
    echo "  --alert-email=EMAIL     Email to send alerts"
    echo "  --log-dir=DIR           Additional log directory to check"
    echo "  --dry-run               Show failures without sending alerts"
    exit 1
}

# Parse arguments
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --check-period=*)
            CHECK_PERIOD="${1#*=}"
            shift
            ;;
        --alert-email=*)
            ALERT_EMAIL="${1#*=}"
            shift
            ;;
        --log-dir=*)
            LOG_DIRS+=("${1#*=}")
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        *)
            usage
            ;;
    esac
done

log_message "Checking for cron failures in the last $CHECK_PERIOD..."

# Convert period to minutes for find command
case "$CHECK_PERIOD" in
    *h)
        MINUTES=$((${CHECK_PERIOD%h} * 60))
        ;;
    *m)
        MINUTES=${CHECK_PERIOD%m}
        ;;
    *)
        MINUTES=60
        ;;
esac

# Find recent log files and check for failures
FAILURES_FOUND=()

for log_dir in "${LOG_DIRS[@]}"; do
    if [ ! -d "$log_dir" ]; then
        continue
    fi
    
    log_message "Checking $log_dir..."
    
    # Find log files modified in the check period
    while IFS= read -r -d '' log_file; do
        if [ ! -f "$log_file" ]; then
            continue
        fi
        
        # Check for failure patterns
        if grep -qE "$FAILURE_PATTERNS" "$log_file" 2>/dev/null; then
            FAILURES_FOUND+=("$log_file")
            log_message "Found failures in: $log_file"
        fi
    done < <(find "$log_dir" -name "*.log" -type f -mmin -"$MINUTES" -print0 2>/dev/null)
done

# Report failures
if [ ${#FAILURES_FOUND[@]} -gt 0 ]; then
    log_message "Found failures in ${#FAILURES_FOUND[@]} log file(s)"
    
    # Build alert body
    ALERT_BODY="Cron Job Failure Detection
==========================

Time: $(date '+%Y-%m-%d %H:%M:%S')
Check Period: Last $CHECK_PERIOD
Files with Failures: ${#FAILURES_FOUND[@]}

Failed Log Files:
"
    
    for log_file in "${FAILURES_FOUND[@]}"; do
        ALERT_BODY="$ALERT_BODY
- $log_file
  Last 10 lines:
$(tail -10 "$log_file" | sed 's/^/  /')
"
    done
    
    ALERT_BODY="$ALERT_BODY

---
Please check the logs and take appropriate action."
    
    if [ "$DRY_RUN" = true ]; then
        log_message "[DRY RUN] Would send alert:"
        echo "$ALERT_BODY"
    else
        # Send alert
        bash /home/dashboard/public_html/scripts/notifications/email_alert.sh \
            --to="$ALERT_EMAIL" \
            --subject="Cron Job Failures Detected" \
            --body="$ALERT_BODY" \
            --severity=critical
    fi
else
    log_message "No failures found"
fi

exit 0

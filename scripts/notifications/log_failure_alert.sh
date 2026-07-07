#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Log Failure Alert Script
# Purpose: Monitor logs for errors and send alerts
# Location: /home/dashboard/public_html/scripts/notifications/log_failure_alert.sh
# Usage: bash log_failure_alert.sh --log-file=/path/to/log --pattern="ERROR"
# ═══════════════════════════════════════════════════════════════════════════

set -e

# Configuration
ALERT_EMAIL="admin@technostationery.com"
DEFAULT_PATTERNS="ERROR|CRITICAL|FATAL|Exception|panic|segfault"
CHECK_INTERVAL=60  # seconds
LOG_FILE=""
PATTERN="$DEFAULT_PATTERNS"
MONITOR_MODE=false

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "/home/dashboard/public_html/var/log/log_monitor.log"
}

usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --log-file=FILE       Log file to monitor"
    echo "  --log-dir=DIR         Directory containing log files"
    echo "  --pattern=PATTERN     Regex pattern to match (default: ERROR|CRITICAL|FATAL)"
    echo "  --alert-email=EMAIL   Email for alerts"
    echo "  --monitor             Continuous monitoring mode"
    echo "  --check-interval=SEC  Check interval in seconds (default: 60)"
    echo "  --dry-run             Show matches without sending alerts"
    exit 1
}

# Parse arguments
LOG_DIR=""
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --log-file=*)
            LOG_FILE="${1#*=}"
            shift
            ;;
        --log-dir=*)
            LOG_DIR="${1#*=}"
            shift
            ;;
        --pattern=*)
            PATTERN="${1#*=}"
            shift
            ;;
        --alert-email=*)
            ALERT_EMAIL="${1#*=}"
            shift
            ;;
        --monitor)
            MONITOR_MODE=true
            shift
            ;;
        --check-interval=*)
            CHECK_INTERVAL="${1#*=}"
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

# Track last checked position
declare -A LAST_POSITION

check_log_file() {
    local file="$1"
    
    if [ ! -f "$file" ]; then
        return
    fi
    
    # Get current file size
    local current_size=$(stat -c%s "$file" 2>/dev/null || echo 0)
    local last_pos="${LAST_POSITION[$file]:-0}"
    
    # If file was truncated or rotated, start from beginning
    if [ "$current_size" -lt "$last_pos" ]; then
        last_pos=0
    fi
    
    # Check new content for patterns
    if [ "$current_size" -gt "$last_pos" ]; then
        local matches=$(tail -c +$((last_pos + 1)) "$file" 2>/dev/null | grep -E "$PATTERN" | tail -20)
        
        if [ -n "$matches" ]; then
            log_message "Found failures in $file"
            
            # Build alert
            local alert_body="Log Failure Alert
====================

Time: $(date '+%Y-%m-%d %H:%M:%S')
File: $file
Pattern: $PATTERN

Matches (last 20):
$matches

---
Please investigate immediately."
            
            if [ "$DRY_RUN" = true ]; then
                log_message "[DRY RUN] Would send alert for $file"
                echo "$alert_body"
            else
                # Send alert
                bash /home/dashboard/public_html/scripts/notifications/email_alert.sh \
                    --to="$ALERT_EMAIL" \
                    --subject="Log Failures Detected in $file" \
                    --body="$alert_body" \
                    --severity=critical
            fi
        fi
        
        # Update position
        LAST_POSITION[$file]=$current_size
    fi
}

# Single file mode
if [ -n "$LOG_FILE" ]; then
    log_message "Monitoring $LOG_FILE for pattern: $PATTERN"
    
    if [ "$MONITOR_MODE" = true ]; then
        # Continuous monitoring
        while true; do
            check_log_file "$LOG_FILE"
            sleep "$CHECK_INTERVAL"
        done
    else
        # Single check
        check_log_file "$LOG_FILE"
    fi
fi

# Directory mode
if [ -n "$LOG_DIR" ]; then
    log_message "Monitoring $LOG_DIR for pattern: $PATTERN"
    
    if [ "$MONITOR_MODE" = true ]; then
        while true; do
            find "$LOG_DIR" -name "*.log" -type f 2>/dev/null | while read -r file; do
                check_log_file "$file"
            done
            sleep "$CHECK_INTERVAL"
        done
    else
        # Single check
        find "$LOG_DIR" -name "*.log" -type f 2>/dev/null | while read -r file; do
            check_log_file "$file"
        done
    fi
fi

if [ -z "$LOG_FILE" ] && [ -z "$LOG_DIR" ]; then
    echo "Error: Must specify --log-file or --log-dir"
    usage
fi

exit 0

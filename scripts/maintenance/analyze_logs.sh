#!/bin/bash
###############################################################################
# Magento Log Analysis Script
# Purpose: Analyze log files for errors, generate daily reports, and alert on issues
# Usage: ./analyze_logs.sh [--report] [--alert] [--watch]
# Schedule: Every 6 hours via cron
###############################################################################

set -e

# Configuration
MAGENTO_ROOT="/home/technadminy7/public_html"
LOG_DIR="$MAGENTO_ROOT/var/log"
REPORT_DIR="$MAGENTO_ROOT/var/reports"
ALERT_EMAIL="technadminy7@example.com"  # Change to your email

# Thresholds
ERROR_THRESHOLD=100
CRITICAL_THRESHOLD=10
REDIS_OOM_THRESHOLD=5
BROKEN_REFERENCE_THRESHOLD=50

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Ensure report directory exists
mkdir -p "$REPORT_DIR"

log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

# Function to count errors in a log file
count_errors() {
    local log_file=$1
    local pattern=$2
    
    if [ -f "$log_file" ]; then
        grep -ci "$pattern" "$log_file" 2>/dev/null || echo 0
    else
        echo 0
    fi
}

# Function to extract recent errors
extract_errors() {
    local log_file=$1
    local pattern=$2
    local limit=${3:-20}
    
    if [ -f "$log_file" ]; then
        grep -i "$pattern" "$log_file" 2>/dev/null | tail -n $limit
    fi
}

# Function to analyze a single log file
analyze_log() {
    local log_file=$1
    local log_name=$(basename "$log_file")
    
    if [ ! -f "$log_file" ]; then
        return
    fi
    
    local size_mb=$(du -m "$log_file" | cut -f1)
    local lines=$(wc -l < "$log_file")
    local errors=$(count_errors "$log_file" "error")
    local critical=$(count_errors "$log_file" "critical")
    local exceptions=$(count_errors "$log_file" "exception")
    local redis_oom=$(count_errors "$log_file" "OOM")
    local broken_ref=$(count_errors "$log_file" "Broken reference")
    
    echo "### $log_name"
    echo "- **Size:** ${size_mb}MB"
    echo "- **Lines:** $lines"
    echo "- **Errors:** $errors"
    echo "- **Critical:** $critical"
    echo "- **Exceptions:** $exceptions"
    echo "- **Redis OOM:** $redis_oom"
    echo "- **Broken References:** $broken_ref"
    echo ""
    
    # Alert on thresholds
    if [ "$errors" -gt "$ERROR_THRESHOLD" ]; then
        log_warn "High error count in $log_name: $errors"
    fi
    
    if [ "$critical" -gt "$CRITICAL_THRESHOLD" ]; then
        log_error "Critical issues in $log_name: $critical"
    fi
    
    if [ "$redis_oom" -gt "$REDIS_OOM_THRESHOLD" ]; then
        log_error "Redis OOM errors detected in $log_name: $redis_oom"
    fi
}

# Function to generate full report
generate_report() {
    local timestamp=$(date '+%Y-%m-%d_%H-%M-%S')
    local report_file="$REPORT_DIR/log_analysis_$timestamp.md"
    
    log_info "Generating log analysis report..."
    
    cat > "$report_file" << EOF
# Magento Log Analysis Report

**Generated:** $(date '+%Y-%m-%d %H:%M:%S')
**Server:** $(hostname)

---

## Executive Summary

EOF

    # Count totals
    local total_errors=0
    local total_critical=0
    local total_redis_oom=0
    
    for log_file in "$LOG_DIR"/*.log; do
        if [ -f "$log_file" ]; then
            total_errors=$((total_errors + $(count_errors "$log_file" "error")))
            total_critical=$((total_critical + $(count_errors "$log_file" "critical")))
            total_redis_oom=$((total_redis_oom + $(count_errors "$log_file" "OOM")))
        fi
    done
    
    cat >> "$report_file" << EOF
| Metric | Count |
|--------|-------|
| Total Errors | $total_errors |
| Critical Issues | $total_critical |
| Redis OOM Errors | $total_redis_oom |

---

## Log File Analysis

EOF

    # Analyze each log file
    for log_file in "$LOG_DIR"/*.log; do
        if [ -f "$log_file" ]; then
            analyze_log "$log_file" >> "$report_file"
        fi
    done
    
    # Add recent critical errors
    cat >> "$report_file" << EOF
---

## Recent Critical Errors (Last 20)

\`\`\`
EOF
    
    for log_file in "$LOG_DIR"/*.log; do
        if [ -f "$log_file" ]; then
            extract_errors "$log_file" "critical" 5 >> "$report_file" 2>/dev/null || true
        fi
    done
    
    cat >> "$report_file" << EOF
\`\`\`

---

## Redis OOM Errors (Last 20)

\`\`\`
EOF
    
    for log_file in "$LOG_DIR"/*.log; do
        if [ -f "$log_file" ]; then
            extract_errors "$log_file" "OOM" 5 >> "$report_file" 2>/dev/null || true
        fi
    done
    
    cat >> "$report_file" << EOF
\`\`\`

---

## Recommendations

EOF

    # Generate recommendations
    if [ "$total_redis_oom" -gt "$REDIS_OOM_THRESHOLD" ]; then
        echo "### 🔴 CRITICAL: Redis Memory Issues Detected" >> "$report_file"
        echo "- Increase Redis maxmemory setting" >> "$report_file"
        echo "- Run Redis memory cleanup script" >> "$report_file"
        echo "- Consider flushing stale cache keys" >> "$report_file"
        echo "" >> "$report_file"
    fi
    
    if [ "$total_errors" -gt "$ERROR_THRESHOLD" ]; then
        echo "### 🟡 WARNING: High Error Count" >> "$report_file"
        echo "- Review exception.log for stack traces" >> "$report_file"
        echo "- Check system.log for recurring issues" >> "$report_file"
        echo "- Consider enabling debug mode for more details" >> "$report_file"
        echo "" >> "$report_file"
    fi
    
    echo "---" >> "$report_file"
    echo "*Report generated by analyze_logs.sh*" >> "$report_file"
    
    log_info "Report saved to: $report_file"
    echo "$report_file"
}

# Function to send alerts
send_alerts() {
    local report_file=$1
    
    # Check for critical issues
    local total_redis_oom=0
    for log_file in "$LOG_DIR"/*.log; do
        if [ -f "$log_file" ]; then
            total_redis_oom=$((total_redis_oom + $(count_errors "$log_file" "OOM")))
        fi
    done
    
    if [ "$total_redis_oom" -gt "$REDIS_OOM_THRESHOLD" ]; then
        log_error "ALERT: Redis OOM errors exceed threshold ($total_redis_oom)"
        # Send email alert if mail is configured
        if command -v mail &> /dev/null; then
            echo "Redis OOM errors detected: $total_redis_oom. Check report: $report_file" | mail -s "[ALERT] Magento Redis OOM" "$ALERT_EMAIL"
        fi
    fi
}

# Function to watch logs in real-time
watch_logs() {
    log_info "Watching logs for errors (Ctrl+C to stop)..."
    
    tail -f "$LOG_DIR"/*.log 2>/dev/null | grep -iE "error|critical|exception" --line-buffered
}

# Main execution
case "${1:-report}" in
    --report|-r)
        generate_report
        ;;
    --alert|-a)
        report_file=$(generate_report)
        send_alerts "$report_file"
        ;;
    --watch|-w)
        watch_logs
        ;;
    --summary|-s)
        log_info "=== Log Summary ==="
        for log_file in "$LOG_DIR"/*.log; do
            if [ -f "$log_file" ]; then
                size=$(du -h "$log_file" | cut -f1)
                errors=$(count_errors "$log_file" "error")
                echo "$(basename $log_file): ${size}, Errors: $errors"
            fi
        done
        ;;
    *)
        echo "Usage: $0 [--report|--alert|--watch|--summary]"
        echo ""
        echo "Options:"
        echo "  --report, -r    Generate full analysis report"
        echo "  --alert, -a     Generate report and send alerts"
        echo "  --watch, -w     Watch logs in real-time for errors"
        echo "  --summary, -s   Show brief summary of all logs"
        exit 1
        ;;
esac

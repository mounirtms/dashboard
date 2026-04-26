#!/bin/bash
###############################################################################
# Lighthouse Performance Audit Script
# Date: April 26, 2026
# Purpose: Automated Lighthouse testing for technostationery.com
###############################################################################

set -euo pipefail

# Configuration
SITE_URL="https://technostationery.com"
REPORT_DIR="/home/technadminy7/public_html/lighthouse-reports"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BASELINE_FILE="$REPORT_DIR/baseline_lighthouse.json"
LATEST_FILE="$REPORT_DIR/latest_lighthouse_$TIMESTAMP.json"
HTML_REPORT="$REPORT_DIR/lighthouse_report_$TIMESTAMP.html"
LOG_FILE="/home/technadminy7/public_html/logs/lighthouse_audit.log"

# Create directories
mkdir -p "$REPORT_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "========================================="
log "Starting Lighthouse Audit"
log "Site: $SITE_URL"
log "========================================="

# Check if Lighthouse is installed
if ! command -v lighthouse &> /dev/null; then
    log "ERROR: Lighthouse not installed. Installing..."
    npm install -g lighthouse @lhci/cli
    if [ $? -ne 0 ]; then
        log "ERROR: Failed to install Lighthouse"
        exit 1
    fi
    log "SUCCESS: Lighthouse installed"
fi

# Clear browser cache (if using Chrome)
log "Clearing Chrome cache..."
rm -rf ~/.config/google-chrome/Default/Cache/* 2>/dev/null || true
rm -rf ~/.cache/google-chrome/* 2>/dev/null || true

# Warm up the site (3 requests)
log "Warming up site..."
for i in {1..3}; do
    curl -s -o /dev/null -w "Request $i: %{http_code} - %{time_total}s\n" "$SITE_URL" | tee -a "$LOG_FILE"
    sleep 2
done

# Run Lighthouse audit
log "Running Lighthouse audit (this may take 1-2 minutes)..."
lighthouse "$SITE_URL" \
    --output=json \
    --output=html \
    --output-path="$REPORT_DIR/lighthouse_report_$TIMESTAMP" \
    --chrome-flags="--headless --no-sandbox --disable-dev-shm-usage --disable-gpu" \
    --only-categories=performance,accessibility,best-practices,seo \
    --quiet 2>&1 | tee -a "$LOG_FILE"

LIGHTHOUSE_EXIT=$?

if [ $LIGHTHOUSE_EXIT -ne 0 ]; then
    log "WARNING: Lighthouse audit encountered issues (exit code: $LIGHTHOUSE_EXIT)"
fi

# Check if report was generated
if [ -f "${REPORT_DIR}/lighthouse_report_${TIMESTAMP}.report.json" ]; then
    log "SUCCESS: Lighthouse report generated"
    
    # Extract key metrics using jq
    REPORT_FILE="${REPORT_DIR}/lighthouse_report_${TIMESTAMP}.report.json"
    
    log "========================================="
    log "LIGHTHOUSE AUDIT RESULTS"
    log "========================================="
    
    # Extract scores
    PERFORMANCE=$(jq -r '.categories.performance.score * 100' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    ACCESSIBILITY=$(jq -r '.categories.accessibility.score * 100' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    BEST_PRACTICES=$(jq -r '.categories["best-practices"].score * 100' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    SEO=$(jq -r '.categories.seo.score * 100' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    
    log "Performance Score: $PERFORMANCE"
    log "Accessibility Score: $ACCESSIBILITY"
    log "Best Practices Score: $BEST_PRACTICES"
    log "SEO Score: $SEO"
    
    log "========================================="
    log "CORE WEB VITALS"
    log "========================================="
    
    # Extract Core Web Vitals
    FCP=$(jq -r '.audits["first-contentful-paint"].displayValue' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    LCP=$(jq -r '.audits["largest-contentful-paint"].displayValue' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    TBT=$(jq -r '.audits["total-blocking-time"].displayValue' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    CLS=$(jq -r '.audits["cumulative-layout-shift"].displayValue' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    SI=$(jq -r '.audits["speed-index"].displayValue' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    TTI=$(jq -r '.audits["interactive"].displayValue' "$REPORT_FILE" 2>/dev/null || echo "N/A")
    
    log "First Contentful Paint (FCP): $FCP"
    log "Largest Contentful Paint (LCP): $LCP"
    log "Total Blocking Time (TBT): $TBT"
    log "Cumulative Layout Shift (CLS): $CLS"
    log "Speed Index (SI): $SI"
    log "Time to Interactive (TTI): $TTI"
    
    log "========================================="
    log "OPPORTUNITIES FOR IMPROVEMENT"
    log "========================================="
    
    # Extract top opportunities
    jq -r '.audits | to_entries[] | select(.value.score < 0.9 and .value.score != null) | "\(.key): \(.value.title) - Score: \(.value.score * 100)%"' "$REPORT_FILE" 2>/dev/null | head -10 | tee -a "$LOG_FILE" || log "No opportunities data available"
    
    # Save as latest
    cp "$REPORT_FILE" "$LATEST_FILE"
    
    # Save as baseline if it doesn't exist
    if [ ! -f "$BASELINE_FILE" ]; then
        log "Saving as baseline report"
        cp "$REPORT_FILE" "$BASELINE_FILE"
    else
        # Compare with baseline
        log "========================================="
        log "COMPARISON WITH BASELINE"
        log "========================================="
        
        BASELINE_PERF=$(jq -r '.categories.performance.score * 100' "$BASELINE_FILE" 2>/dev/null || echo "0")
        DIFF=$(echo "$PERFORMANCE - $BASELINE_PERF" | bc 2>/dev/null || echo "0")
        
        log "Baseline Performance: $BASELINE_PERF"
        log "Current Performance: $PERFORMANCE"
        log "Difference: $DIFF points"
        
        if (( $(echo "$DIFF > 5" | bc -l 2>/dev/null || echo "0") )); then
            log "🎉 IMPROVEMENT: Performance improved by $DIFF points!"
        elif (( $(echo "$DIFF < -5" | bc -l 2>/dev/null || echo "0") )); then
            log "⚠️  REGRESSION: Performance dropped by ${DIFF#-} points!"
        else
            log "📊 STABLE: Performance change within acceptable range"
        fi
    fi
    
    # HTML report location
    if [ -f "${REPORT_DIR}/lighthouse_report_${TIMESTAMP}.report.html" ]; then
        log "========================================="
        log "HTML Report: ${REPORT_DIR}/lighthouse_report_${TIMESTAMP}.report.html"
        log "JSON Report: $REPORT_FILE"
        log "========================================="
    fi
    
else
    log "ERROR: Lighthouse report not generated"
    exit 1
fi

# Clean up old reports (keep last 10)
log "Cleaning up old reports..."
cd "$REPORT_DIR"
ls -t lighthouse_report_*.report.json 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
ls -t lighthouse_report_*.report.html 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true

log "========================================="
log "Lighthouse audit completed successfully"
log "========================================="

exit 0

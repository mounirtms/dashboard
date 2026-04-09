#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Build All Projects Script
# Purpose: Build all projects with comprehensive logging
# Location: /home/dashboard/public_html/scripts/build/build-all.sh
# Usage: bash build-all.sh --environment=production --log-file=/path/to/log
# ═══════════════════════════════════════════════════════════════════════════

set -e

# ═══════════════════════════════════════════════════════════════════════════
# Configuration
# ═══════════════════════════════════════════════════════════════════════════

SCRIPT_NAME="build-all.sh"
LOG_DIR="/home/dashboard/public_html/var/log"
DEFAULT_LOG_FILE="$LOG_DIR/build.log"
ALERT_EMAIL="admin@technostationery.com"

# Project configurations
declare -A PROJECT_PATHS=(
    ["dashboard"]="/home/dashboard/public_html/webapp"
    ["magento"]="/home/technadminy7/public_html"
    ["pim"]="/home/pim/public_html"
)

declare -A PROJECT_BUILD_CMDS=(
    ["dashboard"]="npm run build"
    ["magento"]="php bin/magento setup:static-content:deploy -f"
    ["pim"]="php bin/console pim:installer:assets --symlink --clean --env=prod"
)

# ═══════════════════════════════════════════════════════════════════════════
# Functions
# ═══════════════════════════════════════════════════════════════════════════

log_message() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] [$SCRIPT_NAME] $message" | tee -a "$LOG_FILE"
}

send_alert() {
    local subject="$1"
    local body="$2"
    local severity="${3:-warning}"
    
    echo "$body" | mail -s "[$severity] $subject" "$ALERT_EMAIL" 2>/dev/null || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$severity] $subject: $body" >> "$LOG_DIR/alerts.log"
}

usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --environment=ENV     Target environment (default: all)"
    echo "  --project=PROJECT     Build specific project only"
    echo "  --log-file=FILE       Log file path (default: $DEFAULT_LOG_FILE)"
    echo "  --verbose             Enable verbose output"
    echo "  --dry-run             Show what would be done"
    echo ""
    echo "Examples:"
    echo "  $0                              # Build all projects"
    echo "  $0 --project=dashboard          # Build only dashboard"
    echo "  $0 --log-file=/tmp/build.log    # Custom log file"
    exit 1
}

# ═══════════════════════════════════════════════════════════════════════════
# Parse Arguments
# ═══════════════════════════════════════════════════════════════════════════

ENVIRONMENT="all"
PROJECT=""
LOG_FILE="$DEFAULT_LOG_FILE"
VERBOSE=false
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --environment=*)
            ENVIRONMENT="${1#*=}"
            shift
            ;;
        --project=*)
            PROJECT="${1#*=}"
            shift
            ;;
        --log-file=*)
            LOG_FILE="${1#*=}"
            shift
            ;;
        --verbose)
            VERBOSE=true
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

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# ═══════════════════════════════════════════════════════════════════════════
# Build Functions
# ═══════════════════════════════════════════════════════════════════════════

build_dashboard() {
    local path="${PROJECT_PATHS[dashboard]}"
    local cmd="${PROJECT_BUILD_CMDS[dashboard]}"
    
    log_message "INFO" "Building Dashboard..."
    log_message "INFO" "Path: $path"
    log_message "INFO" "Command: $cmd"
    
    if [ "$DRY_RUN" = true ]; then
        log_message "INFO" "[DRY RUN] Would execute: $cmd"
        return 0
    fi
    
    cd "$path"
    
    # Install dependencies
    log_message "INFO" "Installing dependencies..."
    npm ci --production 2>&1 | tee -a "$LOG_FILE"
    
    # Run build
    log_message "INFO" "Running build..."
    START_TIME=$(date +%s)
    
    if $VERBOSE; then
        eval "$cmd" 2>&1 | tee -a "$LOG_FILE"
    else
        eval "$cmd" >> "$LOG_FILE" 2>&1
    fi
    
    BUILD_STATUS=$?
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    if [ $BUILD_STATUS -ne 0 ]; then
        log_message "ERROR" "Dashboard build failed (took ${DURATION}s)"
        send_alert "Build Failed" "Dashboard build failed" "critical"
        return 1
    fi
    
    log_message "INFO" "Dashboard build completed successfully (took ${DURATION}s)"
    return 0
}

build_magento() {
    local path="${PROJECT_PATHS[magento]}"
    local cmd="${PROJECT_BUILD_CMDS[magento]}"
    local user="technadminy7"
    
    log_message "INFO" "Building Magento..."
    log_message "INFO" "Path: $path"
    log_message "INFO" "Command: $cmd"
    
    if [ "$DRY_RUN" = true ]; then
        log_message "INFO" "[DRY RUN] Would execute: su - $user -c \"$cmd\""
        return 0
    fi
    
    START_TIME=$(date +%s)
    
    # Run as magento user
    su - "$user" -c "cd $path && $cmd" 2>&1 | tee -a "$LOG_FILE"
    
    BUILD_STATUS=$?
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    if [ $BUILD_STATUS -ne 0 ]; then
        log_message "ERROR" "Magento build failed (took ${DURATION}s)"
        send_alert "Build Failed" "Magento build failed" "critical"
        return 1
    fi
    
    log_message "INFO" "Magento build completed successfully (took ${DURATION}s)"
    return 0
}

build_pim() {
    local path="${PROJECT_PATHS[pim]}"
    local cmd="${PROJECT_BUILD_CMDS[pim]}"
    local user="pim"
    
    log_message "INFO" "Building PIM..."
    log_message "INFO" "Path: $path"
    log_message "INFO" "Command: $cmd"
    
    if [ "$DRY_RUN" = true ]; then
        log_message "INFO" "[DRY RUN] Would execute: su - $user -c \"$cmd\""
        return 0
    fi
    
    START_TIME=$(date +%s)
    
    # Run as pim user
    su - "$user" -c "cd $path && $cmd" 2>&1 | tee -a "$LOG_FILE"
    
    BUILD_STATUS=$?
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    if [ $BUILD_STATUS -ne 0 ]; then
        log_message "ERROR" "PIM build failed (took ${DURATION}s)"
        send_alert "Build Failed" "PIM build failed" "critical"
        return 1
    fi
    
    log_message "INFO" "PIM build completed successfully (took ${DURATION}s)"
    return 0
}

# ═══════════════════════════════════════════════════════════════════════════
# Main Build Process
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "=========================================="
log_message "INFO" "Build Started"
log_message "INFO" "=========================================="
log_message "INFO" "Environment: $ENVIRONMENT"
log_message "INFO" "Project: ${PROJECT:-all}"
log_message "INFO" "Log File: $LOG_FILE"

if [ "$DRY_RUN" = true ]; then
    log_message "INFO" "DRY RUN MODE"
fi

TOTAL_START=$(date +%s)
FAILED=0

# Build specific project or all
if [ -n "$PROJECT" ]; then
    case "$PROJECT" in
        dashboard)
            build_dashboard || ((FAILED++))
            ;;
        magento)
            build_magento || ((FAILED++))
            ;;
        pim)
            build_pim || ((FAILED++))
            ;;
        *)
            log_message "ERROR" "Unknown project: $PROJECT"
            exit 1
            ;;
    esac
else
    build_dashboard || ((FAILED++))
    build_magento || ((FAILED++))
    build_pim || ((FAILED++))
fi

TOTAL_END=$(date +%s)
TOTAL_DURATION=$((TOTAL_END - TOTAL_START))

# ═══════════════════════════════════════════════════════════════════════════
# Summary
# ═══════════════════════════════════════════════════════════════════════════

log_message "INFO" "=========================================="
log_message "INFO" "Build Summary"
log_message "INFO" "=========================================="
log_message "INFO" "Total Duration: ${TOTAL_DURATION}s"

if [ $FAILED -gt 0 ]; then
    log_message "ERROR" "FAILED: $FAILED project(s) failed"
    send_alert "Build Summary" "$FAILED project(s) failed to build" "critical"
    exit 1
else
    log_message "INFO" "SUCCESS: All projects built successfully"
    send_alert "Build Summary" "All projects built successfully in ${TOTAL_DURATION}s" "info"
    exit 0
fi
